<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

$src = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
$day = isset($src['day']) ? $src['day'] : 'A';
if (!in_array($day, array('A', 'B', 'C', 'P'), true)) {
    $day = 'A';
}
$phase = isset($src['phase']) ? (int)$src['phase'] : 1;
if ($day === 'P') {
    $phase = 0;
} elseif ($phase < 1 || $phase > 3) {
    $phase = 1;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $items = isset($_POST['it']) && is_array($_POST['it']) ? $_POST['it'] : array();
        foreach ($items as $id => $f) {
            $id = (int)$id;
            if (!empty($f['delete'])) {
                q('DELETE FROM program_items WHERE id = ? AND day_type = ? AND phase = ?', array($id, $day, $phase));
                continue;
            }
            q(
                'UPDATE program_items SET position = ?, label = ?, exercise_id = ?, sets = ?, reps_text = ?, rest_text = ?, starred = ?
                 WHERE id = ? AND day_type = ? AND phase = ?',
                array(
                    max(0, min(999, (int)(isset($f['position']) ? $f['position'] : 1))),
                    substr(trim(isset($f['label']) ? $f['label'] : ''), 0, 8),
                    (int)(isset($f['exercise_id']) ? $f['exercise_id'] : 0),
                    max(1, min(20, (int)(isset($f['sets']) ? $f['sets'] : 3))),
                    substr(trim(isset($f['reps_text']) ? $f['reps_text'] : ''), 0, 40),
                    substr(trim(isset($f['rest_text']) ? $f['rest_text'] : ''), 0, 20),
                    !empty($f['starred']) ? 1 : 0,
                    $id, $day, $phase,
                )
            );
        }
        $n = isset($_POST['new']) && is_array($_POST['new']) ? $_POST['new'] : array();
        if (!empty($n['exercise_id'])) {
            q(
                'INSERT INTO program_items (day_type, phase, position, label, exercise_id, sets, reps_text, rest_text, starred)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array(
                    $day, $phase,
                    max(0, min(999, (int)(isset($n['position']) ? $n['position'] : 99))),
                    substr(trim(isset($n['label']) ? $n['label'] : ''), 0, 8),
                    (int)$n['exercise_id'],
                    max(1, min(20, (int)(isset($n['sets']) ? $n['sets'] : 3))),
                    substr(trim(isset($n['reps_text']) ? $n['reps_text'] : ''), 0, 40),
                    substr(trim(isset($n['rest_text']) ? $n['rest_text'] : ''), 0, 20),
                    !empty($n['starred']) ? 1 : 0,
                )
            );
        }
        $pdo->commit();
        $msg = 'Saved.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = 'Could not save. Check that every row has an exercise selected.';
    }
}

$exercises = fetch_all('SELECT id, name FROM exercises ORDER BY name');
$items = fetch_all(
    'SELECT * FROM program_items WHERE day_type = ? AND phase = ? ORDER BY position, id',
    array($day, $phase)
);

function exercise_select($name, $exercises, $selected)
{
    $s = '<select class="text-input" name="' . h($name) . '">';
    $s .= '<option value="">- choose -</option>';
    foreach ($exercises as $e) {
        $s .= '<option value="' . (int)$e['id'] . '"' . ((int)$e['id'] === (int)$selected ? ' selected' : '') . '>' . h($e['name']) . '</option>';
    }
    return $s . '</select>';
}

page_header('Program', array('nav' => 'coach'));
?>
<h1>Program</h1>
<p class="muted small">This is what swimmers see for each type of session. Sessions are matched by their Day type and Phase on the Sessions page. A starred (*) exercise is skipped on sessions shorter than 60 minutes.</p>

<div class="tabs">
  <?php foreach (array('A', 'B', 'C', 'P') as $d): ?>
    <a class="tab <?= $d === $day ? 'on' : '' ?>" href="<?= h(url('admin/program.php?day=' . $d . '&phase=' . ($d === 'P' ? 0 : max(1, $phase)))) ?>"><?= h(day_type_name($d)) ?></a>
  <?php endforeach; ?>
</div>
<?php if ($day !== 'P'): ?>
<div class="tabs small">
  <?php foreach (array(1, 2, 3) as $p): ?>
    <a class="tab <?= $p === $phase ? 'on' : '' ?>" href="<?= h(url('admin/program.php?day=' . $day . '&phase=' . $p)) ?>">Phase <?= $p ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>

<form method="post" data-confirm-del="Remove the checked exercises from this session type?">
  <?= csrf_field() ?>
  <input type="hidden" name="day" value="<?= h($day) ?>">
  <input type="hidden" name="phase" value="<?= (int)$phase ?>">
  <div class="tablewrap">
  <table class="list edit">
    <thead><tr><th>Order</th><th>Label</th><th>Exercise</th><th>Sets</th><th>Reps / target</th><th>Rest</th><th>*</th><th>Remove</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): $i = (int)$it['id']; ?>
      <tr>
        <td><input class="text-input narrow" name="it[<?= $i ?>][position]" value="<?= (int)$it['position'] ?>"></td>
        <td><input class="text-input narrow" name="it[<?= $i ?>][label]" value="<?= h($it['label']) ?>" maxlength="8"></td>
        <td><?= exercise_select('it[' . $i . '][exercise_id]', $exercises, $it['exercise_id']) ?></td>
        <td><input class="text-input narrow" name="it[<?= $i ?>][sets]" value="<?= (int)$it['sets'] ?>"></td>
        <td><input class="text-input" name="it[<?= $i ?>][reps_text]" value="<?= h($it['reps_text']) ?>" maxlength="40"></td>
        <td><input class="text-input narrow" name="it[<?= $i ?>][rest_text]" value="<?= h($it['rest_text']) ?>" maxlength="20"></td>
        <td><input type="checkbox" name="it[<?= $i ?>][starred]" value="1"<?= $it['starred'] ? ' checked' : '' ?>></td>
        <td><input type="checkbox" class="del" name="it[<?= $i ?>][delete]" value="1"></td>
      </tr>
    <?php endforeach; ?>
      <tr class="newrow">
        <td><input class="text-input narrow" name="new[position]" value="<?= count($items) + 1 ?>"></td>
        <td><input class="text-input narrow" name="new[label]" maxlength="8" placeholder="6"></td>
        <td><?= exercise_select('new[exercise_id]', $exercises, 0) ?></td>
        <td><input class="text-input narrow" name="new[sets]" value="3"></td>
        <td><input class="text-input" name="new[reps_text]" maxlength="40" placeholder="8/side"></td>
        <td><input class="text-input narrow" name="new[rest_text]" maxlength="20" placeholder="60s"></td>
        <td><input type="checkbox" name="new[starred]" value="1"></td>
        <td class="muted small">add row</td>
      </tr>
    </tbody>
  </table>
  </div>
  <p><button class="btn btn-primary" type="submit">Save changes</button></p>
</form>
<?php page_footer(array('nav' => 'coach')); ?>
