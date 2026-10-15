<?php
include 'action.php';
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
if (!isset($_GET['id']) || !isset($foundData) || !is_array($foundData)) {
    $_SESSION['error_message'] = "الصفحة المطلوبة غير موجودة أو الرابط غير صحيح.";
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تعديل البث</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* التنسيقات الخاصة بك كما هي تماماً */
        .servers-section {
            background: var(--bg-secondary, #1a1a1a);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color, rgba(255,255,255,0.1));
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.1));
        }
        .section-header i {
            font-size: 18px;
            color: var(--accent, #dc2626);
        }
        .section-header h3 {
            margin: 0;
            font-size: 16px;
            color: var(--text-primary, #fff);
        }
        .section-header small {
            font-size: 11px;
            color: var(--text-secondary, #888);
        }
        .servers-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .server-item {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            background: var(--bg-tertiary, rgba(255,255,255,0.05));
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color, rgba(255,255,255,0.08));
        }
        .server-item input, .server-item select {
            background: var(--input-bg, rgba(255,255,255,0.08));
            border: 1px solid var(--border-color, rgba(255,255,255,0.15));
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--text-primary, #fff);
            font-size: 13px;
        }
        .server-item input:focus, .server-item select:focus {
            outline: none;
            border-color: var(--accent, #dc2626);
        }
        .server-name-input {
            min-width: 150px;
            width: 150px;
        }
        .server-url-input {
            flex: 2;
            min-width: 200px;
        }
        .server-type-select, .server-device-select {
            min-width: 90px;
        }
        /* تنسيق المشغل الجديد */
        .server-player-select {
            min-width: 100px;
            border-color: #8b5cf6 !important; /* تمييز خيار المشغل بالبنفسجي */
        }
        .proxy-checkbox {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-tertiary, rgba(255,255,255,0.03));
            padding: 5px 10px;
            border-radius: 8px;
        }
        .proxy-checkbox input {
            width: 16px;
            height: 16px;
            margin: 0;
            cursor: pointer;
        }
        .proxy-checkbox label {
            font-size: 12px;
            cursor: pointer;
            color: var(--text-secondary, #aaa);
        }
        .action-btns {
            display: flex;
            gap: 8px;
        }
        .add-server-btn, .remove-server-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .add-server-btn {
            background: #22c55e;
            color: white;
        }
        .add-server-btn:hover {
            background: #16a34a;
        }
        .remove-server-btn {
            background: #dc2626;
            color: white;
        }
        .remove-server-btn:hover {
            background: #b91c1c;
        }
        .info-note {
            background: var(--bg-secondary, #1a1a1a);
            padding: 12px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-secondary, #888);
            text-align: center;
        }
        .submit-btn {
            background: #8b5cf6; /* تغيير لون زر الحفظ للبنفسجي ليتماشى مع التعديلات */
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            width: 100%;
        }
        .submit-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>

<div class="form_container">
    <div class="table_head">
        <h2><i class="fa-solid fa-pen"></i> تعديل البث</h2>
        <div class="table_tools">
            <button type="button" class="theme_toggle" data-theme-toggle><i class="fa-solid fa-moon"></i></button>
            <a href="../index.php" class="logout_btn"><i class="fa-solid fa-ban"></i> الغاء</a>
        </div>
    </div>

    <form action="" method="post" id="mainForm">
        <div class="form-card">
            <label for="title" style="display: block; margin-bottom: 8px; font-weight: bold;">عنوان البث</label>
            <input type="text" name="title" id="title" class="c_input" placeholder="مثال: مباراة اليوم" style="width: 100%;" value="<?= htmlspecialchars($foundData['title']); ?>" required>
        </div>

        <!-- روابط الموبايل -->
        <div class="servers-section">
            <div class="section-header">
                <i class="fa-solid fa-mobile-screen-button"></i>
                <h3>روابط الموبايل <small>(تظهر للموبايل والتابلت فقط)</small></h3>
            </div>
                        <button type="button" class="add-server-btn" style="width:auto;padding:0 12px;margin:6px 0" onclick="plAllEngines('mobile')">⚡ كل المشغلات لنفس الرابط</button>
            <div id="mobile-servers" class="servers-list">
                <?php
                $mobileItems = [];
                $desktopItems = [];
                $m_counter = 0;
                $d_counter = 0;

                foreach ($foundData as $key => $value) {
                    if (is_numeric($key) && is_array($value) && isset($value['Stream_Url'])) {
                        $device = $value['device'] ?? 'legacy';
                        $item = [
                            'name' => $value['name'] ?? 'سيرفر',
                            'url' => $value['Stream_Url'],
                            'type' => $value['Link_type'] ?? 'embed',
                            'player' => $value['player'] ?? 'videojs',
                            'device' => $device,
                            'use_proxy' => isset($value['use_proxy']) && $value['use_proxy']
                        ];
                        if ($device == 'desktop') {
                            $d_counter++;
                            $item['id'] = $d_counter;
                            $desktopItems[] = $item;
                        } else {
                            $m_counter++;
                            $item['id'] = $m_counter;
                            $mobileItems[] = $item;
                        }
                    }
                }

                if (empty($mobileItems)) {
                    $mobileItems[] = ['id' => 1, 'name' => 'البث الرئيسي', 'url' => '', 'type' => 'embed', 'player' => 'videojs', 'device' => 'mobile', 'use_proxy' => false];
                }

                foreach ($mobileItems as $index => $item):
                ?>
                <div class="server-item">
                    <input type="text" name="mobile_server_name_<?= $item['id']; ?>" class="server-name-input" placeholder="اسم السيرفر" value="<?= htmlspecialchars($item['name']); ?>">
                    <input type="url" name="mobile_stream_url_<?= $item['id']; ?>" class="server-url-input" placeholder="رابط الموبايل" value="<?= htmlspecialchars($item['url']); ?>" required>
                    
                    <!-- إضافة اختيار المشغل -->
                    <select name="mobile_player_<?= $item['id']; ?>" class="server-player-select">
                        <option value="videojs" <?= ($item['player'] == 'videojs') ? 'selected' : ''; ?>>Video.js</option>
                        <option value="clappr" <?= ($item['player'] == 'clappr') ? 'selected' : ''; ?>>Clappr</option>
                        <option value="plyr" <?= ($item['player'] == 'plyr') ? 'selected' : ''; ?>>Plyr</option>
                        <option value="dplayer" <?= ($item['player'] == 'dplayer') ? 'selected' : ''; ?>>DPlayer</option>
                        <option value="hlsjs" <?= ($item['player'] == 'hlsjs') ? 'selected' : ''; ?>>HLS.js</option>
                        <option value="native" <?= ($item['player'] == 'native') ? 'selected' : ''; ?>>جوال (Native)</option>
                        <option value="iframe" <?= ($item['player'] == 'iframe') ? 'selected' : ''; ?>>Iframe</option>
                    </select>

                    <select name="mobile_link_type_<?= $item['id']; ?>" class="server-type-select">
                        <option value="embed" <?= ($item['type'] == 'embed') ? 'selected' : ''; ?>>Embed</option>
                        <option value="source" <?= ($item['type'] == 'source') ? 'selected' : ''; ?>>Source</option>
                    </select>

                    <select name="mobile_device_<?= $item['id']; ?>" class="server-device-select">
                        <option value="mobile" <?= ($item['device'] == 'mobile') ? 'selected' : ''; ?>>موبايل</option>
                        <option value="desktop" <?= ($item['device'] == 'desktop') ? 'selected' : ''; ?>>كمبيوتر</option>
                        <option value="legacy" <?= ($item['device'] == 'legacy') ? 'selected' : ''; ?>>عام</option>
                    </select>

                    <label class="proxy-checkbox">
                        <input type="checkbox" name="mobile_use_proxy_<?= $item['id']; ?>" <?= ($item['use_proxy']) ? 'checked' : ''; ?>> <span>بروكسي</span>
                    </label>

                    <div class="action-btns">
                        <?php if ($index == count($mobileItems) - 1): ?>
                        <button type="button" class="add-server-btn" onclick="addMobileServer()">+</button>
                        <?php endif; ?>
                        <?php if (count($mobileItems) > 1): ?>
                        <button type="button" class="remove-server-btn" onclick="removeMobileServer(this)">-</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- روابط الكمبيوتر -->
        <div class="servers-section">
            <div class="section-header">
                <i class="fa-solid fa-desktop"></i>
                <h3>روابط الكمبيوتر <small>(تظهر للكمبيوتر واللاب توب فقط)</small></h3>
            </div>
                        <button type="button" class="add-server-btn" style="width:auto;padding:0 12px;margin:6px 0" onclick="plAllEngines('desktop')">⚡ كل المشغلات لنفس الرابط</button>
            <div id="desktop-servers" class="servers-list">
                <?php
                if (empty($desktopItems)) {
                    $desktopItems[] = ['id' => 1, 'name' => 'البث الرئيسي', 'url' => '', 'type' => 'source', 'player' => 'videojs', 'device' => 'desktop', 'use_proxy' => false];
                }
                foreach ($desktopItems as $index => $item):
                ?>
                <div class="server-item">
                    <input type="text" name="desktop_server_name_<?= $item['id']; ?>" class="server-name-input" placeholder="اسم السيرفر" value="<?= htmlspecialchars($item['name']); ?>">
                    <input type="url" name="desktop_stream_url_<?= $item['id']; ?>" class="server-url-input" placeholder="رابط الكمبيوتر" value="<?= htmlspecialchars($item['url']); ?>">
                    
                    <!-- إضافة اختيار المشغل -->
                    <select name="desktop_player_<?= $item['id']; ?>" class="server-player-select">
                        <option value="videojs" <?= ($item['player'] == 'videojs') ? 'selected' : ''; ?>>Video.js</option>
                        <option value="clappr" <?= ($item['player'] == 'clappr') ? 'selected' : ''; ?>>Clappr</option>
                        <option value="plyr" <?= ($item['player'] == 'plyr') ? 'selected' : ''; ?>>Plyr</option>
                        <option value="dplayer" <?= ($item['player'] == 'dplayer') ? 'selected' : ''; ?>>DPlayer</option>
                        <option value="hlsjs" <?= ($item['player'] == 'hlsjs') ? 'selected' : ''; ?>>HLS.js</option>
                        <option value="native" <?= ($item['player'] == 'native') ? 'selected' : ''; ?>>جوال (Native)</option>
                        <option value="iframe" <?= ($item['player'] == 'iframe') ? 'selected' : ''; ?>>Iframe</option>
                    </select>

                    <select name="desktop_link_type_<?= $item['id']; ?>" class="server-type-select">
                        <option value="source" <?= ($item['type'] == 'source') ? 'selected' : ''; ?>>Source</option>
                        <option value="embed" <?= ($item['type'] == 'embed') ? 'selected' : ''; ?>>Embed</option>
                    </select>

                    <select name="desktop_device_<?= $item['id']; ?>" class="server-device-select">
                        <option value="desktop" <?= ($item['device'] == 'desktop') ? 'selected' : ''; ?>>كمبيوتر</option>
                        <option value="mobile" <?= ($item['device'] == 'mobile') ? 'selected' : ''; ?>>موبايل</option>
                        <option value="legacy" <?= ($item['device'] == 'legacy') ? 'selected' : ''; ?>>عام</option>
                    </select>

                    <label class="proxy-checkbox">
                        <input type="checkbox" name="desktop_use_proxy_<?= $item['id']; ?>" <?= ($item['use_proxy']) ? 'checked' : ''; ?>> <span>بروكسي</span>
                    </label>

                    <div class="action-btns">
                        <?php if ($index == count($desktopItems) - 1): ?>
                        <button type="button" class="add-server-btn" onclick="addDesktopServer()">+</button>
                        <?php endif; ?>
                        <?php if (count($desktopItems) > 1): ?>
                        <button type="button" class="remove-server-btn" onclick="removeDesktopServer(this)">-</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" name="update" class="submit-btn">حفظ التعديلات</button>
    </form>

    <div class="info-note">
        نصيحة: روابط الموبايل استخدم Embed + بروكسي | روابط الكمبيوتر استخدم Source (m3u8) بدون بروكسي
    </div>
</div>

<script>
    let mobileCount = <?= count($mobileItems); ?>;
    let desktopCount = <?= count($desktopItems); ?>;

    function addMobileServer() {
        mobileCount++;
        const container = document.getElementById('mobile-servers');
        const newId = mobileCount;
        
        const newItem = document.createElement('div');
        newItem.className = 'server-item';
        newItem.innerHTML = `
            <input type="text" name="mobile_server_name_${newId}" class="server-name-input" placeholder="اسم السيرفر" value="سيرفر ${newId}">
            <input type="url" name="mobile_stream_url_${newId}" class="server-url-input" placeholder="رابط الموبايل" required>
            <select name="mobile_player_${newId}" class="server-player-select">
                <option value="videojs" selected>Video.js</option>
                <option value="clappr">Clappr</option>
                <option value="plyr">Plyr</option>
                <option value="dplayer">DPlayer</option>
                <option value="hlsjs">HLS.js</option>
                <option value="native">جوال (Native)</option>
                <option value="iframe">Iframe</option>
            </select>
            <select name="mobile_link_type_${newId}" class="server-type-select">
                <option value="embed" selected>Embed</option>
                <option value="source">Source</option>
            </select>
            <select name="mobile_device_${newId}" class="server-device-select">
                <option value="mobile" selected>موبايل</option>
                <option value="desktop">كمبيوتر</option>
                <option value="legacy">عام</option>
            </select>
            <label class="proxy-checkbox">
                <input type="checkbox" name="mobile_use_proxy_${newId}"> <span>بروكسي</span>
            </label>
            <div class="action-btns">
                <button type="button" class="add-server-btn" onclick="addMobileServer()">+</button>
                <button type="button" class="remove-server-btn" onclick="removeMobileServer(this)">-</button>
            </div>
        `;
        
        const prevAddBtn = container.querySelector('.server-item:last-child .add-server-btn');
        if (prevAddBtn) prevAddBtn.remove();
        container.appendChild(newItem);
    }

    // يكرر رابط أول سيرفر على كل المشغلات (Clappr / Video.js / Plyr / DPlayer / HLS.js / جوال)
    function plAllEngines(kind) {
        var cont = document.getElementById(kind + '-servers');
        var first = cont.querySelector('.server-item');
        var url = first.querySelector('.server-url-input').value.trim();
        if (!url) { alert('اكتب رابط أول سيرفر الأول'); return; }
        var baseName = first.querySelector('.server-name-input').value.trim() || 'البث';
        var typeV = first.querySelector('.server-type-select').value;
        var devV = first.querySelector('.server-device-select').value;
        var proxyV = first.querySelector('input[type=checkbox]').checked;
        var used = {};
        cont.querySelectorAll('.server-item').forEach(function (it) {
            if (it.querySelector('.server-url-input').value.trim() === url) used[it.querySelector('.server-player-select').value] = true;
        });
        var engines = [['clappr', 'Clappr'], ['videojs', 'Video.js'], ['plyr', 'Plyr'], ['dplayer', 'DPlayer'], ['hlsjs', 'HLS.js'], ['native', 'جوال']];
        engines.forEach(function (e) {
            if (used[e[0]]) return;
            (kind === 'mobile' ? addMobileServer : addDesktopServer)();
            var items = cont.querySelectorAll('.server-item');
            var it = items[items.length - 1];
            it.querySelector('.server-name-input').value = baseName + ' - ' + e[1];
            it.querySelector('.server-url-input').value = url;
            it.querySelector('.server-player-select').value = e[0];
            it.querySelector('.server-type-select').value = typeV;
            it.querySelector('.server-device-select').value = devV;
            it.querySelector('input[type=checkbox]').checked = proxyV;
        });
    }

    function removeMobileServer(btn) {
        const container = document.getElementById('mobile-servers');
        if (container.querySelectorAll('.server-item').length <= 1) return;
        btn.closest('.server-item').remove();
        // إعادة الترتيب اختيارية هنا للحفاظ على منطق الـ ID
    }

    function addDesktopServer() {
        desktopCount++;
        const container = document.getElementById('desktop-servers');
        const newId = desktopCount;
        
        const newItem = document.createElement('div');
        newItem.className = 'server-item';
        newItem.innerHTML = `
            <input type="text" name="desktop_server_name_${newId}" class="server-name-input" placeholder="اسم السيرفر" value="سيرفر ${newId}">
            <input type="url" name="desktop_stream_url_${newId}" class="server-url-input" placeholder="رابط الكمبيوتر">
            <select name="desktop_player_${newId}" class="server-player-select">
                <option value="videojs" selected>Video.js</option>
                <option value="clappr">Clappr</option>
                <option value="plyr">Plyr</option>
                <option value="dplayer">DPlayer</option>
                <option value="hlsjs">HLS.js</option>
                <option value="native">جوال (Native)</option>
                <option value="iframe">Iframe</option>
            </select>
            <select name="desktop_link_type_${newId}" class="server-type-select">
                <option value="source" selected>Source</option>
                <option value="embed">Embed</option>
            </select>
            <select name="desktop_device_${newId}" class="server-device-select">
                <option value="desktop" selected>كمبيوتر</option>
                <option value="mobile">موبايل</option>
                <option value="legacy">عام</option>
            </select>
            <label class="proxy-checkbox">
                <input type="checkbox" name="desktop_use_proxy_${newId}"> <span>بروكسي</span>
            </label>
            <div class="action-btns">
                <button type="button" class="add-server-btn" onclick="addDesktopServer()">+</button>
                <button type="button" class="remove-server-btn" onclick="removeDesktopServer(this)">-</button>
            </div>
        `;
        
        const prevAddBtn = container.querySelector('.server-item:last-child .add-server-btn');
        if (prevAddBtn) prevAddBtn.remove();
        container.appendChild(newItem);
    }

    function removeDesktopServer(btn) {
        const container = document.getElementById('desktop-servers');
        if (container.querySelectorAll('.server-item').length <= 1) return;
        btn.closest('.server-item').remove();
    }
</script>
<script src="../js/panel-theme.js"></script>
</body>
</html>