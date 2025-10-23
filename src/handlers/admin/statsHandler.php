<?php
// src/handlers/admin/statsHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function showStats($chat_id) {
    $db = Database::getInstance();

    $total_users = $db->fetch("SELECT COUNT(*) as count FROM user")['count'];
    $total_files = $db->fetch("SELECT COUNT(DISTINCT code) as count FROM files")['count'];
    $total_dl = $db->fetch("SELECT SUM(dl) as count FROM files")['count'] ?? 0;
    $active_subs = $db->fetch("SELECT COUNT(*) as count FROM user WHERE vip = 'yes'")['count'];

    $settings = $db->fetch("SELECT bot_mode FROM settings LIMIT 1");
    $bot_status = ($settings['bot_mode'] === 'on') ? "✅ روشن" : "❌ خاموش";

    $text = "📊 <b>آمار کلی ربات:</b>\n\n" .
            "👥 تعداد کل کاربران ربات: {$total_users}\n" .
            "▫️ کل رسانه های آپلود شده: <code>{$total_files}</code>\n" .
            "📥 تعداد کل دانلودها: <code>{$total_dl}</code>\n" .
            "💎 کل اشتراک های فعال: <code>{$active_subs}</code>\n\n" .
            "🔘 وضعیت ربات: {$bot_status}";

    sendMessage($chat_id, $text);
}
