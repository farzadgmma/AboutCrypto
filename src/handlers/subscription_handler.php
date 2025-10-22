<?php
// src/handlers/subscription_handler.php

function handle_subscription_button($chat_id, $user_id) {
    $db = new Database();
    global $BASE_URL; // Assuming BASE_URL is defined in config.php

    // 1. Check if user already has an active subscription
    $user_stmt = $db->executeQuery("SELECT is_vip, vip_expire_date FROM users WHERE id = ?", [$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['is_vip'] && strtotime($user['vip_expire_date']) > time()) {
        sendMessage($chat_id, "شما در حال حاضر یک اشتراک فعال دارید. امکان خرید مجدد وجود ندارد.");
        return;
    }

    // 2. Fetch active subscription plans and payment settings
    $subs_stmt = $db->executeQuery("SELECT id, name, price FROM subscriptions WHERE is_active = TRUE ORDER BY price ASC");
    $subscriptions = $subs_stmt->fetchAll(PDO::FETCH_ASSOC);

    $payment_stmt = $db->executeQuery("SELECT active_gateway FROM payment_settings WHERE id = 1");
    $payment_settings = $payment_stmt->fetch(PDO::FETCH_ASSOC);
    $active_gateway = $payment_settings ? $payment_settings['active_gateway'] : 'zarinpal'; // Default to zarinpal

    if (empty($subscriptions)) {
        sendMessage($chat_id, "در حال حاضر هیچ پلن اشتراکی برای خرید وجود ندارد.");
        return;
    }

    // 3. Create inline keyboard with payment links
    $inline_keyboard = [];
    $payment_page_url = $BASE_URL . "/pay/" . $active_gateway . "/index.php";

    foreach ($subscriptions as $sub) {
        $button_text = $sub['name'] . " - " . number_format($sub['price']) . " تومان";
        $query_params = http_build_query([
            'amount' => $sub['price'],
            'user_id' => $user_id,
            'plan_id' => $sub['id']
        ]);
        $payment_url = $payment_page_url . "?" . $query_params;
        $inline_keyboard[] = [['text' => $button_text, 'url' => $payment_url]];
    }

    // Fetch the custom message text
    $text_stmt = $db->executeQuery("SELECT subscription_message FROM payment_settings WHERE id = 1");
    $text_setting = $text_stmt->fetch(PDO::FETCH_ASSOC);
    $message_text = $text_setting ? $text_setting['subscription_message'] : "لطفاً یکی از پلن‌های اشتراک زیر را برای خرید انتخاب کنید:";

    $reply_markup = json_encode(['inline_keyboard' => $inline_keyboard]);

    sendMessage($chat_id, $message_text, $reply_markup);
}
