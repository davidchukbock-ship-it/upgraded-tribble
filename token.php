<?php
declare(strict_types=1);
// Signed form token (time + HMAC) for the site forms; checked by send.php.
require dirname(__DIR__) . '/src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    \App\Core\App::boot(gcn_load_config());
    echo json_encode(['t' => \App\Services\RequestService::formToken()]);
} catch (Throwable $e) {
    error_log('[gcn-site] token: ' . $e->getMessage());
    http_response_code(503);
    echo '{"t":""}';
}
