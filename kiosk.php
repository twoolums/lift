<?php
require __DIR__ . '/inc/bootstrap.php';

if (!current_kiosk()) {
    http_response_code(403);
    page_header('Kiosk', array('topbar' => false, 'bodyclass' => 'login'));
    echo '<div class="login-card"><h1>Not set up</h1>';
    echo '<p>This device is not set up for weight-room entry. A coach needs to log in on this device and open <strong>Kiosk</strong> in the coach menu.</p>';
    echo '<p class="muted small"><a href="' . h(url('index.php')) . '">Swimmer login</a> &middot; <a href="' . h(url('admin/login.php')) . '">Coach login</a></p></div>';
    page_footer();
    exit;
}

$workouts = fetch_all('SELECT * FROM workouts ORDER BY workout_date, id');
$today = date('Y-m-d');

// Which session? Use ?w=, else today's, else the next upcoming, else the most recent.
$sel = null;
$wantId = isset($_GET['w']) ? (int)$_GET['w'] : 0;
foreach ($workouts as $w) {
    if ((int)$w['id'] === $wantId) {
        $sel = $w;
    }
}
if (!$sel) {
    foreach ($workouts as $w) {
        if ($w['workout_date'] === $today) {
            $sel = $w;
            break;
        }
    }
}
if (!$sel) {
    foreach ($workouts as $w) {
        if ($w['workout_date'] >= $today) {
            $sel = $w;
            break;
        }
    }
}
if (!$sel && $workouts) {
    $sel = $workouts[count($workouts) - 1];
}

$swimmers = fetch_all('SELECT id, first_name, last_name FROM swimmers WHERE active = 1 ORDER BY first_name, last_name');
$labels = swimmer_labels($swimmers, true);

$doneName = '';
if (isset($_GET['done'])) {
    $did = (int)$_GET['done'];
    foreach ($swimmers as $s) {
        if ((int)$s['id'] === $did) {
            $doneName = $s['first_name'];
        }
    }
}

page_header('Weight room', array('topbar' => false, 'main' => false, 'bodyclass' => 'kiosk'));
?>
<div class="wrap kiosk-wrap">
  <h1 class="kiosk-h"><?= h(TEAM_NAME) ?> Weight Room</h1>

  <?php if ($doneName !== ''): ?>
    <div class="alert alert-ok big">&#10003; Saved. Nice work, <?= h($doneName) ?>! Next athlete up.</div>
  <?php endif; ?>

  <?php if (!$sel): ?>
    <div class="card"><p>No sessions are set up yet. A coach can add them under Sessions.</p></div>
  <?php else: ?>
    <form method="get" class="kiosk-session">
      <span class="muted small">Session</span>
      <select name="w" class="text-input" data-autosubmit>
        <?php foreach ($workouts as $w): ?>
          <option value="<?= (int)$w['id'] ?>"<?= (int)$w['id'] === (int)$sel['id'] ? ' selected' : '' ?>><?= h(fmt_date($w['workout_date'], 'D M j')) ?> - <?= h($w['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-small" type="submit">Change</button></noscript>
    </form>

    <form method="get" action="<?= h(url('workout.php')) ?>" class="kiosk-pick">
      <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
      <input type="hidden" name="kiosk" value="1">
      <label for="who" class="kiosk-label">Who's lifting?</label>
      <select id="who" name="swimmer" class="text-input kiosk-select" data-autosubmit required>
        <option value="">Choose your name...</option>
        <?php foreach ($swimmers as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= h($labels[$s['id']]) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-primary btn-block" type="submit">Start</button></noscript>
    </form>
    <p class="muted small center">Tap your name, enter your sets, then tap <strong>Done</strong> so the next swimmer can go.</p>
  <?php endif; ?>
</div>
<?php page_footer(array('main' => false)); ?>
