<?php
// src/handlers/callback_handler.php

function handle_callback_query($update, $db) {
    global $LANG;
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $callback_data = $callback_query['data'];
    $message_id = $callback_query['message']['message_id'];
    $message_text = $callback_query['message']['text'] ?? ''; // Text can be null for media

    // Always acknowledge the button press to remove the loading icon
    answerCallbackQuery($callback_query['id']);

    // --- USER-FACING CALLBACKS ---

    if (strpos($callback_data, 'buy_plan_') === 0) {
        // ... (Payment simulation logic to be added)
        answerCallbackQuery($callback_query['id'], "قابلیت خرید در حال حاضر تکمیل نشده است.", true);
    }
    elseif (strpos($callback_data, 'like_') === 0) {
        $file_code = substr($callback_data, strlen('like_'));
        handle_like_dislike($chat_id, $message_id, $user_id, $file_code, 'like');
    }
    elseif (strpos($callback_data, 'dislike_') === 0) {
        $file_code = substr($callback_data, strlen('dislike_'));
        handle_like_dislike($chat_id, $message_id, $user_id, $file_code, 'dislike');
    }
    elseif (strpos($callback_data, 'search_page_') === 0) {
        // Format: search_page_[search_by]_[page]_[query]
        $parts = explode('_', $callback_data, 5);
        if (count($parts) === 5) {
            $search_by = $parts[2];
            $page = (int)$parts[3];
            $query = $parts[4];
            display_search_results($chat_id, $search_by, $query, $page, $message_id);
        }
    }
    elseif ($callback_data === 'confirm_join') {
            // User claims they've joined the required channels. Re-check them.
            if (check_forced_join($user_id, true)) { // Pass true to show success message
                 // If successful, delete the "please join" message
                deleteMessage($chat_id, $message_id);
                // Optional: Resend the main menu or a welcome back message
                sendMessage($chat_id, "✅ عضویت شما تایید شد. اکنون می‌توانید از ربات استفاده کنید.", get_main_menu_keyboard());
            }
        }
        elseif (strpos($callback_data, 'confirm_seen_') === 0) {
            $file_code = substr($callback_data, strlen('confirm_seen_'));

            // Re-check the forced seen condition.
            // This time, if the cooldown has passed, it should return true.
            if (check_forced_seen($user_id, $file_code)) {
                // If the check passes, delete the "please see" message
                deleteMessage($chat_id, $message_id);
                // And now, actually send the file the user originally requested.
                handle_file_request($chat_id, $user_id, $file_code);
            }
        }
        elseif (strpos($callback_data, 'confirm_reaction_') === 0) {
            $file_code = substr($callback_data, strlen('confirm_reaction_'));

            if (check_forced_reaction($user_id, $file_code)) {
                deleteMessage($chat_id, $message_id);
                handle_file_request($chat_id, $user_id, $file_code);
            }
        }


    // --- ADMIN-ONLY CALLBACKS ---
    if (isAdmin($user_id, $db)) {
        if (strpos($callback_data, 'approve_file_') === 0) {
            $user_file_id = substr($callback_data, strlen('approve_file_'));
            handle_approve_file($chat_id, $message_id, $user_file_id);
        }
        elseif (strpos($callback_data, 'reject_file_') === 0) {
            $user_file_id = substr($callback_data, strlen('reject_file_'));
            handle_reject_file($chat_id, $message_id, $user_file_id);
        }
        elseif (strpos($callback_data, 'delete_file_') === 0) {
            $file_code = substr($callback_data, strlen('delete_file_'));
            handle_delete_file($chat_id, $file_code, $message_id);
        }
        elseif (strpos($callback_data, 'set_password_') === 0) {
            $file_code = substr($callback_data, strlen('set_password_'));
            handle_set_password_request($chat_id, $user_id, $file_code);
            editMessageText($chat_id, $message_id, $message_text . "\n\n⏳ " . $LANG['awaiting_password']);
        }
        elseif (strpos($callback_data, 'remove_password_') === 0) {
            $file_code = substr($callback_data, strlen('remove_password_'));
            handle_remove_password($chat_id, $file_code, $message_id); // Pass message_id to re-render menu
        }
        elseif (strpos($callback_data, 'set_limit_') === 0) {
            $file_code = substr($callback_data, strlen('set_limit_'));
            handle_set_limit_request($chat_id, $user_id, $file_code);
            editMessageText($chat_id, $message_id, $message_text . "\n\n⏳ " . $LANG['awaiting_limit']);
        }
        elseif (strpos($callback_data, 'remove_limit_') === 0) {
            $file_code = substr($callback_data, strlen('remove_limit_'));
            handle_remove_limit($chat_id, $file_code, $message_id); // Pass message_id to re-render menu
        }
        elseif (strpos($callback_data, 'delete_folder_') === 0) {
            $folder_id = substr($callback_data, strlen('delete_folder_'));
            handle_delete_folder($chat_id, $folder_id, $message_id);
        }
        elseif ($callback_data === 'set_gateway_zarinpal' || $callback_data === 'set_gateway_ziball') {
            handle_gateway_selection($chat_id, $message_id, $callback_data);
        }
        elseif (strpos($callback_data, 'manage_sub_') === 0) {
            $sub_number = (int)substr($callback_data, strlen('manage_sub_'));
            show_subscription_management($chat_id, $sub_number, $message_id);
        }
        elseif (preg_match('/^edit(name|duration|price)sub_(\\d+)$/', $callback_data, $matches)) {
            $field = $matches[1];
            $sub_number = (int)$matches[2];
            handle_edit_subscription_request($chat_id, $user_id, $sub_number, $field);
        }
        elseif (strpos($callback_data, 'delete_channel_') === 0) {
            handle_delete_channel($chat_id, $message_id, $callback_data);
            answerCallbackQuery($callback_query['id'], $LANG['channel_deleted']);
        }
        elseif (strpos($callback_data, 'view_ad_') === 0) {
            $ad_id = (int)substr($callback_data, strlen('view_ad_'));
            handle_view_ad($chat_id, $ad_id);
        }
        elseif (strpos($callback_data, 'delete_ad_') === 0) {
            $ad_id = (int)substr($callback_data, strlen('delete_ad_'));
            handle_delete_ad($chat_id, $ad_id, $message_id);
        }
        elseif (strpos($callback_data, 'edit_files_') === 0) {
            $file_code = substr($callback_data, strlen('edit_files_'));
            handle_start_editing_session($chat_id, $user_id, $file_code);
        }

    }
}
