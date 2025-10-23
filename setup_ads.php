<?php
// setup_ads.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h3>Advertisement System Database Setup</h3>";

// Corrected path to config.php
require_once 'config/config.php';
require_once 'src/includes/Database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if ($pdo === null) {
        die("<p>❌ <b>Error:</b> Database connection failed. Please re-check credentials in config.php</p>");
    }
    echo "<p>✅ Database connection successful.</p>";

    // Create 'ads' table
    $sql_ads = "
    CREATE TABLE IF NOT EXISTS ads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('text', 'photo', 'video', 'document', 'audio', 'voice') NOT NULL,
        file_id VARCHAR(255) NULL,
        caption TEXT NULL
    );";
    $pdo->exec($sql_ads);
    echo "<p>✅ Table 'ads' created or already exists.</p>";

    // Add 'ads_active' column to 'settings'
    try {
        $pdo->exec("ALTER TABLE settings ADD COLUMN ads_active BOOLEAN NOT NULL DEFAULT FALSE;");
        echo "<p>✅ Column 'ads_active' added to 'settings' table.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>🔵 Column 'ads_active' already exists in 'settings' table.</p>";
        } else { throw $e; }
    }

    // Add 'ads_position' column to 'settings'
    try {
        $pdo->exec("ALTER TABLE settings ADD COLUMN ads_position ENUM('before', 'after') NOT NULL DEFAULT 'after';");
        echo "<p>✅ Column 'ads_position' added to 'settings' table.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>🔵 Column 'ads_position' already exists in 'settings' table.</p>";
        } else { throw $e; }
    }

    echo "<h4>🎉 Database setup for advertisements completed successfully!</h4>";
    echo "<p>You can now close this window. Please inform the agent that the setup was successful.</p>";

} catch (PDOException $e) {
    die("<p>❌ <b>Database Error:</b> ". $e->getMessage() . "</p>");
}
?>