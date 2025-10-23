<?php
// src/handlers/admin/uploadHandler.php

require_once __DIR__ . '/../../utils/helpers.php';

function handleAdminUpload($user, $message) {
    $user_id = $user['id'];
    $step = $user['step'];

    if ($step === 'admin_upload_start') {
        // This is the first file being sent
        $code = generateUniqueFileCode();
        setUserStep($user_id, 'admin_upload_files', $code);
        saveFileFromMessage($message, $code, $user_id);
    } elseif (strpos($step, 'admin_upload_files') === 0) {
        // Subsequent files for the same upload batch
        $code = $user['step2'];
        saveFileFromMessage($message, $code, $user_id);
    }
}

function saveFileFromMessage($message, $code, $admin_id) {
    $db = Database::getInstance();
    $file_id = null;
    $file_size = null;
    $caption = $message->caption ?? null;
    $type = null;

    if (isset($message->video)) {
        $file_id = $message->video->file_id;
        $file_size = $message->video->file_size;
        $type = "video";
    } elseif (isset($message->document)) {
        $file_id = $message->document->file_id;
        $file_size = $message->document->file_size;
        $type = "document";
    } elseif (isset($message->audio)) {
        $file_id = $message->audio->file_id;
        $file_size = $message->audio->file_size;
        $type = "audio";
    } elseif (isset($message->voice)) {
        $file_id = $message->voice->file_id;
        $file_size = $message->voice->file_size;
        $type = "voice";
    } elseif (isset($message->photo)) {
        $photo = end($message->photo); // Get the highest resolution photo
        $file_id = $photo->file_id;
        $file_size = $photo->file_size;
        $type = "photo";
    }

    if ($file_id) {
        $zaman = date("Y-m-d H:i:s");
        $size_human = convertFileSize($file_size);

        $db->execute(
            "INSERT INTO files (code, id, dl, pass, mahdodl, zaman, likes, dislikes, file_id, file_size, caption, type, fwlock, ghfl_ch, zd_filter, msg_id, thumbnail)
             VALUES (?, ?, 0, 'none', 'none', ?, 0, 0, ?, ?, ?, ?, 'on', 'on', 'off', 'none', 'none')",
            [$code, $admin_id, $zaman, $file_id, $size_human, $caption, $type]
        );
    }
}

function finishAdminUpload($user, $chat_id) {
    $code = $user['step2'];
    setUserStep($user['id'], 'none');

    // Show the file info panel for the newly uploaded file(s)
    showFileInfo($chat_id, $code);
}


function generateUniqueFileCode($length = 6) {
    $db = Database::getInstance();
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        $result = $db->fetch("SELECT code FROM files WHERE code = ?", [$code]);
    } while ($result);
    return $code;
}

function convertFileSize($size) {
    if ($size == 0) return "0 B";
    $i = floor(log($size, 1024));
    return round($size / pow(1024, $i), 2) . ' ' . ['B', 'KB', 'MB', 'GB', 'TB'][$i];
}
