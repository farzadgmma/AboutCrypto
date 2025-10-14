<?php
// src/handlers/start_handler.php

function handle_start($chat_id, $user_id, $first_name) {
    $db = new Database();

    // Check if user already exists
    $stmt = $db->executeQuery("SELECT id FROM users WHERE id = ?", [$user_id]);

    if ($stmt->rowCount() == 0) {
        // New user, insert into database
        $db->executeQuery("INSERT INTO users (id, first_name) VALUES (?, ?)", [$user_id, $first_name]);
        $welcome_message = "سلام " . $first_name . "! به ربات ما خوش آمدید. حساب شما با موفقیت ایجاد شد.";
    } else {
        // Existing user
        $welcome_message = "سلام مجدد " . $first_name . "! خوشحالیم که دوباره شما را می‌بینیم.";
    }

    // --- Dynamic Keyboard Generation ---

    // Base keyboard for all users
    $keyboard_rows = [
        [['text' => 'جستجوی رسانه 🔎']],
        [['text' => 'آپلود فایل 📤'], ['text' => 'حساب کاربری 👤']],
    ];

    // Fetch folders from the database
    $folders_stmt = $db->executeQuery("SELECT name FROM folders ORDER BY name ASC");
    $folders = $folders_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add a button for each folder
    $folder_row = [];
    foreach ($folders as $folder) {
        $folder_row[] = ['text' => '📁 ' . $folder['name']];
        // Create a new row after every 2 folder buttons
        if (count($folder_row) == 2) {
            $keyboard_rows[] = $folder_row;
            $folder_row = [];
        }
    }
    // Add any remaining folder buttons
    if (!empty($folder_row)) {
        $keyboard_rows[] = $folder_row;
    }

    // Check if the user is an admin and add the admin panel button
    if (in_array($user_id, ADMIN_IDS)) {
        $keyboard_rows[] = [['text' => 'پنل ادمین ⚙️']];
    }

    $final_keyboard = [
        'keyboard' => $keyboard_rows,
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];

    $encoded_keyboard = json_encode($final_keyboard);

    sendMessage($chat_id, $welcome_message, $encoded_keyboard);
}
