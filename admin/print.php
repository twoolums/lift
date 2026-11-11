<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    unset($_SESSION['new_codes']);
    redirect('admin/index.php');
}

$codes = isset($_SESSION['new_codes']) ? $_SESSION['new_codes'] : array();
$origin = app_origin_url();

page_header('Login cards', array('nav' => 'coach', 'bodyclass' => 'printpage'));
?>
<div class="noprint">
  <h1>Login cards</h1>
  <?php if ($codes): ?>
    <p class="alert alert-info">These PINs are shown <strong>only once</strong>. Print or screenshot this page now. If a PIN is lost, open the swimmer and choose "Reset PIN".</p>
    <div class="actions">
      <button class="btn btn-primary" onclick="window.print()">Print cards</button>
      <form method="post" class="inline" data-confirm="Hide these PINs? You will not be able to see them again.">
        <?= csrf_field() ?>
        <button class="btn" type="submit">I'm done, hide PINs</button>
      </form>
    </div>
  <?php else: ?>
    <div class="card"><p>No new PINs to show. Add swimmers from the <a href="<?= h(url('admin/index.php')) ?>">Team page</a>.</p></div>
  <?php endif; ?>
</div>

<div class="cards">
<?php foreach ($codes as $c): ?>
  <div class="logincard">
    <div class="lc-team"><?= h(TEAM_NAME) ?> Lift Log</div>
    <div class="lc-name"><?= h($c['name']) ?></div>
    <div class="lc-code"><?= h($c['pin']) ?></div>
    <div class="lc-url"><?= h($origin) ?></div>
    <div class="lc-help">Open the link on your phone, pick your name, type your PIN once, and tap "Keep me logged in".</div>
  </div>
<?php endforeach; ?>
</div>
<?php page_footer(array('nav' => 'coach')); ?>
