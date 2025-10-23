<?php
// src/handlers/forced_interaction_handler.php
// این فایل مسئولیت مدیریت تمام منطق مربوط به تعاملات اجباری را بر عهده دارد.
// این موارد شامل: عضویت اجباری (Forced Join)، بازدید اجباری (Forced Seen)
// و واکنش اجباری (Forced Reaction) می‌شوند.

require_once __DIR__ . '/../includes/helpers.php';

/**
 * منوی اصلی مدیریت تعاملات اجباری را به ادمین نمایش می‌دهد.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param int $chat_id شناسه چت ادمین
 * @param int|null $message_id شناسه پیام برای ویرایش
 */
function show_forced_interaction_menu($pdo, $chat_id, $message_id = null) {
    // دریافت تنظیمات فعلی از جدول 'forced_settings'
    $stmt = $pdo->query("SELECT * FROM forced_settings WHERE id = 1");
    $settings = $stmt->fetch();

    // تعیین وضعیت فعلی هر قابلیت برای نمایش در دکمه‌ها
    $join_status = $settings['join_active'] ? '✅ فعال' : '❌ غیرفعال';
    $seen_status = $settings['seen_active'] ? '✅ فعال' : '❌ غیرفعال';
    $reaction_status = $settings['reaction_active'] ? '✅ فعال' : '❌ غیرفعال';

    $text = "👁‍🗨 <b>مدیریت بازدید و عضویت اجباری</b>\n\n" .
            "در این بخش می‌توانید تنظیمات مربوط به هر یک از قابلیت‌های زیر را مدیریت کنید.";

    // ساخت کیبورد شیشه‌ای با وضعیت فعلی هر قابلیت
    $keyboard = [
        [['text' => '🔐 مدیریت عضویت اجباری (' . $join_status . ')', 'callback_data' => 'fi_join_menu']],
        [['text' => '👁 مدیریت بازدید اجباری (' . $seen_status . ')', 'callback_data' => 'fi_seen_menu']],
        [['text' => '👌🏻 مدیریت واکنش اجباری (' . $reaction_status . ')', 'callback_data' => 'fi_reaction_menu']],
        [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'back_to_main_menu']] // فرض بر اینکه callback برای بازگشت وجود دارد
    ];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);

    // ارسال یا ویرایش پیام
    if ($message_id) {
        edit_message_text($chat_id, $message_id, $text, $reply_markup);
    } else {
        send_message($chat_id, $text, $reply_markup);
    }
}

/**
 * این تابع به عنوان یک مسیریاب برای تمام callbackهای مربوط به این بخش عمل می‌کند.
 * @param PDO $pdo
 * @param stdClass $callback_query
 */
function handle_forced_interaction_callback($pdo, $callback_query) {
    $chat_id = $callback_query->message->chat->id;
    $message_id = $callback_query->message->message_id;
    $data = $callback_query->data;

    // --- مسیریابی کلی ---
    if ($data === 'fi_back_to_main') {
        // در اینجا باید به منوی اصلی ادمین بازگردیم.
        // فعلاً برای سادگی، همین منو را دوباره نمایش می‌دهیم.
        show_forced_interaction_menu($pdo, $chat_id, $message_id);
    }
    // --- بخش عضویت اجباری (Join) ---
    elseif ($data === 'fi_join_menu' || $data === 'back_to_join_menu') {
        show_forced_join_menu($pdo, $chat_id, $message_id);
    } elseif ($data === 'fi_toggle_join') {
        $pdo->query("UPDATE forced_settings SET join_active = NOT join_active WHERE id = 1");
        answer_callback_query($callback_query->id, 'وضعیت عضویت اجباری تغییر کرد.');
        show_forced_join_menu($pdo, $chat_id, $message_id);
    } elseif ($data === 'fi_add_channel') {
        set_user_state($chat_id, 'adding_forced_join_channel');
        send_message($chat_id, 'لطفا شناسه کانال را ارسال کنید (مثلا @channel_name یا -100123456789).');
        answer_callback_query($callback_query->id);
    } elseif (strpos($data, 'fi_remove_channel_') === 0) {
        $channel_id = str_replace('fi_remove_channel_', '', $data);
        $stmt = $pdo->prepare("DELETE FROM forced_join_channels WHERE id = ?");
        $stmt->execute([$channel_id]);
        answer_callback_query($callback_query->id, 'کانال با موفقیت حذف شد.');
        show_forced_join_menu($pdo, $chat_id, $message_id);
    }
    // --- بخش بازدید اجباری (Seen) ---
    elseif ($data === 'fi_seen_menu' || $data === 'back_to_seen_menu') {
        show_forced_seen_menu($pdo, $chat_id, $message_id);
    } elseif ($data === 'fi_toggle_seen') {
        // با فعال کردن بازدید، واکنش غیرفعال می‌شود تا تداخل ایجاد نکنند.
        $pdo->query("UPDATE forced_settings SET seen_active = NOT seen_active, reaction_active = 0 WHERE id = 1");
        answer_callback_query($callback_query->id, 'وضعیت بازدید اجباری تغییر کرد.');
        show_forced_seen_menu($pdo, $chat_id, $message_id);
    } elseif ($data === 'fi_edit_seen_channel') {
        set_user_state($chat_id, 'editing_seen_channel');
        send_message($chat_id, 'لطفا شناسه کانال جدید برای بازدید اجباری را وارد کنید (مثلا @channel_name).');
        answer_callback_query($callback_query->id);
    } elseif ($data === 'fi_edit_seen_count') {
        set_user_state($chat_id, 'editing_seen_count');
        send_message($chat_id, 'لطفا تعداد پست‌هایی که باید بازدید شود را وارد کنید (عدد بین 1 تا 50).');
        answer_callback_query($callback_query->id);
    }
    // --- بخش واکنش اجباری (Reaction) ---
    elseif ($data === 'fi_reaction_menu' || $data === 'back_to_reaction_menu') {
        show_forced_reaction_menu($pdo, $chat_id, $message_id);
    } elseif ($data === 'fi_toggle_reaction') {
        // با فعال کردن واکنش، بازدید غیرفعال می‌شود.
        $pdo->query("UPDATE forced_settings SET reaction_active = NOT reaction_active, seen_active = 0 WHERE id = 1");
        answer_callback_query($callback_query->id, 'وضعیت واکنش اجباری تغییر کرد.');
        show_forced_reaction_menu($pdo, $chat_id, $message_id);
    } elseif ($data === 'fi_edit_reaction_channel') {
        set_user_state($chat_id, 'editing_reaction_channel');
        send_message($chat_id, 'لطفا شناسه کانال جدید برای واکنش اجباری را وارد کنید (مثلا @channel_name).');
        answer_callback_query($callback_query->id);
    } elseif ($data === 'fi_edit_reaction_count') {
        set_user_state($chat_id, 'editing_reaction_count');
        send_message($chat_id, 'لطفا تعداد پست‌هایی که باید واکنش داده شود را وارد کنید (عدد بین 1 تا 50).');
        answer_callback_query($callback_query->id);
    }
}

/**
 * منوی مدیریت عضویت اجباری را نمایش می‌دهد.
 */
function show_forced_join_menu($pdo, $chat_id, $message_id) {
    $stmt = $pdo->query("SELECT join_active FROM forced_settings WHERE id = 1");
    $status = $stmt->fetchColumn() ? '✅ فعال' : '❌ غیرفعال';

    $text = "🔐 <b>مدیریت عضویت اجباری</b>\n\n" .
            "<b>وضعیت فعلی:</b> {$status}\n\n" .
            "لیست کانال‌هایی که کاربر برای استفاده از ربات باید در آن‌ها عضو باشد:";

    $stmt = $pdo->query("SELECT * FROM forced_join_channels");
    $channels = $stmt->fetchAll();

    $keyboard = [[['text' => '🔄 تغییر وضعیت (' . $status . ')', 'callback_data' => 'fi_toggle_join']]];

    if (empty($channels)) {
        $text .= "\n\nهیچ کانالی تنظیم نشده است.";
    } else {
        foreach ($channels as $channel) {
            $keyboard[] = [['text' => "📢 {$channel['channel_identifier']}", 'callback_data' => 'fi_noop'], ['text' => '❌ حذف', 'callback_data' => 'fi_remove_channel_' . $channel['id']]];
        }
    }

    $keyboard[] = [['text' => '➕ افزودن کانال', 'callback_data' => 'fi_add_channel']];
    $keyboard[] = [['text' => '🔙 بازگشت', 'callback_data' => 'fi_back_to_main']];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);
    edit_message_text($chat_id, $message_id, $text, $reply_markup);
}

/**
 * منوی مدیریت بازدید اجباری را نمایش می‌دهد.
 */
function show_forced_seen_menu($pdo, $chat_id, $message_id) {
    $settings = $pdo->query("SELECT seen_active, seen_channel, seen_post_count FROM forced_settings WHERE id = 1")->fetch();
    $status = $settings['seen_active'] ? '✅ فعال' : '❌ غیرفعال';
    $channel = $settings['seen_channel'] ?: 'تنظیم نشده';
    $count = $settings['seen_post_count'];

    $text = "👁 <b>مدیریت بازدید اجباری (سین)</b>\n\n" .
            "<b>وضعیت:</b> {$status}\n" .
            "<b>کانال:</b> {$channel}\n" .
            "<b>تعداد پست:</b> {$count}\n\n" .
            "در صورت فعال بودن، کاربر باید {$count} پست آخر کانال {$channel} را بازدید کند.";

    $keyboard = [
        [['text' => '🔄 تغییر وضعیت (' . $status . ')', 'callback_data' => 'fi_toggle_seen']],
        [['text' => 'ቻ تغییر کانال', 'callback_data' => 'fi_edit_seen_channel']],
        [['text' => '🔢 تغییر تعداد پست', 'callback_data' => 'fi_edit_seen_count']],
        [['text' => '🔙 بازگشت', 'callback_data' => 'fi_back_to_main']],
    ];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);
    edit_message_text($chat_id, $message_id, $text, $reply_markup);
}

/**
 * منوی مدیریت واکنش اجباری را نمایش می‌دهد.
 */
function show_forced_reaction_menu($pdo, $chat_id, $message_id) {
    $settings = $pdo->query("SELECT reaction_active, reaction_channel, reaction_post_count FROM forced_settings WHERE id = 1")->fetch();
    $status = $settings['reaction_active'] ? '✅ فعال' : '❌ غیرفعال';
    $channel = $settings['reaction_channel'] ?: 'تنظیم نشده';
    $count = $settings['reaction_post_count'];

    $text = "👌🏻 <b>مدیریت واکنش اجباری (ری‌اکشن)</b>\n\n" .
            "<b>وضعیت:</b> {$status}\n" .
            "<b>کانال:</b> {$channel}\n" .
            "<b>تعداد پست:</b> {$count}\n\n" .
            "در صورت فعال بودن، کاربر باید به {$count} پست آخر کانال {$channel} واکنش نشان دهد.";

    $keyboard = [
        [['text' => '🔄 تغییر وضعیت (' . $status . ')', 'callback_data' => 'fi_toggle_reaction']],
        [['text' => 'ቻ تغییر کانال', 'callback_data' => 'fi_edit_reaction_channel']],
        [['text' => '🔢 تغییر تعداد پست', 'callback_data' => 'fi_edit_reaction_count']],
        [['text' => '🔙 بازگشت', 'callback_data' => 'fi_back_to_main']],
    ];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);
    edit_message_text($chat_id, $message_id, $text, $reply_markup);
}

/**
 * پاسخ ادمین برای ویرایش تنظیمات تعاملات اجباری را پردازش می‌کند.
 */
function handle_forced_interaction_edit($pdo, $update) {
    $chat_id = $update->message->chat->id;
    $text = $update->message->text;
    $state = get_user_state($chat_id);

    $field_map = [
        'editing_seen_channel' => ['column' => 'seen_channel', 'type' => 'string', 'menu' => 'show_forced_seen_menu'],
        'editing_seen_count' => ['column' => 'seen_post_count', 'type' => 'integer', 'menu' => 'show_forced_seen_menu'],
        'editing_reaction_channel' => ['column' => 'reaction_channel', 'type' => 'string', 'menu' => 'show_forced_reaction_menu'],
        'editing_reaction_count' => ['column' => 'reaction_post_count', 'type' => 'integer', 'menu' => 'show_forced_reaction_menu'],
    ];

    $column = $field_map[$state]['column'];
    $type = $field_map[$state]['type'];
    $menu_function = $field_map[$state]['menu'];
    $value = trim($text);

    // اعتبارسنجی ورودی
    if ($type === 'integer') {
        if (!is_numeric($value) || $value < 1 || $value > 50) {
            send_message($chat_id, 'مقدار نامعتبر است. لطفا یک عدد بین 1 تا 50 وارد کنید.');
            return;
        }
    } elseif ($type === 'string' && strpos($value, '@') !== 0) {
        send_message($chat_id, 'شناسه کانال نامعتبر است. باید با @ شروع شود.');
        return;
    }

    // به‌روزرسانی دیتابیس
    $stmt = $pdo->prepare("UPDATE forced_settings SET {$column} = ? WHERE id = 1");
    $stmt->execute([$value]);

    send_message($chat_id, '✅ تنظیمات با موفقیت به‌روزرسانی شد.');
    clear_user_state($chat_id);

    // نمایش مجدد منوی مربوطه
    $menu_function($pdo, $chat_id, null);
}

/**
 * پاسخ ادمین برای افزودن کانال عضویت اجباری را پردازش می‌کند.
 */
function handle_add_forced_join_channel($pdo, $update) {
    $chat_id = $update->message->chat->id;
    $channel_identifier = trim($update->message->text);

    if (empty($channel_identifier) || (strpos($channel_identifier, '@') !== 0 && strpos($channel_identifier, '-100') !== 0)) {
        send_message($chat_id, 'شناسه کانال نامعتبر است. باید با @ یا -100 شروع شود.');
        clear_user_state($chat_id);
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO forced_join_channels (channel_identifier) VALUES (?)");
        $stmt->execute([$channel_identifier]);
        send_message($chat_id, '✅ کانال با موفقیت افزوده شد.');
    } catch (PDOException $e) {
        // اگر کانال از قبل وجود داشته باشد (به دلیل UNIQUE بودن ستون)، پیام خطا نمایش داده می‌شود
        if ($e->errorInfo[1] == 1062) {
            send_message($chat_id, '⚠️ این کانال قبلاً اضافه شده است.');
        } else {
            send_message($chat_id, 'خطایی در افزودن کانال رخ داد.');
            error_log("Forced Join Add Error: " . $e->getMessage());
        }
    }

    clear_user_state($chat_id);
    show_forced_join_menu($pdo, $chat_id, null);
}
?>