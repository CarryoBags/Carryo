<?php
// سكريبت إعلانات البوب لوحده (لو عايز الإعلان بس في صفحة فيها iframe المشغل بتاعك)
// <script async src="https://دومينك/pops.js.php"></script>
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/pops_lib.php';
echo build_pops_js();
