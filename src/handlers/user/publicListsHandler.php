<?php
// src/handlers/user/publicListsHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function handleTopDownloadsCommand($chat_id, $message_id = null) {
    $db = Database::getInstance();
    $files = $db->fetchAll("SELECT code, type, dl FROM files GROUP BY code ORDER BY dl DESC LIMIT 10");

    if (empty($files)) {
        sendMessage($chat_id, "❌ چیزی آپلود نشده است.");
        return;
    }

    $inline_keyboard = [];
    foreach ($files as $file) {
        $type_fa = doc($file['type']);
        $button_text = "🌀 کد: {$file['code']} | 🔖 نوع: {$type_fa} | 📥 دانلود: {$file['dl']}";
        $inline_keyboard[] = [['text' => $button_text, 'url' => "https://t.me/" . BOT_USERNAME . "?start=dl_{$file['code']}"]];
    }
    $inline_keyboard[] = [['text' => "♻️ بروزرسانی ♻️", 'callback_data' => "refresh_top"]];

    $text = "🔥 <b>پردانلودترین و پربازدیدترین فایل ها:</b>\n\n🚸 برای دانلود روی دکمه مورد نظر کلیک کنید.";

    if ($message_id) {
        editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $inline_keyboard]);
    } else {
        sendMessage($chat_id, $text, ['inline_keyboard' => $inline_keyboard]);
    }
}

function handleNewestFilesCommand($chat_id, $message_id = null) {
    $db = Database::getInstance();
    $files = $db->fetchAll("SELECT code, type, dl, zaman FROM files GROUP BY code ORDER BY zaman DESC LIMIT 10");

    if (empty($files)) {
        sendMessage($chat_id, "❌ چیزی آپلود نشده است.");
        return;
    }

    $inline_keyboard = [];
    foreach ($files as $file) {
        $type_fa = doc($file['type']);
        $button_text = "🌀 کد: {$file['code']} | 🔖 نوع: {$type_fa} | 📥 دانلود: {$file['dl']}";
        $inline_keyboard[] = [['text' => $button_text, 'url' => "https://t.me/" . BOT_USERNAME . "?start=dl_{$file['code']}"]];
    }
    $inline_keyboard[] = [['text' => "♻️ بروزرسانی ♻️", 'callback_data' => "refresh_newest"]];

    $text = "🕐 <b>جدیدترین فایل ها:</b>\n\n🚸 برای دانلود روی دکمه مورد نظر کلیک کنید.";

    if ($message_id) {
        editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $inline_keyboard]);
    } else {
        sendMessage($chat_id, $text, ['inline_keyboard' => $inline_keyboard]);
    }
}

function handleMostLikedCommand($chat_id, $message_id = null) {
    $db = Database::getInstance();
    $files = $db->fetchAll("SELECT code, type, dl, SUM(likes) as total_likes FROM files GROUP BY code ORDER BY total_likes DESC LIMIT 10");

    if (empty($files)) {
        sendMessage($chat_id, "❌ چیزی آپلود نشده است.");
        return;
    }

    $inline_keyboard = [];
    foreach ($files as $file) {
        $type_fa = doc($file['type']);
        $button_text = "🌀 کد: {$file['code']} | 🔖 نوع: {$type_fa} | 👍🏻 لایک: {$file['total_likes']}";
        $inline_keyboard[] = [['text' => $button_text, 'url' => "https://t.me/" . BOT_USERNAME . "?start=dl_{$file['code']}"]];
    }
    $inline_keyboard[] = [['text' => "♻️ بروزرسانی ♻️", 'callback_data' => "refresh_liked"]];

    $text = "❤️ <b>محبوب ترین فایل ها:</b>\n\n🚸 برای دانلود روی دکمه مورد نظر کلیک کنید.";

    if ($message_id) {
        editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $inline_keyboard]);
    } else {
        sendMessage($chat_id, $text, ['inline_keyboard' => $inline_keyboard]);
    }
}
