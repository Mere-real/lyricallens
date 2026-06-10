<?php
// extract_and_save.php
require 'config.php'; 
require_once('getid3-master/getid3/getid3.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_file'])) {
    
    $stmt1 = null; $stmt2 = null; $stmt3 = null; $uploadDest = null;

    // --- 1. PHYSICAL FILE HANDLING ---
    $file = $_FILES['media_file'];
    if ($file['error'] !== 0) die("<h3 style='color:red;'>Upload Error Code: " . $file['error'] . "</h3>");

    $fileSizeMB = round($file['size'] / 1048576, 2); 
    $formatType = '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)); 

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true); 

    $uploadDest = $uploadDir . uniqid('media_', true) . $formatType;
    if (!move_uploaded_file($file['tmp_name'], $uploadDest)) die("Failed to move file.");

    // --- 2. LOCAL EXTRACTION (Duration via getID3) ---
    $getID3 = new getID3;
    $fileInfo = $getID3->analyze($uploadDest);
    $duration = $fileInfo['playtime_string'] ?? '0:00';

    // --- 3. AI EXTRACTION (Gemma 4 via Google API) ---
    $apiKey = GEMINI_API_KEY;;
    $audioData = base64_encode(file_get_contents($uploadDest));
    $mimeType = mime_content_type($uploadDest);

    $promptText = "Analyze this media file. Return exactly a JSON object with three keys: 'lyrics' (the transcribed text), 'genre' (the musical theme, e.g., 'Melancholy/Indie', 'Pop', 'Electronic'), and 'vocal_gender' ('Male', 'Female', or 'Instrumental'). Return ONLY valid JSON, do not include markdown formatting like ```json.";

    $payload = json_encode([
        "contents" => [[
            "parts" => [
                ["text" => $promptText],
                ["inline_data" => ["mime_type" => $mimeType, "data" => $audioData]]
            ]
        ]]
    ]);

    $endpoint = "[https://generativelanguage.googleapis.com/v1beta/models/gemma-4-12b-it:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemma-4-12b-it:generateContent?key=)" . $apiKey;
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) die("<h3 style='color:red;'>API Error: " . curl_error($ch) . "</h3>");
    curl_close($ch);

    $responseData = json_decode($response, true);
    $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    // Clean potential markdown from API response
    $aiText = str_replace(['```json', '```'], '', $aiText); 
    $aiExtracted = json_decode(trim($aiText), true);

    $lyrics = $aiExtracted['lyrics'] ?? 'Extraction failed or no vocals detected.';
    $genre = $aiExtracted['genre'] ?? 'Unknown';
    $vocalGender = $aiExtracted['vocal_gender'] ?? 'Unknown';
    $title = $_POST['title']; 
    $userId = 1; 

    // --- 4. ACID DATABASE TRANSACTION ---
    $conn->begin_transaction();

    try {
        $stmt1 = $conn->prepare("INSERT INTO MEDIA_ASSETS (User_ID, Title, File_Path, Format_Type, File_Size_MB) VALUES (?, ?, ?, ?, ?)");
        $stmt1->bind_param("isssd", $userId, $title, $uploadDest, $formatType, $fileSizeMB);
        $stmt1->execute();
        $assetId = $conn->insert_id; 

        $stmt2 = $conn->prepare("INSERT INTO TEXT_TRANSCRIPTS (Asset_ID, Extracted_Lyrics, Theme_Tag) VALUES (?, ?, ?)");
        $stmt2->bind_param("iss", $assetId, $lyrics, $genre);
        $stmt2->execute();

        $language = "English"; 
        $stmt3 = $conn->prepare("INSERT INTO AUDIO_FEATURES (Asset_ID, Audio_Duration, Vocal_Gender, Genre, Language) VALUES (?, ?, ?, ?, ?)");
        $stmt3->bind_param("issss", $assetId, $duration, $vocalGender, $genre, $language);
        $stmt3->execute();

        $conn->commit();

        // --- SUCCESS UI ---
        echo "<div style='max-width: 650px; margin: 40px auto; padding: 25px; font-family: sans-serif; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid #8e44ad;'>";
        echo "<h2 style='color: #8e44ad; margin-top: 0; text-align: center;'>✨ AI Extraction Complete</h2>";
        
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #e9ecef;'>";
        echo "<h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #bdc3c7; padding-bottom: 5px;'>System Metadata (ABR)</h3>";
        echo "<p><strong>Title:</strong> $title | <strong>Size:</strong> $fileSizeMB MB | <strong>Duration:</strong> $duration</p>";
        echo "</div>";

        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #e9ecef;'>";
        echo "<h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #bdc3c7; padding-bottom: 5px;'>Gemma 4 Analysis (CBR & TBR)</h3>";
        echo "<p><strong>Genre:</strong> $genre | <strong>Vocal Gender:</strong> $vocalGender</p>";
        echo "<strong>Extracted Lyrics:</strong><blockquote style='background: #fff; padding: 10px; border-left: 4px solid #8e44ad;'>$lyrics</blockquote>";
        echo "</div>";

        echo "<div style='display: flex; gap: 10px;'>";
        echo "<a href='upload.php' style='flex: 1; text-align: center; background: #95a5a6; color: white; padding: 12px; text-decoration: none; border-radius: 4px;'>Upload Another</a>";
        echo "<a href='index.php' style='flex: 1; text-align: center; background: #3498db; color: white; padding: 12px; text-decoration: none; border-radius: 4px;'>Search Database →</a>";
        echo "</div></div>";

    } catch (Exception $e) {
        $conn->rollback();
        if ($uploadDest && file_exists($uploadDest)) unlink($uploadDest);
        echo "<h2 style='color: red; text-align: center;'>⚠️ Transaction Failed: " . $e->getMessage() . "</h2>";
    }

    if ($stmt1) $stmt1->close();
    if ($stmt2) $stmt2->close();
    if ($stmt3) $stmt3->close();
}
$conn->close();
?>