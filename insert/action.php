<?php
$random_id = mt_rand(1000000000, 9999999999);
session_start();

$jsonFile = '../data/data.json';

function readDataFromFile() {
    if (!file_exists($GLOBALS['jsonFile'])) {
        return [];
    }
    $jsonData = file_get_contents($GLOBALS['jsonFile']);
    $decoded = json_decode($jsonData, true);
    return is_array($decoded) ? $decoded : [];
}

function writeDataToFile($data) {
    $jsonUpdated = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($GLOBALS['jsonFile'], $jsonUpdated);
}

function collectLinksFromPost() {
    $links = [];
    $position = 1;
    
    // جلب روابط الموبايل (mobile)
    for ($i = 1; $i <= 50; $i++) {
        $urlKey = "mobile_stream_url_{$i}";
        if (isset($_POST[$urlKey]) && !empty(trim($_POST[$urlKey]))) {
            $serverName = isset($_POST["mobile_server_name_{$i}"]) ? trim($_POST["mobile_server_name_{$i}"]) : "سيرفر {$i}";
            $linkType = isset($_POST["mobile_link_type_{$i}"]) ? $_POST["mobile_link_type_{$i}"] : 'embed';
            $player = isset($_POST["mobile_player_{$i}"]) ? $_POST["mobile_player_{$i}"] : 'videojs'; // استقبال المشغل
            $device = isset($_POST["mobile_device_{$i}"]) ? $_POST["mobile_device_{$i}"] : 'mobile';
            $useProxy = isset($_POST["mobile_use_proxy_{$i}"]) ? true : false;
            
            $links[$position] = [
                'name' => $serverName,
                'Stream_Url' => trim($_POST[$urlKey]),
                'player' => $player, // حفظ المشغل في المصفوفة
                'Link_type' => $linkType,
                'use_proxy' => $useProxy,
                'device' => $device
            ];
            $position++;
        }
    }
    
    // جلب روابط الكمبيوتر (desktop)
    for ($i = 1; $i <= 50; $i++) {
        $urlKey = "desktop_stream_url_{$i}";
        if (isset($_POST[$urlKey]) && !empty(trim($_POST[$urlKey]))) {
            $serverName = isset($_POST["desktop_server_name_{$i}"]) ? trim($_POST["desktop_server_name_{$i}"]) : "سيرفر {$i}";
            $linkType = isset($_POST["desktop_link_type_{$i}"]) ? $_POST["desktop_link_type_{$i}"] : 'source';
            $player = isset($_POST["desktop_player_{$i}"]) ? $_POST["desktop_player_{$i}"] : 'videojs'; // استقبال المشغل
            $device = isset($_POST["desktop_device_{$i}"]) ? $_POST["desktop_device_{$i}"] : 'desktop';
            $useProxy = isset($_POST["desktop_use_proxy_{$i}"]) ? true : false;
            
            $links[$position] = [
                'name' => $serverName,
                'Stream_Url' => trim($_POST[$urlKey]),
                'player' => $player, // حفظ المشغل في المصفوفة
                'Link_type' => $linkType,
                'use_proxy' => $useProxy,
                'device' => $device
            ];
            $position++;
        }
    }
    
    ksort($links);
    return array_values($links);
}

// CREATE
if (isset($_POST["submit"])) {
    $data = readDataFromFile();

    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
    $id = "$random_id";
    $links = collectLinksFromPost();

    if ($title === '' || empty($links)) {
        $_SESSION['error_message'] = "Please add title and at least one stream link.";
        header("Location: ../insert/create.php");
        exit();
    }

    $newData = [
        'id' => $id,
        'title' => $title,
        'date' => date('Y-m-d'),
    ];

    $position = 1;
    foreach ($links as $link) {
        $newData[$position] = $link;
        $position++;
    }

    $data[] = $newData;
    writeDataToFile($data);

    $_SESSION['success_message'] = "Entry added successfully!";
    header("Location: ../index.php");
    exit();
}

// DELETE ALL (محمي: لازم تسجيل دخول + توكن + نسخة احتياطية تلقائية قبل المسح)
if (isset($_POST['delete_all'])) {
    $backHome = (strpos($_SERVER['SCRIPT_NAME'], '/insert/') !== false) ? '../index.php' : 'index.php';

    if (!isset($_SESSION['user']) || empty($_SESSION['csrf']) || !isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
        $_SESSION['error_message'] = "تعذر تنفيذ الحذف (الجلسة غير صالحة). حدّث الصفحة وجرّب تاني.";
        header("Location: " . $backHome);
        exit();
    }

    $data = readDataFromFile();
    $count = count($data);

    if ($count === 0) {
        $_SESSION['error_message'] = "مفيش بثوث علشان تتمسح.";
        header("Location: " . $backHome);
        exit();
    }

    // نسخة احتياطية قبل المسح (لو فشلت النسخة، المسح بيتلغي)
    $backupDir = dirname($GLOBALS['jsonFile']) . '/backup';
    if (!is_dir($backupDir)) { @mkdir($backupDir, 0755, true); }
    $backupFile = $backupDir . '/data-' . date('Ymd-His') . '.json';
    if (!@copy($GLOBALS['jsonFile'], $backupFile)) {
        $_SESSION['error_message'] = "تعذر عمل نسخة احتياطية (تأكد من صلاحيات مجلد data)، فالحذف اتلغى.";
        header("Location: " . $backHome);
        exit();
    }
    // نسيب آخر 10 نسخ بس
    $olds = glob($backupDir . '/data-*.json');
    if (is_array($olds) && count($olds) > 10) {
        sort($olds);
        foreach (array_slice($olds, 0, count($olds) - 10) as $oldFile) { @unlink($oldFile); }
    }

    writeDataToFile([]);

    $_SESSION['success_message'] = "تم حذف كل البثوث ($count). نسخة احتياطية في data/backup/" . basename($backupFile);
    header("Location: " . $backHome);
    exit();
}

// DELETE
if (isset($_GET['node'])) {
    $data = readDataFromFile();
    $idToDelete = $_GET['node'];

    $indexToDelete = array_search($idToDelete, array_column($data, 'id'));

    if ($indexToDelete !== false) {
        array_splice($data, $indexToDelete, 1);
        writeDataToFile($data);

        $_SESSION['success_message'] = "Entry deleted successfully!";
        header("Location: ../index.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Entry with ID '$idToDelete' not found.";
        header("Location: ../index.php");
        exit();
    }
}

// EDIT
if (isset($_GET["id"])) {
    $editId = $_GET["id"];
    $data = readDataFromFile();

    $foundData = null;
    foreach ($data as $key => $item) {
        if ($item['id'] == $editId) {
            $foundData = &$data[$key];
            break;
        }
    }

    if ($foundData) {
        if (isset($_POST["update"])) {
            $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
            $links = collectLinksFromPost();

            if ($title === '' || empty($links)) {
                $_SESSION['error_message'] = "Please add title and at least one stream link.";
                header("Location: ../insert/edit.php?id=" . urlencode($editId));
                exit();
            }
            $foundData['title'] = $title;

            foreach ($foundData as $k => $v) {
                if (is_numeric($k)) {
                    unset($foundData[$k]);
                }
            }
            $position = 1;
            foreach ($links as $link) {
                $foundData[$position] = $link;
                $position++;
            }

            writeDataToFile($data);

            $_SESSION['success_message'] = "Entry updated successfully!";
            header("Location: ../index.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Data not found for ID: $editId";
        header("Location: ../index.php");
        exit();
    }
}

?>