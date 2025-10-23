<?php
// src/handlers/password_handler.php

/**
 * مسئولیت پردازش رمز عبور ارسال شده توسط کاربر را بر عهده دارد.
 *
 * @param PDO $pdo آبجکت اتصال به دیتابیس.
 * @param int $user_id شناسه کاربری تلگرام.
 * @param string $submitted_password رمز عبور وارد شده توسط کاربر.
 * @param string $file_code کد فایلی که کاربر قصد دانلود آن را دارد.
 */
function handle_password_submission($pdo, $user_id, $submitted_password, $file_code)
{
    // دریافت اطلاعات فایل برای بررسی صحت رمز عبور
    $stmt = $pdo->prepare("SELECT * FROM files WHERE file_code = ?");
    $stmt->execute([$file_code]);
    $file = $stmt->fetch();

    // اگر فایل پیدا نشد (که بعید است در این مرحله اتفاق بیفتد)
    if (!$file) {
        sendMessage($user_id, "❌ خطای غیرمنتظره: فایل مورد نظر یافت نشد.");
        clear_user_state($user_id); // پاک کردن وضعیت کاربر
        return;
    }

    // بررسی صحت رمز عبور
    if ($submitted_password === $file['password']) {
        // رمز صحیح است
        sendMessage($user_id, "✅ رمز عبور صحیح است. اکنون فایل برای شما ارسال می‌شود...");

        // پاک کردن وضعیت کاربر
        clear_user_state($user_id);

        // ارسال فایل به کاربر (تابع در download_handler.php تعریف شده)
        require_once __DIR__ . '/download_handler.php';
        send_file_to_user($user_id, $file);

    } else {
        // رمز اشتباه است
        sendMessage(
            $user_id,
            "❌ رمز عبور اشتباه است. لطفاً دوباره تلاش کنید یا روی 'انصراف' کلیک کنید."
        );
        // وضعیت کاربر تغییر نمی‌کند تا بتواند دوباره تلاش کند.
    }
}
