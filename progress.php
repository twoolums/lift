<?php
require __DIR__ . '/inc/bootstrap.php';
$sw = require_swimmer();

$eid = isset($_GET['e']) ? (int)$_GET['e'] : 0;

// Is value `a` (val, reps) better than `b`? Heavier wins; ties go to more reps.
function better($type, $a, $b)
{
    if ($b === null) {
        return true;
    }
    if ($type === 'reps') {
        return (int)$a['reps'] > (int)$b['reps'];
    }
    if ((float)$a['val'] > (float)$b['val']) {
        return true;
    }
    return ((float)$a['val'] == (float)$b['val']) && ((int)$a['reps'] > (int)$b['reps']);
}

function set_text($type, $r, $label)
{
    if ($type === 'wr') {
        return num($r['val']) . ' ' . $label . ' x ' . num($r['reps']);
    }
    if ($type === 'reps') {
        return num($r['reps']) . ' reps';
    }
    return num($r['val']) . ' sec';
}

if ($eid > 0) {
    $ex = fetch_one('SELECT * FROM exercises WHERE id = ?', array($eid));
    if (!$ex) {
        redirect('progress.php');
    }
    $type = $ex['input_type'];
    $label = $ex['value_label'] !== '' ? $ex['value_label'] : 'lb';
    $rows = fetch_all(
        'SELECT l.workout_id, w.workout_date, w.title, l.set_no, l.val, l.reps
         FROM set_logs l JOIN workouts w ON w.id = l.workout_id
         WHERE l.swimmer_id = ? AND l.exercise_id = ?
         ORDER BY w.workout_date, l.workout_id, l.set_no',
        array($sw['id'], $eid)
    );
    $sessions = array();
    foreach ($rows as $r) {
        $k = (int)$r['workout_id'];
        if (!isset($sessions[$k])) {
            $sessions[$k] = array('date' => $r['workout_date'], 'title' => $r['title'], 'sets' => array(), 'top' => null);
        }
        $sessions[$k]['sets'][] = $r;
        if (better($type, $r, $sessions[$k]['top'])) {
            $sessions[$k]['top'] = $r;
        }
    }
    $best = null;
    $bestKey = null;
    foreach ($sessions as $k => $s) {
        if ($s['top'] && better($type, $s['top'], $best)) {
            $best = $s['top'];
            $bestKey = $k;
        }
    }
    $points = array();
    foreach ($sessions as $s) {
        if (!$s['top']) {
            continue;
        }
        $y = ($type === 'reps') ? (float)$s['top']['reps'] : (float)$s['top']['val'];
        $points[] = array('label' => fmt_date($s['date'], 'n/j'), 'y' => $y);
    }

    page_header($ex['name'], array('nav' => 'swimmer'));
    ?>
    <p><a href="<?= h(url('progress.php')) ?>">&larr; All exercises</a></p>
    <h1><?= h($ex['name']) ?></h1>
    <?php if ($best): ?>
      <div class="card best">
        <span class="muted small">Personal best</span>
        <div class="best-val"><?= h(set_text($type, $best, $label)) ?></div>
      </div>
    <?php endif; ?>
    <div class="card">
      <h3 class="small-h"><?= $type === 'reps' ? 'Top reps' : ($type === 'sec' ? 'Top hold' : 'Top ' . h($label)) ?> by session</h3>
      <div class="chart" data-points="<?= h(json_encode($points)) ?>"></div>
    </div>
    <?php foreach (array_reverse($sessions, true) as $k => $s): ?>
      <div class="card">
        <div class="row-between">
          <strong><?= h(fmt_date($s['date'], 'D, M j')) ?></strong>
          <?php if ($k === $bestKey): ?><span class="pill pill-done">PR</span><?php endif; ?>
        </div>
        <div class="muted small"><?= h($s['title']) ?></div>
        <ol class="setlist">
          <?php foreach ($s['sets'] as $r): ?>
            <li><?= h(set_text($type, $r, $label)) ?></li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>
    <?php
    page_footer(array('nav' => 'swimmer'));
    exit;
}

// List of exercises this swimmer has logged
$list = fetch_all(
    'SELECT e.id, e.name, e.input_type, e.value_label,
            COUNT(DISTINCT l.workout_id) AS n_sessions,
            MAX(l.val) AS best_val, MAX(l.reps) AS best_reps, MAX(w.workout_date) AS last_date
     FROM set_logs l
     JOIN exercises e ON e.id = l.exercise_id
     JOIN workouts w ON w.id = l.workout_id
     WHERE l.swimmer_id = ?
     GROUP BY e.id, e.name, e.input_type, e.value_label
     ORDER BY e.name',
    array($sw['id'])
);

page_header('My Progress', array('nav' => 'swimmer'));
?>
<h1>My Progress</h1>
<?php if (!$list): ?>
  <div class="card"><p>Log your first session and your lifts will show up here.</p></div>
<?php endif; ?>
<div class="wklist">
<?php foreach ($list as $e):
    $label = $e['value_label'] !== '' ? $e['value_label'] : 'lb';
    if ($e['input_type'] === 'reps') {
        $bestTxt = 'Best: ' . (int)$e['best_reps'] . ' reps';
    } elseif ($e['input_type'] === 'sec') {
        $bestTxt = 'Best: ' . num($e['best_val']) . ' sec';
    } else {
        $bestTxt = 'Best: ' . num($e['best_val']) . ' ' . $label;
    }
?>
  <a class="wk" href="<?= h(url('progress.php?e=' . (int)$e['id'])) ?>">
    <span class="wk-main">
      <span class="wk-title"><?= h($e['name']) ?></span>
      <span class="wk-sub"><?= h($bestTxt) ?> &middot; <?= (int)$e['n_sessions'] ?> session<?= (int)$e['n_sessions'] === 1 ? '' : 's' ?></span>
    </span>
    <span class="pill">&rsaquo;</span>
  </a>
<?php endforeach; ?>
</div>
<?php page_footer(array('nav' => 'swimmer')); ?>
