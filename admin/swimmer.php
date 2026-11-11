<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sw = fetch_one('SELECT * FROM swimmers WHERE id = ?', array($id));
if (!$sw) {
    redirect('admin/index.php');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'rename') {
        $first = trim(isset($_POST['first_name']) ? $_POST['first_name'] : '');
        $last = trim(isset($_POST['last_name']) ? $_POST['last_name'] : '');
        if ($first !== '') {
            q('UPDATE swimmers SET first_name = ?, last_name = ? WHERE id = ?', array(substr($first, 0, 60), substr($last, 0, 60), $id));
            $msg = 'Name updated.';
        }
    } elseif ($action === 'reset_pin') {
        $pin = set_swimmer_pin($id);
        $_SESSION['new_codes'] = array(array('name' => swimmer_full($sw), 'pin' => $pin));
        redirect('admin/print.php');
    } elseif ($action === 'unlock') {
        q('UPDATE swimmers SET failed_pins = 0, locked_until = NULL WHERE id = ?', array($id));
        $msg = 'Unlocked. They can try their PIN again.';
    } elseif ($action === 'toggle_active') {
        $new = $sw['active'] ? 0 : 1;
        q('UPDATE swimmers SET active = ? WHERE id = ?', array($new, $id));
        if (!$new) {
            q('DELETE FROM device_tokens WHERE swimmer_id = ?', array($id));
        }
        $msg = $new ? 'Swimmer re-activated.' : 'Swimmer deactivated. They can no longer log in, but their data is kept.';
    } elseif ($action === 'delete') {
        q('DELETE FROM swimmers WHERE id = ?', array($id));
        redirect('admin/index.php');
    }
    $sw = fetch_one('SELECT * FROM swimmers WHERE id = ?', array($id));
}

$workouts = fetch_all('SELECT * FROM workouts ORDER BY workout_date, id');
$counts = array();
foreach (fetch_all('SELECT workout_id, COUNT(*) AS c FROM set_logs WHERE swimmer_id = ? GROUP BY workout_id', array($id)) as $r) {
    $counts[(int)$r['workout_id']] = (int)$r['c'];
}
$bests = fetch_all(
    'SELECT e.name, e.input_type, e.value_label, MAX(l.val) AS best_val, MAX(l.reps) AS best_reps,
            COUNT(DISTINCT l.workout_id) AS n_sessions
     FROM set_logs l JOIN exercises e ON e.id = l.exercise_id
     WHERE l.swimmer_id = ?
     GROUP BY e.id, e.name, e.input_type, e.value_label
     ORDER BY e.name',
    array($id)
);
$today = date('Y-m-d');
$series = swimmer_series($id);
$participation = swimmer_participation($id);

page_header(swimmer_full($sw), array('nav' => 'coach'));
?>
<p><a href="<?= h(url('admin/index.php')) ?>">&larr; Team</a></p>
<h1><?= h(swimmer_full($sw)) ?><?= $sw['active'] ? '' : ' <span class="muted">(deactivated)</span>' ?></h1>
<?php if ($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>

<div class="grid2">
  <div class="card">
    <h2 class="small-h">Name</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rename">
      <input class="text-input" name="first_name" value="<?= h($sw['first_name']) ?>" required placeholder="First name">
      <input class="text-input" name="last_name" value="<?= h($sw['last_name']) ?>" placeholder="Last name">
      <button class="btn" type="submit">Save name</button>
    </form>
  </div>
  <div class="card">
    <h2 class="small-h">Login</h2>
    <p class="muted small">Last login: <?= $sw['last_login'] ? h(fmt_date($sw['last_login'], 'M j, g:ia')) : 'never' ?></p>
    <?php if ($sw['pin_hash'] === null): ?><p><span class="pill pill-missed">no PIN yet</span></p><?php endif; ?>
    <?php if (!empty($sw['locked_until']) && $sw['locked_until'] > now()): ?>
      <p><span class="pill pill-missed">locked until <?= h(fmt_date($sw['locked_until'], 'g:ia')) ?></span></p>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="unlock">
        <button class="btn" type="submit">Unlock now</button>
      </form>
    <?php endif; ?>
    <form method="post" class="stack" data-confirm="Make a new PIN? The old PIN stops working and the swimmer is logged out on all phones.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_pin">
      <button class="btn" type="submit"><?= $sw['pin_hash'] === null ? 'Create PIN (shows a printable card)' : 'Reset PIN (shows a new printable card)' ?></button>
    </form>
    <form method="post" class="stack" data-confirm="<?= $sw['active'] ? 'Deactivate this swimmer? They will be logged out. Their data is kept.' : 'Re-activate this swimmer?' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_active">
      <button class="btn" type="submit"><?= $sw['active'] ? 'Deactivate' : 'Re-activate' ?></button>
    </form>
    <form method="post" class="stack" data-confirm="Permanently delete this swimmer and ALL of their logged lifts? This cannot be undone.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <button class="btn btn-danger" type="submit">Delete swimmer + data</button>
    </form>
  </div>
</div>

<h2 class="section">Sessions (tap to view or edit their entries)</h2>
<div class="wklist">
<?php foreach ($workouts as $w):
    $wid = (int)$w['id'];
    $c = isset($counts[$wid]) ? $counts[$wid] : 0;
    $exp = expected_sets($w);
    if ($c > 0 && $c >= $exp) { $cls = 'done'; $txt = 'Done'; }
    elseif ($c > 0) { $cls = 'prog'; $txt = $c . ' of ' . $exp . ' sets'; }
    elseif ($w['workout_date'] < $today) { $cls = 'missed'; $txt = 'Not logged'; }
    else { $cls = 'todo'; $txt = 'Upcoming'; }
?>
  <a class="wk wk-<?= $cls ?>" href="<?= h(url('workout.php?id=' . $wid . '&swimmer=' . $id)) ?>">
    <span class="chip"><span class="chip-m"><?= h(fmt_date($w['workout_date'], 'M')) ?></span><span class="chip-d"><?= h(fmt_date($w['workout_date'], 'j')) ?></span></span>
    <span class="wk-main"><span class="wk-title"><?= h($w['title']) ?></span><span class="wk-sub"><?= (int)$w['length_min'] ?> min</span></span>
    <span class="pill pill-<?= $cls ?>"><?= h($txt) ?></span>
  </a>
<?php endforeach; ?>
</div>

<h2 class="section">Best lifts</h2>
<div class="tablewrap">
<table class="list">
  <thead><tr><th>Exercise</th><th>Best</th><th>Sessions</th></tr></thead>
  <tbody>
  <?php foreach ($bests as $b):
      $label = $b['value_label'] !== '' ? $b['value_label'] : 'lb';
      if ($b['input_type'] === 'reps') { $t = (int)$b['best_reps'] . ' reps'; }
      elseif ($b['input_type'] === 'sec') { $t = num($b['best_val']) . ' sec'; }
      else { $t = num($b['best_val']) . ' ' . $label; }
  ?>
    <tr><td><?= h($b['name']) ?></td><td><?= h($t) ?></td><td><?= (int)$b['n_sessions'] ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$bests): ?><tr><td colspan="3" class="muted">Nothing logged yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<h2 class="section">Progress charts</h2>
<div class="card">
  <h3 class="small-h">Attendance: % of planned sets logged per session</h3>
  <div class="bars" data-bars="<?= h(json_encode($participation)) ?>"></div>
</div>
<?php
$shown = 0;
$hidden = 0;
foreach ($series as $e) {
    if ($e['n'] >= 2) { $shown++; } else { $hidden++; }
}
?>
<?php if ($shown): ?>
<p class="muted small">Top set per session. Charts appear once an exercise has been logged in 2 or more sessions.</p>
<div class="grid2">
  <?php foreach ($series as $e): if ($e['n'] < 2) { continue; } ?>
    <div class="card">
      <div class="row-between">
        <h3 class="small-h"><?= h($e['name']) ?></h3>
        <span class="muted small"><?= h(num($e['first'])) ?> &rarr; <?= h(num($e['latest'])) ?> <?= h($e['unit']) ?></span>
      </div>
      <div class="chart" data-points="<?= h(json_encode($e['points'])) ?>"></div>
    </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <p class="muted">No exercise has been logged in 2 or more sessions yet.</p>
<?php endif; ?>
<?php if ($hidden): ?><p class="muted small"><?= (int)$hidden ?> exercise<?= $hidden === 1 ? '' : 's' ?> logged only once so far.</p><?php endif; ?>

<p><a class="btn" href="<?= h(url('admin/export.php?swimmer=' . $id)) ?>">Download this swimmer's data (CSV)</a></p>
<?php page_footer(array('nav' => 'coach')); ?>
