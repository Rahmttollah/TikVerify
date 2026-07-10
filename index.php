<?php
require_once __DIR__ . '/config.php';
$csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
$appName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
$appTagline = htmlspecialchars(APP_TAGLINE, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $appName ?> — Bulk TikTok Link Checker</title>
<meta name="description" content="<?= $appTagline ?>">
<meta name="csrf-token" content="<?= $csrfToken ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?: '1' ?>">
</head>
<body>
<div class="bg-aurora" aria-hidden="true"></div>

<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <span class="brand-mark">
        <svg width="26" height="26" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M34 6c1.2 6 5.4 10 12 10.6v7.4c-4.4 0-8.4-1.3-12-3.8v12.6C34 41 27.8 47 20 47S6 41 6 33.2c0-7.6 6-13.7 13.6-14v8.1c-3.1.5-5.6 3.2-5.6 6.5 0 3.7 3 6.6 6.6 6.6s6.6-2.9 6.6-6.6V6z" fill="url(#g)"/>
          <defs><linearGradient id="g" x1="6" y1="6" x2="46" y2="47" gradientUnits="userSpaceOnUse"><stop stop-color="#7C5CFF"/><stop offset="1" stop-color="#2FD3C7"/></linearGradient></defs>
        </svg>
      </span>
      <span class="brand-name"><?= $appName ?></span>
    </div>

    <div class="topbar-search">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <input type="text" id="topSearch" placeholder="Search results by title or URL…" autocomplete="off">
    </div>

    <nav class="topbar-actions">
      <button class="icon-btn" id="themeToggle" title="Toggle dark / light mode" aria-label="Toggle theme">
        <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
      </button>
      <button class="icon-btn" id="settingsBtn" title="Settings" aria-label="Settings">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
      </button>
      <button class="icon-btn" id="aboutBtn" title="About" aria-label="About">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 11v6M12 7.5v.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </nav>
  </div>
</header>

<main class="shell">

  <section class="hero">
    <div class="hero-badge">Bulk verification · real-time · zero setup</div>
    <h1 class="hero-title">Verify thousands of <span>TikTok links</span> in minutes.</h1>
    <p class="hero-sub">Paste links, campaign notes, or messy exports. <?= $appName ?> extracts every URL, checks availability, and pulls views, likes, comments and HD thumbnails automatically — with automatic fallbacks when TikTok blocks a method.</p>

    <div class="hero-stats" id="heroStats">
      <div class="hstat"><span class="hstat-num" data-count="0" id="statTotalHero">0</span><span class="hstat-label">Links Checked</span></div>
      <div class="hstat"><span class="hstat-num" data-count="0" id="statAvailHero">0</span><span class="hstat-label">Available</span></div>
      <div class="hstat"><span class="hstat-num" data-count="0" id="statBrokenHero">0</span><span class="hstat-label">Broken</span></div>
      <div class="hstat"><span class="hstat-num" data-count="0" id="statRateHero">0%</span><span class="hstat-label">Success Rate</span></div>
    </div>
  </section>

  <section class="panel input-panel">
    <div class="panel-head">
      <h2>1. Add your links</h2>
      <span class="panel-hint">Paste raw text, or drop a .txt / .csv file below</span>
    </div>

    <div class="dropzone" id="dropzone">
      <textarea id="inputText" placeholder="Paste TikTok links, campaign text, or mixed content here…&#10;&#10;https://www.tiktok.com/@user/video/1234567890123456789&#10;https://vm.tiktok.com/ABCdef/"></textarea>
      <div class="dropzone-overlay" id="dropOverlay">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none"><path d="M12 16V4M12 4l-4 4M12 4l4 4M5 20h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <p>Drop your .txt or .csv file</p>
      </div>
    </div>

    <div class="input-toolbar">
      <button class="btn btn-ghost" id="btnUpload">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 16V4M12 4l-4 4M12 4l4 4M5 20h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Upload File
      </button>
      <input type="file" id="fileInput" accept=".txt,.csv" hidden>
      <button class="btn btn-ghost" id="btnPaste">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="6" y="4" width="12" height="17" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1" stroke="currentColor" stroke-width="1.8"/></svg>
        Paste Clipboard
      </button>
      <button class="btn btn-ghost" id="btnExample">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        Load Example
      </button>
      <button class="btn btn-ghost" id="btnClear">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Clear
      </button>
      <span class="toolbar-spacer"></span>
      <span class="input-summary" id="inputSummary">0 links detected</span>
    </div>

    <div class="run-row">
      <button class="btn btn-primary btn-lg" id="btnStart">
        <span class="btn-glow"></span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 5v14l12-7L7 5z" fill="currentColor"/></svg>
        <span>Start Checking</span>
      </button>
      <button class="btn btn-secondary" id="btnPause" disabled>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="6" y="5" width="4" height="14" rx="1" fill="currentColor"/><rect x="14" y="5" width="4" height="14" rx="1" fill="currentColor"/></svg>
        Pause
      </button>
      <button class="btn btn-secondary" id="btnResume" disabled hidden>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 5v14l12-7L7 5z" fill="currentColor"/></svg>
        Resume
      </button>
      <button class="btn btn-danger" id="btnStop" disabled>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"/></svg>
        Stop
      </button>
      <button class="btn btn-secondary" id="btnOpenCreateService" hidden>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        Create Service
      </button>
    </div>
  </section>

  <section class="panel progress-panel" id="progressPanel" hidden>
    <div class="panel-head">
      <h2>Live Progress</h2>
      <span class="status-pill" id="statusPill">Idle</span>
    </div>

    <div class="progress-track">
      <div class="progress-fill" id="progressFill"></div>
    </div>

    <div class="progress-grid">
      <div class="progress-current">
        <img id="currentThumb" class="current-thumb" alt="" src="" hidden>
        <div class="current-thumb placeholder" id="currentThumbPlaceholder"></div>
        <div class="current-info">
          <div class="current-label">Currently checking</div>
          <div class="current-link" id="currentLink">—</div>
          <div class="current-status" id="currentStatus">Waiting to start…</div>
        </div>
      </div>

      <div class="progress-stats">
        <div class="pstat"><span id="pProcessed">0</span><label>Processed</label></div>
        <div class="pstat"><span id="pRemaining">0</span><label>Remaining</label></div>
        <div class="pstat"><span id="pElapsed">0s</span><label>Elapsed</label></div>
        <div class="pstat"><span id="pETA">—</span><label>Est. Remaining</label></div>
        <div class="pstat"><span id="pSpeed">—</span><label>Speed</label></div>
      </div>
    </div>
  </section>

  <section class="panel summary-panel" id="summaryPanel" hidden>
    <div class="panel-head"><h2>Summary</h2></div>
    <div class="summary-grid">
      <div class="scard"><div class="scard-num" id="sTotal">0</div><div class="scard-label">Total URLs</div></div>
      <div class="scard good"><div class="scard-num" id="sAvailable">0</div><div class="scard-label">Available</div></div>
      <div class="scard bad"><div class="scard-num" id="sBroken">0</div><div class="scard-label">Broken</div></div>
      <div class="scard"><div class="scard-num" id="sDuplicates">0</div><div class="scard-label">Duplicates Removed</div></div>
      <div class="scard"><div class="scard-num" id="sNoUrl">0</div><div class="scard-label">Lines Without URLs</div></div>
      <div class="scard"><div class="scard-num" id="sTime">0s</div><div class="scard-label">Processing Time</div></div>
      <div class="scard"><div class="scard-num" id="sSpeed">0/s</div><div class="scard-label">Average Speed</div></div>
      <div class="scard accent"><div class="scard-num" id="sRate">0%</div><div class="scard-label">Success Rate</div></div>
    </div>
  </section>

  <section class="panel results-panel" id="resultsPanel" hidden>
    <div class="panel-head results-head">
      <h2>Results</h2>
      <div class="view-toggle">
        <button class="vtab" data-view="cards">Cards</button>
        <button class="vtab active" data-view="gallery">Gallery</button>
      </div>
    </div>

    <div class="filters-row">
      <div class="chip-group" id="filterChips">
        <button class="chip active" data-filter="all">All</button>
        <button class="chip" data-filter="available">Available</button>
        <button class="chip" data-filter="broken">Broken</button>
        <button class="chip" data-filter="missing">Missing Stats</button>
        <button class="chip" data-filter="newest">Newest</button>
        <button class="chip" data-filter="oldest">Oldest</button>
      </div>
      <div class="results-search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input type="text" id="resultsSearch" placeholder="Filter by URL or title…">
      </div>
    </div>

    <div class="export-row">
      <button class="btn btn-ghost sm" id="btnCopyAllAvailable" hidden>Copy All Available Links</button>
      <button class="btn btn-ghost sm" id="btnCopyAvailable">Copy Available</button>
      <button class="btn btn-ghost sm" id="btnCopyBroken">Copy Broken</button>
      <button class="btn btn-ghost sm" id="btnDownloadTxt">Download TXT</button>
      <button class="btn btn-ghost sm" id="btnDownloadCsv">Download CSV</button>
      <button class="btn btn-ghost sm" id="btnDownloadJson">Download JSON</button>
      <button class="btn btn-ghost sm" id="btnExportHtml">Export HTML</button>
      <button class="btn btn-ghost sm" id="btnExportPdf">Export PDF</button>
    </div>

    <div class="results-grid gallery-view" id="resultsGrid"></div>
    <div class="empty-state" id="emptyState" hidden>
      <svg width="46" height="46" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v16H4z" stroke="currentColor" stroke-width="1.4" opacity=".3"/><path d="M9 15l2.2-2.8L13 14l3-4 3 5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
      <p>No results match this filter yet.</p>
    </div>
  </section>

  <!-- =====================================================================
       Create Service — generate bulk SMM panel order lists (opened as a
       dialog once at least one AVAILABLE result exists)
       ===================================================================== -->
  <dialog class="app-dialog cs-dialog cs-panel" id="createServiceModal">
    <div class="modal-head">
      <h3>Create Service</h3>
      <button type="button" class="icon-btn" data-close aria-label="Close">&times;</button>
    </div>
    <p class="panel-hint cs-dialog-hint">Generate bulk order lists for SMM panels</p>

    <div class="cs-top-row">
      <!-- Service ID + history -->
      <div class="cs-field-group">
        <label class="cs-label" for="csServiceId">Service ID</label>
        <div class="cs-service-wrap">
          <input type="number" id="csServiceId" class="cs-input" placeholder="e.g. 17759" min="1" step="1">
          <button class="btn btn-ghost sm" id="csHistoryToggle" type="button">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            History
          </button>
        </div>
        <div class="cs-history-panel" id="csHistoryPanel" hidden>
          <div class="cs-history-head">
            <span class="cs-history-title">Recent Services</span>
            <button class="btn btn-ghost sm" id="csClearHistory" type="button">Clear All</button>
          </div>
          <div class="cs-history-list" id="csHistoryList"></div>
          <div class="cs-history-empty" id="csHistoryEmpty" hidden>No history yet</div>
        </div>
      </div>

      <!-- Price Per 1000 -->
      <div class="cs-field-group">
        <label class="cs-label" for="csPricePer1k">Price Per 1000 (BDT) <span class="cs-optional">(optional)</span></label>
        <input type="number" id="csPricePer1k" class="cs-input" placeholder="e.g. 35" min="0" step="0.01">
      </div>
    </div>

    <!-- Available videos readout (auto-sourced from verified Results) -->
    <div class="cs-field-group cs-urls-group">
      <label class="cs-label">Available Videos <span class="cs-url-count" id="csUrlCount"></span></label>
      <div class="cs-source-note" id="csSourceNote">Uses every AVAILABLE link from the Results section below — no need to paste URLs again.</div>
    </div>

    <!-- Quantity mode -->
    <div class="cs-qty-section">
      <div class="cs-mode-row">
        <span class="cs-label">Quantity Mode</span>
        <div class="cs-mode-toggle">
          <button class="cs-mode-btn active" data-mode="fixed" type="button">Fixed</button>
          <button class="cs-mode-btn" data-mode="random" type="button">Random</button>
        </div>
      </div>
      <div id="csFixedBlock">
        <label class="cs-label" for="csQtyFixed">Quantity per video</label>
        <input type="number" id="csQtyFixed" class="cs-input cs-qty-input" placeholder="e.g. 3000" min="1" step="1">
      </div>
      <div class="cs-top-row" id="csRandomBlock" hidden>
        <div class="cs-field-group">
          <label class="cs-label" for="csQtyMin">Minimum Quantity</label>
          <input type="number" id="csQtyMin" class="cs-input" placeholder="e.g. 2500" min="1" step="1">
        </div>
        <div class="cs-field-group">
          <label class="cs-label" for="csQtyMax">Maximum Quantity</label>
          <input type="number" id="csQtyMax" class="cs-input" placeholder="e.g. 3000" min="1" step="1">
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="cs-actions-row">
      <button class="btn btn-primary" id="csBtnGenerate" type="button">
        <span class="btn-glow"></span>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        Generate
      </button>
      <button class="btn btn-ghost" id="csBtnCopyOutput" type="button" hidden>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="1.8"/></svg>
        Copy Output
      </button>
      <button class="btn btn-ghost" id="csBtnDownloadOutput" type="button" hidden>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 3v12m0 0l-4.5-4.5M12 15l4.5-4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Download TXT
      </button>
      <button class="btn btn-ghost sm" id="csBtnClear" type="button">Clear</button>
    </div>

    <!-- Live Summary -->
    <div class="cs-summary" id="csSummary" hidden>
      <div class="cs-summary-inner">
        <div class="cs-sum-stat cs-sum-primary">
          <div class="cs-sum-val" id="csSumBdt">৳—</div>
          <div class="cs-sum-label">Total Cost (BDT)</div>
        </div>
        <div class="cs-sum-stat">
          <div class="cs-sum-val" id="csSumVideos">—</div>
          <div class="cs-sum-label">Available Videos</div>
        </div>
        <div class="cs-sum-stat">
          <div class="cs-sum-val" id="csSumOrders">—</div>
          <div class="cs-sum-label">Generated Orders</div>
        </div>
        <div class="cs-sum-stat">
          <div class="cs-sum-val" id="csSumQty">—</div>
          <div class="cs-sum-label">Total Quantity</div>
        </div>
        <div class="cs-sum-stat">
          <div class="cs-sum-val" id="csSumMode">—</div>
          <div class="cs-sum-label">Quantity Mode</div>
        </div>
        <div class="cs-sum-stat">
          <div class="cs-sum-val" id="csSumPrice">—</div>
          <div class="cs-sum-label">Price / 1000</div>
        </div>
      </div>
    </div>

    <!-- Generated output -->
    <div class="cs-output-section" id="csOutputSection" hidden>
      <div class="cs-output-head">
        <label class="cs-label">Generated Output</label>
        <span class="cs-output-lines" id="csOutputLines"></span>
      </div>
      <textarea id="csOutput" class="cs-textarea cs-output-textarea" readonly></textarea>
    </div>
  </dialog>

</main>

<footer class="app-footer">
  <span><?= $appName ?> — runs entirely on standard PHP hosting. No frameworks, no build step.</span>
</footer>

<!-- Settings dialog (native <dialog>) -->
<dialog class="app-dialog" id="settingsModal">
  <div class="modal-head"><h3>Settings</h3><button type="button" class="icon-btn" data-close aria-label="Close">&times;</button></div>
  <div class="modal-body">
    <label class="field">
      <span>Delay between checks (ms)</span>
      <input type="range" id="settingDelay" min="100" max="1500" step="50" value="350">
      <span class="field-value" id="settingDelayValue">350ms</span>
    </label>
    <label class="field checkbox">
      <input type="checkbox" id="settingLazyLoad" checked>
      <span>Lazy-load thumbnails</span>
    </label>
    <label class="field checkbox">
      <input type="checkbox" id="settingSound" checked>
      <span>Sound on completion</span>
    </label>
  </div>
</dialog>

<!-- About dialog (native <dialog>) -->
<dialog class="app-dialog" id="aboutModal">
  <div class="modal-head"><h3>About <?= $appName ?></h3><button type="button" class="icon-btn" data-close aria-label="Close">&times;</button></div>
  <div class="modal-body">
    <p><?= $appTagline ?>. Extracts TikTok links from any pasted text or file, verifies availability, and retrieves view/like/comment counts and thumbnails using several fallback methods so results stay accurate even when TikTok changes its page structure.</p>
    <p class="muted">Built with plain PHP, HTML, CSS and JavaScript — deployable to any standard shared hosting provider.</p>
  </div>
</dialog>

<div class="toast-stack" id="toastStack"></div>

<template id="cardTemplate">
  <article class="result-card">
    <div class="card-thumb-wrap">
      <img class="card-thumb" loading="lazy" alt="">
      <span class="status-badge"></span>
    </div>
    <div class="card-body">
      <h3 class="card-title"></h3>
      <a class="card-url" target="_blank" rel="noopener"></a>
      <div class="card-stats">
        <span class="cstat views"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg><b></b></span>
        <span class="cstat likes"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 21s-7.5-4.6-10-9.3C.5 8 2.4 4.5 6 4.5c2 0 3.5 1 6 3.5 2.5-2.5 4-3.5 6-3.5 3.6 0 5.5 3.5 4 7.2C19.5 16.4 12 21 12 21Z" stroke="currentColor" stroke-width="1.6"/></svg><b></b></span>
        <span class="cstat comments"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 0 1-8.9 8.4A9 9 0 0 1 8 19l-5 1 1.4-4.2A8.4 8.4 0 1 1 21 11.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg><b></b></span>
      </div>
      <p class="card-error"></p>
      <div class="card-actions">
        <a class="btn btn-ghost sm card-open" target="_blank" rel="noopener">Open Video</a>
        <button class="btn btn-ghost sm card-copy">Copy URL</button>
      </div>
    </div>
  </article>
</template>

<script src="script.js?v=<?= @filemtime(__DIR__ . '/script.js') ?: '1' ?>"></script>
</body>
</html>
