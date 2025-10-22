<?php
// src/handlers/payment_handler.php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/telegram_api.php';

function handle_payment_callback($gateway, $query_params) {
    if ($gateway === 'zarinpal') {
        handle_zarinpal_callback($query_params);
    } elseif ($gateway === 'ziball') {
        handle_ziball_callback($query_params);
    }
}

function handle_zarinpal_callback($query_params) {
    global $db;
    $authority = $query_params['Authority'] ?? null;
    $status = $query_params['Status'] ?? null;
    $user_id = $query_params['user_id'] ?? null;
    $plan_id = $query_params['plan_id'] ?? null;

    if ($status !== 'OK' || !$user_id || !$authority || !$plan_id) {
        if ($user_id) sendMessage($user_id, "پرداخت ناموفق بود یا توسط شما لغو شد.");
        return;
    }

    // Fetch plan details and merchant ID from DB
    $plan_stmt = $db->executeQuery("SELECT price, duration_days FROM subscriptions WHERE id = ?", [$plan_id]);
    $plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
    $merchant_stmt = $db->executeQuery("SELECT zarinpal_merchant_id FROM payment_settings WHERE id = 1");
    $merchant_id = $merchant_stmt->fetchColumn();

    if (!$plan || !$merchant_id) {
        sendMessage($user_id, "خطا در پردازش پرداخت: اطلاعات پلن یا مرچنت یافت نشد.");
        return;
    }
    $amount = $plan['price'];

    // Zarinpal Verification
    $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
    $result = $client->PaymentVerification([
        'MerchantID'     => $merchant_id,
        'Authority'      => $authority,
        'Amount'         => $amount,
    ]);

    if ($result->Status == 100) { // Payment is successful
        $duration = $plan['duration_days'];
        $expire_date = date('Y-m-d H:i:s', strtotime("+$duration days"));

        $db->executeQuery("UPDATE users SET is_vip = TRUE, vip_expire_date = ? WHERE id = ?", [$expire_date, $user_id]);

        sendMessage($user_id, "✅ پرداخت شما با موفقیت تایید شد. اشتراک شما تا تاریخ " . $expire_date . " فعال گردید.");
    } else {
        sendMessage($user_id, "خطا در تایید پرداخت. کد خطا: " . $result->Status);
    }
}

function handle_ziball_callback($query_params) {
    global $db;
    $success = $query_params['success'] ?? 0;
    $track_id = $query_params['trackId'] ?? null;
    $order_id = $query_params['orderId'] ?? null; // This should contain our user_id and plan_id

    // Ziball sends orderId as a string, let's parse it
    list($user_id, $plan_id) = explode('_', $order_id);

    if ($success != 1 || !$user_id || !$plan_id || !$track_id) {
        if ($user_id) sendMessage($user_id, "پرداخت ناموفق بود یا توسط شما لغو شد.");
        return;
    }

    // Fetch plan details and merchant ID
    $plan_stmt = $db->executeQuery("SELECT price, duration_days FROM subscriptions WHERE id = ?", [$plan_id]);
    $plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
    $merchant_stmt = $db->executeQuery("SELECT ziball_merchant_id FROM payment_settings WHERE id = 1");
    $merchant_id = $merchant_stmt->fetchColumn();

    if (!$plan || !$merchant_id) {
        sendMessage($user_id, "خطا در پردازش پرداخت: اطلاعات پلن یا مرچنت یافت نشد.");
        return;
    }

    // Ziball Verification
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => "https://gateway.zibal.ir/v1/verify",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode([
        "merchant" => $merchant_id,
        "trackId" => $track_id,
      ]),
      CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
      ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        sendMessage($user_id, "خطا در ارتباط با درگاه پرداخت.");
        return;
    }

    $result = json_decode($response, true);

    if (isset($result['result']) && $result['result'] == 100) { // Payment is successful
        $duration = $plan['duration_days'];
        $expire_date = date('Y-m-d H:i:s', strtotime("+$duration days"));

        $db->executeQuery("UPDATE users SET is_vip = TRUE, vip_expire_date = ? WHERE id = ?", [$expire_date, $user_id]);

        sendMessage($user_id, "✅ پرداخت شما با موفقیت تایید شد. اشتراک شما تا تاریخ " . $expire_date . " فعال گردید.");
    } else {
        $error_message = $result['message'] ?? 'خطای نامشخص';
        sendMessage($user_id, "خطا در تایید پرداخت: " . $error_message);
    }
}
