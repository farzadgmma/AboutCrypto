<?php
// src/handlers/upload_handler.php

/**
 * Handles the initial "Upload File" button press from a user.
 * Sets the user's step to 'awaiting_file'.
 */
function handle_upload_button($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_file' WHERE id = ?", [$user_id]);

    sendMessage($chat_id, "لطفاً فایل خود را ارسال کنید.");
}

/**
 * Handles a received file from a user whose step is 'awaiting_file'.
 * Stores the file info and notifies admins for approval.
 */
function handle_received_file($message) {
    $db = new Database();
    $user_id = $message['from']['id'];
    $chat_id = $message['chat']['id'];

    $file_id = null;
    $file_type = null;
    $caption = $message['caption'] ?? '';

    // Determine file type and get file_id
    if (isset($message['photo'])) {
        $file_id = $message['photo'][count($message['photo']) - 1]['file_id'];
        $file_type = 'photo';
    } elseif (isset($message['video'])) {
        $file_id = $message['video']['file_id'];
        $file_type = 'video';
    } elseif (isset($message['document'])) {
        $file_id = $message['document']['file_id'];
        $file_type = 'document';
    } // Add other file types as needed (audio, voice, etc.)

    if ($file_id) {
        // Insert into user_files table with 'pending' status
        $db->executeQuery(
            "INSERT INTO user_files (user_id, file_id, file_type, caption, status) VALUES (?, ?, ?, ?, 'pending')",
            [$user_id, $file_id, $file_type, $caption]
        );
        $file_db_id = $db->getDb()->lastInsertId();


        // Notify admins
        $notification_text = "یک فایل جدید توسط کاربر ID: $user_id برای تایید ارسال شده است.\n\nنوع فایل: $file_type";
        if (!empty($caption)) {
            $notification_text .= "\nکپشن: " . htmlspecialchars($caption);
        }

        $inline_keyboard = [
            [
                ['text' => '✅ تایید', 'callback_data' => 'approve_file_' . $file_db_id],
                ['text' => '❌ رد', 'callback_data' => 'reject_file_' . $file_db_id]
            ]
        ];
        $reply_markup = json_encode(['inline_keyboard' => $inline_keyboard]);

        // Fetch all admins from both config and database
        $all_admins = ADMIN_IDS;
        $admin_stmt = $db->executeQuery("SELECT user_id FROM admins");
        $db_admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
        $all_admins = array_unique(array_merge($all_admins, $db_admins));


        foreach ($all_admins as $admin_id) {
            sendMessage($admin_id, $notification_text, ['reply_markup' => $reply_markup]);
        }

        // Reset user's step and confirm receipt
        $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
        sendMessage($chat_id, "فایل شما دریافت شد و پس از بررسی توسط ادمین، در ربات قرار خواهد گرفت.");
    } else {
        sendMessage($chat_id, "نوع فایل پشتیبانی نمی‌شود. لطفاً عکس، ویدیو یا سند ارسال کنید.");
    }
}
