<?php
// إعدادات المشغل الافتراضية + دالة التحميل (بتستخدمها embed.php وصفحة التحكم)
function player_defaults() {
    return [
        'theme' => [
            'accent' => '#e11d48', 'accent2' => '#d4af37', 'bar_bg' => '#0a0c15', 'bar_text' => '#f4f6fb',
            'tab_bg' => '#181b2a', 'tab_text' => '#98a2ba', 'player_bg' => '#05060b', 'bar_visible' => 1,
            'bar_position' => 'top', 'btn_size' => 'm', 'btn_radius' => 6,
            'show_embed' => 1, 'show_share' => 1, 'show_fullscreen' => 1, 'show_refresh' => 1
        ],
        'logo' => ['enabled' => 0, 'src' => '', 'x' => 98, 'y' => 3, 'width' => 14, 'opacity' => 85, 'radius' => 0, 'link' => ''],
        'text' => [
            'enabled' => 0, 'content' => '', 'x' => 50, 'y' => 94, 'size' => 16, 'color' => '#ffffff',
            'bg' => '#000000', 'bg_opacity' => 45, 'weight' => '700', 'opacity' => 100,
            'anim' => 'none', 'speed' => 14, 'radius' => 8
        ],
        'ads' => [
            'top'     => ['enabled' => 0, 'code' => '', 'height' => 60],
            'bottom'  => ['enabled' => 0, 'code' => '', 'height' => 60, 'closable' => 1, 'close_after' => 0],
            'preroll' => ['enabled' => 0, 'code' => '', 'seconds' => 8, 'skip_after' => 5, 'width' => 300, 'height' => 250],
            'corner'  => ['enabled' => 0, 'code' => '', 'width' => 300, 'height' => 250, 'delay' => 10, 'side' => 'left']
        ],
        'brand' => ['site_name' => '', 'poster' => ''],
        'tracking' => ['code' => '', 'disable_builtin' => 0],
        'builtin' => ['disable_pops' => 0, 'disable_popup' => 0],
        'pops' => [
            'pop1' => ['enabled' => 0, 'code' => '', 'trigger' => 'load', 'delay' => 0, 'once' => 0, 'scope' => 'both'],
            'pop2' => ['enabled' => 0, 'code' => '', 'trigger' => 'load', 'delay' => 0, 'once' => 0, 'scope' => 'both'],
            'pop3' => ['enabled' => 0, 'code' => '', 'trigger' => 'load', 'delay' => 0, 'once' => 0, 'scope' => 'both']
        ],
        'pop_opts' => ['shield_mode' => 'cooldown', 'shield_cooldown' => 4, 'count' => 1],
        'protect' => ['shell' => 1, 'devtools' => 1]
    ];
}

function player_settings($file) {
    $d = player_defaults();
    if (is_file($file)) {
        $j = json_decode(file_get_contents($file), true);
        if (is_array($j)) $d = array_replace_recursive($d, $j);
    }
    return $d;
}

function pcolor($v, $fallback) {
    return (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? $v : $fallback;
}

// حفظ ملف JSON بشكل آمن: بيتأكد إن الكتابة نجحت فعلاً، وبيعالج مشكلة صلاحيات الملفات المرفوعة بالـ FTP
// بيرجّع true لو تم الحفظ، أو نص فيه سبب الفشل.
function plSaveJson($file, $data) {
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) { $flags |= JSON_INVALID_UTF8_SUBSTITUTE; }
    $json = json_encode($data, $flags);
    if ($json === false) {
        return 'تعذر تجهيز الإعدادات للحفظ (في رموز غير صالحة داخل أحد الأكواد).';
    }
    $ok = (@file_put_contents($file, $json, LOCK_EX) !== false);
    if (!$ok && is_file($file)) {
        @chmod($file, 0666);
        $ok = (@file_put_contents($file, $json, LOCK_EX) !== false);
    }
    if (!$ok) {
        // الملف مش قابل للكتابة: نكتب نسخة جديدة جنبه ونبدّلها (بيشتغل طالما المجلد قابل للكتابة)
        $tmp = $file . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $json) !== false) {
            if (@rename($tmp, $file)) { @chmod($file, 0664); $ok = true; } else { @unlink($tmp); }
        }
    }
    if (!$ok) {
        return 'مش قادر أكتب في الملف ' . basename($file) . '. غيّر صلاحية مجلد data إلى 755 أو 775 وصلاحية الملف إلى 664 (أو امسح الملف من مدير الملفات واحفظ تاني).';
    }
    // تأكيد إن اللي اتكتب هو اللي اتبعت
    $back = json_decode((string)@file_get_contents($file), true);
    if (!is_array($back)) {
        return 'الحفظ تم لكن الملف ' . basename($file) . ' طلع تالف. جرّب تحفظ تاني.';
    }
    return true;
}
