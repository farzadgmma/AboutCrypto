<?php
// src/handlers/folder_handler.php

/**
 * Handles a click on a folder button.
 * Fetches all files within that folder and sends them to the user.
 */
function handle_folder_click($chat_id, $folder_name) {
    $db = new Database();

    // Remove the folder icon from the name
    $folder_name_clean = str_replace('📁 ', '', $folder_name);

    // Get the file codes associated with the folder name
    $stmt = $db->executeQuery("SELECT file_codes FROM folders WHERE name = ?", [$folder_name_clean]);

    if ($stmt->rowCount() > 0) {
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        $file_codes = explode(',', $folder['file_codes']);

        if (empty($file_codes) || empty($file_codes[0])) {
            sendMessage($chat_id, "این پوشه خالی است.");
            return;
        }

        sendMessage($chat_id, "در حال ارسال فایل‌های پوشه **" . $folder_name_clean . "**...", null);

        // Fetch each file's info and send it
        foreach ($file_codes as $code) {
            $code = trim($code);
            $file_stmt = $db->executeQuery("SELECT * FROM files WHERE code = ?", [$code]);
            if ($file_stmt->rowCount() > 0) {
                $file = $file_stmt->fetch(PDO::FETCH_ASSOC);
                // Use the helper function from search_handler to send the file
                sendFile($chat_id, $file['file_id'], $file['file_type'], $file['caption']);
                usleep(300000); // Wait 0.3 seconds between sending files to avoid rate limiting
            }
        }
    } else {
        sendMessage($chat_id, "پوشه مورد نظر یافت نشد.");
    }
}
