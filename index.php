<?php
// index.php
require 'config.php';

// Initialize the base query with JOINs
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

// ABR Logic
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

// TBR Logic
if (!empty($_GET['keyword'])) {
    $sql .= " AND t.Extracted_Lyrics LIKE ?";
    $types .= "s";
    $params[] = '%' . $_GET['keyword'] . '%';
}

// CBR Logic
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
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LyricalLens Search Portal</title>
    <style>
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
        
        /* New styling for the upload button */
        .btn-upload { display: inline-block; background-color: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-bottom: 20px; transition: background-color 0.3s ease; }
        .btn-upload:hover { background-color: #27ae60; }
        .header-actions { text-align: center; margin-bottom: 25px; }
    </style>
</head>
<body>

<div class="container">
    <h1 style="text-align: center; color: #2c3e50; margin-bottom: 10px;">LyricalLens Database Engine</h1>
    
    <div class="header-actions">
        <a href="upload.php" class="btn-upload">➕ Upload New Media</a>
    </div>
    
    <form action="index.php" method="GET">
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
        <a href="index.php" style="display:block; text-align:center; margin-top:10px; color:#7f8c8d; text-decoration:none;">Clear Filters</a>
    </form>

    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

    <h2>Retrieval Results (<?php echo $result->num_rows; ?> found)</h2>
    
    <?php if ($result->num_rows > 0): ?>
        <div class="results-grid">
            <?php while($row = $result->fetch_assoc()): ?>
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

</body>
</html>

<?php 
$stmt->close();
$conn->close(); 
?>