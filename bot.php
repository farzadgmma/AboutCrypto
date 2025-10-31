<?php

// ============== P H P - B O T ==============
// ------------- M A I N - B O T - F I L E -------------
// ---------- R E F A C T O R E D & S E C U R E D ----------

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'jdf.php';
require_once 'utils.php';

// --- Database Connection (PDO) ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    // In production, avoid echoing errors.
    // A generic error message can be sent to the user if the bot fails to function.
    die("Database connection failed.");
}

// --- Update Handling & Variable Extraction ---
$update = json_decode(file_get_contents("php://input"));
$message = $update->message ?? $update->callback_query->message ?? null;
$text = $message->text ?? null;
$chat_id = $message->chat->id ?? null;
$from_id = $update->message->from->id ?? $update->callback_query->from->id ?? null;
$message_id = $message->message_id ?? $update->callback_query->message->message_id ?? null;
$data = $update->callback_query->data ?? null;
$first_name = htmlspecialchars($update->message->from->first_name ?? $update->callback_query->from->first_name ?? 'کاربر');
$tc = $message->chat->type ?? null;

if (!$chat_id || !$from_id) exit();

date_default_timezone_set("Asia/Tehran");

// --- Fetch User, Settings, and check permissions ---
$user_stmt = $pdo->prepare("SELECT * FROM `user` WHERE `id` = ? LIMIT 1");
$user_stmt->execute([$from_id]);
$user = $user_stmt->fetch();

$settings_stmt = $pdo->prepare("SELECT * FROM `settings` WHERE `botid` = ? LIMIT 1");
$settings_stmt->execute([$botid]);
$settings = $settings_stmt->fetch();
if (!$settings) die("Bot settings not configured.");

$is_admin = hasAccess($from_id, $pdo);

// --- Initial State Checks (Bot Offline/User Banned) ---
if (($settings['bot_mode'] ?? 'on') == 'off' && !$is_admin) {
    apiRequest("sendMessage", ["chat_id" => $chat_id, "text" => "⭕️ ربات فعلا خاموش میباشد ."]);
    exit();
}
if ($user && $user["step"] == "ban") {
    apiRequest("sendMessage", ["chat_id" => $chat_id, "text" => "📛 شما از ربات مسدود هستید ."]);
    exit();
}

// ==========================================================
// ------------------ MAIN ROUTER -------------------------
// ==========================================================

$user_step = $user['step'] ?? 'none';

// 1. Handle Callback Queries
if ($data) {
    route_callback_query($pdo, $update, $user, $is_admin);
}
// 2. Handle Text Messages (Commands and Steps)
elseif ($text) {
    // Direct command routing
    switch ($text) {
        case '/start':
        case '🏠 برگشت به منو':
            handle_start_command($pdo, $from_id, $chat_id, $first_name, $settings);
            break;

        case $ramzvorodadmin:
        case '🔧 پنل':
        case '🔙 منوی پنل':
            if ($is_admin) handle_admin_panel($pdo, $chat_id, $from_id, $first_name);
            break;

        case '📤 آپلود تکی/آلبومی رسانه':
        case ($settings['fastupload'] ?? uniqid()): // Use a unique value if not set to avoid accidental trigger
             if ($is_admin) handle_upload_command($pdo, $chat_id, $from_id, $settings);
            break;

        // Add other direct commands here...
        // e.g., case "📊 آمار": if ($is_admin) handle_stats_command(...); break;

        default:
            // If it's not a direct command, it might be part of a multi-step process
            if ($user_step !== 'none') {
                route_user_step($pdo, $update, $user, $is_admin);
            }
            // Or a deep link
            elseif (strpos($text, '/start ') === 0) {
                 route_deep_link($pdo, $update, $user, $is_admin);
            }
            break;
    }
}
// 3. Handle Media/File Messages (for uploads)
elseif ($message && !$text && $is_admin) {
    switch ($user_step) {
        case 'tumupload':
            handle_thumbnail_upload($pdo, $update, $chat_id, $from_id);
            break;
        case 'upload':
            handle_media_upload($pdo, $update, $from_id, $user, $settings);
            break;
        // Add other media steps like 'add_ad_media' if needed
    }
}

// --- Close DB Connection ---
$pdo = null;

?>
