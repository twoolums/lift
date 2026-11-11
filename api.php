<?php
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jfail($code, $msg)
{
    http_response_code($code);
    echo json_encode(array('ok' => false, 'error' => $msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jfail(405, 'POST only');
}

try {
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) {
        jfail(400, 'Bad request');
    }
    if (!csrf_valid(isset($in['csrf']) ? $in['csrf'] : '')) {
        jfail(403, 'Session expired. Reload the page.');
    }

    // Who is being saved for?
    //  - a coach or an authorized kiosk may save for any (active) swimmer
    //  - a logged-in swimmer may only save for themselves
    $swimmerId = 0;
    $requested = isset($in['swimmer_id']) ? (int)$in['swimmer_id'] : 0;
    if ($requested > 0) {
        if (current_coach()) {
            $row = fetch_one('SELECT id FROM swimmers WHERE id = ?', array($requested));
            if ($row) {
                $swimmerId = (int)$row['id'];
            }
        } elseif (current_kiosk()) {
            $row = fetch_one('SELECT id FROM swimmers WHERE id = ? AND active = 1', array($requested));
            if ($row) {
                $swimmerId = (int)$row['id'];
            }
        } else {
            $me = current_swimmer();
            if ($me && (int)$me['id'] === $requested) {
                $swimmerId = $requested;
            } elseif ($me) {
                jfail(403, 'Not allowed');
            }
        }
    } else {
        $me = current_swimmer();
        if ($me) {
            $swimmerId = (int)$me['id'];
        }
    }
    if ($swimmerId < 1) {
        jfail(401, 'Please log in again.');
    }

    $wid = isset($in['workout_id']) ? (int)$in['workout_id'] : 0;
    $eid = isset($in['exercise_id']) ? (int)$in['exercise_id'] : 0;
    $set = isset($in['set_no']) ? (int)$in['set_no'] : 0;
    if ($wid < 1 || $eid < 1 || $set < 1 || $set > 20) {
        jfail(422, 'Invalid request');
    }

    $val = null;
    if (isset($in['val']) && $in['val'] !== '' && $in['val'] !== null) {
        if (!is_numeric($in['val'])) {
            jfail(422, 'Weight must be a number');
        }
        $val = round((float)$in['val'], 1);
        if ($val < 0 || $val > 2000) {
            jfail(422, 'Weight out of range');
        }
    }
    $reps = null;
    if (isset($in['reps']) && $in['reps'] !== '' && $in['reps'] !== null) {
        if (!is_numeric($in['reps'])) {
            jfail(422, 'Reps must be a number');
        }
        $reps = (int)round((float)$in['reps']);
        if ($reps < 0 || $reps > 1000) {
            jfail(422, 'Reps out of range');
        }
    }

    if (!fetch_one('SELECT id FROM workouts WHERE id = ?', array($wid))) {
        jfail(404, 'Session not found');
    }
    if (!fetch_one('SELECT id FROM exercises WHERE id = ?', array($eid))) {
        jfail(404, 'Exercise not found');
    }

    if ($val === null && $reps === null) {
        q(
            'DELETE FROM set_logs WHERE swimmer_id = ? AND workout_id = ? AND exercise_id = ? AND set_no = ?',
            array($swimmerId, $wid, $eid, $set)
        );
    } else {
        q(
            'INSERT INTO set_logs (swimmer_id, workout_id, exercise_id, set_no, val, reps, logged_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE val = VALUES(val), reps = VALUES(reps), logged_at = VALUES(logged_at)',
            array($swimmerId, $wid, $eid, $set, $val, $reps, now())
        );
    }
    echo json_encode(array('ok' => true));
} catch (Throwable $e) {
    jfail(500, 'Server error');
}
