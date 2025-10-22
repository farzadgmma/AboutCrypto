<?php
// config/config.php

// --- Telegram Bot API Settings ---
define('BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN');

// --- Database Settings ---
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_NAME', 'telegram_bot');

// --- Admin User IDs ---
// Add your numeric Telegram User ID here to grant admin privileges.
const ADMIN_IDS = [
    63583254,    // Example Admin ID 1
    // 123456789, // Example Admin ID 2
];

// --- Web Application URL ---
// The base URL of your web server where payment callbacks are handled.
// Example: https://yourdomain.com/bot_directory
define('BASE_URL', 'http://yourwebsite.com');
