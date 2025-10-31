<?php

// ============== P H P - B O T ==============
// ----------- U T I L I T Y - F I L E -----------
// ---- C R Y P T O 1 F I L M . O N L I N E ----

// ==========================================================
// ------------------ CORE & WRAPPER FUNCTIONS ----------------------
// ==========================================================

/**
 * Sends a request to the Telegram Bot API.
 * @param string $method The API method to call.
 * @param array $data The data to send with the request.
 * @return mixed The decoded JSON response from the API, or null on failure.
 */
function apiRequest($method, $data = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        error_log("cURL Error for method $method: " . curl_error($ch));
        return null;
    }
    return json_decode($res);
}

/**
 * Checks if a user has admin privileges.
 * @param int $chat_id The user's ID.
 * @param PDO $pdo The database connection object.
 * @return bool True if the user is an admin, false otherwise.
 */
function hasAccess($chat_id, $pdo) {
    global $admins; // From config.php
    if (in_array($chat_id, $admins)) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT idadmin FROM admins WHERE idadmin = ?");
    $stmt->execute([$chat_id]);
    return $stmt->fetch() !== false;
}

// ==========================================================
// -------------- ROUTING FUNCTIONS ----------------------
// ==========================================================

/**
 * Routes text-based commands that depend on the user's current 'step'.
 */
function route_user_step($pdo, $update, $user, $text, $chat_id, $from_id, $is_admin) {
    $step = $user['step'];

    // --- Admin Steps ---
    if ($is_admin) {
        if ($step == 'addadmintoch') {
            handle_add_admin($pdo, $text, $chat_id, $from_id);
        }
        elseif ($step == 'searchuser') {
            handle_user_search($pdo, $text, $chat_id, $from_id);
        }
        elseif (strpos($step, 'newpass_') === 0) {
            handle_set_password_step3($pdo, $text, $chat_id, $from_id, $user);
        }
        // ... add other admin steps here ...
    }

    // --- User Steps ---
    // Example for a user-specific step
    // if ($step == 'entering_email') {
    //     handle_user_email_entry($pdo, $text, $from_id);
    // }
}

/**
 * Routes callback queries from inline keyboards.
 */
function route_callback_query($pdo, $data, $chat_id, $message_id, $from_id, $is_admin) {
    if (strpos($data, "deladmin_") === 0 && $is_admin) {
        handle_delete_admin($pdo, $data, $chat_id, $message_id, $from_id);
    }
    elseif ((strpos($data, "blockusersearch_") === 0 || strpos($data, "un2usersearch_") === 0) && $is_admin) {
        handle_block_unblock_user($pdo, $data, $chat_id, $message_id, $from_id);
    }
    // ... other callback routes ...
}


// ==========================================================
// -------------- COMMAND & ACTION HANDLERS -------------------
// ==========================================================

function handle_start_command($pdo, $from_id, $chat_id, $first_name, $settings) {
    // ... implementation from previous steps ...
}

function handle_admin_panel($pdo, $chat_id, $from_id, $first_name) {
    // ... implementation from previous steps ...
}

// --- UPLOAD PROCESS ---
function handle_upload_command($pdo, $chat_id, $from_id, $settings) {
    // ... implementation from previous steps ...
}
function handle_thumbnail_upload($pdo, $update, $chat_id, $from_id) {
    // ... implementation from previous steps ...
}
function handle_media_upload($pdo, $update, $from_id, $user, $settings) {
    // ... implementation from previous steps ...
}
function handle_finalize_upload($pdo, $chat_id, $from_id, $user) {
    // ... implementation from previous steps ...
}

// --- ADMIN HANDLERS ---
function handle_add_admin($pdo, $text, $chat_id, $from_id) {
    if (!is_numeric($text)) {
        apiRequest("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ آیدی عددی نامعتبر است."]);
        return;
    }
    // ... rest of the add admin logic ...
}

function handle_delete_admin($pdo, $data, $chat_id, $message_id, $from_id) {
    $admin_id_to_delete = str_replace("deladmin_", "", $data);
    $delete_stmt = $pdo->prepare("DELETE FROM admins WHERE idadmin = ?");
    $delete_stmt->execute([$admin_id_to_delete]);
    apiRequest("answerCallbackQuery", ['callback_query_id' => $GLOBALS['update']->callback_query->id, 'text' => "✅ ادمین حذف شد."]);
    // Optionally refresh the admin list message
}

function handle_user_search($pdo, $text, $chat_id, $from_id) {
    // ... implementation of user search logic ...
}

function handle_block_unblock_user($pdo, $data, $chat_id, $message_id, $from_id) {
    // ... implementation of block/unblock logic ...
}

function handle_set_password_step3($pdo, $text, $chat_id, $from_id, $user) {
    // ... implementation of setting password ...
}


// ==========================================================
// ----------------- UTILITY FUNCTIONS --------------------
// ==========================================================
// ... (All other utility functions: convert, doc, jdate, etc.) ...
function convertToHyperlink($text) { /* ... */ return $text; }
function convert($size) { /* ... */ return "0 MB"; }
function doc($name) { /* ... */ return "فایل"; }
function takhmin($fil) { /* ... */ return 1; }
function getChatstats($chat_id, $token) { /* ... */ return true; }
function is_join($from_id, $channel) { /* ... */ return true; }
function getChannelTitle($channel) { /* ... */ return "کانال"; }
// ... jdate functions ...

?>
