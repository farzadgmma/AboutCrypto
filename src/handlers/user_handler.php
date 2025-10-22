<?php
// src/handlers/user_handler.php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/telegram_api.php';

function handle_account_button($chat_id, $user_id) {
    $db = new Database();

    $stmt = $db->executeQuery("SELECT * FROM users WHERE id = ?", [$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendMessage($chat_id, "اطلاعات حساب شما یافت نشد. لطفاً ربات را مجدداً با دستور /start راه‌اندازی کنید.");
        return;
    }

    // Prepare user details
    $user_name = htmlspecialchars($user['first_name']);
    $download_count = $user['download_count'];
    $join_date = $user['join_date']; // Assuming format is YYYY-MM-DD

    // Subscription details
    $subscription_type = "اشتراک عادی";
    $inline_keyboard = [
        [['text' => "📅 تاریخ عضویت : " . $join_date, 'callback_data' => 'none']]
    ];

    if ($user['is_vip'] ?? false) { // Assumes an 'is_vip' column
        $subscription_type = "💎 اشتراک ویژه";
        $expire_date = $user['vip_expire_date']; // Assumes 'vip_expire_date' column
        $inline_keyboard[] = [['text' => "⭐️ اکانت شما تا تاریخ " . $expire_date . " ویژه می باشد", 'callback_data' => 'none']];
    }

    $message_text = "👤 اطلاعات حساب کاربری شما:\r\n\r\n" .
                    "▪️ ایدی عددی :<code>" . $user_id . "</code>\r\n" .
                    "▪️ نام کاربری : <b>" . $user_name . "</b>\r\n\r\n" .
                    "💎 نوع اشتراک : <b>" . $subscription_type . "</b>\r\n\r\n" .
                    "📥 تعداد دانلودها: <code>" . $download_count . "</code>";

    $reply_markup = json_encode(['inline_keyboard' => $inline_keyboard]);

    sendMessage($chat_id, $message_text, ['reply_markup' => $reply_markup]);
}
