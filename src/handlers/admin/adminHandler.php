<?php
// src/handlers/admin/adminHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function handleAdminState($user, $message) {
    $step = $user['step'];
    $text = $message->text;
    $chat_id = $message->chat->id;
    $user_id = $user['id'];

    // This function will act as a router for various admin states.
    // For example, if an admin is adding a password to a file,
    // their 'step' would be something like 'awaiting_password_SET_{file_code}'.

    // Example: Handling the password setting state
    if (strpos($step, 'awaiting_password_') === 0) {
        $file_code = substr($step, strlen('awaiting_password_'));
        // Logic to update the password for $file_code with $text
        // (This will be fully implemented in a dedicated file management handler)

        // For now, a placeholder:
        $db = Database::getInstance();
        $db->execute("UPDATE files SET pass = ? WHERE code = ?", [$text, $file_code]);
        setUserStep($user_id, 'none');
        sendMessage($chat_id, "✅ رمز عبور برای فایل `$file_code` با موفقیت تنظیم شد.");
    }
    // Add other state handlers here...
}

function showAdminPanel($chat_id) {
    $keyboard = [
        [['text' => "📤 آپلود تکی/آلبومی رسانه"], ['text' => "📂 پوشه سازی رسانه ها"]],
        [['text' => "📊 آمار"], ['text' => "📩 ارسال همگانی"], ['text' => "🎨 شخصی سازی"]],
        [['text' => "🔐 جوین اجباری"], ['text' => "🗂 مدیریت رسانه"]],
        [['text' => "👁‍🗨 ری اکشن/سین اجباری"], ['text' => "💰 تنظیمات پرداخت"]],
        [['text' => "📢 تنظیم تبلیغات"], ['text' => "🔍 جستجوی کاربر"], ['text' => "👨🏻‍💻 مدیریت ادمین"]],
        [['text' => "♻️ آپدیت ربات"], ['text' => "🏠 برگشت به منو"]]
    ];

    sendMessage($chat_id, "به پنل مدیریت خوش آمدید.", ['keyboard' => $keyboard, 'resize_keyboard' => true]);
}
