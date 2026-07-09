TikVerify — Bulk TikTok Link Checker
=====================================

DEPLOYMENT (InfinityFree / any shared PHP hosting)
---------------------------------------------------
1. Upload all 6 files to your hosting root (or a sub-folder):
     index.php
     process.php
     functions.php
     config.php
     style.css
     script.js

2. Visit index.php in your browser. Everything works immediately.
   No Composer, no npm, no terminal access required.

REQUIREMENTS
------------
- PHP 8.0 or newer
- cURL extension (enabled by default on almost all shared hosts)
- JSON extension (always enabled)
- Standard sessions (always enabled)

FEATURES
--------
- Paste TikTok links, campaign text, or messy mixed content
- Upload / drag-and-drop .txt or .csv files
- Paste directly from clipboard
- Removes duplicate links automatically
- Checks each video with three scraping methods + oEmbed fallback:
    1. __UNIVERSAL_DATA_FOR_REHYDRATION__ JSON (current TikTok format)
    2. SIGI_STATE JSON (legacy TikTok format)
    3. Raw regex scan of page HTML for stats
    4. oEmbed API (title + thumbnail; no stats)
- Retrieves: title, thumbnail, views, likes, comments, shares
- Live progress: bar, speed, ETA, current thumbnail preview
- Pause / Resume / Stop at any time
- Filter results: Available, Broken, Missing Stats, Newest, Oldest
- Search by URL or title
- Export: TXT, CSV, JSON, HTML report, PDF (browser print)
- Copy Available / Copy Broken to clipboard
- Dark mode by default (toggle top-right)
- Fully responsive: mobile, tablet, desktop

CONFIGURATION (config.php)
--------------------------
- REQUEST_TIMEOUT  — seconds per request (default 12)
- CONNECT_TIMEOUT  — seconds to connect (default 6)
- USER_AGENTS      — rotated browser UA strings; add more if rate-limited

NOTES
-----
- TikTok actively fights scraping. View/Like/Comment counts may show
  "N/A" for some videos when all methods fail — this is expected.
  Adding more User-Agent strings in config.php can help.
- Short links (vm.tiktok.com, vt.tiktok.com) are automatically resolved
  to their canonical video URL before checking.
- The delay between checks defaults to 350ms; adjust in Settings (gear icon).
  Increasing it reduces the chance of rate-limiting.
- CSRF protection is enabled automatically via PHP sessions.

FILES
-----
  index.php     — Main page HTML + PHP session/CSRF bootstrap
  process.php   — AJAX endpoint (actions: check, stats)
  functions.php — All backend logic (extraction, scraping, HTTP)
  config.php    — Central configuration constants
  style.css     — Full premium dashboard CSS (no build step)
  script.js     — All frontend logic, live updates, export

