<?php
// bot.php
// گزارش‌دهی تمام خطاها برای اشکال‌زدایی آسان‌تر
error_reporting(E_ALL);
ini_set('display_errors', 1);

// بارگذاری autoload.php برای مدیریت وابستگی‌های Composer (مانند Monolog)
require_once __DIR__ . '/vendor/autoload.php';
// بارگذاری فایل‌های کمکی و اصلی کنترل‌کننده‌ها
require_once __DIR__ . '/src/utils/helpers.php';
require_once __DIR__ . '/src/handlers/messageHandler.php';
require_once __DIR__ . '/src/handlers/callbackQueryHandler.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// ایجاد یک نمونه از Logger برای ثبت وقایع و خطاها
$log = new Logger('bot');
// تنظیم کنترل‌کننده برای ذخیره لاگ‌ها در فایل bot.log
$log->pushHandler(new StreamHandler(__DIR__ . '/bot.log', Logger::WARNING));

// دریافت داده‌های خام ورودی از وبهوک تلگرام
$updateJson = file_get_contents('php://input');
// تبدیل داده‌های JSON به یک شیء PHP
$update = json_decode($updateJson);

// اگر داده ورودی معتبر نباشد، آن را در یک فایل لاگ جداگانه ذخیره کرده و خارج می‌شویم
if ($update === null) {
    file_put_contents('invalid_update.log', $updateJson . PHP_EOL, FILE_APPEND);
    exit;
}

try {
    // بررسی اینکه آیا آپدیت شامل یک پیام جدید است
    if (isset($update->message)) {
        // اگر پیام بود، آن را به کنترل‌کننده پیام‌ها ارسال می‌کنیم
        handleMessage($update);
    }
    // بررسی اینکه آیا آپدیت شامل یک کوئری بازگشتی (از دکمه‌های شیشه‌ای) است
    elseif (isset($update->callback_query)) {
        // اگر کوئری بازگشتی بود، آن را به کنترل‌کننده مربوطه ارسال می‌کنیم
        handleCallbackQuery($update);
    }
} catch (Exception $e) {
    // در صورت بروز هرگونه خطا در پردازش آپدیت، آن را لاگ می‌کنیم
    $log->error('خطا در پردازش آپدیت: ' . $e->getMessage(), ['update' => $update]);
}
