<?php
// src/handlers/search_handler.php

function handle_search_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_search_query' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "لطفاً کد یا نام فایل مورد نظر خود را برای جستجو وارد کنید:");
}

/**
 * Initiates a search. If only one result is found, sends it directly.
 * Otherwise, displays the first page of results.
 */
function handle_search_query($chat_id, $user_id, $query_text) {
    $db = new Database();

    // First, let's see how many results we have
    $count_stmt = $db->executeQuery("SELECT COUNT(*) FROM files WHERE code = ? OR file_name LIKE ?", [$query_text, '%' . $query_text . '%']);
    $total_results = $count_stmt->fetchColumn();

    if ($total_results === 1) {
        // If exactly one result, fetch it and send it directly
        $stmt = $db->executeQuery("SELECT * FROM files WHERE code = ? OR file_name LIKE ? LIMIT 1", [$query_text, '%' . $query_text . '%']);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        handle_file_request($chat_id, $user_id, $file['code']);
    } elseif ($total_results > 1) {
        // If more than one result, show paginated list
        display_search_results($chat_id, $query_text, 1, null);
    } else {
        // If no results
        sendMessage($chat_id, "هیچ نتیجه‌ای برای عبارت '" . $query_text . "' یافت نشد.");
    }

    // Reset user step after initiating the search
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
}


/**
 * Displays a paginated list of search results.
 * (This function remains unchanged from the previous step)
 */
function display_search_results($chat_id, $query, $page = 1, $message_id = null) {
    $db = new Database();
    $results_per_page = 5;
    $offset = ($page - 1) * $results_per_page;

    $count_stmt = $db->executeQuery("SELECT COUNT(*) FROM files WHERE code = ? OR file_name LIKE ?", [$query, '%' . $query . '%']);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);

    $query_sql = "SELECT code, file_name, file_type FROM files WHERE code = ? OR file_name LIKE ? LIMIT ? OFFSET ?";
    $stmt = $db->executeQuery($query_sql, [$query, '%' . $query . '%', $results_per_page, $offset]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response_text = "🔍 نتایج جستجو برای: `" . $query . "`\n";
    $response_text .= "صفحه " . $page . " از " . $total_pages . " (مجموع نتایج: " . $total_results . ")\n\n";
    $response_text .= "برای دریافت فایل، کد آن را کپی و ارسال کنید:\n\n";

    foreach ($results as $result) {
        $response_text .= "📂 `" . $result['code'] . "` - " . $result['file_name'] . "\n";
    }

    $keyboard_row = [];
    if ($page > 1) {
        $keyboard_row[] = ['text' => '◀️ قبلی', 'callback_data' => 'search_page_' . ($page - 1) . '_' . $query];
    }
    if ($page < $total_pages) {
        $keyboard_row[] = ['text' => 'بعدی ▶️', 'callback_data' => 'search_page_' . ($page + 1) . '_' . $query];
    }

    $inline_keyboard = ['inline_keyboard' => [$keyboard_row]];
    $encoded_keyboard = !empty($keyboard_row) ? json_encode($inline_keyboard) : null;

    if ($message_id) {
        editMessageText($chat_id, $message_id, $response_text, $encoded_keyboard);
    } else {
        sendMessage($chat_id, $response_text, $encoded_keyboard);
    }
}


function sendFile($chat_id, $file_id, $file_type, $caption = null, $reply_markup = null) {
    // ... (This function remains unchanged)
}
