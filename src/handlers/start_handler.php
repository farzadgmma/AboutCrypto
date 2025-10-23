<?php
// src/handlers/start_handler.php
// این فایل مسئول مدیریت منطق مربوط به دستور /start است.

/**
 * این تابع زمانی فراخوانی می‌شود که کاربر دستور /start را ارسال می‌کند.
 * وظیفه آن نمایش پیام خوشامدگویی و منوی اصلی دکمه‌ها به کاربر است.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param stdClass $update آبجکت کامل آپدیت از تلگرام
 */
function handle_start($pdo, $update) {
    $chat_id = $update->message->chat->id;
    $user_id = $update->message->from->id;

    // دریافت متن خوشامدگویی پیش‌فرض از جدول تنظیمات دیتابیس
    $stmt = $pdo->query("SELECT default_start_text FROM settings WHERE id = 1");
    $start_text = $stmt->fetchColumn();
    // اگر متنی در دیتابیس تنظیم نشده بود، یک متن پیش‌فرض نمایش داده می‌شود
    if (!$start_text) {
        $start_text = "به ربات ما خوش آمدید!";
    }

    // پاک کردن وضعیت کاربر (اگر در مکالمه چند مرحله‌ای بوده، از آن خارج می‌شود)
    clear_user_state($user_id);

    // ساخت منوی اصلی دکمه‌ها برای کاربر
    $keyboard = [
        [['text' => '👤 حساب کاربری']],
        // در آینده دکمه‌های دیگری مانند "جستجوی فایل" یا "راهنما" می‌توان به اینجا اضافه کرد
    ];

    // اگر کاربری که /start را ارسال کرده ادمین باشد، دکمه‌های پنل مدیریت نیز به او نمایش داده می‌شود
    if (in_array($user_id, ADMINS)) {
        // اضافه کردن یک ردیف جدید برای دکمه‌های ادمین
        $admin_buttons_row1 = [
            ['text' => '📤 آپلود تکی/آلبومی رسانه'],
            ['text' => '📊 آمار']
        ];
        $admin_buttons_row2 = [
            ['text' => '💰 تنظیمات پرداخت'],
            ['text' => '👁‍🗨 ری اکشن/سین اجباری']
        ];
        $admin_buttons_row3 = [
            ['text' => '📢 تنظیم تبلیغات']
        ];
        // اضافه کردن دکمه‌های ادمین به بالای کیبورد اصلی
        array_unshift($keyboard, $admin_buttons_row1, $admin_buttons_row2, $admin_buttons_row3);
    }

    // تبدیل آرایه کیبورد به فرمت JSON برای ارسال به تلگرام
    $reply_markup = json_encode([
        'keyboard' => $keyboard,
        'resize_keyboard' => true // این گزینه باعث می‌شود کیبورد اندازه مناسبی داشته باشد
    ]);

    // ارسال پیام خوشامدگویی به همراه کیبورد به کاربر
    send_message($chat_id, $start_text, $reply_markup);
}
