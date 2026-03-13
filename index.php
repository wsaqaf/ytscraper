<?php

require_once "ytscraper.php";

define("UPLOAD_FOLDER", __DIR__ . "/UPLOAD_FOLDER/");

if (!is_dir(UPLOAD_FOLDER)) {
    mkdir(UPLOAD_FOLDER, 0777, true);
}

$message = "";
$processed = false;
$videosFile = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["fileToUpload"])) {
    $originalName = pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_FILENAME);
    $sanitizedName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $originalName);

    $targetFile = UPLOAD_FOLDER . $sanitizedName . ".har";

    if ($_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = $_FILES['fileToUpload']['error'];
        $message = "Upload failed with error code: " . $uploadError;
    }
    elseif (!is_writable(UPLOAD_FOLDER)) {
        $message = "Error: UPLOAD_FOLDER is not writable. Check permissions.";
    }
    else {
        $uploadedTmpParams = $_FILES["fileToUpload"]["tmp_name"];
        $shouldProcess = true;

        if (file_exists($targetFile)) {
            $existingHash = md5_file($targetFile);
            $newHash = md5_file($uploadedTmpParams);

            if ($existingHash === $newHash) {
                // Find existing CSV
                $existingVideos = glob(UPLOAD_FOLDER . $sanitizedName . '_videos_*.csv');
                if ($existingVideos) {
                    usort($existingVideos, function ($a, $b) {
                        return filemtime($b) - filemtime($a); });
                    $videosFile = basename($existingVideos[0]);
                    $message = "File with identical content already exists. Showing existing results.";
                    $processed = true;
                    $shouldProcess = false;
                }
            }
        }

        if ($shouldProcess) {
            if (move_uploaded_file($uploadedTmpParams, $targetFile)) {
                try {
                    $engine = new YTScraperEngine(UPLOAD_FOLDER);
                    $result = $engine->processHar($targetFile);

                    if ($result && $result['count'] > 0) {
                        $videosFile = $result['videos'];
                        $message = "File processed successfully! Found " . $result['count'] . " videos.";
                        $processed = true;
                    }
                    else {
                        $message = "Error: No videos found or parsing failed. The file might be corrupted or format changed.";
                    }
                }
                catch (Exception $e) {
                    $message = "System Error: " . $e->getMessage();
                }
            }
            else {
                $message = "Error uploading file. Check permissions.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube Scraper - HAR to CSV</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
            padding-bottom: 50px;
        }

        .container {
            max-width: 700px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <h2 class="m-0 text-danger">YouTube Scraper</h2>
            <div>
                <a href="https://github.com/wsaqaf/ytscraper" target="_blank" class="btn btn-outline-dark btn-sm mr-2">GitHub</a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Refresh</a>
            </div>
        </div>

        <p class="lead text-center mb-4">Convert YouTube HAR files to Video data CSVs.</p>

        <?php if ($message): ?>
        <div class="alert <?php echo $processed ? 'alert-success' : 'alert-danger'; ?>" role="alert">
            <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
        <?php
endif; ?>

        <div class="card p-4 border-0 bg-light">
            <?php if ($processed): ?>
            <h4 class="card-title text-success">Processing Complete</h4>
            <div class="list-group shadow-sm">
                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                    <span><strong>Videos CSV</strong><br><small>
                            <?php echo $videosFile; ?>
                        </small></span>
                    <div>
                        <a href="UPLOAD_FOLDER/<?php echo $videosFile; ?>" class="btn btn-sm btn-outline-primary"
                            download>Download</a>
                        <a href="view_videos.php?url=<?php echo $videosFile; ?>" class="btn btn-sm btn-primary"
                            target="_blank">View Table</a>
                    </div>
                </div>
            </div>
            <a href="index.php" class="btn btn-link mt-4 d-block text-center">Upload another file</a>
            <?php
else: ?>
            <form action="index.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Select a <strong>.har</strong> file:</label>
                    <input type="file" class="form-control-file border p-2 bg-white rounded" name="fileToUpload"
                        required>
                </div>
                <button type="submit" class="btn btn-danger btn-block btn-lg shadow-sm">Upload & Process</button>
            </form>
            <?php
endif; ?>
        </div>

        <?php
$existing_csvs = glob(UPLOAD_FOLDER . '*_videos_*.csv');
if ($existing_csvs) {
    usort($existing_csvs, function ($a, $b) {
        return filemtime($b) - filemtime($a); });
    echo '<div class="mt-5">';
    echo '<h5 class="mb-3">Previously Processed Files</h5>';
    echo '<div class="list-group shadow-sm">';
    foreach ($existing_csvs as $csv) {
        $filename = basename($csv);
        $date = date("M d, Y H:i", filemtime($csv));
        $size = round(filesize($csv) / 1024, 1) . ' KB';
        echo '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
        echo '<div><span class="badge badge-danger mr-2">Videos</span><span class="text-dark font-weight-bold">' . htmlspecialchars($filename) . '</span><br><small class="text-muted">' . $date . ' • ' . $size . '</small></div>';
        echo '<div class="btn-group-sm">';
        echo '<a href="view_videos.php?url=' . urlencode($filename) . '" class="btn btn-outline-primary mr-1" target="_blank">View</a>';
        echo '<a href="UPLOAD_FOLDER/' . urlencode($filename) . '" class="btn btn-outline-secondary" download>Download</a>';
        echo '</div></div>';
    }
    echo '</div></div>';
}
?>
    </div>

    <footer class="text-center mt-5 mb-4 text-muted">
        <small>
            YTScraper for HAR Analysis
        </small>
    </footer>

</body>

</html>