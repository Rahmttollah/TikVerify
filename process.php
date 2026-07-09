<?php
/**
 * process.php
 * AJAX endpoint. All requests are POST with header X-CSRF-Token.
 *
 * actions:
 *   - "check"  : body { url }              -> single video lookup (used for
 *                                             the live, pausable batch loop)
 *   - "stats"  : body { text }             -> quick extraction stats
 *                                             (total found / duplicates /
 *                                             lines without URL) without
 *                                             hitting the network
 */

require_once __DIR__ . '/functions.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!verify_csrf_token($token)) {
    json_response(['error' => 'Invalid or missing CSRF token'], 403);
}

$action = $input['action'] ?? '';

switch ($action) {
    case 'check':
        $url = trim((string) ($input['url'] ?? ''));
        if ($url === '') {
            json_response(['error' => 'Missing url'], 400);
        }
        $data = get_video_data($url);
        json_response(['result' => $data]);
        break;

    case 'stats':
        $text = (string) ($input['text'] ?? '');
        $allMatches = [];
        preg_match_all(
            '#https?://(?:(?:www\.)?tiktok\.com/@[^/\s]+/video/\d+[^\s|]*|(?:vm|vt|m)\.tiktok\.com/[\w]+[^\s|]*|(?:www\.)?tiktok\.com/t/[\w]+[^\s|]*)#i',
            $text,
            $allMatches
        );
        $rawCount = count($allMatches[0]);
        $unique = extract_urls($text);
        $withoutUrl = count_lines_without_url($text);
        json_response([
            'total_found' => $rawCount,
            'unique' => count($unique),
            'duplicates_removed' => max(0, $rawCount - count($unique)),
            'lines_without_url' => $withoutUrl,
            'urls' => $unique,
        ]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
