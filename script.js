/* =========================================================================
   TikVerify — client-side application logic.
   Plain vanilla JS. Talks to process.php via fetch/AJAX. No build step.
   ========================================================================= */
(function () {
  "use strict";

  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

  const EXTRACT_PATTERN =
    /https?:\/\/(?:(?:www\.)?tiktok\.com\/@[^\/\s]+\/video\/\d+[^\s|]*|(?:vm|vt|m)\.tiktok\.com\/[\w]+[^\s|]*|(?:www\.)?tiktok\.com\/t\/[\w]+[^\s|]*)/gi;

  const EXAMPLE_TEXT =
    "Campaign batch — TikTok creator drops\n" +
    "https://www.tiktok.com/@tiktok/video/7300000000000000001\n" +
    "https://vm.tiktok.com/ZMabcd123/\n" +
    "Notes: check these before Friday\n" +
    "https://www.tiktok.com/@tiktok/video/7300000000000000002 | extra text\n" +
    "https://www.tiktok.com/@tiktok/video/7300000000000000001 (duplicate on purpose)\n" +
    "no link on this line, just a reminder";

  // ---- State -------------------------------------------------------------
  const state = {
    results: [], // { url, status, title, thumbnail, views, likes, comments, error, order }
    queue: [],
    running: false,
    paused: false,
    stopped: false,
    processedCount: 0,
    startTime: 0,
    activeFilter: "all",
    activeView: "gallery",
    searchTerm: "",
    delayMs: 350,
  };

  // ---- DOM shortcuts -------------------------------------------------------
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  const inputText = $("#inputText");
  const dropzone = $("#dropzone");
  const fileInput = $("#fileInput");
  const inputSummary = $("#inputSummary");

  const btnStart = $("#btnStart");
  const btnPause = $("#btnPause");
  const btnResume = $("#btnResume");
  const btnStop = $("#btnStop");
  const btnOpenCreateService = $("#btnOpenCreateService");

  const progressPanel = $("#progressPanel");
  const summaryPanel = $("#summaryPanel");
  const resultsPanel = $("#resultsPanel");
  const resultsGrid = $("#resultsGrid");
  const emptyState = $("#emptyState");

  // =========================================================================
  // Toasts
  // =========================================================================
  function toast(message, type) {
    type = type || "info";
    const el = document.createElement("div");
    el.className = "toast " + type;
    el.textContent = message;
    $("#toastStack").appendChild(el);
    setTimeout(() => el.remove(), 3600);
  }

  // =========================================================================
  // Theme
  // =========================================================================
  (function initTheme() {
    const saved = localStorage.getItem("tikverify_theme");
    const theme = saved || "dark";
    document.documentElement.setAttribute("data-theme", theme);
    $("#themeToggle").addEventListener("click", () => {
      const current = document.documentElement.getAttribute("data-theme");
      const next = current === "dark" ? "light" : "dark";
      document.documentElement.setAttribute("data-theme", next);
      localStorage.setItem("tikverify_theme", next);
    });
  })();

  // =========================================================================
  // Dialogs — native <dialog> element (no custom backdrop/hidden logic).
  // =========================================================================
  function openDialog(id) {
    const dlg = document.getElementById(id);
    if (dlg && typeof dlg.showModal === "function") {
      if (!dlg.open) dlg.showModal();
    } else if (dlg) {
      // Extremely old browser fallback: dialog polyfill absent, just show it.
      dlg.setAttribute("open", "");
    }
  }
  $("#settingsBtn").addEventListener("click", () =>
    openDialog("settingsModal"),
  );
  $("#aboutBtn").addEventListener("click", () => openDialog("aboutModal"));
  $("#btnOpenCreateService").addEventListener("click", () =>
    openDialog("createServiceModal"),
  );

  $$(".app-dialog").forEach((dlg) => {
    // Close via the × button.
    dlg.querySelectorAll("[data-close]").forEach((btn) => {
      btn.addEventListener("click", () => dlg.close());
    });
    // Click on the ::backdrop (outside the dialog box) closes it — a click that
    // lands directly on the <dialog> element itself (not its children) is a
    // backdrop click, since the dialog box is sized to its content.
    dlg.addEventListener("click", (e) => {
      if (e.target === dlg) dlg.close();
    });
  });

  const settingDelay = $("#settingDelay");
  const settingDelayValue = $("#settingDelayValue");
  settingDelay.value = localStorage.getItem("tikverify_delay") || 350;
  settingDelayValue.textContent = settingDelay.value + "ms";
  state.delayMs = parseInt(settingDelay.value, 10);
  settingDelay.addEventListener("input", () => {
    settingDelayValue.textContent = settingDelay.value + "ms";
    state.delayMs = parseInt(settingDelay.value, 10);
    localStorage.setItem("tikverify_delay", settingDelay.value);
  });

  // =========================================================================
  // Ripple + button micro interactions
  // =========================================================================
  $$(".btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      if (btn.disabled) return;
      const rect = btn.getBoundingClientRect();
      const ripple = document.createElement("span");
      ripple.className = "ripple";
      const size = Math.max(rect.width, rect.height);
      ripple.style.width = ripple.style.height = size + "px";
      ripple.style.left = e.clientX - rect.left - size / 2 + "px";
      ripple.style.top = e.clientY - rect.top - size / 2 + "px";
      btn.appendChild(ripple);
      setTimeout(() => ripple.remove(), 650);
    });
  });

  // =========================================================================
  // Input extraction preview (debounced)
  // =========================================================================
  let extractTimer = null;
  function updateInputSummary() {
    const text = inputText.value;
    const matches = text.match(EXTRACT_PATTERN) || [];
    const unique = uniqueUrls(matches);
    inputSummary.textContent =
      unique.length + " link" + (unique.length === 1 ? "" : "s") + " detected";
  }
  function uniqueUrls(list) {
    const seen = new Set();
    const out = [];
    list.forEach((u) => {
      const clean = u.split("|")[0].trim();
      if (!seen.has(clean)) {
        seen.add(clean);
        out.push(clean);
      }
    });
    return out;
  }
  inputText.addEventListener("input", () => {
    clearTimeout(extractTimer);
    extractTimer = setTimeout(updateInputSummary, 150);
  });

  // =========================================================================
  // File upload / drag & drop / clipboard / example / clear
  // =========================================================================
  $("#btnUpload").addEventListener("click", () => fileInput.click());
  fileInput.addEventListener("change", () => {
    const file = fileInput.files[0];
    if (file) readFileIntoInput(file);
  });

  ["dragenter", "dragover"].forEach((evt) =>
    dropzone.addEventListener(evt, (e) => {
      e.preventDefault();
      dropzone.classList.add("dragging");
    }),
  );
  ["dragleave", "drop"].forEach((evt) =>
    dropzone.addEventListener(evt, (e) => {
      e.preventDefault();
      dropzone.classList.remove("dragging");
    }),
  );
  dropzone.addEventListener("drop", (e) => {
    const file = e.dataTransfer.files[0];
    if (file) readFileIntoInput(file);
  });

  function readFileIntoInput(file) {
    const reader = new FileReader();
    reader.onload = () => {
      inputText.value = String(reader.result);
      updateInputSummary();
      toast('Loaded "' + file.name + '"', "success");
    };
    reader.onerror = () => toast("Could not read file", "error");
    reader.readAsText(file);
  }

  $("#btnPaste").addEventListener("click", async () => {
    try {
      const text = await navigator.clipboard.readText();
      inputText.value += (inputText.value ? "\n" : "") + text;
      updateInputSummary();
      toast("Pasted from clipboard", "success");
    } catch (err) {
      toast("Clipboard access denied by browser", "error");
    }
  });

  $("#btnExample").addEventListener("click", () => {
    inputText.value = EXAMPLE_TEXT;
    updateInputSummary();
  });

  $("#btnClear").addEventListener("click", () => {
    inputText.value = "";
    updateInputSummary();
  });

  // =========================================================================
  // API helper
  // =========================================================================
  async function api(action, payload) {
    const res = await fetch("process.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": CSRF_TOKEN,
      },
      body: JSON.stringify(
        Object.assign(
          { action: action, csrf_token: CSRF_TOKEN },
          payload || {},
        ),
      ),
    });
    if (!res.ok) {
      const errBody = await res.json().catch(() => ({}));
      throw new Error(errBody.error || "Request failed (" + res.status + ")");
    }
    return res.json();
  }

  // =========================================================================
  // Batch run controller
  // =========================================================================
  function sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
  }

  async function startRun() {
    const text = inputText.value.trim();
    if (!text) {
      toast("Add some links first", "error");
      return;
    }

    let stats;
    try {
      stats = await api("stats", { text: text });
    } catch (err) {
      toast(err.message, "error");
      return;
    }

    if (stats.unique === 0 || !stats.urls || stats.urls.length === 0) {
      toast("No TikTok links found in the input", "error");
      return;
    }

    state.queue = stats.urls.slice();
    state.results = [];
    state.processedCount = 0;
    state.running = true;
    state.paused = false;
    state.stopped = false;
    state.startTime = Date.now();
    state.linesWithoutUrl = stats.lines_without_url;
    state.duplicatesRemoved = stats.duplicates_removed;

    progressPanel.hidden = false;
    summaryPanel.hidden = true;
    resultsPanel.hidden = false;
    cardNodeMap.clear();
    resultsGrid.innerHTML = "";

    setRunningUI(true);
    updateProgressUI(0, state.queue.length);
    renderResults();

    for (let i = 0; i < state.queue.length; i++) {
      if (state.stopped) break;
      while (state.paused && !state.stopped) {
        setStatusPill("paused");
        await sleep(200);
      }
      if (state.stopped) break;

      const url = state.queue[i];
      setCurrent(url, "Checking…");
      setStatusPill("running");

      let newResult;
      try {
        const resp = await api("check", { url: url });
        newResult = resp.result;
        newResult.order = i;
        state.results.push(newResult);
        setCurrent(
          url,
          newResult.status === "available"
            ? "Available"
            : "Error: " + (newResult.error || "unknown"),
          newResult.thumbnail,
        );
      } catch (err) {
        newResult = { url: url, status: "error", error: err.message, order: i };
        state.results.push(newResult);
        setCurrent(url, "Error: " + err.message);
      }

      state.processedCount++;
      updateProgressUI(state.processedCount, state.queue.length);
      // Append only the newly finished card — never rebuild the whole grid,
      // so previously rendered cards/thumbnails/animations stay untouched.
      appendResultCard(newResult);
      updateHeroStats();

      if (i < state.queue.length - 1) {
        await sleep(state.delayMs);
      }
    }

    finishRun();
  }

  function setRunningUI(running) {
    btnStart.disabled = running;
    btnStart.classList.toggle("loading", running);
    btnPause.disabled = !running;
    btnStop.disabled = !running;
    btnResume.hidden = true;
    btnPause.hidden = false;
    inputText.disabled = running;
    // Hide while running; finishRun() reveals it once the batch is done.
    if (running) {
      btnCopyAllAvailable.hidden = true;
      btnOpenCreateService.hidden = true;
    }
  }

  function setStatusPill(kind) {
    const pill = $("#statusPill");
    pill.className = "status-pill " + kind;
    pill.textContent =
      kind === "running"
        ? "Running"
        : kind === "paused"
          ? "Paused"
          : kind === "done"
            ? "Complete"
            : "Idle";
  }

  function setCurrent(url, statusText, thumb) {
    $("#currentLink").textContent = url;
    $("#currentStatus").textContent = statusText;
    const img = $("#currentThumb");
    const placeholder = $("#currentThumbPlaceholder");
    if (thumb) {
      img.src = thumb;
      img.hidden = false;
      placeholder.hidden = true;
    } else {
      img.hidden = true;
      placeholder.hidden = false;
    }
  }

  function updateProgressUI(processed, total) {
    const pct = total ? Math.round((processed / total) * 100) : 0;
    $("#progressFill").style.width = pct + "%";
    $("#pProcessed").textContent = processed;
    $("#pRemaining").textContent = Math.max(0, total - processed);

    const elapsedSec = (Date.now() - state.startTime) / 1000;
    $("#pElapsed").textContent = formatDuration(elapsedSec);

    const speed = processed > 0 ? processed / elapsedSec : 0;
    $("#pSpeed").textContent = speed > 0 ? speed.toFixed(2) + "/s" : "—";

    const remaining = total - processed;
    const eta = speed > 0 ? remaining / speed : null;
    $("#pETA").textContent =
      eta !== null && isFinite(eta) ? formatDuration(eta) : "—";
  }

  function formatDuration(seconds) {
    if (seconds < 60) return Math.round(seconds) + "s";
    const m = Math.floor(seconds / 60);
    const s = Math.round(seconds % 60);
    return m + "m " + s + "s";
  }

  function finishRun() {
    state.running = false;
    setRunningUI(false);
    setStatusPill(state.stopped ? "idle" : "done");
    renderSummary();
    updateHeroStats();
    // Reveal after the batch finishes (hidden again at next run start via setRunningUI).
    const availableCount = state.results.filter(
      (r) => r.status === "available",
    ).length;
    btnCopyAllAvailable.hidden = availableCount === 0;
    btnOpenCreateService.hidden = availableCount === 0;
    toast(
      state.stopped ? "Stopped early" : "Batch complete",
      state.stopped ? "info" : "success",
    );
    if ($("#settingSound").checked && !state.stopped) {
      playChime();
    }
  }

  function playChime() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.08, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
      osc.start();
      osc.stop(ctx.currentTime + 0.5);
    } catch (e) {
      /* audio not available, ignore */
    }
  }

  btnStart.addEventListener("click", startRun);
  btnPause.addEventListener("click", () => {
    state.paused = true;
    btnPause.hidden = true;
    btnResume.hidden = false;
    btnResume.disabled = false;
    setStatusPill("paused");
  });
  btnResume.addEventListener("click", () => {
    state.paused = false;
    btnResume.hidden = true;
    btnPause.hidden = false;
    setStatusPill("running");
  });
  btnStop.addEventListener("click", () => {
    state.stopped = true;
    state.paused = false;
  });

  // =========================================================================
  // Hero stats (animated counters)
  // =========================================================================
  function animateCounter(el, to) {
    const from = parseInt(el.getAttribute("data-count") || "0", 10);
    const duration = 500;
    const start = performance.now();
    function step(now) {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      const val = Math.round(from + (to - from) * eased);
      el.textContent = el.id === "statRateHero" ? val + "%" : String(val);
      if (t < 1) requestAnimationFrame(step);
      else el.setAttribute("data-count", String(to));
    }
    requestAnimationFrame(step);
  }

  function updateHeroStats() {
    const total = state.results.length;
    const available = state.results.filter(
      (r) => r.status === "available",
    ).length;
    const broken = state.results.filter((r) => r.status === "error").length;
    const rate = total ? Math.round((available / total) * 100) : 0;
    animateCounter($("#statTotalHero"), total);
    animateCounter($("#statAvailHero"), available);
    animateCounter($("#statBrokenHero"), broken);
    animateCounter($("#statRateHero"), rate);
  }

  // =========================================================================
  // Summary panel
  // =========================================================================
  function renderSummary() {
    const total = state.results.length;
    const available = state.results.filter(
      (r) => r.status === "available",
    ).length;
    const broken = total - available;
    const elapsedSec = (Date.now() - state.startTime) / 1000;
    const speed = total > 0 ? total / elapsedSec : 0;
    const rate = total ? Math.round((available / total) * 100) : 0;

    document.dispatchEvent(new CustomEvent("tikverify:resultsChanged"));

    $("#sTotal").textContent = total;
    $("#sAvailable").textContent = available;
    $("#sBroken").textContent = broken;
    $("#sDuplicates").textContent = state.duplicatesRemoved || 0;
    $("#sNoUrl").textContent = state.linesWithoutUrl || 0;
    $("#sTime").textContent = formatDuration(elapsedSec);
    $("#sSpeed").textContent = speed.toFixed(2) + "/s";
    $("#sRate").textContent = rate + "%";
    summaryPanel.hidden = false;
  }

  // =========================================================================
  // Results rendering, filters, search
  // =========================================================================
  const cardTemplate = $("#cardTemplate");
  // Maps a result object (by reference) to the DOM node already rendered for
  // it, so completed results are appended once and never rebuilt/reloaded.
  const cardNodeMap = new Map();

  function formatStat(v) {
    if (v === null || v === undefined || v === "N/A") return "N/A";
    const n = Number(v);
    if (isNaN(n)) return String(v);
    if (n >= 1e6) return (n / 1e6).toFixed(1).replace(/\.0$/, "") + "M";
    if (n >= 1e3) return (n / 1e3).toFixed(1).replace(/\.0$/, "") + "K";
    return String(n);
  }

  function matchesFilter(r) {
    switch (state.activeFilter) {
      case "available":
        return r.status === "available";
      case "broken":
        return r.status === "error";
      case "missing":
        return (
          r.status === "available" &&
          (r.views === "N/A" || r.likes === "N/A" || r.comments === "N/A")
        );
      default:
        return true;
    }
  }

  function matchesSearch(r) {
    if (!state.searchTerm) return true;
    const term = state.searchTerm.toLowerCase();
    return (
      (r.url || "").toLowerCase().includes(term) ||
      (r.title || "").toLowerCase().includes(term)
    );
  }

  function getVisibleResults() {
    let list = state.results.filter(
      (r) => matchesFilter(r) && matchesSearch(r),
    );
    if (state.activeFilter === "newest")
      list = list.slice().sort((a, b) => b.order - a.order);
    else if (state.activeFilter === "oldest")
      list = list.slice().sort((a, b) => a.order - b.order);
    else list = list.slice().sort((a, b) => a.order - b.order);
    return list;
  }

  // Builds (but does not insert) the DOM node for a single result. Reused by
  // both the full rebuild path and the incremental append path so a given
  // result's card, thumbnail, and animation are only ever created once.
  function buildCardNode(r) {
    const node = cardTemplate.content.cloneNode(true);
    const card = node.querySelector(".result-card");
    const img = node.querySelector(".card-thumb");
    const badge = node.querySelector(".status-badge");
    const title = node.querySelector(".card-title");
    const urlEl = node.querySelector(".card-url");
    const errorEl = node.querySelector(".card-error");
    const openBtn = node.querySelector(".card-open");
    const copyBtn = node.querySelector(".card-copy");

    img.src = r.thumbnail || "";
    img.alt = r.title || r.url;
    if (!r.thumbnail) img.classList.add("skeleton");

    if (r.status === "available") {
      const missing =
        r.views === "N/A" || r.likes === "N/A" || r.comments === "N/A";
      badge.textContent = missing ? "Missing Stats" : "Available";
      badge.classList.toggle("missing", missing);
    } else {
      badge.textContent = "Broken";
      badge.classList.add("broken");
    }

    title.textContent = r.title || r.url;
    urlEl.textContent = r.url;
    urlEl.href = r.url;
    openBtn.href = r.url;

    card.querySelector(".views b").textContent = formatStat(r.views);
    card.querySelector(".likes b").textContent = formatStat(r.likes);
    card.querySelector(".comments b").textContent = formatStat(r.comments);

    if (r.error) {
      errorEl.textContent = r.error;
    } else {
      errorEl.remove();
    }

    copyBtn.addEventListener("click", () => copyText(r.url, "URL copied"));

    cardNodeMap.set(r, card);
    return card;
  }

  // Full rebuild: used only when the visible set can change for reasons
  // other than "a new result was appended" (filter chip, search, new run).
  // Reuses already-built card nodes where possible so existing thumbnails
  // and cards are never recreated or re-animated even on a full rebuild.
  function renderResults() {
    const visible = getVisibleResults();
    emptyState.hidden = visible.length > 0;

    const frag = document.createDocumentFragment();
    visible.forEach((r) => {
      const node = cardNodeMap.get(r) || buildCardNode(r);
      frag.appendChild(node);
    });
    resultsGrid.replaceChildren(frag);
  }

  // Incremental update: called once per newly completed result while a run
  // is in progress. Never touches existing cards — only builds and inserts
  // the one new card, in the correct position for the active sort/filter.
  function appendResultCard(r) {
    if (!matchesFilter(r) || !matchesSearch(r)) return;

    const node = buildCardNode(r);
    if (state.activeFilter === "newest") {
      resultsGrid.prepend(node);
    } else {
      resultsGrid.appendChild(node);
    }
    // Only flip empty state once a card has actually been inserted; a
    // non-matching result must not hide the "no results" placeholder.
    emptyState.hidden = true;
  }

  $("#filterChips").addEventListener("click", (e) => {
    const chip = e.target.closest(".chip");
    if (!chip) return;
    $$(".chip").forEach((c) => c.classList.remove("active"));
    chip.classList.add("active");
    state.activeFilter = chip.getAttribute("data-filter");
    renderResults();
  });

  $$(".vtab").forEach((tab) => {
    tab.addEventListener("click", () => {
      $$(".vtab").forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");
      state.activeView = tab.getAttribute("data-view");
      resultsGrid.classList.toggle(
        "gallery-view",
        state.activeView === "gallery",
      );
      resultsGrid.classList.toggle("cards-view", state.activeView === "cards");
    });
  });

  function bindSearch(input) {
    input.addEventListener("input", () => {
      state.searchTerm = input.value.trim();
      renderResults();
    });
  }
  bindSearch($("#resultsSearch"));
  bindSearch($("#topSearch"));

  // =========================================================================
  // Copy / export
  // =========================================================================
  function copyText(text, successMsg) {
    navigator.clipboard.writeText(text).then(
      () => toast(successMsg, "success"),
      () => toast("Copy failed", "error"),
    );
  }

  function downloadBlob(content, filename, mime) {
    const blob = new Blob([content], { type: mime });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
  }

  const btnCopyAllAvailable = $("#btnCopyAllAvailable");

  btnCopyAllAvailable.addEventListener("click", () => {
    const list = state.results
      .filter((r) => r.status === "available")
      .map((r) => r.url);
    if (!list.length) return toast("No available links", "error");
    copyText(
      list.join("\n"),
      list.length + " available links copied to clipboard.",
    );
  });

  $("#btnCopyAvailable").addEventListener("click", () => {
    const list = state.results
      .filter((r) => r.status === "available")
      .map((r) => r.url);
    if (!list.length) return toast("No available links yet", "error");
    copyText(list.join("\n"), list.length + " available links copied");
  });

  $("#btnCopyBroken").addEventListener("click", () => {
    const list = state.results
      .filter((r) => r.status === "error")
      .map((r) => r.url);
    if (!list.length) return toast("No broken links yet", "error");
    copyText(list.join("\n"), list.length + " broken links copied");
  });

  $("#btnDownloadTxt").addEventListener("click", () => {
    const lines = state.results.map(
      (r) =>
        r.url + "\t" + r.status.toUpperCase() + (r.error ? "\t" + r.error : ""),
    );
    downloadBlob(lines.join("\n"), "tikverify-results.txt", "text/plain");
  });

  $("#btnDownloadCsv").addEventListener("click", () => {
    const header = [
      "URL",
      "Status",
      "Title",
      "Views",
      "Likes",
      "Comments",
      "Error",
    ];
    const rows = state.results.map((r) => [
      r.url,
      r.status,
      r.title || "",
      r.views ?? "",
      r.likes ?? "",
      r.comments ?? "",
      r.error || "",
    ]);
    const csv = [header]
      .concat(rows)
      .map((row) =>
        row
          .map((cell) => '"' + String(cell).replace(/"/g, '""') + '"')
          .join(","),
      )
      .join("\n");
    downloadBlob(csv, "tikverify-results.csv", "text/csv");
  });

  $("#btnDownloadJson").addEventListener("click", () => {
    downloadBlob(
      JSON.stringify(state.results, null, 2),
      "tikverify-results.json",
      "application/json",
    );
  });

  $("#btnExportHtml").addEventListener("click", () => {
    const rows = state.results
      .map((r) => {
        const thumb = r.thumbnail ? escapeHtml(r.thumbnail) : "";
        const imgTag = thumb
          ? `<img src="${thumb}" style="width:50px;height:66px;object-fit:cover;border-radius:6px" onerror="this.style.display='none'" alt="">`
          : "";
        const safeUrl = escapeHtml(r.url);
        return `<tr>
        <td>${imgTag}</td>
        <td>${escapeHtml(r.title || "")}</td>
        <td><a href="${safeUrl}">${safeUrl}</a></td>
        <td>${escapeHtml(formatStat(r.views))}</td>
        <td>${escapeHtml(formatStat(r.likes))}</td>
        <td>${escapeHtml(formatStat(r.comments))}</td>
        <td>${escapeHtml(r.status)}</td>
      </tr>`;
      })
      .join("");
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>TikVerify Report</title>
      <style>body{font-family:sans-serif;padding:24px;background:#0a0b12;color:#eef0f8}
      table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #2a2c3c;text-align:left;font-size:13px}
      th{color:#9aa0b4;text-transform:uppercase;font-size:11px}</style></head>
      <body><h1>TikVerify Report</h1><p>${escapeHtml(String(state.results.length))} links checked</p>
      <table><thead><tr><th>Thumb</th><th>Title</th><th>URL</th><th>Views</th><th>Likes</th><th>Comments</th><th>Status</th></tr></thead>
      <tbody>${rows}</tbody></table></body></html>`;
    downloadBlob(html, "tikverify-report.html", "text/html");
  });

  $("#btnExportPdf").addEventListener("click", () => {
    const w = window.open("", "_blank");
    if (!w) {
      toast("Pop-up blocked. Allow pop-ups to export PDF.", "error");
      return;
    }
    const rows = state.results
      .map((r) => {
        const safeUrl = escapeHtml(r.url);
        return `<tr>
        <td>${escapeHtml(r.title || "")}</td>
        <td><a href="${safeUrl}">${safeUrl}</a></td>
        <td>${escapeHtml(formatStat(r.views))}</td>
        <td>${escapeHtml(formatStat(r.likes))}</td>
        <td>${escapeHtml(formatStat(r.comments))}</td>
        <td>${escapeHtml(r.status)}</td>
      </tr>`;
      })
      .join("");
    const checkedOn = escapeHtml(new Date().toLocaleString());
    const count = escapeHtml(String(state.results.length));
    w.document.write(`<!DOCTYPE html><html><head><title>TikVerify Report</title>
      <style>body{font-family:Arial,sans-serif;padding:24px;color:#111}
      table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #ccc;text-align:left;font-size:12px}</style>
      </head><body><h1>TikVerify Report</h1><p>${count} links checked on ${checkedOn}</p>
      <table><thead><tr><th>Title</th><th>URL</th><th>Views</th><th>Likes</th><th>Comments</th><th>Status</th></tr></thead>
      <tbody>${rows}</tbody></table></body></html>`);
    w.document.close();
    w.focus();
    setTimeout(() => w.print(), 300);
  });

  function escapeHtml(str) {
    return String(str).replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  }

  // =========================================================================
  // Create Service — SMM panel bulk order list generator
  // Price Per 1000 is entered directly in BDT — no currency conversion.
  // =========================================================================
  (function () {
    const CS_STORAGE_KEY = "tikverify_service_history";

    let csMode = "fixed"; // 'fixed' | 'random'
    let csHistory = []; // [{ id: string, price: string }, …]

    // -- DOM refs --------------------------------------------------------
    const csServiceId = $("#csServiceId");
    const csPricePer1k = $("#csPricePer1k");
    const csQtyFixed = $("#csQtyFixed");
    const csQtyMin = $("#csQtyMin");
    const csQtyMax = $("#csQtyMax");
    const csFixedBlock = $("#csFixedBlock");
    const csRandomBlock = $("#csRandomBlock");
    const csHistoryPanel = $("#csHistoryPanel");
    const csHistoryList = $("#csHistoryList");
    const csHistoryEmpty = $("#csHistoryEmpty");
    const csSummary = $("#csSummary");
    const csOutputSection = $("#csOutputSection");
    const csOutput = $("#csOutput");
    const csBtnCopyOutput = $("#csBtnCopyOutput");
    const csBtnDownloadOutput = $("#csBtnDownloadOutput");
    const csUrlCount = $("#csUrlCount");
    const csOutputLines = $("#csOutputLines");

    // -- History helpers -------------------------------------------------
    function csLoad() {
      try {
        csHistory = JSON.parse(localStorage.getItem(CS_STORAGE_KEY) || "[]");
      } catch {
        csHistory = [];
      }
      if (!Array.isArray(csHistory)) csHistory = [];
    }

    function csPersist() {
      localStorage.setItem(CS_STORAGE_KEY, JSON.stringify(csHistory));
    }

    // Insert / update an entry and move it to front (most-recently used).
    function csUpsert(id, price) {
      const idx = csHistory.findIndex((h) => h.id === id);
      if (idx >= 0) csHistory.splice(idx, 1);
      csHistory.unshift({ id, price });
      if (csHistory.length > 50) csHistory.pop();
      csPersist();
      csRenderHistory();
    }

    function csDelete(id) {
      csHistory = csHistory.filter((h) => h.id !== id);
      csPersist();
      csRenderHistory();
    }

    function csRenderHistory() {
      csHistoryList.innerHTML = "";
      csHistoryEmpty.hidden = csHistory.length > 0;

      csHistory.forEach((item) => {
        const row = document.createElement("div");
        row.className = "cs-history-item";

        row.innerHTML =
          '<span class="cs-hi-content">' +
          '<span class="cs-hi-id">' +
          escapeHtml(item.id) +
          "</span>" +
          (item.price
            ? '<span class="cs-hi-arrow">→</span>' +
              '<span class="cs-hi-price">' +
              escapeHtml(item.price) +
              " BDT/1k</span>"
            : "") +
          "</span>" +
          '<span class="cs-hi-actions">' +
          '<button class="cs-hi-btn cs-hi-edit" title="Edit">' +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>' +
          "</button>" +
          '<button class="cs-hi-btn cs-hi-del" title="Delete">' +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
          "</button>" +
          "</span>";

        // Select item → fill fields
        row.querySelector(".cs-hi-content").addEventListener("click", () => {
          csServiceId.value = item.id;
          if (item.price) csPricePer1k.value = item.price;
          csHistoryPanel.hidden = true;
          csUpdateSummary();
        });

        // Edit → inline form
        row.querySelector(".cs-hi-edit").addEventListener("click", (e) => {
          e.stopPropagation();
          csShowEditForm(row, item);
        });

        // Delete
        row.querySelector(".cs-hi-del").addEventListener("click", (e) => {
          e.stopPropagation();
          csDelete(item.id);
        });

        csHistoryList.appendChild(row);
      });
    }

    function csShowEditForm(row, item) {
      row.innerHTML =
        '<div class="cs-hi-edit-form">' +
        '<input class="cs-input" type="number" value="' +
        escapeHtml(item.id) +
        '" placeholder="Service ID" min="1" style="max-width:110px">' +
        '<input class="cs-input" type="number" value="' +
        escapeHtml(item.price || "") +
        '" placeholder="Price/1k" step="0.01" min="0" style="max-width:100px">' +
        '<button class="cs-hi-btn cs-hi-save" title="Save">✓</button>' +
        '<button class="cs-hi-btn cs-hi-cancel" title="Cancel">✕</button>' +
        "</div>";

      const idInput = row.querySelector('input[placeholder="Service ID"]');
      const priceInput = row.querySelector('input[placeholder="Price/1k"]');

      row.querySelector(".cs-hi-save").addEventListener("click", () => {
        const newId = idInput.value.trim();
        if (!newId) return;
        csHistory = csHistory.filter((h) => h.id !== item.id);
        csHistory.unshift({ id: newId, price: priceInput.value.trim() });
        csPersist();
        csRenderHistory();
      });
      row
        .querySelector(".cs-hi-cancel")
        .addEventListener("click", () => csRenderHistory());
    }

    // -- Parsing helpers ------------------------------------------------
    // Source of truth: verified AVAILABLE links from the main checker's results — never re-pasted.
    function csGetUrls() {
      return state.results
        .filter((r) => r.status === "available")
        .map((r) => r.url);
    }

    function csRandomInt(min, max) {
      return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function csGetQtys(urlCount) {
      if (csMode === "fixed") {
        const q = parseInt(csQtyFixed.value, 10);
        return q > 0 ? Array(urlCount).fill(q) : null;
      }
      const min = parseInt(csQtyMin.value, 10);
      const max = parseInt(csQtyMax.value, 10);
      if (!(min > 0) || !(max > 0) || min > max) return null;
      return Array.from({ length: urlCount }, () => csRandomInt(min, max));
    }

    // -- Live summary ---------------------------------------------------
    function csUpdateSummary() {
      const urls = csGetUrls();
      const count = urls.length;
      const price = parseFloat(csPricePer1k.value) || 0;

      csUrlCount.textContent =
        count > 0
          ? "(" + count + " available)"
          : "(none yet — run the checker first)";

      if (count === 0) {
        csSummary.hidden = true;
        return;
      }
      csSummary.hidden = false;

      const outputLines = csOutputSection.hidden
        ? []
        : csOutput.value.split("\n").filter(Boolean);
      const generated = outputLines.length;
      const totalQty = outputLines.reduce(
        (s, line) => s + (parseInt(line.split("|")[2], 10) || 0),
        0,
      );
      const hasCost = price > 0 && totalQty > 0;
      const totalBdt = hasCost ? (totalQty / 1000) * price : 0;

      $("#csSumVideos").textContent = count.toLocaleString();
      $("#csSumOrders").textContent =
        generated > 0 ? generated.toLocaleString() : "—";
      $("#csSumQty").textContent =
        totalQty > 0 ? totalQty.toLocaleString() : "—";
      $("#csSumMode").textContent = csMode === "fixed" ? "Fixed" : "Random";
      $("#csSumPrice").textContent =
        price > 0 ? price.toFixed(2) + " BDT" : "—";

      $("#csSumBdt").textContent = hasCost
        ? "\u09F3" +
          totalBdt.toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })
        : "৳—";
    }

    // -- Generate -------------------------------------------------------
    function csGenerate() {
      const serviceId = csServiceId.value.trim();
      const urls = csGetUrls();

      if (!serviceId) {
        toast("Enter a Service ID", "error");
        return;
      }
      if (!urls.length) {
        toast("No AVAILABLE videos yet — run the checker first", "error");
        return;
      }

      const qtys = csGetQtys(urls.length);
      if (!qtys) {
        toast(
          csMode === "fixed"
            ? "Enter a quantity"
            : "Enter a valid min/max quantity range",
          "error",
        );
        return;
      }

      const lines = urls.map((url, i) => serviceId + "|" + url + "|" + qtys[i]);

      csOutput.value = lines.join("\n");
      csOutputSection.hidden = false;
      csOutputLines.textContent =
        "(" + lines.length + " line" + (lines.length !== 1 ? "s" : "") + ")";
      csBtnCopyOutput.hidden = false;
      csBtnDownloadOutput.hidden = false;

      // Save to history (price optional)
      csUpsert(serviceId, csPricePer1k.value.trim());

      csUpdateSummary();
      toast(
        "Generated " + lines.length + " line" + (lines.length !== 1 ? "s" : ""),
        "success",
      );
      csOutputSection.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    // -- Wire up events ------------------------------------------------
    csLoad();
    csRenderHistory();

    // History toggle
    $("#csHistoryToggle").addEventListener("click", (e) => {
      e.stopPropagation();
      csHistoryPanel.hidden = !csHistoryPanel.hidden;
    });
    document.addEventListener("click", (e) => {
      if (
        !e.target.closest("#csHistoryToggle") &&
        !e.target.closest("#csHistoryPanel")
      ) {
        csHistoryPanel.hidden = true;
      }
    });

    // Clear all history
    $("#csClearHistory").addEventListener("click", () => {
      csHistory = [];
      csPersist();
      csRenderHistory();
    });

    // Mode toggle (Fixed / Random)
    $$(".cs-mode-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        $$(".cs-mode-btn").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        csMode = btn.dataset.mode;
        csFixedBlock.hidden = csMode !== "fixed";
        csRandomBlock.hidden = csMode !== "random";
        csUpdateSummary();
      });
    });

    // Live summary on any input change
    [csServiceId, csPricePer1k, csQtyFixed, csQtyMin, csQtyMax].forEach(
      (el) => {
        el.addEventListener("input", csUpdateSummary);
      },
    );

    // Generate
    $("#csBtnGenerate").addEventListener("click", csGenerate);

    // Copy output
    csBtnCopyOutput.addEventListener("click", () => {
      copyText(csOutput.value, "Output copied to clipboard");
    });

    // Download output as TXT
    csBtnDownloadOutput.addEventListener("click", () => {
      downloadBlob(csOutput.value, "tikverify-orders.txt", "text/plain");
    });

    // Clear / reset
    $("#csBtnReset").addEventListener("click", () => {
      csServiceId.value = "";
      csPricePer1k.value = "";
      csQtyFixed.value = "";
      csQtyMin.value = "";
      csQtyMax.value = "";
      csOutput.value = "";
      csOutputSection.hidden = true;
      csBtnCopyOutput.hidden = true;
      csBtnDownloadOutput.hidden = true;
      csSummary.hidden = true;
      csUrlCount.textContent = "";
      csHistoryPanel.hidden = true;
      csMode = "fixed";
      $$(".cs-mode-btn").forEach((b) =>
        b.classList.toggle("active", b.dataset.mode === "fixed"),
      );
      csFixedBlock.hidden = false;
      csRandomBlock.hidden = true;
    });

    // Keep the "Available Videos" count in sync as the checker runs / results change.
    document.addEventListener("tikverify:resultsChanged", csUpdateSummary);
  })();
})();
