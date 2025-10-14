<?php
// src/handlers/upload_handler.php

/**
 * Handles the initial request to upload a file.
 * Sets the user's step to 'awaiting_file'.
 */
function handle_upload_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_file' WHERE id = ?", [$user_id]);

    sendMessage($chat_id, "لطفاً فایل خود را ارسال کنید...");
}

/**
 * Handles a received file from a user.
 * Generates a unique code and saves file info to the database.
 */
function handle_received_file($message) {
    $db = new Database();
    $user_id = $message['from']['id'];
    $chat_id = $message['chat']['id'];

    $file = null;
    $file_type = null;
    $file_name = null;
    $file_size = null;

    // Determine file type and get file_id
    if (isset($message['video'])) {
        $file = $message['video'];
        $file_type = 'video';
        $file_name = $file['file_name'] ?? 'video_file';
    } elseif (isset($message['document'])) {
        $file = $message['document'];
        $file_type = 'document';
        $file_name = $file['file_name'];
    } elseif (isset($message['audio'])) {
        $file = $message['audio'];
        $file_type = 'audio';
        $file_name = $file['file_name'] ?? 'audio_file';
    } elseif (isset($message['photo'])) {
        $file = end($message['photo']); // Get the highest resolution photo
        $file_type = 'photo';
        $file_name = 'photo_file';
    } else {
        sendMessage($chat_id, "نوع فایل پشتیبانی نمی‌شود.");
        return;
    }

    $file_id = $file['file_id'];
    $file_size = $file['file_size'];

    // Generate a unique 6-character code
    $is_unique = false;
    $code = '';
    while (!$is_unique) {
        $code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
        $stmt = $db->executeQuery("SELECT code FROM files WHERE code = ?", [$code]);
        if ($stmt->rowCount() == 0) {
            $is_unique = true;
        }
    }

    // Save file info to the database
    $query = "INSERT INTO files (code, uploader_id, file_id, file_type, file_name, file_size) VALUES (?, ?, ?, ?, ?, ?)";
    $params = [$code, $user_id, $file_id, $file_type, $file_name, $file_size];
    $db->executeQuery($query, $params);

    // Reset user step and notify them
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "فایل شما با موفقیت آپلود شد!\n\nکد فایل شما: `" . $code . "`");
}
