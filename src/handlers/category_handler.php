<?php

function handle_category_start($chat_id) {
    $keyboard = [
        [['text' => 'Movies', 'callback_data' => 'cat_movies']],
        [['text' => 'Music', 'callback_data' => 'cat_music']],
        [['text' => 'Other Files', 'callback_data' => 'cat_other']],
    ];
    $reply_markup = ['inline_keyboard' => $keyboard];
    send_message($chat_id, "Select a category:", $reply_markup);
}
