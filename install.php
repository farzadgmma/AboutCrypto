<?php

// ============== P H P - B O T ==============
// ------ I N S T A L L A T I O N - F I L E ------
// ---- C R Y P T O 1 F I L M . O N L I N E ----

// --- این فایل را فقط یک بار پس از آپلود در مرورگر خود اجرا کنید ---
// --- این اسکریپت تمام جداول مورد نیاز ربات را در دیتابیس شما ایجاد می‌کند ---

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='direction: ltr; text-align: left; font-family: monospace;'>";

// اتصال به فایل تنظیمات
require_once 'config.php';

// پیام شروع
echo "Attempting to connect to database 'DB_NAME' on 'DB_HOST'...\n";

try {
    // ایجاد اتصال PDO
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connection successful!\n";

    // ایجاد دیتابیس در صورت عدم وجود
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `" . DB_NAME . "`;");
    echo "Database '" . DB_NAME . "' is ready.\n\n";

    // --- لیست دستورات SQL برای ایجاد جداول ---
    $sql_commands = [
        "CREATE TABLE IF NOT EXISTS `user` (
            `id` BIGINT PRIMARY KEY,
            `step` VARCHAR(255) DEFAULT 'none',
            `step2` TEXT,
            `step3` TEXT,
            `step4` TEXT,
            `step5` TEXT,
            `spam` BIGINT DEFAULT 0,
            `timejoin` VARCHAR(255),
            `vip` VARCHAR(10) DEFAULT 'no',
            `viptime` DATE,
            `dl` INT DEFAULT 0,
            `expireok` VARCHAR(10) DEFAULT 'no',
            `name` VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `files` (
            `id` BIGINT,
            `code` VARCHAR(255),
            `msg_id` VARCHAR(255) DEFAULT 'none',
            `ghfl_ch` VARCHAR(10) DEFAULT 'on',
            `zd_filter` VARCHAR(10) DEFAULT 'off',
            `dl` INT DEFAULT 1,
            `pass` VARCHAR(255) DEFAULT 'none',
            `mahdodl` VARCHAR(255) DEFAULT 'none',
            `zaman` VARCHAR(255),
            `likes` INT DEFAULT 0,
            `dislikes` INT DEFAULT 0,
            `file_id` TEXT,
            `file_size` VARCHAR(255),
            `caption` TEXT,
            `type` VARCHAR(50),
            `thumbnail` TEXT,
            `fwlock` VARCHAR(10) DEFAULT 'on'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `userfiles` (
            `code` VARCHAR(255),
            `id` BIGINT,
            `file_id` TEXT,
            `caption` TEXT,
            `type` VARCHAR(50)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `settings` (
            `botid` INT PRIMARY KEY,
            `bot_mode` VARCHAR(10) DEFAULT 'on',
            `chupl` VARCHAR(255) DEFAULT 'none',
            `is_all` VARCHAR(255) DEFAULT 'no',
            `auto_delete_minutes` VARCHAR(255) DEFAULT '1',
            `dlfree` INT DEFAULT 0,
            `topdlbut` VARCHAR(10) DEFAULT 'on',
            `newdlbut` VARCHAR(10) DEFAULT 'on',
            `supportbut` VARCHAR(10) DEFAULT 'on',
            `likedlbut` VARCHAR(10) DEFAULT 'on',
            `sendbut` VARCHAR(10) DEFAULT 'on',
            `subbuy` VARCHAR(10) DEFAULT 'on',
            `accountbut` VARCHAR(10) DEFAULT 'on',
            `starttext` TEXT,
            `bottype` VARCHAR(20) DEFAULT 'free',
            `showlikes` VARCHAR(10) DEFAULT 'on',
            `showdownload` VARCHAR(10) DEFAULT 'on',
            `autoacc` VARCHAR(10) DEFAULT 'on',
            `startdefault` VARCHAR(10) DEFAULT 'on',
            `tumbnailvaz` VARCHAR(10) DEFAULT 'off',
            `captionlinkvaz` VARCHAR(10) DEFAULT 'off',
            `signdownload` TEXT,
            `joinchanneltext` TEXT,
            `fastupload` VARCHAR(255) DEFAULT '/up',
            `alljoin` INT DEFAULT 0,
            `placeads` VARCHAR(20) DEFAULT 'after',
            `vaziat` VARCHAR(10) DEFAULT 'on',
            `mtn_s_ch` TEXT,
            `forall` VARCHAR(10) DEFAULT 'false',
            `sendall` VARCHAR(10) DEFAULT 'false',
            `tedad` INT DEFAULT 0,
            `chat_id` BIGINT,
            `msg_id` BIGINT,
            `text` TEXT,
            `sendedit` BIGINT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `peyment` (
            `botid` INT PRIMARY KEY,
            `merichentzarin` VARCHAR(255) DEFAULT 'none',
            `merichentziball` VARCHAR(255) DEFAULT 'none',
            `sub1` VARCHAR(255) DEFAULT 'اشتراک 1^on^30^10000',
            `sub2` VARCHAR(255) DEFAULT 'اشتراک 2^on^60^20000',
            `sub3` VARCHAR(255) DEFAULT 'اشتراک 3^on^90^30000',
            `sub4` VARCHAR(255) DEFAULT 'اشتراک 4^off^0^0',
            `sub5` VARCHAR(255) DEFAULT 'اشتراک 5^off^0^0',
            `sub6` VARCHAR(255) DEFAULT 'اشتراک 6^off^0^0',
            `matnpay` TEXT,
            `waypay` VARCHAR(20) DEFAULT 'zarin'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `channels` (
            `idoruser` VARCHAR(255) PRIMARY KEY,
            `link` VARCHAR(255),
            `type` VARCHAR(50)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `admins` (
            `idadmin` BIGINT PRIMARY KEY,
            `nameadmin` VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `ads` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `type` VARCHAR(50),
            `file_id` TEXT,
            `caption` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `dbremove` (
            `id` BIGINT,
            `message_id` BIGINT,
            `time` BIGINT,
            PRIMARY KEY (`id`, `message_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `folders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) UNIQUE,
            `files` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `reaction` (
            `botid` INT PRIMARY KEY,
            `checkreact` VARCHAR(10) DEFAULT 'off',
            `channelreact` VARCHAR(255) DEFAULT 'none',
            `reacttedad` INT DEFAULT 5,
            `timefakereact` INT DEFAULT 5
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `seen` (
            `botid` INT PRIMARY KEY,
            `checkseen` VARCHAR(10) DEFAULT 'off',
            `channelseen` VARCHAR(255) DEFAULT 'none',
            `adadseen` INT DEFAULT 5,
            `timefake` INT DEFAULT 5
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    // اجرای دستورات
    foreach ($sql_commands as $command) {
        $pdo->exec($command);
        preg_match("/TABLE IF NOT EXISTS `(.*?)`/", $command, $matches);
        $tableName = $matches[1] ?? 'UNKNOWN';
        echo "Table `{$tableName}` created or already exists.\n";
    }

    // --- افزودن رکوردهای پیش‌فرض ---
    echo "\nInserting default records...\n";

    // افزودن رکورد پیش‌فرض برای جدول settings
    $stmt = $pdo->prepare("INSERT IGNORE INTO `settings` (`botid`, `starttext`, `joinchanneltext`, `signdownload`) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $botid,
        '⭐️ Welcome<b> « {firstname} »</b>⭐️',
        'برای استفاده از ربات باید در کانال‌های زیر عضو شوید.',
        'bot username'
    ]);
    echo "Default record for `settings` checked/inserted.\n";

    // افزودن رکورد پیش‌فرض برای جدول peyment
    $stmt = $pdo->prepare("INSERT IGNORE INTO `peyment` (`botid`, `matnpay`) VALUES (?, ?)");
    $stmt->execute([
        $botid,
        'برای دسترسی به فایل‌ها باید اشتراک تهیه کنید.'
    ]);
    echo "Default record for `peyment` checked/inserted.\n";

    // افزودن رکورد پیش‌فرض برای جدول reaction
    $stmt = $pdo->prepare("INSERT IGNORE INTO `reaction` (`botid`) VALUES (?)");
    $stmt->execute([$botid]);
    echo "Default record for `reaction` checked/inserted.\n";

    // افزودن رکورد پیش‌فرض برای جدول seen
    $stmt = $pdo->prepare("INSERT IGNORE INTO `seen` (`botid`) VALUES (?)");
    $stmt->execute([$botid]);
    echo "Default record for `seen` checked/inserted.\n";

    echo "\n\n--- Installation Complete! ---\n";
    echo "You can now delete this file ('install.php') from your host.\n";

} catch (PDOException $e) {
    echo "------------------\n";
    echo "E R R O R !\n";
    echo "------------------\n";
    echo "Could not connect to the database or execute commands.\n";
    echo "Please check your credentials in `config.php` and ensure the database user has permission to CREATE tables.\n\n";
    die("Error details: " . $e->getMessage());
}

echo "</pre>";

?>
