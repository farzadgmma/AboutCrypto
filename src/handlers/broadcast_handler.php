<?php
// src/handlers/broadcast_handler.php

/**
 * Initiates the broadcast process.
 * Asks the admin to send the message they want to broadcast.
 */
function handle_broadcast_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_broadcast_message' WHERE id = ?", [$user_id]);

    sendMessage($chat_id, "لطفاً پیامی که می‌خواهید برای تمام کاربران ارسال شود را وارد کنید:");
}

/**
 * Executes the broadcast.
 * Fetches all users and sends them the given message.
 */
function execute_broadcast($admin_chat_id, $message_text) {
    $db = new Database();

    // Fetch all user IDs
    $stmt = $db->executeQuery("SELECT id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $success_count = 0;
    $total_users = count($users);

    sendMessage($admin_chat_id, "⏳ در حال شروع ارسال پیام همگانی به " . $total_users . " کاربر...");

    foreach ($users as $user) {
        // sendMessage to each user
        // Using @ to suppress errors if a user has blocked the bot
        @sendMessage($user['id'], $message_text);
        $success_count++;
        // Avoid hitting Telegram's rate limits
        usleep(100000); // 0.1 second delay between messages
    }

    // Report back to the admin
    sendMessage($admin_chat_id, "✅ پیام همگانی با موفقیت به " . $success_count . " نفر از " . $total_users . " کاربر ارسال شد.");

    // Reset admin's step
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$admin_chat_id]);
}
