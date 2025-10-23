<?php
// src/handlers/ads_handler.php
// این فایل مسئولیت مدیریت کامل سیستم تبلیغات را بر عهده دارد.

/**
 * منوی اصلی مدیریت تبلیغات را به ادمین نمایش می‌دهد.
 *
 * @param PDO $pdo آبجکت اتصال به دیتابیس.
 * @param int $chat_id شناسه چت ادمین.
 * @param int|null $message_id شناسه پیامی که باید ویرایش شود (اختیاری).
 */
function show_ads_management_menu($pdo, $chat_id, $message_id = null) {
    $settings = get_settings($pdo);
    $ads_status = $settings['ads_active'] ? '✅ فعال' : '❌ غیرفعال';
    $ads_position = $settings['ads_position'] === 'before' ? '🔺 قبل از محتوا' : '🔻 بعد از محتوا';
    $total_ads = $pdo->query("SELECT COUNT(*) FROM ads")->fetchColumn();

    $text = "🪧 <b>مدیریت سیستم تبلیغات</b>\n\n";
    $text .= "▪️ وضعیت نمایش: <b>{$ads_status}</b>\n";
    $text .= "▪️ محل نمایش: <b>{$ads_position}</b>\n";
    $text .= "▪️ تعداد تبلیغات: <b>{$total_ads}</b>\n\n";
    $text .= "از دکمه‌های زیر برای مدیریت استفاده کنید:";

    $keyboard = [
        [['text' => '➕ افزودن تبلیغ', 'callback_data' => 'ads_add']],
        [['text' => '🚧 لیست تبلیغات', 'callback_data' => 'ads_list_0']],
        [['text' => ($settings['ads_active'] ? '🔴 غیرفعال کردن' : '🟢 فعال کردن'), 'callback_data' => 'ads_toggle_status']],
        [['text' => '🔄 تغییر محل نمایش', 'callback_data' => 'ads_toggle_position']],
        [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'ads_back_main']]
    ];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);

    if ($message_id) {
        edit_message_text($chat_id, $message_id, $text, $reply_markup);
    } else {
        send_message($chat_id, $text, $reply_markup);
    }
}

/**
 * مسیریابی کلیک‌های دکمه‌های شیشه‌ای پنل تبلیغات.
 */
function handle_ads_callback($pdo, $callback_query) {
    $chat_id = $callback_query->message->chat->id;
    $message_id = $callback_query->message->message_id;
    $data = $callback_query->data;
    $parts = explode('_', $data);
    $command = $parts[1] ?? '';

    switch ($command) {
        case 'toggle':
            if ($parts[2] === 'status') {
                $pdo->query("UPDATE settings SET ads_active = NOT ads_active WHERE id = 1");
                answer_callback_query($callback_query->id, "وضعیت نمایش تبلیغات به‌روز شد.");
            } elseif ($parts[2] === 'position') {
                $current_pos = $pdo->query("SELECT ads_position FROM settings WHERE id = 1")->fetchColumn();
                $new_pos = $current_pos === 'before' ? 'after' : 'before';
                $stmt = $pdo->prepare("UPDATE settings SET ads_position = ? WHERE id = 1");
                $stmt->execute([$new_pos]);
                answer_callback_query($callback_query->id, "محل نمایش تبلیغات به‌روز شد.");
            }
            show_ads_management_menu($pdo, $chat_id, $message_id);
            break;

        case 'add':
            set_user_state($chat_id, 'adding_ad');
            telegram_request('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
            send_message($chat_id, "لطفاً محتوای تبلیغ خود را ارسال کنید (متن، عکس، ویدیو و...). برای لغو /cancel را بفرستید.");
            answer_callback_query($callback_query->id);
            break;

        case 'list':
            $page = (int)($parts[2] ?? 0);
            show_ads_list($pdo, $chat_id, $message_id, $page);
            answer_callback_query($callback_query->id);
            break;

        case 'delete':
            $ad_id = (int)($parts[2] ?? 0);
            if ($ad_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ?");
                $stmt->execute([$ad_id]);
                answer_callback_query($callback_query->id, "تبلیغ #{$ad_id} حذف شد.");
                show_ads_list($pdo, $chat_id, $message_id, 0);
            }
            break;

        case 'view':
            $ad_id = (int)($parts[2] ?? 0);
            if ($ad_id > 0) {
                send_ad_preview($pdo, $chat_id, $ad_id);
                answer_callback_query($callback_query->id);
            }
            break;

        case 'back':
            if ($parts[2] === 'menu') { // ads_back_menu
                show_ads_management_menu($pdo, $chat_id, $message_id);
            } elseif ($parts[2] === 'main') { // ads_back_main
                // This should return to the main admin menu.
                // Assuming start_handler.php has a function for it.
                require_once __DIR__ . '/start_handler.php';
                handle_start($pdo, $callback_query); // Reuse start handler to show main menu
                telegram_request('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
            }
            answer_callback_query($callback_query->id);
            break;

        default:
            answer_callback_query($callback_query->id);
            break;
    }
}

/**
 * پردازش و ذخیره تبلیغ جدید.
 */
function handle_add_ad($pdo, $update) {
    $message = $update->message;
    $chat_id = $message->chat->id;

    if (isset($message->text) && ($message->text === '/cancel' || $message->text === 'انصراف')) {
        clear_user_state($chat_id);
        send_message($chat_id, "عملیات لغو شد.");
        show_ads_management_menu($pdo, $chat_id);
        return;
    }

    $ad_type = null; $file_id = null; $caption = null;

    if (isset($message->text)) {
        $ad_type = 'text'; $caption = $message->text;
    } elseif (isset($message->photo)) {
        $ad_type = 'photo'; $file_id = $message->photo[count($message->photo) - 1]->file_id; $caption = $message->caption;
    } elseif (isset($message->video)) {
        $ad_type = 'video'; $file_id = $message->video->file_id; $caption = $message->caption;
    } // ... other types like document, audio, voice

    if ($ad_type) {
        $stmt = $pdo->prepare("INSERT INTO ads (ad_type, file_id, caption) VALUES (?, ?, ?)");
        $stmt->execute([$ad_type, $file_id, $caption]);
        send_message($chat_id, "✅ تبلیغ با موفقیت ذخیره شد.");
    } else {
        send_message($chat_id, "❌ نوع محتوا پشتیبانی نمی‌شود.");
    }

    clear_user_state($chat_id);
    show_ads_management_menu($pdo, $chat_id);
}

/**
 * نمایش لیست تبلیغات به صورت صفحه‌بندی شده.
 */
function show_ads_list($pdo, $chat_id, $message_id, $page = 0) {
    $ads_per_page = 5;
    $offset = $page * $ads_per_page;
    $total_ads = $pdo->query("SELECT COUNT(*) FROM ads")->fetchColumn();
    $total_pages = ceil($total_ads / $ads_per_page);

    $stmt = $pdo->prepare("SELECT id, ad_type, caption FROM ads ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $ads_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $text = "🚧 <b>لیست تبلیغات (صفحه " . ($page + 1) . " از " . ($total_pages > 0 ? $total_pages : 1) . ")</b>";
    $keyboard = [];

    if (empty($ads)) {
        $text = "❌ هیچ تبلیغی ثبت نشده است.";
    } else {
        foreach ($ads as $ad) {
            $caption_preview = mb_substr($ad['caption'] ?? 'بدون کپشن', 0, 20);
            $keyboard[] = [
                ['text' => "#{$ad['id']} [{$ad['ad_type']}] - {$caption_preview}...", 'callback_data' => 'ads_view_' . $ad['id']],
                ['text' => '🗑', 'callback_data' => 'ads_delete_' . $ad['id']]
            ];
        }
    }

    $navigation = [];
    if ($page > 0) $navigation[] = ['text' => '⬅️ قبلی', 'callback_data' => 'ads_list_' . ($page - 1)];
    if (($page + 1) < $total_pages) $navigation[] = ['text' => 'بعدی ➡️', 'callback_data' => 'ads_list_' . ($page + 1)];
    if (!empty($navigation)) $keyboard[] = $navigation;

    $keyboard[] = [['text' => '🔙 بازگشت به منوی تبلیغات', 'callback_data' => 'ads_back_menu']];

    edit_message_text($chat_id, $message_id, $text, json_encode(['inline_keyboard' => $keyboard]));
}

/**
 * پیش‌نمایش یک تبلیغ را ارسال می‌کند.
 */
function send_ad_preview($pdo, $chat_id, $ad_id) {
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ?");
    $stmt->execute([$ad_id]);
    $ad = $stmt->fetch();

    if (!$ad) {
        send_message($chat_id, "❌ تبلیغ یافت نشد.");
        return;
    }

    $params = ['chat_id' => $chat_id];
    if ($ad['ad_type'] === 'text') {
        $params['text'] = $ad['caption'];
        telegram_request('sendMessage', $params);
    } else {
        $method = 'send' . ucfirst($ad['ad_type']);
        $params[$ad['ad_type']] = $ad['file_id'];
        $params['caption'] = $ad['caption'];
        telegram_request($method, $params);
    }
}
