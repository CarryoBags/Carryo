<?php
header("Access-Control-Allow-Origin: *");
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
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إضافة بث جديد</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
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
            min-width: 180px;
            width: 180px;
        }
        .server-url-input {
            flex: 2;
            min-width: 220px;
        }
        .server-type-select, .server-device-select {
            min-width: 95px;
        }
        /* إضافة ستايل المشغل للحفاظ على التنسيق */
        .server-player-select {
            min-width: 95px;
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
            background: var(--accent, #dc2626);
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
        <h2><i class="fa-solid fa-plus"></i> اضافة بث جديد</h2>
        <div class="table_tools">
            <button type="button" class="theme_toggle" data-theme-toggle><i class="fa-solid fa-moon"></i></button>
            <a href="../index.php" class="logout_btn"><i class="fa-solid fa-ban"></i> الغاء</a>
        </div>
    </div>

    <form action="" method="post" id="mainForm">
        <div class="form-card">
            <label for="title" style="display: block; margin-bottom: 8px; font-weight: bold;">عنوان البث</label>
            <input type="text" name="title" id="title" class="c_input" placeholder="مثال: مباراة اليوم" style="width: 100%;" required>
        </div>

        <!-- روابط الموبايل -->
        <div class="servers-section">
            <div class="section-header">
                <i class="fa-solid fa-mobile-screen-button"></i>
                <h3>روابط الموبايل <small>(تظهر للموبايل والتابلت فقط)</small></h3>
            </div>
                        <button type="button" class="add-server-btn" style="width:auto;padding:0 12px;margin:6px 0" onclick="plAllEngines('mobile')">⚡ كل المشغلات لنفس الرابط</button>
            <div id="mobile-servers" class="servers-list">
                <div class="server-item">
                    <input type="text" name="mobile_server_name_1" class="server-name-input" placeholder="اسم السيرفر" value="البث الرئيسي">
                    <input type="url" name="mobile_stream_url_1" class="server-url-input" placeholder="رابط الموبايل (مثال: https://example.com)" required>
                    
                    <!-- إضافة المشغل هنا -->
                    <select name="mobile_player_1" class="server-player-select">
                        <option value="videojs">Video.js</option>
                        <option value="clappr">Clappr</option>
                        <option value="plyr">Plyr</option>
                        <option value="dplayer">DPlayer</option>
                        <option value="hlsjs">HLS.js</option>
                        <option value="native">جوال (Native)</option>
                        <option value="iframe">Iframe</option>
                    </select>

                    <select name="mobile_link_type_1" class="server-type-select">
                        <option value="embed" selected>Embed</option>
                        <option value="source">Source</option>
                    </select>
                    <select name="mobile_device_1" class="server-device-select">
                        <option value="mobile" selected>موبايل</option>
                        <option value="desktop">كمبيوتر</option>
                        <option value="legacy">عام</option>
                    </select>
                    <label class="proxy-checkbox">
                        <input type="checkbox" name="mobile_use_proxy_1" checked> <span>بروكسي</span>
                    </label>
                    <div class="action-btns">
                        <button type="button" class="add-server-btn" onclick="addMobileServer()">+</button>
                    </div>
                </div>
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
                <div class="server-item">
                    <input type="text" name="desktop_server_name_1" class="server-name-input" placeholder="اسم السيرفر" value="البث الرئيسي">
                    <input type="url" name="desktop_stream_url_1" class="server-url-input" placeholder="رابط الكمبيوتر (مثال: https://example.com/index.m3u8)">
                    
                    <!-- إضافة المشغل هنا -->
                    <select name="desktop_player_1" class="server-player-select">
                        <option value="videojs">Video.js</option>
                        <option value="clappr">Clappr</option>
                        <option value="plyr">Plyr</option>
                        <option value="dplayer">DPlayer</option>
                        <option value="hlsjs">HLS.js</option>
                        <option value="native">جوال (Native)</option>
                        <option value="iframe">Iframe</option>
                    </select>

                    <select name="desktop_link_type_1" class="server-type-select">
                        <option value="source" selected>Source</option>
                        <option value="embed">Embed</option>
                    </select>
                    <select name="desktop_device_1" class="server-device-select">
                        <option value="desktop" selected>كمبيوتر</option>
                        <option value="mobile">موبايل</option>
                        <option value="legacy">عام</option>
                    </select>
                    <label class="proxy-checkbox">
                        <input type="checkbox" name="desktop_use_proxy_1"> <span>بروكسي</span>
                    </label>
                    <div class="action-btns">
                        <button type="button" class="add-server-btn" onclick="addDesktopServer()">+</button>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" name="submit" class="submit-btn">حفظ البث</button>
    </form>

    <div class="info-note">
        نصيحة: روابط الموبايل استخدم Embed + بروكسي | روابط الكمبيوتر استخدم Source (m3u8) بدون بروكسي
    </div>
</div>

<script>
    let mobileCount = 1;
    let desktopCount = 1;

    function addMobileServer() {
        mobileCount++;
        const container = document.getElementById('mobile-servers');
        const newId = mobileCount;
        
        const newItem = document.createElement('div');
        newItem.className = 'server-item';
        newItem.innerHTML = `
            <input type="text" name="mobile_server_name_${newId}" class="server-name-input" placeholder="اسم السيرفر" value="سيرفر ${newId}">
            <input type="url" name="mobile_stream_url_${newId}" class="server-url-input" placeholder="رابط الموبايل (مثال: https://example.com)" required>
            <select name="mobile_player_${newId}" class="server-player-select">
                <option value="videojs">Video.js</option>
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
                <input type="checkbox" name="mobile_use_proxy_${newId}" checked> <span>بروكسي</span>
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
        const items = container.querySelectorAll('.server-item');
        
        if (items.length <= 1) {
            alert('لا يمكن حذف السيرفر الوحيد، يجب أن يبقى سيرفر واحد على الأقل');
            return;
        }
        
        const row = btn.closest('.server-item');
        row.remove();
        
        const remainingItems = container.querySelectorAll('.server-item');
        mobileCount = remainingItems.length;
        
        remainingItems.forEach((item, idx) => {
            const newId = idx + 1;
            const inputs = item.querySelectorAll('input, select');
            inputs.forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/_\d+$/, `_${newId}`));
                }
            });
        });
        
        const lastItem = container.querySelector('.server-item:last-child');
        const lastAddBtn = lastItem.querySelector('.add-server-btn');
        if (!lastAddBtn) {
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'add-server-btn';
            addBtn.onclick = addMobileServer;
            addBtn.innerHTML = '+';
            lastItem.querySelector('.action-btns').appendChild(addBtn);
        }
    }

    function addDesktopServer() {
        desktopCount++;
        const container = document.getElementById('desktop-servers');
        const newId = desktopCount;
        
        const newItem = document.createElement('div');
        newItem.className = 'server-item';
        newItem.innerHTML = `
            <input type="text" name="desktop_server_name_${newId}" class="server-name-input" placeholder="اسم السيرفر" value="سيرفر ${newId}">
            <input type="url" name="desktop_stream_url_${newId}" class="server-url-input" placeholder="رابط الكمبيوتر (مثال: https://example.com/index.m3u8)">
            <select name="desktop_player_${newId}" class="server-player-select">
                <option value="videojs">Video.js</option>
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
        const items = container.querySelectorAll('.server-item');
        
        if (items.length <= 1) {
            alert('لا يمكن حذف السيرفر الوحيد، يجب أن يبقى سيرفر واحد على الأقل');
            return;
        }
        
        const row = btn.closest('.server-item');
        row.remove();
        
        const remainingItems = container.querySelectorAll('.server-item');
        desktopCount = remainingItems.length;
        
        remainingItems.forEach((item, idx) => {
            const newId = idx + 1;
            const inputs = item.querySelectorAll('input, select');
            inputs.forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/_\d+$/, `_${newId}`));
                }
            });
        });
        
        const lastItem = container.querySelector('.server-item:last-child');
        const lastAddBtn = lastItem.querySelector('.add-server-btn');
        if (!lastAddBtn) {
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'add-server-btn';
            addBtn.onclick = addDesktopServer;
            addBtn.innerHTML = '+';
            lastItem.querySelector('.action-btns').appendChild(addBtn);
        }
    }
</script>
<script src="../js/panel-theme.js"></script>

</body>
</html>