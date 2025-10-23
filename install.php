<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// It is crucial to include the configuration file that contains database credentials.
// This path assumes 'config.php' is in the 'config' directory at the same level as this 'install.php' file.
require_once 'config/config.php';

try {
    // Establish a database connection using PDO.
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    // Set PDO to throw exceptions on error, which is a robust way to handle SQL errors.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // An array holding all the SQL commands to create the necessary tables.
    // This structure ensures that all tables are created in a single, manageable block.
    $tables = [
        "admins" => "CREATE TABLE IF NOT EXISTS `admins` (
            `idadmin` varchar(20) NOT NULL,
            `nameadmin` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`idadmin`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "ads" => "CREATE TABLE IF NOT EXISTS `ads` (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `type` varchar(10) DEFAULT NULL,
            `file_id` varchar(300) DEFAULT NULL,
            `caption` text DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "channels" => "CREATE TABLE IF NOT EXISTS `channels` (
            `idoruser` varchar(100) NOT NULL,
            `link` varchar(200) NOT NULL,
            `type` varchar(10) DEFAULT NULL,
            PRIMARY KEY (`idoruser`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "dbremove" => "CREATE TABLE IF NOT EXISTS `dbremove` (
            `id` bigint(64) NOT NULL,
            `message_id` int(250) NOT NULL,
            `time` int(250) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "files" => "CREATE TABLE IF NOT EXISTS `files` (
            `code` varchar(10) DEFAULT NULL,
            `msg_id` varchar(10) DEFAULT NULL,
            `ghfl_ch` varchar(5) DEFAULT NULL,
            `zd_filter` varchar(5) DEFAULT NULL,
            `id` varchar(20) DEFAULT NULL,
            `dl` int(20) DEFAULT 0,
            `pass` varchar(50) DEFAULT NULL,
            `mahdodl` varchar(10) DEFAULT NULL,
            `zaman` varchar(20) DEFAULT NULL,
            `likes` int(10) DEFAULT 0,
            `dislikes` int(10) DEFAULT 0,
            `file_id` varchar(500) DEFAULT NULL,
            `file_size` varchar(10) DEFAULT NULL,
            `caption` text DEFAULT NULL,
            `type` varchar(10) DEFAULT NULL,
            `thumbnail` text DEFAULT NULL,
            `file` int(10) NOT NULL AUTO_INCREMENT,
            `fwlock` varchar(5) DEFAULT NULL,
            PRIMARY KEY (`file`),
            INDEX `code_index` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "folders" => "CREATE TABLE IF NOT EXISTS `folders` (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `name` text DEFAULT NULL,
            `files` text DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "peyment" => "CREATE TABLE IF NOT EXISTS `peyment` (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `merichentzarin` varchar(300) DEFAULT NULL,
            `merichentziball` varchar(300) DEFAULT NULL,
            `sub1` text DEFAULT NULL,
            `sub2` text DEFAULT NULL,
            `sub3` text DEFAULT NULL,
            `sub4` text DEFAULT NULL,
            `sub5` text DEFAULT NULL,
            `sub6` text DEFAULT NULL,
            `matnpay` text DEFAULT NULL,
            `waypay` varchar(10) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "reaction" => "CREATE TABLE IF NOT EXISTS `reaction` (
            `checkreact` varchar(100) DEFAULT NULL,
            `channelreact` varchar(250) DEFAULT NULL,
            `reacttedad` int(11) DEFAULT NULL,
            `timefakereact` int(11) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "seen" => "CREATE TABLE IF NOT EXISTS `seen` (
            `checkseen` varchar(10) DEFAULT 'off',
            `channelseen` varchar(20) DEFAULT 'none',
            `adadseen` int(11) DEFAULT 0,
            `timefake` int(11) DEFAULT 10
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "settings" => "CREATE TABLE IF NOT EXISTS `settings` (
            `botid` varchar(30) NOT NULL,
            `bot_mode` varchar(20) NOT NULL,
            `mtn_s_ch` text NOT NULL,
            `chupl` varchar(70) NOT NULL,
            `forall` varchar(20) NOT NULL,
            `sendall` varchar(20) NOT NULL,
            `tedad` varchar(20) NOT NULL,
            `text` text NOT NULL,
            `chat_id` varchar(20) NOT NULL,
            `is_all` varchar(20) NOT NULL,
            `factwebir` varchar(20) NOT NULL,
            `msg_id` varchar(20) NOT NULL,
            `topdlbut` varchar(25) DEFAULT NULL,
            `newdlbut` varchar(25) DEFAULT NULL,
            `supportbut` varchar(25) DEFAULT NULL,
            `sendbut` varchar(25) DEFAULT NULL,
            `starttext` text DEFAULT NULL,
            `subbuy` varchar(5) DEFAULT NULL,
            `accountbut` varchar(5) DEFAULT NULL,
            `bottype` varchar(20) DEFAULT NULL,
            `showlikes` varchar(5) DEFAULT NULL,
            `showdownload` varchar(5) DEFAULT NULL,
            `autoacc` varchar(5) DEFAULT NULL,
            `dlfree` int(10) DEFAULT NULL,
            `likedlbut` varchar(5) DEFAULT NULL,
            `startdefault` varchar(5) DEFAULT NULL,
            `tumbnailvaz` varchar(5) DEFAULT NULL,
            `captionlinkvaz` varchar(5) DEFAULT NULL,
            `signdownload` text DEFAULT NULL,
            `joinchanneltext` text DEFAULT NULL,
            `fastupload` text DEFAULT NULL,
            `sendedit` varchar(20) DEFAULT NULL,
            `alljoin` int(20) DEFAULT 0,
            `vaziat` varchar(10) DEFAULT NULL,
            `placeads` varchar(10) DEFAULT NULL,
            PRIMARY KEY (`botid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "user" => "CREATE TABLE IF NOT EXISTS `user` (
            `id` bigint(64) NOT NULL,
            `step` varchar(500) NOT NULL,
            `step2` varchar(500) NOT NULL,
            `step3` varchar(2500) NOT NULL,
            `step4` varchar(500) NOT NULL,
            `step5` varchar(500) NOT NULL,
            `spam` varchar(20) NOT NULL,
            `timejoin` varchar(50) DEFAULT NULL,
            `vip` varchar(50) DEFAULT 'no',
            `viptime` varchar(50) DEFAULT NULL,
            `dl` varchar(50) DEFAULT '0',
            `expireok` varchar(5) DEFAULT 'no',
            `name` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "userfiles" => "CREATE TABLE IF NOT EXISTS `userfiles` (
            `code` varchar(10) DEFAULT NULL,
            `id` int(20) DEFAULT NULL,
            `file_id` varchar(500) DEFAULT NULL,
            `caption` text DEFAULT NULL,
            `type` varchar(10) DEFAULT NULL,
            INDEX `code_index` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    // Loop through the array and execute each CREATE TABLE statement.
    foreach ($tables as $tableName => $query) {
        $pdo->exec($query);
    }

    // Begin a transaction to ensure all initial data is inserted successfully or none at all.
    $pdo->beginTransaction();

    // Check if the settings table is empty before inserting default data.
    // This prevents duplicate entries if the script is run more than once.
    $stmt = $pdo->query("SELECT COUNT(*) FROM `settings` WHERE `botid` = '" . BOT_TOKEN . "'");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `settings` (`botid`, `bot_mode`, `mtn_s_ch`, `chupl`, `forall`, `sendall`, `tedad`, `text`, `chat_id`, `is_all`, `factwebir`, `msg_id`, `topdlbut`, `newdlbut`, `supportbut`, `sendbut`, `starttext`, `subbuy`, `accountbut`, `bottype`, `showlikes`, `showdownload`, `autoacc`, `dlfree`, `likedlbut`, `startdefault`, `tumbnailvaz`, `captionlinkvaz`, `signdownload`, `joinchanneltext`, `fastupload`, `sendedit`, `alljoin`, `vaziat`, `placeads`) VALUES
        ('" . BOT_TOKEN . "', 'on', 'none', 'none', 'false', 'false', '0', 'none', 'no', 'no', '1', 'no', 'on', 'on', 'on', 'off', 'start', 'off', 'off', 'free', 'on', 'on', 'off', 5, 'on', 'on', 'on', 'on', '@uploader', 'join us', '/up', 'none', 0, 'off', 'before');");
    }

    // Check and insert default data for the 'peyment' table.
    $stmt = $pdo->query("SELECT COUNT(*) FROM `peyment`");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `peyment` (`merichentzarin`, `merichentziball`, `sub1`, `sub2`, `sub3`, `sub4`, `sub5`, `sub6`, `matnpay`, `waypay`, `id`) VALUES
        ('none', 'none', 'e1^on^10^100000', 'e2^on^20^200000', 'e3^on^30^300000', 'e4^on^40^50000^400000', 'e5^on^50^50000^500000', 'e6^off^60^600000', 'buy', 'zarin', 1);");
    }

    // Check and insert default data for the 'reaction' table.
    $stmt = $pdo->query("SELECT COUNT(*) FROM `reaction`");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `reaction` (`checkreact`, `channelreact`, `reacttedad`, `timefakereact`) VALUES ('off', 'none', 1, 5);");
    }

    // Check and insert default data for the 'seen' table.
    $stmt = $pdo->query("SELECT COUNT(*) FROM `seen`");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `seen` (`checkseen`, `channelseen`, `adadseen`, `timefake`) VALUES ('off', 'none', 10, 5);");
    }

    // Commit the transaction, making all the data insertions permanent.
    $pdo->commit();

} catch (PDOException $e) {
    // If any error occurs during the process, roll back the transaction.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Halt the script and display a detailed error message.
    die("Database setup failed: " . $e->getMessage());
}

// Once all operations are successful, display a confirmation message to the user.
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب موفق</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f7f6;
        }
        .success-container {
            text-align: center;
            background-color: #ffffff;
            padding: 2rem 3rem;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .success-message {
            font-size: 1.75rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
        }
        .success-details {
            font-size: 1rem;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">&#10004;</div>
        <div class="success-message">عملیات با موفقیت انجام شد</div>
        <div class="success-details">جداول پایگاه داده و داده‌های اولیه با موفقیت ایجاد و درج شدند.</div>
    </div>
</body>
</html>
