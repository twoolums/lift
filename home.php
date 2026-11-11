<?php
require __DIR__ . '/inc/bootstrap.php';
$sw = require_swimmer();

$workouts = fetch_all('SELECT * FROM workouts ORDER BY workout_date, id');

$counts = array();
$rows = fetch_all('SELECT workout_id, COUNT(*) AS c FROM set_logs WHERE swimmer_id = ? GROUP BY workout_id', array($sw['id']));
foreach ($rows as $r) {
    $counts[(int)$r['workout_id']] = (int)$r['c'];
}

$today = date('Y-m-d');
$next = null;
foreach ($workouts as $w) {
    if ($w['workout_date'] >= $today) {
        $next = $w;
        break;
    }
}

function status_for($w, $counts, $today)
{
    $id = (int)$w['id'];
    $c = isset($counts[$id]) ? $counts[$id] : 0;
    $exp = expected_sets($w);
    if ($c > 0 && $c >= $exp) {
        return array('done', 'Done');
    }
    if ($c > 0) {
        return array('prog', $c . ' of ' . $exp . ' sets');
    }
    if ($w['workout_date'] < $today) {
        return array('missed', 'Not logged');
    }
    return array('todo', 'Upcoming');
}

page_header('My Sessions', array('nav' => 'swimmer'));
?>
<h1 class="hello">Hey <?= h($sw['first_name']) ?> &#128075;</h1>

<?php if ($next): ?>
  <?php
    $isToday = ($next['workout_date'] === $today);
    list($cls, $txt) = status_for($next, $counts, $today);
    $nc = isset($counts[(int)$next['id']]) ? $counts[(int)$next['id']] : 0;
  ?>
  <a class="hero" href="<?= h(url('workout.php?id=' . (int)$next['id'])) ?>">
    <span class="hero-tag"><?= $isToday ? 'TODAY' : 'NEXT UP' ?></span>
    <span class="hero-title"><?= h($next['title']) ?></span>
    <span class="hero-date"><?= h(fmt_date($next['workout_date'], 'l, M j')) ?> &middot; <?= (int)$next['length_min'] ?> min</span>
    <span class="hero-go"><?= $nc > 0 ? 'Keep logging' : 'Start logging' ?> &rarr;</span>
  </a>
<?php else: ?>
  <div class="card"><p>No more sessions on the calendar. Nice work this season!</p></div>
<?php endif; ?>

<h2 class="section">All sessions</h2>
<div class="wklist">
<?php foreach ($workouts as $w): ?>
  <?php list($cls, $txt) = status_for($w, $counts, $today); ?>
  <a class="wk wk-<?= h($cls) ?>" href="<?= h(url('workout.php?id=' . (int)$w['id'])) ?>">
    <span class="chip"><span class="chip-m"><?= h(fmt_date($w['workout_date'], 'M')) ?></span><span class="chip-d"><?= h(fmt_date($w['workout_date'], 'j')) ?></span></span>
    <span class="wk-main">
      <span class="wk-title"><?= h($w['title']) ?></span>
      <span class="wk-sub"><?= h(fmt_date($w['workout_date'], 'D')) ?> &middot; <?= (int)$w['length_min'] ?> min<?= (int)$w['phase'] > 0 ? ' &middot; Phase ' . (int)$w['phase'] : '' ?></span>
    </span>
    <span class="pill pill-<?= h($cls) ?>"><?= h($txt) ?></span>
  </a>
<?php endforeach; ?>
</div>
<?php page_footer(array('nav' => 'swimmer')); ?>
