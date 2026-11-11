<?php
// send.php — header-forging mailer. Requires a PHP host with mail() enabled.
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$from_name  = trim($_POST['from_name']  ?? '');
$from_email = trim($_POST['from_email'] ?? '');
$reply_to   = trim($_POST['reply_to']   ?? '');
$to         = trim($_POST['to']         ?? '');
$subject    = trim($_POST['subject']    ?? '');
$message    = $_POST['message'] ?? '';

// basic sanity, not a gate
if (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'bad from address']); exit;
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'bad to address']); exit;
}
if ($subject === '') {
    echo json_encode(['ok' => false, 'error' => 'empty subject']); exit;
}

// header forging
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>\r\n";
if ($reply_to !== '') {
    $headers .= "Reply-To: {$reply_to}\r\n";
}
$headers .= "X-Mailer: PHP/" . phpversion();

// CRLF injection guard on user-controlled header values
$from_email = str_replace(["\r", "\n"], '', $from_email);
$reply_to   = str_replace(["\r", "\n"], '', $reply_to);
$subject    = str_replace(["\r", "\n"], '', $subject);

$ok = @mail($to, $subject, $message, $headers);

echo json_encode(['ok' => (bool)$ok]);
