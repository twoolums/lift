<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

// Stop spreadsheet programs from running text that starts with = + - @ as a formula
function csv_safe($v)
{
    $v = (string)$v;
    if ($v !== '' && strpos('=+-@', $v[0]) !== false) {
        return "'" . $v;
    }
    return $v;
}

$swimmerId = isset($_GET['swimmer']) ? (int)$_GET['swimmer'] : 0;

$sql = 'SELECT s.first_name, s.last_name, w.workout_date, w.title, e.name AS exercise, e.input_type, e.value_label,
               l.set_no, l.val, l.reps, l.logged_at
        FROM set_logs l
        JOIN swimmers s ON s.id = l.swimmer_id
        JOIN workouts w ON w.id = l.workout_id
        JOIN exercises e ON e.id = l.exercise_id';
$params = array();
if ($swimmerId > 0) {
    $sql .= ' WHERE l.swimmer_id = ?';
    $params[] = $swimmerId;
}
$sql .= ' ORDER BY s.first_name, s.last_name, w.workout_date, e.name, l.set_no';

$rows = fetch_all($sql, $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="lift-log-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, array('First name', 'Last name', 'Date', 'Session', 'Exercise', 'Set', 'Value', 'Value unit', 'Reps', 'Logged at'), ',', '"', '\\');
foreach ($rows as $r) {
    $unit = $r['input_type'] === 'sec' ? 'sec' : ($r['input_type'] === 'reps' ? '' : $r['value_label']);
    fputcsv($out, array(
        csv_safe($r['first_name']),
        csv_safe($r['last_name']),
        $r['workout_date'],
        csv_safe($r['title']),
        csv_safe($r['exercise']),
        $r['set_no'],
        $r['val'] === null ? '' : num($r['val']),
        $unit,
        $r['reps'] === null ? '' : $r['reps'],
        $r['logged_at'],
    ), ',', '"', '\\');
}
fclose($out);
