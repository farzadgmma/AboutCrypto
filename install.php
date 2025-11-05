<?php

// ============== P H P - B O T ==============
// -------- I N S T A L L A T I O N - S C R I P T --------
// ---- C R Y P T O 1 F I L M . O N L I N E ----

header('Content-Type: text/html; charset=utf-8');

// --- جلوگیری از اجرای اسکریپت در محیط غیر وب ---
if (php_sapi_name() !== 'cli' && substr(php_sapi_name(), 0, 3) !== 'cgi') {
    echo "<!DOCTYPE html><html><head><title>نصب کننده ربات</title>";
    echo "<style>body { font-family: sans-serif; background-color: #f4f4f9; color: #333; line-height: 1.6; padding: 20px; } .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); } h1 { color: #5a4a9f; } .success { color: #28a745; border-left: 5px solid #28a745; padding: 10px; background-color: #e9f7ef; } .error { color: #dc3545; border-left: 5px solid #dc3545; padding: 10px; background-color: #fceeee; } code { background: #eee; padding: 2px 5px; border-radius: 4px; } .warning { color: #ffc107; border-left: 5px solid #ffc107; padding: 10px; background-color: #fff8e1; font-weight: bold; margin-top: 20px; }</style>";
    echo "</head><body><div class='container'>";
    echo "<h1>🚀 نصب کننده دیتابیس ربات</h1>";
} else {
    echo "🚀 Database Installer Script\n";
}

// --- اتصال به فایل تنظیمات ---
require_once 'config.php';

// --- بررسی وجود اطلاعات دیتابیس در کانفیگ ---
if (DB_NAME === 'YOUR_DATABASE_NAME' || DB_USER === 'YOUR_DATABASE_USERNAME' || !defined('DB_PASS')) {
    echo "<p class='error'>❌ **خطا:** لطفا ابتدا اطلاعات کامل دیتابیس (نام، کاربر و رمز عبور) را در فایل `config.php` وارد کنید.</p>";
    echo "</div></body></html>";
    exit;
}

try {
    // --- ایجاد اتصال PDO ---
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // --- ایجاد دیتابیس در صورت عدم وجود (اختیاری، ممکن است در برخی هاست‌ها دسترسی لازم را نداشته باشد) ---
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `" . DB_NAME . "`;");

    echo "<p>✅ اتصال به سرور دیتابیس موفقیت‌آمیز بود و دیتابیس `" . DB_NAME . "` انتخاب شد.</p>";

    // --- دستورات SQL برای ایجاد جداول ---
    $sql_commands = [
        "CREATE TABLE IF NOT EXISTS `admins` (
          `idadmin` varchar(200) NOT NULL,
          `nameadmin` varchar(200) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `ads` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `type` varchar(200) NOT NULL,
          `file_id` text NOT NULL,
          `caption` text NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `channels` (
          `idoruser` text NOT NULL,
          `link` text NOT NULL,
          `type` varchar(200) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `dbremove` (
          `id` bigint(20) NOT NULL,
          `message_id` bigint(20) NOT NULL,
          `time` bigint(20) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `files` (
          `code` varchar(200) NOT NULL,
          `msg_id` varchar(200) NOT NULL,
          `ghfl_ch` varchar(200) NOT NULL,
          `zd_filter` varchar(200) NOT NULL,
          `id` varchar(200) NOT NULL,
          `dl` varchar(200) NOT NULL,
          `pass` varchar(200) NOT NULL,
          `mahdodl` varchar(200) NOT NULL,
          `zaman` varchar(200) NOT NULL,
          `likes` varchar(200) NOT NULL,
          `dislikes` varchar(200) NOT NULL,
          `file_id` text NOT NULL,
          `file_size` varchar(200) NOT NULL,
          `caption` text NOT NULL,
          `type` varchar(200) NOT NULL,
          `thumbnail` text NOT NULL,
          `fwlock` varchar(200) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `folders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `files` text NOT NULL,
           PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `peyment` (
          `merichentzarin` text NOT NULL,
          `merichentziball` text NOT NULL,
          `sub1` varchar(200) NOT NULL,
          `sub2` varchar(200) NOT NULL,
          `sub3` varchar(200) NOT NULL,
          `sub4` varchar(200) NOT NULL,
          `sub5` varchar(200) NOT NULL,
          `sub6` varchar(200) NOT NULL,
          `matnpay` text NOT NULL,
          `waypay` varchar(200) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        "INSERT INTO `peyment` (`merichentzarin`, `merichentziball`, `sub1`, `sub2`, `sub3`, `sub4`, `sub5`, `sub6`, `matnpay`, `waypay`) VALUES
        ('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'zibal', 'طرح 1 ماهه^on^30^1000', 'طرح 2 ماهه^on^60^2000', 'طرح 3 ماهه^on^90^3000', 'طرح 6 ماهه^off^180^6000', 'طرح 1 ساله^off^365^12000', 'اشتراک ویژه^off^30^5000', 'برای خرید اشتراک اقدام کنید', 'zarin') ON DUPLICATE KEY UPDATE `waypay`=`waypay`;",


        "CREATE TABLE IF NOT EXISTS `reaction` (
          `checkreact` varchar(200) NOT NULL,
          `channelreact` varchar(200) NOT NULL,
          `reacttedad` varchar(200) NOT NULL,
          `timefakereact` varchar(200) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        "INSERT INTO `reaction` (`checkreact`, `channelreact`, `reacttedad`, `timefakereact`) VALUES ('off', 'none', '10', '15') ON DUPLICATE KEY UPDATE `checkreact`=`checkreact`;",


        "CREATE TABLE IF NOT EXISTS `seen` (
          `checkseen` varchar(200) NOT NULL,
          `channelseen` varchar(200) NOT NULL,
          `adadseen` varchar(200) NOT NULL,
          `timefake` varchar(200) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        "INSERT INTO `seen` (`checkseen`, `channelseen`, `adadseen`, `timefake`) VALUES ('off', 'none', '10', '15') ON DUPLICATE KEY UPDATE `checkseen`=`checkseen`;",


        "CREATE TABLE IF NOT EXISTS `settings` (
          `bot_mode` varchar(200) NOT NULL,
          `chupl` varchar(200) NOT NULL,
          `is_all` varchar(200) NOT NULL,
          `factwebir` varchar(200) NOT NULL,
          `dlfree` varchar(200) NOT NULL,
          `topdlbut` varchar(200) NOT NULL,
          `newdlbut` varchar(200) NOT NULL,
          `supportbut` varchar(200) NOT NULL,
          `likedlbut` varchar(200) NOT NULL,
          `sendbut` varchar(200) NOT NULL,
          `subbuy` varchar(200) NOT NULL,
          `accountbut` varchar(200) NOT NULL,
          `starttext` text NOT NULL,
          `bottype` varchar(200) NOT NULL,
          `showlikes` varchar(200) NOT NULL,
          `showdownload` varchar(200) NOT NULL,
          `autoacc` varchar(200) NOT NULL,
          `startdefault` varchar(200) NOT NULL,
          `tumbnailvaz` varchar(200) NOT NULL,
          `captionlinkvaz` varchar(200) NOT NULL,
          `signdownload` text NOT NULL,
          `joinchanneltext` text NOT NULL,
          `fastupload` varchar(200) NOT NULL,
          `alljoin` varchar(200) NOT NULL,
          `placeads` varchar(200) NOT NULL,
          `vaziat` varchar(200) NOT NULL,
          `botid` varchar(200) NOT NULL,
          `sendedit` varchar(200) NOT NULL,
          `text` text NOT NULL,
          `chat_id` varchar(200) NOT NULL,
          `msg_id` varchar(200) NOT NULL,
          `sendall` varchar(200) NOT NULL,
          `forall` varchar(200) NOT NULL,
          `tedad` varchar(200) NOT NULL,
          `mtn_s_ch` text NOT NULL,
          UNIQUE KEY `botid` (`botid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        "INSERT INTO `settings` (`bot_mode`, `chupl`, `is_all`, `factwebir`, `dlfree`, `topdlbut`, `newdlbut`, `supportbut`, `likedlbut`, `sendbut`, `subbuy`, `accountbut`, `starttext`, `bottype`, `showlikes`, `showdownload`, `autoacc`, `startdefault`, `tumbnailvaz`, `captionlinkvaz`, `signdownload`, `joinchanneltext`, `fastupload`, `alljoin`, `placeads`, `vaziat`, `botid`, `sendedit`, `text`, `chat_id`, `msg_id`, `sendall`, `forall`, `tedad`, `mtn_s_ch`) VALUES
        ('on', 'none', 'no', '1', '10', 'on', 'on', 'on', 'on', 'on', 'on', 'on', 'سلام خوش آمدید', 'free', 'on', 'on', 'off', 'on', 'off', 'off', '@YourChannel', 'برای استفاده از ربات باید عضو کانال ما شوید', '/upload', '0', 'after', 'off', '1', 'none', 'none', 'none', 'none', 'false', 'false', '0', '@YourChannel') ON DUPLICATE KEY UPDATE `botid`=`botid`;",

        "CREATE TABLE IF NOT EXISTS `user` (
          `id` varchar(200) NOT NULL,
          `step` varchar(200) NOT NULL DEFAULT 'none',
          `step2` varchar(200) DEFAULT NULL,
          `step3` varchar(200) DEFAULT NULL,
          `step4` varchar(200) DEFAULT NULL,
          `step5` varchar(200) DEFAULT NULL,
          `spam` varchar(200) NOT NULL,
          `timejoin` varchar(200) NOT NULL,
          `vip` varchar(200) NOT NULL,
          `viptime` varchar(200) DEFAULT NULL,
          `dl` varchar(200) NOT NULL,
          `expireok` varchar(200) NOT NULL,
          `name` varchar(200) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `userfiles` (
          `code` varchar(200) NOT NULL,
          `id` varchar(200) NOT NULL,
          `file_id` text NOT NULL,
          `caption` text NOT NULL,
          `type` varchar(200) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    // --- اجرای دستورات ---
    foreach ($sql_commands as $command) {
        $pdo->exec($command);
    }

    echo "<p class='success'>🎉 **موفقیت:** تمام جداول با موفقیت در دیتابیس ایجاد و مقداردهی اولیه شدند.</p>";
    echo "<p class='warning'>🔴 **مهم:** لطفا همین الان فایل `install.php` را از روی هاست خود حذف کنید تا از اجرای مجدد و مشکلات امنیتی جلوگیری شود.</p>";

} catch (PDOException $e) {
    echo "<p class='error'>❌ **خطای دیتابیس:** " . htmlspecialchars($e->getMessage()) . "</p>";
}

if (php_sapi_name() !== 'cli' && substr(php_sapi_name(), 0, 3) !== 'cgi') {
    echo "</div></body></html>";
} else {
    echo "🔴 IMPORTANT: Please delete install.php from your server now.\n";
}

?>
