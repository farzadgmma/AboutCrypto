<?php
// src/handlers/admin_handler.php

/**
 * Displays the main admin panel menu.
 */
function handle_admin_panel($chat_id) {
    $db = new Database();
    $pending_files_stmt = $db->executeQuery("SELECT COUNT(*) as count FROM user_files WHERE status = 'pending'");
    $pending_count = $pending_files_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $pending_button_text = 'فایل‌های در انتظار تایید 📥';
    if ($pending_count > 0) {
        $pending_button_text .= ' (' . $pending_count . ')';
    }

    $keyboard = [
        'keyboard' => [
            [['text' => $pending_button_text]],
            [['text' => 'مدیریت رسانه 🗂'], ['text' => 'مدیریت پوشه‌ها 📁']],
            [['text' => 'آمار ربات 📊'], ['text' => 'پیام همگانی  broadcast']],
            [['text' => 'بازگشت به منوی اصلی 🏠']],
        ],
        'resize_keyboard' => true,
    ];
    $encoded_keyboard = json_encode($keyboard);
    sendMessage($chat_id, "به پنل مدیریت خوش آمدید.", $encoded_keyboard);
}

/**
 * Displays the media management menu.
 */
function handle_media_management($chat_id) {
    $keyboard = [
        'keyboard' => [
            [['text' => 'اطلاعات رسانه ℹ️']],
            // More buttons like 'Set Download Limit' will be added here later
            [['text' => 'بازگشت به پنل ادمین 🔙']],
        ],
        'resize_keyboard' => true,
    ];
    $encoded_keyboard = json_encode($keyboard);
    sendMessage($chat_id, "منوی مدیریت رسانه:", $encoded_keyboard);
}


/**
 * Displays the folder management menu.
 */
function handle_folder_management($chat_id) {
    $keyboard = [
        'keyboard' => [
            [['text' => 'ایجاد پوشه جدید ➕']],
            [['text' => 'لیست پوشه‌ها 📋']],
            [['text' => 'بازگشت به پنل ادمین 🔙']],
        ],
        'resize_keyboard' => true,
    ];
    $encoded_keyboard = json_encode($keyboard);
    sendMessage($chat_id, "منوی مدیریت پوشه‌ها:", $encoded_keyboard);
}

// ... rest of the folder management functions ...
function handle_create_folder_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_folder_name' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "لطفاً نام پوشه جدید را وارد کنید:");
}

function handle_folder_name_received($chat_id, $user_id, $folder_name) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = ? WHERE id = ?", ['awaiting_folder_codes:' . $folder_name, $user_id]);
    sendMessage($chat_id, "نام پوشه دریافت شد. حالا کدهای فایل‌ها را با کاما (,) جدا کرده و ارسال کنید.");
}

function handle_folder_codes_received($chat_id, $user_id, $folder_name, $file_codes_string) {
    $db = new Database();

    $input_codes = explode(',', $file_codes_string);
    $valid_codes = [];
    $invalid_codes = [];

    foreach ($input_codes as $code) {
        $trimmed_code = trim($code);
        if (!empty($trimmed_code)) {
            $stmt = $db->executeQuery("SELECT code FROM files WHERE code = ?", [$trimmed_code]);
            if ($stmt->rowCount() > 0) {
                $valid_codes[] = $trimmed_code;
            } else {
                $invalid_codes[] = $trimmed_code;
            }
        }
    }

    if (empty($valid_codes)) {
        sendMessage($chat_id, "خطا: هیچ‌کدام از کدهای وارد شده معتبر نبودند.");
    } else {
        $valid_codes_string = implode(',', $valid_codes);
        $db->executeQuery("INSERT INTO folders (name, file_codes) VALUES (?, ?)", [$folder_name, $valid_codes_string]);
        $response_message = "✅ پوشه '" . $folder_name . "' با موفقیت ایجاد شد.\n\n";
        if (!empty($invalid_codes)) {
            $response_message .= "⚠️ کدهای نامعتبر زیر نادیده گرفته شدند:\n`" . implode(', ', $invalid_codes) . "`";
        }
        sendMessage($chat_id, $response_message);
    }

    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
    handle_folder_management($chat_id);
}
