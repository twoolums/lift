<?php
require __DIR__ . '/inc/bootstrap.php';

if (current_swimmer()) {
    redirect('home.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sid = isset($_POST['swimmer']) ? (int)$_POST['swimmer'] : 0;
    $pin = normalize_pin(isset($_POST['pin']) ? $_POST['pin'] : '');
    $generic = "That name and PIN didn't match. Check them and try again, or ask your coach.";

    if (too_many_attempts()) {
        $error = 'Too many tries from this network. Wait a few minutes and try again.';
    } else {
        $sw = null;
        if ($sid > 0) {
            $sw = fetch_one('SELECT * FROM swimmers WHERE id = ? AND active = 1 AND pin_hash IS NOT NULL', array($sid));
        }
        if (!$sw || strlen($pin) !== 4) {
            record_failed_attempt();
            $error = $generic;
        } elseif (!empty($sw['locked_until']) && $sw['locked_until'] > now()) {
            $error = 'Too many wrong PINs for that name. Wait 10 minutes and try again, or ask your coach.';
        } elseif (password_verify($pin, $sw['pin_hash'])) {
            q('UPDATE swimmers SET failed_pins = 0, locked_until = NULL, last_login = ? WHERE id = ?', array(now(), $sw['id']));
            session_regenerate_id(true);
            $_SESSION['swimmer_id'] = (int)$sw['id'];
            if (!empty($_POST['remember'])) {
                issue_device_token((int)$sw['id']);
            }
            redirect('home.php');
        } else {
            record_failed_attempt();
            $fails = (int)$sw['failed_pins'] + 1;
            if ($fails >= 5) {
                q('UPDATE swimmers SET failed_pins = 0, locked_until = ? WHERE id = ?', array(date('Y-m-d H:i:s', time() + 600), $sw['id']));
                $error = 'Too many wrong PINs for that name. Wait 10 minutes and try again, or ask your coach.';
            } else {
                q('UPDATE swimmers SET failed_pins = ? WHERE id = ?', array($fails, $sw['id']));
                $error = $generic;
            }
        }
    }
}

$list = fetch_all('SELECT id, first_name, last_name FROM swimmers WHERE active = 1 AND pin_hash IS NOT NULL ORDER BY first_name, last_name');
$labels = swimmer_labels($list);
$chosen = isset($_POST['swimmer']) ? (int)$_POST['swimmer'] : 0;

page_header('Log in', array('topbar' => false, 'bodyclass' => 'login'));
?>
<div class="login-card">
  <h1><?= h(TEAM_NAME) ?></h1>
  <p class="sub">Lift Log</p>
  <?php if ($error): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>
  <?php if (!$list): ?>
    <p>No swimmers are set up yet. Ask your coach.</p>
  <?php else: ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label for="swimmer" class="big-label">Your name</label>
    <select id="swimmer" name="swimmer" class="text-input big-select" required>
      <option value="">Choose your name...</option>
      <?php foreach ($list as $s): ?>
        <option value="<?= (int)$s['id'] ?>"<?= $chosen === (int)$s['id'] ? ' selected' : '' ?>><?= h($labels[$s['id']]) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="pin" class="big-label">Your 4-digit PIN</label>
    <input id="pin" name="pin" class="pin-input" type="password" inputmode="numeric" pattern="[0-9]*"
           maxlength="4" autocomplete="off" placeholder="&bull;&bull;&bull;&bull;" required>
    <label class="check"><input type="checkbox" name="remember" value="1" checked>
      <span>Keep me logged in on this phone</span></label>
    <button type="submit" class="btn btn-primary btn-block">Log in</button>
  </form>
  <?php endif; ?>
  <p class="muted small">Your coach gave you a PIN. You only need to enter it once on each phone.</p>
  <p class="muted small"><a href="<?= h(url('admin/login.php')) ?>">Coach login</a></p>
</div>
<?php page_footer(); ?>
