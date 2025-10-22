<?php
// src/handlers/search_handler.php

/**
 * Handles the admin's request to search by file code.
 * If found, it shows the management menu for that file.
 */
function handle_search_by_code($chat_id, $user_id, $file_code) {
    $db = new Database();
    $stmt = $db->executeQuery("SELECT * FROM files WHERE code = ?", [$file_code]);

    if ($stmt->rowCount() > 0) {
        // File found, display the specific management menu for it
        display_media_management_menu($chat_id, $file_code);
    } else {
        sendMessage($chat_id, "فایلی با کد `" . $file_code . "` یافت نشد.");
    }
    // Reset user step
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
}

/**
 * Handles the admin's request to search by file caption.
 * Kicks off the paginated display of results.
 */
function handle_search_by_caption($chat_id, $user_id, $query_text) {
    display_search_results($chat_id, 'caption', $query_text, 1, null);
    // Reset user step
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
}


/**
 * Displays a paginated list of search results based on a specific field.
 *
 * @param int $chat_id The chat ID.
 * @param string $search_by The database column to search in (e.g., 'caption', 'file_type').
 * @param string $query The search query.
 * @param int $page The current page number.
 * @param int|null $message_id The message ID to edit, if applicable.
 */
function display_search_results($chat_id, $search_by, $query, $page = 1, $message_id = null) {
    $db = new Database();
    $results_per_page = 5;
    $offset = ($page - 1) * $results_per_page;

    // Validate search field to prevent SQL injection
    $allowed_fields = ['caption', 'file_type', 'thumbnail_caption'];
    if (!in_array($search_by, $allowed_fields)) {
        // It's better to log this error than to show it to the user.
        sendMessage($chat_id, "خطای داخلی: نوع جستجوی نامعتبر است.");
        return;
    }

    $search_pattern = '%' . $query . '%';

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM files WHERE " . $search_by . " LIKE ?";
    $count_stmt = $db->executeQuery($count_sql, [$search_pattern]);
    $total_results = $count_stmt->fetchColumn();

    if($total_results == 0){
        sendMessage($chat_id, "هیچ نتیجه‌ای برای عبارت '" . $query . "' یافت نشد.");
        return;
    }

    $total_pages = ceil($total_results / $results_per_page);

    // Get results for the current page
    $query_sql = "SELECT code, caption, file_type FROM files WHERE " . $search_by . " LIKE ? LIMIT ? OFFSET ?";
    $stmt = $db->executeQuery($query_sql, [$search_pattern, $results_per_page, $offset]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response_text = "🔍 نتایج جستجو برای: `" . $query . "`\n";
    $response_text .= "صفحه " . $page . " از " . $total_pages . " (مجموع نتایج: " . $total_results . ")\n\n";
    $response_text .= "برای مدیریت فایل، کد آن را لمس کرده و کپی کنید، سپس در ربات ارسال کنید:\n\n";

    foreach ($results as $result) {
        $short_caption = mb_substr($result['caption'], 0, 50, 'UTF-8');
        if (mb_strlen($result['caption'], 'UTF-8') > 50) {
            $short_caption .= '...';
        }
        $response_text .= "📂 `" . $result['code'] . "` - " . $short_caption . "\n";
    }

    // Pagination buttons
    $keyboard_row = [];
    $callback_prefix = 'search_page_' . $search_by . '_';
    if ($page > 1) {
        $keyboard_row[] = ['text' => '◀️ قبلی', 'callback_data' => $callback_prefix . ($page - 1) . '_' . $query];
    }
    if ($page < $total_pages) {
        $keyboard_row[] = ['text' => 'بعدی ▶️', 'callback_data' => $callback_prefix . ($page + 1) . '_' . $query];
    }

    $inline_keyboard = ['inline_keyboard' => [$keyboard_row]];
    $encoded_keyboard = !empty($keyboard_row) ? json_encode($inline_keyboard) : null;

    if ($message_id) {
        editMessageText($chat_id, $message_id, $response_text, $encoded_keyboard);
    } else {
        sendMessage($chat_id, $response_text, $encoded_keyboard);
    }
}
