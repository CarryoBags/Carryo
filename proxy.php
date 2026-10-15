<?php
/**
 * Proxy Redirector 2026 - تحويل آمن وسريع لرابط البث
 *  - بيفك تشفير الرابط (XOR + Base64) بنفس مفتاح embed.php
 *  - بيسمح بـ http/https بس (منع javascript:/data: والعابرة)
 *  - بيمنع Open Redirect: الدومين لازم يكون مسجّل فعلاً في data/data.json
 *  - بيرفض أي فتح مباشر من المتصفح (404)
 */

error_reporting(0);

// مفتاح التشفير (لازم يطابق المفتاح الموجود في embed.php)
$key = "MySecretKey2024";

// 1) فك التشفير
$data = (isset($_GET['data']) && is_string($_GET['data'])) ? $_GET['data'] : '';
if ($data === '') { pl404(); }

$bin = base64_decode($data, true);
if ($bin === false || $bin === '') { pl404(); }

$real_url = '';
$klen = strlen($key);
for ($i = 0, $n = strlen($bin); $i < $n; $i++) {
    $real_url .= $bin[$i] ^ $key[$i % $klen];
}
// شيل أي حروف تحكم (حماية من HTTP Header Injection)
$real_url = trim(str_replace(["\r", "\n", "\0", "\t"], '', $real_url));

// 2) فلتر الحماية: الفتح المباشر في المتصفح = 404
$referer      = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$secFetchDest = isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? $_SERVER['HTTP_SEC_FETCH_DEST'] : '';

if ($secFetchDest === 'document' || (empty($referer) && empty($secFetchDest))) { pl404(); }

// 3) الرابط لازم يكون http/https وبيكون فيه host صالح
if (!preg_match('#^https?://#i', $real_url)) { pl404(); }
$parts = parse_url($real_url);
if (!is_array($parts) || empty($parts['host'])) { pl404(); }
$host = strtolower($parts['host']);

// 4) منع Open Redirect: الدومين لازم يكون موجود في data/data.json
if (!plHostAllowed($host)) { pl404(); }

// 5) التحويل (302) - أسرع طريقة وبدون أي استهلاك للسيرفر
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('Location: ' . $real_url, true, 302);
exit;

/**
 * هل الدومين مسموح؟ بنبنّي قائمة من دومينات روابط البث الموجودة في data/data.json
 * (حرصاً على الأداء: الملف صغير وبيتقرأ مرة واحدة لكل تحويلة)
 */
function plHostAllowed($host) {
    static $allowed = null;
    if ($allowed === null) {
        $allowed = [];
        $f = __DIR__ . '/data/data.json';
        if (is_file($f)) {
            $j = json_decode((string)@file_get_contents($f), true);
            if (is_array($j)) {
                foreach ($j as $entry) {
                    if (!is_array($entry)) { continue; }
                    foreach ($entry as $k => $v) {
                        if (is_numeric($k) && is_array($v) && !empty($v['Stream_Url'])) {
                            $h = parse_url((string)$v['Stream_Url'], PHP_URL_HOST);
                            if ($h) { $allowed[strtolower($h)] = 1; }
                        }
                    }
                }
            }
        }
    }
    return isset($allowed[$host]);
}

function pl404() {
    header('HTTP/1.1 404 Not Found');
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: text/plain; charset=UTF-8');
    echo '404 Not Found';
    exit;
}
