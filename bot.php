<?php
// bot.php
// این فایل نقطه ورود اصلی ربات است. تمام درخواست‌ها از تلگرام به این فایل ارسال می‌شوند.

// فعال کردن نمایش تمام خطاها برای اشکال‌زدایی در حین توسعه
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// فراخوانی فایل‌های اصلی و ضروری
require_once __DIR__ . '/config/config.php';          // فایل تنظیمات اصلی (توکن، دیتابیس و ...)
require_once __DIR__ . '/src/includes/database.php';   // کلاس مدیریت اتصال به دیتابیس
require_once __DIR__ . '/src/includes/helpers.php';     // توابع کمکی عمومی (مانند ارسال پیام)
require_once __DIR__ . '/src/includes/state_manager.php'; // مدیریت حالت‌های کاربری (برای مکالمات چند مرحله‌ای)

// دریافت آپدیت ورودی از تلگرام به صورت JSON
$update = json_decode(file_get_contents('php://input'));

// اگر آپدیت معتبر نباشد، اسکریپت متوقف می‌شود
if (!$update) {
    // معمولاً بهتر است اینجا لاگ ثبت شود تا درخواست‌های نامعتبر بررسی شوند
    exit('ورودی نامعتبر');
}

// ایجاد یک نمونه از کلاس دیتابیس برای برقراری اتصال
$db = new Database();
$pdo = $db->getConnection();

// بررسی اینکه آیا اتصال به دیتابیس موفقیت‌آمیز بوده است یا خیر
if (!$pdo) {
    // اگر اتصال ناموفق باشد، یک خطا در لاگ سرور ثبت می‌شود و اسکریپت متوقف می‌شود.
    // این کار از نمایش خطاهای حساس به کاربر جلوگیری می‌کند.
    error_log("CRITICAL: Database connection failed.");
    exit('خطا در اتصال به دیتابیس');
}


// ============== شروع مسیریابی اصلی (Routing) ==============

// بخش اول: مسیریابی بر اساس پیام‌های متنی و دستورات (Message)
if (isset($update->message)) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $user_id = $message->from->id;
    $text = $message->text ?? null; // اگر پیام متنی نباشد (مثلاً فایل باشد)، مقدار null خواهد بود

    // بررسی و ثبت کاربر جدید در دیتابیس
    // این تابع در فایل helpers.php قرار دارد
    register_user_if_new($pdo, $message->from);

    // --- بررسی وضعیت‌های خاص کاربر قبل از مسیریابی عادی ---
    $user_state = get_user_state($user_id);

    // اگر ربات منتظر رمز عبور از کاربر باشد
    if ($user_state && strpos($user_state, 'awaiting_password_') === 0) {
        if ($text == 'انصراف') {
            // بازنشانی وضعیت کاربر و نمایش منوی اصلی
            clear_user_state($user_id);
            // نمایش منوی اصلی از فایل start_handler.php
            require_once __DIR__ . '/src/handlers/start_handler.php';
            // برای نمایش مجدد منوی اصلی، از همان تابع استارت استفاده می‌کنیم
            // متن پیام را تغییر می‌دهیم تا مشخص شود عملیات لغو شده است
            $update->message->text = '/start'; // شبیه‌سازی دستور استارت
            handle_start($pdo, $update);
            send_message($user_id, "عملیات لغو شد.");

        } else {
            // پردازش رمز عبور وارد شده
            require_once 'src/handlers/password_handler.php';
            $file_code = str_replace('awaiting_password_', '', $user_state);
            handle_password_submission($pdo, $user_id, $text, $file_code);
        }
        exit; // اجرای اسکریپت در اینجا پایان می‌یابد زیرا این یک اقدام خاص بوده است
    }


    // مسیریابی بر اساس نوع پیام
    if (isset($message->photo) || isset($message->video) || isset($message->document) || isset($message->audio) || isset($message->voice)) {
        // اگر پیام حاوی فایل باشد، آن را به کنترل‌کننده آپلود ارسال می‌کنیم
        require_once __DIR__ . '/src/handlers/upload_handler.php';
        handle_file_receive($pdo, $update);
    }
    // مسیریابی بر اساس دستورات متنی
    elseif (strpos($text, '/start dl_') === 0) {
        // اگر پیام یک لینک دانلود (deep link) باشد
        require_once __DIR__ . '/src/handlers/download_handler.php';
        handle_download_request($pdo, $update);
    }
    elseif ($text === '/start') {
        // دستور شروع اصلی
        require_once __DIR__ . '/src/handlers/start_handler.php';
        handle_start($pdo, $update);
    }
    elseif ($text === '👤 حساب کاربری') {
        // دکمه منوی حساب کاربری
        require_once __DIR__ . '/src/handlers/account_handler.php';
        handle_account_display($pdo, $update);
    }

    // --- مسیریابی دستورات مخصوص ادمین ---
    elseif (in_array($user_id, ADMINS)) {
        switch ($text) {
            case '📤 آپلود تکی/آلبومی رسانه':
                require_once __DIR__ . '/src/handlers/upload_handler.php';
                handle_admin_upload_start($chat_id);
                break;

            case '📊 آمار':
                require_once __DIR__ . '/src/handlers/stats_handler.php';
                handle_stats_request($pdo, $chat_id);
                break;

            case '💰 تنظیمات پرداخت':
                require_once __DIR__ . '/src/handlers/payment_handler.php';
                show_payment_menu($pdo, $chat_id);
                break;

            case '🗂 مدیریت اشتراک ها':
                require_once __DIR__ . '/src/handlers/subscription_handler.php';
                show_subscription_management($pdo, $chat_id);
                break;

            case '👁‍🗨 ری اکشن/سین اجباری':
                require_once __DIR__ . '/src/handlers/forced_interaction_handler.php';
                show_forced_interaction_menu($pdo, $chat_id);
                break;

            case '📢 تنظیم تبلیغات':
                require_once __DIR__ . '/src/handlers/ads_handler.php';
                show_ads_management_menu($pdo, $chat_id);
                break;

            default:
                // اگر دستور ادمین شناخته شده نبود، آن را به عنوان یک وضعیت (state) بررسی می‌کنیم
                handle_state_based_actions($pdo, $update);
                break;
        }
    }
    // اگر پیام از طرف کاربر عادی بود و یک دستور شناخته شده نبود، آن را نادیده می‌گیریم یا یک پیام پیش‌فرض ارسال می‌کنیم
    else {
        // اینجا هم می‌توان پیام "دستور نامعتبر" ارسال کرد
    }
}
// بخش دوم: مسیریابی بر اساس کلیک روی دکمه‌های شیشه‌ای (Callback Query)
elseif (isset($update->callback_query)) {
    $callback_query = $update->callback_query;
    $data = $callback_query->data;
    $user_id = $callback_query->from->id;

    // --- مسیریابی بر اساس پیشوند داده‌های callback ---

    // مربوط به پنل مدیریت اشتراک‌ها
    if (strpos($data, 'sub_') === 0 || $data === 'back_to_payment_menu') {
        require_once __DIR__ . '/src/handlers/subscription_handler.php';
        handle_subscription_callback($pdo, $callback_query);
    }
    // مربوط به پنل مدیریت تعاملات اجباری (جوین، سین و ...)
    elseif (strpos($data, 'fi_') === 0) {
        require_once __DIR__ . '/src/handlers/forced_interaction_handler.php';
        handle_forced_interaction_callback($pdo, $callback_query);
    }
    // کنترل‌کننده بررسی عضویت پس از کلیک کاربر
    elseif (strpos($data, 'check_join_') === 0) {
        require_once __DIR__ . '/src/handlers/download_handler.php';
        handle_recheck_join_request($pdo, $callback_query);
    }
    // مربوط به پنل مدیریت تبلیغات
    elseif (strpos($data, 'ads_') === 0) {
        require_once __DIR__ . '/src/handlers/ads_handler.php';
        handle_ads_callback($pdo, $callback_query);
    }
    // مربوط به پنل تنظیمات پرداخت
    elseif (in_array($data, ['select_gateway_menu', 'manage_subscriptions_menu', 'back_to_payment_menu']) || strpos($data, 'set_gateway_') === 0) {
        require_once __DIR__ . '/src/handlers/payment_handler.php';
        if (strpos($data, 'set_gateway_') === 0) {
            handle_gateway_selection($pdo, $callback_query);
        } else {
            handle_payment_callback($pdo, $callback_query);
        }
    }
    // و سایر کنترل‌کننده‌های callback...
}


/**
 * این تابع مسئولیت مدیریت پیام‌هایی را بر عهده دارد که در یک مکالمه چند مرحله‌ای هستند.
 * بر اساس "وضعیت" (state) ذخیره شده برای کاربر، پیام او به کنترل‌کننده مربوطه ارسال می‌شود.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param stdClass $update آبجکت آپدیت تلگرام
 */
function handle_state_based_actions($pdo, $update) {
    $chat_id = $update->message->chat->id;
    $state = get_user_state($chat_id);

    // اگر کاربر در هیچ وضعیتی نباشد، تابع کاری انجام نمی‌دهد
    if ($state === null) {
        // send_message($chat_id, "دستور شما را متوجه نشدم."); // Optional
        return;
    }

    // بررسی وضعیت‌های مختلف و ارسال به کنترل‌کننده مناسب
    if (strpos($state, 'editing_sub_') === 0) {
        require_once __DIR__ . '/src/handlers/subscription_handler.php';
        handle_subscription_edit($pdo, $update);
    }
    elseif ($state === 'adding_forced_join_channel') {
        require_once __DIR__ . '/src/handlers/forced_interaction_handler.php';
        handle_add_forced_join_channel($pdo, $update);
    }
    elseif (in_array($state, ['editing_seen_channel', 'editing_seen_count', 'editing_reaction_channel', 'editing_reaction_count'])) {
        require_once __DIR__ . '/src/handlers/forced_interaction_handler.php';
        handle_forced_interaction_edit($pdo, $update);
    }
    elseif ($state === 'adding_ad') {
        require_once __DIR__ . '/src/handlers/ads_handler.php';
        handle_add_ad($pdo, $update);
    }
    // سایر وضعیت‌ها اینجا اضافه می‌شوند...
}

?>