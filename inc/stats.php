<?php
// Helpers that turn logged sets into numbers for charts and summaries.

// The number we chart for a set: reps for reps-only exercises, otherwise the weight/height/seconds value.
function set_measure($type, $row)
{
    if ($type === 'reps') {
        return ($row['reps'] === null) ? null : (float)$row['reps'];
    }
    return ($row['val'] === null) ? null : (float)$row['val'];
}

function measure_unit($ex)
{
    if ($ex['input_type'] === 'reps') {
        return 'reps';
    }
    if ($ex['input_type'] === 'sec') {
        return 'sec';
    }
    return $ex['value_label'] !== '' ? $ex['value_label'] : 'lb';
}

// One entry per exercise a swimmer has logged, with the top set of each session in date order.
function swimmer_series($swimmerId)
{
    $rows = fetch_all(
        'SELECT l.exercise_id, e.name, e.input_type, e.value_label, l.workout_id, w.workout_date, l.val, l.reps
         FROM set_logs l
         JOIN exercises e ON e.id = l.exercise_id
         JOIN workouts w ON w.id = l.workout_id
         WHERE l.swimmer_id = ?
         ORDER BY e.name, w.workout_date, l.workout_id',
        array($swimmerId)
    );
    $out = array();
    foreach ($rows as $r) {
        $m = set_measure($r['input_type'], $r);
        if ($m === null) {
            continue;
        }
        $eid = (int)$r['exercise_id'];
        if (!isset($out[$eid])) {
            $out[$eid] = array('id' => $eid, 'name' => $r['name'], 'unit' => measure_unit($r), 'sessions' => array());
        }
        $wid = (int)$r['workout_id'];
        if (!isset($out[$eid]['sessions'][$wid])) {
            $out[$eid]['sessions'][$wid] = array('date' => $r['workout_date'], 'top' => $m);
        } elseif ($m > $out[$eid]['sessions'][$wid]['top']) {
            $out[$eid]['sessions'][$wid]['top'] = $m;
        }
    }
    foreach ($out as $eid => $e) {
        $pts = array();
        $best = null;
        $first = null;
        $latest = null;
        foreach ($e['sessions'] as $s) {
            $pts[] = array('label' => fmt_date($s['date'], 'n/j'), 'y' => $s['top']);
            if ($best === null || $s['top'] > $best) {
                $best = $s['top'];
            }
            if ($first === null) {
                $first = $s['top'];
            }
            $latest = $s['top'];
        }
        $out[$eid]['points'] = $pts;
        $out[$eid]['best'] = $best;
        $out[$eid]['first'] = $first;
        $out[$eid]['latest'] = $latest;
        $out[$eid]['n'] = count($pts);
    }
    return $out;
}

// % of planned sets a swimmer logged, for each session that has already happened.
function swimmer_participation($swimmerId)
{
    $counts = array();
    $rows = fetch_all('SELECT workout_id, COUNT(*) AS c FROM set_logs WHERE swimmer_id = ? GROUP BY workout_id', array($swimmerId));
    foreach ($rows as $r) {
        $counts[(int)$r['workout_id']] = (int)$r['c'];
    }
    $today = date('Y-m-d');
    $out = array();
    foreach (fetch_all('SELECT * FROM workouts ORDER BY workout_date, id') as $w) {
        if ($w['workout_date'] > $today) {
            continue;
        }
        $c = isset($counts[(int)$w['id']]) ? $counts[(int)$w['id']] : 0;
        $exp = max(1, expected_sets($w));
        $out[] = array(
            'label' => fmt_date($w['workout_date'], 'n/j'),
            'value' => min(100, (int)round(100 * $c / $exp)),
            'detail' => $c . ' of ' . $exp . ' sets',
        );
    }
    return $out;
}

// Exercises that at least one active swimmer has logged (for the team progress picker).
function exercises_with_logs()
{
    return fetch_all(
        'SELECT e.id, e.name, e.input_type, e.value_label, COUNT(*) AS n
         FROM set_logs l
         JOIN exercises e ON e.id = l.exercise_id
         JOIN swimmers s ON s.id = l.swimmer_id
         WHERE s.active = 1
         GROUP BY e.id, e.name, e.input_type, e.value_label
         ORDER BY e.name'
    );
}

// Team view of one exercise: per-session team average, plus each athlete's line and summary.
function team_exercise($exerciseId)
{
    $ex = fetch_one('SELECT * FROM exercises WHERE id = ?', array($exerciseId));
    if (!$ex) {
        return null;
    }
    $rows = fetch_all(
        'SELECT l.swimmer_id, s.first_name, s.last_name, l.workout_id, w.workout_date, l.val, l.reps
         FROM set_logs l
         JOIN swimmers s ON s.id = l.swimmer_id AND s.active = 1
         JOIN workouts w ON w.id = l.workout_id
         WHERE l.exercise_id = ?
         ORDER BY w.workout_date, l.workout_id',
        array($exerciseId)
    );
    $tops = array();
    $wdates = array();
    $names = array();
    foreach ($rows as $r) {
        $m = set_measure($ex['input_type'], $r);
        if ($m === null) {
            continue;
        }
        $sid = (int)$r['swimmer_id'];
        $wid = (int)$r['workout_id'];
        $wdates[$wid] = $r['workout_date'];
        $names[$sid] = swimmer_full($r);
        if (!isset($tops[$sid][$wid]) || $m > $tops[$sid][$wid]) {
            $tops[$sid][$wid] = $m;
        }
    }
    asort($wdates);
    $wids = array_keys($wdates);
    $labels = array();
    foreach ($wids as $wid) {
        $labels[] = fmt_date($wdates[$wid], 'n/j');
    }
    $avg = array();
    $counts = array();
    foreach ($wids as $wid) {
        $sum = 0;
        $n = 0;
        foreach ($tops as $byW) {
            if (isset($byW[$wid])) {
                $sum += $byW[$wid];
                $n++;
            }
        }
        $avg[] = $n ? round($sum / $n, 1) : null;
        $counts[] = $n;
    }
    $athletes = array();
    foreach ($tops as $sid => $byW) {
        $vals = array();
        $first = null;
        $latest = null;
        $best = null;
        $n = 0;
        foreach ($wids as $wid) {
            if (isset($byW[$wid])) {
                $v = $byW[$wid];
                $vals[] = $v;
                if ($first === null) {
                    $first = $v;
                }
                $latest = $v;
                if ($best === null || $v > $best) {
                    $best = $v;
                }
                $n++;
            } else {
                $vals[] = null;
            }
        }
        $athletes[] = array(
            'id' => $sid,
            'name' => $names[$sid],
            'values' => $vals,
            'first' => $first,
            'latest' => $latest,
            'best' => $best,
            'n' => $n,
            'change' => ($n >= 2) ? $latest - $first : null,
        );
    }
    usort($athletes, function ($a, $b) {
        if ($a['best'] == $b['best']) {
            return strcmp($a['name'], $b['name']);
        }
        return ($a['best'] > $b['best']) ? -1 : 1;
    });
    return array(
        'exercise' => $ex,
        'unit' => measure_unit($ex),
        'labels' => $labels,
        'avg' => $avg,
        'counts' => $counts,
        'athletes' => $athletes,
    );
}

// % of active swimmers who logged anything, for each session that has already happened.
function team_participation()
{
    $active = (int)q('SELECT COUNT(*) FROM swimmers WHERE active = 1')->fetchColumn();
    $by = array();
    $rows = fetch_all(
        'SELECT l.workout_id, COUNT(DISTINCT l.swimmer_id) AS c
         FROM set_logs l JOIN swimmers s ON s.id = l.swimmer_id AND s.active = 1
         GROUP BY l.workout_id'
    );
    foreach ($rows as $r) {
        $by[(int)$r['workout_id']] = (int)$r['c'];
    }
    $today = date('Y-m-d');
    $out = array();
    foreach (fetch_all('SELECT id, workout_date FROM workouts ORDER BY workout_date, id') as $w) {
        if ($w['workout_date'] > $today) {
            continue;
        }
        $c = isset($by[(int)$w['id']]) ? $by[(int)$w['id']] : 0;
        $out[] = array(
            'label' => fmt_date($w['workout_date'], 'n/j'),
            'value' => $active ? (int)round(100 * $c / $active) : 0,
            'detail' => $c . ' of ' . $active . ' swimmers',
        );
    }
    return $out;
}
