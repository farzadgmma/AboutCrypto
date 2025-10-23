<?php

function handle_join_check($pdo, $update) {
    $callback_query = $update->callback_query;
    $chat_id = $callback_query->message->chat->id;
    $user_id = $callback_query->from->id;
    $message_id = $callback_query->message->message_id;

    if (check_forced_join($pdo, $user_id)) {
        // User has joined, remove the prompt and notify them.
        telegram_request('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "Thank you for joining! You can now access the file again.",
            'reply_markup' => ''
        ]);
        telegram_request('answerCallbackQuery', ['callback_query_id' => $callback_query->id, 'text' => 'Verification successful!']);
    } else {
        // User has not joined all channels yet.
        telegram_request('answerCallbackQuery', [
            'callback_query_id' => $callback_query->id,
            'text' => "You haven't joined all the required channels yet. Please try again.",
            'show_alert' => true
        ]);
    }
}
