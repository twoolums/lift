<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

$exList = exercises_with_logs();
$eid = isset($_GET['e']) ? (int)$_GET['e'] : 0;
if ($eid < 1 && $exList) {
    // default to the exercise with the most logged sets
    $top = $exList[0];
    foreach ($exList as $e) {
        if ((int)$e['n'] > (int)$top['n']) {
            $top = $e;
        }
    }
    $eid = (int)$top['id'];
}

$data = $eid > 0 ? team_exercise($eid) : null;
$participation = team_participation();

$chart = null;
$summary = null;
if ($data && count($data['labels']) >= 1) {
    $series = array(array('name' => 'Team average', 'team' => true, 'values' => $data['avg']));
    foreach ($data['athletes'] as $a) {
        $series[] = array('name' => $a['name'], 'team' => false, 'values' => $a['values']);
    }
    $chart = array('labels' => $data['labels'], 'series' => $series, 'unit' => $data['unit']);

    $changes = array();
    foreach ($data['athletes'] as $a) {
        if ($a['change'] !== null) {
            $changes[] = $a['change'];
        }
    }
    $summary = array(
        'athletes' => count($data['athletes']),
        'avgChange' => $changes ? array_sum($changes) / count($changes) : null,
        'nChange' => count($changes),
        'teamBest' => $data['athletes'] ? $data['athletes'][0] : null,
    );
}

page_header('Team progress', array('nav' => 'coach'));
?>
<h1>Team progress</h1>

<div class="card">
  <h2 class="small-h">Attendance: % of swimmers who logged each session</h2>
  <div class="bars" data-bars="<?= h(json_encode($participation)) ?>"></div>
</div>

<h2 class="section">Lift trends</h2>
<?php if (!$exList): ?>
  <div class="card"><p>No lifts have been logged yet. Charts will appear after your swimmers enter some sets.</p></div>
<?php else: ?>
  <form method="get" class="stack">
    <label for="e" class="big-label">Exercise</label>
    <select id="e" name="e" class="text-input big-select" data-autosubmit>
      <?php foreach ($exList as $e): ?>
        <option value="<?= (int)$e['id'] ?>"<?= (int)$e['id'] === $eid ? ' selected' : '' ?>><?= h($e['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn" type="submit">Show</button></noscript>
  </form>

  <?php if ($data && $chart): ?>
    <div class="tiles">
      <div class="tile"><span class="tile-n"><?= (int)$summary['athletes'] ?></span><span class="tile-l">swimmers logged this</span></div>
      <div class="tile">
        <span class="tile-n"><?= $summary['avgChange'] === null ? '-' : h(($summary['avgChange'] > 0 ? '+' : '') . num(round($summary['avgChange'], 1))) ?></span>
        <span class="tile-l">avg change, first to latest (<?= h($data['unit']) ?>)<?= $summary['nChange'] ? ', ' . (int)$summary['nChange'] . ' swimmers' : '' ?></span>
      </div>
      <?php if ($summary['teamBest']): ?>
      <div class="tile"><span class="tile-n"><?= h(num($summary['teamBest']['best'])) ?></span><span class="tile-l">team best (<?= h($data['unit']) ?>): <?= h($summary['teamBest']['name']) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="row-between">
        <h3 class="small-h">Team average of each swimmer's top set (<?= h($data['unit']) ?>)</h3>
        <label class="check tight"><input type="checkbox" class="toggle-athletes"> <span>Show each swimmer</span></label>
      </div>
      <div class="mchart" data-chart="<?= h(json_encode($chart)) ?>"></div>
      <p class="muted small">Each point is the average of the swimmers who logged this lift that session. Turn on "Show each swimmer" to give every swimmer a color: hover a line (or tap a line or a name in the key) to see who it is and to isolate them. Tap the same name again, or empty space in the chart, to show everyone.</p>
    </div>

    <div class="tablewrap">
    <table class="list">
      <thead><tr><th>Swimmer</th><th>First</th><th>Latest</th><th>Change</th><th>Best</th><th>Sessions</th></tr></thead>
      <tbody>
      <?php foreach ($data['athletes'] as $a): ?>
        <tr>
          <td><a href="<?= h(url('admin/swimmer.php?id=' . (int)$a['id'])) ?>"><?= h($a['name']) ?></a></td>
          <td><?= h(num($a['first'])) ?></td>
          <td><?= h(num($a['latest'])) ?></td>
          <td class="<?= ($a['change'] !== null && $a['change'] > 0) ? 'up' : (($a['change'] !== null && $a['change'] < 0) ? 'down' : '') ?>">
            <?= $a['change'] === null ? '-' : h(($a['change'] > 0 ? '+' : '') . num($a['change'])) ?></td>
          <td><strong><?= h(num($a['best'])) ?></strong></td>
          <td><?= (int)$a['n'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="muted small">Values are in <?= h($data['unit']) ?>. Change = latest session minus first session (needs 2 or more sessions). Deactivated swimmers are left out.</p>
  <?php endif; ?>
<?php endif; ?>
<?php page_footer(array('nav' => 'coach')); ?>
