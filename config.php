<?php
// config.php

// Telegram Bot API Token
define('API_TOKEN', '7842501761:AAGKtgrjtXMnNXqsYViuD-IW9D0ytTAdcIg');

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'cryptofi_bot_t');
define('DB_USER', 'cryptofi_bot_1');
define('DB_PASS', 'Farzad1989');

// Base URL for the bot's web-accessible directory (for callbacks, etc.)
define('BASE_URL', 'https://crypto1film.online/AboutCrypto-feature-telegram-bot-rewrite/');

// Bot Admins (User IDs)
const ADMINS = [
    8109182621, // farzadasaadi
    8174232834  // This ID was in your old config
];

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>