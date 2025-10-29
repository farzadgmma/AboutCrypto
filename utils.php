<?php

// ============== P H P - B O T ==============
// ----------- U T I L I T Y - F I L E -----------
// ---- C R Y P T O 1 F I L M . O N L I N E ----

// فایل شامل توابع کمکی و عمومی که در سراسر ربات استفاده می‌شوند.

/**
 * برای ارسال درخواست به API تلگرام استفاده می‌شود.
 * @param string $method متد API تلگرام (مثلاً 'sendMessage').
 * @param array $datas داده‌های ارسالی به متد.
 * @return mixed نتیجه پاسخ API به صورت آبجکت JSON.
 */
function Crypto1film($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        // در صورت بروز خطا، آن را لاگ می‌گیریم (در عمل بهتر است در فایل لاگ ذخیره شود)
        var_dump(curl_error($ch));
        return null;
    } else {
        return json_decode($res);
    }
}

/**
 * بررسی می‌کند که آیا کاربر ادمین اصلی یا ادمین تعریف شده در دیتابیس است.
 * @param int $chat_id شناسه عددی کاربر.
 * @return bool اگر کاربر ادمین باشد true، در غیر این صورت false.
 */
function hasAccess($chat_id) {
    global $admins, $pdo;
    // ابتدا بررسی آرایه ادمین‌های اصلی در config.php
    if (in_array($chat_id, $admins)) {
        return true;
    }
    // سپس بررسی جدول ادمین‌ها در دیتابیس
    $stmt = $pdo->prepare("SELECT idadmin FROM admins WHERE idadmin = ?");
    $stmt->execute([$chat_id]);
    return $stmt->fetch() !== false;
}

/**
 * متن حاوی فرمت 'text^link' را به هایپرلینک HTML تبدیل می‌کند.
 * @param string $text متن ورودی.
 * @return string متن تبدیل شده با تگ <a>.
 */
function convertToHyperlink($text) {
    if (!$text) return '';
    return preg_replace_callback('/(.+?)\\^(https?:\\/\\/\\S+)/u', function ($matches) {
        return "<a href=\"" . htmlspecialchars($matches[2]) . "\">" . htmlspecialchars($matches[1]) . "</a>";
    }, $text);
}

/**
 * حجم فایل را از بایت به فرمت خواناتر (KB, MB, GB) تبدیل می‌کند.
 * @param int $size حجم فایل به بایت.
 * @return string حجم فایل به صورت خوانا.
 */
function convert($size) {
    if ($size <= 0) return "0 B";
    $i = floor(log($size, 1024));
    return round($size / pow(1024, $i), 2) . " " . ["B", "KB", "MB", "GB", "TB", "PB"][$i];
}


/**
 * نوع فایل را از انگلیسی به فارسی ترجمه می‌کند.
 * @param string $name نام نوع فایل (مثلاً 'document').
 * @return string معادل فارسی.
 */
function doc($name) {
    $types = [
        'document' => 'سند',
        'video'    => 'ویدیو',
        'photo'    => 'عکس',
        'voice'    => 'ویس',
        'audio'    => 'موزیک',
        'sticker'  => 'استیکر',
    ];
    return $types[$name] ?? 'فایل';
}

/**
 * زمان تخمینی برای عملیات همگانی را محاسبه می‌کند.
 * @param int $fil تعداد کاربران باقی‌مانده.
 * @return int زمان تخمینی به دقیقه.
 */
function takhmin($fil) {
    if ($fil <= 200) {
        return 2;
    }
    $besanie = $fil / 200;
    return ceil($besanie) + 1;
}

/**
 * بررسی می‌کند که آیا ربات در یک چت ادمین است یا خیر.
 * @param string|int $chat_id شناسه چت.
 * @param string $token توکن ربات.
 * @return bool اگر ادمین باشد true، در غیر این صورت false.
 */
function getChatstats($chat_id, $token) {
    $url = "https://api.telegram.org/bot" . $token . "/getChatAdministrators?chat_id=" . $chat_id;
    $result = file_get_contents($url);
    if ($result === false) return false;
    $result = json_decode($result);
    return $result->ok ?? false;
}

/**
 * وضعیت عضویت کاربر در یک کانال را بررسی می‌کند.
 * @param int $from_id شناسه کاربر.
 * @param string|int $channel شناسه کانال.
 * @return bool اگر عضو باشد true، در غیر این صورت false.
 */
function is_join($from_id, $channel) {
    $forchaneel = Crypto1film("getChatMember", ["chat_id" => $channel, "user_id" => $from_id]);
    if (isset($forchaneel->result->status)) {
        $status = $forchaneel->result->status;
        return in_array($status, ['member', 'creator', 'administrator']);
    }
    return false;
}

/**
 * عنوان یک کانال را از طریق API تلگرام دریافت می‌کند.
 * @param string|int $channel شناسه کانال.
 * @return string عنوان کانال یا "ناشناخته".
 */
function getChannelTitle($channel) {
    $info = Crypto1film("getChat", ["chat_id" => $channel]);
    if (isset($info->result->title)) {
        return $info->result->title;
    }
    return "ناشناخته";
}

/**
 * تاریخ جلالی را به میلادی تبدیل می‌کند.
 * @param int $j_y سال جلالی.
 * @param int $j_m ماه جلالی.
 * @param int $j_d روز جلالی.
 * @param string $mod جداکننده.
 * @return string تاریخ میلادی.
 */
function jalali_to_gregorian($j_y, $j_m, $j_d, $mod = '') {
    $j_y = (int) $j_y; $j_m = (int) $j_m; $j_d = (int) $j_d;
    $d_4 = $j_y % 4;
    $g_a = [0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $d_y = ($j_m < 7) ? (($j_m - 1) * 31) + $j_d : (($j_m - 7) * 30) + $j_d + 186;
    $jy = $j_y - 621;
    $jd = ($jy * 365) + floor($jy / 4) + $d_y - 1;
    if ($d_4 == 0 && $j_m > 10) $jd++;
    $gd = date("d", mktime(0, 0, 0, 1, $jd, 1));
    $gm = date("m", mktime(0, 0, 0, 1, $jd, 1));
    $gy = date("Y", mktime(0, 0, 0, 1, $jd, 1));
    return $gy . $mod . $gm . $mod . $gd;
}

/**
 * تاریخ میلادی را به جلالی تبدیل می‌کند.
 * @param int $g_y سال میلادی.
 * @param int $g_m ماه میلادی.
 * @param int $g_d روز میلادی.
 * @param string $mod جداکننده.
 * @return string تاریخ جلالی.
 */
function gregorian_to_jalali($g_y, $g_m, $g_d, $mod = '') {
    $g_y = (int) $g_y; $g_m = (int) $g_m; $g_d = (int) $g_d;
    $d_4 = $g_y % 4;
    $g_a = [0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    if ($d_4 == 0) $g_a[3]++;
    $doy = $g_a[(int) $g_m] + $g_d;
    $d_33 = (int) ((($g_y - 16) % 132) * 0.0305);
    $a = ($d_4 == 1 || $d_4 < 1) ? 286 : 287;
    $b = (($d_4 == 1 || $d_4 < 1) && $d_33 == 3) ? 78 : 77;
    $j_y = ($d_4 == 3 && $doy > $b) ? $g_y - 621 : $g_y - 622;
    $jd = 365 * $j_y + (int) (($j_y + 3) / 4) - 9;
    $gd = $doy + (365 * $g_y) + (int) (($g_y - 1) / 4) - $jd;
    $j_d = ($gd < 187) ? $gd % 31 : (($gd - 186) % 30);
    $j_m = ($gd < 187) ? (int) ($gd / 31) + 1 : (int) (($gd - 186) / 30) + 7;
    if ($j_d == 0) { $j_d = 30; $j_m--; }
    return $j_y . $mod . $j_m . $mod . $j_d;
}

/**
 * تاریخ و زمان جلالی را برمی‌گرداند.
 * @param string $format فرمت خروجی.
 * @param string|bool $timestamp تایم‌استمپ.
 * @param string $none پارامتر استفاده نشده.
 * @return string تاریخ و زمان فرمت‌شده.
 */
function jdate($format, $timestamp = '', $none = '') {
    $T_sec = 0;
    if ($timestamp === '') $timestamp = time();
    list($j_y, $j_m, $j_d) = explode('/', gregorian_to_jalali(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp), '/'));
    $m_p = [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];
    $format = str_replace(['F', 'l', 'd', 'm', 'Y', 'H', 'i', 's'], [$m_p[$j_m - 1], date('l', $timestamp), $j_d, $j_m, $j_y, date('H', $timestamp), date('i', $timestamp), date('s', $timestamp)], $format);
    return $format;
}

?>
