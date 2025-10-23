<?php
// src/handlers/user/supportHandler.php

require_once __DIR__ . '/../../utils/helpers.php';
require_once __DIR__ . '/../../config/config.php';

function handleSupportCommand($user_id, $chat_id) {
    setUserStep($user_id, 'awaiting_support_message');
    sendMessage($chat_id, "🔹در صورت سوال یا مشکل در مورد ربات ، آن را در ادامه بنویسید.", ['keyboard' => [[['text' => '🏠 برگشت به منو']]], 'resize_keyboard' => true]);
}

function forwardSupportMessageToAdmin($user, $message) {
    global $admins;
    $main_admin_id = $admins[0];
    $user_id = $user['id'];
    $chat_id = $message->chat->id;

    $header = "🧑‍💻 ادمین عزیز یک پیام از کاربر <code>{$user_id}</code> دارید:\n\n";

    $keyboard = [[
        ['text' => "📝 پاسخ به کاربر", 'callback_data' => "admin_support_reply_{$user_id}"],
        ['text' => "🚫 بلاک کردن کاربر", 'callback_data' => "admin_user_ban_{$user_id}"]
    ]];

    // Forward the message to the main admin
    if (isset($message->text)) {
        apiRequest('sendMessage', [
            'chat_id' => $main_admin_id,
            'text' => $header . "<b>" . htmlspecialchars($message->text) . "</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        // For photos, videos, etc., we can't just forward with a new caption easily.
        // A simple solution is to send the info and then forward the message.
        apiRequest('sendMessage', [
            'chat_id' => $main_admin_id,
            'text' => $header,
            'parse_mode' => 'HTML',
        ]);
        apiRequest('forwardMessage', [
            'chat_id' => $main_admin_id,
            'from_chat_id' => $chat_id,
            'message_id' => $message->message_id
        ]);
        // Send a separate message with the action buttons
        apiRequest('sendMessage', [
            'chat_id' => $main_admin_id,
            'text' => "🔻 اقدام مورد نظر برای کاربر <code>{$user_id}</code> را انتخاب کنید:",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    sendMessage($chat_id, "✅ پیام شما با موفقیت به ادمین ارسال شد.");
    setUserStep($user_id, 'none');
    handleStartCommand($user, $chat_id); // Go back to main menu
}

function askAdminForReply($admin_chat_id, $user_id) {
    setUserStep($admin_chat_id, 'admin_awaiting_reply_' . $user_id);
    sendMessage($admin_chat_id, "✅ پیام خود را برای کاربر <code>{$user_id}</code> ارسال کنید:", ['keyboard' => [[['text' => '🔙 لغو']]], 'resize_keyboard' => true]);
}

function sendReplyToUser($admin_id, $user_id, $message_text) {
    $reply_header = "🟢 <b>پیام از طرف پشتیبانی:</b>\n\n";
    sendMessage($user_id, $reply_header . htmlspecialchars($message_text));
    sendMessage($admin_id, "✅ پیام شما با موفقیت به کاربر ارسال شد.");
    setUserStep($admin_id, 'none');
    showAdminPanel($admin_id); // Return to admin panel
}
