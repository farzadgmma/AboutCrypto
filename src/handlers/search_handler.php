<?php

function handle_search_start($chat_id) {
    set_user_state($chat_id, 'searching');
    send_message($chat_id, "Please enter the code or a part of the caption for the file you want to find.");
}

function handle_search_query($pdo, $update) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $query = $message->text;

    // Search in 'code' and 'caption' fields
    $stmt = $pdo->prepare("SELECT * FROM files WHERE code = ? OR caption LIKE ?");
    $stmt->execute([$query, "%$query%"]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($files) > 0) {
        $reply = "Found " . count($files) . " file(s):\n\n";
        foreach ($files as $file) {
            $reply .= "Code: `" . htmlspecialchars($file['code']) . "`\n";
            $reply .= "Caption: " . htmlspecialchars($file['caption']) . "\n";
            $reply .= "Type: " . htmlspecialchars($file['type']) . "\n\n";
        }
    } else {
        $reply = "No files found matching your query.";
    }

    send_message($chat_id, $reply);
}
