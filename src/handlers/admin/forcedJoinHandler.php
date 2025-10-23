<?php
// src/handlers/admin/forcedJoinHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showForcedJoinPanel($chat_id, $message_id = null) {
    $db = Database::getInstance();
    $channels = $db->fetchAll("SELECT * FROM channels");

    $text = "📣 به بخش مدیریت کانال جوین اجباری و لینک خوش آمدید\n\n";
    $keyboard = [];

    if (empty($channels)) {
        $text .= "❌ هیچ لینک یا کانالی تنظیم نشده.";
    } else {
        $text .= "👇🏻 لیست تمام کانال ها و لینک های اجباری:";
        foreach ($channels as $channel) {
            $name = ($channel['type'] === 'telegram') ? getChannelTitle($channel['idoruser']) : $channel['idoruser'];
            $keyboard[] = [
                ['text' => htmlspecialchars($name), 'url' => $channel['link']],
                ['text' => "❌ حذف", 'callback_data' => "admin_join_deleteconfirm_{$channel['idoruser']}"]
            ];
        }
    }

    $keyboard[] = [['text' => "➕ افزودن کانال یا لینک", 'callback_data' => "admin_join_add"]];

    if($message_id){
        editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
    } else {
        sendMessage($chat_id, $text, ['inline_keyboard' => $keyboard]);
    }
}

function askAddJoinType($chat_id, $message_id) {
    $keyboard = [
        [['text' => "🔹 کانال عمومی 🔹", 'callback_data' => "admin_join_addtype_public"]],
        [['text' => "🔸 کانال خصوصی 🔸", 'callback_data' => "admin_join_addtype_private"]],
        [['text' => "🌐 لینک دلخواه", 'callback_data' => "admin_join_addtype_custom"]],
        [['text' => "🔙 بازگشت", 'callback_data' => "admin_join_main"]]
    ];
    editMessageText($chat_id, $message_id, "🔻 نوع کانال یا لینک مورد نظر را انتخاب کنید:", ['inline_keyboard' => $keyboard]);
}

function deleteJoinChannel($chat_id, $message_id, $channel_idoruser) {
    $db = Database::getInstance();
    $db->execute("DELETE FROM channels WHERE idoruser = ?", [$channel_idoruser]);
    answerCallbackQuery($GLOBALS['update']->callback_query->id, "✅ کانال/لینک با موفقیت حذف شد.", false);
    showForcedJoinPanel($chat_id, $message_id); // Refresh the list
}
