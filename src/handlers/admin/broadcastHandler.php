<?php
// src/handlers/admin/broadcastHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showBroadcastPanel($chat_id) {
    $keyboard = [
        [['text' => "📨 فروارد همگانی"], ['text' => "💌 پیام همگانی"]],
        [['text' => "🔙 منوی پنل"]]
    ];
    sendMessage($chat_id, "<b>🔻 یکی از گزینه های زیر را انتخاب کنید:</b>", ['keyboard' => $keyboard, 'resize_keyboard' => true]);
}

// Full implementation of broadcast will be complex and require cron jobs for non-blocking sending.
// This is a simplified, blocking version for now.

function askForBroadcastForward($chat_id) {
    setUserStep($chat_id, 'admin_awaiting_forward');
    sendMessage($chat_id, "<b>✔️ لطفا پیام مورد نظر خود را فروارد کنید:</b>", ['keyboard' => [[['text' => '🔙 لغو']]], 'resize_keyboard' => true]);
}

function handleBroadcastForward($admin_chat_id, $message) {
    $db = Database::getInstance();
    $users = $db->fetchAll("SELECT id FROM user");

    $total_users = count($users);
    $sent_count = 0;

    sendMessage($admin_chat_id, "⏳ در حال شروع فروارد همگانی به {$total_users} کاربر...");

    foreach ($users as $user) {
        $response = apiRequest('forwardMessage', [
            'chat_id' => $user['id'],
            'from_chat_id' => $admin_chat_id,
            'message_id' => $message->message_id
        ]);
        if ($response && $response['ok']) {
            $sent_count++;
        }
        usleep(100000); // Sleep for 100ms to avoid hitting API limits
    }

    sendMessage($admin_chat_id, "✅ فروارد همگانی با موفقیت به {$sent_count} نفر از {$total_users} کاربر ارسال شد.");
    setUserStep($admin_chat_id, 'none');
}
