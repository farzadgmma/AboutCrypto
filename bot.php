<?php

// --- 1. BOOTSTRAP ---
require_once 'config/config.php';
require_once 'src/includes/database.php';
$LANG = require_once 'src/includes/lang.php';
require_once 'src/handlers/message_handler.php';
require_once 'src/handlers/callback_handler.php';
require_once 'src/includes/utils.php';
require_once 'src/includes/telegram_api.php';

// --- 2. INITIALIZE ---
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) {
    // No update received, likely a direct access attempt.
    exit('Silence is golden.');
}
$db = new Database();

// --- 3. ROUTING ---
if (isset($update['callback_query'])) {
    handle_callback_query($update, $db);
} elseif (isset($update['message'])) {
    handle_message($update, $db);
}
