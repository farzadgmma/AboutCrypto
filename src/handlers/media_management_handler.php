<?php

function show_media_management_menu($chat_id) {
    $keyboard = [
        [['text' => '🔎 جستجو رسانه'], ['text' => '♻️ تغییر کپشن فایل']],
        [['text' => '📁 دسته بندی رسانه ها'], ['text' => '❌ حذف رسانه']],
    ];
    $reply_markup = [
        'keyboard' => $keyboard,
        'resize_keyboard' => true,
    ];
    send_message($chat_id, "Please select an option:", $reply_markup);
}
