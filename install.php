<?php
// install.php
// این اسکریپت تمام جداول مورد نیاز برای ربات را به صورت خودکار در دیتابیس ایجاد می‌کند.
// لطفاً این فایل را در مسیر اصلی ربات روی هاست خود آپلود کرده و یک بار در مرورگر اجرا کنید.
// پس از مشاهده پیام موفقیت‌آمیز، برای امنیت بیشتر، این فایل را از هاست خود حذف نمایید.

// فعال‌سازی نمایش خطاها برای اشکال‌زدایی در حین نصب
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>نصب و راه‌اندازی دیتابیس ربات</h1>";
echo "<p>این اسکریپت در حال تلاش برای اتصال به دیتابیس و ساخت جداول مورد نیاز است...</p>";
echo "<hr>";

// فراخوانی فایل‌های ضروری با استفاده از مسیردهی دقیق
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/includes/database.php';

try {
    // ایجاد یک نمونه از کلاس دیتابیس برای برقراری اتصال
    $db = new Database();
    $pdo = $db->getConnection();

    // بررسی موفقیت‌آمیز بودن اتصال
    if ($pdo === null) {
        die("<p style='color:red;'>❌ <b>خطا:</b> اتصال به دیتابیس ناموفق بود. لطفاً از صحت اطلاعات وارد شده در فایل `config/config.php` اطمینان حاصل کنید.</p>");
    }
    echo "<p style='color:green;'>✅ اتصال به دیتابیس با موفقیت برقرار شد.</p><hr>";

    // --- مجموعه‌ای از دستورات SQL برای ساخت جداول ---
    // از `CREATE TABLE IF NOT EXISTS` استفاده شده تا اگر جدولی از قبل وجود داشت، خطایی رخ ندهد.
    $sql_commands = [

        // جدول کاربران (users)
        // برای نگهداری اطلاعات اصلی کاربران ربات
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT NOT NULL UNIQUE,
            `first_name` VARCHAR(255) NOT NULL,
            `username` VARCHAR(255) NULL,
            `step` VARCHAR(255) DEFAULT 'none',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `vip_status` ENUM('no', 'yes') DEFAULT 'no',
            `vip_expire_date` TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        // جدول فایل‌ها (files)
        // برای نگهداری اطلاعات مربوط به هر فایل آپلود شده
        "CREATE TABLE IF NOT EXISTS `files` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `file_id` VARCHAR(255) NOT NULL,
            `file_unique_id` VARCHAR(255) NOT NULL UNIQUE,
            `file_type` VARCHAR(50) NOT NULL,
            `caption` TEXT NULL,
            `file_code` VARCHAR(20) NOT NULL UNIQUE,
            `uploader_id` BIGINT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `password` VARCHAR(255) NULL,
            `download_limit` INT NULL,
            `download_count` INT DEFAULT 0,
            `forward_lock` BOOLEAN DEFAULT TRUE,
            `channel_lock` BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        // جدول تنظیمات اصلی ربات (settings)
        "CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT PRIMARY KEY DEFAULT 1,
            `bot_active` BOOLEAN NOT NULL DEFAULT TRUE,
            `default_start_text` TEXT NULL,
            `payment_gateway` ENUM('zarinpal', 'zibal') DEFAULT 'zarinpal',
            `ads_active` BOOLEAN NOT NULL DEFAULT FALSE,
            `ads_position` ENUM('before', 'after') NOT NULL DEFAULT 'after'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        // درج مقادیر اولیه برای جدول تنظیمات
        "INSERT IGNORE INTO `settings` (`id`, `default_start_text`) VALUES (1, 'به ربات فایل منیجر خوش آمدید!');",

        // جدول پلن‌های پرداخت (payment_plans)
        "CREATE TABLE IF NOT EXISTS `payment_plans` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `price` INT NOT NULL,
            `duration_days` INT NOT NULL,
            `is_active` BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        // درج پلن‌های اشتراک نمونه
        "INSERT IGNORE INTO `payment_plans` (`id`, `name`, `price`, `duration_days`, `is_active`) VALUES
            (1, 'اشتراک ۱ ماهه', 10000, 30, 1),
            (2, 'اشتراک ۳ ماهه', 25000, 90, 1),
            (3, 'اشتراک ۶ ماهه', 45000, 180, 1),
            (4, 'اشتراک ۱ ساله', 80000, 365, 1),
            (5, 'پلن غیرفعال ۱', 0, 0, 0),
            (6, 'پلن غیرفعال ۲', 0, 0, 0);",

        // جدول تراکنش‌ها (transactions)
        // برای لاگ کردن تمام پرداخت‌های موفق
        "CREATE TABLE IF NOT EXISTS `transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT NOT NULL,
            `plan_id` INT NOT NULL,
            `amount` INT NOT NULL,
            `reference_id` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        // جدول تنظیمات تعاملات اجباری (forced_settings)
        "CREATE TABLE IF NOT EXISTS `forced_settings` (
            `id` INT PRIMARY KEY DEFAULT 1,
            `join_active` BOOLEAN DEFAULT FALSE,
            `seen_active` BOOLEAN DEFAULT FALSE,
            `seen_channel` VARCHAR(255) NULL,
            `seen_post_count` INT DEFAULT 5,
            `reaction_active` BOOLEAN DEFAULT FALSE,
            `reaction_channel` VARCHAR(255) NULL,
            `reaction_post_count` INT DEFAULT 5
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        "INSERT IGNORE INTO `forced_settings` (`id`) VALUES (1);",

        // جدول کانال‌های عضویت اجباری (forced_join_channels)
        "CREATE TABLE IF NOT EXISTS `forced_join_channels` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `channel_identifier` VARCHAR(255) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        // جدول تبلیغات (ads)
        "CREATE TABLE IF NOT EXISTS `ads` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `type` ENUM('text', 'photo', 'video', 'document', 'audio', 'voice') NOT NULL,
            `file_id` VARCHAR(255) NULL,
            `caption` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;"
    ];

    // اجرای تک تک دستورات SQL
    foreach ($sql_commands as $command) {
        $pdo->exec($command);
        // استخراج نام جدول از دستور برای نمایش پیام موفقیت‌آمیز
        if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $command, $matches)) {
            $table_name = $matches[1];
            echo "<p>✅ جدول `{$table_name}` با موفقیت ایجاد شد (یا از قبل وجود داشت).</p>";
        }
    }

    echo "<hr><h2>🎉 تمام جداول با موفقیت ساخته شدند!</h2>";
    echo "<p style='color:orange;'><b>مهم:</b> لطفاً برای امنیت بیشتر، اکنون این فایل (`install.php`) را از هاست خود حذف کنید.</p>";

} catch (PDOException $e) {
    // در صورت بروز هرگونه خطا در اتصال یا اجرای دستورات، پیغام خطا نمایش داده می‌شود.
    die("<p style='color:red;'>❌ <b>خطای دیتابیس:</b> ". $e->getMessage() . "</p>");
}
?>