<?php
// src/handlers/media_handler.php

// ... (previous functions in this file)

/**
 * A helper function that sends the file and increments the download count.
 * Now also includes like/dislike buttons.
 */
function send_file_to_user($chat_id, $file) {
    // Prepare like/dislike buttons
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '👍 (' . $file['likes'] . ')', 'callback_data' => 'like_' . $file['code']],
                ['text' => '👎 (' . $file['dislikes'] . ')', 'callback_data' => 'dislike_' . $file['code']],
            ],
            // We can add the report button here later
            // [['text' => 'گزارش خرابی 🚨', 'callback_data' => 'report_' . $file['code']]]
        ]
    ];
    $encoded_keyboard = json_encode($keyboard);

    // Send the file using the generic sendFile function
    sendFile($chat_id, $file['file_id'], $file['file_type'], $file['caption'], $encoded_keyboard);

    // Increment the download count
    $db = new Database();
    $db->executeQuery("UPDATE files SET download_count = download_count + 1 WHERE id = ?", [$file['id']]);
}

// ... (rest of the functions in this file)
