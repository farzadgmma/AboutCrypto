<?php
// src/handlers/folder_handler.php

function handle_folder_click($chat_id, $user_id, $folder_name, $current_folder_id = null) {
    $db = new Database();
    $folder_name_clean = str_replace('📁 ', '', $folder_name);

    if ($folder_name_clean === '.. بازگشت') {
        if ($current_folder_id) {
            $stmt = $db->executeQuery("SELECT parent_id FROM folders WHERE id = ?", [$current_folder_id]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            display_folder_contents($chat_id, $parent['parent_id']);
        } else {
            // Already at root, so show main menu
            $main_menu_keyboard = get_main_menu_keyboard($user_id, $db);
            sendMessage($chat_id, "منوی اصلی:", json_encode($main_menu_keyboard));
        }
        return;
    }

    $query = "SELECT id FROM folders WHERE name = ? AND parent_id " . ($current_folder_id ? "= ?" : "IS NULL");
    $params = $current_folder_id ? [$folder_name_clean, $current_folder_id] : [$folder_name_clean];
    $stmt = $db->executeQuery($query, $params);

    if ($stmt->rowCount() > 0) {
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        display_folder_contents($chat_id, $folder['id'], $user_id);
    } else {
        sendMessage($chat_id, "پوشه مورد نظر یافت نشد.");
    }
}

function display_folder_contents($chat_id, $folder_id, $user_id = null) {
    $db = new Database();
    $keyboard_rows = [];

    // Get sub-folders
    $sub_folders_stmt = $db->executeQuery("SELECT name FROM folders WHERE parent_id = ? ORDER BY name ASC", [$folder_id]);
    $sub_folders = $sub_folders_stmt->fetchAll(PDO::FETCH_ASSOC);
    $folder_row = [];
    foreach ($sub_folders as $folder) {
        $folder_row[] = ['text' => '📁 ' . $folder['name']];
        if (count($folder_row) == 2) {
            $keyboard_rows[] = $folder_row;
            $folder_row = [];
        }
    }
    if (!empty($folder_row)) {
        $keyboard_rows[] = $folder_row;
    }

    // Get files in the current folder
    $files_stmt = $db->executeQuery("SELECT file_codes FROM folders WHERE id = ?", [$folder_id]);
    $file_codes_str = $files_stmt->fetchColumn();
    $file_codes = !empty($file_codes_str) ? explode(',', $file_codes_str) : [];

    // Add back button if not in the root
    if ($folder_id) {
        $keyboard_rows[] = [['text' => '📁 .. بازگشت']];
    } else {
         $keyboard_rows[] = [['text' => 'بازگشت به منوی اصلی 🏠']];
    }

    $db->executeQuery("UPDATE users SET current_folder_id = ? WHERE id = ?", [$folder_id, $chat_id]);

    $reply_markup = json_encode(['keyboard' => $keyboard_rows, 'resize_keyboard' => true]);
    sendMessage($chat_id, "پوشه فعلی:", $reply_markup);

    // If there are files, send them
    if (!empty($file_codes) && $user_id) {
        sendMessage($chat_id, "در حال ارسال فایل‌های این پوشه...");
        foreach ($file_codes as $code) {
            $code = trim($code);
            if (!empty($code)) {
                handle_file_request($chat_id, $user_id, $code);
                usleep(300000); // 0.3-second delay to avoid rate limiting
            }
        }
    } elseif (empty($sub_folders) && empty($file_codes)) {
        sendMessage($chat_id, "این پوشه خالی است.");
    }
}
