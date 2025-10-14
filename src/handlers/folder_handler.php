<?php
// src/handlers/folder_handler.php

function handle_folder_click($chat_id, $user_id, $folder_name) {
    $db = new Database();
    $folder_name_clean = str_replace('📁 ', '', $folder_name);
    $stmt = $db->executeQuery("SELECT file_codes FROM folders WHERE name = ?", [$folder_name_clean]);

    if ($stmt->rowCount() > 0) {
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $file_codes = explode(',', $folder['file_codes']);

        if (empty($file_codes) || empty($file_codes[0])) {
            sendMessage($chat_id, "این پوشه خالی است.");
            return;
        }

        sendMessage($chat_id, "در حال بررسی فایل‌های پوشه **" . $folder_name_clean . "**...", null);

        foreach ($file_codes as $code) {
            $code = trim($code);
            // Instead of sending directly, call the central file request handler for each file
            handle_file_request($chat_id, $user_id, $code);
            usleep(300000); // 0.3 second delay
        }
    } else {
        sendMessage($chat_id, "پوشه مورد نظر یافت نشد.");
    }
}
