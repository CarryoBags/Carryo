<?php
// كود المشاركة: سطر واحد بيرسم المشغل + بيشغّل الإعلان في الصفحة المضيفة (برّه الـ iframe)
// <script src="https://دومينك/embed.js.php?id=123&h=600"></script>
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/pops_lib.php';

$sch  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base = $sch . '://' . $_SERVER['HTTP_HOST'] . $dir;

$q = $_GET;
$h = isset($q['h']) ? max(150, min(2000, (int)$q['h'])) : 600;
unset($q['h'], $q['w'], $q['from']);
$q['from'] = 'host';                                  // المشغل مايحمّلش إعلانات جواه
$src   = $base . '/embed.php?' . http_build_query($q);
$flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

echo "(function () {\n";
echo "var cs = document.currentScript;\n";
echo "var f = document.createElement('iframe');\n";
echo "f.src = " . json_encode($src, $flags) . ";\n";
echo "f.width = '100%'; f.height = " . $h . "; f.style.border = '0'; f.style.maxWidth = '100%'; f.style.display = 'block';\n";
echo "f.setAttribute('frameborder', '0'); f.setAttribute('allowfullscreen', ''); f.setAttribute('allow', 'autoplay; fullscreen; encrypted-media');\n";
// الـ iframe جوه حاوية: الطبقة الشفافة (طبقة النقر) بتتحط فوق الحاوية في الصفحة المضيفة
echo "var w = document.createElement('div'); w.style.cssText = 'position:relative;width:100%;max-width:100%';\n";
echo "w.appendChild(f);\n";
echo "if (cs && cs.parentNode) { cs.parentNode.insertBefore(w, cs); } else { (document.body || document.documentElement).appendChild(w); }\n";
echo "window.__plHostWrap = w;\n";
echo "})();\n";
echo build_pops_js('player');
