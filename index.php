<?php
session_start();

// Database connections
require_once 'config.php';
// include_once '../../db.php'; // Uncomment if config.php doesn't cover the groupdb connection

// ==========================================
// 1. GROUP MEMBER LOGIC
// ==========================================
if (!isset($_GET['group'])) {
    $group = basename(dirname(__FILE__));
} else {
    $group = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['group']);
}

$members = [];
$sql_group = "SELECT S.full_name, S.matric_no FROM stu S 
              JOIN groupdb G ON S.group_no = G.groupID 
              WHERE G.groupID = ?";

if ($stmt_group = $conn->prepare($sql_group)) {
    $stmt_group->bind_param("s", $group);
    $stmt_group->execute();
    $result_group = $stmt_group->get_result();
    
    while ($row = $result_group->fetch_assoc()) {
        $members[] = $row;
    }
    $stmt_group->close();
}

// ==========================================
// 2. MEDIA ASSETS SEARCH LOGIC
// ==========================================
$sql_media = "SELECT m.Title, m.File_Path, m.Format_Type, m.File_Size_MB, m.Upload_Date, 
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

// ABR Logic
if (!empty($_GET['format_type'])) {
    $sql_media .= " AND m.Format_Type = ?";
    $types .= "s";
    $params[] = $_GET['format_type'];
}
if (!empty($_GET['max_size'])) {
    $sql_media .= " AND m.File_Size_MB <= ?";
    $types .= "d";
    $params[] = $_GET['max_size'];
}

// TBR Logic
if (!empty($_GET['keyword'])) {
    $sql_media .= " AND t.Extracted_Lyrics LIKE ?";
    $types .= "s";
    $params[] = '%' . $_GET['keyword'] . '%';
}

// CBR Logic
if (!empty($_GET['vocal_gender'])) {
    $sql_media .= " AND a.Vocal_Gender = ?";
    $types .= "s";
    $params[] = $_GET['vocal_gender'];
}
if (!empty($_GET['genre'])) {
    $sql_media .= " AND a.Genre = ?";
    $types .= "s";
    $params[] = $_GET['genre'];
}

$stmt_media = $conn->prepare($sql_media);
if (!empty($params)) {
    $stmt_media->bind_param($types, ...$params);
}
$stmt_media->execute();
$result_media = $stmt_media->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LyricalLens Search Portal & Group Roster</title>
    <style>
        /* Base LyricalLens Styles */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .search-section { border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 20px; border-radius: 5px; background: #fafafa; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #3498db; color: white; border: none; padding: 12px; width: 100%; font-size: 1.1em; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        button:hover { background-color: #2980b9; }
        .results-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .tag { background: #3498db; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; margin-right: 5px; display: inline-block;}
        .btn-upload { display: inline-block; background-color: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-bottom: 20px; transition: background-color 0.3s ease; }
        .btn-upload:hover { background-color: #27ae60; }
        .header-actions { text-align: center; margin-bottom: 25px; }

        /* Group Member Table Styles (Scoped to prevent overriding the main theme) */
        .group-container { background: #0f0f0f; color: white; padding: 30px; border-radius: 8px; margin-bottom: 40px; }
        .group-container .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
        .group-container h2 { margin: 0; font-size: 1.8rem; }
        .group-container .group-badge { border: 1px solid #00d2ff; padding: 8px 20px; font-size: 1.2rem; border-radius: 5px; font-weight: bold; }
        .group-container .table-container { border: 1px solid #444; border-radius: 12px; overflow: hidden; background: rgba(255,255,255,0.02); }
        .group-container table { width: 100%; border-collapse: collapse; text-align: left; }
        .group-container th, .group-container td { padding: 15px 20px; border-bottom: 1px solid #333; font-size: 1.1rem; }
        .group-container th { background: #161616; color: #00d2ff; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; }
        .group-container tr:last-child td { border-bottom: none; }
        .group-container tr:hover { background: rgba(255,255,255,0.04); transition: 0.2s; }
        .group-container .text-break { word-break: break-all; line-height: 1.5; font-weight: 500; }
        .group-container .matrix-code { color: #00d2ff; font-weight: bold; font-family: monospace; font-size: 1.2rem; }
        .group-container .bil-col { font-size: 1.2rem; font-weight: bold; }
        .group-container .btn-back { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; background: #555; color: white; transition: 0.3s; margin-top: 20px; font-size: 1rem; }
        .group-container .btn-back:hover { background: #666; }
    </style>
</head>
<body>

<div class="container">
    
    <div class="group-container">
        <div class="header">
            <h2>SENARAI AHLI KUMPULAN</h2>
            <div class="group-badge">
                GROUP: <?php echo htmlspecialchars($group); ?>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">BIL</th>
                        <th>NAMA PENUH</th>
                        <th style="width: 250px;">NO. MATRIK</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: #ff4444; padding: 30px; font-size: 1.2rem;">
                                Tiada data ahli kumpulan ditemui untuk kod group "<?php echo htmlspecialchars($group); ?>".
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $index => $row): ?>
                            <tr>
                                <td class="bil-col"><?php echo $index + 1; ?></td>
                                <td class="text-break" style="text-transform: uppercase;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="matrix-code"><?php echo htmlspecialchars($row['matric_no'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="../../dashboard.php?group=<?php echo urlencode($group); ?>" class="btn-back">BACK TO DASHBOARD</a>
    </div>

    <h1 style="text-align: center; color: #2c3e50; margin-bottom: 10px;">LyricalLens Database Engine</h1>
    
    <div class="header-actions">
        <a href="lyricallens/upload.php" class="btn-upload">➕ Upload New Media</a>
    </div>
    
    <form action="index.php" method="GET">
        <input type="hidden" name="group" value="<?php echo htmlspecialchars($group); ?>">
        
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
        <a href="index.php?group=<?php echo urlencode($group); ?>" style="display:block; text-align:center; margin-top:10px; color:#7f8c8d; text-decoration:none;">Clear Filters</a>
    </form>

    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

    <h2>Retrieval Results (<?php echo $result_media->num_rows; ?> found)</h2>
    
    <?php if ($result_media->num_rows > 0): ?>
        <div class="results-grid">
            <?php while($row = $result_media->fetch_assoc()): ?>
                <div class="card">
                    <h3 style="margin-top: 0; color: #2c3e50;">
                        <?php echo htmlspecialchars($row['Title']); ?> 
                    </h3>
                    
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
                            <div style="padding: 10px; background: #ffeaa7; color: #d35400; border-radius