<?php
// src/handlers/stats_handler.php
// این فایل مسئولیت جمع‌آوری و نمایش آمار کلی ربات به ادمین را بر عهده دارد.

/**
 * این تابع زمانی فراخوانی می‌شود که ادمین روی دکمه '📊 آمار' کلیک می‌کند.
 * آمار مختلف را از دیتابیس استخراج کرده و در یک پیام به ادمین نمایش می‌دهد.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param int $chat_id شناسه چت ادمین
 */
function handle_stats_request($pdo, $chat_id) {
    // --- جمع‌آوری آمار کاربران ---

    // شمارش تعداد کل کاربران ثبت‌نام شده
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // شمارش تعداد کاربران فعال در 5 دقیقه اخیر (آنلاین)
    $five_minutes_ago = date('Y-m-d H:i:s', time() - 300);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE last_activity > ?");
    $stmt->execute([$five_minutes_ago]);
    $online_users = $stmt->fetchColumn();

    // شمارش تعداد کاربران جدید در 24 ساعت گذشته
    $twenty_four_hours_ago = date('Y-m-d H:i:s', time() - 86400);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at > ?");
    $stmt->execute([$twenty_four_hours_ago]);
    $new_users_24h = $stmt->fetchColumn();

    // --- جمع‌آوری آمار فایل‌ها ---

    // شمارش تعداد کل فایل‌های آپلود شده
    $total_files = $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();

    // شمارش تعداد کل دانلودها (این بخش نیاز به یک ستون شمارنده دانلود در جدول files دارد)
    // فرض می‌کنیم ستونی به نام 'download_count' داریم.
    // $total_downloads = $pdo->query("SELECT SUM(download_count) FROM files")->fetchColumn() ?: 0;

    // --- جمع‌آوری آمار اشتراک‌ها ---

    // شمارش تعداد کاربران با اشتراک فعال
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE vip_status = 'yes' AND vip_expire_date > NOW()");
    $active_vip_users = $stmt->fetchColumn();

    // ساخت متن پیام آمار
    $text = "📊 <b>آمار کلی ربات</b>\n\n";
    $text .= "👥 <b>آمار کاربران:</b>\n";
    $text .= "▫️ کل کاربران: <code>{$total_users}</code>\n";
    $text .= "▫️ کاربران آنلاین (5 دقیقه اخیر): <code>{$online_users}</code>\n";
    $text .= "▫️ کاربران جدید (24 ساعت اخیر): <code>{$new_users_24h}</code>\n\n";

    $text .= "📁 <b>آمار فایل‌ها:</b>\n";
    $text .= "▫️ کل فایل‌های آپلود شده: <code>{$total_files}</code>\n";
    // $text .= "▫️ مجموع کل دانلودها: <code>{$total_downloads}</code>\n\n";

    $text .= "💎 <b>آمار اشتراک‌ها:</b>\n";
    $text .= "▫️ کاربران با اشتراک فعال: <code>{$active_vip_users}</code>\n\n";

    $text .= "🗓 <b>تاریخ سرور:</b> " . date('Y-m-d H:i:s');

    // ارسال پیام به ادمین
    send_message($chat_id, $text);
}
?>