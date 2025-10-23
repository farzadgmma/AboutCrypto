<?php
// src/handlers/user/passwordHandler.php

require_once __DIR__ . '/../../utils/helpers.php';
require_once __DIR__ . '/downloadHandler.php'; // Needed to call sendFileToUser

/**
 * Handles the user's submission of a password for a locked file.
 *
 * @param array $user The user data array.
 * @param object $message The message object from Telegram, containing the password.
 */
function handlePasswordSubmission($user, $message) {
    $db = Database::getInstance();
    $user_id = $user['id'];
    $chat_id = $message->chat->id;
    $submitted_password = $message->text;

    // The file code is stored in the user's 'step' field.
    $file_code = substr($user['step'], strlen('awaiting_password_'));

    // Fetch the file to check its actual password.
    $file = $db->fetch("SELECT * FROM files WHERE code = ?", [$file_code]);

    if (!$file) {
        sendMessage($chat_id, "خطایی رخ داد. لطفاً دوباره تلاش کنید.");
        setUserStep($user_id, 'none');
        return;
    }

    // Check if the submitted password is correct.
    if ($submitted_password === $file['pass']) {
        // Password is correct.
        sendMessage($chat_id, "<b>✅ پسورد تایید شد.</b> در حال ارسال فایل...", ['remove_keyboard' => true]);

        // Reset the user's state.
        setUserStep($user_id, 'none');

        // All previous checks have passed, so we can now proceed to the final checks and send the file.
        // We call a slightly modified proceed function to skip the password check we just did.
        proceedAfterPassword($user, $chat_id, $file);

    } else {
        // Password is incorrect.
        sendMessage($chat_id, "❌ رمز عبور نامعتبر است! لطفا دوباره تلاش کنید:", ['keyboard' => [[['text' => '🏠 برگشت به منو']]], 'resize_keyboard' => true]);
        // The user remains in the 'awaiting_password_' state to allow for another attempt.
    }
}

/**
 * After a successful password entry, runs the final checks before sending the file.
 * This is a subset of the main `proceedToNextDownloadStep` function.
 *
 * @param array $user
 * @param int $chat_id
 * @param array $file
 */
function proceedAfterPassword($user, $chat_id, $file) {
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT * FROM settings LIMIT 1");

    // Run the final check: Download Limit
    if ($file['mahdodl'] !== 'none' && $file['dl'] >= (int)$file['mahdodl']) {
        sendMessage($chat_id, "❗️ متاسفانه ظرفیت دانلود این فایل به پایان رسیده است.");
        return;
    }

    // All checks are now complete. Send the file.
    sendFileToUser($chat_id, $file, $settings);
}
