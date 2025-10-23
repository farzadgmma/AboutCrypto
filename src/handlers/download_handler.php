<?php
// src/handlers/download_handler.php
// این فایل مسئولیت مدیریت درخواست‌های دانلود فایل توسط کاربران را بر عهده دارد.

/**
 * این تابع زمانی فراخوانی می‌شود که کاربر روی یک لینک دانلود (deep link) کلیک می‌کند.
 * مثال: https://t.me/YourBot?start=dl_XXXXXX
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param stdClass $update آبجکت کامل آپدیت از تلگرام
 */
function handle_download_request($pdo, $update) {
    $chat_id = $update->message->chat->id;
    $user_id = $update->message->from->id;
    $text = $update->message->text;

    $file_code = substr($text, strlen('/start dl_'));

    // --- مرحله ۱: بررسی عضویت اجباری (Forced Join) ---
    $stmt = $pdo->query("SELECT join_active FROM forced_settings WHERE id = 1");
    if ($stmt->fetchColumn()) { // اگر عضویت اجباری فعال بود
        $unjoined_channels = get_unjoined_channels($pdo, $user_id);

        if (!empty($unjoined_channels)) {
            // اگر کاربر در یک یا چند کانال عضو نبود
            $text = "کاربر گرامی، برای دسترسی به فایل‌ها، لطفاً ابتدا در کانال‌های زیر عضو شوید و سپس روی دکمه «بررسی عضویت» کلیک کنید:";
            $keyboard = [];
            foreach ($unjoined_channels as $channel) {
                // برای هر کانال یک دکمه با لینک آن ایجاد می‌کنیم
                // فرض می‌کنیم لینک کانال در دیتابیس ذخیره شده یا از طریق شناسه ساخته می‌شود
                $link = (strpos($channel['channel_identifier'], '@') === 0)
                    ? 'https://t.me/' . substr($channel['channel_identifier'], 1)
                    : $channel['invite_link']; // نیاز به افزودن ستون invite_link داریم

                $keyboard[] = [['text' => "📢 عضویت در کانال " . $channel['channel_identifier'], 'url' => $link]];
            }
            $keyboard[] = [['text' => '✅ بررسی عضویت', 'callback_data' => 'check_join_' . $file_code]];

            send_message($chat_id, $text, json_encode(['inline_keyboard' => $keyboard]));
            return; // فرآیند دانلود متوقف می‌شود
        }
    }


    // --- مرحله ۲: دریافت اطلاعات فایل ---
    $stmt = $pdo->prepare("SELECT * FROM files WHERE file_code = ?");
    $stmt->execute([$file_code]);
    $file = $stmt->fetch();

    if (!$file) {
        send_message($chat_id, "❌ فایلی با این کد یافت نشد یا حذف شده است.");
        return;
    }

    // --- مرحله ۳: بررسی رمز عبور فایل ---
    if (!empty($file['password'])) {
        // اگر فایل رمز عبور داشته باشد، از کاربر می‌خواهیم آن را وارد کند.
        // وضعیت کاربر را به‌روزرسانی می‌کنیم تا پاسخ بعدی او به عنوان رمز عبور پردازش شود.
        $db = new Database();
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("UPDATE users SET state = ? WHERE user_id = ?");
        $stmt->execute(["awaiting_password_{$file_code}", $chat_id]);

        sendMessage(
            $chat_id,
            "🔐 این فایل با رمز عبور محافظت می‌شود. لطفاً رمز را وارد کنید:",
            null,
            json_encode(['keyboard' => [[['text' => 'انصراف']]], 'resize_keyboard' => true])
        );
        return; // منتظر پاسخ کاربر می‌مانیم
    }

    // --- مرحله ۴: بررسی وضعیت اشتراک کاربر ---
    $settings = get_settings($pdo);
    $user = get_user_by_id($pdo, $chat_id);

    // اگر ربات در حالت اشتراکی باشد و کاربر ادمین نباشد
    if ($settings['bot_type'] === 'subscription' && !in_array($chat_id, ADMINS)) {

        // بررسی اینکه آیا کاربر اشتراک فعال دارد یا خیر
        $has_active_subscription = false;
        if ($user['is_vip'] == 1) {
            // اگر تاریخ انقضا null باشد یعنی اشتراک دائمی است
            if ($user['vip_expire_date'] === null || strtotime($user['vip_expire_date']) > time()) {
                $has_active_subscription = true;
            }
        }

        // اگر کاربر اشتراک فعال ندارد، باید تعداد دانلود رایگان او بررسی شود
        if (!$has_active_subscription) {
            if ($user['download_count'] >= $settings['free_download_limit']) {
                // اگر کاربر از محدودیت دانلود رایگان خود عبور کرده باشد، پیام خرید اشتراک نمایش داده می‌شود.
                require_once __DIR__ . '/payment_handler.php';
                send_subscription_prompt($pdo, $chat_id);
                return; // اجرای این هندلر متوقف می‌شود
            }
        }
    }


    // --- مرحله ۵: بررسی محدودیت دانلود فایل و ارسال نهایی ---

    // بررسی اینکه آیا فایل محدودیت دانلود دارد و آیا به آن رسیده است یا خیر
    if ($file['mahdod_dl'] > 0 && $file['dl_count'] >= $file['mahdod_dl']) {
        sendMessage($chat_id, "❗️متاسفانه ظرفیت دانلود این فایل به پایان رسیده است.");
        return;
    }


    send_file_to_user($chat_id, $file);
}

/**
 * لیستی از کانال‌هایی که کاربر در آن‌ها عضو نیست را باز می‌گرداند.
 * @param PDO $pdo
 * @param int $user_id
 * @return array
 */
function get_unjoined_channels($pdo, $user_id) {
    $stmt = $pdo->query("SELECT * FROM forced_join_channels");
    $all_channels = $stmt->fetchAll();
    $unjoined = [];

    foreach ($all_channels as $channel) {
        $status = get_chat_member_status($channel['channel_identifier'], $user_id);
        // اگر کاربر عضو، ادمین یا سازنده کانال نباشد
        if (!in_array($status, ['creator', 'administrator', 'member'])) {
            $unjoined[] = $channel;
        }
    }
    return $unjoined;
}

/**
 * وضعیت عضویت یک کاربر در یک کانال خاص را با استفاده از API تلگرام بررسی می‌کند.
 * @param string $chat_id شناسه کانال (مانند @channel_name یا -100...)
 * @param int $user_id شناسه کاربر
 * @return string|null وضعیت کاربر (مثلاً 'member', 'left') یا null در صورت خطا
 */
function get_chat_member_status($chat_id, $user_id) {
    $result = telegram_request('getChatMember', [
        'chat_id' => $chat_id,
        'user_id' => $user_id
    ]);

    return $result->ok ? $result->result->status : null;
}


/**
 * این تابع فایل اصلی را بر اساس نوع آن برای کاربر ارسال می‌کند.
 * @param int $chat_id شناسه چت کاربر
 * @param array $file آرایه‌ای از اطلاعات فایل که از دیتابیس خوانده شده است
 */
function send_file_to_user($chat_id, $file) {
    // این تابع مسئولیت ارسال نهایی فایل به کاربر و به‌روزرسانی شمارنده‌ها را دارد.

    $db = new Database();
    $pdo = $db->getConnection();

    try {
        // شروع تراکنش برای اطمینان از انجام کامل عملیات
        $pdo->beginTransaction();

        // ۱. افزایش شمارنده دانلود فایل
        $stmt = $pdo->prepare("UPDATE files SET dl_count = dl_count + 1 WHERE id = ?");
        $stmt->execute([$file['id']]);

        // ۲. افزایش شمارنده دانلود کاربر
        $stmt = $pdo->prepare("UPDATE users SET download_count = download_count + 1 WHERE user_id = ?");
        $stmt->execute([$chat_id]);

        // ۳. ارسال فایل به کاربر
        $file_id = $file['file_id'];
        $caption = $file['caption'] ?? '';
        $file_type = $file['file_type']; // 'photo', 'video', 'document', etc.

        // افزودن امضای دانلود (اگر در تنظیمات وجود داشته باشد)
        $settings = get_settings($pdo);
        if (!empty($settings['download_signature'])) {
            $caption .= "\n\n" . $settings['download_signature'];
        }

        // --- مدیریت ارسال تبلیغات ---
        $ad_to_send = null;
        if ($settings['ads_active']) {
            // دریافت یک تبلیغ تصادفی
            $stmt = $pdo->query("SELECT * FROM ads ORDER BY RAND() LIMIT 1");
            $ad_to_send = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // ارسال تبلیغ قبل از فایل اصلی (در صورت نیاز)
        if ($ad_to_send && $settings['ads_position'] === 'before') {
            send_ad_preview($pdo, $chat_id, $ad_to_send['id']);
        }

        // آماده‌سازی پارامترهای پایه
        $params = [
            'chat_id' => $chat_id,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            // مقدار 'is_protected' از دیتابیس خوانده می‌شود
            'protect_content' => ($file['is_protected'] == 1)
        ];

        // تعیین پارامتر مخصوص نوع فایل, e.g., 'photo' => 'AgAD...'
        $params[$file_type] = $file_id;

        // متد API تلگرام بر اساس نوع فایل ساخته می‌شود, e.g., 'sendPhoto'
        $method = "send" . ucfirst($file_type);

        // ارسال درخواست به تلگرام
        telegram_request($method, $params);

        // ارسال تبلیغ بعد از فایل اصلی (در صورت نیاز)
        if ($ad_to_send && $settings['ads_position'] === 'after') {
            // برای استفاده از تابع ارسال تبلیغ، فایل آن را فراخوانی می‌کنیم
            require_once __DIR__ . '/ads_handler.php';
            send_ad_preview($pdo, $chat_id, $ad_to_send['id']);
        }

        // اگر همه چیز موفق بود، تراکنش را تایید نهایی (commit) کن
        $pdo->commit();

    } catch (Exception $e) {
        // اگر در هر مرحله‌ای از try خطایی رخ دهد، همه تغییرات دیتابیس لغو (rollback) می‌شود
        $pdo->rollBack();

        // ثبت خطا در لاگ سرور برای بررسی توسط ادمین
        error_log("Failed to send file and update counts for file_code {$file['file_code']}: " . $e->getMessage());

        // اطلاع به کاربر که مشکلی پیش آمده است
        send_message($chat_id, "❌ مشکلی در ارسال فایل رخ داد. لطفاً دوباره تلاش کنید.");
    }
}

/**
 * این تابع زمانی فراخوانی می‌شود که کاربر روی دکمه "بررسی عضویت" کلیک می‌کند.
 * عضویت کاربر را دوباره بررسی کرده و در صورت موفقیت، فایل را ارسال می‌کند.
 * @param PDO $pdo
 * @param stdClass $callback_query
 */
function handle_recheck_join_request($pdo, $callback_query) {
    $chat_id = $callback_query->message->chat->id;
    $user_id = $callback_query->from->id;
    $message_id = $callback_query->message->message_id;
    $file_code = str_replace('check_join_', '', $callback_query->data);

    // دوباره لیست کانال‌هایی که کاربر عضو نیست را چک می‌کنیم
    $unjoined_channels = get_unjoined_channels($pdo, $user_id);

    if (empty($unjoined_channels)) {
        // اگر در هیچ کانالی باقی نمانده بود که عضو شود
        answer_callback_query($callback_query->id, "✅ عضویت شما تایید شد. در حال ارسال فایل...");

        // حذف پیام "لطفا عضو شوید"
        telegram_request('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);

        // **مهم:** به جای ارسال مستقیم فایل، فرآیند دانلود را از ابتدا فراخوانی می‌کنیم.
        // این کار تضمین می‌کند که سایر بررسی‌ها (رمز عبور، اشتراک و ...) نیز انجام شوند.
        // برای این کار، یک آبجکت update شبیه‌سازی شده می‌سازیم.
        $fake_update = new stdClass();
        $fake_update->message = new stdClass();
        $fake_update->message->chat = new stdClass();
        $fake_update->message->chat->id = $chat_id;
        $fake_update->message->from = new stdClass();
        $fake_update->message->from->id = $user_id;
        $fake_update->message->text = '/start dl_' . $file_code;

        // فراخوانی مجدد کنترل‌کننده اصلی دانلود
        handle_download_request($pdo, $fake_update);

    } else {
        // اگر هنوز در برخی کانال‌ها عضو نشده بود
        answer_callback_query($callback_query->id, "❌ شما هنوز در تمام کانال‌ها عضو نشده‌اید.", true);
    }
}
