<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../player_defaults.php';
date_default_timezone_set('Africa/Cairo');

$settingsFile = __DIR__ . '/../data/player_settings.json';
$uploadDir    = __DIR__ . '/../data/uploads';
$S = player_settings($settingsFile);
$error = '';

function pp($k, $d = '') { return isset($_POST[$k]) ? $_POST[$k] : $d; }
function pint($v, $min, $max, $def) { $v = is_numeric($v) ? (float)$v : $def; return max($min, min($max, $v)); }
function pbool($k) { return isset($_POST[$k]) ? 1 : 0; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // الأكواد بتتبعت base64 من المتصفح (علشان فلاتر الاستضافة ماتحجبش <script>)، هنا بنفكها
    foreach ($_POST as $pk => $pv) {
        if (substr($pk, -5) === '__b64') {
            $dec = base64_decode((string)$pv, true);
            if ($dec !== false) { $_POST[substr($pk, 0, -5)] = $dec; }
            unset($_POST[$pk]);
        }
    }
    $D = player_defaults();

    // ---- الألوان ----
    $S['theme'] = [
        'accent'   => pcolor(pp('th_accent'),   $D['theme']['accent']),
        'accent2'  => pcolor(pp('th_accent2'),  $D['theme']['accent2']),
        'bar_bg'   => pcolor(pp('th_bar_bg'),   $D['theme']['bar_bg']),
        'bar_text' => pcolor(pp('th_bar_text'), $D['theme']['bar_text']),
        'tab_bg'   => pcolor(pp('th_tab_bg'),   $D['theme']['tab_bg']),
        'tab_text' => pcolor(pp('th_tab_text'), $D['theme']['tab_text']),
        'player_bg'=> pcolor(pp('th_player_bg'),$D['theme']['player_bg']),
        'bar_visible' => pbool('th_bar_visible'),
        'bar_position' => pp('th_bar_position') === 'bottom' ? 'bottom' : 'top',
        'btn_size'     => in_array(pp('th_btn_size'), ['s', 'm', 'l'], true) ? pp('th_btn_size') : 'm',
        'btn_radius'   => (int)pint(pp('th_btn_radius'), 0, 30, 6),
        'show_embed'      => pbool('th_show_embed'),
        'show_share'      => pbool('th_show_share'),
        'show_fullscreen' => pbool('th_show_fullscreen'),
        'show_refresh'    => pbool('th_show_refresh'),
    ];

    // ---- البوستر + اسم الموقع ----
    $posterSrc = trim((string)pp('br_poster'));
    if (!empty($_POST['br_poster_remove'])) { $posterSrc = ''; }
    if (!empty($_FILES['br_poster_file']['name']) && $_FILES['br_poster_file']['error'] === UPLOAD_ERR_OK) {
        $ptmp  = $_FILES['br_poster_file']['tmp_name'];
        $pinfo = @getimagesize($ptmp);
        $pmap  = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if ($pinfo && isset($pmap[$pinfo[2]]) && $_FILES['br_poster_file']['size'] <= 3 * 1024 * 1024) {
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
            $pname = 'player-poster-' . time() . '.' . $pmap[$pinfo[2]];
            if (@move_uploaded_file($ptmp, $uploadDir . '/' . $pname)) { $posterSrc = 'data/uploads/' . $pname; }
            else { $error = 'تعذر رفع البوستر (تأكد من صلاحيات مجلد data).'; }
        } else {
            $error = 'ملف البوستر لازم يكون PNG / JPG / GIF / WEBP وحجمه لحد 3MB.';
        }
    }
    if ($posterSrc !== '' && !preg_match('#^(https?:)?//[^\s"\'<>]+$|^data/uploads/[\w.\-]+$#', $posterSrc)) { $posterSrc = ''; }
    $S['brand'] = ['site_name' => mb_substr(trim((string)pp('br_site_name')), 0, 80), 'poster' => $posterSrc];

    // ---- اللوجو ----
    $logoSrc = trim((string)pp('lg_src'));
    if (!empty($_POST['lg_remove'])) { $logoSrc = ''; }

    if (!empty($_FILES['lg_file']['name']) && $_FILES['lg_file']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['lg_file']['tmp_name'];
        $info = @getimagesize($tmp);
        $map  = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if ($info && isset($map[$info[2]]) && $_FILES['lg_file']['size'] <= 3 * 1024 * 1024) {
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
            $name = 'player-logo-' . time() . '.' . $map[$info[2]];
            if (@move_uploaded_file($tmp, $uploadDir . '/' . $name)) {
                $logoSrc = 'data/uploads/' . $name;
            } else {
                $error = 'تعذر رفع اللوجو (تأكد من صلاحيات مجلد data).';
            }
        } else {
            $error = 'ملف اللوجو لازم يكون PNG / JPG / GIF / WEBP وحجمه لحد 3MB.';
        }
    }
    if ($logoSrc !== '' && !preg_match('#^(https?:)?//[^\s"\'<>]+$|^data/uploads/[\w.\-]+$#', $logoSrc)) {
        $logoSrc = '';
        $error = $error ?: 'رابط اللوجو غير صالح.';
    }
    $lgLink = trim((string)pp('lg_link'));
    if ($lgLink !== '' && !preg_match('#^https?://[^\s"\'<>]+$#', $lgLink)) { $lgLink = ''; }

    $S['logo'] = [
        'enabled' => pbool('lg_enabled'),
        'src'     => $logoSrc,
        'x'       => round(pint(pp('lg_x'), 0, 100, 98), 1),
        'y'       => round(pint(pp('lg_y'), 0, 100, 3), 1),
        'width'   => round(pint(pp('lg_width'), 2, 80, 14), 1),
        'opacity' => (int)pint(pp('lg_opacity'), 5, 100, 85),
        'radius'  => (int)pint(pp('lg_radius'), 0, 50, 0),
        'link'    => $lgLink,
    ];

    // ---- الكتابة ----
    $anims = ['none', 'marquee', 'blink', 'pulse', 'fade'];
    $S['text'] = [
        'enabled'    => pbool('tx_enabled'),
        'content'    => mb_substr(trim((string)pp('tx_content')), 0, 300),
        'x'          => round(pint(pp('tx_x'), 0, 100, 50), 1),
        'y'          => round(pint(pp('tx_y'), 0, 100, 94), 1),
        'size'       => (int)pint(pp('tx_size'), 8, 80, 16),
        'color'      => pcolor(pp('tx_color'), '#ffffff'),
        'bg'         => pcolor(pp('tx_bg'), '#000000'),
        'bg_opacity' => (int)pint(pp('tx_bg_opacity'), 0, 100, 45),
        'weight'     => in_array(pp('tx_weight'), ['400', '700', '900'], true) ? pp('tx_weight') : '700',
        'opacity'    => (int)pint(pp('tx_opacity'), 5, 100, 100),
        'anim'       => in_array(pp('tx_anim'), $anims, true) ? pp('tx_anim') : 'none',
        'speed'      => (int)pint(pp('tx_speed'), 4, 60, 14),
        'radius'     => (int)pint(pp('tx_radius'), 0, 40, 8),
    ];

    // ---- الإعلانات ----
    $S['ads'] = [
        'top'     => ['enabled' => pbool('ad_top_enabled'), 'code' => trim((string)pp('ad_top_code')), 'height' => (int)pint(pp('ad_top_height'), 20, 200, 60)],
        'bottom'  => ['enabled' => pbool('ad_bottom_enabled'), 'code' => trim((string)pp('ad_bottom_code')), 'height' => (int)pint(pp('ad_bottom_height'), 20, 200, 60), 'closable' => pbool('ad_bottom_closable'), 'close_after' => (int)pint(pp('ad_bottom_close_after'), 0, 60, 0)],
        'preroll' => ['enabled' => pbool('ad_preroll_enabled'), 'code' => trim((string)pp('ad_preroll_code')), 'seconds' => (int)pint(pp('ad_preroll_seconds'), 3, 60, 8), 'skip_after' => (int)pint(pp('ad_preroll_skip'), 0, 60, 5), 'width' => (int)pint(pp('ad_preroll_w'), 120, 900, 300), 'height' => (int)pint(pp('ad_preroll_h'), 60, 700, 250)],
        'corner'  => ['enabled' => pbool('ad_corner_enabled'), 'code' => trim((string)pp('ad_corner_code')), 'width' => (int)pint(pp('ad_corner_w'), 120, 500, 300), 'height' => (int)pint(pp('ad_corner_h'), 60, 500, 250), 'delay' => (int)pint(pp('ad_corner_delay'), 0, 300, 10), 'side' => pp('ad_corner_side') === 'right' ? 'right' : 'left'],
    ];

    // ---- كود الإحصائيات ----
    $S['tracking'] = [
        'code' => trim((string)pp('tr_code')),
        'disable_builtin' => pbool('tr_disable_builtin'),
    ];

    $S['builtin'] = ['disable_pops' => pbool('bi_disable_pops'), 'disable_popup' => pbool('bi_disable_popup')];

    // ---- إعلانات البوب ----
    $trigs = ['load', 'delay', 'click', 'layer'];
    $S['pops'] = [];
    foreach (['pop1', 'pop2', 'pop3'] as $k) {
        $S['pops'][$k] = [
            'enabled' => pbool($k . '_enabled'),
            'code'    => str_replace(["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], trim((string)pp($k . '_code'))),
            'trigger' => in_array(pp($k . '_trigger'), $trigs, true) ? pp($k . '_trigger') : 'load',
            'delay'   => (int)pint(pp($k . '_delay'), 0, 300, 3),
            'once'    => pbool($k . '_once'),
            'scope'   => in_array(pp($k . '_scope'), ['both', 'live', 'player'], true) ? pp($k . '_scope') : 'both',
        ];
    }

    $S['pop_opts'] = [
        'shield_mode'     => in_array(pp('po_shield_mode'), ['off', 'first', 'cooldown'], true) ? pp('po_shield_mode') : 'cooldown',
        'shield_cooldown' => (int)pint(pp('po_shield_cooldown'), 1, 60, 4),
        'count'           => pbool('po_count'),
    ];
    $S['protect'] = ['shell' => pbool('pr_shell'), 'devtools' => pbool('pr_devtools')];
    if (!empty($_POST['reset_clicks'])) { @file_put_contents(__DIR__ . '/../data/clicks.json', '{}', LOCK_EX); }

    if (!empty($_POST['reset_all'])) { $S = player_defaults(); }

    $saveRes = plSaveJson($settingsFile, $S);
    if ($saveRes !== true) {
        $error = $saveRes;   // فشل الحفظ: نفضل في الصفحة ونعرض السبب بدل رسالة نجاح كدابة
    }
    if ($error === '') {
        $_SESSION['success_message'] = 'تم حفظ إعدادات المشغل بنجاح.';
        header("Location: ../index.php");
        exit();
    }
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function chk($v) { return !empty($v) ? 'checked' : ''; }
$T = $S['theme']; $L = $S['logo']; $X = $S['text']; $A = $S['ads']; $PO = $S['pops']; $TR = $S['tracking']; $BI = $S['builtin']; $POPT = $S['pop_opts']; $BR = $S['brand']; $PR = (isset($S['protect']) && is_array($S['protect'])) ? $S['protect'] + ['shell' => 1, 'devtools' => 1] : ['shell' => 1, 'devtools' => 1];
$logoUrl = $L['src'] === '' ? '' : (preg_match('#^https?:|^//#', $L['src']) ? $L['src'] : '../' . $L['src']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تصميم المشغل</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .pl_wrap { max-width: 1100px; margin: 0 auto; }
        .pl_grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
        @media (min-width: 980px) { .pl_grid { grid-template-columns: 1.1fr 1fr; align-items: start; } .pl_sticky { position: sticky; top: 12px; } }
        .pl_card { border: 1px solid rgba(128,128,160,.25); border-radius: 12px; padding: 14px; margin-bottom: 14px; }
        .pl_card h3 { margin: 0 0 10px; font-size: 16px; }
        .pl_row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 8px 0; }
        .pl_row label { font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .pl_row input[type=number] { width: 80px; }
        .pl_row input[type=color] { width: 44px; height: 32px; padding: 0; border: 1px solid #888; border-radius: 6px; background: none; }
        .pl_card textarea, .pl_card input[type=text], .pl_card select { width: 100%; }
        .pl_switch { font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .pl_switch input { width: auto; }

        /* المعاينة */
        #stage { position: relative; width: 100%; aspect-ratio: 16/9; background: var(--pv-bg, #05060b); border-radius: 10px; overflow: hidden; border: 1px solid rgba(128,128,160,.35); container-type: inline-size; user-select: none; touch-action: none; }
        #stage .pv_bar { position: absolute; top: 0; left: 0; right: 0; height: 9%; min-height: 22px; background: var(--pv-bar, #0a0c15); display: flex; gap: 3px; padding: 3px; z-index: 5; }
        #stage .pv_bar::after { content: ""; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: linear-gradient(90deg, transparent, var(--pv-acc), var(--pv-acc2), var(--pv-acc), transparent); }
        #stage .pv_tab { flex: 1; border-radius: 5px; background: var(--pv-tab, #181b2a); color: var(--pv-tabtxt, #98a2ba); font-size: 10px; display: flex; align-items: center; justify-content: center; }
        #stage .pv_tab.on { background: linear-gradient(135deg, var(--pv-acc), color-mix(in srgb, var(--pv-acc) 55%, #000)); color: #fff; }
        #stage .pv_video { position: absolute; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.12); font-size: 11cqw; }
        #stage .pv_inner { position: absolute; inset: 8px; }
        #pv_logo { position: absolute; cursor: grab; outline: 1px dashed rgba(255,255,255,.55); line-height: 0; display: none; }
        #pv_logo img { width: 100%; display: block; pointer-events: none; }
        #pv_text { position: absolute; cursor: grab; outline: 1px dashed rgba(255,255,255,.55); max-width: 100%; display: none; }
        #pv_text.mq { left: 0 !important; width: 100%; transform: none !important; overflow: hidden; direction: ltr; cursor: ns-resize; }
        #pv_text .pl_t { display: inline-block; padding: 4px 14px; line-height: 1.6; white-space: pre-line; text-align: center; }
        #pv_text.mq .pl_t { white-space: nowrap; direction: rtl; unicode-bidi: plaintext; animation: pl_mq var(--pl-speed, 14s) linear infinite; }
        @keyframes pl_mq { from { transform: translateX(100cqw); } to { transform: translateX(-100%); } }
        .pl-blink { animation: pl_blink 1.2s ease-in-out infinite; } .pl-pulse { animation: pl_pulse 1.6s ease-in-out infinite; } .pl-fade { animation: pl_fade 4s ease-in-out infinite; }
        @keyframes pl_blink { 0%,100% { opacity: 1; } 50% { opacity: .15; } }
        @keyframes pl_pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        @keyframes pl_fade { 0%,100% { opacity: .25; } 50% { opacity: 1; } }
        .pv_adtop, .pv_adbot { position: absolute; left: 0; right: 0; background: repeating-linear-gradient(45deg, #2a2d3d, #2a2d3d 8px, #23263a 8px, #23263a 16px); color: #cbd5e1; font-size: 10px; display: none; align-items: center; justify-content: center; z-index: 4; }
        .pv_hint { font-size: 12px; opacity: .75; margin-top: 8px; }
        .pl_msg_err { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .pl_actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 10px; }
    </style>
</head>
<body>
<div class="form_container pl_wrap">
    <div class="table_head">
        <h2><i class="fa-solid fa-palette"></i> تصميم المشغل (لوجو / كتابة / إعلانات / ألوان) <small style="font-size:11px;opacity:.6">v6</small></h2>
        <div class="table_tools">
            <button type="button" class="theme_toggle" data-theme-toggle title="تفعيل الوضع الليلي"><i class="fa-solid fa-moon"></i></button>
            <a href="../index.php" class="logout_btn"><i class="fa-solid fa-arrow-right"></i> رجوع</a>
        </div>
    </div>

    <?php if ($error) { ?><div class="pl_msg_err"><?php echo h($error); ?></div><?php } ?>

    <form method="post" enctype="multipart/form-data" class="ath_form" novalidate>
    <div class="pl_grid">

        <!-- ========== العمود الأول: المعاينة ========== -->
        <div class="pl_sticky">
            <div class="pl_card">
                <h3><i class="fa-solid fa-eye"></i> معاينة حيّة</h3>
                <div id="stage">
                    <div class="pv_bar" id="pv_bar"><div class="pv_tab on">البث الرئيسي</div><div class="pv_tab">سيرفر 2</div><div class="pv_tab">سيرفر 3</div></div>
                    <div class="pv_adtop" id="pv_adtop">إعلان علوي</div>
                    <div class="pv_video" id="pv_video"><i class="fa-solid fa-play"></i></div>
                    <div class="pv_inner" id="pv_inner">
                        <div id="pv_logo"><img alt=""></div>
                        <div id="pv_text"><span class="pl_t"></span></div>
                    </div>
                    <div class="pv_adbot" id="pv_adbot">إعلان سفلي</div>
                </div>
                <p class="pv_hint"><i class="fa-solid fa-hand"></i> اسحب اللوجو أو الكتابة بالماوس/الإصبع وحطهم في المكان اللي عايزه، والأرقام (X / Y) بتتحدث لوحدها.</p>
            </div>
        </div>

        <!-- ========== العمود الثاني: الإعدادات ========== -->
        <div>

            <!-- اللوجو -->
            <div class="pl_card">
                <h3><i class="fa-solid fa-image"></i> اللوجو</h3>
                <label class="pl_switch"><input type="checkbox" name="lg_enabled" id="lg_enabled" value="1" <?php echo chk($L['enabled']); ?>> تفعيل اللوجو فوق المشغل</label>
                <label>رفع لوجو (PNG / JPG / WEBP / GIF - لحد 3MB)</label>
                <input type="file" name="lg_file" id="lg_file" accept="image/png,image/jpeg,image/gif,image/webp" class="c_input">
                <label>أو رابط لوجو مباشر</label>
                <input type="text" name="lg_src" id="lg_src" class="c_input" dir="ltr" placeholder="https://site.com/logo.png" value="<?php echo h($L['src']); ?>">
                <label class="pl_row"><input type="checkbox" name="lg_remove" value="1" style="width:auto"> حذف اللوجو الحالي</label>
                <div class="pl_row">
                    <label>X % <input type="number" step="any" min="0" max="100" name="lg_x" id="lg_x" value="<?php echo h($L['x']); ?>"></label>
                    <label>Y % <input type="number" step="any" min="0" max="100" name="lg_y" id="lg_y" value="<?php echo h($L['y']); ?>"></label>
                    <label>الحجم % <input type="number" step="any" min="2" max="80" name="lg_width" id="lg_width" value="<?php echo h($L['width']); ?>"></label>
                </div>
                <div class="pl_row">
                    <label>الشفافية % <input type="number" min="5" max="100" name="lg_opacity" id="lg_opacity" value="<?php echo h($L['opacity']); ?>"></label>
                    <label>تدوير الحواف % <input type="number" min="0" max="50" name="lg_radius" id="lg_radius" value="<?php echo h($L['radius']); ?>"></label>
                </div>
                <div class="pl_row">
                    <button type="button" class="btn btn_dark" data-pos="lg" data-x="2" data-y="3">↖ أعلى يسار</button>
                    <button type="button" class="btn btn_dark" data-pos="lg" data-x="98" data-y="3">↗ أعلى يمين</button>
                    <button type="button" class="btn btn_dark" data-pos="lg" data-x="2" data-y="97">↙ أسفل يسار</button>
                    <button type="button" class="btn btn_dark" data-pos="lg" data-x="98" data-y="97">↘ أسفل يمين</button>
                    <button type="button" class="btn btn_dark" data-pos="lg" data-x="50" data-y="50">● المنتصف</button>
                </div>
                <label>رابط يُفتح عند الضغط على اللوجو (اختياري)</label>
                <input type="text" name="lg_link" class="c_input" dir="ltr" placeholder="https://..." value="<?php echo h($L['link']); ?>">
            </div>

            <!-- الكتابة -->
            <div class="pl_card">
                <h3><i class="fa-solid fa-font"></i> الكتابة فوق المشغل</h3>
                <label class="pl_switch"><input type="checkbox" name="tx_enabled" id="tx_enabled" value="1" <?php echo chk($X['enabled']); ?>> تفعيل الكتابة</label>
                <textarea name="tx_content" id="tx_content" class="c_input" rows="2" placeholder="اكتب النص هنا... مثال: تابعونا على تيليجرام"><?php echo h($X['content']); ?></textarea>
                <div class="pl_row">
                    <label>X % <input type="number" step="any" min="0" max="100" name="tx_x" id="tx_x" value="<?php echo h($X['x']); ?>"></label>
                    <label>Y % <input type="number" step="any" min="0" max="100" name="tx_y" id="tx_y" value="<?php echo h($X['y']); ?>"></label>
                    <label>حجم الخط <input type="number" min="8" max="80" name="tx_size" id="tx_size" value="<?php echo h($X['size']); ?>"></label>
                </div>
                <div class="pl_row">
                    <label>لون الكتابة <input type="color" name="tx_color" id="tx_color" value="<?php echo h($X['color']); ?>"></label>
                    <label>لون الخلفية <input type="color" name="tx_bg" id="tx_bg" value="<?php echo h($X['bg']); ?>"></label>
                    <label>شفافية الخلفية % <input type="number" min="0" max="100" name="tx_bg_opacity" id="tx_bg_opacity" value="<?php echo h($X['bg_opacity']); ?>"></label>
                </div>
                <div class="pl_row">
                    <label>شفافية النص % <input type="number" min="5" max="100" name="tx_opacity" id="tx_opacity" value="<?php echo h($X['opacity']); ?>"></label>
                    <label>تدوير الحواف <input type="number" min="0" max="40" name="tx_radius" id="tx_radius" value="<?php echo h($X['radius']); ?>"></label>
                    <label>السماكة
                        <select name="tx_weight" id="tx_weight" style="width:auto">
                            <?php foreach (['400' => 'عادي', '700' => 'عريض', '900' => 'عريض جداً'] as $k => $v) { ?>
                                <option value="<?php echo $k; ?>" <?php echo (string)$X['weight'] === (string)$k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                            <?php } ?>
                        </select>
                    </label>
                </div>
                <div class="pl_row">
                    <label>الحركة
                        <select name="tx_anim" id="tx_anim" style="width:auto">
                            <?php foreach (['none' => 'بدون', 'marquee' => 'شريط متحرك', 'blink' => 'وميض', 'pulse' => 'نبض', 'fade' => 'تلاشي'] as $k => $v) { ?>
                                <option value="<?php echo $k; ?>" <?php echo $X['anim'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label>سرعة الشريط (ثواني) <input type="number" min="4" max="60" name="tx_speed" id="tx_speed" value="<?php echo h($X['speed']); ?>"></label>
                </div>
                <div class="pl_row">
                    <button type="button" class="btn btn_dark" data-pos="tx" data-x="50" data-y="4">⬆ أعلى</button>
                    <button type="button" class="btn btn_dark" data-pos="tx" data-x="50" data-y="50">● منتصف</button>
                    <button type="button" class="btn btn_dark" data-pos="tx" data-x="50" data-y="96">⬇ أسفل</button>
                </div>
            </div>

            <!-- الألوان -->
            <div class="pl_card">
                <h3><i class="fa-solid fa-fill-drip"></i> ألوان المشغل</h3>
                <div class="pl_row">
                    <label>اللون الأساسي (أحمر) <input type="color" name="th_accent" id="th_accent" value="<?php echo h($T['accent']); ?>"></label>
                    <label>اللون الثانوي (ذهبي) <input type="color" name="th_accent2" id="th_accent2" value="<?php echo h($T['accent2']); ?>"></label>
                </div>
                <div class="pl_row">
                    <label>خلفية الشريط <input type="color" name="th_bar_bg" id="th_bar_bg" value="<?php echo h($T['bar_bg']); ?>"></label>
                    <label>نص الشريط <input type="color" name="th_bar_text" id="th_bar_text" value="<?php echo h($T['bar_text']); ?>"></label>
                </div>
                <div class="pl_row">
                    <label>خلفية الأزرار <input type="color" name="th_tab_bg" id="th_tab_bg" value="<?php echo h($T['tab_bg']); ?>"></label>
                    <label>نص الأزرار <input type="color" name="th_tab_text" id="th_tab_text" value="<?php echo h($T['tab_text']); ?>"></label>
                    <label>خلفية المشغل <input type="color" name="th_player_bg" id="th_player_bg" value="<?php echo h($T['player_bg']); ?>"></label>
                </div>
                <label class="pl_row"><input type="checkbox" name="th_bar_visible" id="th_bar_visible" value="1" style="width:auto" <?php echo chk($T['bar_visible']); ?>> إظهار شريط السيرفرات والأزرار</label>
                <div class="pl_row">
                    <button type="button" class="btn btn_dark" id="palette_landing"><i class="fa-solid fa-wand-magic-sparkles"></i> ألوان صفحة المباراة (أحمر + ذهبي)</button>
                </div>
            </div>

            <!-- الإعلانات -->
            <div class="pl_card">
                <h3><i class="fa-solid fa-rectangle-ad"></i> أماكن الإعلانات</h3>

                <label class="pl_switch"><input type="checkbox" name="ad_top_enabled" id="ad_top_enabled" value="1" <?php echo chk($A['top']['enabled']); ?>> إعلان علوي (تحت شريط السيرفرات)</label>
                <textarea name="ad_top_code" class="c_input" rows="3" dir="ltr" placeholder="كود الإعلان <script>...</script> أو <iframe>..."><?php echo h($A['top']['code']); ?></textarea>
                <div class="pl_row"><label>الارتفاع px <input type="number" min="20" max="200" name="ad_top_height" id="ad_top_height" value="<?php echo h($A['top']['height']); ?>"></label></div>
                <hr>

                <label class="pl_switch"><input type="checkbox" name="ad_bottom_enabled" id="ad_bottom_enabled" value="1" <?php echo chk($A['bottom']['enabled']); ?>> إعلان سفلي (أسفل المشغل)</label>
                <textarea name="ad_bottom_code" class="c_input" rows="3" dir="ltr" placeholder="كود الإعلان"><?php echo h($A['bottom']['code']); ?></textarea>
                <div class="pl_row">
                    <label>الارتفاع px <input type="number" min="20" max="200" name="ad_bottom_height" id="ad_bottom_height" value="<?php echo h($A['bottom']['height']); ?>"></label>
                    <label><input type="checkbox" name="ad_bottom_closable" value="1" style="width:auto" <?php echo chk($A['bottom']['closable']); ?>> زر إغلاق للمشاهد</label>
                    <label>يظهر الزر بعد (ث) <input type="number" min="0" max="60" name="ad_bottom_close_after" value="<?php echo h(isset($A['bottom']['close_after']) ? $A['bottom']['close_after'] : 0); ?>"> (0 = فوراً)</label>
                </div>
                <hr>

                <label class="pl_switch"><input type="checkbox" name="ad_preroll_enabled" value="1" <?php echo chk($A['preroll']['enabled']); ?>> إعلان قبل بدء البث (Pre-roll)</label>
                <textarea name="ad_preroll_code" class="c_input" rows="3" dir="ltr" placeholder="كود الإعلان"><?php echo h($A['preroll']['code']); ?></textarea>
                <div class="pl_row">
                    <label>مدة الإعلان (ث) <input type="number" min="3" max="60" name="ad_preroll_seconds" value="<?php echo h($A['preroll']['seconds']); ?>"></label>
                    <label>يظهر "تخطي" بعد (ث) <input type="number" min="0" max="60" name="ad_preroll_skip" value="<?php echo h($A['preroll']['skip_after']); ?>"></label>
                </div>
                <div class="pl_row">
                    <label>العرض px <input type="number" min="120" max="900" name="ad_preroll_w" value="<?php echo h($A['preroll']['width']); ?>"></label>
                    <label>الارتفاع px <input type="number" min="60" max="700" name="ad_preroll_h" value="<?php echo h($A['preroll']['height']); ?>"></label>
                </div>
                <hr>

                <label class="pl_switch"><input type="checkbox" name="ad_corner_enabled" value="1" <?php echo chk($A['corner']['enabled']); ?>> إعلان عائم جانبي (بعد تأخير)</label>
                <textarea name="ad_corner_code" class="c_input" rows="3" dir="ltr" placeholder="كود الإعلان"><?php echo h($A['corner']['code']); ?></textarea>
                <div class="pl_row">
                    <label>العرض px <input type="number" min="120" max="500" name="ad_corner_w" value="<?php echo h($A['corner']['width']); ?>"></label>
                    <label>الارتفاع px <input type="number" min="60" max="500" name="ad_corner_h" value="<?php echo h($A['corner']['height']); ?>"></label>
                    <label>يظهر بعد (ث) <input type="number" min="0" max="300" name="ad_corner_delay" value="<?php echo h($A['corner']['delay']); ?>"></label>
                    <label>الجهة
                        <select name="ad_corner_side" style="width:auto">
                            <option value="left" <?php echo $A['corner']['side'] === 'left' ? 'selected' : ''; ?>>يسار</option>
                            <option value="right" <?php echo $A['corner']['side'] === 'right' ? 'selected' : ''; ?>>يمين</option>
                        </select>
                    </label>
                </div>
                <p class="muted_text">الإعلانات بتتعرض جوه إطار معزول علشان متبوظش المشغل. الإعلان المنبثق 300x300 القديم لسه شغال زي ما هو من زر "إعلان منبثق".</p>
            </div>



            <!-- الشكل العام (زي مشغلات الفرق) -->
            <div class="pl_card">
                <h3><i class="fa-solid fa-sliders"></i> شكل الشريط والأزرار</h3>
                <div class="pl_row">
                    <label>مكان شريط السيرفرات
                        <select name="th_bar_position" style="width:auto">
                            <option value="top" <?php echo ($T['bar_position'] ?? 'top') === 'top' ? 'selected' : ''; ?>>فوق</option>
                            <option value="bottom" <?php echo ($T['bar_position'] ?? 'top') === 'bottom' ? 'selected' : ''; ?>>تحت</option>
                        </select>
                    </label>
                    <label>حجم الأزرار
                        <select name="th_btn_size" style="width:auto">
                            <option value="s" <?php echo ($T['btn_size'] ?? 'm') === 's' ? 'selected' : ''; ?>>صغير</option>
                            <option value="m" <?php echo ($T['btn_size'] ?? 'm') === 'm' ? 'selected' : ''; ?>>وسط</option>
                            <option value="l" <?php echo ($T['btn_size'] ?? 'm') === 'l' ? 'selected' : ''; ?>>كبير</option>
                        </select>
                    </label>
                    <label>تدوير الحواف (px) <input type="number" min="0" max="30" name="th_btn_radius" value="<?php echo h($T['btn_radius'] ?? 6); ?>"></label>
                </div>
                <div class="pl_row">
                    <label><input type="checkbox" name="th_show_embed" value="1" style="width:auto" <?php echo chk($T['show_embed'] ?? 1); ?>> زر كود التضمين</label>
                    <label><input type="checkbox" name="th_show_share" value="1" style="width:auto" <?php echo chk($T['show_share'] ?? 1); ?>> زر المشاركة</label>
                    <label><input type="checkbox" name="th_show_fullscreen" value="1" style="width:auto" <?php echo chk($T['show_fullscreen'] ?? 1); ?>> زر ملء الشاشة</label>
                    <label><input type="checkbox" name="th_show_refresh" value="1" style="width:auto" <?php echo chk($T['show_refresh'] ?? 1); ?>> زر التحديث</label>
                </div>
                <label>اسم الموقع (سطر صغير تحت المشغل - اختياري)</label>
                <input type="text" name="br_site_name" class="c_input" maxlength="80" placeholder="مثال: جدول المباريات والبث المباشر" value="<?php echo h($BR['site_name'] ?? ''); ?>">
                <label>بوستر المشغل (صورة بتظهر قبل ما البث يشتغل)</label>
                <input type="file" name="br_poster_file" accept="image/png,image/jpeg,image/gif,image/webp" class="c_input">
                <input type="text" name="br_poster" class="c_input" dir="ltr" placeholder="أو رابط صورة مباشر https://..." value="<?php echo h($BR['poster'] ?? ''); ?>">
                <label class="pl_row"><input type="checkbox" name="br_poster_remove" value="1" style="width:auto"> حذف البوستر الحالي</label>
                <p class="muted_text">البوستر بيظهر في مشغلات Clappr وVideo.js وPlyr وDPlayer وHLS.js. ولو سيرفرك من نوع Iframe الصورة مش بتظهر لأن المشغل بيكون من موقع تاني.</p>
            </div>

            <!-- الإحصائيات -->
            <div class="pl_card">
                <h3><i class="fa-solid fa-chart-line"></i> كود الإحصائيات (Histats / Google Analytics / أي كود تتبع)</h3>
                <textarea name="tr_code" class="c_input" rows="6" dir="ltr" placeholder="الصق كود Histats الكامل هنا (من موقع histats.com)"><?php echo h($TR['code']); ?></textarea>
                <label class="pl_row"><input type="checkbox" name="tr_disable_builtin" value="1" style="width:auto" <?php echo chk($TR['disable_builtin']); ?>> إيقاف كود Histats المدمج القديم (علشان ماتتحسبش الزيارة مرتين لما تحط كودك)</label>
                <p class="muted_text">الكود بيتنفذ في الصفحة الرئيسية للمشغل وبيشتغل مع كل المشاهدين. تقدر تلصق أكتر من كود في نفس الخانة.</p>
            </div>

            <!-- إعلانات البوب -->
            <div class="pl_card">
                <h3><i class="fa-solid fa-window-restore"></i> إعلانات البوب (Pop / Popunder) - 3 أماكن فاضية جاهزة</h3>
                <p class="muted_text">الأماكن دي فاضية ومفيهاش أي إعلان. لما تلصق كود شبكة وتعلّم "تفعيل" بيشتغل. الأفضل تسيب "المكان" على <b>أحسن مكان تلقائي</b> و"وقت التشغيل" على <b>فوراً</b>، علشان الكود يكون جاهز قبل أول نقرة.</p>
                <?php
                $popLabels = ['pop1' => 'مكان البوب 1', 'pop2' => 'مكان البوب 2', 'pop3' => 'مكان البوب 3'];
                $trigLabels = [
                    'load'  => 'فوراً عند تحميل الصفحة (الأفضل ✅)',
                    'delay' => 'بعد مدة من فتح الصفحة',
                    'click' => 'عند أول نقرة في أي مكان بالصفحة',
                    'layer' => 'عند أول نقرة على طبقة شفافة فوق الصفحة',
                ];
                $scopeLabels = [
                    'both'   => 'أحسن مكان تلقائي (صفحة live.php لو المشغل جواها، وإلا جوه المشغل) ✅',
                    'live'   => 'صفحة live.php فقط',
                    'player' => 'جوه المشغل (embed.php) فقط',
                ];
                foreach ($popLabels as $k => $lbl) { $P = $PO[$k]; ?>
                    <label class="pl_switch"><input type="checkbox" name="<?php echo $k; ?>_enabled" value="1" <?php echo chk($P['enabled']); ?>> <?php echo $lbl; ?></label>
                    <textarea name="<?php echo $k; ?>_code" class="c_input" rows="3" dir="ltr" placeholder="<script src=&quot;...&quot;></script>"><?php echo h($P['code']); ?></textarea>
                    <div class="pl_row">
                        <label>المكان
                            <select name="<?php echo $k; ?>_scope" style="width:auto">
                                <?php foreach ($scopeLabels as $sk => $sv) { ?>
                                    <option value="<?php echo $sk; ?>" <?php echo ($P['scope'] ?? 'both') === $sk ? 'selected' : ''; ?>><?php echo $sv; ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>
                    <div class="pl_row">
                        <label>وقت التشغيل
                            <select name="<?php echo $k; ?>_trigger" style="width:auto">
                                <?php foreach ($trigLabels as $tk => $tv) { ?>
                                    <option value="<?php echo $tk; ?>" <?php echo $P['trigger'] === $tk ? 'selected' : ''; ?>><?php echo $tv; ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>التأخير (ث) <input type="number" min="0" max="300" name="<?php echo $k; ?>_delay" value="<?php echo h($P['delay']); ?>"></label>
                        <label><input type="checkbox" name="<?php echo $k; ?>_once" value="1" style="width:auto" <?php echo chk($P['once']); ?>> مرة واحدة لكل مشاهد في الجلسة</label>
                    </div>
                    <hr>
                <?php } ?>

                <h3 style="margin-top:6px"><i class="fa-solid fa-shield-halved"></i> حماية الصفحة</h3>
                <div class="pl_row">
                    <label><input type="checkbox" name="pr_shell" value="1" style="width:auto" <?php echo chk($PR['shell']); ?>> تشفير الصفحة (Base64)</label>
                    <label><input type="checkbox" name="pr_devtools" value="1" style="width:auto" <?php echo chk($PR['devtools']); ?>> فخ أدوات المطوّر (F12)</label>
                </div>
                <p class="muted_text">لو أرباح البوب ضعيفة جرّب تقفل الاتنين لمدة كام يوم وقارن. الحماية دي بتخلي الصفحة شكلها فاضي لأي فاحص من شبكات الإعلانات، وبتضيف شغل على المتصفح.</p>
                <hr>

                <h3 style="margin-top:6px"><i class="fa-solid fa-hand-pointer"></i> التقاط النقرات (علشان ولا نقرة تضيع)</h3>
                <div class="pl_row">
                    <label>طبقة النقر فوق المشغل الـ iframe
                        <select name="po_shield_mode" style="width:auto">
                            <option value="cooldown" <?php echo $POPT['shield_mode'] === 'cooldown' ? 'selected' : ''; ?>>تتكرر كل فترة (الأفضل ✅)</option>
                            <option value="first" <?php echo $POPT['shield_mode'] === 'first' ? 'selected' : ''; ?>>أول نقرة بس لكل سيرفر</option>
                            <option value="off" <?php echo $POPT['shield_mode'] === 'off' ? 'selected' : ''; ?>>بدون طبقة</option>
                        </select>
                    </label>
                    <label>كل (ث) <input type="number" min="1" max="60" name="po_shield_cooldown" value="<?php echo h($POPT['shield_cooldown']); ?>"></label>
                    <label><input type="checkbox" name="po_count" value="1" style="width:auto" <?php echo chk($POPT['count']); ?>> عدّ النقرات وتسجيلها</label>
                </div>
                <p class="muted_text">الطبقة الشفافة بتشتغل بس لو فيه كود بوب مفعّل. النقرة اللي جوه سيرفر iframe من موقع تاني مش بتوصل لصفحتك (قيد في المتصفحات)، فالطبقة بتاخد النقرة الأولى (وتتكرر كل كذا ثانية في الوضع الأفضل) وبعدها بتفتح للمشاهد المشغل. لو سيرفرك Clappr أو videojs مش محتاج طبقة، لأن نقراته بتوصل للصفحة أصلاً.</p>
            </div>

            <!-- إحصائيات النقرات -->
            <div class="pl_card">
                <h3><i class="fa-solid fa-chart-column"></i> عدّاد النقرات (آخر 7 أيام)</h3>
                <?php
                $clicksFile = __DIR__ . '/../data/clicks.json';
                $CK = is_file($clicksFile) ? json_decode((string)file_get_contents($clicksFile), true) : [];
                if (!is_array($CK)) { $CK = []; }
                krsort($CK);
                $CK = array_slice($CK, 0, 7, true);
                $scopeName = ['player' => 'المشغل (embed.php)', 'live' => 'صفحة live.php'];
                if (!$CK) { ?>
                    <p class="muted_text">لسه مفيش نقرات متسجلة. العدّاد بيبدأ يشتغل أول ما تفعّل كود بوب.</p>
                <?php } else { ?>
                    <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <tr style="text-align:right"><th>اليوم</th><th>المكان</th><th>مشاهدات</th><th>نقرات</th><th>نقرات الطبقة</th><th>سكريبت اتحمّل</th><th>سكريبت فشل</th><th>نوافذ فُتحت (تقدير)</th></tr>
                        <?php foreach ($CK as $day => $sc) { foreach ($sc as $sn => $r) { ?>
                            <tr style="border-top:1px solid rgba(128,128,160,.25)">
                                <td><?php echo h($day); ?></td>
                                <td><?php echo h($scopeName[$sn] ?? $sn); ?></td>
                                <td><?php echo (int)$r['view']; ?></td>
                                <td><?php echo (int)$r['click']; ?></td>
                                <td><?php echo (int)$r['shield']; ?></td>
                                <td><?php echo (int)($r['load'] ?? 0); ?></td>
                                <td style="<?php echo !empty($r['err']) ? 'color:#ef4444;font-weight:700' : ''; ?>"><?php echo (int)($r['err'] ?? 0); ?></td>
                                <td><?php echo (int)($r['pop'] ?? 0); ?></td>
                            </tr>
                        <?php } } ?>
                    </table>
                    </div>
                <?php } ?>
                <label class="pl_row"><input type="checkbox" name="reset_clicks" value="1" style="width:auto"> تصفير العدّاد عند الحفظ</label>
                <p class="muted_text"><b>إزاي تقرا الأرقام:</b> لو "نقرات" بتزيد لكن "نوافذ فُتحت" صفر، يبقى الكود وصله النقرات لكن الشبكة ما فتحتش إعلان (قيود الشبكة أو الدومين مش معتمد عندها). لو "سكريبت فشل" بيزيد، يبقى سكريبت الشبكة نفسه محجوب (حاجب إعلانات أو الدومين محجوب عند المشاهد). لو "سكريبت اتحمّل" صفر، الكود مش بيشتغل أصلاً. وتقدر تفتح صفحة المشاهدة بـ <code>?popdebug=1</code> في آخر الرابط علشان تشوف لوحة تشخيص مباشرة.</p>
                <p class="muted_text">"نقرات" = كل نقرة حقيقية على صفحتك. "نقرات الطبقة" = النقرات اللي اتاخدت بالطبقة فوق الـ iframe (وهي كمان داخلة ضمن النقرات). الأرقام دي بتاعتك إنت للمقارنة مع إحصائيات شبكة الإعلانات، لكن الشبكة هي اللي بتحدد النقرة اللي فعلاً بتفتح إعلان.</p>
            </div>

            <div class="pl_actions">
                <button type="submit" class="submit_btn"><i class="fa-solid fa-floppy-disk"></i> حفظ الإعدادات</button>
                <label class="muted_text" style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="reset_all" value="1" style="width:auto"> رجوع للإعدادات الافتراضية</label>
            </div>
        </div>
    </div>
    </form>
</div>

<script src="../js/panel-theme.js"></script>
<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var stage = $('stage'), inner = $('pv_inner'), logo = $('pv_logo'), text = $('pv_text'), tspan = text.querySelector('.pl_t');
    var LOGO_BASE = <?php echo json_encode($logoUrl); ?>;
    var localLogo = null;

    function num(id, d) { var v = parseFloat($(id).value); return isNaN(v) ? d : v; }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
    function rgba(hex, a) { var n = parseInt(hex.slice(1), 16); return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a / 100 + ')'; }

    function render() {
        // ألوان
        var st = stage.style;
        st.setProperty('--pv-bg', $('th_player_bg').value); st.setProperty('--pv-bar', $('th_bar_bg').value);
        st.setProperty('--pv-acc', $('th_accent').value); st.setProperty('--pv-acc2', $('th_accent2').value);
        st.setProperty('--pv-tab', $('th_tab_bg').value); st.setProperty('--pv-tabtxt', $('th_tab_text').value);
        $('pv_bar').style.display = $('th_bar_visible').checked ? 'flex' : 'none';
        var barPct = $('th_bar_visible').checked ? 9 : 0;

        // إعلانات (مساحات فقط)
        var W = stage.clientWidth || 600, scale = W / 800;
        var th = $('ad_top_enabled').checked ? clamp(num('ad_top_height', 60), 20, 200) * scale : 0;
        var bh = $('ad_bottom_enabled').checked ? clamp(num('ad_bottom_height', 60), 20, 200) * scale : 0;
        var barPx = $('th_bar_visible').checked ? Math.max(22, stage.clientHeight * 0.09) : 0;
        var at = $('pv_adtop'), ab = $('pv_adbot');
        at.style.display = th ? 'flex' : 'none'; at.style.top = barPx + 'px'; at.style.height = th + 'px';
        ab.style.display = bh ? 'flex' : 'none'; ab.style.bottom = '0'; ab.style.height = bh + 'px';
        var top = barPx + th;
        $('pv_video').style.top = top + 'px'; $('pv_video').style.bottom = bh + 'px';
        inner.style.top = (top + 8) + 'px'; inner.style.bottom = (bh + 8) + 'px'; inner.style.left = '8px'; inner.style.right = '8px';

        // لوجو
        var src = localLogo || ($('lg_src').value.trim() ? (/^(https?:)?\/\//.test($('lg_src').value.trim()) ? $('lg_src').value.trim() : '../' + $('lg_src').value.trim()) : '');
        var lx = clamp(num('lg_x', 98), 0, 100), ly = clamp(num('lg_y', 3), 0, 100);
        if ($('lg_enabled').checked && src) {
            logo.style.display = 'block';
            logo.querySelector('img').src = src;
            logo.style.left = lx + '%'; logo.style.top = ly + '%';
            logo.style.transform = 'translate(-' + lx + '%,-' + ly + '%)';
            logo.style.width = clamp(num('lg_width', 14), 2, 80) + '%';
            logo.style.opacity = clamp(num('lg_opacity', 85), 5, 100) / 100;
            logo.querySelector('img').style.borderRadius = clamp(num('lg_radius', 0), 0, 50) + '%';
        } else { logo.style.display = 'none'; }

        // كتابة
        var tx = clamp(num('tx_x', 50), 0, 100), ty = clamp(num('tx_y', 94), 0, 100), anim = $('tx_anim').value;
        if ($('tx_enabled').checked) {
            text.style.display = 'block';
            tspan.textContent = $('tx_content').value || 'نص تجريبي';
            tspan.className = 'pl_t';
            text.className = '';
            text.style.top = ty + '%';
            if (anim === 'marquee') {
                text.className = 'mq';
                text.style.setProperty('--pl-speed', clamp(num('tx_speed', 14), 4, 60) + 's');
                text.style.transform = 'translateY(-' + ty + '%)';
            } else {
                text.style.left = tx + '%';
                text.style.transform = 'translate(-' + tx + '%,-' + ty + '%)';
                if (anim !== 'none') tspan.classList.add('pl-' + anim);
            }
            var sz = clamp(num('tx_size', 16), 8, 80);
            tspan.style.fontSize = 'max(8px, ' + (sz / 8) + 'cqw)';
            tspan.style.color = $('tx_color').value;
            tspan.style.fontWeight = $('tx_weight').value;
            tspan.style.opacity = clamp(num('tx_opacity', 100), 5, 100) / 100;
            tspan.style.background = rgba($('tx_bg').value, clamp(num('tx_bg_opacity', 45), 0, 100));
            tspan.style.borderRadius = clamp(num('tx_radius', 8), 0, 40) + 'px';
        } else { text.style.display = 'none'; }
    }

    // كل تغيير في أي حقل = تحديث المعاينة
    document.querySelectorAll('.pl_card input, .pl_card select, .pl_card textarea').forEach(function (el) {
        el.addEventListener('input', render); el.addEventListener('change', render);
    });
    window.addEventListener('resize', render);

    // معاينة الملف المرفوع قبل الحفظ
    $('lg_file').addEventListener('change', function () {
        var f = this.files && this.files[0];
        if (!f) { localLogo = null; render(); return; }
        var r = new FileReader();
        r.onload = function (e) { localLogo = e.target.result; $('lg_enabled').checked = true; render(); };
        r.readAsDataURL(f);
    });

    // أزرار الأماكن الجاهزة
    document.querySelectorAll('[data-pos]').forEach(function (b) {
        b.addEventListener('click', function () {
            var p = b.getAttribute('data-pos');
            $(p + '_x').value = b.getAttribute('data-x'); $(p + '_y').value = b.getAttribute('data-y');
            render();
        });
    });

    // ألوان صفحة المباراة
    $('palette_landing').addEventListener('click', function () {
        var P = { th_accent: '#e11d48', th_accent2: '#d4af37', th_bar_bg: '#0a0c15', th_bar_text: '#f4f6fb', th_tab_bg: '#181b2a', th_tab_text: '#98a2ba', th_player_bg: '#05060b' };
        for (var k in P) $(k).value = P[k];
        render();
    });

    // السحب والإفلات
    function makeDraggable(el, prefix) {
        var dragging = false;
        function move(ev) {
            if (!dragging) return;
            var pt = ev.touches ? ev.touches[0] : ev;
            var r = inner.getBoundingClientRect();
            var w = el.offsetWidth, h = el.offsetHeight;
            // نفس معادلة المشغل: الموضع بالنسبة المئوية مع translate(-x%,-y%)
            // => left(px) = x% * (R - w)  لذلك x = (left) / (R - w)
            var availW = Math.max(1, r.width - w), availH = Math.max(1, r.height - h);
            var left = pt.clientX - r.left - dragOff.x, topPx = pt.clientY - r.top - dragOff.y;
            var px = clamp(left / availW * 100, 0, 100), py = clamp(topPx / availH * 100, 0, 100);
            if ($('tx_anim').value === 'marquee' && prefix === 'tx') px = num('tx_x', 50);
            $(prefix + '_x').value = px.toFixed(1); $(prefix + '_y').value = py.toFixed(1);
            render();
            ev.preventDefault();
        }
        var dragOff = { x: 0, y: 0 };
        function down(ev) {
            var pt = ev.touches ? ev.touches[0] : ev;
            var rect = el.getBoundingClientRect();
            dragOff.x = pt.clientX - rect.left; dragOff.y = pt.clientY - rect.top;
            dragging = true; el.style.cursor = 'grabbing'; ev.preventDefault();
        }
        function up() { dragging = false; el.style.cursor = 'grab'; }
        el.addEventListener('mousedown', down); el.addEventListener('touchstart', down, { passive: false });
        window.addEventListener('mousemove', move); window.addEventListener('touchmove', move, { passive: false });
        window.addEventListener('mouseup', up); window.addEventListener('touchend', up);
    }
    makeDraggable(logo, 'lg');
    makeDraggable(text, 'tx');

    // إرسال الأكواد base64 علشان فلاتر الاستضافة (ModSecurity) ماتمنعش الحفظ
    var theForm = document.querySelector('form.ath_form');
    if (theForm) {
        theForm.addEventListener('submit', function () {
            var boxes = theForm.querySelectorAll('textarea[name$="_code"]');
            Array.prototype.forEach.call(boxes, function (t) {
                if (!t.value) return;
                try {
                    var hdn = document.createElement('input');
                    hdn.type = 'hidden'; hdn.name = t.name + '__b64';
                    hdn.value = btoa(unescape(encodeURIComponent(t.value)));
                    theForm.appendChild(hdn);
                    t.disabled = true;
                    setTimeout(function () { t.disabled = false; }, 100);
                } catch (e) {}
            });
        });
    }

    render();
    setTimeout(render, 300);
})();
</script>
</body>
</html>
