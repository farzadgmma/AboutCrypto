<?php

function show_forced_join_menu($pdo, $chat_id) {
    $stmt = $pdo->query("SELECT * FROM forced_joins");
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $message = "Forced Join Channels:\n\n";
    $keyboard = [];
    if (count($channels) > 0) {
        foreach ($channels as $channel) {
            $message .= "- " . htmlspecialchars($channel['channel_id']) . "\n";
            $keyboard[] = [['text' => "Remove " . htmlspecialchars($channel['channel_id']), 'callback_data' => "remove_join_".$channel['id']]];
        }
    } else {
        $message .= "No channels configured.";
    }

    $keyboard[] = [['text' => "Add Channel", 'callback_data' => 'add_join_channel']];

    send_message($chat_id, $message, ['inline_keyboard' => $keyboard]);
}

function handle_add_join_channel_start($chat_id) {
    set_user_state($chat_id, 'adding_join_channel_id');
    send_message($chat_id, "Please send the channel ID (e.g., @channelname or -100123456789).");
}

function handle_add_join_channel_id($pdo, $update) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $channel_id = $message->text;

    set_user_state($chat_id, 'adding_join_channel_link:' . $channel_id);
    send_message($chat_id, "Great. Now send the invite link for the channel (e.g., https://t.me/joinchat/...).");
}

function handle_add_join_channel_link($pdo, $update) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $channel_link = $message->text;

    $state = get_user_state($chat_id);
    $channel_id = substr($state, strlen('adding_join_channel_link:'));

    $stmt = $pdo->prepare("INSERT INTO forced_joins (channel_id, channel_link) VALUES (?, ?)");
    if ($stmt->execute([$channel_id, $channel_link])) {
        send_message($chat_id, "Channel added successfully.");
        show_forced_join_menu($pdo, $chat_id); // Refresh menu
    } else {
        send_message($chat_id, "Failed to add channel.");
    }
    clear_user_state($chat_id);
}


function handle_remove_join_channel($pdo, $update) {
    $callback_query = $update->callback_query;
    $chat_id = $callback_query->message->chat->id;
    $channel_db_id = substr($callback_query->data, strlen('remove_join_'));

    $stmt = $pdo->prepare("DELETE FROM forced_joins WHERE id = ?");
    if ($stmt->execute([$channel_db_id])) {
        telegram_request('answerCallbackQuery', ['callback_query_id' => $callback_query->id, 'text' => 'Channel removed.']);
        show_forced_join_menu($pdo, $chat_id); // Refresh menu
    } else {
        telegram_request('answerCallbackQuery', ['callback_query_id' => $callback_query->id, 'text' => 'Failed to remove channel.']);
    }
}
