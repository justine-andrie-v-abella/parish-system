<?php
/**
 * includes/calendar.php
 * Shared month-calendar rendering so the dashboard's initial load and the
 * AJAX month-navigation fragment (calendar-fragment.php) stay in sync.
 */

function calendar_day_status(string $date, array $dayAgg, array $holidays, int $capacity, string $today): array {
    if (isset($holidays[$date])) {
        return ['status-holiday', $holidays[$date]];
    }
    if (!isset($dayAgg[$date])) {
        return $date < $today ? ['', 'No activity'] : ['status-available', 'Available'];
    }
    $row = $dayAgg[$date];
    if ((int) $row['total'] >= $capacity) {
        return ['status-full', 'Fully booked'];
    }
    if ((int) $row['confirmed_count'] === 0) {
        return ['status-pending', 'Pending approval'];
    }
    return ['status-reserved', 'Reserved'];
}

/** Renders the calendar's inner HTML (head + legend + grid) for a given month/year. */
function render_calendar_fragment(PDO $pdo, int $month, int $year): string {
    $month = max(1, min(12, $month));
    $firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
    $daysInMonth  = (int) date('t', strtotime($firstOfMonth));
    $startWeekday = (int) date('w', strtotime($firstOfMonth));
    $today        = date('Y-m-d');
    $lastOfMonth  = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

    $prevMonth = $month === 1 ? 12 : $month - 1;
    $prevYear  = $month === 1 ? $year - 1 : $year;
    $nextMonth = $month === 12 ? 1 : $month + 1;
    $nextYear  = $month === 12 ? $year + 1 : $year;

    $aggStmt = $pdo->prepare(
        "SELECT appointment_date,
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('confirmed','approved','completed') THEN 1 ELSE 0 END) AS confirmed_count
         FROM appointments
         WHERE appointment_date BETWEEN ? AND ? AND status NOT IN ('cancelled','rejected')
         GROUP BY appointment_date"
    );
    $aggStmt->execute([$firstOfMonth, $lastOfMonth]);
    $dayAgg = [];
    foreach ($aggStmt->fetchAll() as $row) {
        $dayAgg[$row['appointment_date']] = $row;
    }

    $holidayStmt = $pdo->prepare('SELECT holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ?');
    $holidayStmt->execute([$firstOfMonth, $lastOfMonth]);
    $holidays = [];
    foreach ($holidayStmt->fetchAll() as $row) {
        $holidays[$row['holiday_date']] = $row['name'];
    }

    $CAPACITY = 3;

    ob_start();
    ?>
    <div class="cal-head">
      <span class="cal-title"><?php echo date('F Y', strtotime($firstOfMonth)); ?></span>
      <div class="cal-nav">
        <a href="#" class="cal-nav-link" data-month="<?php echo $prevMonth; ?>" data-year="<?php echo $prevYear; ?>" aria-label="Previous month">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <a href="#" class="cal-nav-link" data-month="<?php echo $nextMonth; ?>" data-year="<?php echo $nextYear; ?>" aria-label="Next month">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
        </a>
      </div>
    </div>

    <div class="cal-legend">
      <span><i style="background:#3F9A5C;"></i> Available</span>
      <span><i style="background:#3E5AA8;"></i> Reserved</span>
      <span><i style="background:#D8A227;"></i> Pending</span>
      <span><i style="background:#9A958A;"></i> Holiday</span>
      <span><i style="background:#C0483A;"></i> Full</span>
    </div>

    <div class="cal-grid">
      <?php foreach (['Su','Mo','Tu','We','Th','Fr','Sa'] as $dow): ?>
        <div class="cal-dow"><?php echo $dow; ?></div>
      <?php endforeach; ?>

      <?php for ($i = 0; $i < $startWeekday; $i++): ?>
        <div class="cal-cell empty"></div>
      <?php endfor; ?>

      <?php for ($d = 1; $d <= $daysInMonth; $d++):
          $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
          [$statusClass, $statusLabel] = calendar_day_status($date, $dayAgg, $holidays, $CAPACITY, $today);
          $isToday = $date === $today;
      ?>
        <div class="cal-cell <?php echo $statusClass; ?><?php echo $isToday ? ' today' : ''; ?>" title="<?php echo htmlspecialchars($statusLabel); ?>">
          <span><?php echo $d; ?></span>
          <?php if ($statusClass): ?><span class="dot"></span><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
    <?php
    return ob_get_clean();
}