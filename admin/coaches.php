<?php
require __DIR__ . '/../inc/bootstrap.php';
$me = require_coach();

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $user = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $pass = isset($_POST['password']) ? $_POST['password'] : '';
        $pass2 = isset($_POST['password2']) ? $_POST['password2'] : '';
        if ($user === '' || strlen($user) > 60) {
            $err = 'Choose a username (up to 60 characters).';
        } elseif (strlen($pass) < 10) {
            $err = 'Use a password with at least 10 characters.';
        } elseif ($pass !== $pass2) {
            $err = 'The two passwords do not match.';
        } elseif (fetch_one('SELECT id FROM coaches WHERE username = ?', array($user))) {
            $err = 'That username is already taken.';
        } else {
            q(
                'INSERT INTO coaches (username, password_hash, created_at) VALUES (?, ?, ?)',
                array($user, password_hash($pass, PASSWORD_DEFAULT), now())
            );
            $msg = 'Coach "' . $user . '" added. Give them the username and password, and suggest they change the password after logging in.';
        }
    } elseif ($action === 'change_password') {
        $cur = isset($_POST['current']) ? $_POST['current'] : '';
        $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $new2 = isset($_POST['new_password2']) ? $_POST['new_password2'] : '';
        $row = fetch_one('SELECT * FROM coaches WHERE id = ?', array($me['id']));
        if (!$row || !password_verify($cur, $row['password_hash'])) {
            $err = 'Your current password is not right.';
        } elseif (strlen($new) < 10) {
            $err = 'Use a new password with at least 10 characters.';
        } elseif ($new !== $new2) {
            $err = 'The two new passwords do not match.';
        } else {
            q('UPDATE coaches SET password_hash = ? WHERE id = ?', array(password_hash($new, PASSWORD_DEFAULT), $me['id']));
            $msg = 'Your password was changed.';
        }
    } elseif ($action === 'remove') {
        $rid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($rid === (int)$me['id']) {
            $err = 'You cannot remove your own account. Ask the other coach to do it.';
        } else {
            q('DELETE FROM coaches WHERE id = ?', array($rid));
            $msg = 'Coach removed.';
        }
    }
}

$list = fetch_all('SELECT id, username, created_at FROM coaches ORDER BY created_at, id');

page_header('Coaches', array('nav' => 'coach'));
?>
<h1>Coaches</h1>
<?php if ($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert"><?= h($err) ?></div><?php endif; ?>

<div class="tablewrap">
<table class="list">
  <thead><tr><th>Username</th><th>Added</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $c): ?>
    <tr>
      <td><?= h($c['username']) ?><?= (int)$c['id'] === (int)$me['id'] ? ' <span class="muted">(you)</span>' : '' ?></td>
      <td><?= h(fmt_date($c['created_at'], 'M j, Y')) ?></td>
      <td>
        <?php if ((int)$c['id'] !== (int)$me['id']): ?>
          <form method="post" class="inline" data-confirm="Remove this coach? They will no longer be able to log in.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-small btn-danger" type="submit">Remove</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="grid2">
  <div class="card">
    <h2 class="small-h">Add a coach</h2>
    <p class="muted small">Coaches can see and edit everything: swimmers, sessions, program, kiosk, and exports.</p>
    <form method="post" autocomplete="off" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input class="text-input" name="username" placeholder="Username" required maxlength="60">
      <input class="text-input" name="password" type="password" placeholder="Password (10+ characters)" required>
      <input class="text-input" name="password2" type="password" placeholder="Repeat password" required>
      <button class="btn btn-primary" type="submit">Add coach</button>
    </form>
  </div>
  <div class="card">
    <h2 class="small-h">Change my password</h2>
    <form method="post" autocomplete="off" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <input class="text-input" name="current" type="password" placeholder="Current password" required>
      <input class="text-input" name="new_password" type="password" placeholder="New password (10+ characters)" required>
      <input class="text-input" name="new_password2" type="password" placeholder="Repeat new password" required>
      <button class="btn" type="submit">Change password</button>
    </form>
  </div>
</div>
<p class="muted small">Forgot a coach's password? Remove that coach and add them again with a new one.</p>
<?php page_footer(array('nav' => 'coach')); ?>
