<?php
//parish-system\ajax\save-service.php
require_once '../includes/config.php';
require_role(['priest', 'secretary']);
require_once '../includes/db.php';
require_once '../includes/logs.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$id           = (int) ($_POST['id'] ?? 0);
$key          = strtolower(trim($_POST['key'] ?? ''));
$name         = trim($_POST['name'] ?? '');
$description  = trim($_POST['description'] ?? '');
$fee          = $_POST['fee'] ?? '';
$icon         = trim($_POST['icon'] ?? '');
$requirements = trim($_POST['requirements'] ?? '');
$feesInput    = trim($_POST['fees'] ?? '');
$category     = trim($_POST['category'] ?? 'sacrament');
$certFieldsInput = trim($_POST['cert_fields'] ?? '');

$allowedIcons = ['dove', 'flame', 'rings', 'cross', 'candle', 'vessel'];
$allowedCategories = ['sacrament', 'certificate'];

if (!in_array($category, $allowedCategories, true)) {
    $category = 'sacrament';
}

// The `category` column only exists once migration_add_certificate_requests.sql
// has been run.
$hasCategory = $pdo->query(
    "SELECT 1 FROM information_schema.columns WHERE table_name = 'services' AND column_name = 'category'"
)->fetchColumn() !== false;

if ($name === '' || $description === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please fill in the name and description.']);
    exit;
}
if (!is_numeric($fee) || (int) $fee < 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Fee must be a number, 0 or more.']);
    exit;
}
if (!in_array($icon, $allowedIcons, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose an icon.']);
    exit;
}
$fee = (int) $fee;

// Each non-blank line is "Label | Amount | Note" (note optional).
$feeLines = array_values(array_filter(array_map('trim', explode("\n", $feesInput)), fn($l) => $l !== ''));
$parsedFees = [];
foreach ($feeLines as $line) {
    $parts = array_map('trim', explode('|', $line));
    $label = $parts[0] ?? '';
    $amount = $parts[1] ?? '';
    $note = $parts[2] ?? '';
    if ($label === '' || !is_numeric($amount) || (int) $amount < 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Each fee line needs a label and a numeric amount, e.g. "Sponsors | 100".']);
        exit;
    }
    $parsedFees[] = ['label' => $label, 'amount' => (int) $amount, 'note' => $note !== '' ? $note : null];
}

try {
    $pdo->beginTransaction();

    if ($id > 0) {
        // Editing an existing service — the key never changes (appointments
        // reference it by key, so changing it would orphan historical data).
        $existing = $pdo->prepare('SELECT service_key FROM services WHERE id = ?');
        $existing->execute([$id]);
        $row = $existing->fetch();
        if (!$row) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Service not found.']);
            exit;
        }
        $key = $row['service_key'];

        if ($hasCategory) {
            $update = $pdo->prepare('UPDATE services SET icon = ?, name = ?, description = ?, fee = ?, category = ? WHERE id = ?');
            $update->execute([$icon, $name, $description, $fee, $category, $id]);
        } else {
            $update = $pdo->prepare('UPDATE services SET icon = ?, name = ?, description = ?, fee = ? WHERE id = ?');
            $update->execute([$icon, $name, $description, $fee, $id]);
        }
        $logAction = 'service_updated';
        $logMsg = $_SESSION['full_name'] . " updated the {$name} service.";
    } else {
        // New service — validate + uniqueness-check the key.
        if ($key === '' || !preg_match('/^[a-z0-9_]+$/', $key)) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['error' => 'Service key must be lowercase letters, numbers, and underscores only.']);
            exit;
        }
        $dupe = $pdo->prepare('SELECT id FROM services WHERE service_key = ?');
        $dupe->execute([$key]);
        if ($dupe->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'That service key is already in use. Choose a different one.']);
            exit;
        }

        $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM services')->fetchColumn();

        if ($hasCategory) {
            $insert = $pdo->prepare(
                'INSERT INTO services (service_key, icon, name, description, fee, category, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([$key, $icon, $name, $description, $fee, $category, $maxOrder + 1]);
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO services (service_key, icon, name, description, fee, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([$key, $icon, $name, $description, $fee, $maxOrder + 1]);
        }
        $id = (int) $pdo->lastInsertId();
        $logAction = 'service_created';
        $logMsg = $_SESSION['full_name'] . " added a new service: {$name}.";
    }

    // Replace the requirements list wholesale (simplest, matches the
    // one-line-per-requirement textarea UI).
    $delReq = $pdo->prepare('DELETE FROM service_requirements WHERE service_key = ?');
    $delReq->execute([$key]);

    $lines = array_values(array_filter(array_map('trim', explode("\n", $requirements)), fn($l) => $l !== ''));
    if ($lines) {
        $insReq = $pdo->prepare('INSERT INTO service_requirements (service_key, requirement_text, sort_order) VALUES (?, ?, ?)');
        foreach ($lines as $i => $line) {
            $insReq->execute([$key, $line, $i + 1]);
        }
    }

    // Itemized fees — only touch this table if the migration has been applied.
    if ($pdo->query("SELECT to_regclass('public.service_fees')")->fetchColumn() !== null) {
        $delFee = $pdo->prepare('DELETE FROM service_fees WHERE service_key = ?');
        $delFee->execute([$key]);

        if ($parsedFees) {
            $insFee = $pdo->prepare('INSERT INTO service_fees (service_key, label, amount, note, sort_order) VALUES (?, ?, ?, ?, ?)');
            foreach ($parsedFees as $i => $f) {
                $insFee->execute([$key, $f['label'], $f['amount'], $f['note'], $i + 1]);
            }
        }
    }

    // Certificate request form fields — only touch this table if the
    // migration has been applied.
    if ($pdo->query("SELECT to_regclass('public.service_form_fields')")->fetchColumn() !== null) {
        $delFields = $pdo->prepare('DELETE FROM service_form_fields WHERE service_key = ?');
        $delFields->execute([$key]);

        $fieldLines = array_values(array_filter(array_map('trim', explode("\n", $certFieldsInput)), fn($l) => $l !== ''));
        if ($fieldLines) {
            $insField = $pdo->prepare('INSERT INTO service_form_fields (service_key, field_label, sort_order) VALUES (?, ?, ?)');
            foreach ($fieldLines as $i => $line) {
                $insField->execute([$key, $line, $i + 1]);
            }
        }
    }

    $pdo->commit();

    log_activity($pdo, (int) $_SESSION['user_id'], $logAction, $logMsg, 'service', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong saving this service. Please try again.']);
}