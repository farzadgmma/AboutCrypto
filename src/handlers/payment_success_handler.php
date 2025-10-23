<?php

function handle_successful_payment($pdo, $user_id, $plan_id, $ref_id) {
    // 1. Get plan details
    $stmt = $pdo->prepare("SELECT * FROM payment_plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();

    if (!$plan) {
        error_log("Invalid plan_id {$plan_id} for user_id {$user_id}");
        return;
    }

    // 2. Calculate new expiry date
    $duration = $plan['duration_days'];
    $new_expire_date = date('Y-m-d H:i:s', strtotime("+$duration days"));

    // 3. Update user's subscription status
    $stmt = $pdo->prepare("UPDATE users SET vip_status = 'yes', vip_expire_date = ? WHERE user_id = ?");
    $stmt->execute([$new_expire_date, $user_id]);

    // 4. Log the transaction (optional but recommended)
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, plan_id, reference_id, amount) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $plan_id, $ref_id, $plan['price']]);

    // 5. Notify the user via the bot
    $plan_name = $plan['name'];
    $expire_date_formatted = date('Y-m-d', strtotime($new_expire_date));
    $message_text = "✅ پرداخت شما با موفقیت انجام شد.\n\n" .
                    "طرح اشتراک '<b>{$plan_name}</b>' برای شما فعال گردید.\n" .
                    "تاریخ انقضای اشتراک شما: <b>{$expire_date_formatted}</b>";

    send_message($user_id, $message_text);
}
