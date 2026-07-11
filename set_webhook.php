<?php
require_once __DIR__ . '/config.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$url = "$protocol://$host$dir/bot.php";

$resp = @file_get_contents("https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook?url=" . urlencode($url));
$data = json_decode($resp, true);

?><html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Set Webhook</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#212121;color:#e0e0e0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{background:#2b2b2b;border-radius:16px;padding:30px;width:100%;max-width:460px}
h1{font-size:20px;color:#fff;margin-bottom:12px}
pre{background:#1e1e1e;padding:14px;border-radius:10px;font-size:13px;overflow-x:auto;color:#ccc;margin:16px 0}
.ok{color:#7bff7b}
.fail{color:#ff7b7b}
.btn{display:inline-block;padding:12px 24px;background:#5b9aff;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;margin-top:16px}
</style>
</head>
<body>
<div class="card">
<h1>🔗 Webhook Registration</h1>
<?php if ($resp === false): ?>
<p class="fail">❌ Failed to contact Telegram API.</p>
<?php elseif ($data && $data['ok']): ?>
<p class="ok">✅ Webhook registered successfully!</p>
<pre>URL: <?=htmlspecialchars($url)?>
Status: <?=htmlspecialchars($data['description'] ?? 'OK')?></pre>
<?php else: ?>
<p class="fail">❌ Error: <?=htmlspecialchars($data['description'] ?? 'Unknown')?></p>
<?php endif; ?>
<a class="btn" href="https://t.me/<?=htmlspecialchars(explode(':',BOT_TOKEN)[0])?>" target="_blank">Open Bot</a>
</div>
</body>
</html>
