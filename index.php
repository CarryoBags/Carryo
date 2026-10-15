<?php
session_start();
include 'insert/action.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$data = [];
if (file_exists('data/data.json')) {
    $jsonData = file_get_contents('data/data.json');
    $decoded = json_decode($jsonData, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

$totalItems = count($data);
$totalSources = 0;
$totalEmbeds = 0;
foreach ($data as $item) {
    foreach ($item as $key => $value) {
        if (is_numeric($key) && is_array($value)) {
            $type = isset($value['Link_type']) ? $value['Link_type'] : '';
            $url = isset($value['Stream_Url']) ? trim($value['Stream_Url']) : '';
            if ($url === '') {
                continue;
            }
            if ($type === 'embed') {
                $totalEmbeds++;
            } else {
                $totalSources++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة إدارة البث</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="panel_shell">
        <div class="panel_header">
            <div>
                <h1 class="panel_title">لوحة إدارة البث</h1>
                <p class="panel_subtitle">إدارة الروابط، الإعلانات، وصفحات المشغل من مكان واحد</p>
            </div>
            <div class="header_actions">
                <a href="insert/create.php" class="btn btn_primary"><i class="fa-solid fa-plus"></i> إضافة بث</a>
                <a href="insert/settings.php" class="btn btn_warning"><i class="fa-solid fa-rectangle-ad"></i> إعلان منبثق</a>
                <a href="insert/player_settings.php" class="btn btn_primary"><i class="fa-solid fa-palette"></i> تصميم المشغل</a>
                <a href="api.php" class="btn btn_dark"><i class="fa-solid fa-database"></i> بيانات API</a>
                <button type="button" class="theme_toggle" data-theme-toggle title="تفعيل الوضع الليلي"><i class="fa-solid fa-moon"></i></button>
                <a href="?logout=true" class="btn btn_danger"><i class="fa-solid fa-right-from-bracket"></i> خروج</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])) { ?>
            <div class="success_msg">
                <?php echo $_SESSION['success_message']; ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php } elseif (isset($_SESSION['error_message'])) { ?>
            <div class="error_msg">
                <?php echo $_SESSION['error_message']; ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php } ?>

        <div class="stats_grid">
            <div class="stat_card">
                <span>إجمالي الصفحات</span>
                <strong><?php echo $totalItems; ?></strong>
            </div>
            <div class="stat_card">
                <span>روابط Source</span>
                <strong><?php echo $totalSources; ?></strong>
            </div>
            <div class="stat_card">
                <span>روابط Embed</span>
                <strong><?php echo $totalEmbeds; ?></strong>
            </div>
            <div class="stat_card">
                <span>آخر تحديث</span>
                <strong><?php echo date('Y-m-d'); ?></strong>
            </div>
        </div>

        <div class="table_block">
            <div class="table_head">
                <h2>كل صفحات البث</h2>
                <div class="table_tools">
                    <input type="text" id="tableSearch" class="search_input" placeholder="بحث بعنوان الصفحة...">
                    <?php if (!empty($data)) { ?>
                    <form method="post" action="insert/action.php" style="display:inline;margin:0" onsubmit="return plConfirmDeleteAll(<?php echo (int)$totalItems; ?>);">
                        <input type="hidden" name="delete_all" value="1">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                        <button type="submit" class="btn btn_danger"><i class="fa-solid fa-trash-can"></i> حذف كل البثوث</button>
                    </form>
                    <?php } ?>
                </div>
            </div>
            <div class="table_wrap">
                <?php
                if (empty($data)) {
                    echo '<div class="empty_state">لا يوجد بيانات حالياً.</div>';
                } else {
                    $num = 1;
                    echo '<table class="custom_table" id="streamsTable">';
                    echo '<tr>';
                    echo '<th>#</th>';
                    echo '<th>العنوان</th>';
                    echo '<th>التاريخ</th>';
                    echo '<th>السيرفرات</th>';
                    echo '<th>المعاينة</th>';
                    echo '<th>إجراءات</th>';
                    echo '</tr>';
                    foreach ($data as $item) {
                        $title = isset($item['title']) ? htmlspecialchars($item['title']) : '';
                        $date = isset($item['date']) ? htmlspecialchars($item['date']) : '';
                        $id = isset($item['id']) ? urlencode($item['id']) : '';
                        $rawId = isset($item['id']) ? $item['id'] : '';
                        $servers = 0;
                        foreach ($item as $k => $v) {
                            if (is_numeric($k) && is_array($v) && !empty($v['Stream_Url'])) {
                                $servers++;
                            }
                        }
                        $embedLink = 'embed.php?id=' . $id;
                        echo '<tr>';
                        echo '<td>' . $num++ . '</td>';
                        echo '<td class="title_col">' . $title . '</td>';
                        echo '<td>' . $date . '</td>';
                        echo '<td><span class="chip_count">' . $servers . '</span></td>';
                        echo '<td>';
                        echo '<div class="action_btn">';
                        echo '<a href="' . $embedLink . '" target="_blank" class="action_link" title="فتح"><i class="fa-solid fa-link"></i></a>';
                        echo '</div>';
                        echo '</td>';
                        echo '<td>';
                        echo '<div class="action_btn">';
                        echo '<a href="#" class="action_link copy_row_link" data-link="' . htmlspecialchars($embedLink) . '" title="نسخ الرابط"><i class="fa-solid fa-copy"></i></a>';
                        echo '<a href="insert/edit.php?id=' . $id . '" class="action_link" title="تعديل"><i class="fa-solid fa-pen-to-square"></i></a>';
                        echo '<a href="insert/action.php?node=' . urlencode($rawId) . '" class="action_link delete" onclick="return confirm(\'تأكيد حذف هذا البث؟\');" title="حذف"><i class="fa-solid fa-trash"></i></a>';
                        echo '</div>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                ?>
            </div>
        </div>
    </div>
    <script>
        var searchInput = document.getElementById('tableSearch');
        var table = document.getElementById('streamsTable');
        if (searchInput && table) {
            searchInput.addEventListener('input', function() {
                var query = searchInput.value.toLowerCase();
                var rows = table.querySelectorAll('tr');
                for (var i = 1; i < rows.length; i++) {
                    var titleCell = rows[i].querySelector('.title_col');
                    var text = titleCell ? titleCell.textContent.toLowerCase() : '';
                    rows[i].style.display = text.indexOf(query) > -1 ? '' : 'none';
                }
            });
        }
        var copyButtons = document.querySelectorAll('.copy_row_link');
        for (var c = 0; c < copyButtons.length; c++) {
            copyButtons[c].addEventListener('click', function(e) {
                e.preventDefault();
                var relative = this.getAttribute('data-link');
                var full = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/') + relative;
                navigator.clipboard.writeText(full);
                this.innerHTML = '<i class="fa-solid fa-check"></i>';
                var self = this;
                setTimeout(function(){ self.innerHTML = '<i class="fa-solid fa-copy"></i>'; }, 1200);
            });
        }
    </script>
    <script src="js/panel-theme.js"></script>
<script>
function plConfirmDeleteAll(n) {
    if (!confirm('هتمسح كل البثوث (' + n + ')!\nهيتعمل نسخة احتياطية تلقائياً قبل المسح.\nمتأكد؟')) return false;
    var t = prompt('للتأكيد النهائي اكتب كلمة: حذف');
    return t !== null && t.trim() === 'حذف';
}
</script>
</body>
</html>
