<?php
// src/handlers/list_handler.php

function generate_file_list($chat_id, $title, $files) {
    if (empty($files)) {
        sendMessage($chat_id, "هیچ فایلی برای نمایش در این لیست وجود ندارد.");
        return;
    }

    $inline_keyboard = [];
    foreach ($files as $file) {
        $button_text = "📁 " . ($file['caption'] ?: $file['code']) . " - 📥 " . $file['download_count'];
        $inline_keyboard[] = [['text' => $button_text, 'url' => 'https://t.me/' . BOT_USERNAME . '?start=dl_' . $file['code']]];
    }

    sendMessage($chat_id, "<b>" . $title . "</b>", ['reply_markup' => json_encode(['inline_keyboard' => $inline_keyboard])]);
}

function handle_top_downloads_button($chat_id) {
    $db = new Database();
    $stmt = $db->executeQuery("SELECT code, caption, download_count FROM files ORDER BY download_count DESC LIMIT 10");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    generate_file_list($chat_id, '🔆 پربازدید ترین فایل ها', $files);
}

function handle_newest_files_button($chat_id) {
    $db = new Database();
    $stmt = $db->executeQuery("SELECT code, caption, download_count FROM files ORDER BY created_at DESC LIMIT 10");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    generate_file_list($chat_id, '🌀 جدیدترین فایل ها', $files);
}

function handle_most_liked_button($chat_id) {
    $db = new Database();
    // Assumes a 'likes' column exists in the 'files' table
    $stmt = $db->executeQuery("SELECT code, caption, download_count, likes FROM files ORDER BY likes DESC LIMIT 10");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($files)) {
        sendMessage($chat_id, "هنوز فایلی لایک نشده است.");
        return;
    }

    $inline_keyboard = [];
    foreach ($files as $file) {
        $button_text = "📁 " . ($file['caption'] ?: $file['code']) . " - ♥️ " . $file['likes'];
        $inline_keyboard[] = [['text' => $button_text, 'url' => 'https://t.me/' . BOT_USERNAME . '?start=dl_' . $file['code']]];
    }

    sendMessage($chat_id, "<b>♥️ محبوب ترین فایل ها</b>", ['reply_markup' => json_encode(['inline_keyboard' => $inline_keyboard])]);
}
