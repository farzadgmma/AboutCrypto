<?php
// src/handlers/ads_handler.php

// ... (previous code) ...


/**
 * لیست تبلیغات را به صورت صفحه‌بندی شده نمایش می‌دهد.
 *
 * @param PDO $pdo
 * @param int $chat_id
 * @param int $message_id
 * @param int $page
 */
function show_ads_list($pdo, $chat_id, $message_id, $page = 0) {
    $ads_per_page = 5;
    $offset = $page * $ads_per_page;

    // دریافت تعداد کل تبلیغات برای صفحه‌بندی
    $total_ads = $pdo->query("SELECT COUNT(*) FROM ads")->fetchColumn();
    $total_pages = ceil($total_ads / $ads_per_page);

    // دریافت تبلیغات برای صفحه فعلی
    $stmt = $pdo->prepare("SELECT id, ad_type, caption FROM ads ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $ads_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $text = "🚧 <b>لیست تبلیغات (صفحه " . ($page + 1) . " از {$total_pages})</b>\n\n";
    $keyboard = [];

    if (empty($ads)) {
        $text = "❌ هیچ تبلیغی برای نمایش وجود ندارد.";
    } else {
        foreach ($ads as $ad) {
            $caption_preview = mb_substr($ad['caption'] ?? 'بدون کپشن', 0, 20) . '...';
            $keyboard[] = [
                ['text' => "#{$ad['id']} - {$ad['ad_type']}", 'callback_data' => 'ads_view_' . $ad['id']],
                ['text' => $caption_preview, 'callback_data' => 'ads_view_' . $ad['id']],
                ['text' => '🗑 حذف', 'callback_data' => 'ads_delete_' . $ad['id']]
            ];
        }
    }

    // ساخت دکمه‌های ناوبری (صفحه‌بندی)
    $navigation_buttons = [];
    if ($page > 0) {
        $navigation_buttons[] = ['text' => '⬅️ قبلی', 'callback_data' => 'ads_list_' . ($page - 1)];
    }
    if (($page + 1) < $total_pages) {
        $navigation_buttons[] = ['text' => 'بعدی ➡️', 'callback_data' => 'ads_list_' . ($page + 1)];
    }
    if (!empty($navigation_buttons)) {
        $keyboard[] = $navigation_buttons;
    }

    $keyboard[] = [['text' => '🔙 بازگشت به منوی تبلیغات', 'callback_data' => 'ads_back_to_menu']];

    edit_message_text($chat_id, $message_id, $text, json_encode(['inline_keyboard' => $keyboard]));
}
