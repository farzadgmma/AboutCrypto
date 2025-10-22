<?php
// src/includes/utils.php

/**
 * Checks if a user has admin privileges.
 * An admin is either defined in the main config file or exists in the 'admins' database table.
 *
 * @param int $userId The user's Telegram ID.
 * @param Database $db The database connection object.
 * @return bool True if the user is an admin, false otherwise.
 */
function isAdmin($userId, $db) {
    // 1. Check if the user is a super admin defined in the config file.
    if (in_array($userId, ADMIN_IDS)) {
        return true;
    }

    // 2. Check if the user exists in the 'admins' table in the database.
    $stmt = $db->executeQuery("SELECT idadmin FROM admins WHERE idadmin = ?", [$userId]);
    if ($stmt && $stmt->rowCount() > 0) {
        return true;
    }

    return false;
}

/**
 * Generates a random alphanumeric code of a given length.
 *
 * @param int $length The desired length of the code.
 * @return string The generated random code.
 */
function generate_random_code($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}
