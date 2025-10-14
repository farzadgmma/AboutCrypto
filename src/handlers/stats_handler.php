<?php
// src/handlers/stats_handler.php

/**
 * Handles the request for bot statistics.
 * Fetches and displays the total number of users and files.
 */
function handle_stats_request($chat_id) {
    $db = new Database();

    // Get total users
    $users_stmt = $db->executeQuery("SELECT COUNT(*) as total_users FROM users");
    $total_users = $users_stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Get total files
    $files_stmt = $db->executeQuery("SELECT COUNT(*) as total_files FROM files");
    $total_files = $files_stmt->fetch(PDO::FETCH_ASSOC)['total_files'];

    $stats_message = "📊 **آمار ربات** 📊\n\n";
    $stats_message .= "👥 **تعداد کل کاربران:** " . $total_users . "\n";
    $stats_message .= "📁 **تعداد کل فایل‌ها:** " . $total_files . "\n";

    sendMessage($chat_id, $stats_message);
}
