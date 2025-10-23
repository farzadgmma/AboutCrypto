<?php
// src/includes/state_manager.php
// این فایل شامل توابعی برای مدیریت "وضعیت" (state) کاربران است.
// "وضعیت" به ما کمک می‌کند تا بفهمیم کاربر در کجای یک مکالمه چند مرحله‌ای قرار دارد.
// برای مثال، وقتی ربات از کاربر می‌خواهد "نام خود را وارد کنید"، ما وضعیت کاربر را روی 'awaiting_name' تنظیم می‌کنیم.
// پیام بعدی کاربر به عنوان نام او پردازش خواهد شد.

/**
 * وضعیت فعلی یک کاربر را تنظیم یا ذخیره می‌کند.
 * این اطلاعات در جدول users در ستون 'step' ذخیره می‌شود.
 * @param int $user_id شناسه عددی کاربر
 * @param string $state نام وضعیتی که باید برای کاربر ذخیره شود (مثلاً 'editing_sub_name:1')
 */
function set_user_state($user_id, $state) {
    global $pdo; // دسترسی به آبجکت اتصال دیتابیس که در bot.php ایجاد شده است

    // آماده‌سازی دستور SQL برای به‌روزرسانی ستون 'step'
    $stmt = $pdo->prepare("UPDATE users SET step = ? WHERE user_id = ?");
    // اجرای دستور با مقادیر ورودی
    $stmt->execute([$state, $user_id]);
}

/**
 * وضعیت فعلی یک کاربر را از دیتابیس دریافت می‌کند.
 * @param int $user_id شناسه عددی کاربر
 * @return string|null وضعیت فعلی کاربر را به صورت رشته باز می‌گرداند، یا null اگر وضعیتی نداشته باشد.
 */
function get_user_state($user_id) {
    global $pdo;

    // آماده‌سازی دستور SQL برای خواندن ستون 'step'
    $stmt = $pdo->prepare("SELECT step FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // دریافت نتیجه
    $result = $stmt->fetchColumn();

    // اگر ستون 'step' مقداری غیر از 'none' و غیر null داشته باشد، آن را باز می‌گرداند
    return ($result && $result !== 'none') ? $result : null;
}

/**
 * وضعیت یک کاربر را پاک می‌کند (به حالت 'none' برمی‌گرداند).
 * این تابع معمولاً پس از اتمام یک مکالمه چند مرحله‌ای فراخوانی می‌شود.
 * @param int $user_id شناسه عددی کاربر
 */
function clear_user_state($user_id) {
    // برای پاک کردن وضعیت، کافی است آن را روی 'none' تنظیم کنیم.
    set_user_state($user_id, 'none');
}

?>