<?php
session_start();
// Check if the user is not logged in, redirect to the login page
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Logout logic
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'generate_code') {
        $matches = json_decode($_POST['matches'], true);
        $generatedCode = generateMatchesCode($matches);
        echo json_encode(['success' => true, 'code' => $generatedCode]);
        exit();
    }
}

// Function to generate the final code - متوافق مع النموذج المطلوب
function generateMatchesCode($matches) {
    $code = '';
    foreach ($matches as $match) {
        // حساب وقت انتهاء المباراة (ساعتين بعد البداية)
        $gameends = $match['gameends'] ?? '';
        if (empty($gameends) && !empty($match['datetime'])) {
            try {
                $startTime = new DateTime($match['datetime']);
                $startTime->modify('+2 hours');
                $gameends = $startTime->format('Y-m-d H:i:sP');
            } catch (Exception $e) {
                $gameends = $match['datetime'];
            }
        }
        
        $code .= "<div class='match-container'>\n";
        $code .= "            <div class='right-team fl-start'>\n";
        $code .= "                <div class='team-logo fl-start'>\n";
        $code .= "                    <img alt='{$match['team1']}' src='{$match['image1']}' title='{$match['team1']}' width='70' height='70' loading='lazy'>\n";
        $code .= "                </div>\n";
        $code .= "                <div class='team-name'>{$match['team1']}</div>\n";
        $code .= "            </div>\n";
        $code .= "            <div class='match-center fl-start'>\n";
        $code .= "                <div class='match-timing'>\n";
        $code .= "                    <div class='match-time'>لم تبدأ بعد</div>\n";
        $code .= "                    <div class='result'>0-0</div>\n";
        $code .= "                    <div class='date AlbaDate' data-start='{$match['datetime']}' data-gameends='{$gameends}'> </div>\n";
        $code .= "                </div>\n";
        $code .= "            </div>\n";
        $code .= "            <div class='left-team fl-start'>\n";
        $code .= "                <div class='team-logo fl-end'>\n";
        $code .= "                    <img alt='{$match['team2']}' src='{$match['image2']}' title='{$match['team2']}' width='70' height='70' loading='lazy'>\n";
        $code .= "                </div>\n";
        $code .= "                <div class='team-name'>{$match['team2']}</div>\n";
        $code .= "            </div>\n";
        $code .= "            <div class='match-info'>\n";
        $code .= "                <ul>\n";
        $code .= "                    <li><span>{$match['mic']}</span></li>\n";
        $code .= "                    <li><span>{$match['television']}</span></li>\n";
        $code .= "                    <li><span>{$match['tournament']}</span></li>\n";
        $code .= "                </ul>\n";
        $code .= "            </div>\n";
        $code .= "            <a title='{$match['television']}' href='{$match['link']}'>\n";
        $code .= "                <div class='overlay-match'>\n";
        $code .= "                    <div class='text-match'></div>\n";
        $code .= "                </div>\n";
        $code .= "            </a>\n";
        $code .= "        </div>\n\n";
    }
    return $code;
}
// Parse the matches - متوافق مع الهيكل الجديد
function parseMatches($content) {
    $matches = [];
    
    if (empty($content)) {
        return $matches;
    }
    
    // Debug: Log content length
    error_log("Content length: " . strlen($content));
    
    // Split by match-container divs - استخدام regex أفضل
    $pattern = "/<div class='match-container'>(.*?)<\/div>\s*<\/a>\s*<\/div>/s";
    preg_match_all($pattern, $content, $matchBlocks, PREG_SET_ORDER);
    
    error_log("Found " . count($matchBlocks) . " match blocks");
    
    foreach ($matchBlocks as $blockMatch) {
        $block = $blockMatch[0];
        if (empty(trim($block))) continue;
        
        $match = [];
        
        // Extract team 1 (right-team)
        if (preg_match("/right-team.*?<img[^>]+alt='([^']+)'[^>]+src='([^']+)'/s", $block, $team1)) {
            $match['team1'] = trim($team1[1]);
            $match['image1'] = trim($team1[2]);
        }
        
        // Extract team 2 (left-team)
        if (preg_match("/left-team.*?<img[^>]+alt='([^']+)'[^>]+src='([^']+)'/s", $block, $team2)) {
            $match['team2'] = trim($team2[1]);
            $match['image2'] = trim($team2[2]);
        }
        
        // Extract datetime
        if (preg_match("/data-start='([^']+)'/", $block, $datetime)) {
            $match['datetime'] = trim($datetime[1]);
        }
        
        // Extract match-info items (mic, television, tournament)
        if (preg_match_all("/<li>\s*<span>([^<]*)<\/span>\s*<\/li>/s", $block, $infoItems)) {
            $match['mic'] = isset($infoItems[1][0]) ? trim($infoItems[1][0]) : '';
            $match['television'] = isset($infoItems[1][1]) ? trim($infoItems[1][1]) : '';
            $match['tournament'] = isset($infoItems[1][2]) ? trim($infoItems[1][2]) : '';
        }
        
        // Extract link
        if (preg_match("/<a[^>]+href='([^']+)'/", $block, $link)) {
            $match['link'] = trim($link[1]);
        } else {
            $match['link'] = 'YOURSTREAMLINK';
        }
        
        // Only add if we have minimum required data
        if (!empty($match['team1']) && !empty($match['team2'])) {
            $matches[] = $match;
        } else {
            error_log("Skipping incomplete match: " . json_encode($match));
        }
    }
    
    error_log("Parsed " . count($matches) . " matches successfully");
    return $matches;
}

// Read API content
$apiContent = '';
if (file_exists('data/api.txt')) {
    $apiContent = file_get_contents('data/api.txt');
}
$matches = parseMatches($apiContent);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .matches-manager {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .match-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 15px;
            position: relative;
        }

        .match-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .team-section {
            flex: 1;
            text-align: center;
        }

        .team-section img {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            margin-bottom: 5px;
        }

        .team-section .team-name {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .match-details {
            flex: 1;
            text-align: center;
            padding: 0 15px;
        }

        .match-time {
            font-size: 16px;
            font-weight: 700;
            color: #104783;
            margin-bottom: 5px;
        }

        .tournament-name {
            font-size: 12px;
            color: #666;
        }

        .tv-channel {
            font-size: 11px;
            color: #999;
            margin-top: 3px;
        }

        .link-input-section {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }

        .link-input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            direction: ltr;
            text-align: left;
        }

        .link-input:focus {
            outline: none;
            border-color: #104783;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            justify-content: center;
        }

        .generate-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s;
        }

        .generate-btn:hover {
            background: #218838;
        }

        .refresh-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s;
        }

        .refresh-btn:hover {
            background: #0056b3;
        }

        .code-output {
            width: 100%;
            min-height: 300px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            direction: ltr;
            text-align: left;
            background: #f8f9fa;
        }

        .code-output:focus {
            outline: none;
            border-color: #104783;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .matches-count {
            background: #104783;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .copy-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s;
        }

        .copy-btn:hover {
            background: #5a6268;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            text-align: center;
            display: none;
        }
    </style>
</head>

<body>
    <div class="form_container_table">
        <div class="table_head">
            <h2>Embed Manager</h2>
            <div class="botton_head">
                <a href="insert/get_table.php" class="add_btn"><i class="fa-solid fa-plus"></i> Get table</a>
                <a href="../index.php" class="logout_btn"><i class="fa-solid fa-home"></i> Home</a>
            </div>
        </div>

        <div class="matches-manager">
            <?php if (empty($apiContent)): ?>
                <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <strong>تنبيه:</strong> ملف data/api.txt فارغ أو غير موجود. قم بجلب المباريات أولاً.
                </div>
            <?php endif; ?>
            
            <div class="matches-count">
                <i class="fa-solid fa-futbol"></i> 
                عدد المباريات: <span id="matchCount"><?php echo count($matches); ?></span>
            </div>

            <div id="matchesContainer">
                <?php if (empty($matches)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox"></i>
                        <h3>لا توجد مباريات</h3>
                        <p>قم بجلب المباريات من API أولاً</p>
                    </div>
                <?php else: ?>
<?php foreach ($matches as $index => $match): ?>
    <div class="match-card" data-index="<?php echo $index; ?>">
        <div class="match-preview">
            <!-- الفريق الثاني (left-team) أولاً -->
            <div class="team-section">
                <img src="<?php echo htmlspecialchars($match['image2']); ?>" alt="<?php echo htmlspecialchars($match['team2']); ?>">
                <div class="team-name"><?php echo htmlspecialchars($match['team2']); ?></div>
            </div>

            <div class="match-details">
                <div class="match-time">
                    <i class="fa-solid fa-clock"></i>
                    <?php
                    $date = new DateTime($match['datetime']);
                    echo $date->format('H:i');
                    ?>
                </div>
                <div class="tournament-name"><?php echo htmlspecialchars($match['tournament']); ?></div>
                <div class="tv-channel">
                    <i class="fa-solid fa-tv"></i>
                    <?php echo htmlspecialchars($match['television']); ?>
                </div>
            </div>

            <!-- الفريق الأول (right-team) ثانياً -->
            <div class="team-section">
                <img src="<?php echo htmlspecialchars($match['image1']); ?>" alt="<?php echo htmlspecialchars($match['team1']); ?>">
                <div class="team-name"><?php echo htmlspecialchars($match['team1']); ?></div>
            </div>
        </div>

        <div class="link-input-section">
            <input 
                type="text" 
                class="link-input" 
                placeholder="أدخل رابط البث (مثال: https://example.com/stream)"
                value="<?php echo $match['link'] !== 'YOURSTREAMLINK' ? htmlspecialchars($match['link']) : ''; ?>"
                data-index="<?php echo $index; ?>"
            >
            <button class="delete-btn" onclick="deleteMatch(<?php echo $index; ?>)">
                <i class="fa-solid fa-trash"></i>
                حذف
            </button>
        </div>
    </div>
<?php endforeach; ?>                <?php endif; ?>
            </div>

            <?php if (!empty($matches)): ?>
                <div class="action-buttons">
                    <button class="refresh-btn" onclick="location.reload()">
                        <i class="fa-solid fa-rotate"></i>
                        تحديث
                    </button>
                    <button class="generate-btn" onclick="generateCode()">
                        <i class="fa-solid fa-code"></i>
                        توليد الكود
                    </button>
                    <button class="copy-btn" onclick="copyCode()">
                        <i class="fa-solid fa-copy"></i>
                        نسخ الكود
                    </button>
                </div>

                <div class="success-message" id="successMessage">
                    <i class="fa-solid fa-check-circle"></i>
                    تم توليد الكود بنجاح!
                </div>

                <form action="" class="ath_form" method="post">
                    <textarea class="code-output" id="codeOutput" name="w3review" rows="20" readonly><?php echo htmlspecialchars($apiContent); ?></textarea>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js'></script>
    <script>
        let matchesData = <?php echo json_encode($matches); ?>;

        function deleteMatch(index) {
            if (confirm('هل أنت متأكد من حذف هذه المباراة؟')) {
                matchesData.splice(index, 1);
                updateDisplay();
            }
        }

        function updateDisplay() {
            const container = document.getElementById('matchesContainer');
            const matchCount = document.getElementById('matchCount');
            
            if (matchesData.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox"></i>
                        <h3>لا توجد مباريات</h3>
                        <p>تم حذف جميع المباريات</p>
                    </div>
                `;
                matchCount.textContent = '0';
                document.querySelector('.action-buttons').style.display = 'none';
                document.querySelector('.ath_form').style.display = 'none';
                return;
            }

            matchCount.textContent = matchesData.length;
            
            let html = '';
            matchesData.forEach((match, index) => {
                const datetime = match.datetime.replace('+03:00', '');
const time = new Date(datetime).toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
});
                
                html += `
                    <div class="match-card" data-index="${index}">
                        <div class="match-preview">
                            <div class="team-section">
                                <img src="${match.image1}" alt="${match.team1}">
                                <div class="team-name">${match.team1}</div>
                            </div>

                            <div class="match-details">
                                <div class="match-time">
                                    <i class="fa-solid fa-clock"></i>
                                    ${time}
                                </div>
                                <div class="tournament-name">${match.tournament}</div>
                                <div class="tv-channel">
                                    <i class="fa-solid fa-tv"></i>
                                    ${match.television}
                                </div>
                            </div>

                            <div class="team-section">
                                <img src="${match.image2}" alt="${match.team2}">
                                <div class="team-name">${match.team2}</div>
                            </div>
                        </div>

                        <div class="link-input-section">
                            <input 
                                type="text" 
                                class="link-input" 
                                placeholder="أدخل رابط البث (مثال: https://example.com/stream)"
                                value="${match.link !== 'YOURSTREAMLINK' ? match.link : ''}"
                                data-index="${index}"
                            >
                            <button class="delete-btn" onclick="deleteMatch(${index})">
                                <i class="fa-solid fa-trash"></i>
                                حذف
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function generateCode() {
            // Update links from inputs
            document.querySelectorAll('.link-input').forEach(input => {
                const index = parseInt(input.getAttribute('data-index'));
                const link = input.value.trim();
                matchesData[index].link = link || 'YOURSTREAMLINK';
            });

            // Send to server to generate code
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=generate_code&matches=' + encodeURIComponent(JSON.stringify(matchesData))
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('codeOutput').value = data.code;
                    document.getElementById('successMessage').style.display = 'block';
                    setTimeout(() => {
                        document.getElementById('successMessage').style.display = 'none';
                    }, 3000);
                }
            })
            .catch(error => {
                alert('حدث خطأ في توليد الكود');
                console.error('Error:', error);
            });
        }

        function copyCode() {
            const codeOutput = document.getElementById('codeOutput');
            codeOutput.select();
            document.execCommand('copy');
            
            const btn = event.target.closest('.copy-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> تم النسخ!';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        }

        // Update links in real-time
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('link-input')) {
                const index = parseInt(e.target.getAttribute('data-index'));
                matchesData[index].link = e.target.value.trim() || 'YOURSTREAMLINK';
            }
        });
    </script>
    <script>
document.querySelectorAll('.AlbaDate').forEach(el => {
    let timeText = el.innerText.trim(); // الوقت الأصلي (مثلاً 8:00 PM)

    if (!timeText) return;

    // تحويل الوقت لنفس اليوم حسب جهاز المستخدم
    let now = new Date();
    let dateString = now.toDateString() + " " + timeText;

    let localDate = new Date(dateString);

    if (!isNaN(localDate)) {
        let formatted = localDate.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });

        el.innerHTML = formatted;
    }
});
</script>
</body>
</html>
