<?php
// One-time helper: downloads the site photos server-side. Delete after use.
header('Content-Type: application/json');
if (!hash_equals('5561274b3e5c473a2776', (string)($_GET['k'] ?? ''))) { http_response_code(403); exit('{}'); }
$ids = ['hero'=>10202865,'ind-construction'=>8961064,'inclusive'=>3865555,'ind-agriculture'=>9798995,'ind-food'=>5953714,'ind-mining'=>17839768,'ind-manufacturing'=>29224559,'ind-warehouse'=>4481260,'ind-horeca'=>6937464];
$dir = __DIR__ . '/assets/img'; if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
$res = [];
foreach ($ids as $name => $id) {
    $u = "https://images.pexels.com/photos/$id/pexels-photo-$id.jpeg?auto=compress&cs=tinysrgb&w=1200&h=900&fit=crop";
    $ch = curl_init($u); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>40, CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; GCNSite/1.0)']);
    $d = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $ok = $c === 200 && is_string($d) && strlen($d) > 20000 && substr($d, 0, 2) === "\xFF\xD8";
    if ($ok) { file_put_contents("$dir/$name.jpg", $d); }
    $res[$name] = ['http'=>$c, 'bytes'=>is_string($d) ? strlen($d) : 0, 'saved'=>$ok];
}
echo json_encode($res);
