# YouTube Scraper (YTScraper)

YTScraper is a lightweight, PHP-based tool designed to extract video metadata from YouTube HAR (HTTP Archive) files. It allows researchers and analysts to convert browser activity logs into structured CSV data for local analysis.

## Features

- **HAR Parsing**: Robust extraction of video data (Title, Views, Published Date, Duration, Channel Info, etc.) from complex YouTube network logs.
- **Web Interface**: Easy-to-use upload and processing interface.
- **Live Viewer**: Built-in data table to browse and filter results without leaving the browser.
- **Dockerized**: Ready to run anywhere with a single command.

## Quick Start (Docker)

The easiest way to run YTScraper is using Docker:

1.  **Clone the repository** (or download the files).
2.  **Start the container**:
    ```bash
    docker-compose up -d
    ```
3.  **Access the app**:
    Open [http://localhost:8080](http://localhost:8080) in your browser.

## Manual Installation

If you prefer to run it on a local PHP server:

1.  Ensure you have PHP 8.0+ installed.
2.  Place the files in your web directory (e.g., `www`, `public_html`).
3.  Ensure the `UPLOAD_FOLDER` directory is writable by the web server:
    ```bash
    chmod -R 775 UPLOAD_FOLDER
    ```
4.  Optionally, configure your `php.ini` to allow larger file uploads (up to 500MB recommended for large HAR files).

## Usage Guide

1.  **Capture a HAR file**:
    - Open YouTube in your browser.
    - Open Developer Tools (F12) > Network Tab.
    - Ensure "Preserve log" is checked.
    - Scroll through the videos you want to scrape.
    - Right-click in the network list and select "Save all as HAR with content".
2.  **Upload to YTScraper**:
    - Select the `.har` file in the YTScraper web interface.
    - Click "Upload & Process".
3.  **View & Export**:
    - Click "View Table" to see results in a searchable grid.
    - Click "Download" to save the CSV for use in Excel, R, or Python.

## Technical Details

- **Engine**: The core logic resides in `ytscraper.php`, which implements a recursive search strategy to find hidden JSON blocks within the HAR structure.
- **Privacy**: All processing is done locally on your machine. No data is sent to external servers.

## License

MIT

## Author
**Dr. Walid Al-Saqaf**  
Email: walid[@]al-saqaf.se
