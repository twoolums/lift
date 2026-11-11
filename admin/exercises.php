<?php
require __DIR__ . '/../inc/bootstrap.php';
$coach = require_coach();

function clean_link($u)
{
    $u = trim((string)$u);
    if ($u === '') {
        return '';
    }
    // Only allow normal web links (blocks javascript: and similar)
    if (!preg_match('#^https?://#i', $u)) {
        return null;
    }
    return substr($u, 0, 500);
}

function clean_type($t)
{
    return in_array($t, array('wr', 'reps', 'sec'), true) ? $t : 'wr';
}

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ex = isset($_POST['ex']) && is_array($_POST['ex']) ? $_POST['ex'] : array();
    foreach ($ex as $id => $f) {
        $id = (int)$id;
        $name = trim(isset($f['name']) ? $f['name'] : '');
        $link = clean_link(isset($f['link']) ? $f['link'] : '');
        if ($name === '') {
            continue;
        }
        if ($link === null) {
            $err = 'Links must start with http:// or https://. Some rows were not saved.';
            continue;
        }
        $step = isset($f['step']) && is_numeric($f['step']) ? (float)$f['step'] : 5;
        $step = max(0.5, min(50, $step));
        q(
            'UPDATE exercises SET name = ?, input_type = ?, value_label = ?, step = ?, cue = ?, link_url = ? WHERE id = ?',
            array(
                substr($name, 0, 100),
                clean_type(isset($f['type']) ? $f['type'] : 'wr'),
                substr(trim(isset($f['label']) ? $f['label'] : ''), 0, 20),
                $step,
                substr(trim(isset($f['cue']) ? $f['cue'] : ''), 0, 400),
                $link,
                $id,
            )
        );
    }
    $n = isset($_POST['new']) && is_array($_POST['new']) ? $_POST['new'] : array();
    $newName = trim(isset($n['name']) ? $n['name'] : '');
    if ($newName !== '') {
        $link = clean_link(isset($n['link']) ? $n['link'] : '');
        if ($link === null) {
            $err = 'Links must start with http:// or https://. The new exercise was not added.';
        } else {
            $step = isset($n['step']) && is_numeric($n['step']) ? max(0.5, min(50, (float)$n['step'])) : 5;
            q(
                'INSERT INTO exercises (name, input_type, value_label, step, cue, link_url) VALUES (?, ?, ?, ?, ?, ?)',
                array(
                    substr($newName, 0, 100),
                    clean_type(isset($n['type']) ? $n['type'] : 'wr'),
                    substr(trim(isset($n['label']) ? $n['label'] : 'lb'), 0, 20),
                    $step,
                    substr(trim(isset($n['cue']) ? $n['cue'] : ''), 0, 400),
                    $link,
                )
            );
        }
    }
    if ($err === '') {
        $msg = 'Saved.';
    }
}

$list = fetch_all('SELECT * FROM exercises ORDER BY name');

page_header('Exercises', array('nav' => 'coach'));
?>
<h1>Exercises</h1>
<p class="muted small">Type: <strong>weight + reps</strong> (lb, box inches, ball weight...), <strong>reps only</strong>, or <strong>seconds</strong>. "Step" is how much the + and - buttons change the weight. Add new exercises at the bottom, then put them in a session on the Program page.</p>
<?php if ($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert"><?= h($err) ?></div><?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="tablewrap">
  <table class="list edit">
    <thead><tr><th>Name</th><th>Type</th><th>Unit label</th><th>Step</th><th>Cue</th><th>Link</th></tr></thead>
    <tbody>
    <?php foreach ($list as $e): $i = (int)$e['id']; ?>
      <tr>
        <td><input class="text-input" name="ex[<?= $i ?>][name]" value="<?= h($e['name']) ?>" maxlength="100"></td>
        <td>
          <select class="text-input" name="ex[<?= $i ?>][type]">
            <option value="wr"<?= $e['input_type'] === 'wr' ? ' selected' : '' ?>>weight + reps</option>
            <option value="reps"<?= $e['input_type'] === 'reps' ? ' selected' : '' ?>>reps only</option>
            <option value="sec"<?= $e['input_type'] === 'sec' ? ' selected' : '' ?>>seconds</option>
          </select>
        </td>
        <td><input class="text-input narrow" name="ex[<?= $i ?>][label]" value="<?= h($e['value_label']) ?>" maxlength="20"></td>
        <td><input class="text-input narrow" name="ex[<?= $i ?>][step]" value="<?= h(num($e['step'])) ?>"></td>
        <td><input class="text-input" name="ex[<?= $i ?>][cue]" value="<?= h($e['cue']) ?>" maxlength="400"></td>
        <td><input class="text-input" name="ex[<?= $i ?>][link]" value="<?= h($e['link_url']) ?>" maxlength="500"></td>
      </tr>
    <?php endforeach; ?>
      <tr class="newrow">
        <td><input class="text-input" name="new[name]" placeholder="New exercise name" maxlength="100"></td>
        <td>
          <select class="text-input" name="new[type]">
            <option value="wr">weight + reps</option>
            <option value="reps">reps only</option>
            <option value="sec">seconds</option>
          </select>
        </td>
        <td><input class="text-input narrow" name="new[label]" value="lb" maxlength="20"></td>
        <td><input class="text-input narrow" name="new[step]" value="5"></td>
        <td><input class="text-input" name="new[cue]" maxlength="400"></td>
        <td><input class="text-input" name="new[link]" placeholder="https://..." maxlength="500"></td>
      </tr>
    </tbody>
  </table>
  </div>
  <p><button class="btn btn-primary" type="submit">Save changes</button></p>
</form>
<?php page_footer(array('nav' => 'coach')); ?>
