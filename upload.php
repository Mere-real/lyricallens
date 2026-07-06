<?php
// upload.php
session_start();

// Secure the page: redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Media - LyricalLens</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .upload-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 500px; position: relative; }
        .upload-card h2 { text-align: center; color: #2c3e50; margin-top: 0; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: bold; color: #34495e; display: block; margin-bottom: 8px; }
        input[type="text"], input[type="file"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        button[type="submit"] { background-color: #2ecc71; color: white; border: none; padding: 12px; width: 100%; font-size: 1.1em; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.3s; }
        button[type="submit"]:hover { background-color: #27ae60; }
        
        /* Back Button Styles */
        .btn-back { display: inline-block; background-color: #95a5a6; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-bottom: 20px; transition: background-color 0.3s ease; }
        .btn-back:hover { background-color: #7f8c8d; }
    </style>
</head>
<body>

<div class="upload-card">
    <!-- BACK BUTTON -->
    <a href="dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
    
    <h2>Upload New Media</h2>
    
    <!-- ENCTYPE IS CRITICAL FOR FILE UPLOADS -->
    <form action="extract_and_save.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Media Title</label>
            <input type="text" id="title" name="title" required placeholder="Enter a title for this file">
        </div>
        
        <div class="form-group">
            <label for="media_file">Select Audio or Video File</label>
            <input type="file" id="media_file" name="media_file" accept="audio/*,video/*" required>
        </div>
        
        <button type="submit">Upload & Analyze</button>
    </form>
</div>

<!-- LOADING OVERLAY (Hidden by default) -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.95); z-index:9999; flex-direction:column; justify-content:center; align-items:center;">
    
    <!-- CSS Spinner -->
    <div style="border: 8px solid #f3f3f3; border-top: 8px solid #3498db; border-radius: 50%; width: 70px; height: 70px; animation: spin 1.5s linear infinite;"></div>
    
    <h2 style="color: #2c3e50; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin-top:25px;">Please wait, this might take some time...</h2>
    <p style="color: #7f8c8d; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 1.1em;">Extracting metadata and analyzing audio with Gemini AI.</p>
    
    <style>
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</div>

<!-- JAVASCRIPT TRIGGER -->
<script>
    // Grab the upload form and listen for the submit event
    document.querySelector('form').addEventListener('submit', function() {
        // Show the loading screen
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        // Disable the submit button to prevent duplicate uploads
        let submitBtn = this.querySelector('button[type="submit"]');
        if(submitBtn) {
            submitBtn.style.opacity = '0.5';
            submitBtn.style.pointerEvents = 'none'; // Prevents a second click
            submitBtn.innerText = 'Uploading...';
        }
    });
</script>

</body>
</html>