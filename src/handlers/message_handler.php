<?php
// src/handlers/message_handler.php

// Include all handler dependencies
require_once 'start_handler.php';
require_once 'upload_handler.php';
require_once 'search_handler.php';
require_once 'admin_handler.php';
require_once 'folder_handler.php';
require_once 'stats_handler.php';
require_once 'broadcast_handler.php';
require_once 'subscription_handler.php';
require_once 'payment_handler.php';
require_once 'media_handler.php';
require_once 'user_handler.php';
require_once 'list_handler.php';
require_once 'forced_interaction_handler.php';
require_once 'advertisement_handler.php';

function handle_message($update, $db) {
    global $LANG;
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $first_name = $message['from']['first_name'];
    $text = $message['text'] ?? null;

    // Get user's current step and folder ID from the database
    $user_step = 'none';
    $current_folder_id = null;
    $stmt = $db->executeQuery("SELECT step, current_folder_id FROM users WHERE id = ?", [$user_id]);
    if ($stmt->rowCount() > 0) {
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_step = $user_data['step'];
        $current_folder_id = $user_data['current_folder_id'];
    }

    // 1. Handle step-based logic first (awaiting input)
    if (strpos($user_step, 'awaiting_') === 0) {
        if ($user_step === 'awaiting_file') {
            handle_received_file($message);
            return;
        }
        if (strpos($user_step, 'awaiting_search_query_') === 0) {
            $search_by = substr($user_step, strlen('awaiting_search_query_'));
            handle_search_query($chat_id, $user_id, $search_by, $text);
            return;
        }

        // --- Admin-specific step handling ---
        if (isAdmin($user_id, $db)) {
            if ($user_step === 'awaiting_admin_file') { handle_admin_file_received($message); return; }
            if ($user_step === 'awaiting_channel_forward') { handle_channel_forward_received($chat_id, $user_id, $message); return; }
            if ($user_step === 'awaiting_folder_name') { handle_folder_name_received($chat_id, $user_id, $text); return; }
            if (strpos($user_step, 'awaiting_folder_codes:') === 0) { $folder_name = substr($user_step, 24); handle_folder_codes_received($chat_id, $user_id, $folder_name, $text); return; }
            if ($user_step === 'awaiting_broadcast_message') { execute_broadcast($chat_id, $message); return; } // Pass the whole message
            if ($user_step === 'awaiting_file_code_for_management') {
                if ($text === $LANG['back_to_admin_panel']) {
                    handle_admin_panel($chat_id);
                } else {
                    display_media_management_menu($chat_id, $text);
                }
                return;
            }
            if (strpos($user_step, 'awaiting_password_for_') === 0) { $file_code = substr($user_step, 22); handle_password_received($chat_id, $user_id, $file_code, $text); return; }
            if (strpos($user_step, 'awaiting_limit_for_') === 0) { $file_code = substr($user_step, 19); handle_limit_received($chat_id, $user_id, $file_code, $text); return; }
            if ($user_step === 'awaiting_free_download_limit') { handle_free_download_limit_received($chat_id, $user_id, $text); return; }
            if ($user_step === 'awaiting_zarinpal_merchant_id') { handle_merchant_id_received($chat_id, $user_id, 'zarinpal', $text); return; }
            if ($user_step === 'awaiting_ziball_merchant_id') { handle_merchant_id_received($chat_id, $user_id, 'ziball', $text); return; }
            if ($user_step === 'awaiting_subscription_text') { handle_subscription_text_received($chat_id, $user_id, $text); return; }
            if (preg_match('/^awaiting_sub_(name|duration|price)_(\\d+)$/', $user_step, $matches)) {
                $field = $matches[1];
                $sub_number = (int)$matches[2];
                handle_edit_subscription_received($chat_id, $user_id, $sub_number, $field, $text);
                return;
            }
            if ($user_step === 'awaiting_join_text') { handle_join_text_received($chat_id, $user_id, $text); return; }
            if ($user_step === 'awaiting_ad_position') { handle_ad_position_received($chat_id, $user_id, $text); return; }
            if ($user_step === 'awaiting_ad_content') { handle_ad_content_received($chat_id, $user_id, $message); return; }
            if ($user_step === 'awaiting_search_by_code') {
                if ($text === $LANG['back_to_search_menu']) {
                    promptForSearchType($chat_id, $user_id);
                } else {
                    handle_search_by_code($chat_id, $user_id, $text);
                }
                return;
            }
            if ($user_step === 'awaiting_search_by_caption') {
                 if ($text === $LANG['back_to_search_menu']) {
                    promptForSearchType($chat_id, $user_id);
                } else {
                    handle_search_by_caption($chat_id, $user_id, $text);
                }
                return;
            }
             if ($user_step === 'awaiting_search_type_selection') {
                switch ($text) {
                    case $LANG['search_by_code']:
                        $db->executeQuery("UPDATE users SET step = 'awaiting_search_by_code' WHERE id = ?", [$user_id]);
                        sendMessage($chat_id, "لطفاً کد فایل مورد نظر را وارد کنید:", get_back_to_search_menu_keyboard());
                        break;
                    case $LANG['search_by_caption']:
                        $db->executeQuery("UPDATE users SET step = 'awaiting_search_by_caption' WHERE id = ?", [$user_id]);
                        sendMessage($chat_id, "لطفاً بخشی از کپشن فایل را وارد کنید:", get_back_to_search_menu_keyboard());
                        break;
                    case $LANG['search_by_file_type']:
                        sendMessage($chat_id, "این قابلیت به زودی اضافه می‌شود.");
                        break;
                    case $LANG['search_by_thumbnail_text']:
                        sendMessage($chat_id, "این قابلیت به زودی اضافه می‌شود.");
                        break;
                    case $LANG['back_to_media_management']:
                        handle_media_management($chat_id);
                        break;
                    default:
                        sendMessage($chat_id, "لطفا یک گزینه را از منو انتخاب کنید.");
                        break;
                }
                 return;
            }

        }
        // If no specific step handler is found, maybe it's a simple back button
         if ($text === $LANG['back_to_admin_panel']) {
            handle_admin_panel($chat_id);
            return;
        }

        // --- Handle replies during file editing ---
        if (strpos($user_step, 'editing_files_') === 0 && isset($message['reply_to_message'])) {
            handle_file_edit($user_id, $message);
            return;
        }

    }

    // 2. Handle commands (e.g., /start)
    if ($text && strpos($text, '/') === 0) {
        // Pass the full text to handle potential deep linking like /start dl_xxxx
        handle_start($chat_id, $user_id, $first_name, $db, $text);
        return;
    }

    // 3. Handle keyboard button presses (main menu etc.)
    if ($text) {
        // --- User Buttons ---
        if ($text === $LANG['account']) { handle_account_button($chat_id, $user_id); return; }
        if ($text === $LANG['buy_subscription']) { handle_subscription_button($chat_id, $user_id); return; }
        if ($text === $LANG['upload_file_user']) { handle_upload_button($chat_id, $user_id); return; }
        if ($text === $LANG['top_downloads']) { handle_top_downloads_button($chat_id); return; }
        if ($text === $LANG['newest_files']) { handle_newest_files_button($chat_id); return; }
        if ($text === $LANG['most_liked']) { handle_most_liked_button($chat_id); return; }
        if ($text === $LANG['back_to_main_menu']) { handle_start($chat_id, $user_id, $first_name, $db); return; }
        if ($text === $LANG['search_media_user']) { handle_user_search_type_selection($chat_id, $user_id); return; }
        if ($text === $LANG['search_by_name_caption_user']) {
            $db->executeQuery("UPDATE users SET step = 'awaiting_search_query_caption' WHERE id = ?", [$user_id]);
            sendMessage($chat_id, "لطفاً نام یا بخشی از کپشن فایل را وارد کنید:", get_back_to_main_menu_keyboard());
            return;
        }
         if ($text === $LANG['search_by_code_user']) {
            $db->executeQuery("UPDATE users SET step = 'awaiting_search_query_code' WHERE id = ?", [$user_id]);
            sendMessage($chat_id, "لطفاً کد فایل را وارد کنید:", get_back_to_main_menu_keyboard());
            return;
        }

        // --- Folder Navigation ---
        if (strpos($text, '📁 ') === 0) {
            // First, check if user needs to join channels
            if (!check_forced_join($user_id)) {
                return; // Stop processing if join is required
            }
            handle_folder_click($chat_id, $user_id, $text, $current_folder_id);
            return;
        }

        // --- Admin Buttons ---
        if (isAdmin($user_id, $db)) {
            $pending_button_text = $LANG['pending_files'];
            $stmt = $db->executeQuery("SELECT COUNT(*) as count FROM user_files WHERE status = 'pending'");
            $pending_count_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pending_count_data && $pending_count_data['count'] > 0) {
                $pending_button_text .= ' (' . $pending_count_data['count'] . ')';
            }

            switch ($text) {
                case $LANG['admin_panel']: handle_admin_panel($chat_id); return;
                case $LANG['admin_upload']: handle_admin_upload_request($chat_id, $user_id); return;
                case $pending_button_text: handle_pending_files_request($chat_id); return;
                case $LANG['media_management']: handle_media_management($chat_id); return;
                case $LANG['search_media_admin']: promptForSearchType($chat_id, $user_id); return;
                case $LANG['folder_management']: handle_folder_management($chat_id); return;
                case $LANG['payment_settings']: handle_payment_settings($chat_id); return;
                case $LANG['create_folder']: handle_create_folder_request($chat_id, $user_id); return;
                case $LANG['bot_stats']: handle_stats_request($chat_id); return;
                case $LANG['broadcast']: handle_broadcast_request($chat_id, $user_id); return;
                case $LANG['back_to_admin_panel']: handle_admin_panel($chat_id); return;
                case $LANG['gateway_settings']: handle_gateway_settings_request($chat_id); return;
                case $LANG['free_download_count']: handle_free_download_settings_request($chat_id, $user_id); return;
                case $LANG['set_zarinpal_merchant']: handle_merchant_id_request($chat_id, $user_id, 'zarinpal'); return;
                case $LANG['set_ziball_merchant']: handle_merchant_id_request($chat_id, $user_id, 'ziball'); return;
                case $LANG['change_subscription_text']: handle_subscription_text_request($chat_id, $user_id); return;
                case $LANG['manage_subscriptions']: show_subscription_management($chat_id, 1); return;
                case $LANG['forced_interaction_settings']: handle_forced_interaction_settings($chat_id); return;
                case $LANG['advertisement_settings']: handle_advertisement_settings($chat_id); return;
                case $LANG['forced_join_management']: handle_forced_join_management($chat_id); return;
                case $LANG['forced_view_management']: handle_forced_seen_management($chat_id); return;
                case $LANG['forced_reaction_management']: handle_forced_reaction_management($chat_id); return;
                case $LANG['back_to_interaction_settings']: handle_forced_interaction_settings($chat_id); return;
                case $LANG['list_channels']: handle_list_channels($chat_id); return;
                case $LANG['add_channel']: handle_add_channel_request($chat_id, $user_id); return;
                case $LANG['toggle_ads']: handle_toggle_ads($chat_id); return;
                case $LANG['ad_position']: handle_ad_position_request($chat_id, $user_id); return;
                case $LANG['add_ad']: handle_add_ad_request($chat_id, $user_id); return;
                case $LANG['list_ads']: handle_list_ads_request($chat_id); return;
            }

            if (strpos($text, $LANG['toggle_forced_join_status']) === 0) {
                handle_toggle_forced_join($chat_id);
                return;
            }
            if ($text === $LANG['change_join_text']) {
                handle_change_join_text_request($chat_id, $user_id);
                return;
            }
            // --- In-Edit Mode Buttons ---
            if (strpos($user_step, 'editing_files_') === 0) {
                if ($text === '✅ اعمال تغییرات') {
                    $file_code = substr($user_step, strlen('editing_files_'));
                    handle_apply_edits($chat_id, $user_id, $file_code);
                } elseif ($text === '🔙 لغو ویرایش') {
                    handle_cancel_edits($chat_id, $user_id);
                }
                return; // Prevent further processing
            }
        }

        // If no button is matched, it might be an unhandled state or a casual message.
        // For a structured bot, it's often best to guide the user back.
        // sendMessage($chat_id, "لطفاً از دکمه‌های منو استفاده کنید.");
        return;
    }

    // 4. Handle other message types (photo, video, etc.) if they are not part of a step
    // This is where you'd put logic if a user sends a file *without* first pressing the upload button.
    // For this bot, we require the user to be in an 'awaiting_file' step, so this part can be minimal.
    if (!$text) {
         sendMessage($chat_id, "برای ارسال فایل، لطفاً ابتدا دکمه مربوطه را فشار دهید.");
    }
}
