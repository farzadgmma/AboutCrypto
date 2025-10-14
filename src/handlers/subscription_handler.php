<?php
// src/handlers/subscription_handler.php

/**
 * Handles the "Buy Subscription" button press.
 * Displays available subscription plans to the user.
 */
function handle_buy_subscription_request($chat_id) {
    $db = new Database();

    // Check if the user already has an active subscription
    $user_stmt = $db->executeQuery("SELECT vip_status FROM users WHERE id = ?", [$chat_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['vip_status'] === 'yes') {
        sendMessage($chat_id, "شما در حال حاضر یک اشتراک فعال دارید.");
        return;
    }

    // Fetch active subscription plans from the database
    $stmt = $db->executeQuery("SELECT id, name, price FROM subscription_plans WHERE is_active = TRUE ORDER BY price ASC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($plans)) {
        sendMessage($chat_id, "در حال حاضر هیچ پلن اشتراکی برای فروش وجود ندارد.");
        return;
    }

    $keyboard = [];
    foreach ($plans as $plan) {
        $button_text = $plan['name'] . " - " . number_format($plan['price']) . " تومان";
        // Each button's callback_data will be 'buy_plan_{plan_id}'
        $keyboard[] = [['text' => $button_text, 'callback_data' => 'buy_plan_' . $plan['id']]];
    }

    $inline_keyboard = [
        'inline_keyboard' => $keyboard
    ];
    $encoded_keyboard = json_encode($inline_keyboard);

    sendMessage($chat_id, "لطفاً یکی از پلن‌های اشتراک زیر را انتخاب کنید:", $encoded_keyboard);
}
