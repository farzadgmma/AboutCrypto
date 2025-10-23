<?php
// src/handlers/user/startHandler.php

require_once __DIR__ . '/../../utils/helpers.php';
require_once __DIR__ . '/../../utils/Database.php';

/**
 * Handles the /start command and main menu display.
 *
 * @param array $user The user data array.
 * @param int $chat_id The chat ID to send the message to.
 */
function handleStartCommand($user, $chat_id) {
    $db = Database::getInstance();

    // Reset the user's state to ensure they are back at the main menu.
    setUserStep($user['id'], 'none');

    // Fetch the latest bot settings to build the dynamic keyboard.
    $settings = $db->fetch("SELECT * FROM settings LIMIT 1");

    // Default start text.
    $start_text = $settings['starttext'] ?? "به ربات آپلودر فایل خوش آمدید.";

    // If the "startdefault" setting is on, use the detailed, personalized message.
    if ($settings['startdefault'] === 'on') {
        $joindate_parts = explode("-", $user['timejoin']);
        list($year, $month, $day) = $joindate_parts;
        $joinus_jalali = gregorian_to_jalali($year, $month, $day, "/");

        $start_text = "⭐️ خوش آمدید <b>« " . htmlspecialchars($user['name']) . " »</b>⭐️\r\n\r\n" .
                      "<b>▫️ آیدی شما:</b> <code>" . $user['id'] . "</code>\r\n" .
                      "<b>🗓 تاریخ عضویت:</b> <u>" . $joinus_jalali . "</u>\r\n\r\n" .
                      "<b>📥 تعداد دانلودها:</b> <code>" . ($user['dl'] ?? 0) . "</code>\r\n" .
                      // Uploads count needs a separate query, can be added later if needed.
                      "<b>📤 تعداد آپلودها:</b> <code>0</code>\r\n\r\n" .
                      "🔲 زمان سرور: <b>" . date("H:i:s") . "</b>\r\n" .
                      "🇮🇷 تاریخ شمسی: <b>" . jdate("Y/m/d") . "</b>\r\n" .
                      "🏳️ تاریخ میلادی: <b>" . date("Y-m-d") . "</b>";
    }

    // Generate the dynamic keyboard based on settings.
    $keyboard = buildMainMenuKeyboard($user, $settings);

    // Send the welcome message with the generated keyboard.
    sendMessage($chat_id, $start_text, $keyboard);
}

/**
 * Builds the main menu keyboard dynamically based on bot settings and user status.
 *
 * @param array $user The user's data.
 * @param array $settings The bot's settings.
 * @return array The keyboard structure for the Telegram API.
 */
function buildMainMenuKeyboard($user, $settings) {
    $db = Database::getInstance();
    $keyboard = [];

    // --- 1. Folder Buttons ---
    $folder_buttons = [];
    $folders = $db->fetchAll("SELECT name FROM folders ORDER BY name ASC");
    $row = [];
    foreach ($folders as $folder) {
        $row[] = ['text' => "📁 " . $folder['name']];
        if (count($row) == 2) {
            $folder_buttons[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) {
        $folder_buttons[] = $row;
    }
    if (!empty($folder_buttons)) {
        $keyboard = array_merge($keyboard, $folder_buttons);
    }

    // --- 2. Main Action Buttons ---
    $main_buttons = [];
    if ($settings['sendbut'] === 'on') {
        $main_buttons[] = [['text' => "📤 آپلود فایل"]];
    }

    $row2 = [];
    if ($settings['accountbut'] === 'on') {
        $row2[] = ['text' => "👤 حساب کاربری"];
    }
    if ($settings['subbuy'] === 'on') {
        $row2[] = ['text' => "💰 خرید اشتراک"];
    }
    if (!empty($row2)) {
        $main_buttons[] = $row2;
    }

    $row3 = [];
    if ($settings['newdlbut'] === 'on') {
        $row3[] = ['text' => "🌀 جدیدترین ها"];
    }
    if ($settings['topdlbut'] === 'on') {
        $row3[] = ['text' => "🔆 پربازدید ها"];
    }
    if ($settings['likedlbut'] === 'on') {
        $row3[] = ['text' => "♥️ محبوب ترین ها"];
    }
    if (!empty($row3)) {
        $main_buttons[] = $row3;
    }

    if ($settings['supportbut'] === 'on') {
        $main_buttons[] = [['text' => "👨🏼‍💻 پشتیبانی"]];
    }

    // Merge main buttons into the final keyboard
    if (!empty($main_buttons)) {
       $keyboard = array_merge($keyboard, $main_buttons);
    }

    // --- 3. Admin Buttons (if applicable) ---
    if (isAdmin($user['id'])) {
        $keyboard[] = [['text' => "🔧 پنل"], ['text' => "🚀 آپلود سریع"]];
    }

    // If all buttons are disabled, remove the keyboard.
    if (empty($keyboard)) {
        return ['remove_keyboard' => true];
    }

    return [
        'keyboard' => $keyboard,
        'resize_keyboard' => true,
    ];
}

// Helper function for Jalali date conversion (assuming jdf.php is loaded)
if (!function_exists('gregorian_to_jalali')) {
    function gregorian_to_jalali($gy, $gm, $gd, $mod = '') {
        // This is a placeholder. The actual jdf.php library should be included.
        // For now, it will just return the Gregorian date.
        return "$gy/$gm/$gd";
    }
}
if (!function_exists('jdate')) {
    function jdate($format, $timestamp = '', $none = '', $time_zone = 'Asia/Tehran', $tr_num = 'fa') {
        // Placeholder
        return date($format);
    }
}
