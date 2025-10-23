<?php
// src/handlers/account_handler.php
// این فایل مسئول مدیریت منطق مربوط به نمایش اطلاعات حساب کاربری است.

/**
 * این تابع زمانی فراخوانی می‌شود که کاربر روی دکمه '👤 حساب کاربری' کلیک می‌کند.
 * وظیفه آن دریافت اطلاعات کاربر از دیتابیس و نمایش آن به صورت مرتب است.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param stdClass $update آبجکت کامل آپدیت از تلگرام
 */
function handle_account_display($pdo, $update) {
    $chat_id = $update->message->chat->id;
    $user_id = $update->message->from->id;

    // دریافت اطلاعات کاربر از جدول 'users'
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // اگر به هر دلیلی کاربر در دیتابیس وجود نداشت، یک پیام خطا نمایش داده می‌شود
    if (!$user) {
        send_message($chat_id, "خطایی در بازیابی اطلاعات شما رخ داد. لطفاً دوباره /start را بزنید.");
        return;
    }

    // آماده‌سازی متن پیام اطلاعات کاربری

    // نمایش نام و نام کاربری
    $account_info = "👤 <b>اطلاعات حساب کاربری شما:</b>\n\n";
    $account_info .= "▫️ <b>نام:</b> " . htmlspecialchars($user['first_name']) . "\n";
    $account_info .= "▫️ <b>شناسه عددی:</b> <code>" . $user['user_id'] . "</code>\n";

    // بررسی وضعیت اشتراک ویژه (VIP)
    if ($user['vip_status'] === 'yes' && strtotime($user['vip_expire_date']) > time()) {
        // اگر کاربر اشتراک فعال داشته باشد
        $account_info .= "💎 <b>وضعیت اشتراک:</b> ویژه (VIP)\n";
        // تبدیل تاریخ انقضا به فرمت خوانا
        $expire_date = date('Y-m-d', strtotime($user['vip_expire_date']));
        $account_info .= "🗓 <b>تاریخ انقضا:</b> " . $expire_date . "\n";
    } else {
        // اگر کاربر اشتراک نداشته باشد یا منقضی شده باشد
        $account_info .= "▫️ <b>وضعیت اشتراک:</b> عادی\n";
    }

    // تاریخ عضویت کاربر در ربات
    $join_date = date('Y-m-d', strtotime($user['created_at']));
    $account_info .= "📅 <b>تاریخ عضویت:</b> " . $join_date . "\n";

    // ارسال پیام به کاربر
    send_message($chat_id, $account_info);
}
