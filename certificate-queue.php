<?php
// certificate-queue.php
require_once 'includes/config.php';
require_role(['secretary', 'priest']);
require_once 'includes/db.php';

$sid = (int) $_SESSION['user_id'];
$serviceNames = array_column($services, 'name', 'key');

$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$notifStmt->execute([$sid]);
$notifications = $notifStmt->fetchAll();
$unreadCount = count(array_filter($notifications, fn($n) => !is_true($n['is_read'])));

ob_start();
?>
<div class="tab-panel-wrap">
  <?php if (empty($notifications)): ?>
    <p class="upcoming-empty">You're all caught up.</p>
  <?php else: ?>
    <?php foreach ($notifications as $n): ?>
      <div class="notif-item<?php echo is_true($n['is_read']) ? '' : ' unread'; ?>">
        <span class="notif-dot"></span>
        <div>
          <p><?php echo htmlspecialchars(preg_replace('/^DEMO:\s*/', '', $n['message'])); ?></p>
          <span class="time"><?php echo date('M j, g:i A', strtotime($n['created_at'])); ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php
$notifPanelHtml = ob_get_clean();

// ---------------- Check the certificate-requests migration has been applied ----------------
$certReady = $pdo->query("SELECT to_regclass('public.certificate_requests')")->fetchColumn() !== null;

$filterLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected', 'all' => 'All'];
$allowedFilters = array_keys($filterLabels);
$filter = in_array($_GET['status'] ?? 'pending', $allowedFilters, true) ? ($_GET['status'] ?? 'pending') : 'pending';
$q = trim($_GET['q'] ?? '');

$rows = [];
if ($certReady) {
    $where = '1=1';
    $params = [];
    if ($filter !== 'all') {
        $where .= ' AND c.status = ?';
        $params[] = $filter;
    }
    if ($q !== '') {
        $where .= ' AND (u.full_name LIKE ? OR c.service_key LIKE ? OR c.requestor_name LIKE ? OR c.field_values::text LIKE ?)';
        $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
    }

    $stmt = $pdo->prepare(
        "SELECT c.*, u.full_name, u.email FROM certificate_requests c
         JOIN users u ON u.id = c.user_id
         WHERE $where
         ORDER BY c.created_at DESC LIMIT 300"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

$page_title = 'Certificate Requests — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<style>
.queue-toolbar{ display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
.queue-search{ display:flex; gap:8px; }
.queue-search input{ border:1px solid var(--line); border-radius:999px; padding:9px 16px; font-size:13px; font-family:inherit; width:220px; background:var(--white); }
.queue-search input:focus{ outline:none; border-color:var(--gold); }

.qmodal-overlay{ position:fixed; inset:0; background:rgba(11,20,36,0.55); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; pointer-events:none; transition:opacity .2s; }
.qmodal-overlay.open{ opacity:1; pointer-events:auto; }
.qmodal-box{ background:var(--cream); border-radius:20px; border:1px solid var(--line); width:420px; max-width:100%; padding:24px; max-height:88vh; overflow-y:auto; }
.qmodal-box h3{ font-size:18px; margin-bottom:10px; }
.qmodal-box textarea{ width:100%; border:1px solid var(--line); border-radius:12px; padding:11px 14px; font-family:inherit; font-size:13.5px; min-height:80px; resize:vertical; }
.qmodal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
.qmodal-error{ font-size:12px; color:#A2432F; margin-top:6px; display:none; }
.qmodal-error.show{ display:block; }
</style>

<div class="page-head">
  <span class="eyebrow"><?php echo ucfirst($_SESSION['role']); ?></span>
  <h1>Certificate Requests</h1>
  <p>Review incoming certificate requests, approve or reject them, ask for documents, and release completed certificates.</p>
</div>

<?php if (!$certReady): ?>
  <div class="panel" style="margin-bottom:28px; border-color: var(--gold);">
    <h3>Certificate requests migration not applied yet</h3>
    <p style="font-size:13.5px; color:var(--ink-soft);">
      Import <code>database/migration_add_certificate_requests.sql</code> in the Supabase SQL editor.
    </p>
  </div>
<?php else: ?>

<div class="queue-toolbar">
  <form class="queue-search" method="get">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search parishioner or registrant…">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
  </form>

  <div class="actions-dropdown">
    <button type="button" class="actions-trigger" aria-haspopup="true" aria-expanded="false">
      Filter: <strong><?php echo htmlspecialchars($filterLabels[$filter] ?? ucfirst($filter)); ?></strong>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="actions-menu">
      <?php foreach ($filterLabels as $key => $label): ?>
        <a href="?status=<?php echo $key; ?>&q=<?php echo urlencode($q); ?>"<?php echo $filter === $key ? ' class="active"' : ''; ?>><?php echo $label; ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="requests-table-wrap">
  <?php if (empty($rows)): ?>
    <div class="requests-empty">No requests in this view.</div>
  <?php else: ?>
    <table class="requests-table">
      <thead>
        <tr><th>Request</th><th>Parishioner</th><th>Type</th><th>Details</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $fieldValues = json_decode($r['field_values'] ?? '{}', true) ?: [];
          $detailParts = [];
          foreach ($fieldValues as $label => $value) {
              if ($value !== '' && $value !== null) $detailParts[] = $label . ': ' . $value;
          }
        ?>
          <tr>
            <td>#<?php echo $r['id']; ?></td>
            <td><?php echo htmlspecialchars($r['full_name']); ?></td>
            <td class="svc-cell"><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
            <td><?php echo $detailParts ? htmlspecialchars(implode(' · ', $detailParts)) : '<span style="color:var(--ink-soft);">&mdash;</span>'; ?></td>
            <td><span class="pay-status-chip <?php echo $r['payment_status']; ?>"><?php echo $r['payment_status'] === 'unpaid' ? 'Pending Verification' : ucfirst($r['payment_status']); ?></span></td>
            <td>
              <span class="status-pill <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span>
              <?php if (in_array($r['status'], ['rejected'], true) && $r['status_reason']): ?>
                <div style="font-size:11px; color:var(--ink-soft); margin-top:4px; max-width:160px;"><?php echo htmlspecialchars($r['status_reason']); ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if (in_array($r['status'], ['pending', 'approved'], true)): ?>
                <div class="actions-dropdown">
                  <button type="button" class="actions-trigger" aria-haspopup="true" aria-expanded="false">
                    Actions
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                  </button>
                  <div class="actions-menu">
                    <?php if ($r['status'] === 'pending'): ?>
                      <?php if ($r['payment_status'] === 'paid'): ?>
                        <button type="button" class="approve-btn" data-approve-id="<?php echo $r['id']; ?>">Approve</button>
                      <?php else: ?>
                        <button type="button" class="approve-btn" disabled title="Payment must be verified by the treasurer first">Approve</button>
                      <?php endif; ?>
                      <button type="button" class="reject-btn" data-reject-id="<?php echo $r['id']; ?>">Reject</button>
                      <button type="button" class="docs-btn" data-docs-id="<?php echo $r['id']; ?>">Request Docs</button>
                    <?php else: ?>
                      <?php if ($r['payment_status'] === 'paid'): ?>
                        <button type="button" class="complete-btn" data-complete-id="<?php echo $r['id']; ?>">Mark Completed</button>
                      <?php else: ?>
                        <button type="button" class="complete-btn" disabled title="Payment must be verified first">Mark Completed</button>
                      <?php endif; ?>
                      <button type="button" class="reject-btn" data-reject-id="<?php echo $r['id']; ?>">Reject</button>
                      <button type="button" class="docs-btn" data-docs-id="<?php echo $r['id']; ?>">Request Docs</button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php else: ?>
                <span style="font-size:11px; color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Reject modal -->
<div class="qmodal-overlay" id="rejectModal">
  <div class="qmodal-box">
    <h3>Reject this request</h3>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:10px;">This notifies the parishioner with your reason.</p>
    <textarea id="rejectReason" placeholder="e.g. Record not found, incomplete requirements…"></textarea>
    <p class="qmodal-error" id="rejectError"></p>
    <div class="qmodal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="rejectCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="rejectConfirm">Reject</button>
    </div>
  </div>
</div>

<!-- Request documents modal -->
<div class="qmodal-overlay" id="docsModal">
  <div class="qmodal-box">
    <h3>Request more documents</h3>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:10px;">Documents are brought in person — this just sends a reminder of what's needed.</p>
    <textarea id="docsMessage" placeholder="e.g. Valid ID of requestor…"></textarea>
    <p class="qmodal-error" id="docsError"></p>
    <div class="qmodal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="docsCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="docsConfirm">Send Reminder</button>
    </div>
  </div>
</div>

<script src="assets/js/certificate-queue.js"></script>

<?php endif; ?>

<?php require_once 'includes/dashboard-footer.php'; ?>
