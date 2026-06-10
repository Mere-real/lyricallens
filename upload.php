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

</body>
</html>