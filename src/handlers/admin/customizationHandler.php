<?php
// src/handlers/admin/customizationHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showCustomizationPanel($chat_id, $message_id = null) {
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT * FROM settings LIMIT 1");

    $keyboard = [
        [['text' => "🔻 تغییر وضعیت 🔻", 'callback_data' => 'none'], ['text' => "🔸 دستورات 🔸", 'callback_data' => 'none']]
    ];

    $buttons = [
        'sendbut' => "آپلود توسط کاربر",
        'supportbut' => "پشتیبانی",
        'topdlbut' => "پربازدید ها",
        'newdlbut' => "جدیدترین ها",
        'likedlbut' => "محبوب ترین ها",
        'showlikes' => "نمایش لایک و دیسلایک",
        'showdownload' => "نمایش تعداد دانلود",
        'autoacc' => "تایید خودکار فایل کاربران",
    ];

    foreach ($buttons as $key => $text) {
        $status_icon = ($settings[$key] === 'on') ? "✅ روشن" : "❌ خاموش";
        $keyboard[] = [['text' => $status_icon, 'callback_data' => "admin_setting_toggle_{$key}"], ['text' => $text, 'callback_data' => 'none']];
    }

    $bot_type_text = ($settings['bottype'] === 'sub') ? "💰 نسخه اشتراکی" : "♾ نسخه رایگان";
    $keyboard[] = [['text' => "♻️ تغییر کاربری ربات", 'callback_data' => "admin_setting_bottype"]];

    $message = "✔️ برای شخصی سازی ربات یکی از گزینه های زیر را انتخاب کنید:";

    if ($message_id) {
        editMessageText($chat_id, $message_id, $message, ['inline_keyboard' => $keyboard]);
    } else {
        sendMessage($chat_id, $message, ['inline_keyboard' => $keyboard]);
    }
}

function toggleSetting($chat_id, $message_id, $setting_key) {
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT {$setting_key} FROM settings LIMIT 1");

    if ($settings) {
        $new_value = ($settings[$setting_key] === 'on') ? 'off' : 'on';
        $db->execute("UPDATE settings SET {$setting_key} = ?", [$new_value]);
        answerCallbackQuery($GLOBALS['update']->callback_query->id, "وضعیت با موفقیت تغییر کرد.", false);
        showCustomizationPanel($chat_id, $message_id); // Refresh the panel
    }
}

function showBotTypePanel($chat_id, $message_id) {
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT bottype FROM settings LIMIT 1");
    $current_type = ($settings['bottype'] === 'sub') ? "نسخه اشتراکی" : "نسخه رایگان";

    $keyboard = [
        [['text' => "♾ نسخه رایگان ♾", 'callback_data' => "admin_setting_setbottype_free"]],
        [['text' => "💰 نسخه اشتراکی 💰", 'callback_data' => "admin_setting_setbottype_sub"]],
        [['text' => "🔙 بازگشت", 'callback_data' => "admin_setting_main"]]
    ];

    $text = "🔻 یکی از حالت های زیر را برای ربات مشخص کنید:\n\n" .
            "🌐 رایگان: در این حالت ربات برای همه کاربران رایگان خواهد بود.\n" .
            "💎 اشتراکی: در این حالت کاربران برای دانلود فایل ها نیاز به خرید اشتراک دارند.\n\n" .
            "🔹 نسخه کنونی ربات: <b>{$current_type}</b>";

    editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
}

function setBotType($chat_id, $message_id, $type) {
    $db = Database::getInstance();
    $db->execute("UPDATE settings SET bottype = ?", [$type]);
    answerCallbackQuery($GLOBALS['update']->callback_query->id, "نوع ربات با موفقیت تغییر کرد.", false);
    showBotTypePanel($chat_id, $message_id); // Refresh panel to show the new status
}
