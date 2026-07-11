<?php
$step = $_POST['step'] ?? 'form';
$token = $_POST['token'] ?? '';
$zone = $_POST['zone'] ?? '';
$error = '';
$success = '';

function api($method, $token, $params = []) {
    $url = "https://api.telegram.org/bot$token/$method";
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($params),
        'ignore_errors' => true
    ]]);
    return json_decode(@file_get_contents($url, false, $ctx), true);
}

if ($step === 'install' && $token && $zone) {
    $me = api('getMe', $token);
    if (!$me || !($me['ok'] ?? false)) {
        $error = 'Invalid bot token. Check with @BotFather.';
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $base = "$protocol://$host$dir";
        $mini_url = "$base/mini_app/index.html";
        $webhook_url = "$base/bot.php";

        $config = "<?php\n";
        $config .= "define('BOT_TOKEN', '" . str_replace("'", "\\'", $token) . "');\n";
        $config .= "define('MONETAG_ZONE', '" . str_replace("'", "\\'", $zone) . "');\n";
        $config .= "define('MINI_APP_URL', '" . str_replace("'", "\\'", $mini_url) . "');\n";
        $config .= "define('DB_PATH', __DIR__ . '/database.db');\n";

        if (@file_put_contents(__DIR__ . '/config.php', $config) === false) {
            $error = 'Cannot write config.php. Check file permissions.';
        } else {
            $wh = api('setWebhook', $token, ['url' => $webhook_url]);
            if (!$wh || !($wh['ok'] ?? false)) {
                $error = 'Failed to set webhook: ' . ($wh['description'] ?? 'unknown error');
            } else {
                $db = new PDO("sqlite:" . __DIR__ . "/database.db");
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $db->exec("CREATE TABLE IF NOT EXISTS users (
                    user_id INTEGER PRIMARY KEY,
                    username TEXT DEFAULT '',
                    first_name TEXT DEFAULT '',
                    is_admin INTEGER DEFAULT 0,
                    created_at TEXT DEFAULT (datetime('now'))
                )");
                $db->exec("CREATE TABLE IF NOT EXISTS states (
                    user_id INTEGER PRIMARY KEY,
                    state TEXT NOT NULL,
                    data TEXT DEFAULT '{}',
                    updated_at TEXT DEFAULT (datetime('now'))
                )");
                $botname = $me['result']['username'] ?? 'yourbot';
                $success = "Installation complete! Start your bot: <a href='https://t.me/$botname' target='_blank'>@$botname</a>";
            }
        }
    }
}
?><html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bot Install</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#212121;color:#e0e0e0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{background:#2b2b2b;border-radius:16px;padding:30px;width:100%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,.4)}
h1{font-size:22px;color:#fff;margin-bottom:6px;display:flex;align-items:center;gap:8px}
h1 span{font-size:26px}
.sub{color:#aaa;font-size:13px;margin-bottom:24px}
label{display:block;font-size:13px;font-weight:600;color:#ccc;margin-bottom:5px;margin-top:16px}
input[type=text],input[type=password]{width:100%;padding:12px 14px;border:1px solid #444;border-radius:10px;font-size:15px;background:#1e1e1e;color:#fff;outline:none;transition:border .2s}
input:focus{border-color:#5b9aff}
.btn{width:100%;padding:13px;border:none;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer;margin-top:22px;transition:opacity .2s}
.btn-primary{background:#5b9aff;color:#fff}
.btn-primary:hover{opacity:.9}
.btn-primary:disabled{opacity:.5;cursor:not-allowed}
.error{background:#3d1f1f;color:#ff7b7b;padding:12px 16px;border-radius:10px;font-size:13px;margin-top:16px;border:1px solid #662222}
.success{background:#1a3d1f;color:#7bff7b;padding:12px 16px;border-radius:10px;font-size:13px;margin-top:16px;border:1px solid #226622}
.success a{color:#7bff7b;text-decoration:underline}
.hint{font-size:12px;color:#888;margin-top:4px}
.note{font-size:12px;color:#888;margin-top:18px;padding:12px;background:#1e1e1e;border-radius:10px;line-height:1.5}
.loading{display:none;text-align:center;margin-top:16px}
.loading span{display:inline-block;width:8px;height:8px;border-radius:50%;background:#5b9aff;margin:0 3px;animation:bounce 1.4s infinite ease-in-out both}
.loading span:nth-child(1){animation-delay:-.32s}
.loading span:nth-child(2){animation-delay:-.16s}
@keyframes bounce{0%,80%,100%{transform:scale(0)}40%{transform:scale(1)}}
</style>
</head>
<body>
<div class="card">
<h1><span>🤖</span> Bot Setup</h1>
<p class="sub">Configure your Free Fire Likes Generator bot</p>

<?php if ($error): ?>
<div class="error"><?=htmlspecialchars($error)?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="success"><?=$success?></div>
<div style="margin-top:16px;text-align:center">
<a href="https://t.me/<?=htmlspecialchars($botname ?? 'yourbot')?>" target="_blank" style="color:#5b9aff;font-weight:600;text-decoration:none">Open in Telegram →</a>
</div>
<?php else: ?>
<form method="post" onsubmit="installBtn.disabled=true;loading.style.display='block';this.querySelector('.error')?.remove()">
<input type="hidden" name="step" value="install">
<label>Bot Token</label>
<input type="password" name="token" value="<?=htmlspecialchars($token)?>" placeholder="123456:ABC-DEF1234ghIkl..." required>
<div class="hint">From <a href="https://t.me/BotFather" target="_blank" style="color:#5b9aff">@BotFather</a></div>
<label>Monetag Zone ID</label>
<input type="text" name="zone" value="<?=htmlspecialchars($zone ?: '11192933')?>" placeholder="11192933" required>
<div class="hint">Found in your Monetag ad code (data-zone attribute)</div>
<button type="submit" id="installBtn" class="btn btn-primary">Install &amp; Set Webhook</button>
<div class="loading" id="loading"><span></span><span></span><span></span></div>
</form>
<div class="note">
<strong>What happens:</strong> Config is written, webhook is registered with Telegram, SQLite database is created. Your first /start user becomes admin.
</div>
<?php endif; ?>
</div>
<script>document.querySelector('form')?.addEventListener('submit',function(){document.getElementById('loading').style.display='block'});</script>
</body>
</html>
