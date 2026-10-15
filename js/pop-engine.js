/*
 * PopEngine v2 - محرك إعلانات البوب (مشترك بين embed.php و live.php)
 *  1) بيحمّل أكواد البوب في الصفحة الرئيسية (مش في إطار معزول) وبدري قبل أول نقرة
 *  2) بيحسب كل نقرة حقيقية (isTrusted) ويبعتها للسيرفر على دفعات + عند قفل الصفحة
 *  3) "طبقة النقر": طبقة شفافة فوق الـ iframe علشان نقرات الـ iframe (اللي الصفحة مبتشوفهاش) ماتضيعش
 *  4) تشخيص: هل سكريبت الشبكة اتحمّل ولا اتحجب؟ وهل نافذة الإعلان اتفتحت فعلاً (تقدير)؟
 *     افتح الصفحة بـ  ?popdebug=1  علشان تشوف لوحة التشخيص مباشرة.
 */
(function (w, d) {
    'use strict';

    var E = {}, pops = [], opts = {}, scope = 'player', endpoint = 'click.php';
    var pend = { v: 0, c: 0, h: 0, l: 0, e: 0, p: 0 };           // اللي لسه ما اتبعتش
    var tot  = { v: 0, c: 0, h: 0, l: 0, e: 0, p: 0 };           // إجمالي الجلسة (للوحة التشخيص)
    var timer = null, counting = false, inited = false;
    var shield = null, shieldParent = null, shieldTimer = null;
    var lastClick = 0, popCounted = true, hadFocusAtClick = false;
    var myHost = w.location.hostname;

    function bump(k) { pend[k]++; tot[k]++; schedule(); }

    // ---------- مراقبة تحميل سكريبتات الشبكة (اللي بتتضاف من الكود نفسه) ----------
    var mo = null;
    function watchScripts() {
        if (mo || !w.MutationObserver) return;
        mo = new MutationObserver(function (list) {
            list.forEach(function (m) {
                Array.prototype.forEach.call(m.addedNodes || [], function (n) {
                    if (!n || n.nodeName !== 'SCRIPT' || !n.src || n.__plw) return;
                    var host = ''; try { host = new URL(n.src, w.location.href).hostname; } catch (e) {}
                    if (!host || host === myHost) return;           // بنراقب سكريبتات الشبكات بس
                    n.__plw = 1;
                    n.addEventListener('load',  function () { bump('l'); });
                    n.addEventListener('error', function () { bump('e'); });
                });
            });
        });
        mo.observe(d.documentElement, { childList: true, subtree: true });
    }

    // ---------- حقن الكود (بيشغّل الـ <script> فعلاً) ----------
    function inject(code) {
        // لو الكود اتنسخ من شات أو Word ممكن علامات الاقتباس تتحول لمائلة وتبوّظ الكود
        code = String(code).replace(/[\u2018\u2019\u201A\u2032]/g, "'").replace(/[\u201C\u201D\u201E\u2033]/g, '"');
        watchScripts();
        var tpl = d.createElement('template');
        tpl.innerHTML = code;
        var host = d.body || d.documentElement;
        Array.prototype.slice.call(tpl.content.childNodes).forEach(function (n) {
            if (n.nodeName === 'SCRIPT') {
                var s = d.createElement('script');
                for (var i = 0; i < n.attributes.length; i++) s.setAttribute(n.attributes[i].name, n.attributes[i].value);
                s.text = n.textContent;
                host.appendChild(s);       // المراقب هيلقط تحميله لو له src
            } else {
                host.appendChild(d.importNode(n, true));
            }
        });
    }

    // ---------- العدّاد ----------
    function send(snap) {
        var body = JSON.stringify({ s: scope, v: snap.v, c: snap.c, h: snap.h, l: snap.l, e: snap.e, p: snap.p });
        var ok = false;
        try { ok = navigator.sendBeacon && navigator.sendBeacon(endpoint, new Blob([body], { type: 'text/plain;charset=UTF-8' })); } catch (x) { ok = false; }
        if (ok) return;
        var restore = function () { for (var k in snap) pend[k] += snap[k]; };
        try {
            fetch(endpoint, { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'text/plain' } })
                .then(function (r) { if (!r.ok && r.status >= 500) restore(); })
                .catch(restore);
        } catch (x) { restore(); }
    }

    function flush() {
        var any = false, k, snap = {};
        for (k in pend) { snap[k] = pend[k]; if (pend[k]) any = true; }
        if (!any) return;
        for (k in pend) pend[k] = 0;
        send(snap);
    }

    function schedule() {
        if (!counting || timer) return;
        timer = setTimeout(function () { timer = null; flush(); }, 4000);
    }

    function onClick(e) {
        if (!e.isTrusted) return;                       // نقرات السكريبتات الوهمية مش بتتحسب
        bump('c');                                      // كل نقرة حقيقية على الصفحة بتتحسب
        if (e.target && e.target.id === 'pl_shield') bump('h');   // ونقرات الطبقة كمان داخلة ضمن النقرات
        lastClick = Date.now(); popCounted = false;
        try { hadFocusAtClick = d.hasFocus(); } catch (x) { hadFocusAtClick = true; }
    }

    // تقدير فتح نافذة إعلان: الصفحة بتفقد التركيز / بتتخبي بعد النقرة بثواني قليلة (وماكانش ده بسبب الـ iframe)
    function maybePop() {
        if (popCounted || !hadFocusAtClick || Date.now() - lastClick > 1500) return;
        var a = d.activeElement;
        if (a && a.tagName === 'IFRAME') return;        // التركيز راح لإطار المشغل مش لنافذة جديدة
        popCounted = true; bump('p');
    }

    // ---------- طبقة النقر ----------
    function showShield() {
        if (shield && shieldParent && !shield.parentNode) shieldParent.appendChild(shield);
        if (shield) shield.style.display = 'block';
    }

    E.armShield = function (parent) {
        E.disarmShield();
        if (!parent || !pops.length) return;
        var mode = opts.shield_mode || 'off';
        if (mode === 'off') return;
        try { if (w.getComputedStyle(parent).position === 'static') parent.style.position = 'relative'; } catch (x) {}
        shieldParent = parent;
        shield = d.createElement('div');
        shield.id = 'pl_shield';
        shield.setAttribute('style', 'position:absolute;left:0;top:0;right:0;bottom:0;z-index:5;background:transparent;cursor:pointer;-webkit-tap-highlight-color:transparent;');
        shield.addEventListener('click', function () {
            // الحدث بيكمّل طريقه للـ document (الإعلان بيشوفه) وبعدها الطبقة بتفتح الطريق للمشغل
            setTimeout(function () {
                if (!shield) return;
                if (mode === 'first') {
                    if (shield.parentNode) shield.parentNode.removeChild(shield);
                } else {
                    shield.style.display = 'none';
                    clearTimeout(shieldTimer);
                    shieldTimer = setTimeout(showShield, Math.max(1, +opts.shield_cooldown || 4) * 1000);
                }
            }, 0);
        });
        parent.appendChild(shield);
    };

    E.disarmShield = function () {
        clearTimeout(shieldTimer);
        if (shield && shield.parentNode) shield.parentNode.removeChild(shield);
        shield = null;
    };

    // ---------- لوحة التشخيص ?popdebug=1 ----------
    function debugPanel() {
        var box = d.createElement('div');
        box.setAttribute('style', 'position:fixed;left:6px;top:6px;z-index:2147483647;background:rgba(0,0,0,.88);color:#e5e7eb;font:12px/1.7 Tahoma,sans-serif;padding:8px 10px;border-radius:8px;direction:rtl;max-width:260px;pointer-events:none');
        (d.body || d.documentElement).appendChild(box);
        function paint() {
            var inFrame = (w.self !== w.top);
            box.innerHTML = '<b>تشخيص البوب</b> (' + scope + ')<br>' +
                'أكواد مفعّلة: ' + pops.length + '<br>' +
                'سكريبت اتحمّل: <b style="color:#22c55e">' + tot.l + '</b> | فشل/محجوب: <b style="color:' + (tot.e ? '#ef4444' : '#e5e7eb') + '">' + tot.e + '</b><br>' +
                'نقرات الصفحة: ' + tot.c + ' | نقرات الطبقة: ' + tot.h + '<br>' +
                'نوافذ فُتحت (تقدير): <b>' + tot.p + '</b><br>' +
                'جوه iframe؟ ' + (inFrame ? '<b style="color:#ef4444">نعم (الشبكات غالباً مش بتحسب)</b>' : 'لا ✅') + '<br>' +
                'الطبقة: ' + (shield && shield.parentNode && shield.style.display !== 'none' ? 'شغالة' : 'مفتوحة/مقفولة');
        }
        paint(); setInterval(paint, 500);
    }

    // ---------- التشغيل ----------
    E.init = function (o) {
        if (inited) return; inited = true;
        o = o || {};
        scope = o.scope === 'live' ? 'live' : 'player';
        pops = o.pops || [];
        opts = o.opts || {};
        endpoint = opts.endpoint || 'click.php';
        counting = !!opts.count && pops.length > 0;

        if (counting) {
            pend.v = 1; tot.v = 1;
            d.addEventListener('click', onClick, true);
            w.addEventListener('blur', function () { setTimeout(maybePop, 60); });
            d.addEventListener('visibilitychange', function () {
                if (d.visibilityState === 'hidden') { maybePop(); flush(); }
            });
            setInterval(flush, 8000);
            w.addEventListener('pagehide', flush);
            schedule();
        }
        if (/[?&]popdebug=1/.test(w.location.search)) {
            if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', debugPanel); else debugPanel();
        }

        pops.forEach(function (P) {
            var key = 'pl_pop_' + scope + '_' + P.id, fired = false;
            try { if (P.once && sessionStorage.getItem(key)) return; } catch (x) {}
            function run() {
                if (fired) return; fired = true;
                try { if (P.once) sessionStorage.setItem(key, '1'); } catch (x) {}
                try { inject(P.code); } catch (x) {}
            }
            if (P.trigger === 'delay') { setTimeout(run, (+P.delay || 0) * 1000); }
            else if (P.trigger === 'click') {
                var h = function () { d.removeEventListener('click', h, true); setTimeout(run, (+P.delay || 0) * 1000); };
                d.addEventListener('click', h, true);
            }
            else if (P.trigger === 'layer') {
                var layer = d.createElement('div');
                layer.setAttribute('style', 'position:fixed;left:0;top:0;right:0;bottom:0;z-index:55;background:transparent;cursor:pointer;');
                layer.addEventListener('click', function () {
                    if (layer.parentNode) layer.parentNode.removeChild(layer);
                    setTimeout(run, (+P.delay || 0) * 1000);
                });
                (d.body || d.documentElement).appendChild(layer);
            }
            else { run(); }   // load (الافتراضي والأفضل)
        });
    };

    w.PopEngine = E;
})(window, document);
