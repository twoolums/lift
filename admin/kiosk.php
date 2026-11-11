<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

$msg = '';
$thisSelector = '';
if (!empty($_COOKIE['lifts_kiosk']) && strpos($_COOKIE['lifts_kiosk'], ':') !== false) {
    $parts = explode(':', $_COOKIE['lifts_kiosk'], 2);
    $thisSelector = $parts[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'enable') {
        $label = trim(isset($_POST['label']) ? $_POST['label'] : '');
        if ($label === '') {
            $label = 'Weight room device';
        }
        issue_kiosk_token($label);
        redirect('admin/kiosk.php?enabled=1');
    } elseif ($action === 'revoke') {
        q('DELETE FROM kiosk_devices WHERE id = ?', array((int)(isset($_POST['id']) ? $_POST['id'] : 0)));
        $msg = 'Device revoked. It can no longer open the kiosk.';
    } elseif ($action === 'revoke_all') {
        q('DELETE FROM kiosk_devices');
        $msg = 'All kiosk devices revoked.';
    }
}
if (isset($_GET['enabled'])) {
    $msg = 'This device is now set up as a kiosk.';
}

$devices = fetch_all('SELECT * FROM kiosk_devices WHERE expires_at > ? ORDER BY created_at DESC', array(now()));

page_header('Kiosk', array('nav' => 'coach'));
?>
<h1>Weight-room kiosk</h1>
<?php if ($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>

<div class="card">
  <p>The kiosk is a quick-entry page for a tablet or computer that stays in the weight room. A swimmer walks up, picks their name from a drop-down, enters their lifts, taps <strong>Done</strong>, and the next swimmer goes.</p>
  <p class="muted small">Set it up once <strong>on the device itself</strong> (log in as coach on that device and tap the button below). Only devices you authorize can open the kiosk or see the roster. While a device is authorized, anyone using it can enter lifts for any swimmer, so revoke it when the season ends or if the device goes missing.</p>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="enable">
    <input class="text-input" name="label" placeholder="Name for this device (for example: Weight room iPad)" maxlength="60">
    <button class="btn btn-primary" type="submit">Set up THIS device as a kiosk</button>
  </form>
</div>

<?php if ($thisSelector !== '' && current_kiosk()): ?>
  <p><a class="btn btn-primary" href="<?= h(url('kiosk.php')) ?>">Open the kiosk on this device</a></p>
<?php endif; ?>

<h2 class="section">Authorized devices</h2>
<div class="tablewrap">
<table class="list">
  <thead><tr><th>Device</th><th>Set up</th><th>Expires</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($devices as $d): ?>
    <tr>
      <td><?= h($d['label']) ?><?= $d['selector'] === $thisSelector ? ' <span class="muted">(this device)</span>' : '' ?></td>
      <td><?= h(fmt_date($d['created_at'], 'M j, g:ia')) ?></td>
      <td><?= h(fmt_date($d['expires_at'], 'M j, Y')) ?></td>
      <td>
        <form method="post" class="inline" data-confirm="Revoke this device?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="revoke">
          <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn btn-small btn-danger" type="submit">Revoke</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$devices): ?><tr><td colspan="4" class="muted">No devices authorized yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($devices): ?>
  <form method="post" data-confirm="Revoke every kiosk device?" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="revoke_all">
    <button class="btn btn-danger" type="submit">Revoke all devices</button>
  </form>
<?php endif; ?>
<?php page_footer(array('nav' => 'coach')); ?>
