<?php
// dashboard.php
session_start();
require 'config.php';

// Secure the dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// ==========================================
// TAB 1: LYRICALLENS RETRIEVAL LOGIC
// ==========================================
$sql = "SELECT m.Title, m.File_Path, m.Format_Type, m.File_Size_MB, m.Upload_Date, 
               u.Username AS Uploader,
               t.Extracted_Lyrics, 
               a.Vocal_Gender, a.Genre, a.Audio_Duration
        FROM MEDIA_ASSETS m
        LEFT JOIN USERS u ON m.User_ID = u.User_ID
        LEFT JOIN TEXT_TRANSCRIPTS t ON m.Asset_ID = t.Asset_ID
        LEFT JOIN AUDIO_FEATURES a ON m.Asset_ID = a.Asset_ID
        WHERE 1=1";

$types = "";
$params = [];

if (!empty($_GET['format_type'])) {
    $sql .= " AND m.Format_Type = ?";
    $types .= "s";
    $params[] = $_GET['format_type'];
}
if (!empty($_GET['max_size'])) {
    $sql .= " AND m.File_Size_MB <= ?";
    $types .= "d";
    $params[] = $_GET['max_size'];
}
if (!empty($_GET['keyword'])) {
    $sql .= " AND t.Extracted_Lyrics LIKE ?";
    $types .= "s";
    $params[] = '%' . $_GET['keyword'] . '%';
}
if (!empty($_GET['vocal_gender'])) {
    $sql .= " AND a.Vocal_Gender = ?";
    $types .= "s";
    $params[] = $_GET['vocal_gender'];
}
if (!empty($_GET['genre'])) {
    $sql .= " AND a.Genre = ?";
    $types .= "s";
    $params[] = $_GET['genre'];
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$search_result = $stmt->get_result();

// ==========================================
// TAB 2: MMDB MEDIAS LOGIC
// ==========================================
$vstu_data = [];
$vstu_error = null;

try {
    $check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'mmdb2026' AND table_name = 'vstu'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result && $check_result->fetch_row()[0] > 0) {
        $vstu_sql = "SELECT * FROM `mmdb2026`.`vstu`";
        $vstu_result = $conn->query($vstu_sql);
        
        if ($vstu_result) {
            while ($row = $vstu_result->fetch_assoc()) {
                $vstu_data[] = $row;
            }
        }
    } else {
        $vstu_error = "The table 'vstu' is missing from the 'mmdb2026' database.";
    }
} catch (Throwable $e) { 
    $vstu_error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LyricalLens</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { max-width: 1300px; margin: auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        
        .header-actions { text-align: center; margin-bottom: 25px; }
        .btn-upload { display: inline-block; background-color: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; transition: background-color 0.3s ease; }
        .btn-upload:hover { background-color: #27ae60; }
        .btn-logout { display: inline-block; background-color: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-left: 10px; transition: background-color 0.3s ease; }
        .btn-logout:hover { background-color: #c0392b; }

        .tabs { display: flex; border-bottom: 2px solid #ecf0f1; margin-bottom: 25px; }
        .tab-btn { background: none; border: none; padding: 15px 25px; font-size: 1.1em; cursor: pointer; color: #7f8c8d; border-bottom: 3px solid transparent; transition: all 0.3s ease; font-weight: bold; }
        .tab-btn:hover { color: #3498db; background-color: #f8f9fa; }
        .tab-btn.active { color: #2c3e50; border-bottom-color: #3498db; background-color: transparent; }
        
        .tab-content { display: none; animation: fadeIn 0.4s; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .search-section { border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 20px; border-radius: 5px; background: #fafafa; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button[type="submit"] { background-color: #3498db; color: white; border: none; padding: 12px; width: 100%; font-size: 1.1em; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        button[type="submit"]:hover { background-color: #2980b9; }

        .results-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .tag { background: #3498db; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; margin-right: 5px; display: inline-block;}
        
        /* Table & AI Button Styles */
        .table-responsive { overflow-x: auto; margin-top: 10px; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; font-size: 0.9em; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: middle; }
        .data-table th { background-color: #f8f9fa; color: #2c3e50; font-weight: bold; }
        .data-table tr:nth-child(even) { background-color: #f9f9f9; }
        .data-table tr:hover { background-color: #f1f4f6; }
        
        .btn-ai { background-color: #9b59b6; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: bold; transition: background 0.3s; margin-top: 5px; display: block; width: 100%; text-align: center; }
        .btn-ai:hover { background-color: #8e44ad; }
        .view-link { color: #3498db; text-decoration: none; font-weight: bold; font-size: 0.9em; display: block; margin-bottom: 5px; }
        .view-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h1 style="text-align: center; color: #2c3e50; margin-bottom: 10px;">LyricalLens Dashboard</h1>
    
    <div class="header-actions">
        <p style="margin-bottom: 15px; color: #7f8c8d;">
            Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </p>
        <a href="upload.php" class="btn-upload">➕ Upload New Media</a>
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
    
    <!-- Tab Navigation -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab(event, 'tab-search')">Database Engine</button>
        <button class="tab-btn" onclick="switchTab(event, 'tab-vstu')">MMDB Medias</button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: SEARCH PORTAL CONTENT (RESTORED)    -->
    <!-- ========================================== -->
    <div id="tab-search" class="tab-content active">
        <form action="dashboard.php" method="GET">
            <div class="search-section">
                <h3 style="color: #3498db; margin-top: 0;">Filters</h3>
                <div class="grid-2">
                    <div>
                        <label>Format (ABR)</label>
                        <select name="format_type">
                            <option value="">Any Format</option>
                            <option value=".mp4" <?php if(($_GET['format_type']??'') == '.mp4') echo 'selected'; ?>>Video (.mp4)</option>
                            <option value=".mp3" <?php if(($_GET['format_type']??'') == '.mp3') echo 'selected'; ?>>Audio (.mp3)</option>
                        </select>
                    </div>
                    <div>
                        <label>Max Size MB (ABR)</label>
                        <input type="number" step="0.01" name="max_size" value="<?php echo htmlspecialchars($_GET['max_size'] ?? ''); ?>" placeholder="e.g., 20.00">
                    </div>
                    <div>
                        <label>Keyword in Lyrics (TBR)</label>
                        <input type="text" name="keyword" value="<?php echo htmlspecialchars($_GET['keyword'] ?? ''); ?>" placeholder="e.g., 'melancholy'">
                    </div>
                    <div>
                        <label>Vocal Gender (CBR)</label>
                        <select name="vocal_gender">
                            <option value="">Any</option>
                            <option value="Male" <?php if(($_GET['vocal_gender']??'') == 'Male') echo 'selected'; ?>>Male</option>
                            <option value="Female" <?php if(($_GET['vocal_gender']??'') == 'Female') echo 'selected'; ?>>Female</option>
                            <option value="Instrumental" <?php if(($_GET['vocal_gender']??'') == 'Instrumental') echo 'selected'; ?>>Instrumental</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit">Execute Search Query</button>
            <a href="dashboard.php" style="display:block; text-align:center; margin-top:10px; color:#7f8c8d; text-decoration:none;">Clear Filters</a>
        </form>

        <h3 style="margin-top: 30px;">Retrieval Results (<?php echo $search_result->num_rows; ?> found)</h3>
        
        <?php if ($search_result->num_rows > 0): ?>
            <div class="results-grid">
                <?php while($row = $search_result->fetch_assoc()): ?>
                    <div class="card">
                        <h3 style="margin-top: 0; color: #2c3e50;"><?php echo htmlspecialchars($row['Title']); ?></h3>
                        
                        <div style="margin-bottom: 15px;">
                            <?php if (!empty($row['File_Path']) && file_exists($row['File_Path'])): ?>
                                <?php if ($row['Format_Type'] === '.mp4'): ?>
                                    <video width="100%" controls style="border-radius: 4px; background: #000; max-height: 200px;">
                                        <source src="<?php echo htmlspecialchars($row['File_Path']); ?>" type="video/mp4">
                                    </video>
                                <?php elseif ($row['Format_Type'] === '.mp3'): ?>
                                    <audio controls style="width: 100%;">
                                        <source src="<?php echo htmlspecialchars($row['File_Path']); ?>" type="audio/mpeg">
                                    </audio>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="padding: 10px; background: #ffeaa7; color: #d35400; border-radius: 4px; font-size: 0.9em;">
                                    ⚠️ Media file not found on server.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom: 10px;">
                            <span class="tag"><?php echo htmlspecialchars($row['Format_Type']); ?></span>
                            <span class="tag"><?php echo htmlspecialchars($row['File_Size_MB']); ?> MB</span>
                            <span class="tag"><?php echo htmlspecialchars($row['Genre'] ?? 'No Genre'); ?></span>
                            <span class="tag"><?php echo htmlspecialchars($row['Vocal_Gender'] ?? 'No Vocal Data'); ?></span>
                        </div>

                        <p style="font-size: 0.85em; color: #555; background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 3px solid #3498db;">
                            <strong>Transcript Snippet:</strong><br>
                            <?php 
                                $lyrics = $row['Extracted_Lyrics'];
                                echo !empty($lyrics) ? htmlspecialchars(substr($lyrics, 0, 100)) . '...' : '<em>No transcript available.</em>';
                            ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p style="color: #e74c3c;">No media assets match your query parameters.</p>
        <?php endif; ?>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: MMDB MEDIAS (STRUCTURED VSTU VIEW) -->
    <!-- ========================================== -->
    <div id="tab-vstu" class="tab-content">
        <?php if ($vstu_error): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; border: 1px solid #f5c6cb; margin-top: 10px;">
                <strong>⚠️ Connection Error:</strong><br><?php echo htmlspecialchars($vstu_error); ?>
            </div>
            
        <?php elseif (!empty($vstu_data)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student Info</th>
                            <th>Motto</th>
                            <th>Document / Photo</th>
                            <th>Audio Analysis</th>
                            <th>Video Analysis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vstu_data as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['matric_no']); ?></strong><br>
                                    <?php echo htmlspecialchars($row['full_name']); ?><br>
                                    <span style="color: #7f8c8d; font-size: 0.9em;">Group: <?php echo htmlspecialchars($row['group_no']); ?> | <?php echo htmlspecialchars($row['phone_no']); ?></span>
                                </td>
                                <td style="font-style: italic; color: #555;">
                                    "<?php echo htmlspecialchars($row['life_motto']); ?>"
                                </td>
                                <td>
                                    <?php if(!empty($row['photoStu'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['photoStu']); ?>" target="_blank" class="view-link">📷 View Photo</a>
                                    <?php endif; ?>
                                    <?php if(!empty($row['docStu'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['docStu']); ?>" target="_blank" class="view-link">📄 View Doc</a>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- AUDIO COLUMN -->
                                <td>
                                    <?php if(!empty($row['audioStu'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['audioStu']); ?>" target="_blank" class="view-link">🔊 Play Audio</a>
                                        <form action="analyze_mmdb.php" method="POST" class="analyze-form">
                                            <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($row['audioStu']); ?>">
                                            <input type="hidden" name="title" value="Audio: <?php echo htmlspecialchars($row['matric_no']); ?> - <?php echo htmlspecialchars($row['full_name']); ?>">
                                            <button type="submit" class="btn-ai">✨ Analyze via AI</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #ccc;">No File</span>
                                    <?php endif; ?>
                                </td>

                                <!-- VIDEO COLUMN -->
                                <td>
                                    <?php if(!empty($row['videoStu'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['videoStu']); ?>" target="_blank" class="view-link">🎬 Play Video</a>
                                        <form action="analyze_mmdb.php" method="POST" class="analyze-form">
                                            <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($row['videoStu']); ?>">
                                            <input type="hidden" name="title" value="Video: <?php echo htmlspecialchars($row['matric_no']); ?> - <?php echo htmlspecialchars($row['full_name']); ?>">
                                            <button type="submit" class="btn-ai">✨ Analyze via AI</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #ccc;">No File</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: #7f8c8d;">No records found in the MMDB database.</p>
        <?php endif; ?>
    </div>
</div>

<!-- LOADING OVERLAY -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.95); z-index:9999; flex-direction:column; justify-content:center; align-items:center;">
    <div style="border: 8px solid #f3f3f3; border-top: 8px solid #9b59b6; border-radius: 50%; width: 70px; height: 70px; animation: spin 1.5s linear infinite;"></div>
    <h2 style="color: #2c3e50; margin-top:25px;">Connecting to Gemini AI...</h2>
    <p style="color: #7f8c8d; font-size: 1.1em;">Analyzing MMDB media file. This may take a few minutes.</p>
    <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
</div>

<!-- Scripts -->
<script>
function switchTab(evt, tabName) {
    let tabContents = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabContents.length; i++) { tabContents[i].classList.remove("active"); }
    let tabBtns = document.getElementsByClassName("tab-btn");
    for (let i = 0; i < tabBtns.length; i++) { tabBtns[i].classList.remove("active"); }
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.has('format_type') || urlParams.has('keyword') || urlParams.has('max_size') || urlParams.has('vocal_gender') || urlParams.has('genre')) {
        document.querySelector("button[onclick=\"switchTab(event, 'tab-search')\"]").click();
    }

    // Attach loading screen to all Analyze buttons
    const analyzeForms = document.querySelectorAll('.analyze-form');
    analyzeForms.forEach(form => {
        form.addEventListener('submit', function() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        });
    });
});
</script>

</body>
</html>

<?php 
if (isset($stmt)) $stmt->close();
$conn->close(); 
?>