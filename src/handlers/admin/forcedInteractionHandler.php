<?php
// src/handlers/admin/forcedInteractionHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showForcedInteractionPanel($chat_id, $message_id = null) {
    $keyboard = [
        [['text' => "👌🏻 تنظیم ری اکشن اجباری"], ['text' => "👁‍🗨 تنظیم سین اجباری"]],
        [['text' => "🔙 منوی پنل"]]
    ];
    $text = "<b>🔻 یکی از گزینه های زیر را انتخاب کنید:</b>";

    if($message_id){
        editMessageText($chat_id, $message_id, $text, ['keyboard' => $keyboard, 'resize_keyboard' => true]);
    } else {
        sendMessage($chat_id, $text, ['keyboard' => $keyboard, 'resize_keyboard' => true]);
    }
}

// Functions for reaction settings
function showReactionPanel($chat_id, $message_id) {
    // ... implementation ...
}

// Functions for seen settings
function showSeenPanel($chat_id, $message_id) {
    // ... implementation ...
}
