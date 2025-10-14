<?php
// src/handlers/admin_handler.php

/**
 * Displays the main admin panel menu.
 */
function handle_admin_panel($chat_id) {
    $keyboard = [
        'keyboard' => [
            [['text' => 'مدیریت پوشه‌ها 📁']],
            [['text' => 'آمار ربات 📊'], ['text' => 'پیام همگانی  broadcast']],
            [['text' => 'بازگشت به منوی اصلی 🏠']],
        ],
        'resize_keyboard' => true,
    ];
    $encoded_keyboard = json_encode($keyboard);
    sendMessage($chat_id, "به پنل مدیریت خوش آمدید.", $encoded_keyboard);
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

/**
 * Initiates the folder creation process by asking for a folder name.
 */
function handle_create_folder_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_folder_name' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "لطفاً نام پوشه جدید را وارد کنید:");
}

/**
 * Receives the folder name and asks for file codes.
 */
function handle_folder_name_received($chat_id, $user_id, $folder_name) {
    $db = new Database();
    // Temporarily store the folder name in the step column
    $db->executeQuery("UPDATE users SET step = ? WHERE id = ?", ['awaiting_folder_codes:' . $folder_name, $user_id]);
    sendMessage($chat_id, "نام پوشه دریافت شد. حالا کدهای فایل‌ها را با کاما (,) از هم جدا کرده و ارسال کنید.\n\nمثال: `code1,code2,code3`");
}

/**
 * Receives file codes, validates them, creates the folder, and saves it to the database.
 */
function handle_folder_codes_received($chat_id, $user_id, $folder_name, $file_codes_string) {
    $db = new Database();

    $input_codes = explode(',', $file_codes_string);
    $valid_codes = [];
    $invalid_codes = [];

    // Validate each code
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
        sendMessage($chat_id, "خطا: هیچ‌کدام از کدهای وارد شده معتبر نبودند. پوشه ایجاد نشد.");
        $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
        handle_folder_management($chat_id);
        return;
    }

    // Insert the new folder with only valid codes
    $valid_codes_string = implode(',', $valid_codes);
    $query = "INSERT INTO folders (name, file_codes) VALUES (?, ?)";
    $params = [$folder_name, $valid_codes_string];
    $stmt = $db->executeQuery($query, $params);

    $response_message = "";
    if ($stmt) {
        $response_message .= "✅ پوشه '" . $folder_name . "' با موفقیت با " . count($valid_codes) . " فایل معتبر ایجاد شد.\n\n";
    } else {
        $response_message .= "❌ خطایی در ایجاد پوشه رخ داد. ممکن است نام پوشه تکراری باشد.\n\n";
    }

    if (!empty($invalid_codes)) {
        $response_message .= "⚠️ کدهای نامعتبر زیر نادیده گرفته شدند:\n`" . implode(', ', $invalid_codes) . "`";
    }

    sendMessage($chat_id, $response_message);

    // Reset user step
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
    // Show the folder management menu again
    handle_folder_management($chat_id);
}
