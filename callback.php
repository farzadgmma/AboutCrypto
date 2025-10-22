<?php
// callback.php

require_once 'config/config.php';
require_once 'src/handlers/payment_handler.php';

// Determine the gateway from the URL, e.g., /callback.php?gateway=zarinpal
$gateway = $_GET['gateway'] ?? null;

if ($gateway) {
    handle_payment_callback($gateway, $_GET);
} else {
    // Handle cases where no gateway is specified
    http_response_code(400);
    echo "Gateway not specified.";
}
