<?php

function handle_broadcast_start($chat_id) {
    // Set a state for the admin user to indicate they are about to send a broadcast message
    // This would typically be stored in the database against the admin's user ID.
    // For now, we'll just send the instruction.
    send_message($chat_id, "Please send the message you want to broadcast to all users.");
}

function handle_broadcast_message($pdo, $update) {
    $message = $update->message;
    $chat_id = $message->chat->id;

    // A simple check to see if this is a broadcast message.
    // In a real app, we'd check the admin's state.
    if (!in_array($chat_id, ADMINS)) return;

    $stmt = $pdo->query("SELECT id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total_users = count($users);
    $sent_count = 0;

    foreach ($users as $user_id) {
        // Forward the message to each user
        telegram_request('forwardMessage', [
            'chat_id' => $user_id,
            'from_chat_id' => $chat_id,
            'message_id' => $message->message_id,
        ]);
        $sent_count++;
        // Avoid hitting API limits
        if ($sent_count % 20 == 0) {
            sleep(1);
        }
    }

    send_message($chat_id, "Broadcast complete. Message sent to " . $total_users . " users.");
}
