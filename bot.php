<?php
// bot.php - Main Bot Controller

// --- 1. BOOTSTRAP ---
// Include all necessary files
require_once 'config/config.php';
require_once 'src/includes/database.php';
$LANG = require_once 'src/includes/lang.php'; // Load language file
require_once 'src/handlers/start_handler.php';
require_once 'src/handlers/upload_handler.php';
require_once 'src/handlers/search_handler.php';
require_once 'src/handlers/admin_handler.php';
require_once 'src/handlers/folder_handler.php';
require_once 'src/handlers/stats_handler.php';
require_once 'src/handlers/broadcast_handler.php';
require_once 'src/handlers/subscription_handler.php';
require_once 'src/handlers/media_handler.php';
require_once 'src/includes/utils.php'; // Include helper functions

// --- 2. INITIALIZE ---
$update = json_decode(file_get_contents('php://input'), true);
$db = new Database();

// --- 3. ROUTING ---

// Route Callback Queries (Inline Buttons)
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $callback_data = $callback_query['data'];
    $message_id = $callback_query['message']['message_id'];
    $message_text = $callback_query['message']['text'];

    answerCallbackQuery($callback_query['id']); // Acknowledge the press

    // User-facing callbacks
    if (strpos($callback_data, 'buy_plan_') === 0) {
        // ... (Payment simulation logic from previous step)
    } elseif (strpos($callback_data, 'like_') === 0) {
        // ... (Like logic)
    } elseif (strpos($callback_data, 'dislike_') === 0) {
        // ... (Dislike logic)
    } elseif (strpos($callback_data, 'search_page_') === 0) {
        $parts = explode('_', $callback_data, 4);
        $page = (int)$parts[2];
        $query = $parts[3];
        display_search_results($chat_id, $query, $page, $message_id);
    }

    // Admin-only callbacks
    if (isAdmin($user_id, $db)) {
        if (strpos($callback_data, 'approve_file_') === 0) {
            // ... (Approve logic)
        } elseif (strpos($callback_data, 'reject_file_') === 0) {
            // ... (Reject logic)
        } elseif (strpos($callback_data, 'set_password_') === 0) {
            $file_code = substr($callback_data, strlen('set_password_'));
            handle_set_password_request($chat_id, $user_id, $file_code);
            editMessageText($chat_id, $message_id, $message_text . "\n\n⏳ در انتظار رمز...");
        } elseif (strpos($callback_data, 'remove_password_') === 0) {
            $file_code = substr($callback_data, strlen('remove_password_'));
            handle_remove_password($chat_id, $file_code);
        } elseif (strpos($callback_data, 'set_limit_') === 0) {
            $file_code = substr($callback_data, strlen('set_limit_'));
            handle_set_limit_request($chat_id, $user_id, $file_code);
            editMessageText($chat_id, $message_id, $message_text . "\n\n⏳ در انتظار عدد محدودیت...");
        }
    }
}
// Route Regular Messages
elseif (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $first_name = $message['from']['first_name'];
    $text = $message['text'] ?? null;

    // Get user's current step
    $user_step = 'none';
    $stmt = $db->executeQuery("SELECT step FROM users WHERE id = ?", [$user_id]);
    if ($stmt->rowCount() > 0) {
        $user_step = $stmt->fetch(PDO::FETCH_ASSOC)['step'];
    }

    // A. Handle commands
    if ($text && strpos($text, '/') === 0) {
        $command = explode(' ', substr($text, 1))[0];
        switch ($command) {
            case 'start': handle_start($chat_id, $user_id, $first_name, $db); break;
            default: sendMessage($chat_id, "دستور ناشناخته است."); break;
        }
    }
    // B. Handle step-based logic
    elseif (strpos($user_step, 'awaiting_') === 0) {
        if ($user_step === 'awaiting_file') { handle_received_file($message); }
        elseif ($user_step === 'awaiting_search_query') { handle_search_query($chat_id, $user_id, $text); }
        elseif (isAdmin($user_id, $db)) {
            if ($user_step === 'awaiting_folder_name') { handle_folder_name_received($chat_id, $user_id, $text); }
            elseif (strpos($user_step, 'awaiting_folder_codes:') === 0) { $folder_name = substr($user_step, 24); handle_folder_codes_received($chat_id, $user_id, $folder_name, $text); }
            elseif ($user_step === 'awaiting_broadcast_message') { execute_broadcast($chat_id, $text); }
            elseif ($user_step === 'awaiting_file_code_for_management') { display_media_management_menu($chat_id, $text); }
            elseif (strpos($user_step, 'awaiting_password_for_') === 0) { $file_code = substr($user_step, 22); handle_password_received($chat_id, $user_id, $file_code, $text); }
            elseif (strpos($user_step, 'awaiting_limit_for_') === 0) { $file_code = substr($user_step, 19); handle_limit_received($chat_id, $user_id, $file_code, $text); }
        }
    }
    // C. Handle keyboard buttons
    elseif ($text) {
        if (strpos($text, '📁 ') === 0) { handle_folder_click($chat_id, $user_id, $text); return; }
        if (isAdmin($user_id, $db)) {
            $pending_button_text = 'فایل‌های در انتظار تایید 📥';
            $stmt = $db->executeQuery("SELECT COUNT(*) as count FROM user_files WHERE status = 'pending'");
            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) { $pending_button_text .= ' (' . $stmt->fetch(PDO::FETCH_ASSOC)['count'] . ')'; }
            switch ($text) {
                case 'پنل ادمین ⚙️': handle_admin_panel($chat_id); return;
                case $pending_button_text: handle_pending_files_request($chat_id); return;
                case 'مدیریت رسانه 🗂': handle_media_management($chat_id); return;
                case 'اطلاعات رسانه ℹ️': handle_media_info_request($chat_id, $user_id); return;
                case 'مدیریت پوشه‌ها 📁': handle_folder_management($chat_id); return;
                case 'ایجاد پوشه جدید ➕': handle_create_folder_request($chat_id, $user_id); return;
                case 'آمار ربات 📊': handle_stats_request($chat_id); return;
                case 'پیام همگانی  broadcast': handle_broadcast_request($chat_id, $user_id); return;
                case 'بازگشت به پنل ادمین 🔙': handle_admin_panel($chat_id); return;
            }
        }
        switch ($text) {
            case 'بازگشت به منوی اصلی 🏠': handle_start($chat_id, $user_id, $first_name); break;
            case 'آپلود فایل 📤': handle_upload_request($chat_id, $user_id); break;
            case 'جستجوی رسانه 🔎': handle_search_request($chat_id, $user_id); break;
            case 'خرید اشتراک 💰': handle_buy_subscription_request($chat_id); break;
            case 'حساب کاربری 👤': /* Logic to be added */ sendMessage($chat_id, "این قابلیت به زودی فعال می‌شود."); break;
            default: sendMessage($chat_id, "لطفاً از دکمه‌های منو استفاده کنید."); break;
        }
    } else { sendMessage($chat_id, "نوع پیام پشتیبانی نمی‌شود."); }
}


// --- 4. HELPER FUNCTIONS ---
function sendMessage($chat_id, $text, $reply_markup = null) {
    // ... implementation
}
function editMessageText($chat_id, $message_id, $text, $reply_markup = null) {
    // ... implementation
}
function answerCallbackQuery($callback_query_id, $text = '') {
    // ... implementation
}
// Note: The sendFile function is located in search_handler.php and is included from there.
