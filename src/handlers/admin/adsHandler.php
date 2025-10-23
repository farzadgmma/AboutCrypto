<?php
// src/handlers/admin/adsHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showAdsPanel($chat_id, $message_id = null) {
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT vaziat, placeads FROM settings LIMIT 1");
    $ad_count = $db->fetch("SELECT COUNT(*) as count FROM ads")['count'];

    $status = ($settings['vaziat'] === 'on') ? "✅ فعال" : "❌ غیرفعال";
    $position = ($settings['placeads'] === 'after') ? "🔻 بعد از رسانه" : "🔺 قبل از رسانه";

    $text = "🪧 <b>وضعیت نمایش تبلیغات</b>\n\n" .
            "▪️ نمایش: <b>{$status}</b>\n" .
            "▪️ تعداد تبلیغات فعال: <b>{$ad_count} تبلیغ</b>\n" .
            "🚸 محل نمایش: <b>{$position}</b>";

    $keyboard = [
        [['text' => "🔕 فعال/غیرفعال سازی", 'callback_data' => "admin_ads_toggle"]],
        [['text' => "🪧 محل نمایش", 'callback_data' => "admin_ads_position"]],
        [['text' => "➕ افزودن تبلیغ", 'callback_data' => "admin_ads_add"]],
        [['text' => "🚧 لیست تبلیغات", 'callback_data' => "admin_ads_list"]],
        [['text' => "🔙 بازگشت به پنل اصلی", 'callback_data' => "admin_main"]]
    ];

    if ($message_id) {
        editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
    } else {
        sendMessage($chat_id, $text, ['inline_keyboard' => $keyboard]);
    }
}

function toggleAds($chat_id, $message_id) {
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT vaziat FROM settings LIMIT 1");
    $new_status = ($settings['vaziat'] === 'on') ? 'off' : 'on';
    $db->execute("UPDATE settings SET vaziat = ?", [$new_status]);
    answerCallbackQuery($GLOBALS['update']->callback_query->id, "تبلیغات با موفقیت " . ($new_status === 'on' ? 'فعال' : 'غیرفعال') . " شد.", false);
    showAdsPanel($chat_id, $message_id);
}

// Other functions for adding, listing, deleting ads will be added here.
