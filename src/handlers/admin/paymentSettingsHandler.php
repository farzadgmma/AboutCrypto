<?php
// src/handlers/admin/paymentSettingsHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showPaymentSettingsPanel($chat_id, $message_id = null) {
    $keyboard = [
        [['text' => "⚙️ درگاه پرداخت"], ['text' => "♻️ تغییر اشتراک کاربر"]],
        [['text' => "📥 تعداد دانلود رایگان"]],
        [['text' => "🔑 تغییر مریچنت زرین پال"], ['text' => "🔑 تغییر مریچنت زیبال"]],
        [['text' => "📃 تغییر متن خرید اشتراک"], ['text' => "🗂 مدیریت اشتراک ها"]],
        [['text' => "🔙 منوی پنل"]]
    ];

    $text = "🔻 یکی از گزینه های زیر را انتخاب کنید:";

    if ($message_id) {
        editMessageText($chat_id, $message_id, $text, ['keyboard' => $keyboard, 'resize_keyboard' => true]);
    } else {
        sendMessage($chat_id, $text, ['keyboard' => $keyboard, 'resize_keyboard' => true]);
    }
}

function showGatewayPanel($chat_id, $message_id) {
    $db = Database::getInstance();
    $payment = $db->fetch("SELECT waypay FROM peyment LIMIT 1");
    $current_gateway = ($payment && $payment['waypay'] === 'ziball') ? "درگاه زیبال" : "درگاه زرین پال";

    $keyboard = [
        [['text' => "زرین پال", 'callback_data' => "admin_payment_setgateway_zarin"], ['text' => "زیبال", 'callback_data' => "admin_payment_setgateway_ziball"]],
        [['text' => "🔙 بازگشت", 'callback_data' => "admin_payment_main"]]
    ];

    $text = "▪️ درگاه پرداخت کنونی: <b>{$current_gateway}</b>\n🔻 برای تغییر درگاه پرداخت، یکی از گزینه‌های زیر را انتخاب کنید:";
    editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
}

function setGateway($chat_id, $message_id, $gateway) {
    $db = Database::getInstance();
    $db->execute("UPDATE peyment SET waypay = ?", [$gateway]);
    answerCallbackQuery($GLOBALS['update']->callback_query->id, "✅ درگاه پرداخت با موفقیت تغییر یافت.", false);
    showGatewayPanel($chat_id, $message_id); // Refresh panel
}

function askForMerchantID($chat_id, $gateway) {
    $gateway_name = ($gateway === 'zarin') ? "زرین پال" : "زیبال";
    setUserStep($chat_id, 'admin_awaiting_merchant_' . $gateway);
    sendMessage($chat_id, "✔️ مریچنت {$gateway_name} خود را وارد کنید:", ['keyboard' => [[['text' => '🔙 منوی پنل']]], 'resize_keyboard' => true]);
}

function updateMerchantID($user_id, $gateway, $merchant_id) {
    $db = Database::getInstance();
    $column = ($gateway === 'zarin') ? 'merichentzarin' : 'merichentziball';
    $db->execute("UPDATE peyment SET {$column} = ?", [$merchant_id]);
    sendMessage($user_id, "✅ مریچنت با موفقیت بروزرسانی شد.");
    setUserStep($user_id, 'none');
    showPaymentSettingsPanel($user_id);
}

// Functions for managing subscription plans will be added here
// showSubscriptionManagementPanel(), editSubscription(), etc.
