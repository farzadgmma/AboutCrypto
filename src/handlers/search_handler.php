<?php
// src/handlers/search_handler.php

/**
 * Handles the initial request to search for a file.
 * Sets the user's step to 'awaiting_search_query'.
 */
function handle_search_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_search_query' WHERE id = ?", [$user_id]);

    sendMessage($chat_id, "لطفاً کد یا نام فایل مورد نظر خود را برای جستجو وارد کنید:");
}

/**
 * Handles a search query from a user.
 * Searches the database and sends the file if found.
 */
function handle_search_query($chat_id, $query_text) {
    $db = new Database();

    // Search in 'code' or 'file_name' columns
    $query = "SELECT * FROM files WHERE code = ? OR file_name LIKE ?";
    $params = [$query_text, '%' . $query_text . '%'];
    $stmt = $db->executeQuery($query, $params);

    if ($stmt->rowCount() > 0) {
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        // We found a file, now send it based on its type
        sendFile($chat_id, $file['file_id'], $file['file_type'], $file['caption']);

    } else {
        sendMessage($chat_id, "فایلی با این مشخصات یافت نشد.");
    }

    // Reset user step after search
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$chat_id]);
}

/**
 * A helper function to send files of different types.
 */
function sendFile($chat_id, $file_id, $file_type, $caption = null) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/send' . ucfirst($file_type);

    $data = [
        'chat_id' => $chat_id,
        $file_type => $file_id,
        'caption' => $caption
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context  = stream_context_create($options);
    @file_get_contents($url, false, $context);
}
