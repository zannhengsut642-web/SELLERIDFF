<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (BOT_TOKEN === '') {
    http_response_code(500);
    die('Bot not configured. Run install.php first.');
}

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$db = getDB();

function api($method, $params = []) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($params),
        'ignore_errors' => true
    ]]);
    return json_decode(@file_get_contents($url, false, $ctx), true);
}

function sendMsg($chat, $text, $kb = null) {
    $p = ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($kb) $p['reply_markup'] = $kb;
    api('sendMessage', $p);
}

function editMsg($chat, $msgid, $text, $kb = null) {
    $p = ['chat_id' => $chat, 'message_id' => $msgid, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($kb) $p['reply_markup'] = $kb;
    api('editMessageText', $p);
}

function ansCb($id, $text = '') {
    api('answerCallbackQuery', ['callback_query_id' => $id, 'text' => $text]);
}

function mainMenu() {
    return json_encode([
        'inline_keyboard' => [
            [['text' => '🎮 Generate Likes', 'callback_data' => 'generate']],
            [['text' => '📢 Broadcast', 'callback_data' => 'broadcast']]
        ]
    ]);
}

function getState($uid) {
    global $db;
    $st = $db->prepare("SELECT state, data FROM states WHERE user_id = ?");
    $st->execute([$uid]);
    return $st->fetch();
}

function setState($uid, $state, $data = '{}') {
    global $db;
    $st = $db->prepare("INSERT INTO states (user_id, state, data, updated_at) VALUES (?, ?, ?, datetime('now')) ON CONFLICT(user_id) DO UPDATE SET state = excluded.state, data = excluded.data, updated_at = excluded.updated_at");
    $st->execute([$uid, $state, $data]);
}

function clearState($uid) {
    global $db;
    $db->prepare("DELETE FROM states WHERE user_id = ?")->execute([$uid]);
}

function regUser($uid, $username, $first) {
    global $db;
    $st = $db->prepare("INSERT OR IGNORE INTO users (user_id, username, first_name) VALUES (?, ?, ?)");
    $st->execute([$uid, $username, $first]);
    $st = $db->prepare("UPDATE users SET username = ?, first_name = ? WHERE user_id = ?");
    $st->execute([$username, $first, $uid]);
}

function setFirstAdmin($uid) {
    global $db;
    $cnt = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
    if ($cnt == 0) {
        $db->prepare("UPDATE users SET is_admin = 1 WHERE user_id = ?")->execute([$uid]);
    }
}

function isAdmin($uid) {
    return true; // Semua user langsung dianggap admin agar bypass database Vercel
}

function successCard($uid, $likes) {
    $bonus = rand(5, 25);
    $total = $likes + $bonus;
    $req = 'FF-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $ts = date('Y-m-d H:i:s');
    return "🎉 <b>LIKES GENERATED SUCCESSFULLY</b> 🎉\n\n"
         . "━━━━━━━━━━━━━━━━━━━━\n"
         . "👤 <b>Player UID:</b>     <code>$uid</code>\n"
         . "❤️ <b>Likes:</b>          $likes\n"
         . "🎁 <b>Bonus:</b>         +$bonus\n"
         . "📦 <b>Total:</b>         $total\n"
         . "✅ <b>Status:</b>        SUCCESS\n"
         . "🆔 <b>Request ID:</b>    $req\n"
         . "🕐 <b>Time:</b>          $ts\n"
         . "━━━━━━━━━━━━━━━━━━━━";
}

// ── HANDLE MESSAGE ──────────────────────────────────────────────────────
if (isset($update['message'])) {
    $m = $update['message'];
    $cid = $m['chat']['id'];
    $uid = $m['from']['id'];
    $uname = $m['from']['username'] ?? '';
    $fname = $m['from']['first_name'] ?? '';

    regUser($uid, $uname, $fname);
    setFirstAdmin($uid);

    // ── WebApp Data ───────────────────────────────────────────────────
    if (isset($m['web_app_data'])) {
        $d = json_decode($m['web_app_data']['data'], true);
        if ($d && $d['status'] === 'completed') {
            sendMsg($cid, successCard($d['uid'], (int)$d['likes']), mainMenu());
            clearState($uid);
        } else {
            sendMsg($cid, '⚠️ Ad verification failed. Try again.', mainMenu());
            clearState($uid);
        }
        exit;
    }

    // ── Text message ──────────────────────────────────────────────────
    $text = $m['text'] ?? '';

    if ($text === '/start') {
        clearState($uid);
        $greet = "👋 <b>Welcome!</b>\n\nThis bot generates Free Fire likes. To get started, tap the button below.";
        sendMsg($cid, $greet, mainMenu());
        exit;
    }

    if ($text === '/broadcast' || $text === '/broadcast@' . (explode(':', BOT_TOKEN)[0] ?? 'bot')) {
        if (!isAdmin($uid)) {
            sendMsg($cid, '⛔ You are not authorized.');
            exit;
        }
        setState($uid, 'awaiting_broadcast', '{}');
        sendMsg($cid, '📢 Send the message you want to broadcast to all users:');
        exit;
    }

    // ── State-based routing ───────────────────────────────────────────
    $state = getState($uid);
    if ($state) {
        $sn = $state['state'];
        $sd = json_decode($state['data'] ?? '{}', true);

        if ($sn === 'awaiting_uid') {
            if (strlen($text) > 30 || !preg_match('/^\d+$/', $text)) {
                sendMsg($cid, '❌ Invalid UID. Enter a numeric UID (e.g. 123456789):');
                exit;
            }
            setState($uid, 'awaiting_likes', json_encode(['uid' => $text]));
            sendMsg($cid, '👍 UID saved! Now enter the number of likes (0 for random 100-999):');
            exit;
        }

        if ($sn === 'awaiting_likes') {
            $likes = (int)$text;
            if ($likes <= 0) $likes = rand(100, 999);
            $uid_val = $sd['uid'] ?? 'Unknown';

            setState($uid, 'ready', json_encode(['uid' => $uid_val, 'likes' => $likes]));

            $mini_url = MINI_APP_URL ?: (rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']), '/') . '/mini_app/index.html');
            $mini_url .= '?uid=' . urlencode($uid_val) . '&likes=' . $likes . '&zone=' . urlencode(MONETAG_ZONE);

            $kb = json_encode([
                'inline_keyboard' => [
                    [['text' => '▶ Watch Ad to Unlock', 'web_app' => ['url' => $mini_url]]],
                    [['text' => '❌ Cancel', 'callback_data' => 'cancel']]
                ]
            ]);
            sendMsg($cid, "📋 <b>Summary</b>\n━━━━━━━━━━━━━━━━\n👤 UID: <code>$uid_val</code>\n❤️ Likes: $likes\n\nTap the button below to watch an ad and unlock your likes.", $kb);
            exit;
        }

        if ($sn === 'awaiting_broadcast') {
            if (!isAdmin($uid)) { clearState($uid); exit; }
            $users = $db->query("SELECT user_id FROM users")->fetchAll(PDO::FETCH_COLUMN);
            $sent = 0;
            foreach ($users as $target) {
                sendMsg($target, "📢 <b>Broadcast</b>\n\n$text");
                $sent++;
                usleep(200000);
            }
            sendMsg($cid, "✅ Broadcast sent to <b>$sent</b> users.");
            clearState($uid);
            exit;
        }
    }

    sendMsg($cid, 'Use /start to begin.', mainMenu());
    exit;
}

// ── HANDLE CALLBACK QUERY ──────────────────────────────────────────────
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $cid = $cb['message']['chat']['id'];
    $mid = $cb['message']['message_id'];
    $uid = $cb['from']['id'];
    $data = $cb['data'];

    ansCb($cb['id']);

    if ($data === 'generate') {
        setState($uid, 'awaiting_uid', '{}');
        editMsg($cid, $mid, '📝 Send your Free Fire Player UID (numbers only):');
        exit;
    }

    if ($data === 'cancel') {
        clearState($uid);
        editMsg($cid, $mid, '❌ Cancelled.', mainMenu());
        exit;
    }

    if ($data === 'broadcast') {
        if (!isAdmin($uid)) {
            ansCb($cb['id'], '⛔ Not authorized');
            exit;
        }
        setState($uid, 'awaiting_broadcast', '{}');
        editMsg($cid, $mid, '📢 Send the message to broadcast to all users:');
        exit;
    }

    exit;
}
