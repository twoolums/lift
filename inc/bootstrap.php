<?php
// Core bootstrap: config, session, database, helpers, authentication.
// Requires PHP 7.4+ with the pdo_mysql extension.

if (!file_exists(__DIR__ . '/../config.php')) {
    http_response_code(500);
    exit('Missing config.php');
}
require_once __DIR__ . '/../config.php';

date_default_timezone_set(defined('APP_TZ') ? APP_TZ : 'America/Chicago');

define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'));

function compute_base()
{
    if (defined('BASE_URL_OVERRIDE') && BASE_URL_OVERRIDE !== '') {
        return rtrim(BASE_URL_OVERRIDE, '/');
    }
    $docroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appdir = realpath(__DIR__ . '/..');
    if ($docroot && $appdir) {
        $docroot = rtrim(str_replace('\\', '/', $docroot), '/');
        $appdir = str_replace('\\', '/', $appdir);
        if (strpos($appdir, $docroot) === 0) {
            return substr($appdir, strlen($docroot));
        }
    }
    return '';
}
define('BASE', compute_base());

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('LIFTSESS');
session_set_cookie_params(array(
    'lifetime' => 0,
    'path' => (BASE === '' ? '/' : BASE . '/'),
    'secure' => IS_HTTPS,
    'httponly' => true,
    'samesite' => 'Lax',
));
session_start();

// ---------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function url($path = '')
{
    return BASE . '/' . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

function now()
{
    return date('Y-m-d H:i:s');
}

function fmt_date($ymd, $fmt = 'D M j')
{
    return date($fmt, strtotime($ymd));
}

// Format a DECIMAL like "135.0" as "135" and "2.5" as "2.5"
function num($v)
{
    if ($v === null || $v === '') {
        return '';
    }
    $f = (float)$v;
    if (floor($f) == $f) {
        return (string)(int)$f;
    }
    return rtrim(rtrim(number_format($f, 1, '.', ''), '0'), '.');
}

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));
    }
    return $pdo;
}

function q($sql, $params = array())
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function fetch_all($sql, $params = array())
{
    return q($sql, $params)->fetchAll();
}

function fetch_one($sql, $params = array())
{
    $r = q($sql, $params)->fetch();
    return ($r === false) ? null : $r;
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid($t)
{
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function csrf_check()
{
    $t = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!csrf_valid($t)) {
        http_response_code(400);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

// ---------------------------------------------------------------
// Swimmer PINs: 4 digits, assigned by the coach, stored hashed
// ---------------------------------------------------------------
function generate_pin()
{
    do {
        $p = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        // skip repeats like 7777 and straight runs like 1234 or 4321
        $bad = preg_match('/^(\d)\1{3}$/', $p)
            || strpos('01234567890', $p) !== false
            || strpos('09876543210', $p) !== false;
    } while ($bad);
    return $p;
}

function normalize_pin($s)
{
    return preg_replace('/\D/', '', (string)$s);
}

// Give a swimmer a fresh PIN (also unlocks them and signs them out everywhere). Returns the PIN to print.
function set_swimmer_pin($swimmerId)
{
    $pin = generate_pin();
    q(
        'UPDATE swimmers SET pin_hash = ?, failed_pins = 0, locked_until = NULL WHERE id = ?',
        array(password_hash($pin, PASSWORD_DEFAULT), $swimmerId)
    );
    q('DELETE FROM device_tokens WHERE swimmer_id = ?', array($swimmerId));
    return $pin;
}

// id => label for a dropdown. Uses "First L." unless $full, and falls back to full names for duplicates.
function swimmer_labels($swimmers, $full = false)
{
    $short = array();
    foreach ($swimmers as $s) {
        $short[$s['id']] = $full ? swimmer_full($s) : swimmer_short($s);
    }
    $counts = array_count_values($short);
    $out = array();
    $seen = array();
    foreach ($swimmers as $s) {
        $label = $short[$s['id']];
        if ($counts[$label] > 1) {
            $label = swimmer_full($s);
        }
        if (isset($seen[$label])) {
            $label .= ' #' . $s['id'];
        }
        $seen[$label] = true;
        $out[$s['id']] = $label;
    }
    return $out;
}

// ---------------------------------------------------------------
// Login throttling (generous because a whole team may share school wifi)
// ---------------------------------------------------------------
function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '0.0.0.0';
}

function too_many_attempts()
{
    $since = date('Y-m-d H:i:s', time() - 900);
    $n = (int)q('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', array(client_ip(), $since))->fetchColumn();
    return $n >= 30;
}

function record_failed_attempt()
{
    q('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)', array(client_ip(), now()));
    q('DELETE FROM login_attempts WHERE attempted_at < ?', array(date('Y-m-d H:i:s', time() - 86400)));
}

// ---------------------------------------------------------------
// Swimmer authentication (session + "remember this device" cookie)
// ---------------------------------------------------------------
function cookie_path()
{
    return BASE === '' ? '/' : BASE . '/';
}

function issue_device_token($swimmer_id)
{
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(24));
    $exp = time() + 90 * 86400;
    q(
        'INSERT INTO device_tokens (swimmer_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)',
        array($swimmer_id, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $exp))
    );
    setcookie('lifts_dt', $selector . ':' . $validator, array(
        'expires' => $exp,
        'path' => cookie_path(),
        'secure' => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
}

function clear_device_cookie()
{
    setcookie('lifts_dt', '', array(
        'expires' => time() - 3600,
        'path' => cookie_path(),
        'secure' => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
}

function restore_from_device_cookie()
{
    if (empty($_COOKIE['lifts_dt']) || strpos($_COOKIE['lifts_dt'], ':') === false) {
        return;
    }
    $parts = explode(':', $_COOKIE['lifts_dt'], 2);
    $sel = $parts[0];
    $val = $parts[1];
    $row = fetch_one('SELECT * FROM device_tokens WHERE selector = ? AND expires_at > ?', array($sel, now()));
    if ($row && hash_equals($row['validator_hash'], hash('sha256', $val))) {
        $sw = fetch_one('SELECT id FROM swimmers WHERE id = ? AND active = 1', array($row['swimmer_id']));
        if ($sw) {
            session_regenerate_id(true);
            $_SESSION['swimmer_id'] = (int)$sw['id'];
            return;
        }
    }
    clear_device_cookie();
}

function current_swimmer()
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    if (empty($_SESSION['swimmer_id'])) {
        restore_from_device_cookie();
    }
    $cache = null;
    if (!empty($_SESSION['swimmer_id'])) {
        $cache = fetch_one('SELECT * FROM swimmers WHERE id = ? AND active = 1', array($_SESSION['swimmer_id']));
        if (!$cache) {
            unset($_SESSION['swimmer_id']);
        }
    }
    return $cache;
}

function require_swimmer()
{
    $sw = current_swimmer();
    if (!$sw) {
        redirect('index.php');
    }
    return $sw;
}

function swimmer_short($sw)
{
    $first = function_exists('mb_substr') ? mb_substr($sw['last_name'], 0, 1) : substr($sw['last_name'], 0, 1);
    $initial = $sw['last_name'] !== '' ? ' ' . $first . '.' : '';
    return $sw['first_name'] . $initial;
}

function swimmer_full($sw)
{
    return trim($sw['first_name'] . ' ' . $sw['last_name']);
}

// ---------------------------------------------------------------
// Weight-room kiosk: a device a coach has authorized (long-lived cookie)
// ---------------------------------------------------------------
function issue_kiosk_token($label)
{
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(24));
    $exp = time() + 180 * 86400;
    q(
        'INSERT INTO kiosk_devices (selector, validator_hash, label, created_at, expires_at) VALUES (?, ?, ?, ?, ?)',
        array($selector, hash('sha256', $validator), substr($label, 0, 60), now(), date('Y-m-d H:i:s', $exp))
    );
    setcookie('lifts_kiosk', $selector . ':' . $validator, array(
        'expires' => $exp,
        'path' => cookie_path(),
        'secure' => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
}

function current_kiosk()
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!empty($_COOKIE['lifts_kiosk']) && strpos($_COOKIE['lifts_kiosk'], ':') !== false) {
        $parts = explode(':', $_COOKIE['lifts_kiosk'], 2);
        $row = fetch_one('SELECT * FROM kiosk_devices WHERE selector = ? AND expires_at > ?', array($parts[0], now()));
        if ($row && hash_equals($row['validator_hash'], hash('sha256', $parts[1]))) {
            $cache = $row;
        }
    }
    return $cache;
}

// ---------------------------------------------------------------
// Coach authentication
// ---------------------------------------------------------------
function current_coach()
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!empty($_SESSION['coach_id'])) {
        $last = isset($_SESSION['coach_last']) ? (int)$_SESSION['coach_last'] : 0;
        if ($last && time() - $last > 8 * 3600) {
            unset($_SESSION['coach_id'], $_SESSION['coach_last']);
        } else {
            $cache = fetch_one('SELECT id, username FROM coaches WHERE id = ?', array($_SESSION['coach_id']));
            if ($cache) {
                $_SESSION['coach_last'] = time();
            } else {
                unset($_SESSION['coach_id']);
            }
        }
    }
    return $cache;
}

function require_coach()
{
    $c = current_coach();
    if (!$c) {
        redirect('admin/login.php');
    }
    return $c;
}

// ---------------------------------------------------------------
// Program helpers (what to show for each scheduled session)
// ---------------------------------------------------------------
function day_type_name($t)
{
    $names = array(
        'A' => 'Day A: Lower Strength & Jumps',
        'B' => 'Day B: Upper Push/Pull',
        'C' => 'Day C: Kinetic Chain',
        'P' => 'Meet-Day Primer',
    );
    return isset($names[$t]) ? $names[$t] : $t;
}

function load_program_items()
{
    static $cache = null;
    if ($cache === null) {
        $rows = fetch_all(
            'SELECT pi.*, e.name, e.input_type, e.value_label, e.step, e.cue, e.link_url
             FROM program_items pi JOIN exercises e ON e.id = pi.exercise_id
             ORDER BY pi.day_type, pi.phase, pi.position, pi.id'
        );
        $cache = array();
        foreach ($rows as $r) {
            $cache[$r['day_type'] . '|' . $r['phase']][] = $r;
        }
    }
    return $cache;
}

// Starred exercises are skipped on short (under 60 minute) sessions.
function items_for_workout($w)
{
    $all = load_program_items();
    $key = $w['day_type'] . '|' . $w['phase'];
    $items = isset($all[$key]) ? $all[$key] : array();
    $out = array();
    foreach ($items as $it) {
        if ((int)$it['starred'] === 1 && (int)$w['length_min'] < 60) {
            continue;
        }
        $out[] = $it;
    }
    return $out;
}

function expected_sets($w)
{
    $n = 0;
    foreach (items_for_workout($w) as $it) {
        $n += (int)$it['sets'];
    }
    return $n;
}

function app_origin_url()
{
    $scheme = IS_HTTPS ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'yourwebsite.com';
    return $scheme . '://' . $host . url('');
}

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/stats.php';
