<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LyricalLens - AI Ingestion</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 500px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { font-weight: bold; display: block; margin-bottom: 8px; color: #2c3e50; }
        input[type="text"], input[type="file"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #8e44ad; color: white; border: none; padding: 15px; width: 100%; font-size: 1.1em; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        button:hover { background: #9b59b6; }
    </style>
</head>
<body>

<div class="container">
    <h1 style="text-align: center; color: #8e44ad;">LyricalLens AI Ingestion</h1>
    <p style="text-align: center; color: #7f8c8d; margin-bottom: 30px;">Powered by getID3 & Gemma 4</p>
    
    <form action="extract_and_save.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="media_file">Upload Media File (.mp3, .mp4)</label>
            <input type="file" name="media_file" id="media_file" accept=".mp3, .mp4" required>
        </div>
        <div class="form-group">
            <label for="title">Track/Video Title</label>
            <input type="text" name="title" id="title" required placeholder="Enter the official title...">
        </div>
        <button type="submit">Analyze & Upload</button>
    </form>
</div>
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
    // Grab the upload form and listen for the submit button
    document.querySelector('form').addEventListener('submit', function() {
        // Show the loading screen with flexbox so it centers perfectly
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        // Optional: Disable the submit button so they don't click it twice
        let submitBtn = this.querySelector('button[type="submit"]');
        if(submitBtn) {
            submitBtn.style.opacity = '0.5';
            submitBtn.innerText = 'Uploading...';
        }
    });
</script>
</body>
</html>