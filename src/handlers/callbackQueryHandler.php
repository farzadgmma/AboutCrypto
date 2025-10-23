<?php
// src/handlers/callbackQueryHandler.php

require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/user/downloadHandler.php'; // Needed for re-checking and sending files
require_once __DIR__ . '/admin/adminCallbackHandler.php'; // To handle all admin-related callbacks

/**
 * Handles all incoming callback query updates from Telegram (inline button presses).
 *
 * @param object $update The full update object from Telegram.
 */
function handleCallbackQuery($update) {
    $callback_query = $update->callback_query;
    $callback_id = $callback_query->id;
    $user_id = $callback_query->from->id;
    $chat_id = $callback_query->message->chat->id;
    $message_id = $callback_query->message->message_id;
    $data = $callback_query->data;
    $first_name = $callback_query->from->first_name;

    // Get user data.
    $user = get_or_create_user($user_id, $first_name);

    // --- Callback Routing ---

    // Route all admin-related callbacks to a dedicated handler.
    if (strpos($data, 'admin_') === 0) {
        if (isAdmin($user_id)) {
            handleAdminCallback($chat_id, $message_id, $data);
        } else {
            answerCallbackQuery($callback_id, "شما اجازه دسترسی به این بخش را ندارید.", true);
        }
        return;
    }

    // Handle the "I have joined" button press.
    if (strpos($data, 'confirm_join_') === 0) {
        $file_code = substr($data, 13);
        handleConfirmJoin($user, $chat_id, $message_id, $callback_id, $file_code);
        return;
    }

    // Handle "none" callbacks (buttons that should do nothing).
    if ($data === 'none') {
        answerCallbackQuery($callback_id, '');
        return;
    }

    // Placeholder for other callbacks like 'like' or 'dislike'.
    // answerCallbackQuery($callback_id, "این دکمه هنوز فعال نشده است.");
}


/**
 * Handles the logic for the "✅ عضو شدم" (I have joined) button.
 *
 * @param array $user User data.
 * @param int $chat_id Chat ID.
 * @param int $message_id Message ID of the join prompt.
 * @param string $callback_id Callback query ID.
 * @param string $file_code The code of the file the user wants to download.
 */
function handleConfirmJoin($user, $chat_id, $message_id, $callback_id, $file_code) {
    $db = Database::getInstance();
    $channels = $db->fetchAll("SELECT * FROM channels");

    $all_joined = true;
    foreach ($channels as $channel) {
        if (!isUserMember($user['id'], $channel['idoruser'])) {
            $all_joined = false;
            break; // No need to check further if one is not joined.
        }
    }

    if ($all_joined) {
        // The user has joined all channels.
        answerCallbackQuery($callback_id, "✅ عضویت شما تایید شد. در حال ارسال فایل...", false);
        // Delete the "Please join" message to clean up the chat.
        apiRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);

        // Now, re-trigger the download process from the next step.
        $file = $db->fetch("SELECT * FROM files WHERE code = ?", [$file_code]);
        if ($file) {
            proceedToNextDownloadStep($user, $chat_id, $file);
        }
    } else {
        // The user has not joined all channels.
        answerCallbackQuery($callback_id, "❌ شما هنوز در تمام کانال‌ها عضو نشده‌اید.", true);
    }
}
