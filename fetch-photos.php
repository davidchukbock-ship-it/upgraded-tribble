<?php
// One-time helper: downloads the site photos server-side. Delete after use.
header('Content-Type: application/json');
if (!hash_equals('ab29a48565113ca6252f', (string)($_GET['k'] ?? ''))) { http_response_code(403); exit('{}'); }
$ids = ['ind-agriculture'=>12612073,'ind-mining'=>33774180,'ind-manufacturing'=>9242289,'ind-warehouse'=>37912780,'ind-construction'=>7108780,'ind-horeca'=>4350094];
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
