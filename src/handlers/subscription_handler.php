<?php
// src/handlers/subscription_handler.php
// این فایل مسئولیت مدیریت تمام منطق مربوط به طرح‌های اشتراک (VIP) را بر عهده دارد.
// این شامل نمایش، ویرایش، فعال/غیرفعال کردن و ناوبری بین طرح‌ها می‌شود.

require_once __DIR__ . '/../includes/helpers.php';

/**
 * منوی مدیریت یک طرح اشتراک مشخص را نمایش می‌دهد.
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @param int $chat_id شناسه چت ادمین
 * @param int|null $message_id شناسه پیامی که باید ویرایش شود (برای ناوبری)
 * @param int $sub_id شماره طرح اشتراکی که باید نمایش داده شود (پیش‌فرض: 1)
 */
function show_subscription_management($pdo, $chat_id, $message_id = null, $sub_id = 1) {
    // دریافت اطلاعات طرح اشتراک مشخص شده از دیتابیس
    $stmt = $pdo->prepare("SELECT * FROM payment_plans WHERE id = ?");
    $stmt->execute([$sub_id]);
    $plan = $stmt->fetch();

    // اگر طرحی با این شماره وجود نداشته باشد، پیام خطا ارسال می‌شود
    if (!$plan) {
        send_message($chat_id, 'این طرح اشتراک یافت نشد.');
        return;
    }

    // آماده‌سازی متغیرها برای نمایش خواناتر
    $status = $plan['is_active'] ? '✅ فعال' : '❌ غیرفعال';
    $price = number_format($plan['price']) . ' تومان'; // فرمت‌دهی قیمت به صورت سه‌رقم سه‌رقم
    $duration = $plan['duration_days'] . ' روز';

    // ساخت متن اصلی پیام
    $text = "⚙️ <b>مدیریت طرح اشتراک شماره #{$sub_id}</b>\n\n" .
            "<b>نام طرح:</b> " . htmlspecialchars($plan['name']) . "\n" .
            "<b>وضعیت:</b> {$status}\n" .
            "<b>مدت زمان:</b> {$duration}\n" .
            "<b>قیمت:</b> {$price}\n\n" .
            "برای ویرایش هر مورد، روی دکمه مربوطه کلیک کنید.";

    // ساخت کیبورد شیشه‌ای (inline keyboard)
    $keyboard = [
        [['text' => '✏️ ویرایش نام', 'callback_data' => "sub_edit_name_{$sub_id}"]],
        [['text' => '🔄 تغییر وضعیت', 'callback_data' => "sub_toggle_status_{$sub_id}"]],
        [['text' => '⏳ ویرایش مدت', 'callback_data' => "sub_edit_duration_{$sub_id}"]],
        [['text' => '💰 ویرایش قیمت', 'callback_data' => "sub_edit_price_{$sub_id}"]],
    ];

    // ساخت دکمه‌های ناوبری (قبلی/بعدی)
    $nav_buttons = [];
    if ($sub_id > 1) {
        $nav_buttons[] = ['text' => '◀️ طرح قبلی', 'callback_data' => 'sub_nav_' . ($sub_id - 1)];
    }
    // فرض بر این است که 6 پلن اشتراک داریم.
    if ($sub_id < 6) {
        $nav_buttons[] = ['text' => 'طرح بعدی ▶️', 'callback_data' => 'sub_nav_' . ($sub_id + 1)];
    }

    // اگر دکمه‌های ناوبری وجود داشت، آن‌ها را به کیبورد اضافه می‌کنیم
    if (!empty($nav_buttons)) {
        $keyboard[] = $nav_buttons;
    }

    // اضافه کردن دکمه بازگشت به منوی تنظیمات پرداخت
    $keyboard[] = [['text' => '🔙 بازگشت به تنظیمات پرداخت', 'callback_data' => 'back_to_payment_menu']];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);

    // اگر message_id ارسال شده باشد، پیام قبلی ویرایش می‌شود، در غیر این صورت پیام جدید ارسال می‌شود
    if ($message_id) {
        edit_message_text($chat_id, $message_id, $text, $reply_markup);
    } else {
        send_message($chat_id, $text, $reply_markup);
    }
}

/**
 * این تابع callbackهای مربوط به منوی مدیریت اشتراک‌ها را پردازش می‌کند.
 * @param PDO $pdo
 * @param stdClass $callback_query
 */
function handle_subscription_callback($pdo, $callback_query) {
    $chat_id = $callback_query->message->chat->id;
    $message_id = $callback_query->message->message_id;
    $data = $callback_query->data;

    // --- مدیریت دکمه بازگشت ---
    if ($data === 'back_to_payment_menu') {
        require_once __DIR__ . '/payment_handler.php';
        show_payment_menu($pdo, $chat_id, $message_id);
        answer_callback_query($callback_query->id);
        return;
    }

    // --- مدیریت دکمه‌های ناوبری ---
    if (strpos($data, 'sub_nav_') === 0) {
        $sub_id = (int)str_replace('sub_nav_', '', $data);
        show_subscription_management($pdo, $chat_id, $message_id, $sub_id);
        answer_callback_query($callback_query->id);
    }
    // --- مدیریت دکمه تغییر وضعیت (فعال/غیرفعال) ---
    elseif (strpos($data, 'sub_toggle_status_') === 0) {
        $sub_id = (int)str_replace('sub_toggle_status_', '', $data);
        // دستور SQL برای معکوس کردن مقدار ستون is_active
        $stmt = $pdo->prepare("UPDATE payment_plans SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$sub_id]);
        answer_callback_query($callback_query->id, 'وضعیت با موفقیت تغییر کرد.');
        show_subscription_management($pdo, $chat_id, $message_id, $sub_id); // نمایش مجدد منو برای دیدن تغییر
    }
    // --- مدیریت دکمه‌های ویرایش ---
    elseif (strpos($data, 'sub_edit_') === 0) {
        $parts = explode('_', $data);
        $action = $parts[2]; // مثلا 'name', 'duration', 'price'
        $sub_id = (int)$parts[3];

        // آرایه‌ای برای مپ کردن اکشن به پیام و وضعیت مورد نیاز
        $field_map = [
            'name' => ['prompt' => 'لطفا نام جدید طرح را وارد کنید:', 'state' => 'editing_sub_name'],
            'duration' => ['prompt' => 'لطفا مدت زمان جدید را به روز وارد کنید (فقط عدد):', 'state' => 'editing_sub_duration'],
            'price' => ['prompt' => 'لطفا قیمت جدید را به تومان وارد کنید (فقط عدد):', 'state' => 'editing_sub_price'],
        ];

        // اگر اکشن معتبر بود
        if (array_key_exists($action, $field_map)) {
            $prompt = $field_map[$action]['prompt'];
            $state_prefix = $field_map[$action]['state'];

            // تنظیم وضعیت کاربر برای دریافت پاسخ در مرحله بعد
            set_user_state($chat_id, "{$state_prefix}:{$sub_id}");
            // ارسال پیام راهنما به ادمین
            send_message($chat_id, $prompt);
            answer_callback_query($callback_query->id);
        }
    }
}

/**
 * این تابع پاسخ متنی ادمین برای ویرایش یک طرح را پردازش می‌کند.
 * @param PDO $pdo
 * @param stdClass $update
 */
function handle_subscription_edit($pdo, $update) {
    $chat_id = $update->message->chat->id;
    $text = $update->message->text;
    $state = get_user_state($chat_id);

    // استخراج پیشوند وضعیت و شماره طرح از رشته وضعیت
    // مثال: از 'editing_sub_name:1' مقادیر 'editing_sub_name' و '1' استخراج می‌شود
    list($state_prefix, $sub_id) = explode(':', $state);
    $sub_id = (int)$sub_id;

    // آرایه‌ای برای مپ کردن وضعیت به نام ستون در دیتابیس و نوع داده
    $field_map = [
        'editing_sub_name' => ['column' => 'name', 'type' => 'string'],
        'editing_sub_duration' => ['column' => 'duration_days', 'type' => 'integer'],
        'editing_sub_price' => ['column' => 'price', 'type' => 'integer'],
    ];

    // اگر وضعیت معتبر نباشد، آن را پاک کرده و خارج می‌شویم
    if (!array_key_exists($state_prefix, $field_map)) {
        clear_user_state($chat_id);
        return;
    }

    $column = $field_map[$state_prefix]['column'];
    $type = $field_map[$state_prefix]['type'];
    $value = $text;

    // اعتبارسنجی و پاکسازی ورودی ادمین
    if ($type === 'integer') {
        if (!is_numeric($value) || $value < 0) {
            send_message($chat_id, 'مقدار وارد شده نامعتبر است. لطفا یک عدد صحیح و مثبت وارد کنید.');
            return;
        }
        $value = (int)$value;
    } else { // اگر نوع رشته (string) باشد
        $value = htmlspecialchars(trim($value)); // حذف فضاهای خالی و کاراکترهای HTML
        if (empty($value)) {
            send_message($chat_id, 'مقدار وارد شده نمی‌تواند خالی باشد.');
            return;
        }
    }

    // به‌روزرسانی مقدار جدید در دیتابیس
    $stmt = $pdo->prepare("UPDATE payment_plans SET {$column} = ? WHERE id = ?");
    if ($stmt->execute([$value, $sub_id])) {
        send_message($chat_id, '✅ طرح اشتراک با موفقیت به‌روزرسانی شد.');
    } else {
        send_message($chat_id, 'خطایی در به‌روزرسانی رخ داد.');
    }

    // پاک کردن وضعیت کاربر پس از اتمام عملیات
    clear_user_state($chat_id);

    // نمایش مجدد منوی مدیریت برای مشاهده تغییرات
    show_subscription_management($pdo, $chat_id, null, $sub_id);
}
?>