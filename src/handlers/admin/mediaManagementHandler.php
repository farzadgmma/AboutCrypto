<?php
// src/handlers/admin/mediaManagementHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function handleMediaCallback($chat_id, $message_id, $action, $payload) {
    switch ($action) {
        case 'list':
            // The payload is the page number
            $page = (int)$payload;
            // Edit the existing message to show the new page
            editMessageText($chat_id, $message_id, "در حال بارگیری صفحه {$page}...", listAllMedia($chat_id, $page, $message_id));
            break;
        case 'info':
            // The payload is the file code
            showFileInfo($chat_id, $payload);
            break;
        case 'deleteallconfirm':
            // Show a confirmation before deleting all files
            $keyboard = [
                [['text' => "✅ بله، همه را حذف کن", 'callback_data' => "admin_media_deleteallnow"], ['text' => "❌ لغو", 'callback_data' => "admin_media_list_1"]]
            ];
            editMessageText($chat_id, $message_id, "⚠️ آیا از حذف تمام رسانه‌ها و پوشه‌ها مطمئن هستید؟ این عمل غیرقابل بازگشت است.", ['inline_keyboard' => $keyboard]);
            break;
        case 'deleteallnow':
            // Delete all files and folders
            $db = Database::getInstance();
            $db->execute("DELETE FROM files");
            $db->execute("DELETE FROM folders");
            editMessageText($chat_id, $message_id, "✅ تمام رسانه‌ها و پوشه‌ها با موفقیت حذف شدند.");
            break;
    }
}


/**
 * Displays the main media management menu.
 */
function showMediaManagementPanel($chat_id) {
    $keyboard = [
        [['text' => "🔐 تنظیم پسورد"], ['text' => "📥 محدودیت دانلود"]],
        [['text' => "🔏 قفل فروارد"], ['text' => "📢 قفل کانال"], ['text' => "🔥 ضد فیلتر"]],
        [['text' => "📛 تایم حذف"], ['text' => "❎ حذف رسانه"]],
        [['text' => "➖➖➖➖➖"]],
        [['text' => "💬 تنظیم امضا کانال"], ['text' => "📣 تنظیم کانال رسانه"]],
        [['text' => "🌠 فعال/غیرفعال سازی تامنیل"], ['text' => "🚫 فعال/غیرفعال سازی حذف لینک"]],
        [['text' => "🗯 تنظیم امضا دانلود"]],
        [['text' => "🗂 تمام رسانه ها"], ['text' => "🔍 اطلاعات رسانه"]],
        [['text' => "➖➖➖➖➖"]],
        [['text' => "📥 تنظیم دانلود فیک"], ['text' => "👍🏻 تنظیم لایک فیک"]],
        [['text' => "🔙 منوی پنل"]]
    ];

    sendMessage($chat_id, "<b>🗂️ مدیریت رسانه</b>\n\nلطفا یک گزینه را انتخاب کنید:", ['keyboard' => $keyboard, 'resize_keyboard' => true]);
}

/**
 * Handles the "List All Media" command, displaying a paginated list of files.
 */
function listAllMedia($chat_id, $page = 1, $message_id = null) {
    $db = Database::getInstance();
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Get the total count of unique files.
    $total_files = $db->fetch("SELECT COUNT(DISTINCT code) as count FROM files")['count'];
    $total_pages = ceil($total_files / $per_page);

    // Fetch the files for the current page.
    $files = $db->fetchAll("SELECT code, type, dl FROM files GROUP BY code ORDER BY file DESC LIMIT ? OFFSET ?", [$per_page, $offset]);

    if (empty($files)) {
        $text = "❌ هیچ رسانه‌ای آپلود نشده است.";
        if ($message_id) {
            editMessageText($chat_id, $message_id, $text);
        } else {
            sendMessage($chat_id, $text);
        }
        return;
    }

    $keyboard = [];
    foreach ($files as $file) {
        $type_fa = doc($file['type']);
        $button_text = "🌀 کد: {$file['code']} | 🔖 نوع: {$type_fa} | 📥 دانلود: {$file['dl']}";
        $keyboard[] = [['text' => $button_text, 'callback_data' => "admin_media_info_{$file['code']}"]];
    }

    // Pagination buttons
    $pagination_row = [];
    if ($page > 1) {
        $pagination_row[] = ['text' => "⬅️ صفحه قبلی", 'callback_data' => "admin_media_list_" . ($page - 1)];
    }
    if ($page < $total_pages) {
        $pagination_row[] = ['text' => "صفحه بعدی ➡️", 'callback_data' => "admin_media_list_" . ($page + 1)];
    }
    if (!empty($pagination_row)) {
        $keyboard[] = $pagination_row;
    }

    $keyboard[] = [['text' => "🗑️ حذف تمام رسانه ها", 'callback_data' => "admin_media_deleteallconfirm"]];

    $message_text = "🗂 <b>تعداد کل رسانه‌ها:</b> {$total_files}\n📋 صفحه: {$page} از {$total_pages}\n\nبرای مشاهده اطلاعات هر رسانه، روی آن کلیک کنید.";

    if ($message_id) {
        editMessageText($chat_id, $message_id, $message_text, ['inline_keyboard' => $keyboard]);
    } else {
        sendMessage($chat_id, $message_text, ['inline_keyboard' => $keyboard]);
    }
}

function showFileInfo($chat_id, $file_code) {
    $db = Database::getInstance();
    $file = $db->fetch("SELECT * FROM files WHERE code = ? LIMIT 1", [$file_code]);

    if (!$file) {
        sendMessage($chat_id, "❌ رسانه‌ای با این کد یافت نشد.");
        return;
    }

    $yorn = $file['msg_id'] !== 'none' ? "✅ ارسال شده" : "❌ ارسال نشده";
    $khikhi = $file['msg_id'] !== 'none' ? "✅ ارسال شده" : "📢 ارسال به کانال";
    $khidata = $file['msg_id'] !== 'none' ? 'none' : "admin_media_sendchannel_{$file_code}";

    $ispass = $file['pass'] === 'none' ? "❌ بدون رمز" : "<code>{$file['pass']}</code>";
    $ismahd = $file['mahdodl'] === 'none' ? "❌ بدون محدودیت" : "{$file['mahdodl']} دانلود";

    $hesofff = $file['zd_filter'] === 'on' ? "✅" : "❌";
    $fwlock2 = $file['fwlock'] === 'on' ? "✅" : "❌";
    $hesofff2 = $file['ghfl_ch'] === 'on' ? "✅" : "❌";

    $file_type = doc($file['type']);
    $zaman_parts = explode('-', $file['zaman']);
    $zaman = $zaman_parts[0]; // Just the date part for display

    $text = "🔍 <b>اطلاعات رسانه با کد:</b> <code>{$file_code}</code>\n\n" .
            "▪️ نوع فایل: <b>{$file_type}</b>\n" .
            "▪️ حجم: <b>{$file['file_size']}</b>\n\n" .
            "📥 تعداد دانلود: <code>{$file['dl']}</code>\n\n" .
            "🔘 ارسال به کانال: {$yorn}\n" .
            "🔘 پسورد: {$ispass}\n" .
            "🔘 محدودیت دانلود: {$ismahd}\n" .
            "🔘 ضد فیلتر: {$hesofff}\n" .
            "🔘 ضد فروارد: {$fwlock2}\n" .
            "🔘 قفل کانال: {$hesofff2}\n\n" .
            "▪️ آپلود شده توسط: <a href='tg://user?id={$file['id']}'>{$file['id']}</a>\n" .
            "🗓 زمان آپلود: <b>{$zaman}</b>";

    $keyboard = [
        [['text' => "🔗 لینک دریافت", 'url' => "https://t.me/" . BOT_USERNAME . "?start=dl_{$file_code}"]],
        [['text' => $khikhi, 'callback_data' => $khidata], ['text' => "قفل فروارد: {$fwlock2}", 'callback_data' => "admin_media_togglefw_{$file_code}"]],
        [['text' => "📥 محدودیت دانلود", 'callback_data' => "admin_media_setlimit_{$file_code}"], ['text' => "🔐 تنظیم رمزعبور", 'callback_data' => "admin_media_setpass_{$file_code}"]],
        [['text' => "ضدفیلتر: {$hesofff}", 'callback_data' => "admin_media_togglefilter_{$file_code}"], ['text' => "قفل کانال: {$hesofff2}", 'callback_data' => "admin_media_togglechannel_{$file_code}"]],
        [['text' => "🔗 تنظیم لینک اختصاصی", 'callback_data' => "admin_media_setcode_{$file_code}"], ['text' => "🗑 حذف فایل", 'callback_data' => "admin_media_deleteconfirm_{$file_code}"]],
        [['text' => "🔙 بازگشت به لیست", 'callback_data' => 'admin_media_list_1']]
    ];

    sendMessage($chat_id, $text, ['inline_keyboard' => $keyboard]);
}


// Helper function to convert file type to Persian.
function doc($name) {
    $types = [
        "document" => "سند", "video" => "ویدیو", "photo" => "عکس",
        "voice" => "ویس", "audio" => "موزیک", "sticker" => "استیکر",
    ];
    return $types[$name] ?? 'ناشناخته';
}
