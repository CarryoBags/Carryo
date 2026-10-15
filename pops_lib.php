<?php
// مكتبة مشتركة: بتبني سكريبت JavaScript فيه محرك البوب + أكواد الإعلانات (بيستخدمه pops.js.php و embed.js.php)
require_once __DIR__ . '/player_defaults.php';

function build_pops_js($scope = 'live') {
    $scope = ($scope === 'player') ? 'player' : 'live';
    $pops = [];

    // 1) أماكن البوب 1-3 (من data/player_settings.json)
    $lps = player_settings(__DIR__ . '/data/player_settings.json');
    foreach (['pop1', 'pop2', 'pop3'] as $k) {
        $pp = isset($lps['pops'][$k]) ? $lps['pops'][$k] : null;
        if (!is_array($pp) || empty($pp['enabled']) || trim((string)($pp['code'] ?? '')) === '') continue;
        $trg = isset($pp['trigger']) ? $pp['trigger'] : 'load';
        $pops[] = [
            'id'      => $k,
            'code'    => (string)$pp['code'],
            'trigger' => in_array($trg, ['load', 'delay', 'click'], true) ? $trg : 'load',
            'delay'   => max(0, min(300, (int)($pp['delay'] ?? 0))),
            'once'    => !empty($pp['once']) ? 1 : 0
        ];
    }

    // 2) سكريبتات اللوحة (من data/panel_settings.json)
    $psjFile = __DIR__ . '/data/panel_settings.json';
    if (is_file($psjFile)) {
        $psj = json_decode((string)file_get_contents($psjFile), true);
        if (is_array($psj) && !empty($psj['pop_scripts_enabled']) && !empty($psj['pop_scripts']) && is_array($psj['pop_scripts'])) {
            $n = 0;
            foreach ($psj['pop_scripts'] as $it) {
                $n++;
                $code = is_array($it) ? trim((string)($it['code'] ?? '')) : trim((string)$it);
                if ($code === '') continue;
                $trg = is_array($it) ? ($it['trigger'] ?? 'load') : 'load';
                $pops[] = [
                    'id'      => 'ps' . $n,
                    'code'    => $code,
                    'trigger' => in_array($trg, ['load', 'delay', 'click'], true) ? $trg : 'load',
                    'delay'   => is_array($it) ? max(0, min(300, (int)($it['delay'] ?? 0))) : 0,
                    'once'    => 0
                ];
            }
        }
    }

    // إعدادات الطبقة والعدّاد (كانت فاضية {} في الوضع ده، فالطبقة والعدّاد ما كانوش بيشتغلوا خالص)
    $po = (isset($lps['pop_opts']) && is_array($lps['pop_opts'])) ? $lps['pop_opts'] : [];
    $sm = isset($po['shield_mode']) ? $po['shield_mode'] : 'off';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $opts = [
        'shield_mode'     => in_array($sm, ['off', 'first', 'cooldown'], true) ? $sm : 'off',
        'shield_cooldown' => max(1, min(60, (int)(isset($po['shield_cooldown']) ? $po['shield_cooldown'] : 4))),
        'count'           => !empty($po['count']) ? 1 : 0,
        'endpoint'        => '//' . $_SERVER['HTTP_HOST'] . $dir . '/click.php'   // رابط كامل: الصفحة المضيفة دومين تاني
    ];

    $engine = (string)@file_get_contents(__DIR__ . '/js/pop-engine.js');
    $jf = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $cfg = json_encode($pops, $jf);
    $optsJson = json_encode($opts, $jf);

    $js  = "(function () {\n";
    $js .= "if (window.__panelPopsLoaded) return; window.__panelPopsLoaded = 1;\n";   // لو اتحمّل مرتين مايشتغلش مرتين
    $js .= $engine . "\n";
    $js .= "var cfg = " . $cfg . ";\n";
    $js .= "var opts = " . $optsJson . ";\n";
    // __plHostWrap: الحاوية اللي embed.js.php حاطت فيها الـ iframe. الطبقة بتتحط فوقها علشان النقرة توصل للصفحة المضيفة
    $js .= "function go() { if (window.PopEngine && cfg.length) { PopEngine.init({ scope: " . json_encode($scope) . ", pops: cfg, opts: opts }); if (window.__plHostWrap) { PopEngine.armShield(window.__plHostWrap); } } }\n";
    $js .= "if (document.body) { go(); } else { document.addEventListener('DOMContentLoaded', go); }\n";
    $js .= "})();\n";
    return $js;
}
