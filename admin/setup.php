<?php
require __DIR__ . '/../inc/bootstrap.php';

$count = (int)q('SELECT COUNT(*) FROM coaches')->fetchColumn();
if ($count > 0) {
    http_response_code(403);
    page_header('Setup', array('topbar' => false, 'bodyclass' => 'login'));
    echo '<div class="login-card"><h1>Setup is finished</h1><p>A coach account already exists. For safety, delete <code>admin/setup.php</code> from your server.</p>';
    echo '<p><a href="' . h(url('admin/login.php')) . '">Go to coach login</a></p></div>';
    page_footer();
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $key = isset($_POST['setup_key']) ? $_POST['setup_key'] : '';
    $user = isset($_POST['username']) ? trim($_POST['username']) : '';
    $pass = isset($_POST['password']) ? $_POST['password'] : '';
    $pass2 = isset($_POST['password2']) ? $_POST['password2'] : '';
    if (!hash_equals((string)SETUP_KEY, (string)$key)) {
        $error = 'That setup key does not match SETUP_KEY in config.php.';
    } elseif ($user === '' || strlen($user) > 60) {
        $error = 'Choose a username (up to 60 characters).';
    } elseif (strlen($pass) < 10) {
        $error = 'Use a password with at least 10 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'The two passwords do not match.';
    } else {
        q(
            'INSERT INTO coaches (username, password_hash, created_at) VALUES (?, ?, ?)',
            array($user, password_hash($pass, PASSWORD_DEFAULT), now())
        );
        redirect('admin/login.php');
    }
}

page_header('First-time setup', array('topbar' => false, 'bodyclass' => 'login'));
?>
<div class="login-card">
  <h1>First-time setup</h1>
  <p class="sub">Create the coach account</p>
  <?php if ($error): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label class="big-label" for="k">Setup key (from config.php)</label>
    <input id="k" name="setup_key" class="text-input" type="password" required>
    <label class="big-label" for="u">Coach username</label>
    <input id="u" name="username" class="text-input" type="text" required>
    <label class="big-label" for="p">Password (10+ characters)</label>
    <input id="p" name="password" class="text-input" type="password" required>
    <label class="big-label" for="p2">Repeat password</label>
    <input id="p2" name="password2" class="text-input" type="password" required>
    <button type="submit" class="btn btn-primary btn-block">Create coach account</button>
  </form>
</div>
<?php page_footer(); ?>
