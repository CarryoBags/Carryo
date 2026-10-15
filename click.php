<?php
// عدّاد النقرات: بيستقبل دفعات (مشاهدات / نقرات / نقرات الطبقة) ويسجّلها في data/clicks.json
// بيستخدم flock علشان ولا نقرة تضيع حتى لو كذا مشاهد بعتوا في نفس اللحظة.
date_default_timezone_set('Africa/Cairo');
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

// منع تضخيم العدّاد من مواقع تانية: المتصفحات الحديثة بتبعت Sec-Fetch-Site،
// لو جاي من موقع مختلف (cross-site) نرفض. العملاء اللي مش بتبعت الهيدر (أدوات/قديم) بيعدّوا عادي.
$sfs = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($sfs === 'cross-site') { http_response_code(403); exit; }

$raw = file_get_contents('php://input', false, null, 0, 512);
$j = json_decode($raw, true);
if (!is_array($j)) { http_response_code(400); exit; }

$scope = (isset($j['s']) && $j['s'] === 'live') ? 'live' : 'player';
$v = max(0, min(50,  (int)($j['v'] ?? 0)));
$c = max(0, min(500, (int)($j['c'] ?? 0)));
$h = max(0, min(500, (int)($j['h'] ?? 0)));
$l = max(0, min(100, (int)($j['l'] ?? 0)));   // سكريبت إعلان اتحمّل
$e = max(0, min(100, (int)($j['e'] ?? 0)));   // سكريبت إعلان فشل/اتحجب
$p = max(0, min(500, (int)($j['p'] ?? 0)));   // نافذة إعلان اتفتحت (تقدير)
if (!$v && !$c && !$h && !$l && !$e && !$p) { http_response_code(204); exit; }

$file = __DIR__ . '/data/clicks.json';
// لو الملف مرفوع بصلاحيات تانية: نصلّحها أو نبدّله بنسخة جديدة
if (is_file($file) && !is_writable($file)) {
    @chmod($file, 0666);
    if (!is_writable($file)) {
        $keep = @file_get_contents($file);
        $tmpf = $file . '.new';
        if (@file_put_contents($tmpf, $keep === false || $keep === '' ? '{}' : $keep) !== false && @rename($tmpf, $file)) { @chmod($file, 0664); }
    }
}
$fp = @fopen($file, 'c+');
if (!$fp) { http_response_code(500); exit; }

if (flock($fp, LOCK_EX)) {
    $d = json_decode((string)stream_get_contents($fp), true);
    if (!is_array($d)) $d = [];
    $day = date('Y-m-d');
    if (!isset($d[$day][$scope])) $d[$day][$scope] = ['view' => 0, 'click' => 0, 'shield' => 0, 'load' => 0, 'err' => 0, 'pop' => 0];
    foreach (['load', 'err', 'pop'] as $nk) { if (!isset($d[$day][$scope][$nk])) $d[$day][$scope][$nk] = 0; }
    $d[$day][$scope]['view']   += $v;
    $d[$day][$scope]['click']  += $c;
    $d[$day][$scope]['shield'] += $h;
    $d[$day][$scope]['load']   += $l;
    $d[$day][$scope]['err']    += $e;
    $d[$day][$scope]['pop']    += $p;
    krsort($d);
    $d = array_slice($d, 0, 90, true);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d));
    fflush($fp);
    flock($fp, LOCK_UN);
}
fclose($fp);
http_response_code(204);
