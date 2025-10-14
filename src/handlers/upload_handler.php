<?php
// src/handlers/upload_handler.php

function handle_upload_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_file' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "لطفاً فایل خود را ارسال کنید...");
}

function handle_received_file($message) {
    $user_id = $message['from']['id'];
    if (in_array($user_id, ADMIN_IDS)) {
        handle_admin_file_upload($message);
    } else {
        handle_user_file_submission($message);
    }
}

function handle_admin_file_upload($message) {
    $db = new Database();
    $user_id = $message['from']['id'];
    $chat_id = $message['chat']['id'];

    $file_info = extract_file_info($message);
    if (!$file_info) {
        sendMessage($chat_id, "نوع فایل پشتیبانی نمی‌شود.");
        return;
    }

    $code = generate_unique_code($db, 'files');
    $query = "INSERT INTO files (code, uploader_id, file_id, file_type, file_name, file_size, caption) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $params = [$code, $user_id, $file_info['file_id'], $file_info['file_type'], $file_info['file_name'], $file_info['file_size'], $file_info['caption']];
    $db->executeQuery($query, $params);

    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "فایل شما (به عنوان ادمین) با موفقیت آپلود شد!\n\nکد فایل: `" . $code . "`");
}

function handle_user_file_submission($message) {
    $db = new Database();
    $user_id = $message['from']['id'];
    $chat_id = $message['chat']['id'];

    $file_info = extract_file_info($message);
    if (!$file_info) {
        sendMessage($chat_id, "نوع فایل پشتیبانی نمی‌شود.");
        return;
    }

    $query = "INSERT INTO user_files (user_id, file_id, file_type, caption) VALUES (?, ?, ?, ?)";
    $params = [$user_id, $file_info['file_id'], $file_info['file_type'], $file_info['caption']];
    $db->executeQuery($query, $params);

    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "فایل شما با موفقیت برای بررسی ارسال شد. پس از تایید توسط ادمین، به شما اطلاع داده خواهد شد.");

    // Notify admins
    $pending_files_stmt = $db->executeQuery("SELECT COUNT(*) as count FROM user_files WHERE status = 'pending'");
    $pending_count = $pending_files_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    foreach (ADMIN_IDS as $admin_id) {
        sendMessage($admin_id, "یک فایل جدید برای تایید ارسال شده است. (" . $pending_count . " فایل در صف انتظار)");
    }
}

function extract_file_info($message) {
    $file_info = [
        'file_id'   => null, 'file_type' => null, 'file_name' => null,
        'file_size' => null, 'caption'   => $message['caption'] ?? null,
    ];

    if (isset($message['video'])) {
        $file = $message['video'];
        $file_info['file_type'] = 'video';
        $file_info['file_name'] = $file['file_name'] ?? 'video.mp4';
    } elseif (isset($message['document'])) {
        $file = $message['document'];
        $file_info['file_type'] = 'document';
        $file_info['file_name'] = $file['file_name'];
    } elseif (isset($message['audio'])) {
        $file = $message['audio'];
        $file_info['file_type'] = 'audio';
        $file_info['file_name'] = $file['file_name'] ?? 'audio.mp3';
    } elseif (isset($message['photo'])) {
        $file = end($message['photo']);
        $file_info['file_type'] = 'photo';
        $file_info['file_name'] = 'photo.jpg';
    } else {
        return null;
    }

    $file_info['file_id'] = $file['file_id'];
    $file_info['file_size'] = $file['file_size'] ?? 0;

    return $file_info;
}

function generate_unique_code($db, $table_name) {
    $code = '';
    do {
        $code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6);
        $stmt = $db->executeQuery("SELECT code FROM " . $table_name . " WHERE code = ?", [$code]);
    } while ($stmt->rowCount() > 0);
    return $code;
}
