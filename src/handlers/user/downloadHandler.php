<?php
// src/handlers/user/downloadHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function handleDownloadRequest($user, $chat_id, $file_code) {
    $db = Database::getInstance();

    $file = $db->fetch("SELECT * FROM files WHERE code = ?", [$file_code]);
    if (!$file) {
        sendMessage($chat_id, "❌ فایل مورد نظر یافت نشد.");
        return;
    }

    // Step 1: Forced Join Check (if enabled for the file)
    $settings = $db->fetch("SELECT joinchanneltext FROM settings LIMIT 1");
    $channels = $db->fetchAll("SELECT * FROM channels");

    if ($file['ghfl_ch'] === 'on' && !empty($channels)) {
        $unjoined_channels = [];
        foreach ($channels as $channel) {
            if (!isUserMember($user['id'], $channel['idoruser'])) {
                $unjoined_channels[] = $channel;
            }
        }

        if (!empty($unjoined_channels)) {
            $message_text = $settings['joinchanneltext'] ?? "برای دسترسی به فایل، ابتدا باید در کانال‌های زیر عضو شوید:";
            $keyboard = [];
            foreach ($unjoined_channels as $channel) {
                $channel_name = ($channel['type'] === 'customlink') ? $channel['idoruser'] : getChannelTitle($channel['idoruser']);
                $keyboard[] = [['text' => $channel_name, 'url' => $channel['link']]];
            }
            $keyboard[] = [['text' => "✅ عضو شدم", 'callback_data' => "confirm_join_{$file_code}"]];

            sendMessage($chat_id, $message_text, ['inline_keyboard' => $keyboard]);
            return;
        }
    }

    proceedToNextDownloadStep($user, $chat_id, $file);
}


function proceedToNextDownloadStep($user, $chat_id, $file) {
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT bottype, dlfree FROM settings LIMIT 1");

    // Step 2: Subscription & Free Download Check
    if ($settings['bottype'] === 'sub' && $user['vip'] !== 'yes' && !isAdmin($user['id'])) {
        if ($user['dl'] >= $settings['dlfree']) {
            handleSubscriptionCommand($user, $chat_id); // Show payment options
            return;
        }
    }

    // Step 3: File Password Check
    if ($file['pass'] !== 'none' && !empty($file['pass'])) {
        // Ask the user for the password
        setUserStep($user['id'], 'awaiting_password_' . $file['code']);
        sendMessage($chat_id, "<b>🔐 این فایل دارای رمز عبور است. لطفاً رمز را وارد کنید:</b>", ['keyboard' => [[['text' => '🏠 برگشت به منو']]], 'resize_keyboard' => true]);
        return;
    }

    // Step 4: Download Limit Check
    if ($file['mahdodl'] !== 'none' && $file['dl'] >= (int)$file['mahdodl']) {
        sendMessage($chat_id, "❗️ متاسفانه ظرفیت دانلود این فایل به پایان رسیده است.");
        return;
    }

    // All checks passed, send the file.
    sendFileToUser($chat_id, $file, $settings);
}


function sendFileToUser($chat_id, $file, $settings) {
    // This is a simplified version. The full implementation should handle ads and like/dislike buttons.
    $caption = convertToHyperlink($file['caption'] ?? '');
    $final_caption = $caption . "\n\n" . ($settings['signdownload'] ?? '');

    $params = [
        'chat_id' => $chat_id,
        'caption' => $final_caption,
        'parse_mode' => 'HTML',
        'protect_content' => ($file['fwlock'] === 'on')
    ];

    $params[$file['type']] = $file['file_id'];
    $method = 'send' . ucfirst($file['type']);

    $response = apiRequest($method, $params);

    if ($response && $response['ok']) {
        $db = Database::getInstance();
        $db->execute("UPDATE files SET dl = dl + 1 WHERE code = ?", [$file['code']]);
        $db->execute("UPDATE user SET dl = dl + 1 WHERE id = ?", [$chat_id]);
    } else {
        // Log error if sending failed
        error_log("Failed to send file {$file['code']} to user {$chat_id}. Response: " . json_encode($response));
    }
}


function isUserMember($user_id, $channel_id) {
    $response = apiRequest('getChatMember', ['chat_id' => $channel_id, 'user_id' => $user_id]);
    if ($response && $response['ok']) {
        $status = $response['result']['status'];
        return in_array($status, ['creator', 'administrator', 'member']);
    }
    return false;
}

function getChannelTitle($channel_id) {
    $response = apiRequest('getChat', ['chat_id' => $channel_id]);
    if ($response && $response['ok'] && isset($response['result']['title'])) {
        return $response['result']['title'];
    }
    return $channel_id;
}
function convertToHyperlink($text) {
    if ($text === null) {
        return '';
    }
    return preg_replace_callback('/(.+?)\^https?:\/\/(\S+)/', function ($matches) {
        return "<a href=\"https://{$matches[2]}\">{$matches[1]}</a>";
    }, $text);
}
