<?php
// Shared page layout helpers.

function asset($file)
{
    $path = __DIR__ . '/../assets/' . $file;
    $v = file_exists($path) ? filemtime($path) : 1;
    return url('assets/' . $file) . '?v=' . $v;
}

/**
 * Print the top of an HTML page.
 * $opts: nav => 'swimmer' | 'coach' | 'none', body => array of data attributes,
 *        topbar => bool (default true), bodyclass => string
 */
function page_header($title, $opts = array())
{
    $nav = isset($opts['nav']) ? $opts['nav'] : 'none';
    $topbar = array_key_exists('topbar', $opts) ? $opts['topbar'] : true;
    $bodyclass = isset($opts['bodyclass']) ? $opts['bodyclass'] : '';
    $bodyAttrs = isset($opts['body']) ? $opts['body'] : array();
    if (!isset($bodyAttrs['data-api'])) {
        $bodyAttrs['data-api'] = url('api.php');
    }
    if (!isset($bodyAttrs['data-csrf'])) {
        $bodyAttrs['data-csrf'] = csrf_token();
    }
    $attrs = '';
    foreach ($bodyAttrs as $k => $v) {
        $attrs .= ' ' . h($k) . '="' . h($v) . '"';
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
    echo '<meta name="theme-color" content="#1B3A5C">';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<title>' . h($title) . ' | ' . h(TEAM_NAME) . '</title>';
    echo '<link rel="stylesheet" href="' . h(asset('style.css')) . '">';
    echo '</head><body class="' . h($bodyclass . ' nav-' . $nav) . '"' . $attrs . '>';

    if ($topbar) {
        echo '<header class="topbar"><div class="topbar-in">';
        echo '<span class="brand">' . h(TEAM_NAME) . ' Lift Log</span>';
        if ($nav === 'coach') {
            echo '<nav class="coachnav">';
            echo '<a href="' . h(url('admin/index.php')) . '">Team</a>';
            echo '<a href="' . h(url('admin/progress.php')) . '">Progress</a>';
            echo '<a href="' . h(url('admin/kiosk.php')) . '">Kiosk</a>';
            echo '<a href="' . h(url('admin/sessions.php')) . '">Sessions</a>';
            echo '<a href="' . h(url('admin/program.php')) . '">Program</a>';
            echo '<a href="' . h(url('admin/exercises.php')) . '">Exercises</a>';
            echo '<a href="' . h(url('admin/coaches.php')) . '">Coaches</a>';
            echo '<a href="' . h(url('admin/export.php')) . '">Export</a>';
            echo '<form method="post" action="' . h(url('admin/login.php')) . '" class="inline">' . csrf_field()
                . '<input type="hidden" name="action" value="logout"><button class="linkbtn">Log out</button></form>';
            echo '</nav>';
        }
        echo '</div></header>';
    }
    if (!array_key_exists('main', $opts) || $opts['main']) {
        echo '<main class="wrap' . ($nav === 'coach' ? ' wide' : '') . '">';
    }
}

function page_footer($opts = array())
{
    $nav = isset($opts['nav']) ? $opts['nav'] : 'none';
    if (!array_key_exists('main', $opts) || $opts['main']) {
        echo '</main>';
    }
    if ($nav === 'swimmer') {
        $self = basename($_SERVER['SCRIPT_NAME']);
        echo '<nav class="tabbar">';
        echo '<a href="' . h(url('home.php')) . '" class="' . ($self === 'home.php' ? 'on' : '') . '"><span class="ti">&#127946;</span>Sessions</a>';
        echo '<a href="' . h(url('progress.php')) . '" class="' . ($self === 'progress.php' ? 'on' : '') . '"><span class="ti">&#128200;</span>Progress</a>';
        echo '<form method="post" action="' . h(url('logout.php')) . '">' . csrf_field()
            . '<button type="submit"><span class="ti">&#128682;</span>Log out</button></form>';
        echo '</nav>';
    }
    echo '<script src="' . h(asset('app.js')) . '"></script>';
    echo '</body></html>';
}
