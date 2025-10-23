<?php
// src/handlers/admin/adminCallbackHandler.php

require_once __DIR__ . '/../../utils/helpers.php';
// Include specific handlers for each admin section as they are built
require_once __DIR__ . '/mediaManagementHandler.php';
require_once __DIR__ . '/userManagementHandler.php';
require_once __DIR__ . '/adminsManagementHandler.php';
require_once __DIR__ . '/customizationHandler.php';
require_once __DIR__ . '/forcedJoinHandler.php';
require_once __DIR__ . '/paymentSettingsHandler.php';
require_once __DIR__ . '/adsHandler.php';

/**
 * Routes all admin-related callback queries to the appropriate function.
 *
 * @param int $chat_id
 * @param int $message_id
 * @param string $data The callback data from the button.
 */
function handleAdminCallback($chat_id, $message_id, $data) {
    // The data will be prefixed, e.g., "admin_media_delete_{file_code}"
    $parts = explode('_', $data);
    $section = $parts[1] ?? null; // e.g., 'media', 'user', 'settings'
    $action = $parts[2] ?? null;  // e.g., 'delete', 'edit', 'list'
    $payload = $parts[3] ?? null; // e.g., a file_code or user_id

    switch ($section) {
        case 'media':
            handleMediaCallback($chat_id, $message_id, $action, $payload);
            break;
        case 'settings':
            // handleSettingsCallback($chat_id, $message_id, $action, $payload);
            break;
        case 'user':
            if ($action === 'ban') {
                handleUserBan($chat_id, $payload, $message_id);
            } elseif ($action === 'unban') {
                handleUserUnban($chat_id, $payload, $message_id);
            }
            break;
        case 'admin':
            if ($action === 'list') {
                listAdmins($chat_id, $message_id);
            } elseif ($action === 'deleteconfirm') {
                confirmDeleteAdmin($chat_id, $message_id, $payload);
            } elseif ($action === 'delete') {
                deleteAdmin($chat_id, $message_id, $payload);
            }
            break;
        case 'setting':
            if ($action === 'toggle') {
                toggleSetting($chat_id, $message_id, $payload);
            } elseif ($action === 'bottype') {
                showBotTypePanel($chat_id, $message_id);
            } elseif ($action === 'setbottype') {
                setBotType($chat_id, $message_id, $payload);
            } elseif ($action === 'main') {
                showCustomizationPanel($chat_id, $message_id);
            }
            break;
        case 'join':
            if ($action === 'main') {
                showForcedJoinPanel($chat_id, $message_id);
            } elseif ($action === 'add') {
                askAddJoinType($chat_id, $message_id);
            } elseif ($action === 'delete') {
                deleteJoinChannel($chat_id, $message_id, $payload);
            }
            break;
        case 'payment':
            if ($action === 'main') {
                showPaymentSettingsPanel($chat_id, $message_id);
            } elseif ($action === 'gateway') {
                showGatewayPanel($chat_id, $message_id);
            } elseif ($action === 'setgateway') {
                setGateway($chat_id, $message_id, $payload);
            }
            break;
        case 'ads':
            if ($action === 'toggle') {
                toggleAds($chat_id, $message_id);
            }
            // Add other ad actions here...
            break;
        // Add other sections like 'broadcast', etc.
        default:
            answerCallbackQuery($GLOBALS['update']->callback_query->id, "دستور نامعتبر است.", true);
            break;
    }
}
