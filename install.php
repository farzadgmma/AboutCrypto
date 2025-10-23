<?php
// install.php
// این اسکریپت تمام جداول مورد نیاز برای ربات را به صورت خودکار در دیتابیس ایجاد و به‌روزرسانی می‌کند.
// لطفاً این فایل را در مسیر اصلی ربات روی هاست خود آپلود کرده و یک بار در مرورگر اجرا کنید.
// پس از مشاهده پیام موفقیت‌آمیز، برای امنیت بیشتر، این فایل را از هاست خود حذف نمایید.

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>نصب و راه‌اندازی دیتابیس ربات</h1>";
echo "<p>این اسکریپت در حال تلاش برای اتصال به دیتابیس و ساخت جداول مورد نیاز است...</p>";
echo "<hr>";

// فراخوانی فایل‌های ضروری با استفاده از مسیردهی دقیق
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/includes/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if ($pdo === null) {
        die("<p style='color:red;'>❌ <b>خطا:</b> اتصال به دیتابیس ناموفق بود. لطفاً از صحت اطلاعات وارد شده در فایل `config/config.php` اطمینان حاصل کنید.</p>");
    }
    echo "<p style='color:green;'>✅ اتصال به دیتابیس با موفقیت برقرار شد.</p><hr>";

    // --- مجموعه‌ای از دستورات SQL برای ساخت جداول ---
    $sql_commands = [

        "CREATE TABLE IF NOT EXISTS `users` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY, `user_id` BIGINT NOT NULL UNIQUE, `first_name` VARCHAR(255) NOT NULL, `username` VARCHAR(255) NULL,
            `step` VARCHAR(255) DEFAULT 'none', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `vip_status` ENUM('no', 'yes') DEFAULT 'no', `vip_expire_date` TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        "CREATE TABLE IF NOT EXISTS `files` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `file_id` VARCHAR(255) NOT NULL, `file_unique_id` VARCHAR(255) NOT NULL UNIQUE, `file_type` VARCHAR(50) NOT NULL,
            `caption` TEXT NULL, `file_code` VARCHAR(20) NOT NULL UNIQUE, `uploader_id` BIGINT NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `password` VARCHAR(255) NULL, `download_limit` INT NULL, `download_count` INT DEFAULT 0, `forward_lock` BOOLEAN DEFAULT TRUE, `channel_lock` BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        "CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT PRIMARY KEY DEFAULT 1, `bot_active` BOOLEAN NOT NULL DEFAULT TRUE, `default_start_text` TEXT NULL, `payment_gateway` ENUM('zarinpal', 'zibal') DEFAULT 'zarinpal',
            `ads_active` BOOLEAN NOT NULL DEFAULT FALSE, `ads_position` ENUM('before', 'after') NOT NULL DEFAULT 'after'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",
        "INSERT IGNORE INTO `settings` (`id`, `default_start_text`) VALUES (1, 'به ربات فایل منیجر خوش آمدید!');",

        "CREATE TABLE IF NOT EXISTS `payment_plans` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `price` INT NOT NULL, `duration_days` INT NOT NULL, `is_active` BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",
        "INSERT IGNORE INTO `payment_plans` (`id`, `name`, `price`, `duration_days`, `is_active`) VALUES
            (1, 'اشتراک ۱ ماهه', 10000, 30, 1), (2, 'اشتراک ۳ ماهه', 25000, 90, 1), (3, 'اشتراک ۶ ماهه', 45000, 180, 1),
            (4, 'اشتراک ۱ ساله', 80000, 365, 1), (5, 'پلن غیرفعال ۱', 0, 0, 0), (6, 'پلن غیرفعال ۲', 0, 0, 0);",

        "CREATE TABLE IF NOT EXISTS `transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` BIGINT NOT NULL, `plan_id` INT NOT NULL, `amount` INT NOT NULL,
            `reference_id` VARCHAR(255) NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        "CREATE TABLE IF NOT EXISTS `forced_settings` (
            `id` INT PRIMARY KEY DEFAULT 1, `join_active` BOOLEAN DEFAULT FALSE, `seen_active` BOOLEAN DEFAULT FALSE, `seen_channel` VARCHAR(255) NULL,
            `seen_post_count` INT DEFAULT 5, `reaction_active` BOOLEAN DEFAULT FALSE, `reaction_channel` VARCHAR(255) NULL, `reaction_post_count` INT DEFAULT 5
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",
        "INSERT IGNORE INTO `forced_settings` (`id`) VALUES (1);",

        "CREATE TABLE IF NOT EXISTS `forced_join_channels` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `channel_identifier` VARCHAR(255) NOT NULL UNIQUE, `invite_link` VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;",

        "CREATE TABLE IF NOT EXISTS `ads` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `type` ENUM('text', 'photo', 'video', 'document', 'audio', 'voice') NOT NULL,
            `file_id` VARCHAR(255) NULL, `caption` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;"
    ];

    // اجرای دستورات SQL
    foreach ($sql_commands as $command) {
        $pdo->exec($command);
        if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $command, $matches)) {
            $table_name = $matches[1];
            echo "<p>✅ جدول `{$table_name}` با موفقیت ایجاد/بررسی شد.</p>";
        }
    }

    // --- به‌روزرسانی ساختار جداول موجود ---
    echo "<hr><p>در حال بررسی و به‌روزرسانی ساختار جداول...</p>";
    try {
        $pdo->exec("ALTER TABLE `forced_join_channels` ADD COLUMN `invite_link` VARCHAR(255) NULL AFTER `channel_identifier`;");
        echo "<p>✅ ستون `invite_link` با موفقیت به جدول `forced_join_channels` اضافه شد.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>🔵 ستون `invite_link` از قبل در جدول `forced_join_channels` وجود داشت.</p>";
        } else {
            throw $e;
        }
    }

    echo "<hr><h2>🎉 تمام جداول با موفقیت ساخته و به‌روزرسانی شدند!</h2>";
    echo "<p style='color:orange;'><b>مهم:</b> لطفاً برای امنیت بیشتر، اکنون این فایل (`install.php`) را از هاست خود حذف کنید.</p>";

} catch (PDOException $e) {
    die("<p style='color:red;'>❌ <b>خطای دیتابیس:</b> ". $e->getMessage() . "</p>");
}
?>