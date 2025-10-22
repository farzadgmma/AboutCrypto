<?php
// src/handlers/forced_interaction_handler.php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/telegram_api.php';
require_once __DIR__ . '/../includes/utils.php';

function handle_forced_interaction_settings($chat_id) {
    $keyboard = [
        'keyboard' => [
            [['text' => '🔐 مدیریت عضویت اجباری']],
            [['text' => '👁️‍🗨️ مدیریت بازدید اجباری'], ['text' => '👌🏻 مدیریت واکنش اجباری']],
            [['text' => 'بازگشت به پنل ادمین 🔙']],
        ],
        'resize_keyboard' => true,
    ];
    sendMessage($chat_id, "به بخش تنظیمات تعامل اجباری خوش آمدید.", json_encode($keyboard));
}

function handle_forced_seen_management($chat_id) {
    global $db;
    $settings = get_interaction_settings('seen');

    $status_text = $settings['is_enabled'] ? 'فعال ✅' : 'غیرفعال ❌';
    $channel_text = $settings['channel_id'] ?: 'تنظیم نشده';
    $post_count_text = $settings['post_count'] . ' پست آخر';
    $wait_time_text = $settings['wait_time'] . ' ثانیه';

    $keyboard = [
        'inline_keyboard' => [
            [['text' => 'دستورات 🔸', 'callback_data' => 'none'], ['text' => '🔻 تغییر وضعیت 🔻', 'callback_data' => 'none']],
            [['text' => '👁 وضعیت بازدید اجباری:', 'callback_data' => 'none'], ['text' => $status_text, 'callback_data' => 'toggle_seen']],
            [['text' => '📢 کانال بازدید اجباری:', 'callback_data' => 'none'], ['text' => $channel_text, 'callback_data' => 'set_seen_channel']],
            [['text' => '♾ تعداد بازدید اجباری:', 'callback_data' => 'none'], ['text' => $post_count_text, 'callback_data' => 'set_seen_count']],
            [['text' => '🕰 تایم انتظار:', 'callback_data' => 'none'], ['text' => $wait_time_text, 'callback_data' => 'set_seen_time']]
        ]
    ];

    sendMessage($chat_id, "👁 وضعیت بازدید اجباری کانال:", json_encode($keyboard));
}

function handle_forced_reaction_management($chat_id) {
    // This will be similar to the seen management
    sendMessage($chat_id, "این بخش به زودی پیاده‌سازی می‌شود.");
}

function get_interaction_settings($type) {
    global $db;
    $default_settings = [
        'is_enabled' => false,
        'channel_id' => null,
        'post_count' => 5, // Default value
        'wait_time' => 10 // Default value in seconds
    ];

    $stmt = $db->executeQuery("SELECT * FROM forced_interaction_settings WHERE type = ?", [$type]);
    $db_settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$db_settings) {
        return $default_settings;
    }

    return [
        'is_enabled' => (bool)$db_settings['is_enabled'],
        'channel_id' => $db_settings['channel_id'],
        'post_count' => $db_settings['post_count'] ?? $default_settings['post_count'],
        'wait_time' => $db_settings['wait_time'] ?? $default_settings['wait_time']
    ];
}


function handle_forced_join_management($chat_id) {
    $keyboard = [
        'keyboard' => [
            [['text' => 'افزودن کانال ➕']],
            [['text' => 'لیست کانال‌ها 📋']],
            [['text' => 'بازگشت به تنظیمات تعامل 🔙']],
        ],
        'resize_keyboard' => true,
    ];
    sendMessage($chat_id, "به بخش مدیریت عضویت اجباری خوش آمدید.", json_encode($keyboard));
}

function handle_list_channels($chat_id, $message_id = null) {
    global $db;
    $channels = $db->query("SELECT * FROM forced_join_channels")->fetchAll(PDO::FETCH_ASSOC);

    $text = 'لیست کانال‌های عضویت اجباری:';
    $inline_keyboard = [];

    if (empty($channels)) {
        $text = "هیچ کانالی برای عضویت اجباری تنظیم نشده است.";
    } else {
        foreach ($channels as $channel) {
            $url_id = str_starts_with($channel['channel_id'], '@') ? substr($channel['channel_id'], 1) : 'c/' . substr($channel['channel_id'], 4);
            $inline_keyboard[] = [
                ['text' => $channel['channel_title'], 'url' => 'https://t.me/' . $url_id],
                ['text' => 'حذف ❌', 'callback_data' => 'delete_channel_' . $channel['id']]
            ];
        }
    }

    $keyboard = json_encode(['inline_keyboard' => $inline_keyboard]);

    if ($message_id) {
        editMessageText($chat_id, $message_id, $text, $keyboard);
    } else {
        sendMessage($chat_id, $text, $keyboard);
    }
}

function handle_delete_channel($chat_id, $message_id, $callback_data) {
    global $db;
    $channel_id_to_delete = substr($callback_data, strlen('delete_channel_'));

    $db->executeQuery("DELETE FROM forced_join_channels WHERE id = ?", [$channel_id_to_delete]);

    // Refresh the list
    handle_list_channels($chat_id, $message_id);
}

function handle_add_channel_request($chat_id, $user_id) {
    global $db;
    $db->setUserStep($user_id, 'awaiting_channel_forward');
    sendMessage($chat_id, "لطفا یک پیام از کانال مورد نظر به اینجا فوروارد کنید.\n\n⚠️ ربات باید در کانال ادمین باشد.");
}

function handle_channel_forward_received($chat_id, $user_id, $message) {
    global $db;

    if (!isset($message['forward_from_chat']) || $message['forward_from_chat']['type'] !== 'channel') {
        sendMessage($chat_id, "خطا: لطفا یک پیام از *کانال* فوروارد کنید.");
        return;
    }

    $channel_id = $message['forward_from_chat']['id'];
    $channel_title = $message['forward_from_chat']['title'];

    // Check if channel already exists
    $stmt = $db->executeQuery("SELECT id FROM forced_join_channels WHERE channel_id = ?", [$channel_id]);
    if ($stmt->fetch()) {
        sendMessage($chat_id, "این کانال قبلا اضافه شده است.");
        $db->setUserStep($user_id, 'none');
        return;
    }

    // Check if bot is admin in the channel
    $bot_info = telegram_api('getMe');
    if (!$bot_info || !$bot_info['ok']) {
        sendMessage($chat_id, "خطا در دریافت اطلاعات ربات. لطفا بعدا تلاش کنید.");
        return;
    }
    $bot_id = $bot_info['result']['id'];

    $admins = telegram_api('getChatAdministrators', ['chat_id' => $channel_id]);
    $is_bot_admin = false;
    if ($admins && $admins['ok']) {
        foreach ($admins['result'] as $admin) {
            if ($admin['user']['id'] == $bot_id) {
                $is_bot_admin = true;
                break;
            }
        }
    }

    if (!$is_bot_admin) {
        sendMessage($chat_id, "خطا: ربات در کانال `{$channel_title}` ادمین نیست. لطفا ربات را ادمین کرده و دوباره تلاش کنید.");
        return;
    }

    // Add channel to database
    $db->executeQuery("INSERT INTO forced_join_channels (channel_id, channel_title) VALUES (?, ?)", [$channel_id, $channel_title]);
    $db->setUserStep($user_id, 'none');

    sendMessage($chat_id, "کانال `{$channel_title}` با موفقیت به لیست عضویت اجباری اضافه شد.");
    handle_forced_join_management($chat_id); // Show the menu again
}

function handle_toggle_forced_join($chat_id) {
    global $db;
    // Get current status
    $stmt = $db->executeQuery("SELECT is_enabled FROM forced_interaction_settings WHERE type = 'join'");
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    // If setting doesn't exist, create it and set to true (enabled)
    if (!$setting) {
        $db->executeQuery("INSERT INTO forced_interaction_settings (type, is_enabled) VALUES ('join', 1)");
        $new_status = true;
    } else {
        // Toggle the status
        $new_status = !$setting['is_enabled'];
        $db->executeQuery("UPDATE forced_interaction_settings SET is_enabled = ? WHERE type = 'join'", [(int)$new_status]);
    }

    $status_message = $new_status ? "فعال شد" : "غیرفعال شد";
    sendMessage($chat_id, "وضعیت عضویت اجباری با موفقیت {$status_message}.");

    // Show the management menu again with updated status
    handle_forced_join_management($chat_id);
}

function handle_change_join_text_request($chat_id, $user_id) {
    global $db;
    $db->setUserStep($user_id, 'awaiting_join_text');

    // Get current text to show it to the admin
    $stmt = $db->executeQuery("SELECT message FROM forced_interaction_settings WHERE type = 'join'");
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_text = ($setting && $setting['message']) ? $setting['message'] : "هنوز متنی تنظیم نشده است.";

    sendMessage($chat_id, "متن فعلی:\n`{$current_text}`\n\nلطفا متن جدید برای پیام عضویت اجباری را ارسال کنید:");
}

function handle_join_text_received($chat_id, $user_id, $text) {
    global $db;

    // Ensure the 'join' setting row exists, then update it.
    $db->executeQuery("INSERT INTO forced_interaction_settings (type, message) VALUES ('join', ?) ON DUPLICATE KEY UPDATE message = ?", [$text, $text]);

    $db->setUserStep($user_id, 'none');

    sendMessage($chat_id, "متن عضویت اجباری با موفقیت به‌روزرسانی شد.");
    handle_forced_join_management($chat_id);
}


function check_forced_join($user_id) {
    // TODO: Check if user is a member of all required channels.
    // Returns true if they are, otherwise returns an array of channels they need to join.
    return true;
}

function check_forced_seen($user_id, $file_code) {
    global $db;
    $settings = get_interaction_settings('seen');

    // If the feature is disabled, the user can proceed.
    if (!$settings['is_enabled']) {
        return true;
    }

    // Check the user's cooldown timer.
    $user_stmt = $db->executeQuery("SELECT interaction_cooldown_until FROM users WHERE id = ?", [$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // If the timer is still active, the check fails.
    if ($user_data && strtotime($user_data['interaction_cooldown_until']) > time()) {
        sendMessage($user_id, "❌ هنوز پست های کانال را مشاهده نکرده اید");
        return false;
    }

    // If the timer has expired or does not exist, initiate the check.
    // Set a new cooldown and send instructions to the user.
    $wait_time = $settings['wait_time'];
    $new_cooldown = date('Y-m-d H:i:s', time() + $wait_time);
    $db->executeQuery("UPDATE users SET interaction_cooldown_until = ? WHERE id = ?", [$new_cooldown, $user_id]);

    $channel_link = 'https://t.me/' . $settings['channel_id'];
    $post_count = $settings['post_count'];
    $message_text = "🔻<b>برای مشاهده این محتوا ابتدا به کانال زیر رفته و {$post_count} پست آخر را سین کنید .</b>\r\n\r\n🔹سپس روی دکمه 'مشاهده کردم' بزنید تا پست برای شما نمایش داده شود.";

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🆔 ورود به کانال و مشاهده پست ها', 'url' => $channel_link]],
            [['text' => '👁 مشاهده کردم', 'callback_data' => 'confirm_seen_' . $file_code]]
        ]
    ];

    sendMessage($user_id, $message_text, json_encode($keyboard));

    // The check fails this time, but the process has been initiated for the user to pass it next time.
    return false;
}

function check_forced_reaction($user_id, $file_code) {
    global $db;
    $settings = get_interaction_settings('reaction');

    if (!$settings['is_enabled']) {
        return true;
    }

    $user_stmt = $db->executeQuery("SELECT interaction_cooldown_until FROM users WHERE id = ?", [$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data && strtotime($user_data['interaction_cooldown_until']) > time()) {
        sendMessage($user_id, "❌ هنوز روی پست های کانال ری اکشن نزده اید");
        return false;
    }

    $wait_time = $settings['wait_time'];
    $new_cooldown = date('Y-m-d H:i:s', time() + $wait_time);
    $db->executeQuery("UPDATE users SET interaction_cooldown_until = ? WHERE id = ?", [$new_cooldown, $user_id]);

    $channel_link = 'https://t.me/' . $settings['channel_id'];
    $post_count = $settings['post_count'];
    $message_text = "🔻<b>برای مشاهده این محتوا ابتدا به کانال زیر رفته و برای {$post_count} پست آخر ری اکشن بزنید.</b>\r\n\r\n🔹سپس روی دکمه 'ری اکشن زدم' بزنید تا فایل برای شما نمایش داده شود.";

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🆔 ورود به کانال و مشاهده پست ها', 'url' => $channel_link]],
            [['text' => '👌🏻 ری اکشن زدم', 'callback_data' => 'confirm_reaction_' . $file_code]]
        ]
    ];

    sendMessage($user_id, $message_text, json_encode($keyboard));

    return false;
}

?>