<?php

// ============== P H P - B O T ==============
// ------------- M A I N - B O T - F I L E -------------
// ---- C R Y P T O 1 F I L M . O N L I N E ----

// --- غیرفعال کردن نمایش خطاها در خروجی برای امنیت ---
error_reporting(0);

// --- اتصال فایل‌های ضروری ---
require_once 'config.php';
require_once 'utils.php';

// --- بررسی محدوده IP تلگرام (کد امنیتی از فایل اصلی شما) ---
$telegram_ip_ranges = [
    ["lower" => "149.154.160.0", "upper" => "149.154.175.255"],
    ["lower" => "91.108.4.0", "upper" => "91.108.7.255"]
];
$ip_dec = (float) sprintf("%u", ip2long($_SERVER["REMOTE_ADDR"]));
$ok = false;
foreach ($telegram_ip_ranges as $telegram_ip_range) {
    if (!$ok) {
        $lower_dec = (float) sprintf("%u", ip2long($telegram_ip_range["lower"]));
        $upper_dec = (float) sprintf("%u", ip2long($telegram_ip_range["upper"]));
        if ($lower_dec <= $ip_dec && $ip_dec <= $upper_dec) {
            $ok = true;
        }
    }
}
// if (!$ok) {
//     // در محیط تست این خط را کامنت کنید
//     // die("Access Denied.");
// }

// --- اتصال به دیتابیس با PDO ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // در صورت عدم اتصال، خطا را لاگ گرفته و اسکریپت را متوقف می‌کنیم
    // (در عمل بهتر است خطا در فایل لاگ ذخیره شود)
    file_put_contents('db_error_log.txt', $e->getMessage() . "\n", FILE_APPEND);
    die("Database connection failed.");
}

// --- دریافت آپدیت از تلگرام ---
$update = json_decode(file_get_contents("php://input"));

// --- استخراج اطلاعات اصلی از آپدیت ---
$message = $update->message ?? $update->callback_query->message ?? null;
$text = $message->text ?? null;
$chat_id = $message->chat->id ?? null;
$from_id = $update->message->from->id ?? $update->callback_query->from->id ?? null;
$message_id = $message->message_id ?? $update->callback_query->message->message_id ?? null;
$data = $update->callback_query->data ?? null;
$first_name = $update->message->from->first_name ?? $update->callback_query->from->first_name ?? 'کاربر';


// --- اگر اطلاعات اصلی وجود نداشت، اسکریپت را متوقف کن ---
if (!$chat_id || !$from_id) {
    exit();
}

// --- تنظیم منطقه زمانی ---
date_default_timezone_set("Asia/Tehran");

// --- دریافت اطلاعات کاربر از دیتابیس ---
// اطلاعات کاربری که در حال تعامل با ربات است را از جدول 'user' واکشی می‌کنیم.
$user_stmt = $pdo->prepare("SELECT * FROM `user` WHERE `id` = ?");
$user_stmt->execute([$from_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// --- دریافت تمام تنظیمات ربات از دیتابیس ---
// تمام تنظیمات ذخیره شده برای این ربات (با botid مشخص) را یکجا دریافت می‌کنیم تا در ادامه از آن‌ها استفاده کنیم.
$settings_stmt = $pdo->prepare("SELECT * FROM `settings` WHERE `botid` = ?");
$settings_stmt->execute([$botid]);
$settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);


// --- بررسی دسترسی ادمین ---
$is_admin = hasAccess($from_id);

// --- تعریف کیبورد پنل ادمین (دقیقاً مطابق با ساختار درخواستی) ---
$panelmenu = json_encode([
    'keyboard' => [
        // ردیف اول
        [['text' => "📤 آپلود تکی/آلبومی رسانه"], ['text' => "📂 پوشه سازی رسانه ها"]],
        // ردیف دوم
        [['text' => "📊 آمار"], ['text' => "📩 ارسال همگانی"], ['text' => "🎨 شخصی سازی"]],
        // ردیف سوم
        [['text' => "🔐 جوین اجباری"], ['text' => "🗂 مدیریت رسانه"]],
        // ردیف چهارم
        [['text' => "👁‍🗨 ری اکشن/سین اجباری"], ['text' => "💰 تنظیمات پرداخت"]],
        // ردیف پنجم
        [['text' => "📢 تنظیم تبلیغات"], ['text' => "🔍 جستجوی کاربر"], ['text' => "👨🏻‍💻 مدیریت ادمین"]],
        // ردیف ششم
        [['text' => "♻️ آپدیت ربات"], ['text' => "🏠 برگشت به منو"]]
    ],
    'resize_keyboard' => true
]);

// ==========================================================
// ----------- شروع منطق اصلی و مسیریابی دستورات -----------
// ==========================================================

if ($text == $ramzvorodadmin || ($text == "🔧 پنل" && $is_admin)) {
    if ($is_admin) {
        Crypto1film('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🌟 *سلام مدیر گرامی!* خوش آمدید 🌟\n\n🚀 *چه کاری می‌خواهید انجام دهید؟*\n👇 لطفاً یکی از گزینه‌های زیر را انتخاب کنید:",
            'parse_mode' => 'Markdown',
            'reply_markup' => $panelmenu
        ]);
    }
}
// سایر دستورات در ادامه اینجا اضافه خواهند شد...

// --- دستور آپلود فایل توسط ادمین ---
// این بخش زمانی اجرا می‌شود که ادمین روی دکمه "آپلود تکی/آلبومی رسانه" کلیک کند.
elseif (($text == "📤 آپلود تکی/آلبومی رسانه" || $text == $settings['fastupload']) && $is_admin) {

    // --- تولید یک کد ۶ رقمی منحصر به فرد برای فایل‌ها ---
    // یک حلقه برای اطمینان از اینکه کد تولید شده در دیتابیس وجود ندارد.
    $code = '';
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $length = 6;
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        $stmt = $pdo->prepare("SELECT code FROM files WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
    } while ($stmt->fetch());

    // --- آماده‌سازی اطلاعات زمان و تاریخ برای ذخیره ---
    $zaman = jdate("Y/m/d") . "-" . date("H:i:s");
    $step2_data = $code . "|" . $zaman;

    // --- بررسی اینکه آیا آپلود با پیش‌نمایش (تامنیل) فعال است یا خیر ---
    if ($settings['tumbnailvaz'] == 'on') {
        // اگر فعال بود، از ادمین درخواست تامنیل می‌شود.
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✅ پیش نمایش (تامنیل) رسانه که میتواند عکس یا ویدیو باشد را به همراه کپشن ارسال کنید",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "🔙 منوی پنل"]]],
                'resize_keyboard' => true
            ])
        ]);
        // وضعیت کاربر را برای دریافت تامنیل به‌روزرسانی می‌کنیم.
        $user_update_stmt = $pdo->prepare("UPDATE user SET step = 'tumupload', step2 = ? WHERE id = ?");
        $user_update_stmt->execute([$step2_data, $from_id]);
    } else {
        // اگر غیرفعال بود، مستقیماً درخواست فایل‌ها ارسال می‌شود.
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ لطفا تمام فایل های خود را ارسال کنید و در انتها روی گزینه ذخیره بزنید.\n\n🗂 شما می توانید پرونده(سند) ، ویدیو ، عکس ، ویس ، استیکر ، موزیک را ارسال کنید تا در ربات آپلود شود \n\n« 🚸 کپشن ها از خود فایل دریافت میشود »\n\n🔻راهنما افزودن کپشن :\n➖➖➖➖➖➖\n🌀 افزودن هایپرلینک:\ntext^https://google.com\n\n🔘نقل قول کردن متن:\n<blockquote>text</blockquote> \n\n🌀 نمونه برجسته کردن متن :\n<b> text </b> \n\n🌀 نمونه کج کردن متن :\n<i> text </i>\n\n🌀 نمونه کد کردن متن :\n<code> text </code>",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => "☑️ ذخیره فایل ها"]],
                    [['text' => "🔙 منوی پنل"]]
                ],
                'resize_keyboard' => true
            ])
        ]);
        // وضعیت کاربر را برای دریافت فایل‌ها به‌روزرسانی می‌کنیم.
        $user_update_stmt = $pdo->prepare("UPDATE user SET step = 'upload', step2 = ? WHERE id = ?");
        $user_update_stmt->execute([$step2_data, $from_id]);
    }
}

// --- بخش تنظیم تبلیغات ---

// --- منوی اصلی تنظیم تبلیغات ---
elseif ($text == "📢 تنظیم تبلیغات" && $is_admin) {
    $vaziat = ($settings['vaziat'] == 'on') ? "✅ فعال" : "❌ غیرفعال";
    $place = ($settings['placeads'] == 'after') ? "🔻 بعد از نمایش رسانه" : "🔺 قبل از نمایش رسانه";
    $ad_count = $pdo->query("SELECT COUNT(id) FROM ads")->fetchColumn();

    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "🪧 *وضعیت نمایش تبلیغات*\n\n" .
                  "▪️ نمایش: *" . $vaziat . "*\n" .
                  "▪️ تعداد تبلیغات فعال: *" . $ad_count . "* تبلیغ\n" .
                  "🚸 محل نمایش تبلیغ: *" . $place . "*",
        'parse_mode' => "Markdown",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "🔕 فعال/غیرفعال سازی تبلیغات"], ['text' => "🪧 محل نمایش تبلیغات"]],
                [['text' => "➕ افزودن تبلیغ"], ['text' => "🚧 لیست تبلیغات"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
}

// --- فعال/غیرفعال سازی تبلیغات ---
elseif ($text == "🔕 فعال/غیرفعال سازی تبلیغات" && $is_admin) {
    if ($settings['vaziat'] == 'on') {
        $pdo->prepare("UPDATE settings SET vaziat = 'off' WHERE botid = ?")->execute([$botid]);
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❎ مشاهده تبلیغات برای کاربران غیرفعال شد"]);
    } else {
        $pdo->prepare("UPDATE settings SET vaziat = 'on' WHERE botid = ?")->execute([$botid]);
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "✅ مشاهده تبلیغات برای کاربران فعال شد"]);
    }
}

// --- منوی محل نمایش تبلیغات ---
elseif ($text == "🪧 محل نمایش تبلیغات" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "🔻 یکی از گزینه های زیر را انتخاب کنید:",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "🔺 قبل از ارسال رسانه"], ['text' => "🔻 بعد از ارسال رسانه"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
    $pdo->prepare("UPDATE user SET step = 'placeshowads' WHERE id = ?")->execute([$from_id]);
}

// --- تنظیم محل نمایش تبلیغات ---
elseif ($user['step'] == 'placeshowads' && in_array($text, ["🔺 قبل از ارسال رسانه", "🔻 بعد از ارسال رسانه"]) && $is_admin) {
    $place = ($text == "🔺 قبل از ارسال رسانه") ? 'before' : 'after';
    $message = ($place == 'before') ? "✅ تبلیغ قبل از ارسال رسانه قرار گرفت." : "✅ تبلیغ بعد از ارسال رسانه قرار گرفت.";

    $pdo->prepare("UPDATE settings SET placeads = ? WHERE botid = ?")->execute([$place, $botid]);
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);

    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => $message,
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
}

// --- افزودن تبلیغ (مرحله اول) ---
elseif ($text == "➕ افزودن تبلیغ" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ محتوای تبلیغ خود را شامل *متن یا عکس، ویدیو، سند، وویس، صوت* (با کپشن یا بدون کپشن) ارسال کنید:",
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'addads' WHERE id = ?")->execute([$from_id]);
}

// --- افزودن تبلیغ (مرحله دوم) ---
elseif ($user['step'] == 'addads' && $text != "🔙 منوی پنل" && $is_admin) {
    $type = 'none'; $file_id = 'none'; $caption = '';

    if (isset($update->message->text)) {
        $type = 'text'; $caption = $update->message->text;
    } elseif (isset($update->message->video)) {
        $type = 'video'; $file_id = $update->message->video->file_id; $caption = $update->message->caption;
    } elseif (isset($update->message->photo)) {
        $type = 'photo'; $file_id = $update->message->photo[count($update->message->photo)-1]->file_id; $caption = $update->message->caption;
    } elseif (isset($update->message->document)) {
        $type = 'document'; $file_id = $update->message->document->file_id; $caption = $update->message->caption;
    } elseif (isset($update->message->audio)) {
        $type = 'audio'; $file_id = $update->message->audio->file_id; $caption = $update->message->caption;
    } elseif (isset($update->message->voice)) {
        $type = 'voice'; $file_id = $update->message->voice->file_id; $caption = $update->message->caption;
    }

    if ($type != 'none') {
        $pdo->prepare("INSERT INTO ads (type, file_id, caption) VALUES (?, ?, ?)")->execute([$type, $file_id, $caption]);
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "✅ با موفقیت اضافه شد."]);
        $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ نوع رسانه پشتیبانی نمی‌شود."]);
    }
}

// --- لیست تبلیغات ---
elseif ($text == "🚧 لیست تبلیغات" && $is_admin) {
    $ads_stmt = $pdo->query("SELECT id, type FROM ads ORDER BY id ASC");
    $ads = $ads_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($ads) > 0) {
        $keyboard = [[['text' => "🔢", 'callback_data' => "none"], ['text' => "نوع تبلیغ", 'callback_data' => "none"], ['text' => "مشاهده", 'callback_data' => "none"], ['text' => "حذف", 'callback_data' => "none"]]];
        foreach($ads as $ad) {
            $type_fa = doc($ad['type']);
            $keyboard[] = [
                ['text' => (string)$ad['id'], 'callback_data' => "none"],
                ['text' => $type_fa, 'callback_data' => "none"],
                ['text' => "👁", 'callback_data' => "viewads_" . $ad['id']],
                ['text' => "❌", 'callback_data' => "deleteads_" . $ad['id']]
            ];
        }
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "📢 تعداد کل تبلیغات: *" . count($ads) . "* تبلیغ",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ هیچ تبلیغی وجود ندارد."]);
    }
}

// --- مشاهده تبلیغ (Callback) ---
elseif (strpos($data, "viewads_") === 0 && $is_admin) {
    $ad_id = str_replace("viewads_", "", $data);
    $ad_stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ?");
    $ad_stmt->execute([$ad_id]);
    $ad = $ad_stmt->fetch(PDO::FETCH_ASSOC);

    if ($ad) {
        $params = ['chat_id' => $chat_id, 'caption' => $ad['caption'], 'parse_mode' => 'HTML'];
        switch ($ad['type']) {
            case 'text': Crypto1film('sendMessage', ['chat_id' => $chat_id, 'text' => $ad['caption'], 'parse_mode' => 'HTML']); break;
            case 'photo': $params['photo'] = $ad['file_id']; Crypto1film('sendPhoto', $params); break;
            case 'video': $params['video'] = $ad['file_id']; Crypto1film('sendVideo', $params); break;
            case 'document': $params['document'] = $ad['file_id']; Crypto1film('sendDocument', $params); break;
            case 'audio': $params['audio'] = $ad['file_id']; Crypto1film('sendAudio', $params); break;
            case 'voice': $params['voice'] = $ad['file_id']; Crypto1film('sendVoice', $params); break;
        }
    } else {
        Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => '❌ تبلیغ یافت نشد.']);
    }
}

// --- حذف تبلیغ (Callback) ---
elseif (strpos($data, "deleteads_") === 0 && $is_admin) {
    $ad_id = str_replace("deleteads_", "", $data);
    $pdo->prepare("DELETE FROM ads WHERE id = ?")->execute([$ad_id]);

    // مرتب‌سازی مجدد ID ها
    $pdo->exec("SET @count = 0; UPDATE `ads` SET `id` = @count:= @count + 1;");

    Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "✅ تبلیغ حذف شد."]);

    // رفرش لیست تبلیغات
    $ads_stmt = $pdo->query("SELECT id, type FROM ads ORDER BY id ASC");
    $ads = $ads_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($ads) > 0) {
        $keyboard = [[['text' => "🔢", 'callback_data' => "none"], ['text' => "نوع تبلیغ", 'callback_data' => "none"], ['text' => "مشاهده", 'callback_data' => "none"], ['text' => "حذف", 'callback_data' => "none"]]];
        foreach($ads as $ad) {
            $type_fa = doc($ad['type']);
            $keyboard[] = [['text' => (string)$ad['id'], 'callback_data' => "none"], ['text' => $type_fa, 'callback_data' => "none"], ['text' => "👁", 'callback_data' => "viewads_" . $ad['id']], ['text' => "❌", 'callback_data' => "deleteads_" . $ad['id']]];
        }
        Crypto1film('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "📢 تعداد کل تبلیغات: *" . count($ads) . "* تبلیغ", 'parse_mode' => 'Markdown', 'reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);
    } else {
        Crypto1film('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "❌ تمام تبلیغات حذف شدند."]);
    }
}

// --- منوی تنظیم ری اکشن اجباری ---
elseif (($text == "👌🏻 تنظیم ری اکشن اجباری" || $data == "reactnoww") && $is_admin) {
    $react_settings_stmt = $pdo->query("SELECT * FROM reaction LIMIT 1");
    $react_settings = $react_settings_stmt->fetch(PDO::FETCH_ASSOC);

    $status = ($react_settings['checkreact'] == 'on') ? "✅ روشن" : "❌ خاموش";
    $channel = ($react_settings['channelreact'] == 'none') ? "نامشخص" : "@" . $react_settings['channelreact'];

    $keyboard = [
        [['text' => "🔻 تغییر وضعیت 🔻", "callback_data" => "none"], ['text' => "🔸 دستورات 🔸", "callback_data" => "none"]],
        [['text' => $status, "callback_data" => "reactchange"], ['text' => "👌🏻 وضعیت ری اکشن اجباری:", "callback_data" => "none"]],
        [['text' => $channel, "callback_data" => "reactchannelchange"], ['text' => "📢 کانال ری اکشن اجباری:", "callback_data" => "none"]],
        [['text' => $react_settings['reacttedad'] . " پست آخر", "callback_data" => "reactedadchange"], ['text' => "♾ تعداد ری اکشن اجباری:", "callback_data" => "none"]],
        [['text' => $react_settings['timefakereact'] . " ثانیه", "callback_data' => "reacttimefakechange"], ['text' => "🕰 تایم فیک:", "callback_data" => "none"]]
    ];

    $message_data = [
        'chat_id' => $chat_id,
        'text' => "👌🏻 *وضعیت ری اکشن اجباری کانال:*\n\n🔻 برای تغییر وضعیت، دکمه مورد نظر را انتخاب کنید.\n\n⚠️ *توجه:* در صورت فعال بودن ری اکشن اجباری، سین اجباری غیرفعال می‌شود.",
        'parse_mode' => "Markdown",
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ];

    if ($data == "reactnoww") {
        $message_data['message_id'] = $message_id;
        Crypto1film('editMessageText', $message_data);
    } else {
        Crypto1film('sendMessage', $message_data);
    }
}

// --- تغییر وضعیت ری اکشن اجباری (Callback) ---
elseif ($data == "reactchange" && $is_admin) {
    $current_status = $pdo->query("SELECT checkreact FROM reaction LIMIT 1")->fetchColumn();

    if ($current_status == 'on') {
        $pdo->query("UPDATE reaction SET checkreact = 'off'");
        Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "ری اکشن اجباری خاموش شد"]);
    } else {
        $pdo->query("UPDATE reaction SET checkreact = 'on'");
        $pdo->query("UPDATE seen SET checkseen = 'off'");
        Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "ری اکشن اجباری روشن شد (سین اجباری غیرفعال شد)"]);
    }
}

// --- تغییر کانال ری اکشن اجباری (مرحله اول) ---
elseif ($data == "reactchannelchange" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✅ کانال مورد نظر خود را وارد کنید:\n\n⚠️ کانال باید عمومی باشد.\n⚠️ یوزرنیم کانال را بدون @ وارد کنید.",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'setreactchannel' WHERE id = ?")->execute([$from_id]);
}

// --- تغییر کانال ری اکشن اجباری (مرحله دوم) ---
elseif ($user['step'] == 'setreactchannel' && $text != "🔙 منوی پنل" && $is_admin) {
    if (strpos($text, "@") !== false) {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ آیدی کانال باید بدون @ ارسال شود"]);
    } else {
        $pdo->prepare("UPDATE reaction SET channelreact = ?")->execute([$text]);
        $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ کانال با موفقیت ست شد",
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "🔙 برگشت به منو ری اکشن اجباری", "callback_data" => "reactnoww"]]]])
        ]);
    }
}

// --- تغییر تعداد ری اکشن اجباری (مرحله اول) ---
elseif ($data == "reactedadchange" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✅ تعداد ری اکشن‌ها را از منوی زیر انتخاب کنید (بین ۱ تا ۴۰):",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "1"], ['text' => "2"], ['text' => "3"], ['text' => "4"], ['text' => "5"]],
                [['text' => "10"], ['text' => "15"], ['text' => "20"], ['text' => "30"], ['text' => "40"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
    $pdo->prepare("UPDATE user SET step = 'reactedadchangestep' WHERE id = ?")->execute([$from_id]);
}

// --- تغییر تعداد ری اکشن اجباری (مرحله دوم) ---
elseif ($user['step'] == 'reactedadchangestep' && $text != "🔙 منوی پنل" && $is_admin) {
    if (is_numeric($text) && $text >= 1 && $text <= 100) {
        $pdo->prepare("UPDATE reaction SET reacttedad = ?")->execute([$text]);
        $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ تعداد ری اکشن اجباری با موفقیت روی *" . $text . "* ست شد.",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "⛔ عدد وارد شده معتبر نیست."]);
    }
}

// --- تغییر تایم فیک ری اکشن (مرحله اول) ---
elseif ($data == "reacttimefakechange" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "🕰 تایم فیک (به ثانیه) برای انتظار کاربران را از منوی زیر وارد کنید:",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "1"], ['text' => "2"], ['text' => "3"], ['text' => "4"], ['text' => "5"]],
                [['text' => "10"], ['text' => "15"], ['text' => "20"], ['text' => "25"], ['text' => "30"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
    $pdo->prepare("UPDATE user SET step = 'reactchangetime' WHERE id = ?")->execute([$from_id]);
}

// --- تغییر تایم فیک ری اکشن (مرحله دوم) ---
elseif ($user['step'] == 'reactchangetime' && $text != "🔙 منوی پنل" && $is_admin) {
    if (is_numeric($text) && $text >= 1 && $text <= 60) {
        $pdo->prepare("UPDATE reaction SET timefakereact = ?")->execute([$text]);
        $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ تایم فیک با موفقیت روی *" . $text . "* ثانیه ست شد.",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "⛔ عدد وارد شده معتبر نیست."]);
    }
}

// --- تغییر تعداد سین اجباری (مرحله اول) ---
elseif ($data == "seentedadchange" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✅ تعداد بازدیدها را از منوی زیر انتخاب کنید (بین ۱ تا ۴۰):",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "1"], ['text' => "2"], ['text' => "3"], ['text' => "4"], ['text' => "5"]],
                [['text' => "6"], ['text' => "7"], ['text' => "8"], ['text' => "9"], ['text' => "10"]],
                [['text' => "15"], ['text' => "20"], ['text' => "25"], ['text' => "30"], ['text' => "40"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
    $pdo->prepare("UPDATE user SET step = 'seentedadchangestep' WHERE id = ?")->execute([$from_id]);
}

// --- تغییر تعداد سین اجباری (مرحله دوم) ---
elseif ($user['step'] == 'seentedadchangestep' && $text != "🔙 منوی پنل" && $is_admin) {
    if (is_numeric($text) && $text >= 1 && $text <= 100) {
        $pdo->prepare("UPDATE seen SET adadseen = ?")->execute([$text]);
        $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ تعداد سین اجباری با موفقیت روی *" . $text . "* ست شد.",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "⛔ عدد وارد شده معتبر نیست. لطفا یک عدد بین 1 تا 100 وارد کنید."]);
    }
}

// --- تغییر تایم فیک سین (مرحله اول) ---
elseif ($data == "seetimefakechange" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "🕰 تایم فیک (به ثانیه) برای انتظار کاربران را از منوی زیر وارد کنید:",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "1"], ['text' => "2"], ['text' => "3"], ['text' => "4"], ['text' => "5"]],
                [['text' => "10"], ['text' => "15"], ['text' => "20"], ['text' => "25"], ['text' => "30"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
    $pdo->prepare("UPDATE user SET step = 'seenchangetime' WHERE id = ?")->execute([$from_id]);
}

// --- تغییر تایم فیک سین (مرحله دوم) ---
elseif ($user['step'] == 'seenchangetime' && $text != "🔙 منوی پنل" && $is_admin) {
    if (is_numeric($text) && $text >= 1 && $text <= 60) {
        $pdo->prepare("UPDATE seen SET timefake = ?")->execute([$text]);
        $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ تایم فیک با موفقیت روی *" . $text . "* ثانیه ست شد.",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "⛔ عدد وارد شده معتبر نیست. لطفا یک عدد بین 1 تا 60 وارد کنید."]);
    }
}

// --- بخش جستجوی کاربر ---

// --- جستجوی کاربر (مرحله اول: درخواست آیدی) ---
elseif ($text == "🔍 جستجوی کاربر" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ ایدی عددی کاربر مورد نظر را ارسال کنید:",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'searchuser' WHERE id = ?")->execute([$from_id]);
}

// --- جستجوی کاربر (مرحله دوم: نمایش اطلاعات) ---
elseif ($user['step'] == 'searchuser' && $text != "🔙 منوی پنل" && $is_admin) {
    if (!is_numeric($text)) {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ آیدی عددی نامعتبر است."]);
        return;
    }

    $search_id = $text;

    // جلوگیری از جستجوی ادمین اصلی
    if ($search_id == ADMIN_ID) {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ امکان مشاهده اطلاعات ادمین اصلی وجود ندارد."]);
        return;
    }

    $user_info_stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $user_info_stmt->execute([$search_id]);
    $user_info = $user_info_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_info) {
        $uploads = $pdo->prepare("SELECT COUNT(DISTINCT code) FROM userfiles WHERE id = ?");
        $uploads->execute([$search_id]);
        $uploads_count = $uploads->fetchColumn();

        $joindate = $user_info["timejoin"];
        $partsjoin = explode("-", $joindate);
        $joinus = gregorian_to_jalali($partsjoin[0], $partsjoin[1], $partsjoin[2], "/");

        $is_banned = ($user_info['step'] == 'ban');
        $ban_button_text = $is_banned ? "✅ آنبلاک کاربر" : "⛔️ بلاک کاربر";
        $ban_callback_data = $is_banned ? "unblockuser_" . $search_id : "blockuser_" . $search_id;
        $subscription_type = ($user_info['vip'] == 'yes') ? "اشتراک ویژه" : "اشتراک عادی";
        $status = $is_banned ? "بلاک شده" : "فعال";

        $user_details = "👤 *اطلاعات حساب کاربری:*\n\n" .
                        "▪️ آیدی عددی: `" . $user_info['id'] . "`\n" .
                        "▪️ نام کاربری: *" . htmlspecialchars($user_info['name']) . "*\n" .
                        "💎 نوع اشتراک: *" . $subscription_type . "*\n" .
                        "📥 تعداد دانلودها: `" . $user_info['dl'] . "`\n" .
                        "📤 تعداد آپلودها: `" . $uploads_count . "`\n" .
                        "📅 تاریخ عضویت: " . $joinus . "\n" .
                        "🚫 وضعیت: *" . $status . "*";

        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => $user_details,
            'parse_mode' => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => $ban_button_text, 'callback_data' => $ban_callback_data]],
                    [['text' => "👁 پیوی کاربر", 'url' => "tg://user?id=" . $search_id]]
                ]
            ])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ کاربری با این آیدی یافت نشد."]);
    }
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
}

// --- بلاک/آنبلاک کاربر (Callback) ---
elseif ((strpos($data, "blockuser_") === 0 || strpos($data, "unblockuser_") === 0) && $is_admin) {
    $is_blocking = (strpos($data, "blockuser_") === 0);
    $user_id = str_replace($is_blocking ? "blockuser_" : "unblockuser_", "", $data);

    $new_step = $is_blocking ? 'ban' : 'none';
    $pdo->prepare("UPDATE user SET step = ? WHERE id = ?")->execute([$new_step, $user_id]);

    $alert_text = $is_blocking ? "کاربر با موفقیت بلاک شد." : "کاربر با موفقیت آنبلاک شد.";
    Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => $alert_text, 'show_alert' => true]);

    $notification_text = $is_blocking ? "🔴 حساب کاربری شما مسدود شده است." : "🟢 حساب کاربری شما از حالت مسدود خارج شده است.";
    Crypto1film('sendMessage', ['chat_id' => $user_id, 'text' => $notification_text]);

    // رفرش اطلاعات کاربر برای ادمین
    $user_info_stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $user_info_stmt->execute([$user_id]);
    $user_info = $user_info_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_info) {
        $uploads = $pdo->prepare("SELECT COUNT(DISTINCT code) FROM userfiles WHERE id = ?");
        $uploads->execute([$user_id]);
        $uploads_count = $uploads->fetchColumn();
        $joindate = $user_info["timejoin"];
        $partsjoin = explode("-", $joindate);
        $joinus = gregorian_to_jalali($partsjoin[0], $partsjoin[1], $partsjoin[2], "/");
        $is_banned = ($user_info['step'] == 'ban');
        $ban_button_text = $is_banned ? "✅ آنبلاک کاربر" : "⛔️ بلاک کاربر";
        $ban_callback_data = $is_banned ? "unblockuser_" . $user_id : "blockuser_" . $user_id;
        $subscription_type = ($user_info['vip'] == 'yes') ? "اشتراک ویژه" : "اشتراک عادی";
        $status = $is_banned ? "بلاک شده" : "فعال";
        $user_details = "👤 *اطلاعات حساب کاربری:*\n\n" . "▪️ آیدی عددی: `" . $user_info['id'] . "`\n" . "▪️ نام کاربری: *" . htmlspecialchars($user_info['name']) . "*\n" . "💎 نوع اشتراک: *" . $subscription_type . "*\n" . "📥 تعداد دانلودها: `" . $user_info['dl'] . "`\n" . "📤 تعداد آپلودها: `" . $uploads_count . "`\n" . "📅 تاریخ عضویت: " . $joinus . "\n" . "🚫 وضعیت: *" . $status . "*";
        Crypto1film('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $user_details,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => $ban_button_text, 'callback_data' => $ban_callback_data]], [['text' => "👁 پیوی کاربر", 'url' => "tg://user?id=" . $user_id]]]])
        ]);
    }
}

// --- بخش مدیریت ادمین ---

// --- منوی اصلی مدیریت ادمین ---
elseif ($text == "👨🏻‍💻 مدیریت ادمین" && $is_admin) {
    // فقط ادمین اصلی به این بخش دسترسی دارد
    if ($from_id == ADMIN_ID) {
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "❗️ به بخش مدیریت ادمین های ربات خوش آمدید.\n\n🔻 یکی از گزینه های زیر را انتخاب کنید.",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => "➕ افزودن ادمین"]],
                    [['text' => "🔙 منوی پنل"], ['text' => "👥 لیست ادمین ها"]]
                ],
                'resize_keyboard' => true
            ])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ شما دسترسی به این بخش را ندارید."]);
    }
}

// --- افزودن ادمین جدید (مرحله اول) ---
elseif ($text == "➕ افزودن ادمین" && $from_id == ADMIN_ID) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ ایدی عددی کاربر مورد نظر را برای افزودن به لیست ادمین‌ها وارد کنید:",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'addadmintoch' WHERE id = ?")->execute([$from_id]);
}

// --- افزودن ادمین جدید (مرحله دوم) ---
elseif ($user['step'] == 'addadmintoch' && $text != "🔙 منوی پنل" && $from_id == ADMIN_ID) {
    if (!is_numeric($text)) {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ آیدی عددی نامعتبر است."]);
        return;
    }

    // بررسی اینکه آیا کاربر در دیتابیس کاربران وجود دارد
    $user_to_add_stmt = $pdo->prepare("SELECT id, name FROM user WHERE id = ?");
    $user_to_add_stmt->execute([$text]);
    $user_to_add = $user_to_add_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_to_add) {
        // بررسی اینکه آیا از قبل ادمین بوده است
        $admin_check_stmt = $pdo->prepare("SELECT idadmin FROM admins WHERE idadmin = ?");
        $admin_check_stmt->execute([$user_to_add['id']]);
        if ($admin_check_stmt->fetch()) {
            Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "⚠️ کاربر `" . $user_to_add['id'] . "` از قبل ادمین بوده است.", 'parse_mode' => 'Markdown']);
        } else {
            // افزودن به جدول ادمین‌ها
            $pdo->prepare("INSERT INTO admins (idadmin, nameadmin) VALUES (?, ?)")->execute([$user_to_add['id'], $user_to_add['name']]);
            Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "✅ کاربر `" . $user_to_add['id'] . "` با نام *" . $user_to_add['name'] . "* با موفقیت ادمین شد.", 'parse_mode' => 'Markdown']);
            Crypto1film("sendMessage", ['chat_id' => $user_to_add['id'], 'text' => "🎉 تبریک! شما به عنوان ادمین ربات انتخاب شدید."]);
            $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
        }
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ کاربری با این آیدی در ربات یافت نشد."]);
    }
}

// --- لیست ادمین‌ها ---
elseif ($text == "👥 لیست ادمین ها" && $from_id == ADMIN_ID) {
    $admins_stmt = $pdo->query("SELECT idadmin, nameadmin FROM admins");
    $admins_list = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($admins_list) > 0) {
        $keyboard = [[['text' => "👤 آیدی ادمین", 'callback_data' => "none"], ['text' => "👤 نام ادمین", 'callback_data' => "none"], ['text' => "❌ حذف", 'callback_data' => "none"]]];
        foreach ($admins_list as $admin) {
            $keyboard[] = [
                ['text' => (string) $admin['idadmin'], 'callback_data' => "none"],
                ['text' => $admin['nameadmin'], 'callback_data' => "none"],
                ['text' => "❌", 'callback_data' => "deladmin_" . $admin['idadmin']]
            ];
        }
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "👇🏻 لیست تمام ادمین های ربات",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ هیچ ادمین دیگری وجود ندارد."]);
    }
}

// --- حذف ادمین (Callback) ---
elseif (strpos($data, "deladmin_") === 0 && $from_id == ADMIN_ID) {
    $admin_id_to_delete = str_replace("deladmin_", "", $data);

    // جلوگیری از حذف خود ادمین اصلی
    if ($admin_id_to_delete == ADMIN_ID) {
        Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "شما نمی‌توانید خودتان را حذف کنید!", 'show_alert' => true]);
        return;
    }

    $pdo->prepare("DELETE FROM admins WHERE idadmin = ?")->execute([$admin_id_to_delete]);
    Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "✅ ادمین حذف شد."]);

    // رفرش لیست ادمین‌ها
    $admins_stmt = $pdo->query("SELECT idadmin, nameadmin FROM admins");
    $admins_list = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($admins_list) > 0) {
        $keyboard = [[['text' => "👤 آیدی ادمین", 'callback_data' => "none"], ['text' => "👤 نام ادمین", 'callback_data' => "none"], ['text' => "❌ حذف", 'callback_data' => "none"]]];
        foreach ($admins_list as $admin) {
            $keyboard[] = [['text' => (string) $admin['idadmin'], 'callback_data' => "none"], ['text' => $admin['nameadmin'], 'callback_data' => "none"], ['text' => "❌", 'callback_data' => "deladmin_" . $admin['idadmin']]];
        }
        Crypto1film('editMessageText', [
            'chat_id' => $chat_id, 'message_id' => $message_id,
            'text' => "👇🏻 لیست تمام ادمین های ربات (ادمین مورد نظر حذف شد)",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        Crypto1film('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "❌ تمام ادمین‌ها حذف شدند."]);
    }
}

// --- افزودن کانال خصوصی (مرحله اول: درخواست آیدی) ---
elseif ($text == "🔸 کانال خصوصی 🔸" && $user['step'] == 'addch1' && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ لطفا آیدی عددی کانال خصوصی را ارسال کنید.\n\n🔹نمونه: `-1009876262727`\n\n⚠️ ربات باید حتما در کانال ادمین باشد.",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'addcpr' WHERE id = ?")->execute([$from_id]);
}

// --- افزودن کانال خصوصی (مرحله دوم: دریافت آیدی و درخواست لینک) ---
elseif ($user['step'] == 'addcpr' && $text != "🔙 منوی پنل" && $is_admin) {
    // بررسی تکراری نبودن
    $stmt = $pdo->prepare("SELECT idoruser FROM channels WHERE idoruser = ?");
    $stmt->execute([$text]);
    if ($stmt->fetch()) {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ این کانال قبلا ثبت شده است"]);
        return;
    }

    // بررسی ادمین بودن و فرمت آیدی
    if (strpos($text, "-100") === 0 && getChatstats($text, API_KEY)) {
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ لطفا لینک خصوصی (invite link) کانال را ارسال کنید:",
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
        $pdo->prepare("UPDATE user SET step = 'addchpr1', step2 = ? WHERE id = ?")->execute([$text, $from_id]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ آیدی عددی اشتباه است یا ربات در کانال ادمین نیست."]);
    }
}

// --- افزودن کانال خصوصی (مرحله سوم: دریافت لینک و ذخیره) ---
elseif ($user['step'] == 'addchpr1' && $text != "🔙 منوی پنل" && $is_admin) {
    if (strpos($text, "https://t.me/+") === 0 || strpos($text, "https://t.me/joinchat/") === 0) {
        $channel_id = $user['step2'];
        $pdo->prepare("INSERT INTO channels (idoruser, link, type) VALUES (?, ?, 'telegram')")->execute([$channel_id, $text]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "کانال خصوصی با موفقیت افزوده شد.",
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
        $pdo->prepare("UPDATE user SET step = 'none', step2 = 'none' WHERE id = ?")->execute([$from_id]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ لینک ارسالی اشتباه است. باید لینک دعوت باشد."]);
    }
}

// --- افزودن لینک دلخواه (مرحله اول: درخواست نام) ---
elseif ($text == "🌐 لینک دلخواه" && $user['step'] == 'addch1' && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ نام لینک را ارسال کنید (این نام روی دکمه نمایش داده می‌شود):",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'addcpr2' WHERE id = ?")->execute([$from_id]);
}

// --- افزودن لینک دلخواه (مرحله دوم: دریافت نام و درخواست لینک) ---
elseif ($user['step'] == 'addcpr2' && $text != "🔙 منوی پنل" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ لینک مورد نظر خود را که با `https` یا `http` شروع میشود ارسال کنید:",
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'addchpr3', step2 = ? WHERE id = ?")->execute([$text, $from_id]);
}

// --- افزودن لینک دلخواه (مرحله سوم: دریافت لینک و ذخیره) ---
elseif ($user['step'] == 'addchpr3' && $text != "🔙 منوی پنل" && $is_admin) {
    if (filter_var($text, FILTER_VALIDATE_URL)) {
        $link_name = $user['step2'];
        $pdo->prepare("INSERT INTO channels (idoruser, link, type) VALUES (?, ?, 'customlink')")->execute([$link_name, $text]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ لینک دلخواه شما با موفقیت افزوده شد.",
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
        $pdo->prepare("UPDATE user SET step = 'none', step2 = 'none' WHERE id = ?")->execute([$from_id]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ لینک ارسالی اشتباه است."]);
    }
}

// --- مرحله دریافت پیش‌نمایش (تامنیل) ---
// این بخش زمانی اجرا می‌شود که ادمین یک تامنیل (عکس یا ویدیو) ارسال می‌کند.
elseif ($user['step'] == 'tumupload' && $text != "🔙 منوی پنل") {
    $thumbnail_data = null;
    $thumbnail_type = null;

    // --- بررسی نوع فایل ارسالی برای تامنیل ---
    if (isset($update->message->video)) {
        $thumbnail_data = $update->message->video->file_id;
        $thumbnail_type = 'video';
        $caption = $update->message->caption;
    } elseif (isset($update->message->photo)) {
        $photo = $update->message->photo;
        $thumbnail_data = $photo[count($photo) - 1]->file_id;
        $thumbnail_type = 'photo';
        $caption = $update->message->caption;
    }

    // --- اگر فایل معتبر بود ---
    if ($thumbnail_data) {
        // --- ذخیره اطلاعات تامنیل در step3 کاربر ---
        // فرمت: file_id^type^caption
        $step3_data = $thumbnail_data . "^" . $thumbnail_type . "^" . $caption;
        $user_update_stmt = $pdo->prepare("UPDATE user SET step = 'upload', step3 = ? WHERE id = ?");
        $user_update_stmt->execute([$step3_data, $from_id]);

        // --- ارسال پیام برای درخواست فایل‌های اصلی ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ لطفا تمام فایل های خود را ارسال کنید و در انتها روی گزینه ذخیره بزنید.\n\n🗂 شما می توانید پرونده(سند) ، ویدیو ، عکس ، ویس ، استیکر ، موزیک را ارسال کنید تا در ربات آپلود شود \n\n« 🚸 کپشن ها از خود فایل دریافت میشود »",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => "☑️ ذخیره فایل ها"]],
                    [['text' => "🔙 منوی پنل"]]
                ],
                'resize_keyboard' => true
            ])
        ]);
    } else {
        // --- اگر فایل نامعتبر بود ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "❌ پیش نمایش باید یک عکس یا ویدیو باشد",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "🔙 منوی پنل"]]],
                'resize_keyboard' => true
            ])
        ]);
    }
}

// --- مرحله دریافت پیش‌نمایش (تامنیل) ---
// این بخش زمانی اجرا می‌شود که ادمین یک تامنیل (عکس یا ویدیو) ارسال می‌کند.
elseif ($user['step'] == 'tumupload' && $text != "🔙 منوی پنل") {
    $thumbnail_data = null;
    $thumbnail_type = null;

    // --- بررسی نوع فایل ارسالی برای تامنیل ---
    if (isset($update->message->video)) {
        $thumbnail_data = $update->message->video->file_id;
        $thumbnail_type = 'video';
        $caption = $update->message->caption;
    } elseif (isset($update->message->photo)) {
        $photo = $update->message->photo;
        $thumbnail_data = $photo[count($photo) - 1]->file_id;
        $thumbnail_type = 'photo';
        $caption = $update->message->caption;
    }

    // --- اگر فایل معتبر بود ---
    if ($thumbnail_data) {
        // --- ذخیره اطلاعات تامنیل در step3 کاربر ---
        // فرمت: file_id^type^caption
        $step3_data = $thumbnail_data . "^" . $thumbnail_type . "^" . $caption;
        $user_update_stmt = $pdo->prepare("UPDATE user SET step = 'upload', step3 = ? WHERE id = ?");
        $user_update_stmt->execute([$step3_data, $from_id]);

        // --- ارسال پیام برای درخواست فایل‌های اصلی ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ لطفا تمام فایل های خود را ارسال کنید و در انتها روی گزینه ذخیره بزنید.\n\n🗂 شما می توانید پرونده(سند) ، ویدیو ، عکس ، ویس ، استیکر ، موزیک را ارسال کنید تا در ربات آپلود شود \n\n« 🚸 کپشن ها از خود فایل دریافت میشود »",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => "☑️ ذخیره فایل ها"]],
                    [['text' => "🔙 منوی پنل"]]
                ],
                'resize_keyboard' => true
            ])
        ]);
    } else {
        // --- اگر فایل نامعتبر بود ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "❌ پیش نمایش باید یک عکس یا ویدیو باشد",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "🔙 منوی پنل"]]],
                'resize_keyboard' => true
            ])
        ]);
    }
}

// --- مرحله دریافت فایل‌ها برای آپلود ---
// این بخش زمانی اجرا می‌شود که ادمین در حال ارسال فایل‌های اصلی است.
elseif ($user['step'] == 'upload' && $text != "☑️ ذخیره فایل ها" && $text != "🔙 منوی پنل") {

    // اگر ادمین به جای فایل، متن ارسال کند، به او خطا نمایش داده می‌شود.
    if ($text) {
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "❌ خطا: لطفاً یک فایل معتبر ارسال کنید.",
            'parse_mode' => "HTML"
        ]);
        exit(); // خروج از اسکریپت
    }

    // --- استخراج کد و زمان از step2 کاربر ---
    $parts = explode("|", $user['step2']);
    list($code, $zaman) = $parts;

    // --- استخراج اطلاعات تامنیل (اگر وجود داشته باشد) از step3 ---
    $thumbnail = $user['step3'] ?? 'none';

    // --- متغیرهایی برای نگهداری اطلاعات فایل ---
    $file_id = null;
    $file_size = 0;
    $caption = '';
    $type = '';

    // --- بررسی نوع فایل ارسالی و استخراج اطلاعات ---
    if (isset($update->message->video)) {
        $file_id = $update->message->video->file_id;
        $file_size = $update->message->video->file_size;
        $caption = $update->message->caption;
        $type = "video";
    } elseif (isset($update->message->sticker)) {
        $file_id = $update->message->sticker->file_id;
        $file_size = $update->message->sticker->file_size;
        $caption = $update->message->caption;
        $type = "sticker";
    } elseif (isset($update->message->audio)) {
        $file_id = $update->message->audio->file_id;
        $file_size = $update->message->audio->file_size;
        $caption = $update->message->caption;
        $type = "audio";
    } elseif (isset($update->message->voice)) {
        $file_id = $update->message->voice->file_id;
        $file_size = $update->message->voice->file_size;
        $caption = $update->message->caption;
        $type = "voice";
    } elseif (isset($update->message->document)) {
        $file_id = $update->message->document->file_id;
        $file_size = $update->message->document->file_size;
        $caption = $update->message->caption;
        $type = "document";
    } elseif (isset($update->message->photo)) {
        $photo = $update->message->photo;
        $file_id = $photo[count($photo) - 1]->file_id; // دریافت بالاترین کیفیت عکس
        $file_size = $photo[count($photo) - 1]->file_size;
        $caption = $update->message->caption;
        $type = "photo";
    }

    // --- اگر فایل معتبری دریافت شده بود ---
    if ($file_id) {
        // --- بررسی تنظیمات برای حذف لینک از کپشن ---
        if ($settings['captionlinkvaz'] == "on") {
            $caption = preg_replace("/(?!<a href=\".*\">)(.?https?:\\/\\/\\S+|@\\w+)/iu", "", $caption);
        }

        // --- تبدیل حجم فایل به فرمت خوانا ---
        $size = convert($file_size);

        // --- آماده‌سازی دستور SQL برای درج فایل در دیتابیس ---
        $insert_stmt = $pdo->prepare(
            "INSERT INTO files (code, msg_id, ghfl_ch, zd_filter, id, dl, pass, mahdodl, zaman, likes, dislikes, file_id, file_size, caption, type, thumbnail, fwlock)
             VALUES (?, 'none', 'on', 'off', ?, '1', 'none', 'none', ?, '0', '0', ?, ?, ?, ?, ?, 'on')"
        );
        // --- اجرای دستور با مقادیر مربوطه ---
        $insert_stmt->execute([$code, $from_id, $zaman, $file_id, $size, $caption, $type, $thumbnail]);

        // --- به‌روزرسانی step4 و step5 کاربر برای نگهداری اطلاعات آخرین فایل ---
        // این اطلاعات در مرحله بعد (ذخیره نهایی) استفاده می‌شود.
        $user_update_stmt = $pdo->prepare("UPDATE user SET step4 = ?, step5 = ? WHERE id = ?");
        $user_update_stmt->execute([$size, $type, $from_id]);
    }
}

// --- مرحله نهایی آپلود و ذخیره فایل‌ها ---
// این بخش زمانی اجرا می‌شود که ادمین پس از ارسال فایل‌ها، روی دکمه "ذخیره فایل ها" کلیک می‌کند.
elseif ($text == "☑️ ذخیره فایل ها" && $user['step'] == 'upload' && $is_admin) {

    // --- بررسی اینکه آیا حداقل یک فایل آپلود شده است ---
    // step5 حاوی نوع آخرین فایل است. اگر خالی باشد یعنی فایلی آپلود نشده.
    if (empty($user['step5'])) {
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "⚠️ <b>حداقل یک فایل ارسال کنید!</b>",
            'parse_mode' => 'HTML'
        ]);
        exit();
    }

    // --- استخراج اطلاعات ذخیره شده از مراحل قبلی ---
    $parts = explode("|", $user['step2']);
    list($code, $zaman) = $parts;
    $size = $user['step4']; // حجم آخرین فایل
    $thumbnail = $user['step3'] ?? 'none'; // اطلاعات تامنیل

    // --- دریافت نام کاربری ربات برای ساخت لینک ---
    $bot_info = Crypto1film('getMe');
    $bot_username = $bot_info->result->username ?? '';

    // --- ارسال پیام موقت "در حال آپلود" ---
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "<b>📥 در حال آپلود فایل...</b>",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([ // بازگشت به منوی پنل
            'keyboard' => [[['text' => "🔙 منوی پنل"]]],
            'resize_keyboard' => true
        ])
    ]);

    // --- متن نهایی که پس از آپلود نمایش داده می‌شود ---
    $final_text = "✅ <b> فایل ها با موفقیت آپلود شدند.</b>\n\n" .
                  "▪️ کد رسانه : <code>" . $code . "</code>\n" .
                  "▪️ حجم رسانه : <b>" . $size . "</b>\n\n" .
                  "▫️زمان آپلود : <code>" . $zaman . "</code>\n" .
                  "👤 توسط : <code>" . $from_id . "</code>\n\n" .
                  "🔗 <b>لینک دانلود:</b> https://t.me/" . $bot_username . "?start=dl_" . $code . " \n\n" .
                  "<b>🔻 برای ویرایش رسانه ، یکی از دکمه های زیر را انتخاب کنید:</b>";

    // --- کیبورد شیشه‌ای برای مدیریت فایل آپلود شده ---
    $final_keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => "🔗 لینک دریافت و مشاهده فایل", "url" => "https://t.me/" . $bot_username . "?start=dl_" . $code]],
            [['text' => "📢 ارسال به کانال", "callback_data" => "send_" . $code], ['text' => "قفل فروارد :✅", "callback_data" => "antiforward_" . $code]],
            [['text' => "📥 تنظیم محدودیت دانلود", "callback_data" => "mahdl_" . $code], ['text' => "🔐 تنظیم رمزعبور", "callback_data" => "Setpas_" . $code]],
            [['text' => "ضدفیلتر :❌", "callback_data" => "pnlzdfilter_" . $code], ['text' => "قفل کانال : ✅", "callback_data" => "ghflch_" . $code]],
            [['text' => "🔗 تنظیم لینک اختصاصی", "callback_data" => "speciallink_" . $code], ['text' => "🗑 حذف فایل", "callback_data" => "delu_" . $code]]
        ]
    ]);

    // --- بررسی وجود تامنیل و ارسال پیام نهایی ---
    if ($thumbnail != 'none') {
        $parts2 = explode("^", $thumbnail);
        list($fileid, $typetub, $captub) = $parts2;

        // --- ارسال تامنیل ---
        $upload_result = Crypto1film("send" . $typetub, [
            'chat_id' => $chat_id,
            $typetub => $fileid,
            'caption' => "<b>" . $captub . "</b>",
            'parse_mode' => "HTML"
        ]);
        $msg_id2 = $upload_result->result->message_id;

        // --- ارسال پیام نهایی به عنوان ریپلای به تامنیل ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'reply_to_message_id' => $msg_id2,
            'text' => $final_text,
            'parse_mode' => "HTML",
            'reply_markup' => $final_keyboard
        ]);
    } else {
        // --- اگر تامنیل نبود، فقط پیام نهایی را ارسال کن ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => $final_text,
            'parse_mode' => "HTML",
            'reply_markup' => $final_keyboard
        ]);
    }

    // --- پاک کردن وضعیت کاربر پس از اتمام آپلود برای جلوگیری از تداخل ---
    $reset_user_stmt = $pdo->prepare("UPDATE user SET step = 'none', step2 = 'none', step3 = 'none', step4 = 'none', step5 = 'none' WHERE id = ?");
    $reset_user_stmt->execute([$from_id]);
}

// --- دستور ورود به بخش "پوشه سازی رسانه ها" ---
// این بخش زمانی اجرا می‌شود که ادمین روی دکمه مربوطه در پنل کلیک می‌کند.
elseif ($text == "📂 پوشه سازی رسانه ها" && $is_admin) {
    // --- کیبورد منوی پوشه‌سازی ---
    $folder_menu = json_encode([
        'keyboard' => [
            [['text' => "➕ افزودن پوشه جدید"]],
            [['text' => "🔙 منوی پنل"], ['text' => "🗂 لیست پوشه ها"]]
        ],
        'resize_keyboard' => true
    ]);

    // --- ارسال پیام و کیبورد به ادمین ---
    Crypto1film('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "📂 *در این بخش میتوانید نسبت به قرار دادن رسانه های مورد نظر در یک پوشه و نمایش در منوی کاربران اقدام کنید*\n\n*🚸 توجه داشته باشید فایل های داخل پوشه هنگام نمایش ، نیاز به پسورد ، قفل کانال و ... ندارند.*\n\n🔻 یکی از گزینه های زیر را انتخاب کنید:",
        'parse_mode' => 'Markdown',
        'reply_markup' => $folder_menu
    ]);
}

// --- دستور "افزودن پوشه جدید" (مرحله اول: درخواست نام) ---
elseif ($text == "➕ افزودن پوشه جدید" && $is_admin) {
    // --- ارسال پیام برای درخواست نام پوشه ---
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ نام پوشه مورد نظر را وارد کنید:",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'keyboard' => [[['text' => "🔙 منوی پنل"]]],
            'resize_keyboard' => true
        ])
    ]);
    // --- به‌روزرسانی وضعیت کاربر برای دریافت نام پوشه ---
    $user_update_stmt = $pdo->prepare("UPDATE user SET step = 'addnewfolder' WHERE id = ?");
    $user_update_stmt->execute([$from_id]);
}

// --- دستور "افزودن پوشه جدید" (مرحله دوم: دریافت نام و درخواست کدها) ---
elseif ($user['step'] == 'addnewfolder' && $text != "🔙 منوی پنل" && $is_admin) {
    // --- بررسی اینکه آیا پوشه‌ای با این نام از قبل وجود دارد ---
    $folder_check_stmt = $pdo->prepare("SELECT name FROM folders WHERE name = ?");
    $folder_check_stmt->execute([$text]);

    if ($folder_check_stmt->fetch()) {
        // --- اگر نام تکراری بود، خطا نمایش داده می‌شود ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "❌ خطا: نام پوشه '" . htmlspecialchars($text) . "' قبلاً وجود دارد. لطفاً نام دیگری انتخاب کنید.",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "🔙 منوی پنل"]]],
                'resize_keyboard' => true
            ])
        ]);
    } else {
        // --- اگر نام جدید بود، درخواست کدهای رسانه ارسال می‌شود ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ کد رسانه های خود را با قرار دادن علامت « , » بین کدها ارسال کنید.\n🔻 مثال :\n<code>ir43sa,ok49vj,so8jfl</code>",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "🔙 منوی پنل"]]],
                'resize_keyboard' => true
            ])
        ]);
        // --- به‌روزرسانی وضعیت کاربر برای دریافت کدهای رسانه ---
        // نام پوشه در step2 ذخیره می‌شود.
        $user_update_stmt = $pdo->prepare("UPDATE user SET step = 'addnewfolder2', step2 = ? WHERE id = ?");
        $user_update_stmt->execute([$text, $from_id]);
    }
}

// --- دستور "افزودن پوشه جدید" (مرحله سوم: دریافت کدها و ایجاد پوشه) ---
elseif ($user['step'] == 'addnewfolder2' && $text != "🔙 منوی پنل" && $is_admin) {
    // --- نام پوشه از step2 کاربر خوانده می‌شود ---
    $folder_name = $user['step2'];
    // --- کدهای ورودی بر اساس کاما (,) جدا می‌شوند ---
    $codes = explode(",", $text);

    $valid_codes = [];
    $invalid_codes = [];

    // --- اعتبارسنجی هر کد در دیتابیس ---
    foreach ($codes as $code) {
        $code = trim($code); // حذف فاصله‌های اضافی
        if (!empty($code)) {
            $stmt = $pdo->prepare("SELECT code FROM files WHERE code = ? LIMIT 1");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $valid_codes[] = $code; // افزودن به لیست کدهای معتبر
            } else {
                $invalid_codes[] = $code; // افزودن به لیست کدهای نامعتبر
            }
        }
    }

    // --- اگر حداقل یک کد معتبر وجود داشت ---
    if (!empty($valid_codes)) {
        $valid_codes_str = implode(",", $valid_codes);
        // --- درج پوشه جدید در جدول 'folders' ---
        $insert_folder_stmt = $pdo->prepare("INSERT INTO folders (name, files) VALUES (?, ?)");
        $insert_folder_stmt->execute([$folder_name, $valid_codes_str]);

        // --- ساخت پیام موفقیت ---
        $success_message = "<b>✅ پوشه با موفقیت ایجاد شد.</b>\n\n" .
                           "🗂 نام پوشه: " . htmlspecialchars($folder_name) . "\n" .
                           "📁 تعداد فایل‌های معتبر: " . count($valid_codes) . "\n";

        // --- اگر کدهای نامعتبری وجود داشت، به پیام اضافه می‌شود ---
        if (!empty($invalid_codes)) {
            $success_message .= "\n⚠️ کدهای نامعتبر که اضافه نشدند:\n" .
                                "<code>" . implode(", ", $invalid_codes) . "</code>";
        }

        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => $success_message,
            'parse_mode' => "HTML",
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);

    } else {
        // --- اگر هیچ کد معتبری یافت نشد ---
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "❌ هیچ کد معتبری یافت نشد. لطفاً دوباره تلاش کنید.",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
    }

    // --- پاک کردن وضعیت کاربر ---
    $reset_user_stmt = $pdo->prepare("UPDATE user SET step = 'none', step2 = 'none' WHERE id = ?");
    $reset_user_stmt->execute([$from_id]);
}

// --- دستور "لیست پوشه ها" ---
elseif ($text == "🗂 لیست پوشه ها" && $is_admin) {
    // --- دریافت تمام پوشه‌ها از دیتابیس ---
    $folders_stmt = $pdo->query("SELECT id, name FROM folders ORDER BY id ASC");
    $folders = $folders_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($folders) > 0) {
        $keyboard = [];
        // --- هدر جدول ---
        $keyboard[] = [
            ['text' => "نام پوشه", 'callback_data' => "none"],
            ['text' => "محتوا", 'callback_data' => "none"],
            ['text' => "حذف ❌", 'callback_data' => "none"]
        ];

        // --- ایجاد دکمه برای هر پوشه ---
        // نمایش ۱۰ مورد اول برای صفحه‌بندی
        $display_folders = array_slice($folders, 0, 10);
        foreach ($display_folders as $folder) {
            $keyboard[] = [
                ['text' => $folder['name'], 'callback_data' => "none"],
                ['text' => "👁", 'callback_data' => "seefolder_" . $folder['id']],
                ['text' => "❌", 'callback_data' => "deletefolder_" . $folder['id']]
            ];
        }

        // --- دکمه صفحه‌بندی (اگر تعداد پوشه‌ها بیشتر از ۱۰ باشد) ---
        if (count($folders) > 10) {
            $keyboard[] = [['text' => "▶️ صفحه بعدی", 'callback_data' => "folderpage_2"]];
        }

        $total_folders = count($folders);
        $total_pages = ceil($total_folders / 10);

        Crypto1film('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🗂 تعداد کل پوشه های ایجاد شده : " . $total_folders . "\nصفحه: 1 از " . $total_pages,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);

    } else {
        // --- اگر هیچ پوشه‌ای وجود نداشت ---
        Crypto1film('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ هیچ پوشه ای اضافه نشده است .",
            'parse_mode' => 'HTML'
        ]);
    }
}

// --- مدیریت بازگشت‌های تماس (Callbacks) مربوط به لیست پوشه‌ها ---

// --- صفحه‌بندی لیست پوشه‌ها ---
elseif (strpos($data, 'folderpage_') === 0 && $is_admin) {
    $page = (int) str_replace('folderpage_', '', $data);
    if ($page < 1) $page = 1;

    $limit = 10;
    $offset = ($page - 1) * $limit;

    // دریافت کل پوشه‌ها برای شمارش
    $total_folders_stmt = $pdo->query("SELECT COUNT(id) FROM folders");
    $total_folders = $total_folders_stmt->fetchColumn();
    $total_pages = ceil($total_folders / $limit);

    // دریافت پوشه‌های صفحه فعلی
    $folders_stmt = $pdo->prepare("SELECT id, name FROM folders ORDER BY id ASC LIMIT ? OFFSET ?");
    $folders_stmt->execute([$limit, $offset]);
    $folders = $folders_stmt->fetchAll(PDO::FETCH_ASSOC);

    $keyboard = [];
    $keyboard[] = [
        ['text' => "نام پوشه", 'callback_data' => "none"],
        ['text' => "محتوا", 'callback_data' => "none"],
        ['text' => "حذف ❌", 'callback_data' => "none"]
    ];

    foreach ($folders as $folder) {
        $keyboard[] = [
            ['text' => $folder['name'], 'callback_data' => "none"],
            ['text' => "👁", 'callback_data' => "seefolder_" . $folder['id']],
            ['text' => "❌", 'callback_data' => "deletefolder_" . $folder['id']]
        ];
    }

    $navigation_row = [];
    if ($page > 1) {
        $navigation_row[] = ['text' => "◀️ صفحه قبلی", 'callback_data' => "folderpage_" . ($page - 1)];
    }
    if ($page < $total_pages) {
        $navigation_row[] = ['text' => "▶️ صفحه بعدی", 'callback_data' => "folderpage_" . ($page + 1)];
    }
    if (!empty($navigation_row)) {
        $keyboard[] = $navigation_row;
    }

    Crypto1film('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🗂 تعداد کل پوشه های ایجاد شده : " . $total_folders . "\nصفحه: " . $page . " از " . $total_pages,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ]);
}

// --- حذف یک پوشه ---
elseif (strpos($data, 'deletefolder_') === 0 && $is_admin) {
    $folder_id = str_replace('deletefolder_', '', $data);

    $delete_stmt = $pdo->prepare("DELETE FROM folders WHERE id = ?");
    $delete_stmt->execute([$folder_id]);

    Crypto1film('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => '✅ پوشه با موفقیت حذف شد.',
        'show_alert' => false
    ]);

    // رفرش کردن لیست پوشه‌ها (نمایش صفحه اول)
    $folders_stmt = $pdo->query("SELECT id, name FROM folders ORDER BY id ASC");
    $folders = $folders_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($folders) > 0) {
        $keyboard = [];
        $keyboard[] = [
            ['text' => "نام پوشه", 'callback_data' => "none"],
            ['text' => "محتوا", 'callback_data' => "none"],
            ['text' => "حذف ❌", 'callback_data' => "none"]
        ];

        $display_folders = array_slice($folders, 0, 10);
        foreach ($display_folders as $folder) {
            $keyboard[] = [
                ['text' => $folder['name'], 'callback_data' => "none"],
                ['text' => "👁", 'callback_data' => "seefolder_" . $folder['id']],
                ['text' => "❌", 'callback_data' => "deletefolder_" . $folder['id']]
            ];
        }

        if (count($folders) > 10) {
            $keyboard[] = [['text' => "▶️ صفحه بعدی", 'callback_data' => "folderpage_2"]];
        }

        $total_folders = count($folders);
        $total_pages = ceil($total_folders / 10);

        Crypto1film('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "🗂 تعداد کل پوشه های ایجاد شده : " . $total_folders . "\nصفحه: 1 از " . $total_pages,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        Crypto1film('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "❌ تمام پوشه‌ها حذف شدند.",
            'parse_mode' => 'HTML'
        ]);
    }
}

// --- مشاهده محتوای یک پوشه ---
elseif (strpos($data, 'seefolder_') === 0 && $is_admin) {
    $folder_id = str_replace('seefolder_', '', $data);

    $folder_stmt = $pdo->prepare("SELECT files FROM folders WHERE id = ?");
    $folder_stmt->execute([$folder_id]);
    $folder = $folder_stmt->fetch(PDO::FETCH_ASSOC);

    if ($folder && !empty($folder['files'])) {
        $file_codes = explode(",", $folder['files']);

        Crypto1film('answerCallbackQuery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => 'در حال ارسال فایل‌های پوشه...',
            'show_alert' => false
        ]);

        foreach ($file_codes as $code) {
            $code = trim($code);
            $files_stmt = $pdo->prepare("SELECT * FROM files WHERE code = ?");
            $files_stmt->execute([$code]);
            $files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($files as $file) {
                $caption = $file['caption'] ? convertToHyperlink($file['caption']) : '';
                $params = [
                    'chat_id' => $chat_id,
                    'caption' => $caption . "\n\n" . ($settings['signdownload'] ?? ''),
                    'parse_mode' => 'HTML',
                ];

                switch ($file['type']) {
                    case 'photo':
                        $params['photo'] = $file['file_id'];
                        Crypto1film('sendPhoto', $params);
                        break;
                    case 'video':
                        $params['video'] = $file['file_id'];
                        Crypto1film('sendVideo', $params);
                        break;
                    case 'audio':
                        $params['audio'] = $file['file_id'];
                        Crypto1film('sendAudio', $params);
                        break;
                    case 'voice':
                        $params['voice'] = $file['file_id'];
                        Crypto1film('sendVoice', $params);
                        break;
                    case 'document':
                        $params['document'] = $file['file_id'];
                        Crypto1film('sendDocument', $params);
                        break;
                     case 'sticker':
                        $params['sticker'] = $file['file_id'];
                        Crypto1film('sendSticker', $params);
                        break;
                }
                 // برای جلوگیری از محدودیت تلگرام، بین ارسال‌ها کمی تأخیر ایجاد می‌کنیم
                usleep(300000); // 0.3 ثانیه
            }
        }
    } else {
        Crypto1film('answerCallbackQuery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => '❌ پوشه یافت نشد یا خالی است.',
            'show_alert' => true
        ]);
    }
}

// --- دستور آمار ---
elseif ($text == "📊 آمار" && $is_admin) {
    // --- آمار کاربران ---
    $total_users = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
    $online_users_stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?");
    $online_users_stmt->execute([time() - 300]);
    $online_users = $online_users_stmt->fetchColumn();
    $twenty_four_hour_users_stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?");
    $twenty_four_hour_users_stmt->execute([time() - 86400]);
    $twenty_four_hour_users = $twenty_four_hour_users_stmt->fetchColumn();

    // --- آمار رسانه‌ها ---
    $admin_uploads = $pdo->query("SELECT COUNT(DISTINCT code) FROM files")->fetchColumn();
    $user_uploads = $pdo->query("SELECT COUNT(DISTINCT code) FROM userfiles")->fetchColumn();

    // --- آمار دانلودها ---
    $total_dl_stmt = $pdo->query("SELECT SUM(dl) AS total_dl FROM files");
    $total_dl = $total_dl_stmt->fetch(PDO::FETCH_ASSOC)['total_dl'] ?? 0;

    // --- وضعیت ربات ---
    $bot_status = ($settings['bot_mode'] == 'on') ? "✅" : "❌";

    $stats_text = "📊 *آمار کلی ربات:*\n\n" .
                  "📶 تعداد کل کاربران آنلاین: *" . $online_users . "*\n" .
                  "▪️ تعداد کل کاربران ۲۴ ساعت گذشته: *" . $twenty_four_hour_users . "*\n" .
                  "👥 تعداد کل کاربران ربات: *" . $total_users . "*\n\n" .
                  "▫️ کل رسانه‌های آپلود شده (توسط ادمین): *" . $admin_uploads . "* رسانه\n" .
                  "▪️ کل رسانه‌های آپلود شده (توسط کاربران): *" . $user_uploads . "* رسانه\n" .
                  "📥 تعداد کل دانلودها: *" . $total_dl . "* دانلود\n" .
                  "📣 *کاربران جوین شده در کانال با ربات:* " . ($settings['alljoin'] ?? 0) . " نفر\n\n" .
                  "🔘 وضعیت ربات: *" . $bot_status . "*\n";

    // --- آمار اشتراک (اگر ربات اشتراکی باشد) ---
    if ($settings['bottype'] == 'sub') {
        $active_subs = $pdo->query("SELECT COUNT(*) FROM user WHERE vip = 'yes'")->fetchColumn();
        $stats_text .= "💎 کل اشتراک های فعال: *" . $active_subs . "* اشتراک\n";
    }

    $date = jdate("Y/m/d");
    $ToDay = jdate("l");
    $time = date("H:i:s");
    $dateen = date("Y-m-d");
    $ToDayen = date("l");
    $timeen = date("H:i:s");

    Crypto1film('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $stats_text,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🚀 مشاهده آمار به صورت کامل", "callback_data" => "staticks"]],
                [['text' => (string) $date, "callback_data' => "none"], ['text' => (string) $ToDay, "callback_data" => "none"], ['text' => (string) $time, "callback_data" => "none"]],
                [['text' => (string) $dateen, "callback_data" => "none"], ['text' => (string) $ToDayen, "callback_data" => "none"], ['text' => (string) $timeen, "callback_data" => "none"]]
            ]
        ])
    ]);
}

// --- نمایش آمار کامل (Callback) ---
elseif ($data == "staticks" && $is_admin) {
    // آمار سرور
    $load = sys_getloadavg();
    $mem = memory_get_usage();
    $ver = phpversion();

    // آمار کاربران در بازه‌های زمانی مختلف
    $current_time = time();
    $one_hour_ago = $current_time - 3600;
    $twenty_four_hours_ago = $current_time - 86400;
    $yesterday_start = strtotime("yesterday");
    $yesterday_end = strtotime("today");
    $week_ago = $current_time - 604800;
    $month_ago = $current_time - 2592000;

    $total_users = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
    $online_users = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?"); $online_users->execute([$current_time - 300]); $online_users = $online_users->fetchColumn();
    $one_hour_users = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?"); $one_hour_users->execute([$one_hour_ago]); $one_hour_users = $one_hour_users->fetchColumn();
    $twenty_four_hour_users = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?"); $twenty_four_hour_users->execute([$twenty_four_hours_ago]); $twenty_four_hour_users = $twenty_four_hour_users->fetchColumn();
    $yesterday_users = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam BETWEEN ? AND ?"); $yesterday_users->execute([$yesterday_start, $yesterday_end]); $yesterday_users = $yesterday_users->fetchColumn();
    $week_users = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?"); $week_users->execute([$week_ago]); $week_users = $week_users->fetchColumn();
    $month_users = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?"); $month_users->execute([$month_ago]); $month_users = $month_users->fetchColumn();

    // آمار کلی
    $channels = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
    $adminall = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $folders = $pdo->query("SELECT COUNT(*) FROM folders")->fetchColumn();
    $fil2 = $pdo->query("SELECT COUNT(DISTINCT code) FROM files")->fetchColumn();
    $fil3 = $pdo->query("SELECT COUNT(DISTINCT code) FROM userfiles")->fetchColumn();
    $sub = $pdo->query("SELECT COUNT(*) FROM user WHERE vip = 'yes'")->fetchColumn();
    $video_count = $pdo->query("SELECT COUNT(*) FROM files WHERE type='video'")->fetchColumn();
    $audio_count = $pdo->query("SELECT COUNT(*) FROM files WHERE type IN ('audio','voice')")->fetchColumn();
    $photo_count = $pdo->query("SELECT COUNT(*) FROM files WHERE type='photo'")->fetchColumn();
    $document_count = $pdo->query("SELECT COUNT(*) FROM files WHERE type='document'")->fetchColumn();
    $video_count2 = $pdo->query("SELECT COUNT(*) FROM userfiles WHERE type='video'")->fetchColumn();
    $audio_count2 = $pdo->query("SELECT COUNT(*) FROM userfiles WHERE type IN ('audio','voice')")->fetchColumn();
    $photo_count2 = $pdo->query("SELECT COUNT(*) FROM userfiles WHERE type='photo'")->fetchColumn();
    $document_count2 = $pdo->query("SELECT COUNT(*) FROM userfiles WHERE type='document'")->fetchColumn();
    $total_likes = $pdo->query("SELECT COALESCE(SUM(likes), 0) FROM files")->fetchColumn();
    $total_dislikes = $pdo->query("SELECT COALESCE(SUM(dislikes), 0) FROM files")->fetchColumn();
    $total_dl = $pdo->query("SELECT SUM(dl) FROM files")->fetchColumn() ?? 0;
    $banned_users_count = $pdo->query("SELECT COUNT(*) FROM user WHERE step = 'ban'")->fetchColumn();

    // وضعیت‌ها
    $vazaitads = ($settings['vaziat'] == "on") ? "✅ روشن" : "❌ خاموش";
    $reactcheck = ($settings['reactcheck'] == "on") ? "✅ روشن" : "❌ خاموش";
    $seencheck = ($settings['seencheck'] == "on") ? "✅ روشن" : "❌ خاموش";
    $a4 = ($settings['bot_mode'] == "on") ? "✅ روشن" : "❌ خاموش";

    $full_stats_text = "📊 *بخش آمار ربات به صورت کامل*\n\n" .
        "📶 تعداد کل کاربران آنلاین: *" . $online_users . "*\n" .
        "▪️ تعداد کل کاربران یک ساعت گذشته: *" . $one_hour_users . "*\n" .
        "▪️ تعداد کل کاربران ۲۴ ساعت گذشته: *" . $twenty_four_hour_users . "*\n" .
        "▫️ تعداد کل کاربران دیروز: *" . $yesterday_users . "*\n" .
        "▫️ تعداد کل کاربران هفته گذشته: *" . $week_users . "*\n" .
        "▫️ تعداد کل کاربران ماه گذشته: *" . $month_users . "*\n\n" .
        "👥 تعداد کل کاربران ربات: *" . $total_users . "*\n" .
        "➖➖➖➖➖➖\n" .
        "👤 *آمار رسانه ها (آپلود شده توسط ادمین):*\n" .
        "🔸 کل رسانه ها: *" . $fil2 . "*\n" .
        "🔸 ویدیو: *" . $video_count . "* | صوت: *" . $audio_count . "* | عکس: *" . $photo_count . "* | سند: *" . $document_count . "*\n\n" .
        "👤 *آمار رسانه ها (آپلود شده توسط کاربران):*\n" .
        "🔸 کل رسانه ها: *" . $fil3 . "*\n" .
        "🔸 ویدیو: *" . $video_count2 . "* | صوت: *" . $audio_count2 . "* | عکس: *" . $photo_count2 . "* | سند: *" . $document_count2 . "*\n" .
        "➖➖➖➖➖➖\n" .
        "📂 تعداد کل پوشه ها: *" . $folders . "*\n" .
        "➖➖➖➖➖➖\n" .
        "💎 *آمار اشتراک ها:*\n" .
        "💎 کل اشتراک های فعال: *" . $sub . "*\n" .
        "➖➖➖➖➖➖\n" .
        "⭐️ *آمار لایک، دیسلایک و دانلود:*\n" .
        "📥 تعداد کل دانلودها: *" . $total_dl . "*\n" .
        "👍🏻 تعداد کل لایک ها: *" . $total_likes . "*\n" .
        "👎🏻 تعداد کل دیسلایک ها: *" . $total_dislikes . "*\n" .
        "➖➖➖➖➖➖\n" .
        "🚀 *آمار دیگر:*\n" .
        "🔘 کاربران مسدود شده: *" . $banned_users_count . "*\n" .
        "🔘 وضعیت سین اجباری: *" . $seencheck . "*\n" .
        "🔘 وضعیت ری اکشن اجباری: *" . $reactcheck . "*\n" .
        "🔘 وضعیت تبلیغات: *" . $vazaitads . "*\n" .
        "🔘 کانال/لینک جوین اجباری: *" . $channels . "*\n" .
        "🔘 ادمین های ربات: *" . $adminall . "*\n" .
        "➖➖➖➖➖➖\n" .
        "◽️ *وضعیت سرور:*\n" .
        "▫️ میانگین انتقال داده: *" . $load[0] . "*\n" .
        "▫️ استفاده از رم: *" . convert($mem) . "*\n" .
        "▫️ نسخه PHP: *" . $ver . "*\n\n" .
        "🔘️ وضعیت ربات: *" . $a4 . "*";

    $date = jdate("Y/m/d");
    $ToDay = jdate("l");
    $time = date("H:i:s");
    $dateen = date("Y-m-d");
    $ToDayen = date("l");
    $timeen = date("H:i:s");

    Crypto1film('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $full_stats_text,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🚀 مشاهده آمار کلی", "callback_data" => "lowstats"]],
                [['text' => (string) $date, "callback_data" => "none"], ['text' => (string) $ToDay, "callback_data" => "none"], ['text' => (string) $time, "callback_data" => "none"]],
                [['text' => (string) $dateen, "callback_data" => "none"], ['text' => (string) $ToDayen, "callback_data" => "none"], ['text' => (string) $timeen, "callback_data" => "none"]]
            ]
        ])
    ]);
}

// --- بازگشت به آمار کلی ---
elseif ($data == "lowstats" && $is_admin) {
     // این کد تکراری است و در دستور آمار اصلی قرار دارد، اینجا برای کامل بودن منطق بازگشت قرار داده شده
    $total_users = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
    $online_users_stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?"); $online_users_stmt->execute([time() - 300]); $online_users = $online_users_stmt->fetchColumn();
    $twenty_four_hour_users_stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE spam > ?"); $twenty_four_hour_users_stmt->execute([time() - 86400]); $twenty_four_hour_users = $twenty_four_hour_users_stmt->fetchColumn();
    $admin_uploads = $pdo->query("SELECT COUNT(DISTINCT code) FROM files")->fetchColumn();
    $user_uploads = $pdo->query("SELECT COUNT(DISTINCT code) FROM userfiles")->fetchColumn();
    $total_dl = $pdo->query("SELECT SUM(dl) FROM files")->fetchColumn() ?? 0;
    $bot_status = ($settings['bot_mode'] == 'on') ? "✅" : "❌";
    $stats_text = "📊 *آمار کلی ربات:*\n\n" . "📶 تعداد کل کاربران آنلاین: *" . $online_users . "*\n" . "▪️ تعداد کل کاربران ۲۴ ساعت گذشته: *" . $twenty_four_hour_users . "*\n" . "👥 تعداد کل کاربران ربات: *" . $total_users . "*\n\n" . "▫️ کل رسانه‌های آپلود شده (توسط ادمین): *" . $admin_uploads . "* رسانه\n" . "▪️ کل رسانه‌های آپلود شده (توسط کاربران): *" . $user_uploads . "* رسانه\n" . "📥 تعداد کل دانلودها: *" . $total_dl . "* دانلود\n" . "📣 *کاربران جوین شده در کانال با ربات:* " . ($settings['alljoin'] ?? 0) . " نفر\n\n" . "🔘 وضعیت ربات: *" . $bot_status . "*\n";
    if ($settings['bottype'] == 'sub') {
        $active_subs = $pdo->query("SELECT COUNT(*) FROM user WHERE vip = 'yes'")->fetchColumn();
        $stats_text .= "💎 کل اشتراک های فعال: *" . $active_subs . "* اشتراک\n";
    }
    $date = jdate("Y/m/d"); $ToDay = jdate("l"); $time = date("H:i:s"); $dateen = date("Y-m-d"); $ToDayen = date("l"); $timeen = date("H:i:s");
    Crypto1film('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $stats_text,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "🚀 مشاهده آمار به صورت کامل", "callback_data" => "staticks"]], [['text' => (string) $date, "callback_data' => "none"], ['text' => (string) $ToDay, "callback_data" => "none"], ['text' => (string) $time, "callback_data" => "none"]], [['text' => (string) $dateen, "callback_data" => "none"], ['text' => (string) $ToDayen, "callback_data" => "none"], ['text' => (string) $timeen, "callback_data" => "none"]]]])
    ]);
}

// --- بخش ارسال همگانی (Broadcast) ---

// --- منوی اصلی ارسال همگانی ---
elseif ($text == "📩 ارسال همگانی" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "<b>🔻 یکی از گزینه های زیر را انتخاب کنید:</b>",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "📨 فروارد همگانی"], ['text' => "💌 پیام همگانی"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
}

// --- درخواست فروارد همگانی ---
elseif ($text == "📨 فروارد همگانی" && $is_admin) {
    // --- بررسی اینکه آیا همگانی دیگری در حال انجام است ---
    if ($settings['is_all'] == 'no') {
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "<b>✔️ لطفا پیام مورد نظر خود را فروارد کنید:</b>",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "🔙 منوی پنل"]]],
                'resize_keyboard' => true
            ])
        ]);
        // --- تنظیم وضعیت کاربر برای دریافت پیام فرواردی ---
        $pdo->prepare("UPDATE user SET step = 'forall' WHERE id = ?")->execute([$from_id]);
    } else {
        // --- اگر همگانی دیگری فعال بود، خطا نمایش داده می‌شود ---
        $user_count = $pdo->query("SELECT COUNT(id) FROM user")->fetchColumn();
        $remaining = $user_count - $settings['tedad'];
        $estimated_time = Takhmin($remaining);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "❌ خطا برای انجام عملیات همگانی\n\nادمینی دیگر اقدام به همگانی کرده و هنوز عملیات به اتمام نرسیده است. لطفا تا پایان عملیات قبلی صبر کنید.",
            'parse_mode' => "HTML"
        ]);
    }
}

// --- دریافت پیام برای فروارد همگانی ---
elseif ($user['step'] == 'forall' && $text != "🔙 منوی پنل" && $is_admin) {
    // --- تنظیمات دیتابیس برای شروع فروارد ---
    $pdo->prepare("UPDATE settings SET `forall` = 'true', tedad = '0', chat_id = ?, msg_id = ?, is_all = ? WHERE botid = ?")
        ->execute([$chat_id, $message_id, $from_id, $botid]);

    $user_count = $pdo->query("SELECT COUNT(id) FROM user")->fetchColumn();
    $estimated_time = Takhmin($user_count);

    // --- ارسال پیام شروع عملیات به ادمین ---
    $response = Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "📣 *پیام در صف ارسال قرار گرفت!*\n\n✅ *بعد از اتمام فروارد، به شما اطلاع داده میشود.*\n\n👥 تعداد اعضای ربات: *" . $user_count . "* نفر\n🚀 زمان تخمینی ارسال: *" . $estimated_time . "* دقیقه",
        'parse_mode' => "Markdown"
    ]);

    // --- ذخیره آیدی پیام وضعیت برای آپدیت‌های بعدی (در کرون جاب) ---
    if ($response && $response->ok) {
        $status_message_id = $response->result->message_id;
        $pdo->prepare("UPDATE settings SET sendedit = ? WHERE botid = ?")->execute([$status_message_id, $botid]);
    }

    // --- ریست کردن وضعیت کاربر ---
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
}

// --- درخواست پیام همگانی (متنی) ---
elseif ($text == "💌 پیام همگانی" && $is_admin) {
    if ($settings['is_all'] == 'no') {
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "🔺 *نکات مهم از ارسال پیام همگانی:*\n\n🔹 شما فقط میتوانید متن با فرمت HTML ارسال کنید.\n🔸 برای ارسال عکس، فیلم و... از بخش `فروارد همگانی` استفاده کنید.\n\n📩 لطفا پیام متنی را در اینجا ارسال کنید:",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "🔙 منوی پنل"]]],
                'resize_keyboard' => true
            ])
        ]);
        $pdo->prepare("UPDATE user SET step = 'sendall' WHERE id = ?")->execute([$from_id]);
    } else {
        $user_count = $pdo->query("SELECT COUNT(id) FROM user")->fetchColumn();
        $remaining = $user_count - $settings['tedad'];
        $estimated_time = Takhmin($remaining);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "❌ خطا برای انجام عملیات همگانی\n\nادمینی دیگر اقدام به همگانی کرده و هنوز عملیات به اتمام نرسیده است. لطفا تا پایان عملیات قبلی صبر کنید.",
            'parse_mode' => "HTML"
        ]);
    }
}

// --- دریافت متن برای پیام همگانی ---
elseif ($user['step'] == 'sendall' && $text != "🔙 منوی پنل" && $is_admin) {
    // --- تنظیمات دیتابیس برای شروع ارسال پیام ---
    $pdo->prepare("UPDATE settings SET sendall = 'true', tedad = '0', text = ?, is_all = ? WHERE botid = ?")
        ->execute([$text, $from_id, $botid]);

    $user_count = $pdo->query("SELECT COUNT(id) FROM user")->fetchColumn();
    $estimated_time = Takhmin($user_count);

    // --- ارسال پیام شروع عملیات به ادمین ---
    $response = Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "📣 *پیام در صف ارسال قرار گرفت!*\n\n✅ *بعد از اتمام ارسال، به شما اطلاع داده میشود.*\n\n👥 تعداد اعضای ربات: *" . $user_count . "* نفر\n🚀 زمان تخمینی ارسال: *" . $estimated_time . "* دقیقه",
        'parse_mode' => "Markdown"
    ]);

    // --- ذخیره آیدی پیام وضعیت ---
    if ($response && $response->ok) {
        $status_message_id = $response->result->message_id;
        $pdo->prepare("UPDATE settings SET sendedit = ? WHERE botid = ?")->execute([$status_message_id, $botid]);
    }

    // --- ریست کردن وضعیت کاربر ---
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
}

// --- بخش شخصی سازی (Personalization) ---

// --- منوی اصلی شخصی سازی ---
elseif ($text == "🎨 شخصی سازی" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "<b>🔻 یکی از گزینه های زیر را انتخاب کنید:</b>",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "🔘 فعال/غیرفعال سازی دکمه ها"]],
                [['text' => "♻️ تغییر کاربری ربات"], ['text' => "♻️ تغییر متن جوین اجباری"]],
                [['text' => "⚪️ روشن/خاموش ربات"], ['text' => "🚀 تغییر کد آپلود سریع"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
}

// --- روشن/خاموش کردن ربات ---
elseif ($text == "⚪️ روشن/خاموش ربات" && $is_admin) {
    if ($settings['bot_mode'] == 'on') {
        $pdo->prepare("UPDATE settings SET bot_mode = 'off' WHERE botid = ?")->execute([$botid]);
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❎ ربات خاموش شد."]);
    } else {
        $pdo->prepare("UPDATE settings SET bot_mode = 'on' WHERE botid = ?")->execute([$botid]);
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "✅ ربات روشن شد."]);
    }
}

// --- تغییر کد آپلود سریع (مرحله اول) ---
elseif ($text == "🚀 تغییر کد آپلود سریع" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ کد یا دستور مورد نظر خود را برای دسترسی به آپلود سریع ارسال کنید:\n\n🔻مثال :\n<code>/up</code>",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'fastupload' WHERE id = ?")->execute([$from_id]);
}

// --- تغییر کد آپلود سریع (مرحله دوم) ---
elseif ($user['step'] == 'fastupload' && $text != "🔙 منوی پنل" && $is_admin) {
    $pdo->prepare("UPDATE settings SET fastupload = ? WHERE botid = ?")->execute([$text, $botid]);
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
    Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "✅ با موفقیت جایگزین شد."]);
}

// --- تغییر متن جوین اجباری (مرحله اول) ---
elseif ($text == "♻️ تغییر متن جوین اجباری" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ متن مورد نظر خود برای نمایش در جوین اجباری وارد کنید:",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'changejointext' WHERE id = ?")->execute([$from_id]);
}

// --- تغییر متن جوین اجباری (مرحله دوم) ---
elseif ($user['step'] == 'changejointext' && $text != "🔙 منوی پنل" && $is_admin) {
    $pdo->prepare("UPDATE settings SET joinchanneltext = ? WHERE botid = ?")->execute([$text, $botid]);
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
    Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "✅ با موفقیت جایگزین شد."]);
}

// --- منوی فعال/غیرفعال سازی دکمه‌ها ---
elseif (($text == "🔘 فعال/غیرفعال سازی دکمه ها" || $data == "sakhsisazimenu") && $is_admin) {
    // بازنشانی وضعیت کاربر
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);

    // تعریف دکمه‌ها و وضعیت فعلی آنها از دیتابیس
    $buttons = [
        "sendbut" => ["text" => "آپلود توسط کاربر", "db" => "sendbut"],
        "supbut" => ["text" => "پشتیبانی", "db" => "supportbut"],
        "topdlbut" => ["text" => "پربازدید ها", "db" => "topdlbut"],
        "newdlbut" => ["text" => "جدیدترین ها", "db" => "newdlbut"],
        "likedlbut" => ["text" => "محبوب ترین ها", "db" => "likedlbut"],
        "showlikes" => ["text" => "نمایش لایک و دیسلایک", "db" => "showlikes"],
        "showdownload" => ["text" => "نمایش تعداد دانلود", "db" => "showdownload"],
        "autoacc" => ["text" => "تایید خودکار فایل کاربران", "db" => "autoacc"]
    ];

    $keyboard = [[['text' => "🔻 تغییر وضعیت 🔻", "callback_data" => "none"], ['text' => "🔸 دستورات 🔸", "callback_data" => "none"]]];
    foreach ($buttons as $key => $button) {
        $status_icon = ($settings[$button['db']] == "on") ? "✅ روشن" : "❌ خاموش";
        $keyboard[] = [['text' => $status_icon, "callback_data" => "pchange_" . $key], ['text' => $button['text'], "callback_data" => "none"]];
    }
    $keyboard[] = [['text' => "🔹 تغییر متن شروع 🔹", "callback_data" => "changetextstart"]];

    $message_data = [
        'chat_id' => $chat_id,
        'text' => "✔️ برای شخصی سازی ربات یکی از گزینه های زیر را انتخاب کنید:",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode(["inline_keyboard" => $keyboard])
    ];

    // اگر از طریق callback آمده، پیام را ویرایش کن، در غیر این صورت پیام جدید بفرست
    if ($data == "sakhsisazimenu") {
        $message_data['message_id'] = $message_id;
        Crypto1film("editMessageText", $message_data);
    } else {
        Crypto1film("sendMessage", $message_data);
    }
}

// --- مدیریت Callback برای تغییر وضعیت دکمه‌ها ---
elseif (strpos($data, "pchange_") === 0 && $is_admin) {
    $key_to_change = str_replace("pchange_", "", $data);

    $buttons_db_map = [
        "sendbut" => "sendbut", "supbut" => "supportbut", "topdlbut" => "topdlbut",
        "newdlbut" => "newdlbut", "likedlbut" => "likedlbut", "showlikes" => "showlikes",
        "showdownload" => "showdownload", "autoacc" => "autoacc"
    ];

    $db_column = $buttons_db_map[$key_to_change];
    $current_value = $settings[$db_column];
    $new_value = ($current_value == "on") ? "off" : "on";

    // آپدیت دیتابیس
    $pdo->prepare("UPDATE settings SET `$db_column` = ? WHERE botid = ?")->execute([$new_value, $botid]);

    // واکشی مجدد تنظیمات برای نمایش به‌روز
    $settings_stmt = $pdo->prepare("SELECT * FROM `settings` WHERE `botid` = ?");
    $settings_stmt->execute([$botid]);
    $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

    // ساخت مجدد کیبورد با وضعیت جدید
    $buttons = [
        "sendbut" => ["text" => "آپلود توسط کاربر", "db" => "sendbut"], "supbut" => ["text" => "پشتیبانی", "db" => "supportbut"],
        "topdlbut" => ["text" => "پربازدید ها", "db" => "topdlbut"], "newdlbut" => ["text" => "جدیدترین ها", "db" => "newdlbut"],
        "likedlbut" => ["text" => "محبوب ترین ها", "db" => "likedlbut"], "showlikes" => ["text" => "نمایش لایک و دیسلایک", "db" => "showlikes"],
        "showdownload" => ["text" => "نمایش تعداد دانلود", "db" => "showdownload"], "autoacc" => ["text" => "تایید خودکار فایل کاربران", "db" => "autoacc"]
    ];

    $keyboard = [[['text' => "🔻 تغییر وضعیت 🔻", "callback_data" => "none"], ['text' => "🔸 دستورات 🔸", "callback_data" => "none"]]];
    foreach ($buttons as $key => $button) {
        $status_icon = ($settings[$button['db']] == "on") ? "✅ روشن" : "❌ خاموش";
        $keyboard[] = [['text' => $status_icon, "callback_data" => "pchange_" . $key], ['text' => $button['text'], "callback_data" => "none"]];
    }
    $keyboard[] = [['text' => "🔹 تغییر متن شروع 🔹", "callback_data" => "changetextstart"]];

    // ویرایش پیام با کیبورد جدید
    Crypto1film("editMessageReplyMarkup", [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => json_encode(["inline_keyboard' => $keyboard])
    ]);
}

// --- تغییر کاربری ربات (رایگان/اشتراکی) ---
elseif ($text == "♻️ تغییر کاربری ربات" && $is_admin) {
    $current_type = ($settings['bottype'] == 'free') ? "نسخه رایگان" : "نسخه اشتراکی";
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "🔻 یکی از حالت های زیر را برای ربات مشخص کنید:\n\n" .
                  "🌐 *رایگان:* در این حالت ربات برای همه کاربران رایگان خواهد بود.\n" .
                  "💎 *اشتراکی:* در این حالت کاربران برای دانلود فایل ها نیاز به خرید اشتراک دارند.\n\n" .
                  "🔹 نسخه کنونی ربات: *" . $current_type . "*",
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "♾ نسخه رایگان ♾"], ['text' => "💰 نسخه اشتراکی 💰"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
}

// --- تنظیم به نسخه رایگان ---
elseif ($text == "♾ نسخه رایگان ♾" && $is_admin) {
    if ($settings['bottype'] == 'free') {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "⚠️ نسخه رایگان از قبل فعال بوده است"]);
    } else {
        $pdo->prepare("UPDATE settings SET bottype = 'free', subbuy = 'off', accountbut = 'off' WHERE botid = ?")->execute([$botid]);
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "✅ نسخه رایگان فعال شد"]);
    }
}

// --- تنظیم به نسخه اشتراکی ---
elseif ($text == "💰 نسخه اشتراکی 💰" && $is_admin) {
    if ($settings['bottype'] == 'sub') {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "⚠️ نسخه اشتراکی از قبل فعال بوده است"]);
    } else {
        $pdo->prepare("UPDATE settings SET bottype = 'sub', subbuy = 'on', accountbut = 'on' WHERE botid = ?")->execute([$botid]);
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "✅ نسخه اشتراکی فعال شد"]);
    }
}

// --- مدیریت Callback برای "تغییر متن شروع" ---
elseif ($data == "changetextstart" && $is_admin) {
    $startdefault_status = ($settings['startdefault'] == 'on') ? "✅ فعال" : "❌ غیرفعال";

    Crypto1film("editMessageText", [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "✔️ متن شروع را ارسال کنید\n\n" .
                  "✅ *راهنمای استفاده از امکانات متن:*\n" .
                  "🔘 *نقل قول:* `<blockquote>text</blockquote>`\n" .
                  "🔘 *برجسته:* `<b>text</b>`\n" .
                  "🔘 *کج:* `<i>text</i>`\n" .
                  "🔘 *کد:* `<code>text</code>`\n\n" .
                  "🚸 همچنین میتوانید از دکمه «متن پیشرفته شروع» ، یک متن پیشرفته برای بخش شروع ست کنید.",
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "💬 متن پیشرفته شروع", "callback_data' => "changestartdefault"], ['text' => $startdefault_status, "callback_data" => "changestartdefault"]],
                [['text' => "🔙 لغو", "callback_data" => "sakhsisazimenu"]]
            ]
        ])
    ]);
    // تنظیم وضعیت کاربر برای دریافت متن شروع
    $pdo->prepare("UPDATE user SET step = 'settextstart' WHERE id = ?")->execute([$from_id]);
}

// --- دریافت متن شروع جدید ---
elseif ($user['step'] == 'settextstart' && $is_admin) {
    // آپدیت متن شروع در دیتابیس
    $pdo->prepare("UPDATE settings SET starttext = ? WHERE botid = ?")->execute([$text, $botid]);

    // بازنشانی وضعیت کاربر
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);

    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "متن با موفقیت تغییر پیدا کرد",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'inline_keyboard' => [[['text' => "🔙 برگشت به منو شخصی سازی", "callback_data" => "sakhsisazimenu"]]]
        ])
    ]);
}

// --- فعال/غیرفعال کردن متن پیشرفته شروع ---
elseif ($data == "changestartdefault" && $is_admin) {
    $new_status = ($settings['startdefault'] == 'on') ? 'off' : 'on';
    $pdo->prepare("UPDATE settings SET startdefault = ? WHERE botid = ?")->execute([$new_status, $botid]);

    $message_text = ($new_status == 'on') ? "✅ متن پیشرفته فعال شد." : "✅ متن پیشرفته غیرفعال شد.";

    Crypto1film("deleteMessage", ['chat_id' => $chat_id, 'message_id' => $message_id]);
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => $message_text,
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'inline_keyboard' => [[['text' => "🔙 برگشت به منو شخصی سازی", "callback_data" => "sakhsisazimenu"]]]
        ])
    ]);

    // بازنشانی وضعیت کاربر
    $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
}

// --- بخش جوین اجباری (Forced Join) ---

// --- منوی اصلی جوین اجباری ---
elseif ($text == "🔐 جوین اجباری" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "📣 به بخش مدیریت کانال جوین اجباری و لینک خوش آمدید\n\n🔻 یکی از گزینه های زیر را انتخاب کنید:",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "➕ افزودن کانال یا لینک"]],
                [['text' => "🔙 منوی پنل"], ['text' => "📣 لیست کانال ها و لینک ها"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
}

// --- منوی افزودن کانال/لینک ---
elseif ($text == "➕ افزودن کانال یا لینک" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "🔻 نوع کانال یا لینک مورد نظر را از طریق گزینه های زیر انتخاب کنید:",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "🔹 کانال عمومی 🔹"], ['text' => "🔸 کانال خصوصی 🔸"]],
                [['text' => "🌐 لینک دلخواه"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
    $pdo->prepare("UPDATE user SET step = 'addch1' WHERE id = ?")->execute([$from_id]);
}

// --- افزودن کانال عمومی (مرحله اول) ---
elseif ($text == "🔹 کانال عمومی 🔹" && $user['step'] == 'addch1' && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✔️ لطفا یوزرنیم کانال عمومی خود را بدون @ ارسال کنید\n\n⚠️ ربات را باید در کانال ادمین کرده و تمام دسترسی‌ها را به آن بدهید.",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'addchpub' WHERE id = ?")->execute([$from_id]);
}

// --- افزودن کانال عمومی (مرحله دوم) ---
elseif ($user['step'] == 'addchpub' && $text != "🔙 منوی پنل" && $is_admin) {
    $channel_username = "@" . str_replace("@", "", $text);

    // بررسی تکراری نبودن کانال
    $stmt = $pdo->prepare("SELECT idoruser FROM channels WHERE idoruser = ?");
    $stmt->execute([$channel_username]);
    if ($stmt->fetch()) {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ این کانال قبلا ثبت شده است"]);
        return;
    }

    // بررسی ادمین بودن ربات در کانال
    if (getChatstats($channel_username, API_KEY)) {
        $link = "https://t.me/" . str_replace("@", "", $text);
        $pdo->prepare("INSERT INTO channels (idoruser, link, type) VALUES (?, ?, 'telegram')")->execute([$channel_username, $link]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "کانال " . $channel_username . " با موفقیت افزوده شد.",
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
        ]);
        $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ ربات هنوز در کانال " . $channel_username . " ادمین نیست!"]);
    }
}

// --- لیست کانال‌ها و لینک‌ها ---
elseif ($text == "📣 لیست کانال ها و لینک ها" && $is_admin) {
    $channels_stmt = $pdo->query("SELECT idoruser, link, type FROM channels");
    $channels = $channels_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($channels) > 0) {
        $keyboard = [];
        foreach ($channels as $channel) {
            $name = ($channel['type'] == 'telegram') ? getChannelTitle($channel['idoruser']) : $channel['idoruser'];
            if ($name) { // فقط در صورتی که نام کانال دریافت شود، نمایش بده
                $keyboard[] = [['text' => $name, 'url' => $channel['link']], ['text' => "❌ حذف", 'callback_data' => "delc_" . $channel['idoruser']]];
            }
        }
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "👇🏻 لیست تمام کانال ها و لینک های اجباری",
            'parse_mode' => "HTML",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ هیچ لینک یا کانالی تنظیم نشده."]);
    }
}

// --- حذف کانال یا لینک (Callback) ---
elseif (strpos($data, "delc_") === 0 && $is_admin) {
    $idoruser_to_delete = str_replace("delc_", "", $data);

    $pdo->prepare("DELETE FROM channels WHERE idoruser = ?")->execute([$idoruser_to_delete]);

    Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "✅ حذف شد."]);

    // رفرش کردن لیست
    $channels_stmt = $pdo->query("SELECT idoruser, link, type FROM channels");
    $channels = $channels_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($channels) > 0) {
        $keyboard = [];
        foreach ($channels as $channel) {
            $name = ($channel['type'] == 'telegram') ? getChannelTitle($channel['idoruser']) : $channel['idoruser'];
             if ($name) {
                $keyboard[] = [['text' => $name, 'url' => $channel['link']], ['text' => "❌ حذف", 'callback_data' => "delc_" . $channel['idoruser']]];
            }
        }
        Crypto1film('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "👇🏻 لیست تمام کانال ها و لینک های جوین اجباری\n\n(مورد انتخاب شده حذف شد)",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        Crypto1film('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "❌ تمام کانال ها و لینک ها حذف شده است."
        ]);
    }
}

// --- بخش آپدیت ربات ---
elseif ($text == "♻️ آپدیت ربات" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "♻️ *نسخه کنونی:* `v3.0.0`\n\n" .
                  "⌨️ *طراحی و توسعه:* `factweb.ir`\n" .
                  "🔔 *کانال ما:* @factwebir\n\n" .
                  "▪️ *دریافت آخرین آپدیت:* zaya.io/maxupload",
        'parse_mode' => "Markdown",
        'disable_web_page_preview' => true
    ]);
}

// --- بخش ری‌اکشن/سین اجباری ---

// --- منوی اصلی ---
elseif ($text == "👁‍🗨 ری اکشن/سین اجباری" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "<b>🔻 یکی از گزینه های زیر را انتخاب کنید:</b>",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "👌🏻 تنظیم ری اکشن اجباری"], ['text' => "👁‍🗨 تنظیم سین اجباری"]],
                [['text' => "🔙 منوی پنل"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
}

// --- منوی تنظیم سین اجباری ---
elseif (($text == "👁‍🗨 تنظیم سین اجباری" || $data == "seennoww") && $is_admin) {
    $seen_settings_stmt = $pdo->query("SELECT * FROM seen LIMIT 1");
    $seen_settings = $seen_settings_stmt->fetch(PDO::FETCH_ASSOC);

    $status = ($seen_settings['checkseen'] == 'on') ? "✅ روشن" : "❌ خاموش";
    $channel = ($seen_settings['channelseen'] == 'none') ? "نامشخص" : "@" . $seen_settings['channelseen'];

    $keyboard = [
        [['text' => "🔻 تغییر وضعیت 🔻", "callback_data" => "none"], ['text' => "🔸 دستورات 🔸", "callback_data" => "none"]],
        [['text' => $status, "callback_data" => "seenchange"], ['text' => "👁 وضعیت سین اجباری:", "callback_data" => "none"]],
        [['text' => $channel, "callback_data" => "seenchannelchange"], ['text' => "📢 کانال سین اجباری:", "callback_data" => "none"]],
        [['text' => $seen_settings['adadseen'] . " پست آخر", "callback_data" => "seentedadchange"], ['text' => "♾ تعداد سین اجباری:", "callback_data" => "none"]],
        [['text' => $seen_settings['timefake'] . " ثانیه", "callback_data' => "seetimefakechange"], ['text' => "🕰 تایم فیک:", "callback_data" => "none"]]
    ];

    $message_data = [
        'chat_id' => $chat_id,
        'text' => "👁 *وضعیت سین اجباری کانال:*\n\n🔻 برای تغییر وضعیت، دکمه مورد نظر را انتخاب کنید.\n\n⚠️ *توجه:* در صورت فعال بودن سین اجباری، ری اکشن اجباری غیرفعال می‌شود.",
        'parse_mode' => "Markdown",
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ];

    if ($data == "seennoww") {
        $message_data['message_id'] = $message_id;
        Crypto1film('editMessageText', $message_data);
    } else {
        Crypto1film('sendMessage', $message_data);
    }
}

// --- تغییر وضعیت سین اجباری (Callback) ---
elseif ($data == "seenchange" && $is_admin) {
    $seen_settings_stmt = $pdo->query("SELECT checkseen FROM seen LIMIT 1");
    $current_status = $seen_settings_stmt->fetchColumn();

    if ($current_status == 'on') {
        $pdo->query("UPDATE seen SET checkseen = 'off'");
        Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "سین اجباری خاموش شد"]);
    } else {
        // غیرفعال کردن ری‌اکشن اجباری هنگام فعال کردن سین اجباری
        $pdo->query("UPDATE seen SET checkseen = 'on'");
        $pdo->query("UPDATE reaction SET checkreact = 'off'");
        Crypto1film('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "سین اجباری روشن شد (ری‌اکشن اجباری غیرفعال شد)"]);
    }
    // رفرش کردن منو
    // برای سادگی، فرض می‌کنیم ادمین دوباره روی دکمه می‌زند یا از منو استفاده می‌کند.
    // در حالت ایده‌آل، باید منو را با `editMessageReplyMarkup` به‌روزرسانی کرد.
}

// --- تغییر کانال سین اجباری (مرحله اول) ---
elseif ($data == "seenchannelchange" && $is_admin) {
    Crypto1film("sendMessage", [
        'chat_id' => $chat_id,
        'text' => "✅ کانال مورد نظر خود را وارد کنید:\n\n⚠️ کانال باید عمومی باشد.\n⚠️ یوزرنیم کانال را بدون @ وارد کنید.",
        'parse_mode' => "HTML",
        'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 منوی پنل"]]], 'resize_keyboard' => true])
    ]);
    $pdo->prepare("UPDATE user SET step = 'setseenshannel' WHERE id = ?")->execute([$from_id]);
}

// --- تغییر کانال سین اجباری (مرحله دوم) ---
elseif ($user['step'] == 'setseenshannel' && $text != "🔙 منوی پنل" && $is_admin) {
    if (strpos($text, "@") !== false) {
        Crypto1film("sendMessage", ['chat_id' => $chat_id, 'text' => "❌ آیدی کانال باید بدون @ ارسال شود"]);
    } else {
        $pdo->prepare("UPDATE seen SET channelseen = ?")->execute([$text]);
        $pdo->prepare("UPDATE user SET step = 'none' WHERE id = ?")->execute([$from_id]);
        Crypto1film("sendMessage", [
            'chat_id' => $chat_id,
            'text' => "✔️ کانال با موفقیت ست شد",
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "🔙 برگشت به منو سین اجباری", "callback_data" => "seennoww"]]]])
        ]);
    }
}

// --- بازگشت به منوی پنل ---
elseif ($text == "🔙 منوی پنل" && $is_admin) {
    // پاک کردن وضعیت کاربر برای جلوگیری از تداخل در دستورات بعدی
    $pdo->prepare("UPDATE user SET step = 'none', step2 = 'none', step3 = 'none', step4 = 'none', step5 = 'none' WHERE id = ?")->execute([$from_id]);

    // ارسال مجدد منوی اصلی پنل ادمین
    Crypto1film('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "🌟 *به منوی اصلی پنل بازگشتید.*",
        'parse_mode' => 'Markdown',
        'reply_markup' => $panelmenu
    ]);
}


?>
