<?php
$full_url = "https://stad-yallashoot.live/";
// Get the 'id' parameter from the URL
$id = isset($_GET['id']) ? $_GET['id'] : '';
// Read the JSON file content
$jsonData = file_get_contents('data/data.json');
$data = json_decode($jsonData, true);
$urls = [];

// إعدادات البوب لصفحة live.php (من data/player_settings.json - تصميم المشغل)
require_once __DIR__ . '/player_defaults.php';
$lps = player_settings(__DIR__ . '/data/player_settings.json');
$livePops = [];
foreach (['pop1','pop2','pop3'] as $k) {
    $pp = $lps['pops'][$k];
    if (empty($pp['enabled']) || trim((string)$pp['code']) === '') continue;
    $sc = isset($pp['scope']) ? $pp['scope'] : 'both';
    if ($sc === 'player') continue;
    $livePops[] = [
        'id' => $k, 'code' => (string)$pp['code'],
        'trigger' => in_array($pp['trigger'], ['load','delay','click','layer'], true) ? $pp['trigger'] : 'load',
        'delay' => max(0, min(300, (int)$pp['delay'])), 'once' => !empty($pp['once']) ? 1 : 0
    ];
}
$lpo = $lps['pop_opts'];
$liveOpts = [
    'shield_mode' => in_array($lpo['shield_mode'], ['off','first','cooldown'], true) ? $lpo['shield_mode'] : 'cooldown',
    'shield_cooldown' => max(1, min(60, (int)$lpo['shield_cooldown'])),
    'count' => !empty($lpo['count']) ? 1 : 0,
    'endpoint' => 'click.php'
];

foreach ($data as $entry) {
    if ($entry['id'] === $id) {
        foreach ($entry as $key => $value) {
            if (is_numeric($key) && is_array($value) && isset($value['Stream_Url'])) {
                $streamUrl = $value['Stream_Url'];
                if (!empty($streamUrl)) {
                    // التعديل هنا: بدل ما يضيف الرابط مباشرة، يحوله لـ embed.php
                    $urls[] = "embed.php?url=" . urlencode($streamUrl) . "&from=live";
                }
            }
        }
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <!-- باقي الهيدر زي ما هو -->
    <meta charset="UTF-8">
    <title>Embed</title>
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { height: 100%; }
        body {
            margin: 0;
            background: #000;
            font-family: sans-serif;
            direction: rtl;
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }
        /* شريط السيرفرات: يفضل ظاهر فوق ولو السيرفرات كتير بيتزحلق بالعرض على الموبايل */
        .servers_list {
            display: flex;
            flex: 0 0 auto;
            width: 100%;
            background: #000;
            padding: 4px 2px;
            gap: 4px;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .servers_list::-webkit-scrollbar { display: none; }
        .servers_list button {
            flex: 1 0 auto;
            min-width: 92px;
            height: 42px;
            padding: 0 12px;
            border: 0;
            border-radius: 4px;
            background: #444;
            color: #fff !important;
            font-size: 15px;
            font-weight: 600;
            text-shadow: 0 1px 1px rgb(0 0 0 / 30%);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            white-space: nowrap;
            -webkit-tap-highlight-color: transparent;
        }
        .servers_list button.active {
            background: #1b7b35;
            transition: .3s all cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* منطقة المشغل: بتاخد كل المساحة الفاضلة تحت الشريط */
        .server_container {
            position: relative;
            flex: 1 1 auto;
            width: 100%;
            min-height: 0;
            background: #000;
            overflow: hidden;
        }
        .server_container iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
        }
        @media (max-width: 520px) {
            .servers_list button { min-width: 78px; height: 40px; font-size: 14px; padding: 0 8px; }
        }
        /* على الموبايل بالوضع الأفقي نقلّل ارتفاع الشريط علشان مساحة الفيديو تكبر */
        @media (orientation: landscape) and (max-height: 480px) {
            .servers_list button { height: 34px; font-size: 13px; }
        }
    </style>
</head>
<body>

<div class="servers_list" id="buttons"></div>
<div class="server_container">
    <iframe id="video" title="مشغل البث" src="about:blank" frameborder="0"
        sandbox="allow-scripts allow-same-origin allow-forms allow-presentation"
        allow="autoplay; encrypted-media; fullscreen"
        allowfullscreen></iframe>
</div>

<script>
var links = <?php echo json_encode($urls); ?>;
var iframe = document.getElementById("video");
var buttons = document.getElementById("buttons");

if(links.length){
    iframe.src = links[0];
    for (var i = 0; i < links.length; i++) {
        var button = document.createElement("button");
        button.innerHTML = "Server " + (i+1);
        if(i === 0) button.classList.add("active");
        button.onclick = function(index) {
            return function() {
                var activeButton = document.getElementsByClassName("active")[0];
                if(activeButton){
                    activeButton.classList.remove("active");
                }
                this.classList.add("active");
                iframe.src = links[index];
                if (window.PopEngine) PopEngine.armShield(document.querySelector('.server_container'));
            }
        }(i);
        buttons.appendChild(button);
    }
}
</script>

<script src="js/pop-engine.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/pop-engine.js'); ?>"></script>
<script>
(function () {
    if (!window.PopEngine) return;
    PopEngine.init({
        scope: 'live',
        pops: <?php echo json_encode($livePops, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        opts: <?php echo json_encode($liveOpts); ?>
    });
    PopEngine.armShield(document.querySelector('.server_container'));
})();
</script>

</body>
</html>