<?php
$upload_dir = 'UPLOAD_FOLDER/';
$raw_filename = $_GET['url'] ?? ($_GET['file'] ?? '');
$selected_file = basename($raw_filename);
$file_path = $upload_dir . $selected_file;

// Helper to sort existing files
$csv_files = glob($upload_dir . '*_videos_*.csv');
if ($csv_files) {
    usort($csv_files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
}
else {
    $csv_files = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>YouTube Video Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-size: 0.9rem;
        }

        .container-fluid {
            padding: 20px;
        }

        .table-responsive {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        .thumb-img {
            width: 120px;
            height: auto;
            border-radius: 4px;
            object-fit: cover;
        }

        td {
            vertical-align: middle !important;
        }

        .desc-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .desc-cell:hover {
            white-space: normal;
            word-wrap: break-word;
        }
    </style>
</head>

<body>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2 class="m-0 text-danger">YouTube Video Viewer</h2>
            <a href="index.php" class="btn btn-outline-danger btn-sm">Upload New File</a>
        </div>

        <!-- file selector -->
        <form action="" method="get" class="mb-4 p-3 bg-light rounded border">
            <div class="row g-2 align-items-center">
                <div class="col-auto"><label class="col-form-label fw-bold">Select Dataset:</label></div>
                <div class="col-md-6">
                    <select name="url" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose a file --</option>
                        <?php foreach ($csv_files as $file):
    $bn = basename($file);
    $dt = date("Y-m-d H:i", filemtime($file));
?>
                        <option value="<?= htmlspecialchars($bn)?>" <?= $selected_file === $bn ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bn)?> (
                            <?= $dt?>)
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php
if ($selected_file && file_exists($file_path)) {
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 0, ",", "\"", "\\"); // Fixed: added escape char

        // Map headers to indices
        $hMap = array_flip($headers);

        echo '<div class="table-responsive">';
        echo '<table id="videosTable" class="table table-striped table-hover table-bordered table-sm w-100">';
        echo '<thead class="table-dark"><tr>';
        echo '<th>Thumbnail</th><th>Title</th><th>Description</th><th>Views</th><th>Published</th><th>Duration</th><th>Channel</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) { // Fixed: added escape char
            // Safe extraction
            $getVal = function ($key) use ($data, $hMap) {
                return isset($hMap[$key]) && isset($data[$hMap[$key]]) ? $data[$hMap[$key]] : '';
            };

            $video_id = $getVal('video_id');
            $title = $getVal('title');
            $thumb = $getVal('thumbnail_url');
            $views = $getVal('view_count');
            $pub = $getVal('published');
            $dur = $getVal('duration');
            $chan = $getVal('channel_name');
            $chanId = $getVal('channel_id');
            $desc = $getVal('description');
            $url = "https://www.youtube.com/watch?v=" . $video_id;

            echo '<tr>';
            echo "<td><a href='$url' target='_blank'><img src='$thumb' class='thumb-img' loading='lazy'></a></td>";
            echo "<td style='max-width: 250px;'><a href='$url' target='_blank' class='fw-bold text-dark text-decoration-none'>$title</a></td>";
            echo "<td class='desc-cell' style='max-width: 300px;' title='" . htmlspecialchars($desc, ENT_QUOTES) . "'>$desc</td>";
            echo "<td>$views</td>";
            echo "<td>$pub</td>";
            echo "<td>$dur</td>";
            echo "<td><a href='https://www.youtube.com/channel/$chanId' target='_blank'>$chan</a></td>";
            echo "<td><a href='$url' target='_blank' class='btn btn-xs btn-danger'>Watch</a></td>";
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        fclose($handle);
    }
}
else {
    echo '<div class="alert alert-info">Please select a CSV file.</div>';
}
?>

        <footer class="text-center mt-4 mb-4 text-muted">YTScraper Viewer</footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            if ($('#videosTable').length) {
                $('#videosTable').DataTable({
                    "order": [],
                    "pageLength": 25,
                    "responsive": true
                });
            }
        });
    </script>
</body>

</html>