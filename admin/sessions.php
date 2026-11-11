<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

function valid_date($d)
{
    $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
    return $dt && $dt->format('Y-m-d') === $d;
}

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rows = isset($_POST['w']) && is_array($_POST['w']) ? $_POST['w'] : array();
    foreach ($rows as $id => $f) {
        $id = (int)$id;
        if (!empty($f['delete'])) {
            q('DELETE FROM workouts WHERE id = ?', array($id));
            continue;
        }
        $date = isset($f['date']) ? trim($f['date']) : '';
        $day = isset($f['day_type']) ? $f['day_type'] : 'A';
        if (!valid_date($date) || !in_array($day, array('A', 'B', 'C', 'P'), true)) {
            $err = 'Some rows were not saved: check the dates (YYYY-MM-DD).';
            continue;
        }
        $phase = ($day === 'P') ? 0 : max(1, min(3, (int)(isset($f['phase']) ? $f['phase'] : 1)));
        q(
            'UPDATE workouts SET workout_date = ?, title = ?, day_type = ?, phase = ?, length_min = ?, notes = ? WHERE id = ?',
            array(
                $date,
                substr(trim(isset($f['title']) ? $f['title'] : ''), 0, 100),
                $day,
                $phase,
                max(10, min(300, (int)(isset($f['length_min']) ? $f['length_min'] : 60))),
                substr(trim(isset($f['notes']) ? $f['notes'] : ''), 0, 500),
                $id,
            )
        );
    }
    $n = isset($_POST['new']) && is_array($_POST['new']) ? $_POST['new'] : array();
    $nd = isset($n['date']) ? trim($n['date']) : '';
    if ($nd !== '') {
        $day = isset($n['day_type']) ? $n['day_type'] : 'A';
        if (!valid_date($nd) || !in_array($day, array('A', 'B', 'C', 'P'), true)) {
            $err = 'The new session was not added: check the date (YYYY-MM-DD).';
        } else {
            $phase = ($day === 'P') ? 0 : max(1, min(3, (int)(isset($n['phase']) ? $n['phase'] : 1)));
            $title = trim(isset($n['title']) ? $n['title'] : '');
            if ($title === '') {
                $title = day_type_name($day);
            }
            q(
                'INSERT INTO workouts (workout_date, title, day_type, phase, length_min, notes) VALUES (?, ?, ?, ?, ?, ?)',
                array(
                    $nd,
                    substr($title, 0, 100),
                    $day,
                    $phase,
                    max(10, min(300, (int)(isset($n['length_min']) ? $n['length_min'] : 60))),
                    substr(trim(isset($n['notes']) ? $n['notes'] : ''), 0, 500),
                )
            );
        }
    }
    if ($err === '') {
        $msg = 'Saved.';
    }
}

$list = fetch_all('SELECT * FROM workouts ORDER BY workout_date, id');

function day_select($name, $selected)
{
    $s = '<select class="text-input" name="' . h($name) . '">';
    foreach (array('A', 'B', 'C', 'P') as $d) {
        $s .= '<option value="' . $d . '"' . ($d === $selected ? ' selected' : '') . '>' . h($d === 'P' ? 'Primer' : 'Day ' . $d) . '</option>';
    }
    return $s . '</select>';
}

page_header('Sessions', array('nav' => 'coach'));
?>
<h1>Sessions</h1>
<p class="muted small">These are your scheduled lift days. Change a date if the schedule moves. <strong>Day type + Phase</strong> decide which exercises show (set those on the Program page). Sessions under 60 minutes hide starred (*) exercises. Deleting a session also deletes any lifts logged on it.</p>
<?php if ($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert"><?= h($err) ?></div><?php endif; ?>

<form method="post" data-confirm-del="Delete the checked sessions AND every lift logged on them? This cannot be undone.">
  <?= csrf_field() ?>
  <div class="tablewrap">
  <table class="list edit">
    <thead><tr><th>Date</th><th>Title</th><th>Day</th><th>Phase</th><th>Min</th><th>Note for swimmers</th><th>Delete</th></tr></thead>
    <tbody>
    <?php foreach ($list as $w): $i = (int)$w['id']; ?>
      <tr>
        <td><input class="text-input" type="date" name="w[<?= $i ?>][date]" value="<?= h($w['workout_date']) ?>"></td>
        <td><input class="text-input" name="w[<?= $i ?>][title]" value="<?= h($w['title']) ?>" maxlength="100"></td>
        <td><?= day_select('w[' . $i . '][day_type]', $w['day_type']) ?></td>
        <td><input class="text-input narrow" name="w[<?= $i ?>][phase]" value="<?= (int)$w['phase'] ?>"></td>
        <td><input class="text-input narrow" name="w[<?= $i ?>][length_min]" value="<?= (int)$w['length_min'] ?>"></td>
        <td><input class="text-input" name="w[<?= $i ?>][notes]" value="<?= h($w['notes']) ?>" maxlength="500"></td>
        <td><input type="checkbox" class="del" name="w[<?= $i ?>][delete]" value="1"></td>
      </tr>
    <?php endforeach; ?>
      <tr class="newrow">
        <td><input class="text-input" type="date" name="new[date]"></td>
        <td><input class="text-input" name="new[title]" placeholder="(optional)" maxlength="100"></td>
        <td><?= day_select('new[day_type]', 'A') ?></td>
        <td><input class="text-input narrow" name="new[phase]" value="1"></td>
        <td><input class="text-input narrow" name="new[length_min]" value="60"></td>
        <td><input class="text-input" name="new[notes]" maxlength="500"></td>
        <td class="muted small">add row</td>
      </tr>
    </tbody>
  </table>
  </div>
  <p><button class="btn btn-primary" type="submit">Save changes</button></p>
</form>
<?php page_footer(array('nav' => 'coach')); ?>
