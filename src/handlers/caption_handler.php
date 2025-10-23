<?php

function handle_caption_start($chat_id) {
    set_user_state($chat_id, 'caption_change_code');
    send_message($chat_id, "Please enter the code of the file you want to edit the caption for.");
}

function handle_caption_code($pdo, $update) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $file_code = $message->text;

    // Check if file exists
    $stmt = $pdo->prepare("SELECT * FROM files WHERE code = ?");
    $stmt->execute([$file_code]);

    if ($stmt->fetch()) {
        set_user_state($chat_id, 'caption_change_new_caption:' . $file_code);
        send_message($chat_id, "File found. Now, please send the new caption.");
    } else {
        send_message($chat_id, "File with that code not found.");
        clear_user_state($chat_id);
    }
}

function handle_new_caption($pdo, $update) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $new_caption = $message->text;

    $state = get_user_state($chat_id);
    $file_code = substr($state, strlen('caption_change_new_caption:'));

    $stmt = $pdo->prepare("UPDATE files SET caption = ? WHERE code = ?");
    if ($stmt->execute([$new_caption, $file_code])) {
        send_message($chat_id, "Caption updated successfully for file `$file_code`.");
    } else {
        send_message($chat_id, "Failed to update caption.");
    }

    clear_user_state($chat_id);
}
