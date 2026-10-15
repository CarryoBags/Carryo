<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../player_defaults.php';
$settingsFile = __DIR__ . '/../data/panel_settings.json';
$saveError = '';
$defaultSettings = [
    'popup_enabled' => 0,
    'popup_delay' => 2,
    'popup_code' => '',
    'pop_scripts_enabled' => 0,
    'disable_builtin_pops' => 0,
    'pop_scripts' => []
];

$settings = $defaultSettings;
if (file_exists($settingsFile)) {
    $raw = file_get_contents($settingsFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $settings = array_merge($settings, $decoded);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $popupEnabled = isset($_POST['popup_enabled']) ? 1 : 0;
    $popupDelay = isset($_POST['popup_delay']) ? (int)$_POST['popup_delay'] : 2;
    $popupCode = isset($_POST['popup_code']) ? trim((string)$_POST['popup_code']) : '';

    if ($popupDelay < 0) {
        $popupDelay = 0;
    }
    if ($popupDelay > 30) {
        $popupDelay = 30;
    }

    $popScripts = [];
    foreach ([1, 2, 3] as $i) {
        $c = isset($_POST['pop_code_' . $i]) ? trim((string)$_POST['pop_code_' . $i]) : '';
        if ($c !== '') {
            $popScripts[] = ['code' => $c, 'trigger' => 'load', 'delay' => 0];
        }
    }

    // array_merge علشان أي إعدادات تانية في الملف ماتتمسحش
    $settings = array_merge($settings, [
        'popup_enabled' => $popupEnabled,
        'popup_delay' => $popupDelay,
        'popup_code' => $popupCode,
        'pop_scripts_enabled' => isset($_POST['pop_scripts_enabled']) ? 1 : 0,
        'disable_builtin_pops' => isset($_POST['disable_builtin_pops']) ? 1 : 0,
        'pop_scripts' => $popScripts
    ]);

    $saveRes = plSaveJson($settingsFile, $settings);
    if ($saveRes === true) {
        $_SESSION['success_message'] = "Popup ad settings saved successfully.";
        header("Location: ../index.php");
        exit();
    }
    $saveError = $saveRes;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إعداد إعلان منبثق</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="form_container">
        <?php if ($saveError !== '') { ?><div class="error_msg"><?php echo htmlspecialchars($saveError); ?></div><?php } ?>
        <div class="table_head">
            <h2>إعداد إعلان منبثق 300x300</h2>
            <div class="table_tools">
                <button type="button" class="theme_toggle" data-theme-toggle title="تفعيل الوضع الليلي"><i class="fa-solid fa-moon"></i></button>
                <a href="../index.php" class="logout_btn"><i class="fa-solid fa-arrow-right"></i> رجوع</a>
            </div>
        </div>
        <form method="post" class="ath_form">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="popup_enabled" value="1" <?php echo !empty($settings['popup_enabled']) ? 'checked' : ''; ?> style="width:auto;">
                تفعيل الإعلان المنبثق
            </label>

            <label for="popup_delay">زمن الظهور بالثواني (0 إلى 30)</label>
            <input type="number" name="popup_delay" id="popup_delay" class="c_input" min="0" max="30" value="<?php echo (int)$settings['popup_delay']; ?>">

            <label for="popup_code">كود الإعلان (حجم البانر 300x300)</label>
            <textarea name="popup_code" id="popup_code" class="c_input" rows="10" placeholder="<script>...</script>"><?php echo htmlspecialchars($settings['popup_code']); ?></textarea>
            <div class="inline_form_footer" style="margin-top:10px;">
                <button type="button" id="previewPopupAdBtn" class="btn btn_dark"><i class="fa-solid fa-eye"></i> معاينة الإعلان</button>
                <span class="muted_text">المعاينة لا تحفظ الإعدادات، فقط لعرض الكود الحالي.</span>
            </div>
            <div id="popupPreviewWrap" style="display:none;margin-top:12px;">
                <div style="width:300px;height:300px;border:1px solid #d7dff6;border-radius:10px;overflow:hidden;background:#fff;">
                    <div id="popupPreviewMount" style="width:300px;height:300px;"></div>
                </div>
            </div>

            <hr style="margin:18px 0;">
            <h3 style="margin:0 0 8px;">إعلانات بوب (سكريبتات Pop / Popunder)</h3>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="pop_scripts_enabled" value="1" <?php echo !empty($settings['pop_scripts_enabled']) ? 'checked' : ''; ?> style="width:auto;">
                تفعيل سكريبتات البوب دي في المشغل
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="disable_builtin_pops" value="1" <?php echo !empty($settings['disable_builtin_pops']) ? 'checked' : ''; ?> style="width:auto;">
                إيقاف البوب المدمج القديم جوه ملف المشغل (علشان ماتتنفذش مرتين)
            </label>
            <?php for ($i = 1; $i <= 3; $i++) {
                $v = isset($settings['pop_scripts'][$i - 1]['code']) ? $settings['pop_scripts'][$i - 1]['code'] : ''; ?>
                <label for="pop_code_<?php echo $i; ?>">كود بوب <?php echo $i; ?></label>
                <textarea name="pop_code_<?php echo $i; ?>" id="pop_code_<?php echo $i; ?>" class="c_input" rows="4" dir="ltr" placeholder="<script src=&quot;...&quot;></script>"><?php echo htmlspecialchars($v); ?></textarea>
            <?php } ?>
            <span class="muted_text">بتشتغل فوراً مع تحميل المشغل، وبتتخزن في data/panel_settings.json.</span>

            <div class="inline_form_footer">
                <span class="muted_text">سيتم عرض الكود داخل نافذة منبثقة بمقاس ثابت 300x300 في صفحة المشغل.</span>
                <button type="submit" class="submit_btn"><i class="fa-solid fa-floppy-disk"></i> حفظ الإعدادات</button>
            </div>
        </form>
    </div>
    <script src="../js/panel-theme.js"></script>
    <script>
        function normalizeAdCode(code) {
            if (!code) return '';
            var cleaned = String(code);
            cleaned = cleaned.replace(/`(https?:\/\/[^`]+)`/gi, '$1');
            cleaned = cleaned.replace(/(['"])\s*(https?:\/\/[^'"]+)\s*\1/gi, '$1$2$1');
            return cleaned.trim();
        }

        function renderPopupPreview() {
            var codeInput = document.getElementById('popup_code');
            var wrap = document.getElementById('popupPreviewWrap');
            var mount = document.getElementById('popupPreviewMount');
            if (!codeInput || !wrap || !mount) return;

            var cleanedCode = normalizeAdCode(codeInput.value);
            if (!cleanedCode) {
                wrap.style.display = 'none';
                mount.innerHTML = '';
                return;
            }

            var iframe = document.createElement('iframe');
            iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox');
            iframe.setAttribute('scrolling', 'no');
            iframe.style.cssText = 'width:300px;height:300px;border:0;display:block;background:transparent;';
            iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=300,height=300,initial-scale=1"><style>html,body{margin:0;padding:0;width:300px;height:300px;overflow:hidden;background:transparent;}*{box-sizing:border-box;}</style></head><body>' + cleanedCode + '</body></html>';

            mount.innerHTML = '';
            mount.appendChild(iframe);
            wrap.style.display = 'block';
        }

        var previewBtn = document.getElementById('previewPopupAdBtn');
        if (previewBtn) {
            previewBtn.addEventListener('click', renderPopupPreview);
        }
    </script>
</body>
</html>
