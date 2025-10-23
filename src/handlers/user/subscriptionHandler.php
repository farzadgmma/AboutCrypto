<?php
// src/handlers/user/subscriptionHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

/**
 * Handles the "Buy Subscription" command.
 * Displays the subscription plans to the user.
 *
 * @param array $user The user data array.
 * @param int $chat_id The chat ID.
 */
function handleSubscriptionCommand($user, $chat_id) {
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT subbuy, bottype FROM settings LIMIT 1");

    // Only proceed if the subscription button is enabled and the bot is in subscription mode.
    if ($settings['subbuy'] !== 'on' || $settings['bottype'] !== 'sub') {
        return;
    }

    // If the user already has an active subscription, inform them.
    if ($user['vip'] === 'yes') {
        sendMessage($chat_id, "<b>❌ شما در حال حاضر یک اشتراک فعال دارید و نیازی به خرید مجدد نیست.</b>");
        return;
    }

    // Fetch payment and subscription plan details from the database.
    $payment = $db->fetch("SELECT * FROM peyment LIMIT 1");
    if (!$payment) {
        sendMessage($chat_id, "خطا: طرح‌های اشتراک در حال حاضر تعریف نشده‌اند.");
        return;
    }

    // Determine the correct payment gateway URL endpoint.
    $gateway = ($payment['waypay'] === 'zarin') ? 'zarin' : 'ziball';
    // This assumes the payment scripts are in a `/pay/` directory relative to the web root.
    // The actual domain/base URL should be configured properly on the server.
    $base_payment_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $payment_url_path = "/pay/{$gateway}/index.php";

    $keyboard = [];
    // Loop through the 6 subscription slots.
    for ($i = 1; $i <= 6; $i++) {
        $sub_key = 'sub' . $i;
        if (isset($payment[$sub_key]) && !empty($payment[$sub_key])) {
            // Subscription format: name^status^duration^price
            $parts = explode('^', $payment[$sub_key]);
            if (count($parts) >= 4 && $parts[1] === 'on') {
                $name = $parts[0];
                $price_rials = $parts[3]; // Price in Rials

                // The URL to the payment gateway script with necessary parameters.
                $full_payment_url = $base_payment_url . $payment_url_path . "?amount=" . $price_rials . "&id=" . $user['id'];

                $keyboard[] = [['text' => $name, 'url' => $full_payment_url]];
            }
        }
    }

    // If no active subscription plans are found, inform the user.
    if (empty($keyboard)) {
        sendMessage($chat_id, "در حال حاضر هیچ طرح اشتراک فعالی وجود ندارد.");
        return;
    }

    $message_text = $payment['matnpay'] ?? "برای ادامه، لطفا یکی از طرح‌های اشتراک زیر را انتخاب و خریداری کنید:";

    // Send the message with the dynamically generated payment links.
    sendMessage($chat_id, $message_text, ['inline_keyboard' => $keyboard]);
}
