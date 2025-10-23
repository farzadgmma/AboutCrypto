<?php
// src/handlers/messageHandler.php

// بارگذاری تمام فایل‌های مورد نیاز
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/admin/adminHandler.php';
require_once __DIR__ . '/user/startHandler.php';
require_once __DIR__ . '/user/accountHandler.php';
require_once __DIR__ . '/user/downloadHandler.php';
require_once __DIR__ . '/user/subscriptionHandler.php';
require_once __DIR__ . '/user/publicListsHandler.php';
require_once __DIR__ . '/user/supportHandler.php';
require_once __DIR__ . '/user/passwordHandler.php';
require_once __DIR__ . '/admin/mediaManagementHandler.php';
require_once __DIR__ . '/admin/uploadHandler.php';
require_once __DIR__ . '/admin/userManagementHandler.php';
require_once __DIR__ . '/admin/adminsManagementHandler.php';
require_once __DIR__ . '/admin/customizationHandler.php';
require_once __DIR__ . '/admin/forcedJoinHandler.php';
require_once __DIR__ . '/admin/paymentSettingsHandler.php';
require_once __DIR__ . '/admin/adsHandler.php';
require_once __DIR__ . '/admin/forcedInteractionHandler.php';
require_once __DIR__ . '/admin/broadcastHandler.php';
require_once __DIR__ . '/admin/statsHandler.php';


/**
 * نقطه ورود اصلی برای پردازش تمام پیام‌های متنی و فایل‌های ارسالی کاربران.
 *
 * @param object $update آبجکت کامل آپدیت از تلگرام.
 */
function handleMessage($update) {
    // استخراج اطلاعات ضروری از پیام
    $message = $update->message;
    $chat_id = $message->chat->id;
    $user_id = $message->from->id;
    $text = $message->text ?? ''; // در صورت ارسال فایل، متن خالی است
    $first_name = $message->from->first_name;

    // دریافت اطلاعات کاربر از دیتابیس یا ایجاد کاربر جدید در صورت عدم وجود
    $user = get_or_create_user($user_id, $first_name);

    // اگر کاربر مسدود شده باشد، پیام مسدودیت را نمایش داده و پردازش را متوقف می‌کند
    if ($user['step'] === 'ban') {
        sendMessage($chat_id, "📛 شما از ربات مسدود هستید.");
        return;
    }

    // --- مسیریابی دستورات اصلی ---

    // بررسی دستور /start همراه با کد دانلود فایل
    if (strpos($text, '/start dl_') === 0) {
        $file_code = substr($text, 10);
        handleDownloadRequest($user, $chat_id, $file_code);
        return;
    }

    // بررسی دستور /start همراه با کد فایل آپلود شده توسط کاربر
    if (strpos($text, '/start user_') === 0) {
        // این بخش بعداً پیاده‌سازی خواهد شد
        sendMessage($chat_id, "در حال پردازش لینک فایل کاربر...");
        return;
    }

    // --- مسیریابی دستورات متنی منوی اصلی ---
    switch ($text) {
        case '/start':
        case '🏠 برگشت به منو':
            handleStartCommand($user, $chat_id);
            break;

        case '👤 حساب کاربری':
            handleAccountCommand($user, $chat_id);
            break;

        case '💰 خرید اشتراک':
            handleSubscriptionCommand($user, $chat_id);
            break;

        case '🔆 پربازدید ها':
            handleTopDownloadsCommand($chat_id);
            break;

        case '🌀 جدیدترین ها':
            handleNewestFilesCommand($chat_id);
            break;

        case '♥️ محبوب ترین ها':
            handleMostLikedCommand($chat_id);
            break;

        case '👨🏼‍💻 پشتیبانی':
            handleSupportCommand($user_id, $chat_id);
            break;

        // --- ورودی‌های پنل ادمین ---
        case '🔧 پنل':
        case '🔙 منوی پنل':
            if (isAdmin($user_id)) {
                showAdminPanel($chat_id);
            }
            break;
        case '🗂 مدیریت رسانه':
            if (isAdmin($user_id)) {
                showMediaManagementPanel($chat_id);
            }
            break;
        case '🗂 تمام رسانه ها':
            if (isAdmin($user_id)) {
                listAllMedia($chat_id);
            }
            break;
        case '📤 آپلود تکی/آلبومی رسانه':
            if (isAdmin($user_id)) {
                setUserStep($user_id, 'admin_upload_start');
                sendMessage($chat_id, "✔️ لطفا تمام فایل های خود را ارسال کنید و در انتها روی گزینه ذخیره بزنید.", ['keyboard' => [[['text' => '☑️ ذخیره فایل ها']], [['text' => '🔙 منوی پنل']]], 'resize_keyboard' => true]);
            }
            break;
        case '☑️ ذخیره فایل ها':
            if (isAdmin($user_id) && strpos($user['step'], 'admin_upload_') === 0) {
                finishAdminUpload($user, $chat_id);
            }
            break;
        case '🔍 جستجوی کاربر':
            if(isAdmin($user_id)) {
                showUserSearchPanel($chat_id);
            }
            break;
        case '👨🏻‍💻 مدیریت ادمین':
            if(isAdmin($user_id)) {
                showAdminManagementPanel($chat_id);
            }
            break;
        case '➕ افزودن ادمین':
            if(isAdmin($user_id)) {
                askForAdminId($chat_id);
            }
            break;
        case '👥 لیست ادمین ها':
            if(isAdmin($user_id)) {
                listAdmins($chat_id);
            }
            break;
        case '🎨 شخصی سازی':
            if(isAdmin($user_id)) {
                showCustomizationPanel($chat_id);
            }
            break;
        case '🔐 جوین اجباری':
            if(isAdmin($user_id)) {
                showForcedJoinPanel($chat_id);
            }
            break;
        case '💰 تنظیمات پرداخت':
            if(isAdmin($user_id)) {
                showPaymentSettingsPanel($chat_id);
            }
            break;
        case '⚙️ درگاه پرداخت':
            if(isAdmin($user_id)) {
                showGatewayPanel($chat_id, $message->message_id);
            }
            break;
        case '🔑 تغییر مریچنت زرین پال':
            if(isAdmin($user_id)) {
                askForMerchantID($chat_id, 'zarin');
            }
            break;
        case '🔑 تغییر مریچنت زیبال':
            if(isAdmin($user_id)) {
                askForMerchantID($chat_id, 'ziball');
            }
            break;
        case '📢 تنظیم تبلیغات':
            if(isAdmin($user_id)) {
                showAdsPanel($chat_id);
            }
            break;
        case '👁‍🗨 ری اکشن/سین اجباری':
            if(isAdmin($user_id)) {
                showForcedInteractionPanel($chat_id);
            }
            break;
        case '📩 ارسال همگانی':
            if(isAdmin($user_id)) {
                showBroadcastPanel($chat_id);
            }
            break;
        case '📨 فروارد همگانی':
            if(isAdmin($user_id)) {
                askForBroadcastForward($chat_id);
            }
            break;
        case '📊 آمار':
            if(isAdmin($user_id)) {
                showStats($chat_id);
            }
            break;
        default:
            // --- پردازش مبتنی بر وضعیت (Step) ---
            $step = $user['step'];

            if ($step !== 'none') {
                if ($step === 'admin_awaiting_user_id') {
                    handleUserSearch($chat_id, $text);
                    setUserStep($user_id, 'none');
                    return;
                }
                if ($step === 'admin_awaiting_add_admin_id') {
                    addAdmin($chat_id, $text);
                    return;
                }
                if (strpos($step, 'admin_awaiting_merchant_') === 0) {
                    $gateway = substr($step, strlen('admin_awaiting_merchant_'));
                    updateMerchantID($user_id, $gateway, $text);
                    return;
                }
                if ($step === 'admin_awaiting_forward') {
                    handleBroadcastForward($chat_id, $message);
                    return;
                }
                if (strpos($step, 'admin_upload_') === 0 && (isset($message->document) || isset($message->video) || isset($message->photo) || isset($message->audio) || isset($message->voice))) {
                    handleAdminUpload($user, $message);
                    return;
                }
                if ($step === 'awaiting_support_message') {
                     forwardSupportMessageToAdmin($user, $message);
                     return;
                }
                if (strpos($step, 'awaiting_password_') === 0) {
                    handlePasswordSubmission($user, $message);
                    return;
                }
                 if (strpos($step, 'admin_') === 0) {
                    handleAdminState($user, $message);
                    return;
                }

                // اگر وضعیت مشخصی وجود نداشت، پیام پیش‌فرض ارسال می‌شود
                sendMessage($chat_id, "دستور نامشخص است. لطفا از دکمه ها استفاده کنید.");
                setUserStep($user_id, 'none'); // ریست کردن وضعیت کاربر
            }
            break;
    }
}
