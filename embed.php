<?php
// 1. بداية التقاط المخرجات (علشان نشفر الصفحة كاملة في الآخر)
ob_start(); 
// علشان أي تغيير في الإعدادات يظهر فوراً (بدون كاش)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// -----------------------------------------------------------------------------
// 1. إعدادات التشفير الجديدة (XOR) - كودك الأصلي
// -----------------------------------------------------------------------------
$encryptionKey = "MySecretKey2024"; 

function xorEncrypt($text, $key) {
    $result = '';
    for($i = 0; $i < strlen($text); $i++) {
        $result .= $text[$i] ^ $key[$i % strlen($key)];
    }
    return base64_encode($result);
}

// -----------------------------------------------------------------------------
// 2. قراءة البيانات وتجهيزها - كودك الأصلي
// -----------------------------------------------------------------------------
$id = isset($_GET['id']) ? $_GET['id'] : '';
$jsonFile = 'data/data.json';
$allItems = [];

if (!empty($id) && file_exists($jsonFile)) {
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);
    foreach ($data as $entry) {
        if ($entry['id'] == $id) {
            foreach ($entry as $key => $value) {
                if (is_numeric($key) && is_array($value) && isset($value['Stream_Url'])) {
                    $name = $value['name'] ?? 'سيرفر ' . $key;
                    $streamUrl = $value['Stream_Url'];
                    $linkType = $value['Link_type'] ?? 'source';
                    $playerType = $value['player'] ?? 'videojs'; 
                    $device = isset($value['device']) ? $value['device'] : 'legacy';
                    
                    if (!empty($streamUrl)) {
                        $allItems[] = [
                            'name' => $name,
                            'url' => xorEncrypt($streamUrl, $encryptionKey),
                            'type' => $linkType,
                            'player' => $playerType, 
                            'device' => $device
                        ];
                    }
                }
            }
            break;
        }
    }
}

// دعم مباشر: embed.php?url=... (بتستخدمها صفحة live.php)
// الرابط بيتشفّر هنا وبيمر بنفس البروكسي، ولازم دومينه يكون موجود في data/data.json (منع سوء الاستخدام)
if (empty($allItems) && !empty($_GET['url']) && is_string($_GET['url'])) {
    $rawUrl = trim($_GET['url']);
    $urlHost = $rawUrl !== '' ? strtolower((string)parse_url($rawUrl, PHP_URL_HOST)) : '';
    $hostOk  = false;
    if ($urlHost !== '' && preg_match('#^https?://#i', $rawUrl) && is_array($data)) {
        foreach ($data as $entry) {
            if (!is_array($entry)) { continue; }
            foreach ($entry as $k => $v) {
                if (is_numeric($k) && is_array($v) && !empty($v['Stream_Url'])
                    && strtolower((string)parse_url($v['Stream_Url'], PHP_URL_HOST)) === $urlHost) { $hostOk = true; break 2; }
            }
        }
    }
    if ($hostOk) {
        $isHls = (stripos($rawUrl, '.m3u8') !== false);
        $allItems[] = [
            'name'   => 'البث المباشر',
            'url'    => xorEncrypt($rawUrl, $encryptionKey),
            'type'   => $isHls ? 'source' : 'embed',
            'player' => $isHls ? 'hlsjs' : 'iframe',
            'device' => 'all'
        ];
    }
}

if (empty($allItems)) {
    $allItems = [
        ['name' => 'البث الافتراضي', 'url' => xorEncrypt('.', $encryptionKey), 'type' => 'source', 'player' => 'videojs', 'device' => 'legacy']
    ];
}

// نجمع دومينات البث علشان نعمل preconnect/نقصّر زمن الاتصال (تحسين سرعة التشغيل)
function xorDecryptPhp($enc, $key) {
    $bin = base64_decode((string)$enc, true);
    if ($bin === false || $bin === '') { return ''; }
    $out = ''; $klen = strlen($key);
    for ($i = 0, $n = strlen($bin); $i < $n; $i++) { $out .= $bin[$i] ^ $key[$i % $klen]; }
    return $out;
}
$plPreconnect = [];
foreach ($allItems as $it) {
    $u = xorDecryptPhp($it['url'], $encryptionKey);
    $h = parse_url($u, PHP_URL_HOST);
    $s = parse_url($u, PHP_URL_SCHEME);
    if ($h && !isset($plPreconnect[$h])) { $plPreconnect[$h] = ($s === 'http') ? 'http' : 'https'; }
}

// -----------------------------------------------------------------------------
// إعدادات تصميم المشغل (لوجو / كتابة / إعلانات / ألوان) - من data/player_settings.json
// تتعدل من: insert/player_settings.php
// -----------------------------------------------------------------------------
require_once __DIR__ . '/player_defaults.php';
$ps = player_settings(__DIR__ . '/data/player_settings.json');
$th = $ps['theme'];
$cAccent  = pcolor($th['accent'],  '#e11d48');
$cAccent2 = pcolor($th['accent2'], '#d4af37');
$cBarBg   = pcolor($th['bar_bg'],  '#0a0c15');
$cBarText = pcolor($th['bar_text'],'#f4f6fb');
$cTabBg   = pcolor($th['tab_bg'],  '#181b2a');
$cTabText = pcolor($th['tab_text'],'#98a2ba');
$cPlayBg  = pcolor($th['player_bg'],'#05060b');
$btnSizes = ['s' => [40, 11], 'm' => [48, 12], 'l' => [56, 14]];
$bsz = isset($btnSizes[$th['btn_size'] ?? 'm']) ? $btnSizes[$th['btn_size'] ?? 'm'] : $btnSizes['m'];
$barH = !empty($th['bar_visible']) ? $bsz[0] : 0;          // ارتفاع شريط السيرفرات
$btnFs = $bsz[1];
$barBottom = (($th['bar_position'] ?? 'top') === 'bottom'); // الشريط فوق ولا تحت
$barTop = $barBottom ? 0 : $barH;
$barBot = $barBottom ? $barH : 0;
$btnRad = max(0, min(30, (int)($th['btn_radius'] ?? 6)));
$showEmbed  = !isset($th['show_embed'])      || !empty($th['show_embed']);
$showShare  = !isset($th['show_share'])      || !empty($th['show_share']);
$showFull   = !isset($th['show_fullscreen']) || !empty($th['show_fullscreen']);
$showReload = !isset($th['show_refresh'])    || !empty($th['show_refresh']);
$siteName = trim((string)($ps['brand']['site_name'] ?? ''));
$footH = $siteName !== '' ? 26 : 0;
$topH = (!empty($ps['ads']['top']['enabled']) && trim($ps['ads']['top']['code']) !== '') ? max(20, min(200, (int)$ps['ads']['top']['height'])) : 0;
$botH = (!empty($ps['ads']['bottom']['enabled']) && trim($ps['ads']['bottom']['code']) !== '') ? max(20, min(200, (int)$ps['ads']['bottom']['height'])) : 0;

// نبعت للمتصفح بس الحاجات المفعّلة
$cfg = ['logo' => null, 'text' => null, 'ads' => [], 'poster' => ''];
$posterSrc = trim((string)($ps['brand']['poster'] ?? ''));
if ($posterSrc !== '' && preg_match('#^(https?:)?//[^\s"\'<>]+$|^data/uploads/[\w.\-]+$#', $posterSrc)) { $cfg['poster'] = $posterSrc; }
$lg = $ps['logo'];
if (!empty($lg['enabled']) && preg_match('#^(https?:)?//[^\s"\'<>]+$|^data/uploads/[\w.\-]+$#', (string)$lg['src'])) {
    $cfg['logo'] = [
        'src' => $lg['src'], 'x' => (float)$lg['x'], 'y' => (float)$lg['y'], 'width' => (float)$lg['width'],
        'opacity' => (int)$lg['opacity'], 'radius' => (int)$lg['radius'],
        'link' => preg_match('#^https?://[^\s"\'<>]+$#', (string)$lg['link']) ? $lg['link'] : ''
    ];
}
$tx = $ps['text'];
if (!empty($tx['enabled']) && trim((string)$tx['content']) !== '') {
    $cfg['text'] = [
        'content' => (string)$tx['content'], 'x' => (float)$tx['x'], 'y' => (float)$tx['y'], 'size' => (int)$tx['size'],
        'color' => pcolor($tx['color'], '#ffffff'), 'bg' => pcolor($tx['bg'], '#000000'), 'bg_opacity' => (int)$tx['bg_opacity'],
        'weight' => in_array((string)$tx['weight'], ['400','700','900'], true) ? (string)$tx['weight'] : '700',
        'opacity' => (int)$tx['opacity'], 'anim' => in_array($tx['anim'], ['none','marquee','blink','pulse','fade'], true) ? $tx['anim'] : 'none',
        'speed' => max(4, min(60, (int)$tx['speed'])), 'radius' => (int)$tx['radius']
    ];
}
foreach (['top','bottom','preroll','corner'] as $slot) {
    $a = $ps['ads'][$slot];
    if (!empty($a['enabled']) && trim((string)$a['code']) !== '') {
        $cfg['ads'][$slot] = $a;
    }
}

// كود الإحصائيات (Histats / Google Analytics / أي كود تتبع)
$cfg['tracking'] = trim((string)$ps['tracking']['code']) !== '' ? (string)$ps['tracking']['code'] : null;
$disableBuiltinStats = !empty($ps['tracking']['disable_builtin']);
// إعدادات اللوحة (data/panel_settings.json) - فيها سكريبتات البوب كمان
$psj = [];
$psjFile = __DIR__ . '/data/panel_settings.json';
if (is_file($psjFile)) {
    $tmpj = json_decode(file_get_contents($psjFile), true);
    if (is_array($tmpj)) $psj = $tmpj;
}
$disableBuiltinPops = !empty($ps['builtin']['disable_pops']) || !empty($psj['disable_builtin_pops']);

// إعلانات البوب: بتتحمّل مرة واحدة في الصفحة الرئيسية (مش جوه إطار معزول)
// scope: player = جوه المشغل بس | live = في صفحة live.php بس | both = أحسن مكان متاح
$inLive = (isset($_GET['from']) && $_GET['from'] === 'live');
$cfg['pops'] = [];
foreach (['pop1','pop2','pop3'] as $k) {
    $pp = $ps['pops'][$k];
    if (empty($pp['enabled']) || trim((string)$pp['code']) === '') continue;
    $sc = isset($pp['scope']) ? $pp['scope'] : 'both';
    if ($sc === 'live') continue;                    // مكانه صفحة live.php
    if ($sc === 'both' && $inLive) continue;         // المشغل جوه live.php: البوب بيشتغل في الصفحة الأم
    $cfg['pops'][] = [
        'id' => $k, 'code' => (string)$pp['code'],
        'trigger' => in_array($pp['trigger'], ['load','delay','click','layer'], true) ? $pp['trigger'] : 'load',
        'delay' => max(0, min(300, (int)$pp['delay'])), 'once' => !empty($pp['once']) ? 1 : 0
    ];
}
$po = $ps['pop_opts'];
$cfg['pop_opts'] = [
    'shield_mode' => in_array($po['shield_mode'], ['off','first','cooldown'], true) ? $po['shield_mode'] : 'cooldown',
    'shield_cooldown' => max(1, min(60, (int)$po['shield_cooldown'])),
    'count' => !empty($po['count']) ? 1 : 0,
    'endpoint' => 'click.php'
];

// سكريبتات البوب اللي في data/panel_settings.json (المفتاح pop_scripts)
if (!empty($psj['pop_scripts_enabled']) && !empty($psj['pop_scripts']) && is_array($psj['pop_scripts'])) {
    $n = 0;
    foreach ($psj['pop_scripts'] as $it) {
        $n++;
        $code = is_array($it) ? trim((string)($it['code'] ?? '')) : trim((string)$it);
        if ($code === '') continue;
        $trg = (is_array($it) && in_array(($it['trigger'] ?? ''), ['load','delay','click','layer'], true)) ? $it['trigger'] : 'load';
        $cfg['pops'][] = [
            'id' => 'ps' . $n, 'code' => $code, 'trigger' => $trg,
            'delay' => is_array($it) ? max(0, min(300, (int)($it['delay'] ?? 0))) : 0, 'once' => 0
        ];
    }
}
// المشغل جاي من embed.js.php (from=host): البوب بيتحمّل في الصفحة المضيفة، فمنحمّلوش هنا تاني (كان بيتحمّل مرتين)
if (isset($_GET['from']) && $_GET['from'] === 'host') { $cfg['pops'] = []; }

// خيارات الحماية (من اللوحة). لو المفتاح مش موجود في الإعدادات القديمة: الحماية شغالة زي ما كانت
$protectShell = !isset($ps['protect']['shell']) || !empty($ps['protect']['shell']);
$protectDev   = !isset($ps['protect']['devtools']) || !empty($ps['protect']['devtools']);

// رابط المشاركة: من غير from=host (علشان اللي ينسخه يلاقي الإعلانات شغالة)
function plShareUrl() {
    $q = $_GET; unset($q['from']);
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    $sch = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $sch . '://' . $_SERVER['HTTP_HOST'] . $path . ($q ? '?' . http_build_query($q) : '');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<?php if (!$disableBuiltinPops) { ?>
<script>(function(s){s.dataset.zone='11122998',s.src='https://llvpn.com/tag.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<?php } ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1,user-scalable=no,viewport-fit=cover">
    <title>بث مباشر</title>

    <!-- preconnect / dns-prefetch: بيقصّروا زمن الاتصال بالسيرفرات والـ CDN -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://vjs.zencdn.net">
<?php foreach ($plPreconnect as $ph => $scheme) { ?>
    <link rel="preconnect" href="<?php echo htmlspecialchars($scheme . '://' . $ph, ENT_QUOTES, 'UTF-8'); ?>">
<?php } ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
    

    
    
    <style>
        :root {
            --bar-bg: <?php echo $cBarBg; ?>; --bar-border: rgba(255,255,255,0.075); --active-bg: <?php echo $cAccent; ?>;
            --accent2: <?php echo $cAccent2; ?>; --text: <?php echo $cBarText; ?>; --sub-text: <?php echo $cTabText; ?>;
            --tab-bg: <?php echo $cTabBg; ?>; --player-bg: <?php echo $cPlayBg; ?>;
            --bar-size: <?php echo (int)$barH; ?>px; --bar-h: <?php echo (int)$barTop; ?>px; --barb-h: <?php echo (int)$barBot; ?>px; --foot-h: <?php echo (int)$footH; ?>px; --btn-r: <?php echo (int)$btnRad; ?>px; --btn-fs: <?php echo (int)$btnFs; ?>px;
            --top-h: <?php echo (int)$topH; ?>px; --bot-h: <?php echo (int)$botH; ?>px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        html { height:100%; }
        /* 100dvh بيحل مشكلة اختفاء جزء من المشغل في متصفحات الموبايل (شريط العنوان) */
        body { background:var(--player-bg); font-family:'Cairo', Tahoma, sans-serif; height:100vh; height:100dvh; overflow:hidden; color:var(--text); -webkit-text-size-adjust:100%; }
        .tabs-bar { position:fixed; top:0; left:0; right:0; height:var(--bar-size); background:var(--bar-bg); border-bottom:1px solid var(--bar-border); display:<?php echo $barH ? 'flex' : 'none'; ?>; align-items:stretch; z-index:100; padding-left:env(safe-area-inset-left); padding-right:env(safe-area-inset-right); }
        .tabs-bar::after { content:""; position:absolute; left:0; right:0; bottom:-1px; height:2px; background:linear-gradient(90deg, transparent, var(--active-bg), var(--accent2), var(--active-bg), transparent); pointer-events:none; }
        #buttons { display:flex; flex:1; gap:3px; padding:4px; overflow-x:auto; scrollbar-width: none; }
        #buttons::-webkit-scrollbar { display: none; }
        .tab-btn { flex:1; min-width: 80px; background:var(--tab-bg); border:none; color:var(--sub-text); font-family:inherit; font-size:var(--btn-fs); font-weight:bold; cursor:pointer; border-radius:var(--btn-r); transition:0.2s; white-space: nowrap; display: flex; align-items: center; justify-content: center; }
        .tab-btn:hover { color:var(--text); }
        .tab-btn.active { background:var(--active-bg); background:linear-gradient(135deg, var(--active-bg), color-mix(in srgb, var(--active-bg) 55%, #000)); color:#fff; box-shadow:0 2px 10px color-mix(in srgb, var(--active-bg) 45%, transparent); }
        .tabs-bar.bar-bottom { top:auto; bottom:0; border-bottom:0; border-top:1px solid var(--bar-border); }
        .tabs-bar.bar-bottom::after { bottom:auto; top:-1px; }
        #site_strip { position:fixed; left:0; right:0; bottom:calc(var(--barb-h) + var(--bot-h)); height:var(--foot-h); display:flex; align-items:center; justify-content:center; background:var(--bar-bg); color:var(--sub-text); font-size:12px; font-weight:700; z-index:45; border-top:1px solid var(--bar-border); }
        .action-btn { background:transparent; border:none; color:var(--text); width:45px; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; border-right: 1px solid var(--bar-border); transition:0.2s; }
        .action-btn:hover { color:var(--accent2); background:rgba(255,255,255,0.04); }
        .player-wrapper { position:fixed; top:calc(var(--bar-h) + var(--top-h)); left:0; right:0; bottom:calc(var(--barb-h) + var(--bot-h) + var(--foot-h)); background:var(--player-bg); }
        iframe, .video-js, #clappr-player { width:100% !important; height:100% !important; border:none; }
        .embed-modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; }
        .modal-content { background:#10121d; padding:20px; border-radius:12px; width:90%; max-width:500px; border:1px solid var(--active-bg); text-align:center; }
        .modal-content textarea { width:100%; height:100px; background:#000; color:var(--accent2); border:1px solid #333; border-radius:8px; padding:10px; margin:15px 0; resize:none; font-family:monospace; font-size:11px; }
        .modal-btn { background:var(--active-bg); color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold; font-family:inherit; }

        /* ===== طبقة اللوجو والكتابة ===== */
        #overlay_layer { position:fixed; top:calc(var(--bar-h) + var(--top-h)); left:0; right:0; bottom:calc(var(--barb-h) + var(--bot-h) + var(--foot-h)); z-index:50; pointer-events:none; container-type:inline-size; }
        #overlay_layer .ov-inner { position:absolute; inset:8px; }
        #pl_logo { position:absolute; display:block; pointer-events:none; line-height:0; }
        #pl_logo img { width:100%; height:auto; display:block; user-select:none; -webkit-user-drag:none; }
        #pl_text { position:absolute; max-width:100%; pointer-events:none; }
        #pl_text .pl_t { display:inline-block; padding:4px 14px; line-height:1.6; white-space:pre-line; text-align:center; }
        #pl_text.mq { left:0 !important; width:100%; transform:none !important; overflow:hidden; direction:ltr; }
        #pl_text.mq .pl_t { white-space:nowrap; direction:rtl; unicode-bidi:plaintext; animation:pl_mq var(--pl-speed,14s) linear infinite; }
        @keyframes pl_mq { from { transform:translateX(100cqw); } to { transform:translateX(-100%); } }
        .pl-blink { animation:pl_blink 1.2s ease-in-out infinite; }
        .pl-pulse { animation:pl_pulse 1.6s ease-in-out infinite; }
        .pl-fade  { animation:pl_fade 4s ease-in-out infinite; }
        @keyframes pl_blink { 0%,100% { opacity:1; } 50% { opacity:.15; } }
        @keyframes pl_pulse { 0%,100% { transform:scale(1); } 50% { transform:scale(1.08); } }
        @keyframes pl_fade  { 0%,100% { opacity:.25; } 50% { opacity:1; } }

        /* ===== أماكن الإعلانات ===== */
        .ad-slot { position:fixed; left:0; right:0; background:#000; z-index:60; overflow:hidden; }
        .ad-slot iframe { width:100% !important; height:100% !important; border:0; display:block; }
        #ad_top { top:var(--bar-h); height:var(--top-h); }
        #ad_bottom { bottom:calc(var(--barb-h) + var(--foot-h)); height:var(--bot-h); }
        #ad_bottom .ad-x { position:absolute; top:2px; left:2px; width:20px; height:20px; border-radius:50%; background:rgba(0,0,0,.7); color:#fff; border:1px solid rgba(255,255,255,.25); font-size:11px; cursor:pointer; z-index:2; line-height:1; }
        .ad-tag { position:absolute; top:2px; right:4px; font-size:9px; color:#fff; background:rgba(0,0,0,.55); padding:0 5px; border-radius:4px; z-index:2; pointer-events:none; }
        #preroll { position:fixed; top:calc(var(--bar-h) + var(--top-h)); left:0; right:0; bottom:calc(var(--barb-h) + var(--bot-h) + var(--foot-h)); z-index:90; background:rgba(3,5,12,.94); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; }
        #preroll .pr-box { position:relative; background:#000; border:1px solid var(--bar-border); border-radius:10px; overflow:hidden; max-width:94vw; max-height:68%; }
        #preroll .pr-box iframe { width:100% !important; height:100% !important; border:0; display:block; }
        #preroll .pr-bar { display:flex; align-items:center; gap:12px; font-size:13px; color:var(--sub-text); }
        #preroll .pr-skip { background:var(--active-bg); color:#fff; border:none; padding:7px 16px; border-radius:8px; font-family:inherit; font-weight:700; cursor:pointer; }
        #corner_ad { position:fixed; bottom:calc(var(--barb-h) + var(--foot-h) + var(--bot-h) + 12px); z-index:80; background:#000; border:1px solid var(--bar-border); border-radius:10px; overflow:hidden; max-width:92vw; max-height:70vh; box-shadow:0 8px 24px rgba(0,0,0,.5); animation:sl-pop-in .35s ease-out; }
        #corner_ad iframe { width:100% !important; height:100% !important; border:0; display:block; }
        #corner_ad .ad-x { position:absolute; top:4px; left:4px; width:22px; height:22px; border-radius:50%; background:rgba(0,0,0,.75); color:#fff; border:1px solid rgba(255,255,255,.3); font-size:12px; cursor:pointer; z-index:3; line-height:1; }

        /* ===== البوب ===== */
        #pop_layer { position:fixed; top:calc(var(--bar-h) + var(--top-h)); left:0; right:0; bottom:calc(var(--barb-h) + var(--bot-h) + var(--foot-h)); z-index:55; background:transparent; cursor:pointer; }

        /* ===== لودر ===== */
        #pl_loader { position:fixed; top:calc(var(--bar-h) + var(--top-h)); left:0; right:0; bottom:calc(var(--barb-h) + var(--bot-h) + var(--foot-h)); z-index:40; display:none; align-items:center; justify-content:center; pointer-events:none; }
        #pl_loader i { font-size:34px; color:var(--active-bg); animation:pl_spin 1s linear infinite; }
        @keyframes pl_spin { to { transform:rotate(360deg); } }

        /* ===== تجاوب مع الشاشات الصغيرة ===== */
        @media (max-width: 520px) {
            .tab-btn { min-width: 62px; }
            .action-btn { width: 38px; font-size: 14px; }
            .modal-content { padding: 14px; }
            #preroll .pr-bar { font-size: 12px; gap: 8px; }
        }
        @media (max-width: 360px) {
            .action-btn { width: 34px; font-size: 13px; }
        }
        @media (orientation: landscape) and (max-height: 460px) {
            #preroll .pr-box { max-height: 60%; }
        }
    </style>
</head>



<style>
#sl-thought-wrapper {
  position: fixed;
  bottom: calc(16px + var(--barb-h, 0px) + var(--foot-h, 0px) + var(--bot-h, 0px));
  right: 16px;
  z-index: 999999;
  font-family: Arial, Tahoma, sans-serif;
  width: min(320px, 88vw);
  height: auto;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  animation: sl-pop-in 0.4s ease-out;
}

@keyframes sl-pop-in {
  from { transform: scale(0.7); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}

#sl-thought-bubble {
  position: relative;
  background: linear-gradient(135deg, #0d6efd 0%, #0a3d91 60%, #041b3d 100%);
  color: #ffffff;
  border-radius: 22px;
  padding: 14px 16px;
  width: min(240px, 78vw);
  font-size: 12.5px;
  line-height: 1.6;
  box-shadow: 0 8px 24px rgba(13,110,253,0.35);
  text-align: center;
  direction: rtl;
  margin-bottom: 70px;
  margin-right: 30px;
}

#sl-thought-bubble b { color: #ffffff; font-weight: 800; }

#sl-thought-bubble .sl-close {
  position: absolute;
  top: -6px;
  left: -6px;
  width: 20px;
  height: 20px;
  background: #0a1a33;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0,0,0,0.3);
}
#sl-thought-bubble .sl-close:hover { background: #14284d; }

.sl-gplay-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #000;
  border-radius: 10px;
  padding: 6px 12px;
  margin-top: 8px;
  text-decoration: none;
  justify-content: center;
  transition: transform 0.15s ease;
}
.sl-gplay-btn:hover { transform: scale(1.04); }
.sl-gplay-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
.sl-gplay-text { text-align: left; line-height: 1.1; }
.sl-gplay-text .small { font-size: 7.5px; color: #ddd; display: block; letter-spacing: 0.5px; }
.sl-gplay-text .big { font-size: 13px; color: #fff; font-weight: 600; font-family: Arial, sans-serif; }

#sl-bubble-dot1, #sl-bubble-dot2 {
  position: absolute;
  background: #0d6efd;
  border-radius: 50%;
  box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

#sl-bubble-dot1 {
  width: 11px;
  height: 11px;
  bottom: 52px;
  right: 54px;
}

#sl-bubble-dot2 {
  width: 7px;
  height: 7px;
  bottom: 42px;
  right: 40px;
}

#sl-avatar-circle {
  position: absolute;
  bottom: 0;
  right: 6px;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #0d1b2a;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(0,0,0,0.35);
  overflow: hidden;
  cursor: pointer;
}
#sl-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }

/* تصغير إضافي على الشاشات الصغيرة جدًا */
@media (max-width: 380px) {
  #sl-thought-bubble {
    padding: 12px 14px;
    font-size: 11.5px;
    margin-bottom: 60px;
  }
  #sl-avatar-circle { width: 44px; height: 44px; }
}
</style>

<div id="sl-thought-wrapper">
  <div id="sl-thought-bubble">
    <span class="sl-close" onclick="document.getElementById('sl-thought-wrapper').style.display='none'">✕</span>
    عايز تعرف كل حاجة عن مبارياتك المفضلة؟ ⚽<br>
    حمّل <b>شوت لايف</b> وتابع الإحصائيات لحظة بلحظة!

    <a class="sl-gplay-btn" href="https://play.google.com/store/apps/details?id=com.f0otball.c&hl=ln" target="_blank" rel="noopener">
      <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
        <path fill="#00d2ff" d="M99 20c-7 4-11 12-11 22v428c0 10 4 18 11 22l231-236L99 20z"/>
        <path fill="#00f076" d="M330 236 99 20c1-1 2-2 4-3l264 151-37 68z"/>
        <path fill="#ffce00" d="M367 304l-264 151c-2-1-3-2-4-3l231-216 37 68z"/>
        <path fill="#ff3a44" d="M330 236l37-68 82 47c17 10 17 32 0 42l-82 47-37-68z"/>
      </svg>
      <span class="sl-gplay-text">
        <span class="small">GET IT ON</span>
        <span class="big">Google Play</span>
      </span>
    </a>
  </div>

  <div id="sl-bubble-dot1"></div>
  <div id="sl-bubble-dot2"></div>

  <div id="sl-avatar-circle" onclick="window.open('https://play.google.com/store/apps/details?id=com.f0otball.c&hl=ln','_blank')">
    <img src="https://iili.io/n3GS4Uu.webp" alt="شوت لايف">
  </div>
</div>

<script>
setTimeout(function () {
  var el = document.getElementById('sl-thought-wrapper');
  if (el) el.style.display = 'flex';
}, 5000);
</script>



<body>

<div class="tabs-bar<?php echo $barBottom ? ' bar-bottom' : ''; ?>">
    <div id="buttons"></div>
    <?php if ($showEmbed) { ?><button class="action-btn" onclick="openEmbed()" title="كود التضمين"><i class="fa-solid fa-code"></i></button><?php } ?>
    <?php if ($showShare) { ?><button class="action-btn" onclick="shareStream()" title="مشاركة"><i class="fa-solid fa-share-nodes"></i></button><?php } ?>
    <?php if ($showFull) { ?><button class="action-btn" onclick="toggleFullscreen()" title="ملء الشاشة"><i class="fa-solid fa-expand"></i></button><?php } ?>
    <?php if ($showReload) { ?><button class="action-btn" onclick="location.reload()" title="تحديث"><i class="fa fa-sync"></i></button><?php } ?>
</div>

<div class="player-wrapper" id="player_container"></div>

<!-- طبقة اللوجو والكتابة فوق المشغل -->
<div id="overlay_layer"><div class="ov-inner">
    <a id="pl_logo" style="display:none" target="_blank" rel="noopener"><img alt=""></a>
    <div id="pl_text" style="display:none"><span class="pl_t"></span></div>
</div></div>
<div id="pl_loader"><i class="fa-solid fa-circle-notch"></i></div>
<?php if ($siteName !== '') { ?><div id="site_strip"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>

<!-- أماكن الإعلانات -->
<div class="ad-slot" id="ad_top" style="display:none"></div>
<div class="ad-slot" id="ad_bottom" style="display:none"></div>

<div id="embed_modal" class="embed-modal">
    <div class="modal-content">
        <h3 style="color:#fff;">كود تضمين البث</h3>
        <textarea id="embed_code" readonly><iframe src="<?php echo htmlspecialchars(plShareUrl(), ENT_QUOTES, 'UTF-8'); ?>" width="100%" height="600" frameborder="0" allowfullscreen></iframe></textarea>
        <div style="display:flex; gap:10px; justify-content:center;">
            <button class="modal-btn" onclick="copyEmbed()">نسخ الكود</button>
            <button class="modal-btn" style="background:#444;" onclick="closeEmbed()">إغلاق</button>
        </div>
    </div>
</div>

<script src="js/pop-engine.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/pop-engine.js'); ?>"></script>

<script>
// -----------------------------------------------------------------------------
// 3. دالة فك التشفير (JavaScript)
// -----------------------------------------------------------------------------
var encKey = "MySecretKey2024"; 

function xorDecrypt(text, key) {
    var result = '';
    for(var i = 0; i < text.length; i++) {
        result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length));
    }
    return result;
}

var allItems = <?php echo json_encode($allItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var container = document.getElementById("player_container");
var buttonsDiv = document.getElementById("buttons");
var currentVideoPlayer = null;

// منع الزر الأيمن والاختصارات بشكل مبدئي
document.addEventListener('contextmenu', event => event.preventDefault());

function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|Windows Phone/i.test(navigator.userAgent);
}

var isMobile = isMobileDevice();
var items = allItems.filter(function(item) {
    return isMobile ? (item.device !== 'desktop') : (item.device !== 'mobile');
});

// إيقاف أي عنصر صوت/فيديو قديم لسه شغال (بيمنع تداخل الأصوات أو الصدى)
function plStopAllMedia() {
    try {
        document.querySelectorAll('video, audio').forEach(function (m) {
            try { m.pause(); m.removeAttribute('src'); m.load(); } catch (e) {}
        });
    } catch (e) {}
}

// ===== لودر التحميل: بيظهر وقت تجهيز البث وبيختفي أول ما الفيديو يبدأ =====
var plLoaderEl = document.getElementById('pl_loader'), plLoaderTm = null;
function plShowLoader() {
    if (!plLoaderEl) return;
    plLoaderEl.style.display = 'flex';
    clearTimeout(plLoaderTm);
    plLoaderTm = setTimeout(plHideLoader, 5000);   // أمان: مايفضلش ظاهر للأبد
}
function plHideLoader() {
    if (!plLoaderEl) return;
    clearTimeout(plLoaderTm);
    plLoaderEl.style.display = 'none';
}

// ===== محركات إضافية (بتتحمّل من الـ CDN وقت الاختيار بس - مفيش تحميل زيادة) =====
var PL_LIBS = {
    hls:     { js: 'https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js' },
    plyr:    { js: 'https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.min.js',   css: 'https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.css' },
    dplayer: { js: 'https://cdn.jsdelivr.net/npm/dplayer@1/dist/DPlayer.min.js', css: 'https://cdn.jsdelivr.net/npm/dplayer@1/dist/DPlayer.min.css' },
    videojs: { js: 'https://vjs.zencdn.net/8.10.0/video.min.js', css: 'https://vjs.zencdn.net/8.10.0/video-js.css' }
};
var plLoaded = {};
function plLoad(url, isCss) {
    if (plLoaded[url]) return plLoaded[url];
    plLoaded[url] = new Promise(function (resolve, reject) {
        var el = document.createElement(isCss ? 'link' : 'script');
        if (isCss) { el.rel = 'stylesheet'; el.href = url; } else { el.src = url; el.async = true; }
        el.onload = resolve; el.onerror = function () { delete plLoaded[url]; reject(new Error('load failed: ' + url)); };
        document.head.appendChild(el);
    });
    return plLoaded[url];
}
function plNeed(name) {
    var L = PL_LIBS[name], jobs = [];
    if (L.css) jobs.push(plLoad(L.css, true));
    jobs.push(plLoad(L.js, false));
    return Promise.all(jobs);
}
function plPosterUrl() { return (typeof PL !== 'undefined' && PL && PL.poster) ? PL.poster : ''; }

// تشغيل HLS على عنصر فيديو: hls.js لو المتصفح مش بيدعم HLS لوحده، والأصلي في سفاري/ايفون
function plAttachHls(video, url) {
    if (video.canPlayType('application/vnd.apple.mpegurl')) { video.src = url; return null; }
    return plNeed('hls').then(function () {
        if (!window.Hls || !Hls.isSupported()) { video.src = url; return null; }
        var hls = new Hls({ enableWorker: true, lowLatencyMode: false, maxBufferLength: 30, manifestLoadingMaxRetry: 6, levelLoadingMaxRetry: 6, fragLoadingMaxRetry: 6, nudgeMaxRetry: 10, maxAudioFramesDrift: 1, stretchShortVideoTrack: true });
        hls.on(Hls.Events.ERROR, function (e, data) {
            if (!data.fatal) return;
            if (data.type === Hls.ErrorTypes.NETWORK_ERROR) hls.startLoad();
            else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) hls.recoverMediaError();
        });
        hls.loadSource(url); hls.attachMedia(video);
        return hls;
    });
}

function plStartEngine(engine, url) {
    var token = {}; plStartEngine.cur = token;
    var poster = plPosterUrl();
    var fail = function () { if (plStartEngine.cur === token) container.innerHTML = '<div style="color:#98a2ba;display:flex;align-items:center;justify-content:center;height:100%;font-family:inherit">تعذر تحميل المشغل، جرّب سيرفر تاني</div>'; };

    if (engine === 'native' || engine === 'hlsjs') {
        container.innerHTML = '<video id="pl-native" controls autoplay playsinline webkit-playsinline style="width:100%;height:100%;background:#000"></video>';
        var v = document.getElementById('pl-native'); if (poster) v.poster = poster;
        v.addEventListener('playing', plHideLoader);
        v.addEventListener('loadeddata', plHideLoader);
        var h = plAttachHls(v, url);
        currentVideoPlayer = { destroy: function () { try { if (h && h.then) h.then(function (x) { x && x.destroy(); }); else if (h) h.destroy(); } catch (e) {} } };
        v.play && v.play().catch(function () {});
        if (h && h.catch) h.catch(function () { plHideLoader(); fail(); });
    }
    else if (engine === 'plyr') {
        container.innerHTML = '<video id="pl-plyr" controls autoplay playsinline style="width:100%;height:100%"></video>';
        var pv = document.getElementById('pl-plyr'); if (poster) pv.poster = poster;
        plNeed('plyr').then(function () {
            if (plStartEngine.cur !== token) return;
            var hp = plAttachHls(pv, url);
            var plyr = new Plyr(pv, { autoplay: true, ratio: null });
            try { plyr.on('playing', plHideLoader); } catch (e) { pv.addEventListener('playing', plHideLoader); }
            currentVideoPlayer = { destroy: function () { try { plyr.destroy(); } catch (e) {} try { if (hp && hp.then) hp.then(function (x) { x && x.destroy(); }); } catch (e) {} } };
        }).catch(function () { plHideLoader(); fail(); });
    }
    else if (engine === 'dplayer') {
        container.innerHTML = '<div id="pl-dplayer" style="width:100%;height:100%"></div>';
        Promise.all([plNeed('hls'), plNeed('dplayer')]).then(function () {
            if (plStartEngine.cur !== token) return;
            var dp = new DPlayer({ container: document.getElementById('pl-dplayer'), autoplay: true, live: true, lang: 'en',
                video: { url: url, type: 'hls', pic: poster || undefined } });
            try { dp.on('playing', plHideLoader); } catch (e) {}
            currentVideoPlayer = { destroy: function () { try { dp.destroy(); } catch (e) {} } };
        }).catch(function () { plHideLoader(); fail(); });
    }
}

// تحميل مكتبة عند الحاجة بس (Clappr كان بيتحمّل مع كل زيارة حتى لو السيرفر iframe)
var plScriptState = {};
function plLoadScript(src, cb) {
    var st = plScriptState[src];
    if (st === 'ok') { cb(); return; }
    if (st && st.push) { st.push(cb); return; }
    plScriptState[src] = [cb];
    var el = document.createElement('script');
    el.src = src; el.async = true; el.__plw = 1;   // __plw: محرك البوب مايحسبهاش سكريبت إعلان
    el.onload = function () { var q = plScriptState[src]; plScriptState[src] = 'ok'; q.forEach(function (f) { try { f(); } catch (e) {} }); };
    el.onerror = function () { plScriptState[src] = null; };
    document.head.appendChild(el);
}

function initPlayer(item, retryCount) {
    retryCount = retryCount || 0;
    if (currentVideoPlayer) {
        try { if (typeof currentVideoPlayer.dispose === 'function') currentVideoPlayer.dispose(); } catch (e) {}
        try { if (typeof currentVideoPlayer.destroy === 'function') currentVideoPlayer.destroy(); } catch (e) {}
        currentVideoPlayer = null;
    }
    plStopAllMedia();
    container.innerHTML = '';
    plShowLoader();

   var decodedUrl = "proxy.php?data=" + encodeURIComponent(item.url);
    
    if (item.player === 'iframe' || item.type === 'embed') {
        // sandbox: بيقيّد الإطار المستدعى ويمنع فتح نوافذ منبثقة منه
        container.innerHTML = `<iframe src="${decodedUrl}" sandbox="allow-scripts allow-same-origin allow-forms allow-presentation" allowfullscreen allow="autoplay; encrypted-media; fullscreen"></iframe>`;
        var plFrame = container.querySelector('iframe');
        if (plFrame) { plFrame.addEventListener('load', plHideLoader); }
    } 
    else if (item.player === 'clappr') {
        var clapprDiv = document.createElement('div'); clapprDiv.id = 'clappr-player';
        container.appendChild(clapprDiv);
        // Clappr بيتحمّل هنا بس لما السيرفر يحتاجه
        plLoadScript('https://cdn.jsdelivr.net/npm/clappr@latest/dist/clappr.min.js', function () {
        if (!clapprDiv.parentNode) return;   // المشاهد غيّر السيرفر قبل ما المكتبة تخلص تحميل
        var clapprInstance = new Clappr.Player({ 
            source: decodedUrl, 
            parentId: "#clappr-player", 
            autoPlay: true, 
            width: '100%', 
            height: '100%',
            mimeType: "application/x-mpegURL",
            poster: plPosterUrl() || undefined,
            playback: {
                playInline: true,
                preload: 'auto',
                // إعدادات HLS لتثبيت الصوت ومنع التقطيع والتشويش
                hlsjsConfig: {
                    enableWorker: true,
                    lowLatencyMode: false,
                    backBufferLength: 30,
                    maxBufferLength: 30,
                    maxMaxBufferLength: 60,
                    liveSyncDurationCount: 3,
                    liveMaxLatencyDurationCount: 10,
                    manifestLoadingMaxRetry: 6,
                    levelLoadingMaxRetry: 6,
                    fragLoadingMaxRetry: 6,
                    nudgeMaxRetry: 10,
                    maxAudioFramesDrift: 1,
                    stretchShortVideoTrack: true,
                    forceKeyFrameOnDiscontinuity: true
                }
            }
        });
        currentVideoPlayer = clapprInstance;
        try { clapprInstance.on(Clappr.Events.PLAYER_PLAY, plHideLoader); } catch (e) {}
        // لو حصل خطأ في البث: إعادة تشغيل تلقائية (حتى 3 مرات)
        try {
            clapprInstance.on(Clappr.Events.PLAYER_ERROR, function () {
                if (currentVideoPlayer !== clapprInstance || retryCount >= 3) return;
                setTimeout(function () {
                    if (currentVideoPlayer === clapprInstance) initPlayer(item, retryCount + 1);
                }, 3000 * (retryCount + 1));
            });
        } catch (e) {}
        });
    } 
    else if (item.player === 'plyr' || item.player === 'dplayer' || item.player === 'hlsjs' || item.player === 'native') {
        plStartEngine(item.player, decodedUrl);
    }
    else {
        // video.js بيتحمّل عند الحاجة بس (توفير ~500KB على السيرفرات اللي مش بتستخدمه)
        container.innerHTML = `<video id="vjs-player" class="video-js vjs-default-skin vjs-big-play-centered" controls autoplay preload="auto"></video>`;
        var vjsStart = function () {
            if (!document.getElementById('vjs-player')) return;   // المشاهد غيّر السيرفر قبل ما المكتبة تخلص تحميل
            currentVideoPlayer = videojs('vjs-player', {
                autoplay: true,
                preload: 'auto',
                poster: plPosterUrl() || undefined,
                html5: { vhs: { limitRenditionByPlayerDimensions: true, useBandwidthFromLocalStorage: true } },
                sources: [{ src: decodedUrl, type: 'application/x-mpegURL' }]
            });
            try { currentVideoPlayer.on('playing', plHideLoader); } catch (e) {}
            try { currentVideoPlayer.on('error', plHideLoader); } catch (e) {}
        };
        if (window.videojs) { vjsStart(); }
        else { plNeed('videojs').then(vjsStart).catch(function () { plHideLoader(); }); }
    }
}

function openEmbed() { document.getElementById('embed_modal').style.display = 'flex'; }
function closeEmbed() { document.getElementById('embed_modal').style.display = 'none'; }
function copyEmbed() {
    var copyText = document.getElementById("embed_code");
    copyText.select();
    document.execCommand("copy");
    alert("تم نسخ الكود!");
}

function plServUrl() {
    var u = window.location.href.replace(/([?&])serv=\d+&?/, '$1').replace(/[?&]$/, '');
    return u + (u.indexOf('?') > -1 ? '&' : '?') + 'serv=' + (typeof plCurrentServ !== 'undefined' ? plCurrentServ : 1);
}

function shareStream() {
    if (navigator.share) {
        navigator.share({ title: 'مشاهدة البث المباشر', url: plServUrl() }).catch(console.error);
    } else {
        try {
            var tmp = document.createElement('textarea'); tmp.value = plServUrl();
            document.body.appendChild(tmp); tmp.select(); document.execCommand('copy'); document.body.removeChild(tmp);
        } catch (e) {}
        alert('تم نسخ رابط البث!');
    }
}

var plServ = Math.max(1, parseInt((location.search.match(/[?&]serv=(\d+)/) || [0, 1])[1], 10) || 1);
if (plServ > items.length) plServ = 1;
var plCurrentServ = plServ;
if (items.length) {
    initPlayer(items[plServ - 1]);
    items.forEach(function(item, index) {
        var btn = document.createElement("button");
        btn.textContent = item.name;
        btn.className = "tab-btn" + (index === plServ - 1 ? " active" : "");
        btn.onclick = function() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            plCurrentServ = index + 1;
            initPlayer(item);
        };
        buttonsDiv.appendChild(btn);
    });
}
</script>

<script>
// -----------------------------------------------------------------------------
// تصميم المشغل: لوجو + كتابة + إعلانات + ملء الشاشة (إضافات - مش بتغير في المشغل نفسه)
// -----------------------------------------------------------------------------
var PL = <?php echo json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function toggleFullscreen() {
    var el = document.documentElement;
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        (el.requestFullscreen || el.webkitRequestFullscreen || function(){}).call(el);
    } else {
        (document.exitFullscreen || document.webkitExitFullscreen || function(){}).call(document);
    }
}

function plHexRgba(hex, a) {
    var n = parseInt(hex.slice(1), 16);
    return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + (a / 100) + ')';
}

function plAdFrame(code) {
    var f = document.createElement('iframe');
    f.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox');
    f.setAttribute('scrolling', 'no');
    f.srcdoc = '<!DOCTYPE html><html><head><meta charset="UTF-8"><base target="_blank"><style>html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:transparent}body{display:flex;align-items:center;justify-content:center}</style></head><body>' + code + '</' + 'body></html>';
    return f;
}

function plClamp(v, a, b) { v = +v; return isNaN(v) ? a : Math.max(a, Math.min(b, v)); }

// --- اللوجو ---
(function () {
    var L = PL.logo; if (!L) return;
    var a = document.getElementById('pl_logo'), img = a.querySelector('img');
    var x = plClamp(L.x, 0, 100), y = plClamp(L.y, 0, 100);
    img.src = L.src;
    a.style.left = x + '%'; a.style.top = y + '%';
    a.style.transform = 'translate(-' + x + '%,-' + y + '%)';
    a.style.width = plClamp(L.width, 2, 80) + '%';
    a.style.opacity = plClamp(L.opacity, 5, 100) / 100;
    img.style.borderRadius = plClamp(L.radius, 0, 50) + '%';
    if (L.link) { a.href = L.link; a.style.pointerEvents = 'auto'; }
    a.style.display = 'block';
})();

// --- الكتابة ---
(function () {
    var T = PL.text; if (!T) return;
    var box = document.getElementById('pl_text'), s = box.querySelector('.pl_t');
    var x = plClamp(T.x, 0, 100), y = plClamp(T.y, 0, 100);
    s.textContent = T.content;
    box.style.top = y + '%';
    if (T.anim === 'marquee') {
        box.className = 'mq';
        box.style.setProperty('--pl-speed', plClamp(T.speed, 4, 60) + 's');
        box.style.transform = 'translateY(-' + y + '%)';
    } else {
        box.style.left = x + '%';
        box.style.transform = 'translate(-' + x + '%,-' + y + '%)';
        if (T.anim === 'blink') s.classList.add('pl-blink');
        if (T.anim === 'pulse') s.classList.add('pl-pulse');
        if (T.anim === 'fade') s.classList.add('pl-fade');
    }
    var sz = plClamp(T.size, 8, 80);
    s.style.fontSize = sz + 'px';
    s.style.fontSize = 'max(10px, ' + (sz / 8) + 'cqw)';
    s.style.color = T.color;
    s.style.fontWeight = T.weight;
    s.style.opacity = plClamp(T.opacity, 5, 100) / 100;
    s.style.background = plHexRgba(T.bg, plClamp(T.bg_opacity, 0, 100));
    s.style.borderRadius = plClamp(T.radius, 0, 40) + 'px';
    box.style.display = 'block';
})();

// --- إعلان علوي / سفلي ---
(function () {
    var A = PL.ads || {};
    if (A.top) {
        var t = document.getElementById('ad_top');
        t.appendChild(plAdFrame(A.top.code));
        t.insertAdjacentHTML('beforeend', '<span class="ad-tag">إعلان</span>');
        t.style.display = 'block';
    }
    if (A.bottom) {
        var b = document.getElementById('ad_bottom');
        b.appendChild(plAdFrame(A.bottom.code));
        b.insertAdjacentHTML('beforeend', '<span class="ad-tag">إعلان</span>');
        if (+A.bottom.closable) {
            var x = document.createElement('button');
            x.className = 'ad-x'; x.textContent = '✕'; x.title = 'إغلاق';
            x.onclick = function () { b.style.display = 'none'; document.documentElement.style.setProperty('--bot-h', '0px'); };
            b.appendChild(x);
            var ca = Math.max(0, Math.min(60, parseInt(A.bottom.close_after, 10) || 0));   // ظهور زر الإغلاق بعد N ثانية
            if (ca > 0) {
                x.style.display = 'none';
                var cd = document.createElement('span');
                cd.setAttribute('style', 'position:absolute;top:2px;left:2px;z-index:3;background:rgba(0,0,0,.7);color:#fff;border-radius:10px;padding:1px 8px;font:11px/18px Cairo,sans-serif');
                cd.textContent = 'إغلاق بعد ' + ca + ' ث';
                b.appendChild(cd);
                var civ = setInterval(function () {
                    ca--;
                    if (ca <= 0) { clearInterval(civ); if (cd.parentNode) cd.parentNode.removeChild(cd); x.style.display = ''; }
                    else { cd.textContent = 'إغلاق بعد ' + ca + ' ث'; }
                }, 1000);
            }
        }
        b.style.display = 'block';
    }
})();

// --- إعلان قبل البث (Pre-roll) ---
(function () {
    var P = (PL.ads || {}).preroll; if (!P) return;
    var w = plClamp(P.width, 120, 900), h = plClamp(P.height, 60, 700);
    var total = plClamp(P.seconds, 3, 60), skipAfter = plClamp(P.skip_after, 0, total);
    var el = document.createElement('div'); el.id = 'preroll';
    var box = document.createElement('div'); box.className = 'pr-box';
    box.style.width = w + 'px'; box.style.height = h + 'px';
    box.appendChild(plAdFrame(P.code));
    box.insertAdjacentHTML('beforeend', '<span class="ad-tag">إعلان</span>');
    var bar = document.createElement('div'); bar.className = 'pr-bar';
    var count = document.createElement('span');
    var skip = document.createElement('button'); skip.className = 'pr-skip'; skip.textContent = 'تخطي ⏭'; skip.style.display = 'none';
    bar.appendChild(count); bar.appendChild(skip);
    el.appendChild(box); el.appendChild(bar);
    document.body.appendChild(el);
    var left = total;
    function close() { clearInterval(timer); if (el.parentNode) el.parentNode.removeChild(el); }
    skip.onclick = close;
    function tick() {
        count.textContent = 'سيبدأ البث بعد ' + left + ' ثانية';
        if ((total - left) >= skipAfter) skip.style.display = 'inline-block';
        if (left <= 0) close();
        left--;
    }
    var timer = setInterval(tick, 1000); tick();
})();

// --- إعلان جانبي عائم (بعد تأخير) ---
(function () {
    var C = (PL.ads || {}).corner; if (!C) return;
    var w = plClamp(C.width, 120, 500), h = plClamp(C.height, 60, 500);
    setTimeout(function () {
        var el = document.createElement('div'); el.id = 'corner_ad';
        el.style.width = w + 'px'; el.style.height = h + 'px';
        el.style[C.side === 'right' ? 'right' : 'left'] = '12px';
        el.appendChild(plAdFrame(C.code));
        el.insertAdjacentHTML('beforeend', '<span class="ad-tag">إعلان</span>');
        var x = document.createElement('button'); x.className = 'ad-x'; x.textContent = '✕'; x.title = 'إغلاق';
        x.onclick = function () { el.parentNode && el.parentNode.removeChild(el); };
        el.appendChild(x);
        document.body.appendChild(el);
    }, plClamp(C.delay, 0, 300) * 1000);
})();

// --- إعلانات البوب: بتتنفذ في الصفحة الرئيسية علشان الـ popunder يشتغل ---
function plInject(code) {
    var tpl = document.createElement('template');
    tpl.innerHTML = code;
    Array.prototype.slice.call(tpl.content.childNodes).forEach(function (n) {
        if (n.nodeName === 'SCRIPT') {
            var s = document.createElement('script');
            for (var i = 0; i < n.attributes.length; i++) s.setAttribute(n.attributes[i].name, n.attributes[i].value);
            s.text = n.textContent;
            document.body.appendChild(s);
        } else {
            document.body.appendChild(document.importNode(n, true));
        }
    });
}

// --- تشغيل محرك البوب (js/pop-engine.js): تحميل الأكواد + عدّاد النقرات + طبقة النقر ---
(function () {
    if (!window.PopEngine) return;
    PopEngine.init({ scope: 'player', pops: PL.pops || [], opts: PL.pop_opts || {} });
    try {
        var it0 = (typeof items !== 'undefined' && items.length) ? items[0] : null;
        if (it0 && (it0.player === 'iframe' || it0.type === 'embed')) PopEngine.armShield(container);
    } catch (e) {}
})();

// --- كود الإحصائيات (Histats وغيره) ---
(function () {
    if (!PL.tracking) return;
    try { plInject(PL.tracking); } catch (e) {}
})();

// --- عند تغيير السيرفر: نضبط طبقة النقر حسب نوع المشغل (iframe محتاج طبقة، غيره لأ) ---
// (اللودر بقى بيتدار جوه initPlayer نفسه، فمفيش تكرار هنا)
(function () {
    var orig = initPlayer;
    initPlayer = function (item) {
        var r = orig.apply(this, arguments);
        try {
            if (window.PopEngine) {
                if (item && (item.player === 'iframe' || item.type === 'embed')) PopEngine.armShield(container);
                else PopEngine.disarmShield();
            }
        } catch (e) {}
        return r;
    };
})();
</script>

<?php if ($protectDev) { ?>
<script>
// -----------------------------------------------------------------------------
// سكريبت الحماية "الضربة القاضية" - Order 66
// -----------------------------------------------------------------------------
(function() {
    window.isTerminated = false;

    function executeOrder66() {
        if (window.isTerminated) return;
        window.isTerminated = true;

        if (typeof videojs !== 'undefined') {
            videojs.getAllPlayers().forEach(player => { player.dispose(); });
        }
        if (typeof currentVideoPlayer !== 'undefined' && currentVideoPlayer.destroy) {
            currentVideoPlayer.destroy();
        }

        const container = document.getElementById('player_container');
        if (container) {
            container.innerHTML = "";
            container.style.display = "none";
        }

        document.body.innerHTML = `
            <div style="background:#000; color:red; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; font-family:sans-serif; position:fixed; top:0; left:0; width:100%; z-index:999999;">
                <h1 style="font-size:30px; margin-bottom:10px;">⚠️ تم إيقاف المشغل</h1>
                <p style="color:#fff; font-size:18px;">يرجى إغلاق أدوات المطور (F12) وتحديث الصفحة.</p>
                <button onclick="location.reload()" style="padding:12px 25px; background:red; color:#fff; border:none; border-radius:5px; cursor:pointer; margin-top:20px; font-weight:bold;">إعادة تحميل</button>
            </div>`;
        window.stop();
    }

    // فخ الديباجر
    setInterval(function() {
        const start = performance.now();
        debugger; 
        if (performance.now() - start > 100) {
            executeOrder66();
        }
    }, 200);

    window.addEventListener('keydown', function(e) {
        if (e.key === "F12" || (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase())) || (e.ctrlKey && e.key.toUpperCase() === 'U')) {
            e.preventDefault();
            executeOrder66();
        }
    });

    setInterval(() => { console.clear(); }, 50);
})();
</script>
<?php } ?>
</body>
</html>
<?php
// قفل الخزنة وتحويل كل ما سبق لـ Base64
$html_full_code = ob_get_clean();
if (!$protectShell) { echo $html_full_code; exit; }   // من غير تشفير الصفحة 
$encoded_content = base64_encode($html_full_code); 
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Streaming...</title>
    <style>body { background: #000; margin: 0; padding: 0; overflow: hidden; }</style>
</head>





<body>
    <script>
        (function() {
            try {
                var data = "<?php echo $encoded_content; ?>";
                var binString = atob(data);
                var bytes = Uint8Array.from(binString, function(m) {
                    return m.charCodeAt(0);
                });
                var decoded = new TextDecoder().decode(bytes);
                
                document.open();
                document.write(decoded);
                document.close();
            } catch (e) {
                location.reload();
            }
        })();
    </script>
    <noscript></noscript>
</body>
</html>