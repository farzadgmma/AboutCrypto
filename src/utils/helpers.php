<?php
// src/utils/helpers.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/Database.php';

/**
 * Sends a request to the Telegram Bot API.
 *
 * @param string $method The API method to call.
 * @param array $data The data to send with the request.
 * @return mixed The decoded JSON response from the API.
 */
functionapiRequest($method, $data = []) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        // In a production environment, you should log this error.
        error_log('cURL Error: ' . curl_error($ch));
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}

/**
 * Sends a text message to a chat.
 *
 * @param int $chat_id The ID of the chat.
 * @param string $text The text of the message.
 * @param array|null $reply_markup Optional. An inline keyboard or other reply markup.
 * @param int|null $reply_to_message_id Optional. If the message is a reply, ID of the original message.
 */
function sendMessage($chat_id, $text, $reply_markup = null, $reply_to_message_id = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    if ($reply_to_message_id) {
        $data['reply_to_message_id'] = $reply_to_message_id;
    }
    return apiRequest('sendMessage', $data);
}

/**
 * Edits the text of a message.
 *
 * @param int $chat_id The ID of the chat.
 * @param int $message_id The ID of the message to edit.
 * @param string $text The new text of the message.
 * @param array|null $reply_markup Optional. A new inline keyboard.
 */
function editMessageText($chat_id, $message_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    return apiRequest('editMessageText', $data);
}

/**
 * Answers a callback query (e.g., shows a notification to the user).
 *
 * @param string $callback_query_id The ID of the callback query.
 * @param string $text The text of the notification.
 * @param bool $show_alert Whether to show an alert-style notification.
 */
function answerCallbackQuery($callback_query_id, $text, $show_alert = false) {
    return apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'show_alert' => $show_alert,
    ]);
}


/**
 * Checks if a user is an administrator of the bot.
 *
 * @param int $user_id The user's Telegram ID.
 * @return bool True if the user is an admin, false otherwise.
 */
function isAdmin($user_id) {
    global $admins;

    // Check the hardcoded list of admins in config.php.
    if (in_array($user_id, $admins)) {
        return true;
    }

    // Check the 'admins' table in the database.
    $db = Database::getInstance();
    $admin = $db->fetch("SELECT idadmin FROM admins WHERE idadmin = ?", [$user_id]);

    return $admin !== false;
}

/**
 * Fetches or creates a user record in the database.
 * If the user doesn't exist, a new record is created with default values.
 *
 * @param int $user_id The user's Telegram ID.
 * @param string $first_name The user's first name.
 * @return array The user's data from the database.
 */
function get_or_create_user($user_id, $first_name) {
    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM user WHERE id = ?", [$user_id]);

    if (!$user) {
        $dateen = date("Y-m-d");
        $db->execute(
            "INSERT INTO user (id, name, timejoin, step, step2, step3, step4, step5, spam, vip, viptime, dl, expireok) VALUES (?, ?, ?, 'none', 'none', 'none', 'none', 'none', '0', 'no', null, '0', 'no')",
            [$user_id, $first_name, $dateen]
        );
        // Fetch the newly created user to return a consistent object.
        $user = $db->fetch("SELECT * FROM user WHERE id = ?", [$user_id]);
    }

    return $user;
}

/**
 * Updates the step (state) of a user in the database.
 *
 * @param int $user_id The user's Telegram ID.
 * @param string $step The new step/state.
 * @param string|null $step2 Optional secondary step data.
 */
function setUserStep($user_id, $step, $step2 = null) {
    $db = Database::getInstance();
    if ($step2 !== null) {
        $db->execute("UPDATE user SET step = ?, step2 = ? WHERE id = ?", [$step, $step2, $user_id]);
    } else {
        $db->execute("UPDATE user SET step = ? WHERE id = ?", [$step, $user_id]);
    }
}
