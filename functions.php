<?php
/**
 * functions.php
 * All backend logic: URL extraction, validation, multi-method scraping
 * with automatic fallback chain, and small utilities.
 */

require_once __DIR__ . '/config.php';

// =========================================================================
// 1. URL extraction & validation
// =========================================================================

/**
 * Extract every TikTok URL from a raw block of text, removing duplicates
 * while preserving order (mirrors the original Python `extract_urls`).
 */
function extract_urls(string $text): array
{
    $pattern = '#https?://(?:'
        . '(?:www\.)?tiktok\.com/@[^/\s]+/video/\d+[^\s|]*'
        . '|(?:vm|vt|m)\.tiktok\.com/[\w]+[^\s|]*'
        . '|(?:www\.)?tiktok\.com/t/[\w]+[^\s|]*'
        . ')#i';

    preg_match_all($pattern, $text, $matches);

    $seen = [];
    $unique = [];
    foreach ($matches[0] as $url) {
        $clean = trim(explode('|', $url)[0]);
        if (!isset($seen[$clean])) {
            $seen[$clean] = true;
            $unique[] = $clean;
        }
    }
    return $unique;
}

/** Count how many non-empty input lines contained no extractable URL. */
function count_lines_without_url(string $text): int
{
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $count = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (empty(extract_urls($line))) {
            $count++;
        }
    }
    return $count;
}

function is_valid_tiktok_url(string $url): bool
{
    if (strlen($url) > MAX_URL_LENGTH) {
        return false;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return false;
    }
    return (bool) preg_match('/(^|\.)tiktok\.com$/i', $host);
}

// =========================================================================
// 2. Low-level HTTP helper
// =========================================================================

/**
 * Strict SSRF allowlist check. Ensures the host is an exact TikTok domain
 * and the scheme is https only. Call this on every URL before fetching,
 * and again on the effective (post-redirect) URL.
 */
function is_allowed_remote_url(string $url): bool
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https') {
        return false;
    }
    $host = strtolower($parts['host'] ?? '');
    // Only these TikTok-owned domains are permitted.
    $allowed = [
        'tiktok.com', 'www.tiktok.com', 'vm.tiktok.com',
        'vt.tiktok.com', 'm.tiktok.com',
    ];
    if (in_array($host, $allowed, true)) {
        return true;
    }
    // Allow TikTok CDN domains for thumbnails and oEmbed.
    if (preg_match('/^[a-z0-9\-]+\.tiktokcdn\.com$/i', $host)) {
        return true;
    }
    return false;
}

/**
 * Fetch a URL with real redirect-following, enforced hop-by-hop against the
 * SSRF allowlist, browser-like headers, and a persistent per-session cookie
 * jar so anti-bot cookies set on one request (e.g. the short-link redirect)
 * are sent back on the next (e.g. the canonical page fetch).
 */
function http_get(string $url, array $extraHeaders = [], bool $followRedirects = true): array
{
    if (!is_allowed_remote_url($url)) {
        return ['ok' => false, 'body' => '', 'http_code' => 0, 'effective_url' => $url, 'error' => 'URL not in allowlist'];
    }

    $ch = curl_init();
    $userAgent = USER_AGENTS[array_rand(USER_AGENTS)];
    $cookieJar = tiktok_cookie_jar_path();

    $headers = array_merge([
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    ], $extraHeaders);

    // Aborts the transfer the instant a redirect targets a disallowed host,
    // so SSRF protection holds even though curl follows redirects natively.
    // Location headers are frequently relative (e.g. "/about?lang=en"), so
    // they must be resolved against the current effective URL before the
    // allowlist check — otherwise parse_url() finds no host and every
    // relative redirect is (incorrectly) rejected.
    $blockedRedirect = false;
    $headerFn = function ($curlHandle, $headerLine) use (&$blockedRedirect) {
        if (preg_match('/^Location:\s*(.+)$/i', trim($headerLine), $m)) {
            $currentUrl = curl_getinfo($curlHandle, CURLINFO_EFFECTIVE_URL) ?: '';
            $target = resolve_relative_url($currentUrl, trim($m[1]));
            if (!is_allowed_remote_url($target)) {
                $blockedRedirect = true;
                return -1; // abort the transfer
            }
        }
        return strlen($headerLine);
    };

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $followRedirects,
        CURLOPT_MAXREDIRS => MAX_REDIRECTS,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_HEADERFUNCTION => $headerFn,
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    $error = curl_error($ch);
    curl_close($ch);

    if ($blockedRedirect) {
        $error = 'Redirect target not in allowlist';
        $httpCode = 0;
    }

    return [
        'ok' => !$blockedRedirect && $body !== false && $body !== '' && $httpCode >= 200 && $httpCode < 400,
        'body' => $body !== false ? $body : '',
        'http_code' => $httpCode,
        'effective_url' => $effectiveUrl,
        'error' => $error,
    ];
}

/** Extract the numeric TikTok video ID from any canonical/short video URL. */
function extract_video_id(string $url): ?string
{
    if (preg_match('/\/video\/(\d+)/', $url, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Resolve a possibly-relative URL reference against a base URL, following
 * the same rules browsers use for HTTP `Location` headers (RFC 3986 §5.3,
 * simplified): absolute URLs pass through unchanged; protocol-relative
 * (`//host/path`) inherit the base scheme; absolute-path (`/path`) replace
 * the base path; everything else (relative path, query-only, empty) is
 * merged against the base's directory.
 */
function resolve_relative_url(string $base, string $ref): string
{
    if ($ref === '') {
        return $base;
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $ref)) {
        return $ref; // already absolute
    }

    $baseParts = parse_url($base);
    if (!$baseParts || !isset($baseParts['scheme'], $baseParts['host'])) {
        return $ref; // can't resolve without a valid base; let allowlist reject it
    }
    $scheme = $baseParts['scheme'];
    $authority = $baseParts['host'] . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '');

    if (str_starts_with($ref, '//')) {
        return $scheme . ':' . $ref; // protocol-relative
    }
    if ($ref[0] === '/') {
        return $scheme . '://' . $authority . $ref; // absolute-path
    }
    if ($ref[0] === '?') {
        $basePath = $baseParts['path'] ?? '/';
        return $scheme . '://' . $authority . $basePath . $ref; // query-only
    }
    if ($ref[0] === '#') {
        return $scheme . '://' . $authority . ($baseParts['path'] ?? '/') . (isset($baseParts['query']) ? '?' . $baseParts['query'] : '') . $ref;
    }

    // Relative path: merge against the base's directory, then collapse
    // "." and ".." segments per RFC 3986.
    $basePath = $baseParts['path'] ?? '/';
    $dir = substr($basePath, 0, strrpos($basePath, '/') + 1) ?: '/';
    $merged = $dir . $ref;

    $inputSegments = explode('/', $merged);
    $outputSegments = [];
    foreach ($inputSegments as $seg) {
        if ($seg === '.') {
            continue;
        }
        if ($seg === '..') {
            array_pop($outputSegments);
            continue;
        }
        $outputSegments[] = $seg;
    }
    $resolvedPath = implode('/', $outputSegments);
    if ($resolvedPath === '' || $resolvedPath[0] !== '/') {
        $resolvedPath = '/' . $resolvedPath;
    }

    return $scheme . '://' . $authority . $resolvedPath;
}

/** Decode a raw (unquoted) JSON string fragment that still has JSON escapes. */
function unescape_json_fragment(string $s): string
{
    $decoded = json_decode('"' . $s . '"');
    return $decoded !== null ? $decoded : $s;
}

// =========================================================================
// 3. Resolution + scraping fallback chain
// =========================================================================

/**
 * Resolve short links (vm./vt./m.tiktok.com, /t/xxx) to their canonical
 * https://www.tiktok.com/@user/video/XXXXXXXX URL by following redirects.
 * Falls back to a manual HEAD-style probe if the GET didn't redirect for
 * some reason (e.g. TikTok occasionally serves an interstitial page).
 */
function resolve_canonical_url(string $url): ?string
{
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    $needsResolve = preg_match('/^(vm|vt|m)\.tiktok\.com$/i', $host) || strpos($url, '/t/') !== false;
    if (!$needsResolve) {
        return null;
    }

    $res = http_get($url, ['Referer: https://www.tiktok.com/'], true);
    $candidate = $res['effective_url'] ?? null;

    if ($candidate && $candidate !== $url && extract_video_id($candidate)) {
        return $candidate;
    }

    // The redirect chain may have landed on an interstitial m.tiktok.com page
    // that embeds the canonical URL in its HTML rather than an HTTP redirect.
    if ($res['ok'] && $res['body']) {
        if (preg_match('#https://www\.tiktok\.com/@[^"\'\\\\\s]+/video/\d+#', $res['body'], $m)) {
            return $m[0];
        }
    }

    // Still landed on a resolvable page with a video ID in the URL (e.g. an
    // m.tiktok.com video URL) even if it's not the canonical www. form —
    // that's good enough to proceed with scraping.
    if ($candidate && extract_video_id($candidate)) {
        return $candidate;
    }

    return null;
}

/** Method A: parse the __UNIVERSAL_DATA_FOR_REHYDRATION__ JSON blob (current TikTok pages). */
function fetch_via_universal_data(string $html): ?array
{
    if (!preg_match('/<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)<\/script>/s', $html, $m)) {
        return null;
    }
    $data = json_decode($m[1], true);
    if (!$data) {
        return null;
    }
    $scope = $data['__DEFAULT_SCOPE__'] ?? [];
    $detail = $scope['webapp.video-detail'] ?? null;
    $item = $detail['itemInfo']['itemStruct'] ?? null;
    if (!$item) {
        return null;
    }
    return normalize_item_struct($item);
}

/** Method B: parse the legacy SIGI_STATE JSON blob. */
function fetch_via_sigi_state(string $html, string $url): ?array
{
    if (!preg_match('/<script id="SIGI_STATE"[^>]*>(.*?)<\/script>/s', $html, $m)) {
        return null;
    }
    $data = json_decode($m[1], true);
    if (!$data) {
        return null;
    }
    $videoId = extract_video_id($url);
    $itemModule = $data['ItemModule'] ?? [];
    $item = $videoId && isset($itemModule[$videoId]) ? $itemModule[$videoId] : reset($itemModule);
    if (!$item) {
        return null;
    }
    return normalize_item_struct($item);
}

/** Method C: last-resort regex scraping of raw stat fields anywhere in the HTML. */
function fetch_via_regex_scrape(string $html): ?array
{
    $get = function (string $key) use ($html) {
        return preg_match('/"' . $key . '":(\d+)/', $html, $m) ? (int) $m[1] : null;
    };
    $views = $get('playCount');
    $likes = $get('diggCount');
    $comments = $get('commentCount');
    $shares = $get('shareCount');

    if ($views === null && $likes === null && $comments === null) {
        return null;
    }

    $title = null;
    if (preg_match('/"desc":"(.*?)","/', $html, $m)) {
        $title = unescape_json_fragment($m[1]);
    }
    $thumbnail = null;
    if (preg_match('/"originCover":"(.*?)"/', $html, $m)) {
        $thumbnail = unescape_json_fragment($m[1]);
    } elseif (preg_match('/"cover":"(.*?)"/', $html, $m)) {
        $thumbnail = unescape_json_fragment($m[1]);
    }

    return [
        'title' => $title ?: null,
        'author' => null,
        'thumbnail' => $thumbnail ?: null,
        'views' => $views,
        'likes' => $likes,
        'comments' => $comments,
        'shares' => $shares,
    ];
}

/** Normalize a TikTok `itemStruct` object into our flat result shape. */
function normalize_item_struct(array $item): array
{
    $stats = $item['stats'] ?? $item['statsV2'] ?? [];
    $video = $item['video'] ?? [];
    $author = $item['author'] ?? [];

    $numeric = function ($v) {
        return $v !== null && $v !== '' ? (int) $v : null;
    };

    return [
        'title' => $item['desc'] ?? null,
        'author' => $author['nickname'] ?? $author['uniqueId'] ?? null,
        'thumbnail' => $video['originCover'] ?? $video['cover'] ?? $video['dynamicCover'] ?? null,
        'views' => $numeric($stats['playCount'] ?? null),
        'likes' => $numeric($stats['diggCount'] ?? null),
        'comments' => $numeric($stats['commentCount'] ?? null),
        'shares' => $numeric($stats['shareCount'] ?? null),
    ];
}

/** Method D: oEmbed API — no stats, but very reliable for title/thumbnail. */
function fetch_via_oembed(string $url): ?array
{
    $res = http_get('https://www.tiktok.com/oembed?url=' . urlencode($url));
    if (!$res['ok']) {
        return null;
    }
    $data = json_decode($res['body'], true);
    if (!$data) {
        return null;
    }
    return [
        'title' => $data['title'] ?? null,
        'author' => $data['author_name'] ?? null,
        'thumbnail' => $data['thumbnail_url'] ?? null,
        'views' => null,
        'likes' => null,
        'comments' => null,
        'shares' => null,
    ];
}

/**
 * Master lookup: tries every method in order, keeps the best partial data,
 * and only reports fields as "N/A" if truly no method could supply them.
 */
function get_video_data(string $originalUrl): array
{
    $result = [
        'url' => $originalUrl,
        'status' => 'error',
        'method' => null,
        'video_id' => null,
        'title' => null,
        'author' => null,
        'thumbnail' => null,
        'views' => null,
        'likes' => null,
        'comments' => null,
        'shares' => null,
        'error' => null,
    ];

    if (!is_valid_tiktok_url($originalUrl)) {
        $result['error'] = 'Not a valid TikTok URL';
        return $result;
    }

    // Step 1: always resolve short links (vt./vm./m.tiktok.com, /t/xxx) to
    // their canonical https://www.tiktok.com/@user/video/XXXXXXXX form
    // *before* requesting metadata, so every URL shape scrapes identically.
    $url = $originalUrl;
    $canonical = resolve_canonical_url($url);
    if ($canonical) {
        $url = $canonical;
        $result['url'] = $canonical;
    }
    $result['video_id'] = extract_video_id($url);

    $page = http_get($url, ['Referer: https://www.tiktok.com/']);
    $lastError = $page['error'] ?: ('HTTP ' . $page['http_code']);

    // If the canonical page fetch failed outright (e.g. blocked, timed out)
    // but we do have a video ID, retry once against the m.tiktok.com mobile
    // page — it's frequently less aggressively bot-gated than www.
    if (!$page['ok'] && $result['video_id'] && strpos($url, 'm.tiktok.com') === false) {
        $mobileUrl = preg_replace('#^https://(www\.)?tiktok\.com#i', 'https://m.tiktok.com', $url);
        $retry = http_get($mobileUrl, ['Referer: https://www.tiktok.com/']);
        if ($retry['ok']) {
            $page = $retry;
            $url = $mobileUrl;
        }
    }

    $merge = function (array $data, string $method) use (&$result) {
        foreach (['title', 'author', 'thumbnail', 'views', 'likes', 'comments', 'shares'] as $field) {
            if ($result[$field] === null && ($data[$field] ?? null) !== null) {
                $result[$field] = $data[$field];
            }
        }
        $result['method'] = $result['method'] ? $result['method'] . '+' . $method : $method;
    };

    $haveCoreStats = function () use (&$result) {
        return $result['views'] !== null && $result['likes'] !== null && $result['comments'] !== null;
    };

    if ($page['ok']) {
        $html = $page['body'];

        // Method A (primary): current TikTok page JSON payload.
        $a = fetch_via_universal_data($html);
        if ($a) {
            $merge($a, 'universal_data');
        }

        // Method B: legacy page JSON payload, used to fill any gaps.
        if (!$haveCoreStats()) {
            $b = fetch_via_sigi_state($html, $url);
            if ($b) {
                $merge($b, 'sigi_state');
            }
        }

        // Method C: raw regex scan of the HTML for stat fields.
        if (!$haveCoreStats()) {
            $c = fetch_via_regex_scrape($html);
            if ($c) {
                $merge($c, 'regex_scrape');
            }
        }
    }

    // oEmbed is the absolute last resort: it never returns stats, so we only
    // reach for it when every scraping method above produced nothing at all
    // — not just missing title/thumbnail/views, but every field.
    $scrapedFields = ['title', 'author', 'thumbnail', 'views', 'likes', 'comments', 'shares'];
    $hasAnythingYet = false;
    foreach ($scrapedFields as $field) {
        if ($result[$field] !== null) {
            $hasAnythingYet = true;
            break;
        }
    }
    if (!$hasAnythingYet) {
        $d = fetch_via_oembed($originalUrl);
        if ($d) {
            $merge($d, 'oembed_fallback');
        }
    }

    $hasAnything = false;
    foreach ($scrapedFields as $field) {
        if ($result[$field] !== null) {
            $hasAnything = true;
            break;
        }
    }

    if ($hasAnything) {
        $result['status'] = 'available';
        $result['title'] = $result['title'] ?: '(title unavailable)';
        $result['views'] = $result['views'] ?? 'N/A';
        $result['likes'] = $result['likes'] ?? 'N/A';
        $result['comments'] = $result['comments'] ?? 'N/A';
    } else {
        $result['status'] = 'error';
        $result['error'] = $lastError ?: 'Unable to retrieve video data (all methods failed)';
    }

    if (defined('DEBUG_LOG_METHODS') && DEBUG_LOG_METHODS) {
        error_log(sprintf(
            '[TikVerify] url=%s resolved=%s video_id=%s method=%s status=%s views=%s likes=%s comments=%s',
            $originalUrl,
            $result['url'],
            $result['video_id'] ?? 'n/a',
            $result['method'] ?? 'none',
            $result['status'],
            is_int($result['views']) ? $result['views'] : ($result['views'] ?? 'null'),
            is_int($result['likes']) ? $result['likes'] : ($result['likes'] ?? 'null'),
            is_int($result['comments']) ? $result['comments'] : ($result['comments'] ?? 'null')
        ));
    }

    return $result;
}

// =========================================================================
// 4. Security helpers
// =========================================================================

function verify_csrf_token(?string $token): bool
{
    return $token !== null && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data);
    exit;
}
