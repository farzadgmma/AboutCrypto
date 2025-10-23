<?php
// src/handlers/user/accountHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

/**
 * Handles the "User Account" command.
 * Displays the user's account information.
 *
 * @param array $user The user data array from the database.
 * @param int $chat_id The chat ID to send the message to.
 */
function handleAccountCommand($user, $chat_id) {
    // Fetch settings to check if the button should be active.
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT accountbut FROM settings LIMIT 1");

    if ($settings['accountbut'] !== 'on') {
        // If the button is disabled in settings, do nothing.
        return;
    }

    $user_id = $user['id'];
    $first_name = htmlspecialchars($user['name']);
    $download_count = $user['dl'] ?? 0;

    // Convert join date to Jalali.
    $joindate_parts = explode("-", $user['timejoin']);
    list($year, $month, $day) = $joindate_parts;
    $joinus_jalali = gregorian_to_jalali($year, $month, $day, "/");

    $inline_keyboard = [
        [['text' => "📅 تاریخ عضویت: " . $joinus_jalali, 'callback_data' => 'none']]
    ];

    $message_text = "👤 اطلاعات حساب کاربری شما:\r\n\r\n" .
                    "▪️ آیدی عددی: <code>" . $user_id . "</code>\r\n" .
                    "▪️ نام شما: <b>" . $first_name . "</b>\r\n\r\n" .
                    "📥 تعداد دانلودها: <code>" . $download_count . "</code>\r\n\r\n";

    // Check if the user has a VIP subscription.
    if ($user['vip'] === 'yes' && !empty($user['viptime'])) {
        $message_text .= "💎 نوع اشتراک: <b>💎 اشتراک ویژه</b>";

        // Convert VIP expiration date to Jalali.
        $expire_parts = explode("-", $user['viptime']);
        if(count($expire_parts) == 3){
            list($ex_year, $ex_month, $ex_day) = $expire_parts;
            $expire_jalali = gregorian_to_jalali($ex_year, $ex_month, $ex_day, "/");

            // Add the expiration date to the inline keyboard.
            $inline_keyboard[] = [['text' => "⭐️ اکانت شما تا تاریخ " . $expire_jalali . " ویژه می‌باشد", 'callback_data' => 'none']];
        }
    } else {
        $message_text .= "💎 نوع اشتراک: <b>اشتراک عادی</b>";
    }

    // Send the message with the constructed text and keyboard.
    sendMessage($chat_id, $message_text, ['inline_keyboard' => $inline_keyboard]);
}
