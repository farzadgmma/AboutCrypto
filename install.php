<?php
require_once 'config/config.php';
require_once 'src/includes/database.php';

try {
    $db = new Database();
    $pdo = $db->getDb();
    echo "Database connection successful.\n";

    // Table for users
    $sql_users = "
    CREATE TABLE IF NOT EXISTS users (
        id BIGINT PRIMARY KEY,
        first_name VARCHAR(255) NOT NULL,
        step VARCHAR(255) DEFAULT 'none',
        join_date DATE,
        download_count INT DEFAULT 0,
        is_banned BOOLEAN DEFAULT FALSE,
        is_vip BOOLEAN DEFAULT FALSE,
        vip_expire_date DATE DEFAULT NULL,
        interaction_cooldown_until TIMESTAMP NULL DEFAULT NULL,
        current_folder_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (current_folder_id) REFERENCES folders(id) ON DELETE SET NULL
    );";

    // Table for bot settings
    $sql_settings = "
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        starttext TEXT,
        startdefault ENUM('on', 'off') DEFAULT 'on',
        sendbut ENUM('on', 'off') DEFAULT 'on',
        accountbut ENUM('on', 'off') DEFAULT 'on',
        subbuy ENUM('on', 'off') DEFAULT 'on',
        newdlbut ENUM('on', 'off') DEFAULT 'on',
        topdlbut ENUM('on', 'off') DEFAULT 'on',
        likedlbut ENUM('on', 'off') DEFAULT 'on',
        supportbut ENUM('on', 'off') DEFAULT 'on'
    );";

    // Table for files uploaded by admins
    $sql_files = "
    CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(10) UNIQUE NOT NULL,
        file_id VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        caption TEXT,
        password VARCHAR(255) NULL,
        download_limit INT NULL,
        download_count INT DEFAULT 0,
        likes INT DEFAULT 0,
        forward_lock BOOLEAN DEFAULT TRUE,
        is_filtered BOOLEAN DEFAULT FALSE,
        channel_lock BOOLEAN DEFAULT TRUE,
        uploader_id BIGINT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";

    // Table for files uploaded by users (pending approval)
    $sql_user_files = "
    CREATE TABLE IF NOT EXISTS user_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT,
        file_id VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        caption TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );";

    // Table for folders
    $sql_folders = "
    CREATE TABLE IF NOT EXISTS folders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        parent_id INT NULL, -- For nested folders
        FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
    );";

    // Junction table for files and folders
    $sql_folder_files = "
    CREATE TABLE IF NOT EXISTS folder_files (
        folder_id INT,
        file_id INT,
        PRIMARY KEY (folder_id, file_id),
        FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
        FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
    );";

    // Table for additional admins
    $sql_admins = "
    CREATE TABLE IF NOT EXISTS admins (
        user_id BIGINT PRIMARY KEY,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );";

    // Table for pending file edits
    $sql_pending_edits = "
    CREATE TABLE IF NOT EXISTS pending_edits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id BIGINT NOT NULL,
        batch_code VARCHAR(10) NOT NULL,
        original_file_id INT NOT NULL,
        edit_type ENUM('caption', 'replace', 'delete') NOT NULL,
        new_value TEXT,
        new_file_type VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (original_file_id) REFERENCES files(id) ON DELETE CASCADE
    );";


    $pdo->exec($sql_users);
    echo "Table 'users' created or already exists.\n";
    $pdo->exec($sql_settings);
    echo "Table 'settings' created or already exists.\n";
    $pdo->exec($sql_files);
    echo "Table 'files' created or already exists.\n";
    $pdo->exec($sql_user_files);
    echo "Table 'user_files' created or already exists.\n";
    $pdo->exec($sql_folders);
    echo "Table 'folders' created or already exists.\n";
    $pdo->exec($sql_folder_files);
    echo "Table 'folder_files' created or already exists.\n";
    $pdo->exec($sql_admins);
    echo "Table 'admins' created or already exists.\n";
    $pdo->exec($sql_pending_edits);
    echo "Table 'pending_edits' created or already exists.\n";

    // Table for subscription plans
    $sql_subscriptions = "
    CREATE TABLE IF NOT EXISTS subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price INT NOT NULL,
        duration_days INT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE
    );";
    $pdo->exec($sql_subscriptions);
    echo "Table 'subscriptions' created or already exists.\n";

    // Table for payment settings
    $sql_payment_settings = "
    CREATE TABLE IF NOT EXISTS payment_settings (
        id INT PRIMARY KEY DEFAULT 1,
        active_gateway ENUM('zarinpal', 'ziball') DEFAULT 'zarinpal',
        zarinpal_merchant_id VARCHAR(255) NULL,
        ziball_merchant_id VARCHAR(255) NULL,
        subscription_message TEXT NULL,
        free_downloads_count INT DEFAULT 0
    );";
    $pdo->exec($sql_payment_settings);
    echo "Table 'payment_settings' created or already exists.\n";

    // Insert default settings if not present
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $default_start_text = "⭐️ Welcome<b> « {first_name} »</b>⭐️\r\n\r\n<b>▫️ ID :</b> <code>{user_id}</code>\r\n<b>🗓 Join Date :</b> <u>{join_date}</u>\r\n\r\n<b>📥 Downloads :</b> <code>{download_count}</code>\r\n\r\n🔲 TIME: <b>{time}</b>\r\n🇮🇷 Date: <b>{date}</b>";
        $pdo->prepare("INSERT INTO settings (starttext) VALUES (?)")->execute([$default_start_text]);
        echo "Default settings inserted.\n";
    }

    // Insert default payment settings
    $stmt_payment = $pdo->query("SELECT COUNT(*) FROM payment_settings");
    if ($stmt_payment->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO payment_settings (id, subscription_message) VALUES (1, 'لطفاً یکی از پلن‌های اشتراک زیر را برای خرید انتخاب کنید:')");
        echo "Default payment settings inserted.\n";
    }

    // Tables for Forced Interaction
    $sql_forced_join = "
    CREATE TABLE IF NOT EXISTS forced_join_channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id VARCHAR(255) NOT NULL UNIQUE,
        invite_link VARCHAR(255) NOT NULL,
        type ENUM('public', 'private', 'custom_link') NOT NULL
    );";

    $sql_forced_interaction = "
    CREATE TABLE IF NOT EXISTS forced_interaction_settings (
        name VARCHAR(50) PRIMARY KEY,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        channel_username VARCHAR(255) NULL,
        post_count INT NOT NULL DEFAULT 5,
        fake_timer_seconds INT NOT NULL DEFAULT 10
    );";

    $pdo->exec($sql_forced_join);
    echo "Table 'forced_join_channels' created or already exists.\n";
    $pdo->exec($sql_forced_interaction);
    echo "Table 'forced_interaction_settings' created or already exists.\n";

    // Insert default settings for seen and reaction
    $pdo->exec("INSERT IGNORE INTO forced_interaction_settings (name, is_active, channel_username, post_count, fake_timer_seconds) VALUES
        ('forced_seen', 0, NULL, 5, 15),
        ('forced_reaction', 0, NULL, 5, 15);");
    echo "Default forced interaction settings inserted.\n";


} catch (PDOException $e) {
    die("Database installation failed: " . $e->getMessage());
}

echo "Installation script finished successfully.\n";
