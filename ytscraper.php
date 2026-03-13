<?php

class YTScraperEngine
{
    private $uploadPath;

    public function __construct($uploadPath)
    {
        $this->uploadPath = rtrim($uploadPath, '/') . '/';
    }

    public function processHar($filePath)
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(600);

        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        $videos = [];

        // Debug
        $debugLog = fopen($this->uploadPath . "debug_har.txt", "w");

        // Helper recursive function
        $finder = function ($obj) use (&$videos, &$finder, $debugLog) {
            if (is_array($obj)) {

                // Helper to get ID
                $vid = $obj['videoId'] ?? ($obj['externalVideoId'] ?? null);

                // --- Case A: videoDetails (Player Data) ---
                if (isset($obj['videoDetails'])) {
                    $vd = $obj['videoDetails'];
                    if (isset($vd['videoId'])) {
                        $v = $vd['videoId'];
                        if (!isset($videos[$v]))
                            $videos[$v] = $this->initVideo($v);
                        $this->extractFromVideoDetails($vd, $videos[$v]);
                    }
                }

                // --- Case B: playerMicroformatRenderer (Rich Metadata) ---
                if (isset($obj['playerMicroformatRenderer'])) {
                    $pmfr = $obj['playerMicroformatRenderer'];
                    // It usually has externalVideoId
                    $v = $pmfr['externalVideoId'] ?? null;
                    if ($v) {
                        fwrite($debugLog, "Found Microformat for $v\n");
                        if (!isset($videos[$v]))
                            $videos[$v] = $this->initVideo($v);
                        $this->extractFromMicroformat($pmfr, $videos[$v]);
                    }
                }

                // --- Case C: videoRenderer (Search Results) ---
                if (isset($obj['videoId']) && !isset($obj['videoDetails'])) { // Avoid re-processing videoDetails parent
                    $v = $obj['videoId'];
                    if (!isset($videos[$v]))
                        $videos[$v] = $this->initVideo($v);
                    $this->extractFromRenderer($obj, $videos[$v]);
                }

                // --- Case D: compactVideoRenderer (Sidebar/Related) ---
                if (isset($obj['compactVideoRenderer'])) {
                    $cvr = $obj['compactVideoRenderer'];
                    if (isset($cvr['videoId'])) {
                        $v = $cvr['videoId'];
                        if (!isset($videos[$v]))
                            $videos[$v] = $this->initVideo($v);
                        $this->extractFromRenderer($cvr, $videos[$v]);
                    }
                }

                // Recursive step
                foreach ($obj as $k => $v) {
                    $finder($v);
                }
            }
        };

        // Strategy 1: JSON Decode
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['log']['entries'])) {
            foreach ($data['log']['entries'] as $entry) {
                if (isset($entry['response']['content']['text'])) {
                    $txt = $entry['response']['content']['text'];
                    $inner = json_decode($txt, true);
                    if ($inner)
                        $finder($inner);
                }
            }
        }

        // Strategy 2: Robust Scan for specific blocks
        $markers = ['playerResponse', 'videoDetails', 'playerMicroformatRenderer', 'microformat'];
        foreach ($markers as $marker) {
            $offset = 0;
            $searchStr = '\\"' . $marker . '\\"';

            while (($pos = strpos($content, $searchStr, $offset)) !== false) {
                // Find start of object
                $startObj = strpos($content, '{', $pos);
                if ($startObj !== false && ($startObj - $pos) < 50) {
                    $jsonBlock = $this->extractJsonBlock($content, $startObj);
                    if ($jsonBlock) {
                        $decoded = json_decode($jsonBlock, true);
                        if (!$decoded)
                            $decoded = json_decode(stripslashes($jsonBlock), true);

                        if ($decoded) {
                            if ($marker === 'playerResponse') {
                                $finder($decoded);
                            }
                            elseif ($marker === 'videoDetails') {
                                $finder(['videoDetails' => $decoded]);
                            }
                            elseif ($marker === 'playerMicroformatRenderer') {
                                $finder(['playerMicroformatRenderer' => $decoded]);
                            }
                            elseif ($marker === 'microformat') {
                                $finder($decoded);
                            }
                        }
                    }
                }
                $offset = $pos + strlen($searchStr);
            }
        }

        fclose($debugLog);

        // Save CSV
        $timestamp = date("Ymd_His");
        $baseName = pathinfo($filePath, PATHINFO_FILENAME);
        $baseName = preg_replace("/[^a-zA-Z0-9\._-]/", "", $baseName);
        $videosCsvName = $baseName . "_videos_" . $timestamp . ".csv";
        $videosCsvPath = $this->uploadPath . $videosCsvName;

        if (!empty($videos)) {
            $fp = fopen($videosCsvPath, 'w');
            fputcsv($fp, ['video_id', 'title', 'published', 'view_count', 'duration', 'channel_name', 'channel_id', 'thumbnail_url', 'description', 'video_url'], ",", "\"", "\\");
            foreach ($videos as $vid) {
                fputcsv($fp, [
                    $vid['video_id'],
                    $vid['title'],
                    $vid['published'],
                    $vid['view_count'],
                    $vid['duration'],
                    $vid['channel_name'],
                    $vid['channel_id'],
                    $vid['thumbnail_url'],
                    $vid['description'],
                    "https://www.youtube.com/watch?v=" . $vid['video_id']
                ], ",", "\"", "\\");
            }
            fclose($fp);
        }
        else {
            file_put_contents($videosCsvPath, "video_id,title,published,view_count,duration,channel_name,channel_id,thumbnail_url,description,video_url\n");
        }

        return ['videos' => $videosCsvName, 'count' => count($videos)];
    }

    // --- Extraction Helpers ---

    private function initVideo($vid)
    {
        return [
            'video_id' => $vid, 'title' => '', 'view_count' => '', 'published' => '',
            'channel_name' => '', 'channel_id' => '', 'thumbnail_url' => '', 'duration' => '', 'description' => ''
        ];
    }

    private function extractFromVideoDetails($vd, &$video)
    {
        if (isset($vd['title']))
            $video['title'] = $vd['title'];
        if (isset($vd['viewCount']))
            $video['view_count'] = $vd['viewCount'];
        if (isset($vd['lengthSeconds']))
            $video['duration'] = gmdate("H:i:s", (int)$vd['lengthSeconds']);
        if (isset($vd['channelId']))
            $video['channel_id'] = $vd['channelId'];
        if (isset($vd['author']))
            $video['channel_name'] = $vd['author'];
        if (isset($vd['shortDescription']))
            $video['description'] = $vd['shortDescription'];
        if (isset($vd['thumbnail']['thumbnails'][0]['url']))
            $video['thumbnail_url'] = $vd['thumbnail']['thumbnails'][0]['url'];
    }

    private function extractFromMicroformat($pmfr, &$video)
    {
        // pmfr is the playerMicroformatRenderer object
        if (isset($pmfr['publishDate']))
            $video['published'] = $pmfr['publishDate'];
        if (isset($pmfr['ownerChannelName']))
            $video['channel_name'] = $pmfr['ownerChannelName'];
        if (isset($pmfr['uploadDate']) && empty($video['published']))
            $video['published'] = $pmfr['uploadDate'];
        if (isset($pmfr['thumbnail']['thumbnails'][0]['url']) && empty($video['thumbnail_url']))
            $video['thumbnail_url'] = $pmfr['thumbnail']['thumbnails'][0]['url'];
        if (isset($pmfr['lengthSeconds']) && empty($video['duration']))
            $video['duration'] = gmdate("H:i:s", (int)$pmfr['lengthSeconds']);
        if (isset($pmfr['viewCount']) && empty($video['view_count']))
            $video['view_count'] = $pmfr['viewCount'];
    }

    private function extractFromRenderer($obj, &$video)
    {
        if (isset($obj['title']))
            $video['title'] = $this->extractText($obj['title']);
        if (isset($obj['viewCountText']))
            $video['view_count'] = $this->extractText($obj['viewCountText']);
        if (isset($obj['publishedTimeText']))
            $video['published'] = $this->extractText($obj['publishedTimeText']);
        if (isset($obj['thumbnail']['thumbnails'][0]['url']))
            $video['thumbnail_url'] = $obj['thumbnail']['thumbnails'][0]['url'];

        if (isset($obj['ownerText'])) {
            $video['channel_name'] = $this->extractText($obj['ownerText']);
            if (isset($obj['ownerText']['runs'][0]['navigationEndpoint']['browseEndpoint']['browseId'])) {
                $video['channel_id'] = $obj['ownerText']['runs'][0]['navigationEndpoint']['browseEndpoint']['browseId'];
            }
        }
        elseif (isset($obj['shortBylineText'])) {
            $video['channel_name'] = $this->extractText($obj['shortBylineText']);
        }

        if (isset($obj['lengthText']))
            $video['duration'] = $this->extractText($obj['lengthText']);
        if (isset($obj['descriptionSnippet']))
            $video['description'] = $this->extractText($obj['descriptionSnippet']);
    }

    private function extractText($obj)
    {
        if (is_string($obj))
            return $obj;
        if (isset($obj['simpleText']))
            return $obj['simpleText'];
        if (isset($obj['runs'])) {
            $t = '';
            foreach ($obj['runs'] as $r)
                $t .= $r['text'] ?? '';
            return $t;
        }
        return '';
    }

    private function extractJsonBlock($content, $startObj)
    {
        $braceCount = 0;
        $inString = false;
        $escape = false;
        $endObj = $startObj;
        $len = strlen($content);
        $maxLen = 50000;

        for ($i = $startObj; $i < $len && ($i - $startObj) < $maxLen; $i++) {
            $char = $content[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\') {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $inString = !$inString;
            }
            if (!$inString) {
                if ($char === '{')
                    $braceCount++;
                if ($char === '}') {
                    $braceCount--;
                    if ($braceCount === 0)
                        return substr($content, $startObj, $i - $startObj + 1);
                }
            }
        }
        return null;
    }
}
?>