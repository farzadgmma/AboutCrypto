<?php
// src/handlers/start_handler.php

function handle_start($chat_id, $user_id, $first_name, $db, $text = null) {
    // Check for deep linking for file downloads
    if ($text && preg_match('/^\/start dl_([a-zA-Z0-9]+)$/', $text, $matches)) {
        $file_code = $matches[1];
        handle_file_request($chat_id, $user_id, $file_code);
        return;
    }

    // Check if user already exists
    $stmt = $db->executeQuery("SELECT id, first_name, download_count, join_date FROM users WHERE id = ?", [$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch bot settings for dynamic content
    $settings_stmt = $db->executeQuery("SELECT * FROM settings WHERE id = 1");
    $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // New user
        $join_date = date('Y-m-d');
        $db->executeQuery(
            "INSERT INTO users (id, first_name, join_date) VALUES (?, ?, ?)",
            [$user_id, $first_name, $join_date]
        );
        $download_count = 0;
    } else {
        // Existing user
        $join_date = $user['join_date'];
        $download_count = $user['download_count'];
        // Update name if changed
        if ($user['first_name'] !== $first_name) {
            $db->executeQuery("UPDATE users SET first_name = ? WHERE id = ?", [$first_name, $user_id]);
        }
    }

    // Clear user step to return them to the main menu
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);

    // Prepare the welcome message
    $welcome_message = "خوش آمدید!"; // Default message
    if ($settings['startdefault'] === 'on' && !empty($settings['starttext'])) {
        // Replace placeholders with actual data
        $welcome_message = str_replace(
            ['{first_name}', '{user_id}', '{join_date}', '{download_count}', '{time}', '{date}'],
            [$first_name, $user_id, $join_date, $download_count, date('H:i:s'), date('Y-m-d')],
            $settings['starttext']
        );
    } elseif (!empty($settings['starttext'])) {
         $welcome_message = $settings['starttext'];
    }


    // --- Dynamic Keyboard Generation ---
    $keyboard_rows = [];

    // Folder buttons (root level only)
    $folders_stmt = $db->executeQuery("SELECT name FROM folders WHERE parent_id IS NULL ORDER BY name ASC");
    $folders = $folders_stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($folders) {
        $folder_rows = array_chunk(array_map(function($f) { return ['text' => '📁 ' . $f['name']]; }, $folders), 2);
        $keyboard_rows = array_merge($keyboard_rows, $folder_rows);
    }

    // Main menu buttons based on settings
    if ($settings['sendbut'] === 'on') {
        $keyboard_rows[] = [['text' => 'آپلود فایل 📤']];
    }

    $row_2 = [];
    if ($settings['accountbut'] === 'on') {
        $row_2[] = ['text' => 'حساب کاربری 👤'];
    }
    if ($settings['subbuy'] === 'on') {
        $row_2[] = ['text' => 'خرید اشتراک 💰'];
    }
    if (!empty($row_2)) {
        $keyboard_rows[] = $row_2;
    }

    $row_3 = [];
    if ($settings['newdlbut'] === 'on') {
        $row_3[] = ['text' => 'جدیدترین ها 🌀'];
    }
    if ($settings['topdlbut'] === 'on') {
        $row_3[] = ['text' => 'پربازدید ها 🔆'];
    }
    if ($settings['likedlbut'] === 'on') {
        $row_3[] = ['text' => 'محبوب ترین ها ♥️'];
    }
     if (!empty($row_3)) {
        $keyboard_rows[] = $row_3;
    }

    if ($settings['supportbut'] === 'on') {
        $keyboard_rows[] = [['text' => 'پشتیبانی 👨🏼‍💻']];
    }

    // Add Search Button (always visible for now)
    $keyboard_rows[] = [['text' => 'جستجوی رسانه 🔎']];


    // Check if the user is an admin and add the admin panel button
    if (isAdmin($user_id, $db)) {
        $keyboard_rows[] = [['text' => 'پنل ادمین ⚙️']];
    }

    $final_keyboard = [
        'keyboard' => $keyboard_rows,
        'resize_keyboard' => true,
    ];

    sendMessage($chat_id, $welcome_message, ['reply_markup' => json_encode($final_keyboard)]);
}

/**
 * Handles a user's request to download/view a file.
 * This is the central function that gets called after a /start dl_xxxx command,
 * or after a user successfully passes a forced interaction check.
 */
function handle_file_request($chat_id, $user_id, $file_code) {
    global $db, $LANG;

    // First, always check for forced join, as it's the highest priority.
    if (!check_forced_join($user_id)) {
        return; // Stop if the user needs to join channels.
    }

    // Second, check for forced seen. Pass the file_code for the callback.
    if (!check_forced_seen($user_id, $file_code)) {
        return; // Stop if the user needs to view posts.
    }

    // Third, check for forced reaction.
    if (!check_forced_reaction($user_id, $file_code)) {
        return; // Stop if the user needs to react to posts.
    }

    // If all checks pass, proceed to send the file.
    // (This part will be expanded to include password checks, limits, etc.)

    $stmt = $db->executeQuery("SELECT * FROM files WHERE code = ?", [$file_code]);
    if ($stmt->rowCount() > 0) {
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        // For now, a simple send. This will be replaced by the full media_handler logic.
        sendFile($chat_id, $file['file_id'], $file['file_type'], $file['caption']);

        // Increment download count
        $db->executeQuery("UPDATE files SET download_count = download_count + 1 WHERE id = ?", [$file['id']]);
        $db->executeQuery("UPDATE users SET download_count = download_count + 1 WHERE id = ?", [$user_id]);

    } else {
        sendMessage($chat_id, $LANG['file_not_found']);
    }
}
