<?php
require __DIR__ . '/inc/bootstrap.php';

// Three ways to open this page:
//  - a swimmer logged in on their own phone (default)
//  - a coach editing for a swimmer (?swimmer=ID)
//  - an authorized weight-room kiosk (?swimmer=ID&kiosk=1)
$coach = current_coach();
$kiosk = current_kiosk();
$swimmerParam = isset($_GET['swimmer']) ? (int)$_GET['swimmer'] : 0;
$mode = 'self';
if ($swimmerParam > 0 && !empty($_GET['kiosk']) && $kiosk) {
    $sw = fetch_one('SELECT * FROM swimmers WHERE id = ? AND active = 1', array($swimmerParam));
    if (!$sw) {
        redirect('kiosk.php');
    }
    $mode = 'kiosk';
} elseif ($swimmerParam > 0 && $coach) {
    $sw = fetch_one('SELECT * FROM swimmers WHERE id = ?', array($swimmerParam));
    if (!$sw) {
        http_response_code(404);
        exit('Swimmer not found');
    }
    $mode = 'coach';
} else {
    $sw = require_swimmer();
}
$coachMode = ($mode === 'coach');
$kioskMode = ($mode === 'kiosk');

$wid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$w = fetch_one('SELECT * FROM workouts WHERE id = ?', array($wid));
if (!$w) {
    http_response_code(404);
    exit('Session not found');
}

$items = items_for_workout($w);

// Sets already saved for this session
$logs = array();
$rows = fetch_all(
    'SELECT exercise_id, set_no, val, reps FROM set_logs WHERE swimmer_id = ? AND workout_id = ?',
    array($sw['id'], $w['id'])
);
foreach ($rows as $r) {
    $logs[(int)$r['exercise_id']][(int)$r['set_no']] = $r;
}

// What this swimmer did last time for each exercise (used for placeholders and "fill from last time")
$last = array();
$prev = fetch_all(
    'SELECT l.exercise_id, l.workout_id, l.set_no, l.val, l.reps
     FROM set_logs l JOIN workouts x ON x.id = l.workout_id
     WHERE l.swimmer_id = ? AND l.workout_id <> ? AND x.workout_date < ?
     ORDER BY x.workout_date DESC, l.workout_id DESC, l.set_no ASC',
    array($sw['id'], $w['id'], $w['workout_date'])
);
foreach ($prev as $r) {
    $e = (int)$r['exercise_id'];
    if (!isset($last[$e])) {
        $last[$e] = array('wid' => (int)$r['workout_id'], 'sets' => array());
    }
    if ($last[$e]['wid'] === (int)$r['workout_id']) {
        $last[$e]['sets'][(int)$r['set_no']] = $r;
    }
}

function stepper_html($field, $value, $last, $step, $label, $mode, $start)
{
    $s = '<div class="field"><span class="flabel">' . h($label) . '</span><div class="stepper">';
    $s .= '<button type="button" class="step minus" aria-label="Decrease ' . h($label) . '">&minus;</button>';
    $s .= '<input class="inp" type="number" inputmode="' . h($mode) . '" step="any" min="0" data-field="' . h($field)
        . '" data-step="' . h($step) . '" data-start="' . h($start) . '"';
    if ($last !== '') {
        $s .= ' data-last="' . h($last) . '" placeholder="' . h($last) . '"';
    }
    $s .= ' value="' . h($value) . '">';
    $s .= '<button type="button" class="step plus" aria-label="Increase ' . h($label) . '">+</button>';
    $s .= '</div></div>';
    return $s;
}

function last_summary($type, $sets)
{
    $parts = array();
    foreach ($sets as $r) {
        if ($type === 'wr') {
            $parts[] = num($r['val']) . '&times;' . num($r['reps']);
        } elseif ($type === 'reps') {
            $parts[] = num($r['reps']);
        } else {
            $parts[] = num($r['val']) . 's';
        }
    }
    return implode(', ', $parts);
}

if ($kioskMode) {
    $backUrl = url('kiosk.php?w=' . (int)$w['id']);
    $doneUrl = url('kiosk.php?w=' . (int)$w['id'] . '&done=' . (int)$sw['id']);
} elseif ($coachMode) {
    $backUrl = url('admin/swimmer.php?id=' . (int)$sw['id']);
    $doneUrl = $backUrl;
} else {
    $backUrl = url('home.php');
    $doneUrl = $backUrl;
}
$totalExpected = expected_sets($w);

page_header($w['title'], array(
    'nav' => 'none',
    'topbar' => false,
    'main' => false,
    'bodyclass' => 'workout',
    'body' => array(
        'data-page' => 'workout',
        'data-api' => url('api.php'),
        'data-csrf' => csrf_token(),
        'data-workout' => (int)$w['id'],
        'data-swimmer' => (int)$sw['id'],
        'data-idle-url' => $kioskMode ? $backUrl : '',
    ),
));
?>
<div class="wbar">
  <a class="wback" href="<?= h($backUrl) ?>" aria-label="Back">&larr;</a>
  <div class="wtitle">
    <strong><?= h($w['title']) ?></strong>
    <span><?= h(fmt_date($w['workout_date'], 'D, M j')) ?><?= (int)$w['phase'] > 0 ? ' &middot; Phase ' . (int)$w['phase'] : '' ?></span>
  </div>
  <span id="save-status" class="save-status" role="status" aria-live="polite">Saved</span>
</div>

<div class="wrap">
<?php if ($coachMode): ?>
  <div class="alert alert-info">Coach view: you are editing <strong><?= h(swimmer_full($sw)) ?></strong>'s entries.</div>
<?php endif; ?>
<?php if ($kioskMode): ?>
  <div class="alert alert-info big">Logging for <strong><?= h(swimmer_full($sw)) ?></strong>. Not you? <a href="<?= h($backUrl) ?>">Pick a different name</a>.</div>
<?php endif; ?>
<?php if ($w['notes'] !== ''): ?>
  <div class="note"><?= h($w['notes']) ?></div>
<?php endif; ?>
<?php if ((int)$w['length_min'] < 60 && $w['day_type'] !== 'P'): ?>
  <p class="muted small">This is a <?= (int)$w['length_min'] ?>-minute session, so the shorter version is shown.</p>
<?php endif; ?>

<?php if (!$items): ?>
  <div class="card"><p>No exercises are set up for this session yet.</p></div>
<?php endif; ?>

<?php foreach ($items as $it):
    $eid = (int)$it['exercise_id'];
    $type = $it['input_type'];
    $nSets = (int)$it['sets'];
    if (!empty($logs[$eid])) {
        $nSets = max($nSets, max(array_keys($logs[$eid])));
    }
    $lastSets = isset($last[$eid]) ? $last[$eid]['sets'] : array();
    $startReps = preg_match('/\d+/', $it['reps_text'], $m) ? $m[0] : '0';
    $valLabel = $it['value_label'] !== '' ? $it['value_label'] : ($type === 'sec' ? 'sec' : 'lb');
    $needVal = ($type === 'wr' || $type === 'sec');
    $needReps = ($type === 'wr' || $type === 'reps');
?>
  <section class="card ex" data-exercise="<?= $eid ?>">
    <div class="ex-head">
      <span class="ex-label"><?= h($it['label']) ?></span>
      <div class="ex-name">
        <h3><?= h($it['name']) ?><?= (int)$it['starred'] === 1 ? ' <span class="muted">*</span>' : '' ?></h3>
        <p class="rx"><?= (int)$it['sets'] ?> &times; <?= h($it['reps_text']) ?><?= $it['rest_text'] !== '' && $it['rest_text'] !== '-' ? ' &middot; rest ' . h($it['rest_text']) : '' ?></p>
      </div>
    </div>
    <?php if ($it['cue'] !== '' || ($it['link_url'] !== '' && !$kioskMode)): ?>
      <details class="cue">
        <summary>How to do it</summary>
        <?php if ($it['cue'] !== ''): ?><p><?= h($it['cue']) ?></p><?php endif; ?>
        <?php if ($it['link_url'] !== '' && !$kioskMode): ?><p><a class="btn btn-small" href="<?= h($it['link_url']) ?>" target="_blank" rel="noopener noreferrer">Watch / read &#8599;</a></p><?php endif; ?>
      </details>
    <?php endif; ?>

    <?php if ($lastSets): ?>
      <div class="lastrow">
        <span class="muted small">Last time: <?= last_summary($type, $lastSets) ?></span>
        <button type="button" class="btn btn-small fill-last">Fill from last time</button>
      </div>
    <?php endif; ?>

    <div class="sets">
    <?php for ($i = 1; $i <= $nSets; $i++):
        $cur = isset($logs[$eid][$i]) ? $logs[$eid][$i] : null;
        $ref = null;
        if (isset($lastSets[$i])) {
            $ref = $lastSets[$i];
        } elseif ($lastSets) {
            $ref = end($lastSets);
        }
        $curVal = ($cur && $cur['val'] !== null) ? num($cur['val']) : '';
        $curReps = ($cur && $cur['reps'] !== null) ? (string)(int)$cur['reps'] : '';
        $lastVal = ($ref && $ref['val'] !== null) ? num($ref['val']) : '';
        $lastReps = ($ref && $ref['reps'] !== null) ? (string)(int)$ref['reps'] : '';
        $saved = $cur && (!$needVal || $cur['val'] !== null) && (!$needReps || $cur['reps'] !== null);
    ?>
      <div class="setrow<?= $saved ? ' saved' : '' ?>" data-exercise="<?= $eid ?>" data-set="<?= $i ?>" data-type="<?= h($type) ?>">
        <div class="setno"><span>Set <span class="setn"><?= $i ?></span></span><span class="tick" aria-hidden="true">&#10003;</span></div>
        <div class="fields">
          <?php if ($needVal): ?>
            <?= stepper_html('val', $curVal, $lastVal, num($it['step']), $valLabel, 'decimal', '0') ?>
          <?php endif; ?>
          <?php if ($needReps): ?>
            <?= stepper_html('reps', $curReps, $lastReps, '1', 'reps', 'numeric', $startReps) ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endfor; ?>
    </div>
    <button type="button" class="btn btn-small add-set">+ Add a set</button>
  </section>
<?php endforeach; ?>

<?php if ($items): ?>
  <p class="muted small center">Everything saves automatically. <?= (int)$totalExpected ?> sets planned today.</p>
<?php endif; ?>
<?php if ($kioskMode): ?>
  <p class="center"><a class="btn btn-primary btn-xl done-next" href="<?= h($doneUrl) ?>">Done &mdash; next athlete &rarr;</a></p>
<?php else: ?>
  <p class="center"><a class="btn btn-primary" href="<?= h($doneUrl) ?>"><?= $coachMode ? 'Back to swimmer' : 'Back to sessions' ?></a></p>
<?php endif; ?>
</div>
<?php page_footer(array('main' => false)); ?>
