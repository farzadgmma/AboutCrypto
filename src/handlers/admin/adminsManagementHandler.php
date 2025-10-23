<?php
// src/handlers/admin/adminsManagementHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showAdminManagementPanel($chat_id) {
    $keyboard = [
        [['text' => "➕ افزودن ادمین"]],
        [['text' => "🔙 منوی پنل"], ['text' => "👥 لیست ادمین ها"]]
    ];

    sendMessage($chat_id, "❗️ به بخش مدیریت ادمین های ربات خوش آمدید.\n\n🔻 یکی از گزینه های زیر را انتخاب کنید.", ['keyboard' => $keyboard, 'resize_keyboard' => true]);
}

function askForAdminId($chat_id) {
    setUserStep($chat_id, 'admin_awaiting_add_admin_id');
    sendMessage($chat_id, "✔️ ایدی عددی ادمین را وارد کنید", ['keyboard' => [[['text' => '🔙 منوی پنل']]], 'resize_keyboard' => true]);
}

function addAdmin($admin_chat_id, $target_user_id) {
    $db = Database::getInstance();

    // Check if the target user exists in the main user table
    $user_data = $db->fetch("SELECT id, name FROM user WHERE id = ?", [$target_user_id]);
    if (!$user_data) {
        sendMessage($admin_chat_id, "❌ کاربر با این آیدی یافت نشد. لطفا ابتدا مطمئن شوید کاربر ربات را استارت کرده است.");
        return;
    }

    // Check if the user is already an admin
    if (isAdmin($target_user_id)) {
        sendMessage($admin_chat_id, "⚠️ این کاربر از قبل ادمین بوده است.");
        return;
    }

    // Add the user to the admins table
    $db->execute("INSERT INTO admins (idadmin, nameadmin) VALUES (?, ?)", [$user_data['id'], $user_data['name']]);

    sendMessage($admin_chat_id, "✅ کاربر <b>" . htmlspecialchars($user_data['name']) . "</b> (<code>{$user_data['id']}</code>) با موفقیت به لیست ادمین‌ها اضافه شد.");
    sendMessage($target_user_id, "🎉 تبریک! شما توسط مدیریت به عنوان ادمین جدید ربات انتخاب شدید.");

    setUserStep($admin_chat_id, 'none');
}

function listAdmins($chat_id, $message_id = null) {
    global $admins; // The primary admin from config.php
    $db = Database::getInstance();

    $secondary_admins = $db->fetchAll("SELECT idadmin, nameadmin FROM admins");

    if (empty($secondary_admins) && count($admins) <= 1) {
        $text = "❌ به جز شما، ادمین دیگری وجود ندارد.";
        if ($message_id) {
            editMessageText($chat_id, $message_id, $text);
        } else {
            sendMessage($chat_id, $text);
        }
        return;
    }

    $keyboard = [[['text' => "👤 ایدی ادمین 👤", 'callback_data' => 'none'], ['text' => "❌ حذف", 'callback_data' => 'none']]];

    // Add primary admin(s) from config file (they cannot be deleted via the bot)
    foreach ($admins as $admin_id) {
        // Don't show the current admin themselves in the list to delete.
        if ($admin_id != $chat_id) {
            $keyboard[] = [['text' => "{$admin_id} (اصلی)", 'callback_data' => 'none'], ['text' => "🔒", 'callback_data' => 'none']];
        }
    }

    // Add other admins from the database
    foreach ($secondary_admins as $admin) {
        $keyboard[] = [['text' => $admin['idadmin'], 'callback_data' => 'none'], ['text' => "❌", 'callback_data' => "admin_admin_deleteconfirm_{$admin['idadmin']}"]];
    }

    $text = "👇🏻 لیست تمام ادمین های ربات:";
    if ($message_id) {
        editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
    } else {
        sendMessage($chat_id, $text, ['inline_keyboard' => $keyboard]);
    }
}

function confirmDeleteAdmin($chat_id, $message_id, $admin_id_to_delete) {
    $keyboard = [
        [['text' => "✅ بله، حذف کن", 'callback_data' => "admin_admin_delete_{$admin_id_to_delete}"], ['text' => "❌ لغو", 'callback_data' => "admin_admin_list"]]
    ];
    editMessageText($chat_id, $message_id, "آیا از حذف ادمین <code>{$admin_id_to_delete}</code> مطمئن هستید؟", ['inline_keyboard' => $keyboard]);
}


function deleteAdmin($admin_chat_id, $message_id, $admin_id_to_delete) {
    $db = Database::getInstance();
    $db->execute("DELETE FROM admins WHERE idadmin = ?", [$admin_id_to_delete]);

    answerCallbackQuery($GLOBALS['update']->callback_query->id, "✅ ادمین با موفقیت حذف شد.", false);

    // Let the removed admin know
    sendMessage($admin_id_to_delete, "شما از لیست ادمین‌های ربات حذف شدید.");

    // Refresh the list
    listAdmins($admin_chat_id, $message_id);
}
