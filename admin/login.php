<?php
require __DIR__ . '/../inc/bootstrap.php';

// No coach yet? Send to first-time setup.
$count = (int)q('SELECT COUNT(*) FROM coaches')->fetchColumn();
if ($count === 0) {
    redirect('admin/setup.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    csrf_check();
    unset($_SESSION['coach_id'], $_SESSION['coach_last']);
    redirect('admin/login.php');
}

if (current_coach()) {
    redirect('admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (too_many_attempts()) {
        $error = 'Too many tries from this network. Wait a few minutes and try again.';
    } else {
        $user = isset($_POST['username']) ? trim($_POST['username']) : '';
        $pass = isset($_POST['password']) ? $_POST['password'] : '';
        $row = fetch_one('SELECT * FROM coaches WHERE username = ?', array($user));
        if ($row && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['coach_id'] = (int)$row['id'];
            $_SESSION['coach_last'] = time();
            redirect('admin/index.php');
        }
        record_failed_attempt();
        $error = 'Wrong username or password.';
    }
}

page_header('Coach login', array('topbar' => false, 'bodyclass' => 'login'));
?>
<div class="login-card">
  <h1><?= h(TEAM_NAME) ?></h1>
  <p class="sub">Coach login</p>
  <?php if ($error): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="u" class="big-label">Username</label>
    <input id="u" name="username" class="text-input" type="text" autocomplete="username" required autofocus>
    <label for="p" class="big-label">Password</label>
    <input id="p" name="password" class="text-input" type="password" autocomplete="current-password" required>
    <button type="submit" class="btn btn-primary btn-block">Log in</button>
  </form>
  <p class="muted small"><a href="<?= h(url('index.php')) ?>">Swimmer login</a></p>
</div>
<?php page_footer(); ?>
