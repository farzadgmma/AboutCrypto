<?php
// src/handlers/broadcast_handler.php

/**
 * Initiates the broadcast process.
 */
function handle_broadcast_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_broadcast_message' WHERE id = ?", [$user_id]);

    sendMessage($chat_id, "لطفاً پیامی که می‌خواهید برای تمام کاربران ارسال شود را وارد کنید:");
}

/**
 * Adds the broadcast message to a queue for later processing by the cron job.
 */
function execute_broadcast($admin_chat_id, $message_text) {
    $db = new Database();

    // Fetch all user IDs
    $stmt = $db->executeQuery("SELECT id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_users = count($users);
    $queued_count = 0;

    // Prepare the insert statement
    $query = "INSERT INTO broadcast_queue (user_id, message_text) VALUES (?, ?)";
    $insert_stmt = $db->conn->prepare($query);

    foreach ($users as $user) {
        // Add each message to the queue
        if ($user['id'] != $admin_chat_id) { // Don't send to the admin who initiated it
            $insert_stmt->execute([$user['id'], $message_text]);
            $queued_count++;
        }
    }

    // Report back to the admin
    sendMessage($admin_chat_id, "✅ پیام شما با موفقیت در صف ارسال برای " . $queued_count . " کاربر قرار گرفت. این پیام‌ها به تدریج توسط سرور ارسال خواهند شد.");

    // Reset admin's step
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$admin_chat_id]);
}
