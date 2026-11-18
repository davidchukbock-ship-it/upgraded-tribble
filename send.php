<?php
declare(strict_types=1);
// Receives the request form. Validates, stores a backup, emails the company, appends to Google Sheets.
$cfgFile = __DIR__ . '/config.php';
$cfg = is_file($cfgFile) ? require $cfgFile : [];
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
function done(bool $ok, string $lang, string $err = '', bool $json = true): never {
    if ($json) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => $ok, 'error' => $err], JSON_UNESCAPED_UNICODE); exit; }
    header('Location: ' . ($lang === 'ru' ? '/ru/' : '/') . 'contact/?sent=' . ($ok ? '1' : '0')); exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
$g = static fn(string $k, int $n = 200): string => mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string)($_POST[$k] ?? ''))), 0, $n);
$lang = ($_POST['lang'] ?? 'en') === 'ru' ? 'ru' : 'en';
$msg = static fn(string $en, string $ru): string => $lang === 'ru' ? $ru : $en;

if ($g('website') !== '') done(true, $lang, '', $wantsJson);                 // honeypot: pretend success
$t = (int)($_POST['t'] ?? 0);
if ($t > 0 && (time() - intdiv($t, 1000)) < ($cfg['min_seconds'] ?? 3)) done(false, $lang, $msg('Please wait a moment and send again.', 'Подождите секунду и отправьте ещё раз.'), $wantsJson);
if (empty($_POST['consent'])) done(false, $lang, $msg('Please confirm consent.', 'Подтвердите согласие.'), $wantsJson);

$d = ['solution' => $g('solution', 60), 'name' => $g('name', 120), 'company' => $g('company', 120), 'region' => $g('region', 120),
      'team_size' => $g('team_size', 40), 'contact' => $g('contact', 120), 'message' => mb_substr(trim((string)($_POST['message'] ?? '')), 0, 2000),
      'page' => $g('page', 40), 'lang' => $lang];
if ($d['solution'] === '' || $d['company'] === '' || $d['region'] === '' || $d['contact'] === '')
    done(false, $lang, $msg('Please fill in all required fields.', 'Заполните все обязательные поля.'), $wantsJson);
$isMail = (bool)filter_var($d['contact'], FILTER_VALIDATE_EMAIL);
if (!$isMail && !preg_match('/^\+?[0-9 ()\-]{7,20}$/', $d['contact']))
    done(false, $lang, $msg('Enter a valid email or phone.', 'Укажите корректный email или телефон.'), $wantsJson);

// storage dir (blocked from web by .htaccess)
$dir = __DIR__ . '/data'; if (!is_dir($dir)) { @mkdir($dir, 0750, true); @file_put_contents($dir . '/.htaccess', "Require all denied\n"); }
// rate limit per IP (hashed)
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'gcn');
$rl = $dir . '/rl-' . substr($ip, 0, 16) . '.json'; $now = time();
$hits = is_file($rl) ? array_filter((array)json_decode((string)file_get_contents($rl), true), static fn($x) => $x > $now - 3600) : [];
if (count($hits) >= ($cfg['max_per_hour'] ?? 5)) done(false, $lang, $msg('Too many requests. Try again later.', 'Слишком много запросов. Попробуйте позже.'), $wantsJson);
$hits[] = $now; @file_put_contents($rl, json_encode(array_values($hits)));

$d['at'] = gmdate('c');
// 1) local backup first: a request is never lost
$ok = @file_put_contents($dir . '/requests.jsonl', json_encode($d, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) !== false;
// 2) email
$mailed = false;
if (!empty($cfg['mail_to'])) {
    $body = "New website request\n\n";
    foreach ($d as $k => $v) { $body .= str_pad($k, 10) . ': ' . $v . "\n"; }
    $h = ['From' => ($cfg['mail_from'] ?? $cfg['mail_to']), 'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8'];
    if ($isMail) $h['Reply-To'] = $d['contact'];
    $mailed = @mail($cfg['mail_to'], '=?UTF-8?B?' . base64_encode('GCN site: ' . $d['solution'] . ' — ' . $d['company']) . '?=', $body, $h);
}
// 3) Google Sheets via Apps Script web app (formula-injection guarded)
$sheeted = false;
if (!empty($cfg['sheet_url']) && function_exists('curl_init')) {
    $safe = array_map(static fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $d);
    $ch = curl_init($cfg['sheet_url']);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['secret' => $cfg['sheet_secret'] ?? '', 'row' => $safe], JSON_UNESCAPED_UNICODE)]);
    $r = curl_exec($ch); $sheeted = $r !== false && str_contains((string)$r, '"ok":true'); curl_close($ch);
}
if (!$ok && !$mailed && !$sheeted) done(false, $lang, $msg('Could not send. Please write to us by email.', 'Не удалось отправить. Напишите нам на почту.'), $wantsJson);
done(true, $lang, '', $wantsJson);
