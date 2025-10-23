<?php
// src/includes/helpers.php
// این فایل شامل مجموعه‌ای از توابع کمکی است که در بخش‌های مختلف ربات استفاده می‌شوند.
// هدف از این فایل، جلوگیری از نوشتن کدهای تکراری و سازماندهی بهتر پروژه است.


/**
 * یک پیام متنی به کاربر یا کانال مشخص شده ارسال می‌کند.
 * این تابع به عنوان یک پوشش (wrapper) برای متد sendMessage تلگرام عمل می‌کند.
 * @param int|string $chat_id شناسه چت مقصد (می‌تواند آیدی عددی کاربر یا نام کاربری کانال باشد)
 * @param string $text متنی که باید ارسال شود. از تگ‌های HTML پشتیبانی می‌کند.
 * @param string|null $reply_markup کیبورد شیشه‌ای یا کیبورد معمولی به صورت JSON.
 * @param int|null $reply_to_message_id اگر این پیام پاسخی به پیام دیگری است، شناسه آن پیام را اینجا قرار دهید.
 */
function send_message($chat_id, $text, $reply_markup = null, $reply_to_message_id = null) {
    // آماده‌سازی آرایه پارامترها برای ارسال به API تلگرام
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML', // همیشه از فرمت HTML برای متن‌ها استفاده می‌کنیم
        'disable_web_page_preview' => true, // پیش‌نمایش لینک‌ها را غیرفعال می‌کند
    ];
    // اگر کیبورد ارسال شده باشد، آن را به پارامترها اضافه می‌کنیم
    if ($reply_markup) {
        $params['reply_markup'] = $reply_markup;
    }
    // اگر پیام در پاسخ به پیام دیگری است، آن را اضافه می‌کنیم
    if ($reply_to_message_id) {
        $params['reply_to_message_id'] = $reply_to_message_id;
    }
    // فراخوانی تابع عمومی برای ارسال درخواست به تلگرام
    telegram_request('sendMessage', $params);
}

/**
 * یک پیام متنی موجود را ویرایش می‌کند.
 * معمولاً برای به‌روزرسانی پیام پس از کلیک روی دکمه شیشه‌ای استفاده می‌شود.
 * @param int|string $chat_id شناسه چت
 * @param int $message_id شناسه پیامی که باید ویرایش شود
 * @param string $text متن جدید
 * @param string|null $reply_markup کیبورد شیشه‌ای جدید
 */
function edit_message_text($chat_id, $message_id, $text, $reply_markup = null) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($reply_markup) {
        $params['reply_markup'] = $reply_markup;
    }
    telegram_request('editMessageText', $params);
}

/**
 * به یک Callback Query پاسخ می‌دهد.
 * این کار باعث می‌شود علامت ساعت (loading) کنار دکمه شیشه‌ای متوقف شود.
 * @param string $callback_query_id شناسه کوئری که از آبجکت آپدیت دریافت می‌شود
 * @param string|null $text متنی که به صورت یک نوتیفیکیشن کوچک به کاربر نمایش داده می‌شود
 * @param bool $show_alert اگر true باشد، متن به صورت یک پاپ-آپ بزرگ نمایش داده می‌شود
 */
function answer_callback_query($callback_query_id, $text = null, $show_alert = false) {
    $params = [
        'callback_query_id' => $callback_query_id,
    ];
    if ($text) {
        $params['text'] = $text;
        $params['show_alert'] = $show_alert;
    }
    telegram_request('answerCallbackQuery', $params);
}

/**
 * تابع اصلی برای ارسال هر نوع درخواست به API تلگرام با استفاده از cURL.
 * @param string $method نام متد API (مانند 'sendMessage', 'editMessageText')
 * @param array $params آرایه‌ای از پارامترهایی که باید به متد ارسال شوند
 * @return mixed نتیجه بازگشتی از API تلگرام به صورت آبجکت PHP
 */
function telegram_request($method, $params = []) {
    // ساخت URL کامل برای درخواست
    $url = 'https://api.telegram.org/bot' . API_TOKEN . '/' . $method;

    // مقداردهی اولیه cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // نتیجه را به صورت رشته بازگرداند
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params); // پارامترها را به صورت POST ارسال کند

    // اجرای درخواست
    $result = curl_exec($ch);

    // بررسی وجود خطا در cURL
    if (curl_error($ch)) {
        // ثبت خطا در لاگ سرور برای بررسی‌های بعدی
        error_log("cURL Error for method {$method}: " . curl_error($ch));
    }

    // بستن cURL
    curl_close($ch);

    // تبدیل نتیجه JSON به آبجکت PHP و بازگرداندن آن
    return json_decode($result);
}

/**
 * بررسی می‌کند که آیا کاربر جدید است یا خیر. اگر جدید باشد، او را در دیتابیس ثبت می‌کند.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param stdClass $from آبجکت 'from' از پیام تلگرام که حاوی اطلاعات کاربر است
 */
function register_user_if_new($pdo, $from) {
    // ابتدا بررسی می‌کنیم که آیا کاربری با این user_id در دیتابیس وجود دارد یا خیر
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
    $stmt->execute([$from->id]);

    // اگر نتیجه‌ای یافت نشد (کاربر جدید است)
    if ($stmt->fetch() === false) {
        // اطلاعات کاربر را در جدول users درج می‌کنیم
        $stmt = $pdo->prepare("INSERT INTO users (user_id, first_name, username) VALUES (?, ?, ?)");
        // مقادیر را با اطلاعات دریافتی از تلگرام پر می‌کنیم
        $stmt->execute([$from->id, $from->first_name, $from->username ?? null]);
    }
}

?>