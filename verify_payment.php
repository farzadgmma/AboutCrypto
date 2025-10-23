<?php
// This file acts as the callback URL for payment gateways.
// It verifies the payment and then triggers the success handler.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/includes/Database.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/handlers/payment_success_handler.php';

$db = new Database();
$pdo = $db->getConnection();

// --- Zarinpal Verification ---
if (isset($_GET['gateway']) && $_GET['gateway'] === 'zarinpal') {
    $authority = $_GET['Authority'];
    $status = $_GET['Status'];
    $user_id = $_GET['user_id'];
    $plan_id = $_GET['plan_id'];

    if ($status === 'OK') {
        $stmt = $pdo->prepare("SELECT price FROM payment_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch();
        $amount = $plan['price'];

        $verification_data = [
            'MerchantID' => ZARINPAL_MERCHANT_ID,
            'Authority' => $authority,
            'Amount' => $amount,
        ];

        $jsonData = json_encode($verification_data);
        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($jsonData)]);
        $result = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($result, true);

        if (!empty($result['data']) && $result['data']['code'] == 100) {
            handle_successful_payment($pdo, $user_id, $plan_id, $result['data']['ref_id']);
            echo 'Payment successful. You can return to the bot.';
        } else {
            // Log the error
            error_log('Zarinpal Verification Failed: ' . json_encode($result));
            echo 'Payment verification failed.';
        }
    } else {
        echo 'Payment was cancelled by the user.';
    }
}
// --- Zibal Verification ---
elseif (isset($_GET['gateway']) && $_GET['gateway'] === 'zibal') {
    $trackId = $_GET['trackId'];
    $success = $_GET['success'];
    $user_id = $_GET['user_id'];
    $plan_id = $_GET['plan_id'];

    if ($success == 1) {
        $verification_data = [
            'merchant' => ZIBAL_MERCHANT_ID,
            'trackId' => $trackId,
        ];

        $ch = curl_init('https://gateway.zibal.ir/v1/verify');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verification_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $result = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($result, true);

        if ($result['result'] == 100) {
            handle_successful_payment($pdo, $user_id, $plan_id, $trackId);
             echo 'Payment successful. You can return to the bot.';
        } else {
             error_log('Zibal Verification Failed: ' . json_encode($result));
            echo 'Payment verification failed.';
        }
    } else {
        echo 'Payment was cancelled by the user.';
    }
} else {
    echo 'Invalid payment gateway.';
}
