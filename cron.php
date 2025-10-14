<?php
// cron.php - Scheduled Tasks

// This script should be run by a cron job on your server.
// Example cron job to run every minute:
// * * * * * /usr/bin/php /path/to/your/project/cron.php

require_once 'config/config.php';
require_once 'src/includes/database.php';

function cronSendMessage($chat_id, $text) {
    // ... (function remains the same)
}

// --- Task 1: Process Broadcast Queue (runs every minute) ---
function process_broadcast_queue() {
    echo "Processing broadcast queue...\n";
    $db = new Database();
    $limit = 20; // Send 20 messages per minute

    $stmt = $db->executeQuery(
        "SELECT id, user_id, message_text FROM broadcast_queue WHERE status = 'pending' LIMIT ?",
        [$limit]
    );
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sent_count = 0;

    foreach ($messages as $message) {
        // Send the message
        cronSendMessage($message['user_id'], $message['message_text']);

        // Update the status to 'sent'
        $db->executeQuery("UPDATE broadcast_queue SET status = 'sent' WHERE id = ?", [$message['id']]);

        $sent_count++;
        usleep(100000); // 0.1 second delay
    }

    if ($sent_count > 0) {
        echo "Sent " . $sent_count . " broadcast messages.\n";
    }
}


// --- Task 2: Check for Expired Subscriptions (runs once per day) ---
function check_expired_subscriptions() {
    // To prevent this from running every minute, we check if it's midnight.
    // This is a simple way to simulate a daily task within a single cron job.
    if (date('H:i') !== '00:00') {
        return; // Only run at midnight
    }

    echo "Running subscription expiry check...\n";
    $db = new Database();
    $today = date('Y-m-d');

    $stmt = $db->executeQuery(
        "SELECT id FROM users WHERE vip_status = 'yes' AND vip_expiry_date <= ?",
        [$today]
    );
    $expired_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;

    foreach ($expired_users as $user) {
        $db->executeQuery("UPDATE users SET vip_status = 'no', vip_expiry_date = NULL WHERE id = ?", [$user['id']]);
        cronSendMessage($user['id'], "⚠️ اشتراک ویژه شما به پایان رسیده است.");
        $count++;
        usleep(100000);
    }

    if ($count > 0) {
        echo "Found and processed " . $count . " expired subscriptions.\n";
    }
}


// --- Execute Tasks ---
process_broadcast_queue();
check_expired_subscriptions();

echo "Cron job finished.\n";
