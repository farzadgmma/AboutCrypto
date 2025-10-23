<?php

function handle_delete_start($chat_id) {
    set_user_state($chat_id, 'deleting_file');
    send_message($chat_id, "Please enter the code of the file you want to delete.");
}

function handle_delete_code($pdo, $update) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $file_code = $message->text;

    $stmt = $pdo->prepare("DELETE FROM files WHERE code = ?");

    if ($stmt->execute([$file_code])) {
        if ($stmt->rowCount() > 0) {
            send_message($chat_id, "File with code `$file_code` has been deleted.");
        } else {
            send_message($chat_id, "No file found with code `$file_code`.");
        }
    } else {
        send_message($chat_id, "An error occurred while trying to delete the file.");
    }

    clear_user_state($chat_id);
}
