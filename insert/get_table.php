<?php
// ==========================================
// إعدادات المنطقة الزمنية
// ==========================================
// للمغرب (توقيت غرينتش +1 في الشتاء، +0 في الصيف)
date_default_timezone_set('Africa/Casablanca');

// ==========================================

// مسح محتوى الملف أولاً
$file = fopen('../data/api.txt', 'w');
fclose($file);

include("simple_html_dom.php");

// رابط الموقع
$url = 'https://www.channellivetv.com//';

// إنشاء كائن DOM
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
curl_close($ch);

$html = str_get_html($response);

// التحقق من نجاح الاتصال
if (!$html) {
    echo "خطأ: لم نستطع الاتصال بالموقع<br>";
    echo "<a href='../api.php'>العودة</a>";
    exit();
}

$totalMatches = 0;

// البحث عن جميع المباريات في القسم النشط
$matches = $html->find('.albaflex.active .AY_Match');

if (empty($matches)) {
    echo "خطأ: لم يتم العثور على مباريات<br>";
    echo "<a href='../api.php'>العودة</a>";
    exit();
}




// التاريخ الحالي
$currentDate = date('Y-m-d');

// المرور على كل مباراة
foreach ($matches as $match) {
    $totalMatches++;
    
    // استخراج اسم الفريق الأول
    $team1Name = $match->find('.TM1 .TM_Name', 0);
    $teamName1 = $team1Name ? trim($team1Name->plaintext) : 'فريق 1';
    
    // استخراج صورة الفريق الأول
    $team1Img = $match->find('.TM1 .TM_Logo img', 0);
    $image1 = '';
    if ($team1Img) {
        $image1 = $team1Img->getAttribute('data-src') ?: $team1Img->src;
    }
    
    // استخراج اسم الفريق الثاني
    $team2Name = $match->find('.TM2 .TM_Name', 0);
    $teamName2 = $team2Name ? trim($team2Name->plaintext) : 'فريق 2';
    
    // استخراج صورة الفريق الثاني
    $team2Img = $match->find('.TM2 .TM_Logo img', 0);
    $image2 = '';
    if ($team2Img) {
        $image2 = $team2Img->getAttribute('data-src') ?: $team2Img->src;
    }
    
    // استخراج وقت المباراة
    $timeElement = $match->find('.MT_Time', 0);
    $matchTime = $timeElement ? trim($timeElement->plaintext) : '';
    $displayTime = $matchTime;
    
    // استخراج حالة المباراة
    $statusElement = $match->find('.MT_Stat', 0);
    $status = $statusElement ? trim($statusElement->plaintext) : 'لم تبدأ بعد';
    
    // استخراج النتيجة
    $scores = $match->find('.RS-goals');
    $result = 'VS';
    if (count($scores) >= 2) {
        $score1 = trim($scores[0]->plaintext);
        $score2 = trim($scores[1]->plaintext);
        $result = $score1 . '-' . $score2;
    }
    
// استخراج معلومات البطولة
$infoList = $match->find('.MT_Info ul li');
$tournamentHTML = '';
$firstSpan = ''; // لحفظ أول عنصر للعنوان

if (!empty($infoList)) {
    $isFirst = true;
    foreach ($infoList as $item) {
        $spanElement = $item->find('span', 0);
        if ($spanElement) {
            $text = trim($spanElement->plaintext);
            if (!empty($text)) {
                $tournamentHTML .= "<li><span>{$text}</span></li>";
                // حفظ أول عنصر للاستخدام في العنوان
                if ($isFirst) {
                    $firstSpan = $text;
                    $isFirst = false;
                }
            }
        }
    }
}

// إذا لم يتم العثور على أي معلومات
if (empty($tournamentHTML)) {
    $tournamentHTML = '<li><span>بطولة</span></li>';
    $firstSpan = 'بطولة';
}

if (empty($firstSpan)) {
    $firstSpan = 'مباراة';
}

// تحويل الوقت إلى تنسيق 24 ساعة
$formattedTime = '00:00'; // قيمة افتراضية
if (!empty($matchTime) && preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $matchTime, $timeMatches)) {
    $hour = intval($timeMatches[1]);
    $min = intval($timeMatches[2]);
    $ampm = strtoupper($timeMatches[3]);
    
    if ($ampm === 'PM' && $hour < 12) {
        $hour += 12;
    } elseif ($ampm === 'AM' && $hour === 12) {
        $hour = 0;
    }
    
    $formattedTime = sprintf('%02d:%02d', $hour, $min);
} elseif (!empty($matchTime) && preg_match('/^(\d{1,2}):(\d{2})$/', $matchTime, $timeMatches)) {
    // إذا كان الوقت بالفعل بصيغة 24 ساعة
    $hour = intval($timeMatches[1]);
    $min = intval($timeMatches[2]);
    $formattedTime = sprintf('%02d:%02d', $hour, $min);
}

// التحقق من صحة الوقت قبل استخدامه
if (empty($formattedTime) || $formattedTime === '00:00') {
    // استخدام الوقت الحالي كبديل
    $formattedTime = date('H:i');
}

// ===============================
// وقت بداية المباراة (كما هو)
// ===============================
// وقت بداية المباراة
$startDateTime = "{$currentDate} {$formattedTime}:00+02:00";

try {
    // إنشاء كائن DateTime
    $start = new DateTime($startDateTime);

    // ❗ طرح ساعة من موعد المباراة
    $start->modify('0 hour');

    // إضافة ساعتين = وقت نهاية المباراة
    $end = clone $start;
    $end->modify('+2 hours');

    // تنسيق القيم
    $startDateTime = $start->format('Y-m-d H:i:sP');
    $endDateTime   = $end->format('Y-m-d H:i:sP');
} catch (Exception $e) {
    // في حالة فشل إنشاء DateTime، استخدم قيم افتراضية
    error_log("خطأ في تحويل التاريخ: " . $e->getMessage() . " - الوقت: {$matchTime}");
    $now = new DateTime('now', new DateTimeZone('Africa/Casablanca'));
    $startDateTime = $now->format('Y-m-d H:i:sP');
    $end = clone $now;
    $end->modify('+2 hours');
    $endDateTime = $end->format('Y-m-d H:i:sP');
}

// بناء HTML للمباراة
$matchHTML = "<div class='match-container'>\n";
$matchHTML .= "  <div class='right-team fl-start'>\n";
$matchHTML .= "    <div class='team-logo fl-start'>\n";
$matchHTML .= "      <img alt='{$teamName1}' src='{$image1}' title='{$teamName1}' width='70' height='70' loading='lazy'>\n";
$matchHTML .= "    </div>\n";
$matchHTML .= "    <div class='team-name'>{$teamName1}</div>\n";
$matchHTML .= "  </div>\n";
$matchHTML .= "  <div class='match-center fl-start'>\n";
$matchHTML .= "    <div class='match-timing'>\n";
$matchHTML .= "      <div class='match-time'>{$status}</div>\n";
$matchHTML .= "      <div class='result'>{$result}</div>\n";
$matchHTML .= "      <div class='date AlbaDate' data-start='{$startDateTime}' data-gameends='{$endDateTime}'> {$displayTime} </div>\n";
$matchHTML .= "    </div>\n";
$matchHTML .= "  </div>\n";
$matchHTML .= "  <div class='left-team fl-start'>\n";
$matchHTML .= "    <div class='team-logo fl-end'>\n";
$matchHTML .= "      <img alt='{$teamName2}' src='{$image2}' title='{$teamName2}' width='70' height='70' loading='lazy'>\n";
$matchHTML .= "    </div>\n";
$matchHTML .= "    <div class='team-name'>{$teamName2}</div>\n";
$matchHTML .= "  </div>\n";
$matchHTML .= "  <div class='match-info'>\n";
$matchHTML .= "    <ul>\n";
$matchHTML .= "      {$tournamentHTML}\n";
$matchHTML .= "    </ul>\n";
$matchHTML .= "  </div>\n";
$matchHTML .= "  <a title='{$firstSpan}' href='#'>\n";
$matchHTML .= "    <div class='overlay-match'>\n";
$matchHTML .= "      <div class='text-match'></div>\n";
$matchHTML .= "    </div>\n";
$matchHTML .= "  </a>\n";
$matchHTML .= "</div>\n\n";    
    // حفظ في الملف
    file_put_contents('../data/api.txt', $matchHTML, FILE_APPEND);
}

// تنظيف الذاكرة
$html->clear();
unset($html);

// إعادة التوجيه المباشرة إلى api.php
header('Location: ../api.php');
exit();
?>