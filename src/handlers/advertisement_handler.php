<?php
// src/handlers/advertisement_handler.php

/**
 * Displays the main advertisement settings menu.
 */
function handle_advertisement_settings($chat_id) {
    $db = new Database();

    // Fetch current settings to display status
    $ads_enabled_stmt = $db->executeQuery("SELECT value FROM settings WHERE name = 'ads_enabled' LIMIT 1");
    $ads_enabled = ($ads_enabled_stmt->fetch(PDO::FETCH_ASSOC)['value'] ?? 'off') === 'on';

    $ads_position_stmt = $db->executeQuery("SELECT value FROM settings WHERE name = 'ads_position' LIMIT 1");
    $ads_position = $ads_position_stmt->fetch(PDO::FETCH_ASSOC)['value'] ?? 'after';

    $ads_count_stmt = $db->executeQuery("SELECT COUNT(*) as count FROM ads");
    $ads_count = $ads_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Build the status text
    $status_text = "🪧 *وضعیت نمایش تبلیغات*\n\n";
    $status_text .= "▪️ نمایش: *" . ($ads_enabled ? '✅ فعال' : '❌ غیرفعال') . "*\n";
    $status_text .= "▪️ تعداد تبلیغات فعال: *" . $ads_count . " تبلیغ*\n";
    $status_text .= "🚸 محل نمایش تبلیغ: *" . ($ads_position === 'before' ? '🔺 قبل از نمایش رسانه' : '🔻 بعد از نمایش رسانه') . "*";

    // Build the keyboard
    $keyboard = [
        'keyboard' => [
            [['text' => 'فعال/غیرفعال سازی تبلیغات 🔕'], ['text' => 'محل نمایش تبلیغات 🪧']],
            [['text' => 'افزودن تبلیغ ➕'], ['text' => 'لیست تبلیغات 🚧']],
            [['text' => 'بازگشت به پنل ادمین 🔙']],
        ],
        'resize_keyboard' => true,
    ];
    $encoded_keyboard = json_encode($keyboard);

    sendMessage($chat_id, $status_text, $encoded_keyboard, "Markdown");
}

/**
 * Displays a specific ad to the admin.
 */
function handle_view_ad($chat_id, $ad_id) {
    $db = new Database();
    $stmt = $db->executeQuery("SELECT * FROM ads WHERE id = ?", [$ad_id]);

    if ($stmt->rowCount() > 0) {
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);
        sendFile($chat_id, $ad['file_id'], $ad['ad_type'], $ad['caption']);
    } else {
        sendMessage($chat_id, "خطا: تبلیغ یافت نشد.");
    }
}

/**
 * Deletes a specific ad.
 */
function handle_delete_ad($chat_id, $ad_id, $message_id) {
    $db = new Database();
    $db->executeQuery("DELETE FROM ads WHERE id = ?", [$ad_id]);

    // Refresh the list
    editMessageText($chat_id, $message_id, "تبلیغ حذف شد. در حال بارگذاری مجدد لیست...");
    handle_list_ads_request($chat_id);
}

/**
 * Toggles the advertisement system on or off.
 */
function handle_toggle_ads($chat_id) {
    $db = new Database();
    $ads_enabled_stmt = $db->executeQuery("SELECT value FROM settings WHERE name = 'ads_enabled' LIMIT 1");
    $current_status = $ads_enabled_stmt->fetch(PDO::FETCH_ASSOC)['value'] ?? 'off';

    $new_status = ($current_status === 'on') ? 'off' : 'on';

    $db->executeQuery("INSERT INTO settings (name, value) VALUES ('ads_enabled', ?) ON DUPLICATE KEY UPDATE value = ?", [$new_status, $new_status]);

    $message = ($new_status === 'on') ? "✅ مشاهده تبلیغات برای کاربران فعال شد" : "❎ مشاهده تبلیغات برای کاربران غیرفعال شد";
    sendMessage($chat_id, $message);

    // Refresh the menu to show the new status
    handle_advertisement_settings($chat_id);
}

/**
 * Asks the admin to choose the ad display position.
 */
function handle_ad_position_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_ad_position' WHERE id = ?", [$user_id]);

    $keyboard = [
        'keyboard' => [
            [['text' => '🔺 قبل از ارسال رسانه']],
            [['text' => '🔻 بعد از ارسال رسانه']],
            [['text' => 'بازگشت به پنل ادمین 🔙']],
        ],
        'resize_keyboard' => true,
    ];
    $encoded_keyboard = json_encode($keyboard);
    sendMessage($chat_id, "محل نمایش تبلیغ را انتخاب کنید:", $encoded_keyboard);
}

/**
 * Handles the admin's choice for ad position.
 */
function handle_ad_position_received($chat_id, $user_id, $text) {
    $db = new Database();
    $new_position = null;

    if ($text === '🔺 قبل از ارسال رسانه') {
        $new_position = 'before';
    } elseif ($text === '🔻 بعد از ارسال رسانه') {
        $new_position = 'after';
    }

    if ($new_position) {
        $db->executeQuery("INSERT INTO settings (name, value) VALUES ('ads_position', ?) ON DUPLICATE KEY UPDATE value = ?", [$new_position, $new_position]);
        sendMessage($chat_id, "✅ محل نمایش تبلیغ با موفقیت تنظیم شد.");
        $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
        handle_advertisement_settings($chat_id);
    } else {
        sendMessage($chat_id, "لطفا یکی از گزینه‌های روی کیبورد را انتخاب کنید.");
    }
}

/**
 * Asks the admin to send the content for the new ad.
 */
function handle_add_ad_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_ad_content' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "✔️ محتوای تبلیغ خود را شامل *متن یا عکس ، ویدیو ، سند ، وویس ، صوت (با کپشن یا بدون کپشن)* ارسال کنید:", null, "Markdown");
}

/**
 * Handles the received ad content and saves it to the database.
 */
function handle_ad_content_received($chat_id, $user_id, $message) {
    $db = new Database();
    $ad_type = null;
    $file_id = null;
    $caption = null;

    if (isset($message['text'])) {
        $ad_type = 'text';
        $caption = $message['text'];
    } elseif (isset($message['photo'])) {
        $ad_type = 'photo';
        $file_id = $message['photo'][count($message['photo']) - 1]['file_id'];
        $caption = $message['caption'] ?? null;
    } elseif (isset($message['video'])) {
        $ad_type = 'video';
        $file_id = $message['video']['file_id'];
        $caption = $message['caption'] ?? null;
    } elseif (isset($message['document'])) {
        $ad_type = 'document';
        $file_id = $message['document']['file_id'];
        $caption = $message['caption'] ?? null;
    } elseif (isset($message['audio'])) {
        $ad_type = 'audio';
        $file_id = $message['audio']['file_id'];
        $caption = $message['caption'] ?? null;
    } elseif (isset($message['voice'])) {
        $ad_type = 'voice';
        $file_id = $message['voice']['file_id'];
        $caption = $message['caption'] ?? null;
    }

    if ($ad_type) {
        $db->executeQuery("INSERT INTO ads (ad_type, file_id, caption) VALUES (?, ?, ?)", [$ad_type, $file_id, $caption]);
        sendMessage($chat_id, "✅ تبلیغ با موفقیت اضافه شد.");
        $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
        handle_advertisement_settings($chat_id);
    } else {
        sendMessage($chat_id, "❌ نوع رسانه پشتیبانی نمی‌شود. لطفاً دوباره تلاش کنید.");
    }
}

/**
 * Lists all current ads for the admin.
 */
function handle_list_ads_request($chat_id) {
    $db = new Database();
    $stmt = $db->executeQuery("SELECT id, ad_type FROM ads ORDER BY id ASC");

    if ($stmt->rowCount() > 0) {
        $keyboard = [['text' => '🔢', 'callback_data' => 'none'], ['text' => 'نوع', 'callback_data' => 'none'], ['text' => 'مشاهده', 'callback_data' => 'none'], ['text' => 'حذف', 'callback_data' => 'none']]];
        while ($ad = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type_fa = translate_ad_type($ad['ad_type']);
            $keyboard[] = [
                ['text' => (string)$ad['id'], 'callback_data' => 'none'],
                ['text' => $type_fa, 'callback_data' => 'none'],
                ['text' => '👁️', 'callback_data' => 'view_ad_' . $ad['id']],
                ['text' => '❌', 'callback_data' => 'delete_ad_' . $ad['id']],
            ];
        }
        $encoded_keyboard = json_encode(['inline_keyboard' => $keyboard]);
        sendMessage($chat_id, "📢 لیست تبلیغات:", $encoded_keyboard);
    } else {
        sendMessage($chat_id, "❌ هیچ تبلیغی وجود ندارد.");
    }
}

/**
 * Translates ad type from English to Persian.
 */
function translate_ad_type($type) {
    switch ($type) {
        case 'text': return 'متن';
        case 'photo': return 'عکس';
        case 'video': return 'ویدئو';
        case 'document': return 'سند';
        case 'audio': return 'صوت';
        case 'voice': return 'صدا';
        default: return 'ناشناخته';
    }
}
