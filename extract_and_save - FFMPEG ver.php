<?php
// extract_and_save.php

require 'config.php'; 
require_once('getid3-master/getid3/getid3.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_file'])) {
    
    $userId = 1; 
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

    // --- 2. FFMPEG COMPRESSION & OPTIMIZATION ---
    // Create a path for a compressed audio-only version of the file
    $compressedAudioPath = $uploadDir . uniqid('audio_proc_', true) . '.mp3';

    // FFmpeg command rules:
    // -i : Input file
    // -vn: Strip out the video completely (makes it tiny)
    // -acodec libmp3lame: Convert audio to standard MP3
    // -q:a 5: Sets a variable bitrate around 130kbps (perfect clarity for AI text/genre analysis)
    // -t 180: Truncates/cuts the file to the first 3 minutes (optional, but guarantees it stays small)
    $ffmpegCommand = sprintf(
        "ffmpeg -i %s -vn -acodec libmp3lame -q:a 5 -t 180 %s 2>&1",
        escapeshellarg($uploadDest),
        escapeshellarg($compressedAudioPath)
    );

    // Execute the compression command
    exec($ffmpegCommand, $output, $returnStatus);

    if ($returnStatus !== 0) {
        // If FFmpeg failed, clean up the original file and halt
        if (file_exists($uploadDest)) unlink($uploadDest);
        die("<h3 style='color:red;'>FFmpeg Compression Error. Ensure FFmpeg is installed on your server.</h3>");
    }

    // Double-check the newly compressed audio size
    $compressedSizeMB = round(filesize($compressedAudioPath) / 1048576, 2);
    if ($compressedSizeMB > 19) {
        if (file_exists($uploadDest)) unlink($uploadDest);
        if (file_exists($compressedAudioPath)) unlink($compressedAudioPath);
        die("<h3 style='color:red;'>Error: Even after compression, the audio track is over 20MB. Try uploading a shorter clip.</h3>");
    }

    // --- 3. LOCAL EXTRACTION (Duration via getID3) ---
    $getID3 = new getID3;
    $fileInfo = $getID3->analyze($uploadDest);
    $duration = $fileInfo['playtime_string'] ?? '0:00';

    // --- 4. AI EXTRACTION (Gemini 1.5 Flash via Google API) ---
    $apiKey = GEMINI_API_KEY;
    
    // Notice we pass the COMPRESSED audio data to the API, not the original heavy video file
    $audioData = base64_encode(file_get_contents($compressedAudioPath));
    $mimeType = "audio/mp3"; // Overriding mime type to match our processed file

    $promptText = "Analyze this audio file. Return a JSON object with exactly three keys: 'lyrics' (the transcribed text), 'genre' (the musical theme, e.g., 'Melancholy/Indie', 'Pop', 'Electronic'), and 'vocal_gender' ('Male', 'Female', or 'Instrumental').";

    $payload = json_encode([
        "contents" => [[
            "parts" => [
                ["text" => $promptText],
                ["inline_data" => ["mime_type" => $mimeType, "data" => $audioData]]
            ]
        ]],
        "generationConfig" => [
            "response_mime_type" => "application/json"
        ]
    ]);

    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=" . $apiKey;
    
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
    
    if (isset($responseData['error'])) {
        if (file_exists($compressedAudioPath)) unlink($compressedAudioPath);
        die("<h3 style='color:red;'>Gemini Error: " . htmlspecialchars($responseData['error']['message']) . "</h3>");
    }

    // Clean up the temporary compressed audio file now that the API request is done
    if (file_exists($compressedAudioPath)) unlink($compressedAudioPath);

    $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    $aiExtracted = json_decode($aiText, true);

    $lyrics = $aiExtracted['lyrics'] ?? 'Extraction failed or no vocals detected.';
    $genre = $aiExtracted['genre'] ?? 'Unknown';
    $vocalGender = $aiExtracted['vocal_gender'] ?? 'Unknown';
    $title = $_POST['title']; 

    // --- 5. ACID DATABASE TRANSACTION ---
    $conn->begin_transaction();

    try {
        // Save the ORIGINAL video path to the database asset logs
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
        echo "<div style='max-width: 650px; margin: 40px auto; padding: 25px; font-family: sans-serif; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid #1b73e8;'>";
        echo "<h2 style='color: #1b73e8; margin-top: 0; text-align: center;'>✨ Compressed Gemini Processing Complete</h2>";
        
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #e9ecef;'>";
        echo "<h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #bdc3c7; padding-bottom: 5px;'>System Metadata</h3>";
        echo "<p><strong>Original Size:</strong> $fileSizeMB MB | <strong>Sent to API (Audio Only):</strong> $compressedSizeMB MB | <strong>Duration:</strong> $duration</p>";
        echo "</div>";

        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #e9ecef;'>";
        echo "<h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #bdc3c7; padding-bottom: 5px;'>Gemini Audio Analysis</h3>";
        echo "<p><strong>Genre:</strong> $genre | <strong>Vocal Gender:</strong> $vocalGender</p>";
        echo "<strong>Transcribed Lyrics:</strong><blockquote style='background: #fff; padding: 10px; border-left: 4px solid #8e44ad;'>$lyrics</blockquote>";
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