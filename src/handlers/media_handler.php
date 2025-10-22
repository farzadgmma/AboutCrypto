<?php
// src/handlers/media_handler.php

function handle_admin_upload_request($chat_id, $user_id) {
    $db = new Database();
    $db->executeQuery("UPDATE users SET step = 'awaiting_admin_file' WHERE id = ?", [$user_id]);
    sendMessage($chat_id, "لطفاً فایل یا رسانه مورد نظر خود را برای آپلود ارسال کنید...");
}

function handle_admin_file_received($message) {
    $db = new Database();
    $user_id = $message['from']['id'];
    $chat_id = $message['chat']['id'];

    $file_id = null;
    $file_type = null;
    $caption = $message['caption'] ?? '';

    // Extract file info from message
    if (isset($message['photo'])) {
        $file_id = $message['photo'][count($message['photo']) - 1]['file_id'];
        $file_type = 'photo';
    } elseif (isset($message['video'])) {
        $file_id = $message['video']['file_id'];
        $file_type = 'video';
    } elseif (isset($message['document'])) {
        $file_id = $message['document']['file_id'];
        $file_type = 'document';
    } elseif (isset($message['audio'])) {
        $file_id = $message['audio']['file_id'];
        $file_type = 'audio';
    } elseif (isset($message['voice'])) {
        $file_id = $message['voice']['file_id'];
        $file_type = 'voice';
    }

    if (!$file_id) {
        sendMessage($chat_id, "نوع فایل ارسال شده پشتیبانی نمی‌شود.");
        return;
    }

    // Generate a unique code for the file
    $file_code = generateUniqueFileCode($db);

    // TODO: Add setting to remove links from caption
    // $caption = preg_replace('/(https?:\/\/[^\s]+)/', '', $caption);

    // Save file to the database
    $query = "INSERT INTO files (code, file_id, file_type, caption, uploader_id) VALUES (?, ?, ?, ?, ?)";
    $db->executeQuery($query, [$file_code, $file_id, $file_type, $caption, $user_id]);

    // Reset admin's step
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);

    display_media_management_menu($chat_id, $file_code);
}


function display_media_management_menu($chat_id, $file_code, $message_id = null) {
    $db = new Database();
    $stmt = $db->executeQuery("SELECT * FROM files WHERE code = ?", [$file_code]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        $error_text = "خطا: رسانه‌ای با کد `$file_code` یافت نشد.";
        if ($message_id) {
            editMessageText($chat_id, $message_id, $error_text);
        } else {
            sendMessage($chat_id, $error_text);
        }
        return;
    }

    // Prepare details for the message
    $upload_date = date("Y/m/d - H:i:s", strtotime($file['created_at']));
    $uploader_id = $file['uploader_id'];
    $download_link = "https://t.me/" . BOT_USERNAME . "?start=dl_" . $file_code;

    $message_text = "✅ فایل با موفقیت آپلود شد.\n\n" .
                    "▪️ کد رسانه : `" . $file_code . "`\n" .
                    "▪️ زمان آپلود : " . $upload_date . "\n" .
                    "👤 توسط : " . $uploader_id . "\n\n" .
                    "🔗 لینک دانلود : " . $download_link . "\n\n" .
                    "🔻 برای ویرایش رسانه، یکی از دکمه های زیر را انتخاب کنید:";

    // Prepare buttons for the inline keyboard
    $keyboard = [
        [['text' => "🔗 لینک دریافت و مشاهده فایل", 'url' => $download_link]],
        [
            ['text' => "ارسال به کانال 📢", 'callback_data' => "send_to_channel_" . $file_code],
            ['text' => ($file['forward_lock'] ?? false) ? "قفل فروارد ✅" : "قفل فروارد ❌", 'callback_data' => "toggle_forward_lock_" . $file_code]
        ],
        [
            ['text' => "📥 تنظیم محدودیت دانلود", 'callback_data' => "set_limit_" . $file_code],
            ['text' => "🔐 تنظیم رمزعبور", 'callback_data' => "set_password_" . $file_code]
        ],
        [
            ['text' => ($file['is_filtered'] ?? false) ? "ضدفیلتر ✅" : "ضدفیلتر ❌", 'callback_data' => "toggle_filter_" . $file_code],
            ['text' => ($file['channel_lock'] ?? true) ? "قفل کانال ✅" : "قفل کانال ❌", 'callback_data' => "toggle_channel_lock_" . $file_code]
        ],
        [
            ['text' => "🔗 تنظیم لینک اختصاصی", 'callback_data' => "set_custom_link_" . $file_code],
            ['text' => "🗑 حذف فایل", 'callback_data' => "delete_file_" . $file_code]
        ],
        [
            ['text' => "✏️ ویرایش فایل ها", 'callback_data' => "edit_files_" . $file_code]
        ]
    ];

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);

    if ($message_id) {
        editMessageText($chat_id, $message_id, $message_text, $reply_markup, 'Markdown');
    } else {
        sendMessage($chat_id, $message_text, $reply_markup, 'Markdown');
    }
}


function handle_start_editing_session($chat_id, $user_id, $file_code) {
    global $db, $LANG;

    // 1. Set user state to editing
    $db->executeQuery("UPDATE users SET step = ? WHERE id = ?", ['editing_files_' . $file_code, $user_id]);

    // 2. Send instructions and the "Apply Changes" button
    $keyboard = [
        'keyboard' => [
            [['text' => '✅ اعمال تغییرات']],
            [['text' => '🔙 لغو ویرایش']]
        ],
        'resize_keyboard' => true
    ];
    $instructions = "شما وارد حالت ویرایش برای مجموعه فایل با کد `{$file_code}` شدید.\n\n";
    $instructions .= "اکنون فایل‌های این مجموعه برای شما ارسال می‌شود. برای ویرایش:\n\n";
    $instructions .= "1. **برای تغییر کپشن:** روی فایل مورد نظر ریپلای (Reply) کرده و متن جدید را بنویسید.\n";
    $instructions .= "2. **برای جایگزینی فایل:** روی فایل مورد نظر ریپلای کرده و فایل (عکس، ویدیو، سند...) جدید را ارسال کنید.\n";
    $instructions .= "3. **برای حذف یک فایل:** روی فایل مورد نظر ریپلای کرده و دستور `/delete` را ارسال کنید.\n\n";
    $instructions .= "پس از اتمام کار، دکمه **'اعمال تغییرات'** را بزنید.";
    sendMessage($chat_id, $instructions, json_encode($keyboard), "Markdown");

    // 3. Resend all files for the admin to see and reply to
    $stmt = $db->executeQuery("SELECT * FROM files WHERE code = ? ORDER BY id ASC", [$file_code]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($files)) {
        sendMessage($chat_id, "هیچ فایلی برای ویرایش در این مجموعه یافت نشد.");
        // Reset user state and show admin panel
        $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
        handle_admin_panel($chat_id);
        return;
    }

    foreach ($files as $file) {
        // We add the unique DB `id` of the file to the caption.
        // We make it "invisible" using a zero-width character and markdown link.
        // This is crucial for identifying which exact file the admin replies to.
        $invisible_marker = "[\xE2\x80\x8B](tg://file_id/" . $file['id'] . ")";
        $caption_with_marker = ($file['caption'] ?? '') . $invisible_marker;

        // Use a generic sender function to send the file back to the admin
        send_file_by_type(
            $chat_id,
            $file['file_type'],
            $file['file_id'],
            $caption_with_marker,
            'Markdown' // Important to parse the invisible link
        );
         // Small delay to ensure messages are sent in the correct order
        usleep(300000); // 300ms delay
    }
}

function handle_file_edit($user_id, $message) {
    global $db;
    $reply_to_message = $message['reply_to_message'];
    $replied_caption = $reply_to_message['caption'] ?? '';

    // 1. Extract the unique file ID from the invisible marker in the caption
    if (!preg_match('/\(tg:\/\/file_id\/(\d+)\)/', $replied_caption, $matches)) {
        sendMessage($user_id, "خطا: فایل اصلی برای ویرایش شناسایی نشد. لطفاً فقط روی فایل‌هایی که ربات ارسال کرده ریپلای کنید.");
        return;
    }
    $original_file_db_id = (int)$matches[1];

    $edit_type = null;
    $new_content = null;

    // 2. Determine the type of edit
    if (isset($message['text'])) {
        if ($message['text'] === '/delete') {
            $edit_type = 'delete';
            $new_content = null; // No content needed for deletion
        } else {
            $edit_type = 'caption';
            $new_content = $message['text'];
        }
    } else { // It's a file replacement
        if (isset($message['photo'])) {
            $edit_type = 'replace_file';
            $new_content = json_encode(['type' => 'photo', 'id' => $message['photo'][count($message['photo']) - 1]['file_id']]);
        } elseif (isset($message['video'])) {
            $edit_type = 'replace_file';
            $new_content = json_encode(['type' => 'video', 'id' => $message['video']['file_id']]);
        } elseif (isset($message['document'])) {
            $edit_type = 'replace_file';
            $new_content = json_encode(['type' => 'document', 'id' => $message['document']['file_id']]);
        } // ... add other file types as needed (audio, voice)
        else {
            sendMessage($user_id, "نوع فایل ارسالی برای جایگزینی پشتیبانی نمی‌شود.");
            return;
        }
    }

    // 3. Store the pending edit in the database
    if ($edit_type) {
        $db->executeQuery(
            "INSERT INTO pending_edits (user_id, original_file_id, edit_type, new_content, created_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE edit_type = VALUES(edit_type), new_content = VALUES(new_content)",
            [$user_id, $original_file_db_id, $edit_type, $new_content]
        );

        // 4. Give feedback to the admin
        $feedback_message = '';
        switch ($edit_type) {
            case 'caption':
                $feedback_message = "✅ کپشن جدید برای این فایل ثبت موقت شد.";
                break;
            case 'replace_file':
                $feedback_message = "✅ فایل جدید برای جایگزینی ثبت موقت شد.";
                break;
            case 'delete':
                $feedback_message = "✅ درخواست حذف این فایل ثبت موقت شد.";
                break;
        }
        // Send feedback as a reply to the admin's message
        sendMessage($user_id, $feedback_message, null, null, $message['message_id']);
    }
}

function handle_apply_edits($chat_id, $user_id, $file_code) {
    global $db;

    // 1. Get all pending edits for this user
    $stmt = $db->executeQuery("SELECT * FROM pending_edits WHERE user_id = ?", [$user_id]);
    $edits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($edits)) {
        sendMessage($chat_id, "هیچ تغییری برای اعمال یافت نشد. عملیات لغو شد.");
    } else {
        foreach ($edits as $edit) {
            $file_id = $edit['original_file_id'];
            switch ($edit['edit_type']) {
                case 'caption':
                    $db->executeQuery("UPDATE files SET caption = ? WHERE id = ?", [$edit['new_content'], $file_id]);
                    break;
                case 'replace_file':
                    $new_file_data = json_decode($edit['new_content'], true);
                    $db->executeQuery(
                        "UPDATE files SET file_type = ?, file_id = ? WHERE id = ?",
                        [$new_file_data['type'], $new_file_data['id'], $file_id]
                    );
                    break;
                case 'delete':
                    $db->executeQuery("DELETE FROM files WHERE id = ?", [$file_id]);
                    break;
            }
        }
        sendMessage($chat_id, "✅ تمام تغییرات با موفقیت اعمال شد.");
    }

    // 2. Clean up pending edits table for the user
    $db->executeQuery("DELETE FROM pending_edits WHERE user_id = ?", [$user_id]);

    // 3. Reset user state and show the final media management menu
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);
    display_media_management_menu($chat_id, $file_code);
}

function handle_cancel_edits($chat_id, $user_id) {
    global $db;

    // 1. Just delete the pending edits without applying them
    $db->executeQuery("DELETE FROM pending_edits WHERE user_id = ?", [$user_id]);

    // 2. Reset user state
    $db->executeQuery("UPDATE users SET step = 'none' WHERE id = ?", [$user_id]);

    // 3. Send confirmation and show admin panel
    sendMessage($chat_id, "عملیات ویرایش لغو شد و هیچ تغییری اعمال نگردید.", get_admin_panel_keyboard());
}
