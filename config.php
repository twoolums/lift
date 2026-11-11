<?php
// ---------------------------------------------------------------
// Swim Lift Log: settings. Edit the values below for your host.
// ---------------------------------------------------------------

// Database (create an empty MySQL database + user in your hosting control panel)
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Shown at the top of every page and on the printed login cards
define('TEAM_NAME', 'Swim Team');

// Time zone used to decide what "today" is
define('APP_TZ', 'America/Chicago');

// One-time key required to create the first coach account (admin/setup.php).
// Change this to your own random phrase before uploading.
define('SETUP_KEY', 'change-this-before-you-upload');

// Leave empty to auto-detect the folder the app lives in (for example /lifts).
// Only set this if links come out wrong, e.g. '/lifts'.
define('BASE_URL_OVERRIDE', '');
