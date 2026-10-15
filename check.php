<?php
// صفحة فحص: بتقولك هل الملفات الجديدة موجودة في المكان الصح (احذفها بعد ما تخلص)
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

$opc = 'غير متاح';
if (function_exists('opcache_reset')) { @opcache_reset(); $opc = 'تم مسح كاش PHP تلقائياً'; }

$checks = [
    ['embed.php',                  'plStartEngine'],
    ['live.php',                   'PopEngine'],
    ['click.php',                  'clicks.json'],
    ['player_defaults.php',        'plSaveJson'],
    ['insert/player_settings.php', 'po_shield_mode'],
    ['js/pop-engine.js',           'popdebug'],
];
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>فحص الملفات</title>
<style>body{font-family:Tahoma,sans-serif;background:#0a0c15;color:#f4f6fb;padding:20px}table{border-collapse:collapse;width:100%;max-width:760px}td,th{border:1px solid #333;padding:8px;text-align:right}.ok{color:#22c55e}.no{color:#e11d48}code{background:#181b2a;padding:2px 6px;border-radius:4px}</style></head><body>
<h2>فحص ملفات المشغل</h2>
<p>مجلد الصفحة دي: <code><?php echo h(__DIR__); ?></code></p>
<p>كاش PHP: <?php echo h($opc); ?></p>
<table>
<tr><th>الملف</th><th>موجود؟</th><th>النسخة الجديدة؟</th><th>آخر تعديل</th></tr>
<?php foreach ($checks as $c) {
    $f = __DIR__ . '/' . $c[0];
    $exists = is_file($f);
    $new = $exists && strpos((string)file_get_contents($f), $c[1]) !== false;
    echo '<tr><td><code>' . h($c[0]) . '</code></td>'
       . '<td class="' . ($exists ? 'ok' : 'no') . '">' . ($exists ? 'نعم' : 'لا') . '</td>'
       . '<td class="' . ($new ? 'ok' : 'no') . '">' . ($new ? 'جديدة ✅' : 'قديمة أو ناقصة ❌') . '</td>'
       . '<td>' . ($exists ? h(date('Y-m-d H:i:s', filemtime($f))) : '-') . '</td></tr>';
} ?>
</table>
<?php if (is_file(__DIR__ . '/cgi-bin/embed.php') || is_dir(__DIR__ . '/cgi-bin/insert')) { ?>
<p class="no"><b>تنبيه:</b> لقيت ملفات جوه مجلد <code>cgi-bin/cgi-bin</code>. معناها إنك رفعت المجلد نفسه بدل محتواه. انقل اللي جواه لمكان index.php وامسح المجلد الزيادة.</p>
<?php } ?>
<p>صلاحية الكتابة على <code>data</code>: <b class="<?php echo is_writable(__DIR__ . '/data') ? 'ok' : 'no'; ?>"><?php echo is_writable(__DIR__ . '/data') ? 'تمام' : 'مقفولة ❌'; ?></b></p>
<p style="opacity:.7">بعد ما كل حاجة تبقى ✅ امسح check.php من السيرفر.</p>
</body></html>
