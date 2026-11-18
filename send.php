<?php
declare(strict_types=1);
// Site request form -> CRM (table contact_requests). Lives in /site/ next to the CRM; uses CRM classes read-only,
// no CRM file is modified. Encrypted contact, rate limit, history, staff notification S20 – same as the CRM's own form.
use App\Core\App;
use App\Core\Crypto;
use App\Core\Db;
use App\Services\History;
use App\Services\NotificationService;
use App\Services\RequestService;

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$lang = in_array($_POST['lang'] ?? 'en', ['ru', 'vi', 'es'], true) ? $_POST['lang'] : 'en';
function done(bool $ok, string $lang, string $err = '', bool $json = true): never {
    if ($json) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => $ok, 'error' => $err], JSON_UNESCAPED_UNICODE); exit; }
    $base = '/site' . ($lang === 'en' ? '' : '/' . $lang);
    header('Location: ' . $base . '/contact/?sent=' . ($ok ? '1' : '0')); exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
header('Cache-Control: no-store');
$M = [
    'wait'    => ['Please wait a moment and send again.', 'Подождите секунду и отправьте ещё раз.', 'Vui lòng đợi một chút rồi gửi lại.', 'Espere un momento y vuelva a enviar.'],
    'consent' => ['Please confirm consent.', 'Подтвердите согласие.', 'Vui lòng xác nhận đồng ý.', 'Confirme su consentimiento.'],
    'fields'  => ['Please fill in all required fields.', 'Заполните все обязательные поля.', 'Vui lòng điền đầy đủ các trường bắt buộc.', 'Complete todos los campos obligatorios.'],
    'contact' => ['Enter a valid email or phone.', 'Укажите корректный email или телефон.', 'Vui lòng nhập email hoặc số điện thoại hợp lệ.', 'Introduzca un correo o teléfono válido.'],
    'rate'    => ['Too many requests. Try again later.', 'Слишком много запросов. Попробуйте позже.', 'Quá nhiều yêu cầu. Vui lòng thử lại sau.', 'Demasiadas solicitudes. Inténtelo más tarde.'],
    'fail'    => ['Could not send. Please try again later.', 'Не удалось отправить. Попробуйте позже.', 'Không gửi được. Vui lòng thử lại sau.', 'No se pudo enviar. Inténtelo más tarde.'],
];
$msg = static function (string $k) use ($M, $lang): string { return $M[$k][['en' => 0, 'ru' => 1, 'vi' => 2, 'es' => 3][$lang]]; };
$g = static fn(string $k, int $n = 200): string => mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string) ($_POST[$k] ?? ''))), 0, $n);

if ($g('website') !== '') done(true, $lang, '', $wantsJson);   // honeypot: pretend success
if (empty($_POST['consent'])) done(false, $lang, $msg('consent'), $wantsJson);

$d = ['solution' => $g('solution', 60), 'name' => $g('name', 120), 'company' => $g('company', 120), 'region' => $g('region', 120),
      'team_size' => $g('team_size', 40), 'contact' => $g('contact', 120), 'page' => $g('page', 40),
      'message' => mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 2000)];
if ($d['solution'] === '' || $d['company'] === '' || $d['region'] === '' || $d['contact'] === '') done(false, $lang, $msg('fields'), $wantsJson);
$isMail = (bool) filter_var($d['contact'], FILTER_VALIDATE_EMAIL);
if (!$isMail && !preg_match('/^\+?[0-9 ()\-]{7,20}$/', $d['contact'])) done(false, $lang, $msg('contact'), $wantsJson);

try {
    require dirname(__DIR__) . '/src/bootstrap.php';
    App::boot(gcn_load_config());
    if (!RequestService::checkToken((string) ($_POST['t'] ?? ''))) done(false, $lang, $msg('wait'), $wantsJson);

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $secret = (string) App::cfg('security.data_key', '');
    $ipHash = hash_hmac('sha256', $ip, $secret);
    $since = gmdate('Y-m-d H:i:s', time() - 3600);
    if ((int) Db::val('SELECT COUNT(*) FROM contact_requests WHERE ip_hash = ? AND created_at >= ?', [$ipHash, $since]) >= 5) done(false, $lang, $msg('rate'), $wantsJson);

    $topic = match ($d['solution']) {
        'Workforce Teams', 'Staffing', 'Outstaffing' => 'COMPANY',
        'Partner Network' => 'PARTNER',
        'Inclusive Workforce' => 'CANDIDATE',
        default => 'OTHER',
    };
    $lines = ['Solution: ' . $d['solution'], 'Region: ' . $d['region']];
    if ($d['team_size'] !== '') $lines[] = 'Team size: ' . $d['team_size'];
    if (!$isMail) $lines[] = 'Phone given as the contact';
    $lines[] = 'Form: ' . ($d['page'] !== '' ? $d['page'] : 'site');
    $text = implode("\n", $lines) . ($d['message'] !== '' ? "\n\n" . $d['message'] : '');
    $name = $d['name'] !== '' ? $d['name'] : $d['company'];
    $emailEnc = Crypto::encrypt($d['contact']);
    $masked = $isMail ? RequestService::maskEmail($d['contact']) : 'tel:***' . substr(preg_replace('/\D/', '', $d['contact']), -2);
    $now = App::now();
    $id = Db::tx(function () use ($name, $d, $isMail, $emailEnc, $masked, $topic, $text, $lang, $ipHash, $now) {
        $id = Db::insert('contact_requests', [
            'name' => mb_substr($name, 0, 120), 'email_enc' => $emailEnc, 'email_masked' => $masked,
            'phone_enc' => $isMail ? null : $emailEnc, 'company' => mb_substr($d['company'], 0, 160),
            'topic' => $topic, 'message' => mb_substr($text, 0, 4000), 'lang' => $lang,
            'status' => 'NEW', 'consent' => 1, 'ip_hash' => $ipHash, 'created_at' => $now, 'updated_at' => $now,
        ]);
        History::add('request', (string) $id, null, 'NEW', null, 'site');
        NotificationService::staff('S20', ['id' => $id, 'type' => $topic], "S20:request:$id");
        return $id;
    });
} catch (Throwable $e) {
    error_log('[gcn-site] ' . $e->getMessage());
    done(false, $lang, $msg('fail'), $wantsJson);
}
done(true, $lang, '', $wantsJson);
