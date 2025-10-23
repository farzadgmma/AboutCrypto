<?php
// src/handlers/admin/userManagementHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showUserSearchPanel($chat_id) {
    setUserStep($chat_id, 'admin_awaiting_user_id');
    sendMessage($chat_id, "✔️ ایدی عددی کاربر مورد نظر را ارسال کنید:", ['keyboard' => [[['text' => '🔙 منوی پنل']]], 'resize_keyboard' => true]);
}

function handleUserSearch($admin_chat_id, $target_user_id) {
    $db = Database::getInstance();
    $user_info = $db->fetch("SELECT * FROM user WHERE id = ?", [$target_user_id]);

    if (!$user_info) {
        sendMessage($admin_chat_id, "❌ کاربری با این آیدی یافت نشد.");
        return;
    }

    // Admins cannot view details of other admins for security.
    if (isAdmin($target_user_id)) {
        sendMessage($admin_chat_id, "❌ امکان مشاهده اطلاعات ادمین‌ها وجود ندارد.");
        return;
    }

    $subscription_type = ($user_info['vip'] === 'yes') ? "<b>💎 اشتراک ویژه</b>" : "<b>اشتراک عادی</b>";
    $is_banned = ($user_info['step'] === 'ban');

    $joindate_parts = explode("-", $user_info['timejoin']);
    $joinus_jalali = gregorian_to_jalali($joindate_parts[0], $joindate_parts[1], $joindate_parts[2], "/");

    $message_text = "👤 <b>اطلاعات حساب کاربری:</b>\n\n" .
                    "▪️ آیدی عددی: <code>{$user_info['id']}</code>\n" .
                    "▪️ نام کاربری: <b>" . htmlspecialchars($user_info['name']) . "</b>\n" .
                    "💎 نوع اشتراک: {$subscription_type}\n" .
                    "📥 تعداد دانلودها: <code>{$user_info['dl']}</code>\n" .
                    "📅 تاریخ عضویت: {$joinus_jalali}";

    $keyboard = [[
        ['text' => $is_banned ? "✅ آنبلاک کاربر" : "⛔️ بلاک کاربر", 'callback_data' => "admin_user_" . ($is_banned ? 'unban' : 'ban') . "_{$user_info['id']}"],
        ['text' => "👁 پیوی کاربر", 'url' => "tg://user?id={$user_info['id']}"]
    ]];

    sendMessage($admin_chat_id, $message_text, ['inline_keyboard' => $keyboard]);
}

function handleUserBan($admin_chat_id, $user_id, $message_id) {
    $db = Database::getInstance();
    $db->execute("UPDATE user SET step = 'ban' WHERE id = ?", [$user_id]);
    sendMessage($user_id, "🔴 حساب کاربری شما توسط مدیریت مسدود شد.");
    answerCallbackQuery($GLOBALS['update']->callback_query->id, "کاربر با موفقیت بلاک شد.", true);
    // Refresh the info message
    handleUserSearch($admin_chat_id, $user_id);
    // Delete the old message to avoid clutter
    apiRequest('deleteMessage', ['chat_id' => $admin_chat_id, 'message_id' => $message_id]);
}

function handleUserUnban($admin_chat_id, $user_id, $message_id) {
    $db = Database::getInstance();
    $db->execute("UPDATE user SET step = 'none' WHERE id = ?", [$user_id]);
    sendMessage($user_id, "🟢 حساب شما توسط مدیریت از حالت مسدودیت خارج شد.");
    answerCallbackQuery($GLOBALS['update']->callback_query->id, "کاربر با موفقیت آنبلاک شد.", true);
    // Refresh the info message
    handleUserSearch($admin_chat_id, $user_id);
    // Delete the old message
    apiRequest('deleteMessage', ['chat_id' => $admin_chat_id, 'message_id' => $message_id]);
}
