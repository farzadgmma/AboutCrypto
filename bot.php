<?php
// bot.php

// Include necessary files
require_once 'config/config.php';
require_once 'src/includes/database.php';
require_once 'src/handlers/start_handler.php';
require_once 'src/handlers/upload_handler.php';
require_once 'src/handlers/search_handler.php';
require_once 'src/handlers/admin_handler.php';
require_once 'src/handlers/folder_handler.php';
require_once 'src/handlers/stats_handler.php';
require_once 'src/handlers/broadcast_handler.php'; // Include the broadcast handler

// --- Main Bot Logic ---
$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $first_name = $message['from']['first_name'];
    $text = $message['text'] ?? null;

    $db = new Database();
    $user_step = 'none';
    $stmt = $db->executeQuery("SELECT step FROM users WHERE id = ?", [$user_id]);
    if ($stmt->rowCount() > 0) {
        $user_step = $stmt->fetch(PDO::FETCH_ASSOC)['step'];
    }

    // --- Message Routing ---
    if ($text && strpos($text, '/') === 0) {
        $command = explode(' ', substr($text, 1))[0];
        switch ($command) {
            case 'start':
                handle_start($chat_id, $user_id, $first_name);
                break;
            default:
                sendMessage($chat_id, "دستور ناشناخته است.");
                break;
        }
    }
    // --- Step-based Logic ---
    elseif (strpos($user_step, 'awaiting_') === 0) {
        if ($user_step === 'awaiting_file') {
            handle_received_file($message);
        }
        elseif ($user_step === 'awaiting_search_query') {
            handle_search_query($chat_id, $text);
        }
        elseif (in_array($user_id, ADMIN_IDS)) { // Admin-specific steps
            if ($user_step === 'awaiting_folder_name') {
                handle_folder_name_received($chat_id, $user_id, $text);
            }
            elseif (strpos($user_step, 'awaiting_folder_codes:') === 0) {
                $folder_name = substr($user_step, strlen('awaiting_folder_codes:'));
                handle_folder_codes_received($chat_id, $user_id, $folder_name, $text);
            }
            elseif ($user_step === 'awaiting_broadcast_message') {
                execute_broadcast($chat_id, $text);
            }
        }
    }
    // --- Keyboard Button Logic ---
    elseif ($text) {
        // Handle folder clicks first
        if (strpos($text, '📁 ') === 0) {
            handle_folder_click($chat_id, $text);
            return;
        }

        // Admin-only buttons
        if (in_array($user_id, ADMIN_IDS)) {
            switch ($text) {
                case 'پنل ادمین ⚙️':
                    handle_admin_panel($chat_id);
                    return;
                case 'مدیریت پوشه‌ها 📁':
                    handle_folder_management($chat_id);
                    return;
                case 'ایجاد پوشه جدید ➕':
                    handle_create_folder_request($chat_id, $user_id);
                    return;
                case 'آمار ربات 📊':
                    handle_stats_request($chat_id);
                    return;
                case 'پیام همگانی  broadcast':
                    handle_broadcast_request($chat_id, $user_id);
                    return;
                case 'بازگشت به پنل ادمین 🔙':
                    handle_admin_panel($chat_id);
                    return;
            }
        }

        // Buttons for all users
        switch ($text) {
            case 'بازگشت به منوی اصلی 🏠':
                handle_start($chat_id, $user_id, $first_name);
                break;
            case 'آپلود فایل 📤':
                handle_upload_request($chat_id, $user_id);
                break;
            case 'جستجوی رسانه 🔎':
                handle_search_request($chat_id, $user_id);
                break;
            case 'حساب کاربری 👤':
                sendMessage($chat_id, "این قابلیت به زودی فعال می‌شود.");
                break;
            default:
                sendMessage($chat_id, "لطفاً از دکمه‌های منو استفاده کنید.");
                break;
        }
    }
    else {
         sendMessage($chat_id, "نوع پیام پشتیبانی نمی‌شود.");
    }
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }
    $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($data)]];
    $context  = stream_context_create($options);
    @file_get_contents($url, false, $context);
}
