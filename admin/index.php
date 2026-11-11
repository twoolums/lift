<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

$msg = '';
// ---- Add swimmers (one per line: "First Last") ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_swimmers') {
    csrf_check();
    $lines = preg_split('/\r\n|\r|\n/', isset($_POST['names']) ? $_POST['names'] : '');
    $new = array();
    foreach ($lines as $line) {
        $line = trim(preg_replace('/\s+/', ' ', $line));
        if ($line === '') {
            continue;
        }
        $parts = explode(' ', $line, 2);
        $first = substr($parts[0], 0, 60);
        $last = isset($parts[1]) ? substr($parts[1], 0, 60) : '';
        $pin = generate_pin();
        q(
            'INSERT INTO swimmers (first_name, last_name, pin_hash, active, created_at) VALUES (?, ?, ?, 1, ?)',
            array($first, $last, password_hash($pin, PASSWORD_DEFAULT), now())
        );
        $new[] = array('name' => trim($first . ' ' . $last), 'pin' => $pin);
    }
    if ($new) {
        $_SESSION['new_codes'] = $new;
        redirect('admin/print.php');
    }
    $msg = 'Type at least one name.';
}

// ---- Create PINs for swimmers who don't have one yet (for example, after upgrading) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'make_pins') {
    csrf_check();
    $new = array();
    foreach (fetch_all('SELECT * FROM swimmers WHERE pin_hash IS NULL AND active = 1 ORDER BY first_name, last_name') as $row) {
        $new[] = array('name' => swimmer_full($row), 'pin' => set_swimmer_pin((int)$row['id']));
    }
    if ($new) {
        $_SESSION['new_codes'] = $new;
        redirect('admin/print.php');
    }
    $msg = 'Everyone already has a PIN.';
}

$swimmers = fetch_all('SELECT * FROM swimmers ORDER BY active DESC, first_name, last_name');
$needPin = 0;
foreach ($swimmers as $row) {
    if ($row['active'] && $row['pin_hash'] === null) {
        $needPin++;
    }
}
$workouts = fetch_all('SELECT * FROM workouts ORDER BY workout_date, id');

// Sets logged per swimmer per session
$matrix = array();
$totals = array();
foreach (fetch_all('SELECT swimmer_id, workout_id, COUNT(*) AS c FROM set_logs GROUP BY swimmer_id, workout_id') as $r) {
    $matrix[(int)$r['swimmer_id']][(int)$r['workout_id']] = (int)$r['c'];
}
$expected = array();
foreach ($workouts as $w) {
    $expected[(int)$w['id']] = expected_sets($w);
}
$today = date('Y-m-d');

page_header('Team', array('nav' => 'coach'));
?>
<h1>Team</h1>
<?php if ($msg): ?><div class="alert"><?= h($msg) ?></div><?php endif; ?>

<?php if ($needPin > 0): ?>
  <div class="alert alert-info">
    <strong><?= (int)$needPin ?> swimmer<?= $needPin === 1 ? '' : 's' ?> still need a PIN</strong> before they can log in.
    <form method="post" class="inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="make_pins">
      <button class="btn btn-small" type="submit">Create PINs for swimmers who need one</button>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h2 class="small-h">Add swimmers</h2>
  <p class="muted small">One name per line (first and last). Each swimmer gets a private 4-digit PIN you can print and hand out.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_swimmers">
    <textarea name="names" class="text-input" rows="4" placeholder="Jake Miller&#10;Sam Lopez"></textarea>
    <button class="btn btn-primary" type="submit">Create swimmers + PINs</button>
  </form>
</div>

<h2 class="section">Who has logged what</h2>
<p class="muted small">&#10003; = most of the planned sets logged &middot; &#9679; = some sets &middot; &ndash; = nothing logged (only for dates that have passed).</p>
<div class="tablewrap">
<table class="grid">
  <thead>
    <tr>
      <th class="sticky">Swimmer</th>
      <?php foreach ($workouts as $w): ?>
        <th title="<?= h($w['title']) ?>"><?= h(fmt_date($w['workout_date'], 'n/j')) ?><br><span class="muted"><?= h($w['day_type']) ?></span></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($swimmers as $s): ?>
    <tr class="<?= $s['active'] ? '' : 'inactive' ?>">
      <th class="sticky"><a href="<?= h(url('admin/swimmer.php?id=' . (int)$s['id'])) ?>"><?= h(swimmer_full($s)) ?></a><?= $s['active'] ? '' : ' <span class="muted">(off)</span>' ?></th>
      <?php foreach ($workouts as $w):
          $wid = (int)$w['id'];
          $c = isset($matrix[(int)$s['id']][$wid]) ? $matrix[(int)$s['id']][$wid] : 0;
          $exp = max(1, $expected[$wid]);
          if ($c >= 0.8 * $exp) { $sym = '&#10003;'; $cls = 'c-done'; }
          elseif ($c > 0) { $sym = '&#9679;'; $cls = 'c-part'; }
          elseif ($w['workout_date'] < $today) { $sym = '&ndash;'; $cls = 'c-miss'; }
          else { $sym = ''; $cls = ''; }
      ?>
        <td class="<?= $cls ?>"><a href="<?= h(url('workout.php?id=' . $wid . '&swimmer=' . (int)$s['id'])) ?>"><?= $sym ?></a></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$swimmers): ?>
    <tr><td colspan="<?= count($workouts) + 1 ?>" class="muted">No swimmers yet. Add some above.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<h2 class="section">Swimmers</h2>
<div class="tablewrap">
<table class="list">
  <thead><tr><th>Name</th><th>Last login</th><th>Sets logged</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($swimmers as $s):
      $total = 0;
      if (isset($matrix[(int)$s['id']])) {
          $total = array_sum($matrix[(int)$s['id']]);
      }
  ?>
    <tr class="<?= $s['active'] ? '' : 'inactive' ?>">
      <td><?= h(swimmer_full($s)) ?><?= $s['active'] ? '' : ' <span class="muted">(deactivated)</span>' ?><?= ($s['active'] && $s['pin_hash'] === null) ? ' <span class="pill pill-missed">needs PIN</span>' : '' ?></td>
      <td><?= $s['last_login'] ? h(fmt_date($s['last_login'], 'M j, g:ia')) : '<span class="muted">never</span>' ?></td>
      <td><?= (int)$total ?></td>
      <td><a class="btn btn-small" href="<?= h(url('admin/swimmer.php?id=' . (int)$s['id'])) ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php page_footer(array('nav' => 'coach')); ?>
