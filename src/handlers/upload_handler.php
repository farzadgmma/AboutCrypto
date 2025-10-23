<?php
// src/handlers/upload_handler.php
// این فایل مسئولیت مدیریت تمام منطق مربوط به آپلود فایل را بر عهده دارد.
// این شامل شروع آپلود توسط ادمین و دریافت فایل از کاربران می‌شود.

/**
 * این تابع زمانی فراخوانی می‌شود که ادمین روی دکمه "آپلود" کلیک می‌کند.
 * یک پیام راهنما برای ادمین ارسال کرده و وضعیت او را برای دریافت فایل تنظیم می‌کند.
 * @param int $chat_id شناسه چت ادمین
 */
function handle_admin_upload_start($chat_id) {
    // تنظیم وضعیت ادمین به 'admin_uploading'
    // این کار به ربات می‌گوید که پیام‌های بعدی این کاربر (تا زمانی که وضعیت پاک شود) فایل‌هایی برای آپلود هستند.
    set_user_state($chat_id, 'admin_uploading');

    // ارسال پیام راهنما به ادمین
    $text = "لطفاً فایل یا فایل‌های خود را ارسال کنید. می‌توانید چندین فایل را به صورت آلبومی ارسال کنید.\n\n" .
            "پس از ارسال تمام فایل‌ها، یک دکمه برای ذخیره‌سازی نهایی به شما نمایش داده خواهد شد.";

    send_message($chat_id, $text);
}

/**
 * این تابع زمانی فراخوانی می‌شود که یک پیام حاوی فایل دریافت می‌شود.
 * این تابع وضعیت کاربر را بررسی کرده و بر اساس آن تصمیم می‌گیرد که فایل را چگونه پردازش کند.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param stdClass $update آبجکت کامل آپدیت از تلگرام
 */
function handle_file_receive($pdo, $update) {
    $chat_id = $update->message->chat->id;
    $user_id = $update->message->from->id;
    $state = get_user_state($user_id);

    // بررسی وضعیت کاربر برای تشخیص نوع آپلود
    if ($state === 'admin_uploading') {
        // اگر وضعیت کاربر 'admin_uploading' باشد، یعنی یک ادمین در حال آپلود فایل است.
        process_admin_file($pdo, $update->message);
    }
    // در آینده می‌توان وضعیت‌های دیگری مانند 'user_uploading' (آپلود توسط کاربر عادی) را نیز اینجا مدیریت کرد.
    // else {
    //     // اگر کاربر در هیچ وضعیت آپلودی نباشد، می‌توان فایل را نادیده گرفت یا پیام خطا داد.
    //     send_message($chat_id, "برای آپلود فایل، لطفاً ابتدا از منوی مربوطه اقدام کنید.");
    // }
}

/**
 * این تابع یک فایل ارسال شده توسط ادمین را پردازش و در دیتابیس ذخیره می‌کند.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param stdClass $message آبجکت پیام از تلگرام
 */
function process_admin_file($pdo, $message) {
    $file_info = extract_file_info($message);

    // اگر هیچ اطلاعات فایلی استخراج نشد، تابع متوقف می‌شود.
    if (!$file_info) {
        return;
    }

    // تولید یک کد منحصر به فرد 8 کاراکتری برای فایل
    $file_code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

    // آماده‌سازی دستور SQL برای درج اطلاعات فایل در جدول 'files'
    $stmt = $pdo->prepare(
        "INSERT INTO files (file_id, file_unique_id, file_type, caption, file_code, uploader_id, forward_lock, channel_lock)
         VALUES (?, ?, ?, ?, ?, ?, TRUE, TRUE)" // به صورت پیش‌فرض قفل‌ها فعال هستند
    );

    // اجرای دستور با اطلاعات استخراج شده از فایل
    $stmt->execute([
        $file_info['file_id'],
        $file_info['file_unique_id'],
        $file_info['file_type'],
        $file_info['caption'],
        $file_code,
        $message->from->id
    ]);

    // پس از ذخیره موفقیت‌آمیز فایل، یک منوی مدیریتی به ادمین نمایش داده می‌شود.
    show_file_management_menu($message->chat->id, $file_code);
}


/**
 * این تابع یک منوی شیشه‌ای با گزینه‌های مدیریتی برای فایل تازه آپلود شده نمایش می‌دهد.
 * @param int $chat_id شناسه چت ادمین
 * @param string $file_code کد منحصر به فرد فایلی که مدیریت می‌شود
 */
function show_file_management_menu($chat_id, $file_code) {
    $download_link = "https://t.me/" . BOT_USERNAME . "?start=dl_" . $file_code;

    $text = "✅ فایل شما با موفقیت ذخیره شد.\n\n" .
            "<b>کد فایل:</b> <code>{$file_code}</code>\n" .
            "<b>لینک دانلود:</b> <code>{$download_link}</code>\n\n" .
            "می‌توانید تنظیمات این فایل را در زیر مدیریت کنید:";

    $keyboard = [
        [['text' => '🔐 تنظیم/حذف رمز عبور', 'callback_data' => "set_password_{$file_code}"]],
        [['text' => '📥 محدودیت دانلود', 'callback_data' => "set_download_limit_{$file_code}"]],
        [['text' => '🔄 قفل فروارد (فعال)', 'callback_data' => "toggle_forward_lock_{$file_code}"]],
        [['text' => '📢 قفل کانال (فعال)', 'callback_data' => "toggle_channel_lock_{$file_code}"]],
        [['text' => '🗑 حذف فایل', 'callback_data' => "delete_file_{$file_code}"]],
        [['text' => '📣 ارسال به کانال', 'callback_data' => "send_to_channel_{$file_code}"]]
    ];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);

    send_message($chat_id, $text, $reply_markup);

    // پس از نمایش منو، وضعیت آپلود ادمین را پاک می‌کنیم تا آماده دریافت دستورات بعدی باشد.
    clear_user_state($chat_id);
}


/**
 * این تابع اطلاعات کلیدی یک فایل (مانند file_id، نوع، کپشن و ...) را از آبجکت پیام تلگرام استخراج می‌کند.
 * @param stdClass $message آبجکت پیام
 * @return array|null آرایه‌ای از اطلاعات فایل یا null در صورت عدم وجود فایل
 */
function extract_file_info($message) {
    $file = null;
    $file_type = '';

    // بر اساس نوع پیام، اطلاعات فایل مربوطه را استخراج می‌کنیم
    if (isset($message->video)) {
        $file = $message->video;
        $file_type = 'video';
    } elseif (isset($message->document)) {
        $file = $message->document;
        $file_type = 'document';
    } elseif (isset($message->audio)) {
        $file = $message->audio;
        $file_type = 'audio';
    } elseif (isset($message->voice)) {
        $file = $message->voice;
        $file_type = 'voice';
    } elseif (isset($message->photo)) {
        // برای عکس، تلگرام آرایه‌ای از سایزهای مختلف ارسال می‌کند. ما بزرگترین سایز (آخرین عضو آرایه) را انتخاب می‌کنیم.
        $file = end($message->photo);
        $file_type = 'photo';
    }

    // اگر هیچ فایلی در پیام وجود نداشت، null را باز می‌گردانیم
    if ($file === null) {
        return null;
    }

    // بازگرداندن آرایه‌ای مرتب از اطلاعات استخراج شده
    return [
        'file_id' => $file->file_id,
        'file_unique_id' => $file->file_unique_id,
        'file_type' => $file_type,
        'caption' => $message->caption ?? null, // اگر پیام کپشن نداشت، null ذخیره می‌شود
    ];
}
