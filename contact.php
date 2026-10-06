<?php
/**
 * POST /contact.php   Contact form handler for siddharthaparmar.com
 *
 * Works on any Apache, LiteSpeed or nginx host with PHP 7.4+.
 * No dependencies. Uses Resend if a key is configured, otherwise mail().
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit(json_encode(['error' => 'Method not allowed']));
}

$cfg = __DIR__ . '/config.php';
if (is_readable($cfg)) { require_once $cfg; }

if (!defined('CONTACT_TO'))        define('CONTACT_TO', 'hello@siddharthaparmar.com');
if (!defined('CONTACT_FROM'))      define('CONTACT_FROM', 'website@siddharthaparmar.com');
if (!defined('CONTACT_FROM_NAME')) define('CONTACT_FROM_NAME', 'siddharthaparmar.com');
if (!defined('RESEND_API_KEY'))    define('RESEND_API_KEY', (string) getenv('RESEND_API_KEY'));

/* ---------- simple per-IP rate limit: 5 sends per hour ---------- */
$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$dir  = sys_get_temp_dir() . '/sp_contact';
@mkdir($dir, 0700, true);
$file = $dir . '/' . sha1($ip);
$hits = [];
if (is_readable($file)) {
    $hits = array_filter(
        (array) json_decode((string) file_get_contents($file), true),
        fn($t) => is_numeric($t) && $t > time() - 3600
    );
}
if (count($hits) >= 5) {
    http_response_code(429);
    exit(json_encode(['error' => 'Too many messages. Try again later.']));
}

/* ---------- read and validate ---------- */
$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

if (!empty($data['company_url'])) {          // honeypot
    exit(json_encode(['ok' => true]));
}

if (!function_exists('mb_substr')) {          // mbstring is not guaranteed on shared hosts
    function mb_substr($s, $start, $len = null, $enc = null) { return substr($s, $start, $len); }
}
$clean = fn($k, $max) => mb_substr(trim((string) ($data[$k] ?? '')), 0, $max);

$name    = $clean('name', 200);
$email   = $clean('email', 200);
$org     = $clean('org', 200);
$extra   = $clean('extra', 300);
$message = $clean('message', 5000);

$labels  = [
    'investor' => 'Investor',
    'partner'  => 'Partner or client',
    'press'    => 'Press',
    'speaking' => 'Speaking',
    'aurum-sell-mine'   => 'Aurum: sell a mine',
    'aurum-buy-mine'    => 'Aurum: buy a mine',
    'aurum-buy-copper'  => 'Aurum: buy copper',
    'aurum-sell-copper' => 'Aurum: sell copper',
    'aurum-investor'    => 'Aurum: investor brief',
];
$enquiry = $labels[(string) ($data['enquiry'] ?? '')] ?? 'General';

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing or invalid fields']));
}
// header injection guard
if (preg_match('/[\r\n]/', $name . $email . $org)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid input']));
}

$e    = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$subj = sprintf('[%s] %s%s', $enquiry, $name, $org !== '' ? ' - ' . $org : '');

$html = '<h2 style="font:600 18px system-ui;margin:0 0 14px">' . $e($enquiry) . ' enquiry</h2>'
      . '<table style="font:14px system-ui;border-collapse:collapse">'
      . '<tr><td style="padding:4px 14px 4px 0;color:#666">Name</td><td>' . $e($name) . '</td></tr>'
      . '<tr><td style="padding:4px 14px 4px 0;color:#666">Email</td><td>' . $e($email) . '</td></tr>'
      . '<tr><td style="padding:4px 14px 4px 0;color:#666">Organisation</td><td>' . ($org !== '' ? $e($org) : '&mdash;') . '</td></tr>'
      . '<tr><td style="padding:4px 14px 4px 0;color:#666">Detail</td><td>' . ($extra !== '' ? $e($extra) : '&mdash;') . '</td></tr>'
      . '</table>'
      . '<p style="font:14px/1.6 system-ui;white-space:pre-wrap;margin-top:18px">' . $e($message) . '</p>';

$sent = false;

/* ---------- preferred path: Resend API ---------- */
if (RESEND_API_KEY !== '' && function_exists('curl_init')) {
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from'     => CONTACT_FROM_NAME . ' <' . CONTACT_FROM . '>',
            'to'       => [CONTACT_TO],
            'reply_to' => $email,
            'subject'  => $subj,
            'html'     => $html,
        ]),
    ]);
    $res  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        $sent = true;
    } else {
        error_log('contact.php resend ' . $code . ' ' . (string) $res);
    }
}

/* ---------- fallback: PHP mail() ---------- */
if (!$sent) {
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . CONTACT_FROM_NAME . ' <' . CONTACT_FROM . '>',
        'Reply-To: ' . $email,
    ]);
    $sent = @mail(CONTACT_TO, $subj, $html, $headers, '-f' . CONTACT_FROM);
}

if (!$sent) {
    http_response_code(502);
    exit(json_encode(['error' => 'Send failed']));
}

$hits[] = time();
@file_put_contents($file, json_encode(array_values($hits)), LOCK_EX);

echo json_encode(['ok' => true]);
