<?php
/**
 * calendar-fragment.php
 * Returns just the calendar's inner HTML (no page chrome) for the given
 * ?month=&year=. Called via fetch() from the header dropdown so month
 * navigation doesn't reload the page or close the dropdown.
 */
require_once 'includes/config.php';
require_role(['parishioner', 'priest', 'secretary', 'treasurer']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';

$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');

header('Content-Type: text/html; charset=UTF-8');
echo render_calendar_fragment($pdo, $month, $year);