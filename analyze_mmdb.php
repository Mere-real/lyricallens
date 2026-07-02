<?php
// analyze_mmdb.php
session_start();
require 'config.php';
require_once('getid3-master/getid3/getid3.php');

set_time_limit(0); 

if (!isset($_SESSION['user_id'])) {
    die("<h3 style='color:red;'>Access Denied: You must be logged in.</h3>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_path'])) {
    
    $userId = $_SESSION['user_id']; 
    $uploadDest = $_POST['file_path']; // The existing path (e.g. uploads/1779151815_AUDIO...)
    $title = $_POST['title'];

    // 1. Verify file actually exists on the server
    if (!file_exists($uploadDest)) {
        die("<h3 style='color:red;'>Error: The requested file could not be found on the server at path: " . htmlspecialchars($uploadDest) . "</h3>");
    }

    // 2. Local Extraction
    $fileSizeMB = round(filesize($uploadDest) / 1048576, 2); 
    $formatType = '.' . strtolower(pathinfo($uploadDest, PATHINFO_EXTENSION)); 
    $mimeType = mime_content_type($uploadDest);

    $getID3 = new getID3;
    $fileInfo = $getID3->analyze($uploadDest);
    $duration = $fileInfo['playtime_string'] ?? '0:00';

    // 3. AI EXTRACTION STEP 1: UPLOAD TO GEMINI
    $ch1 = curl_init("https://generativelanguage.googleapis.com/upload/v1beta/files?key=" . GEMINI_API_KEY);
    
    curl_setopt_array($ch1, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_POSTFIELDS => file_get_contents($uploadDest),
        CURLOPT_HTTPHEADER => [
            "X-Goog-Upload-Protocol: raw",
            "Content-Type: " . $mimeType
        ]
    ]);
    
    $uploadResponse = curl_exec($ch1);
    if (curl_errno($ch1)) die("<h3 style='color:red;'>Upload API Error: " . curl_error($ch1) . "</h3>");
    curl_close($ch1);

    $uploadData = json_decode($uploadResponse, true);
    if (isset($uploadData['error'])) {
        die("<h3 style='color:red;'>Gemini Upload Error: " . htmlspecialchars($uploadData['error']['message']) . "</h3>");
    }

    $fileUri = $uploadData['file']['uri'] ?? null;
    $fileName = $uploadData['file']['name'] ?? null;
    if (!$fileUri || !$fileName) die("<h3 style='color:red;'>Failed to retrieve File details from Gemini.</h3>");

    // 4. AI EXTRACTION STEP 2: POLL FOR "ACTIVE" STATE
    $isActive = false;
    $maxAttempts = 15; 
    $attempts = 0;

    while (!$isActive && $attempts < $maxAttempts) {
        $chCheck = curl_init("https://generativelanguage.googleapis.com/v1beta/" . $fileName . "?key=" . GEMINI_API_KEY);
        curl_setopt($chCheck, CURLOPT_RETURNTRANSFER, true);
        $checkResponse = curl_exec($chCheck);
        curl_close($chCheck);

        $checkData = json_decode($checkResponse, true);
        $state = $checkData['state'] ?? 'FAILED';

        if ($state === 'ACTIVE') {
            $isActive = true;
        } elseif ($state === 'FAILED') {
            die("<h3 style='color:red;'>Gemini Processing Error: Google failed to process the media file.</h3>");
        } else {
            sleep(5);
            $attempts++;
        }
    }

    if (!$isActive) {
        die("<h3 style='color:red;'>Timeout Error: File took too long to process on Google's servers.</h3>");
    }

    // 5. AI EXTRACTION STEP 3: ANALYZE THE UPLOADED FILE
    $promptText = "Analyze this media file. Return a JSON object with exactly three keys: 'lyrics' (the transcribed text or speech), 'genre' (the musical/spoken theme), and 'vocal_gender' ('Male', 'Female', or 'Instrumental').";

    $payload = json_encode([
        "contents" => [[
            "parts" => [
                ["text" => $promptText],
                ["file_data" => ["mime_type" => $mimeType, "file_uri" => $fileUri]]
            ]
        ]],
        "generationConfig" => [
            "response_mime_type" => "application/json"
        ]
    ]);

    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=" . GEMINI_API_KEY;
    
    $ch2 = curl_init($endpoint);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    
    $response = curl_exec($ch2);
    if (curl_errno($ch2)) die("<h3 style='color:red;'>Analysis API Error: " . curl_error($ch2) . "</h3>");
    curl_close($ch2);

    $responseData = json_decode($response, true);
    if (isset($responseData['error'])) {
        die("<h3 style='color:red;'>Gemini Analysis Error: " . htmlspecialchars($responseData['error']['message']) . "</h3>");
    }

    $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    $aiExtracted = json_decode($aiText, true);

    $lyrics = $aiExtracted['lyrics'] ?? 'Extraction failed or no vocals detected.';
    $genre = $aiExtracted['genre'] ?? 'Unknown';
    $vocalGender = $aiExtracted['vocal_gender'] ?? 'Unknown';

    // 6. ACID DATABASE TRANSACTION
    $conn->begin_transaction();
    $stmt1 = null; $stmt2 = null; $stmt3 = null;

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

        // SUCCESS UI
        echo "<div style='max-width: 650px; margin: 40px auto; padding: 25px; font-family: sans-serif; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid #9b59b6;'>";
        echo "<h2 style='color: #9b59b6; margin-top: 0; text-align: center;'>✨ MMDB Extraction Complete</h2>";
        
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #e9ecef;'>";
        echo "<h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #bdc3c7; padding-bottom: 5px;'>System Metadata</h3>";
        echo "<p><strong>Title:</strong> $title | <strong>Size:</strong> $fileSizeMB MB | <strong>Duration:</strong> $duration</p>";
        echo "</div>";

        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #e9ecef;'>";
        echo "<h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #bdc3c7; padding-bottom: 5px;'>Gemini Audio Analysis</h3>";
        echo "<p><strong>Genre:</strong> $genre | <strong>Vocal Gender:</strong> $vocalGender</p>";
        echo "<strong>Transcribed Lyrics:</strong><blockquote style='background: #fff; padding: 10px; border-left: 4px solid #8e44ad;'>$lyrics</blockquote>";
        echo "</div>";

        echo "<div style='text-align: center;'>";
        echo "<a href='dashboard.php' style='display: inline-block; background: #3498db; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; font-weight: bold;'>Return to Dashboard</a>";
        echo "</div></div>";

    } catch (Exception $e) {
        $conn->rollback();
        echo "<h2 style='color: red; text-align: center;'>⚠️ Transaction Failed: " . $e->getMessage() . "</h2>";
    }

    if ($stmt1) $stmt1->close();
    if ($stmt2) $stmt2->close();
    if ($stmt3) $stmt3->close();
}
$conn->close();
?>