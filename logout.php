<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_COOKIE['lifts_dt']) && strpos($_COOKIE['lifts_dt'], ':') !== false) {
        $parts = explode(':', $_COOKIE['lifts_dt'], 2);
        q('DELETE FROM device_tokens WHERE selector = ?', array($parts[0]));
    }
    clear_device_cookie();
    unset($_SESSION['swimmer_id']);
}
redirect('index.php');
