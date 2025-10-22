<?php
// src/includes/telegram_api.php

/**
 * Sends a request to the Telegram Bot API.
 *
 * @param string $method The API method name.
 * @param array $data The data to send with the request.
 * @return mixed The decoded JSON response from the API, or false on failure.
 */
function apiRequest($method, $data = []) {
    if (!defined('BOT_TOKEN')) {
        // Log or handle the error appropriately
        error_log("Bot token is not defined.");
        return false;
    }

    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    // It's good practice to set a timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("cURL Error: " . $error);
        return false;
    }

    return json_decode($result, true);
}

/**
 * Sends a text message.
 *
 * @param int $chat_id
 * @param string $text
 * @param array $options Optional parameters like reply_markup, parse_mode, etc.
 * @return mixed API response.
 */
function sendMessage($chat_id, $text, $options = []) {
    $data = array_merge([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML' // Default parse mode
    ], $options);
    return apiRequest('sendMessage', $data);
}

/**
 * Edits an existing text message.
 *
 * @param int $chat_id
 * @param int $message_id
 * @param string $text
 * @param array $options Optional parameters like reply_markup, parse_mode, etc.
 * @return mixed API response.
 */
function editMessageText($chat_id, $message_id, $text, $options = []) {
    $data = array_merge([
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ], $options);
    return apiRequest('editMessageText', $data);
}

/**
 * Edits the reply markup of an existing message.
 *
 * @param int $chat_id
 * @param int $message_id
 * @param array $reply_markup The new inline keyboard markup.
 * @return mixed API response.
 */
function editMessageReplyMarkup($chat_id, $message_id, $reply_markup) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => json_encode($reply_markup)
    ];
    return apiRequest('editMessageReplyMarkup', $data);
}


/**
 * Sends a photo.
 *
 * @param int $chat_id
 * @param string $photo File ID or URL.
 * @param array $options Optional parameters like caption, reply_markup, etc.
 * @return mixed API response.
 */
function sendPhoto($chat_id, $photo, $options = []) {
    $data = array_merge([
        'chat_id' => $chat_id,
        'photo' => $photo,
        'parse_mode' => 'HTML'
    ], $options);
    return apiRequest('sendPhoto', $data);
}

/**
 * Sends a video.
 *
 * @param int $chat_id
 * @param string $video File ID or URL.
 * @param array $options Optional parameters like caption, reply_markup, etc.
 * @return mixed API response.
 */
function sendVideo($chat_id, $video, $options = []) {
    $data = array_merge([
        'chat_id' => $chat_id,
        'video' => $video,
        'parse_mode' => 'HTML'
    ], $options);
    return apiRequest('sendVideo', $data);
}

/**
 * Sends an audio file.
 *
 * @param int $chat_id
 * @param string $audio File ID or URL.
 * @param array $options Optional parameters like caption, reply_markup, etc.
 * @return mixed API response.
 */
function sendAudio($chat_id, $audio, $options = []) {
    $data = array_merge([
        'chat_id' => $chat_id,
        'audio' => $audio,
        'parse_mode' => 'HTML'
    ], $options);
    return apiRequest('sendAudio', $data);
}

/**
 * Sends a document/file.
 *
 * @param int $chat_id
 * @param string $document File ID or URL.
 * @param array $options Optional parameters like caption, reply_markup, etc.
 * @return mixed API response.
 */
function sendDocument($chat_id, $document, $options = []) {
    $data = array_merge([
        'chat_id' => $chat_id,
        'document' => $document,
        'parse_mode' => 'HTML'
    ], $options);
    return apiRequest('sendDocument', $data);
}


/**
 * Answers a callback query (from an inline button press).
 *
 * @param string $callback_query_id
 * @param string $text Optional. Text to show as a notification.
 * @param bool $show_alert Optional. If true, an alert will be shown.
 * @return mixed API response.
 */
function answerCallbackQuery($callback_query_id, $text = '', $show_alert = false) {
    $data = [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'show_alert' => $show_alert
    ];
    return apiRequest('answerCallbackQuery', $data);
}

function deleteMessage($chat_id, $message_id) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
    ];
    return apiRequest('deleteMessage', $data);
}

function send_file_by_type($chat_id, $file_type, $file_id, $caption = null, $parse_mode = null) {
    $method = 'send' . ucfirst($file_type); // e.g., sendPhoto, sendVideo
    $data = [
        'chat_id' => $chat_id,
        $file_type => $file_id, // e.g., 'photo' => $file_id
    ];
    if ($caption) {
        $data['caption'] = $caption;
    }
    if ($parse_mode) {
        $data['parse_mode'] = $parse_mode;
    }

    return apiRequest($method, $data);
}

/**
 * Forwards a message of any kind.
 *
 * @param int $chat_id The target chat ID.
 * @param int $from_chat_id The source chat ID.
 * @param int $message_id The message ID to forward.
 * @return mixed API response.
 */
function forwardMessage($chat_id, $from_chat_id, $message_id) {
    $data = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id,
    ];
    return apiRequest('forwardMessage', $data);
}

/**
 * Deletes a message.
 *
 * @param int $chat_id
 * @param int $message_id
 * @return mixed API response.
 */
function deleteMessage($chat_id, $message_id) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
    ];
    return apiRequest('deleteMessage', $data);
}
