<?php
// src/handlers/payment_handler.php
// این فایل مسئولیت مدیریت تمام منطق مربوط به تنظیمات پرداخت را بر عهده دارد.

/**
 * منوی اصلی تنظیمات پرداخت را به ادمین نمایش می‌دهد.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param int $chat_id شناسه چت ادمین
 * @param int|null $message_id شناسه پیام برای ویرایش (در صورت بازگشت از منوهای دیگر)
 */
function show_payment_menu($pdo, $chat_id, $message_id = null) {
    // دریافت درگاه پرداخت فعال از دیتابیس
    $stmt = $pdo->query("SELECT payment_gateway FROM settings WHERE id = 1");
    $current_gateway = $stmt->fetchColumn();

    // تعیین نام فارسی درگاه برای نمایش
    if ($current_gateway === 'zarinpal') {
        $gateway_name = 'زرین‌پال';
    } elseif ($current_gateway === 'zibal') {
        $gateway_name = 'زیبال';
    } else {
        $gateway_name = 'تنظیم نشده';
    }

    $text = "💰 <b>تنظیمات پرداخت</b>\n\n" .
            "در این بخش می‌توانید درگاه پرداخت و سایر تنظیمات مربوط به اشتراک‌ها را مدیریت کنید.\n\n" .
            "▫️ <b>درگاه پرداخت فعلی:</b> {$gateway_name}";

    $keyboard = [
        [['text' => '💳 انتخاب درگاه پرداخت', 'callback_data' => 'select_gateway_menu']],
        [['text' => '🗂 مدیریت طرح‌های اشتراک', 'callback_data' => 'manage_subscriptions_menu']],
        // در آینده گزینه‌های دیگری مانند "تغییر متن خرید اشتراک" اضافه خواهد شد
        [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'back_to_main_menu']]
    ];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);

    // اگر message_id وجود داشته باشد، پیام قبلی را ویرایش می‌کند، در غیر این صورت پیام جدید ارسال می‌کند.
    if ($message_id) {
        edit_message_text($chat_id, $message_id, $text, $reply_markup);
    } else {
        send_message($chat_id, $text, $reply_markup);
    }
}

/**
 * این تابع callbackهای مربوط به منوی پرداخت را مدیریت می‌کند.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param stdClass $callback_query آبجکت callback_query از تلگرام
 */
function handle_payment_callback($pdo, $callback_query) {
    $chat_id = $callback_query->message->chat->id;
    $message_id = $callback_query->message->message_id;
    $data = $callback_query->data;

    // مسیریابی بر اساس داده‌های callback
    switch ($data) {
        case 'select_gateway_menu':
            show_gateway_selection_menu($pdo, $chat_id, $message_id);
            break;
        case 'manage_subscriptions_menu':
            // فراخوانی تابع از کنترل‌کننده اشتراک‌ها برای نمایش منوی مدیریت
            require_once __DIR__ . '/subscription_handler.php';
            show_subscription_management($pdo, $chat_id, $message_id);
            break;
        case 'back_to_payment_menu':
            show_payment_menu($pdo, $chat_id, $message_id);
            break;
        // موارد دیگر ...
    }

    // پاسخ به callback برای حذف حالت لودینگ
    answer_callback_query($callback_query->id);
}

/**
 * منوی انتخاب درگاه پرداخت را نمایش می‌دهد.
 * @param PDO $pdo
 * @param int $chat_id
 * @param int $message_id
 */
function show_gateway_selection_menu($pdo, $chat_id, $message_id) {
    $stmt = $pdo->query("SELECT payment_gateway FROM settings WHERE id = 1");
    $current_gateway = $stmt->fetchColumn();

    $text = "لطفاً درگاه پرداخت مورد نظر خود را انتخاب کنید.";

    // علامت‌گذاری درگاه فعال
    $zarinpal_text = ($current_gateway === 'zarinpal') ? '✅ زرین‌پال' : 'زرین‌پال';
    $zibal_text = ($current_gateway === 'zibal') ? '✅ زیبال' : 'زیبال';

    $keyboard = [
        [['text' => $zarinpal_text, 'callback_data' => 'set_gateway_zarinpal']],
        [['text' => $zibal_text, 'callback_data' => 'set_gateway_zibal']],
        [['text' => '🔙 بازگشت', 'callback_data' => 'back_to_payment_menu']]
    ];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);
    edit_message_text($chat_id, $message_id, $text, $reply_markup);
}

/**
 * درگاه پرداخت انتخاب شده توسط ادمین را در دیتابیس ذخیره می‌کند.
 * @param PDO $pdo
 * @param stdClass $callback_query
 */
function handle_gateway_selection($pdo, $callback_query) {
    $chat_id = $callback_query->message->chat->id;
    $message_id = $callback_query->message->message_id;
    $data = $callback_query->data;

    // استخراج نام درگاه از داده callback
    $gateway = str_replace('set_gateway_', '', $data);

    // به‌روزرسانی دیتابیس
    $stmt = $pdo->prepare("UPDATE settings SET payment_gateway = ? WHERE id = 1");
    $stmt->execute([$gateway]);

    // نمایش پیام تایید به ادمین
    answer_callback_query($callback_query->id, "درگاه پرداخت با موفقیت به {$gateway} تغییر یافت.");

    // نمایش مجدد منوی انتخاب درگاه با حالت به‌روز شده
    show_gateway_selection_menu($pdo, $chat_id, $message_id);
}
?>