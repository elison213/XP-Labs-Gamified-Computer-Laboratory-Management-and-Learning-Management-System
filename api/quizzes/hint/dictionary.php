<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../lib/Auth.php';

use XPLabs\Lib\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole('student');
$word = trim((string) ($_GET['word'] ?? ''));
$word = strtolower(preg_replace('/[^a-z0-9\- ]/i', '', $word));
if ($word === '') {
    http_response_code(400);
    echo json_encode(['error' => 'word is required']);
    exit;
}

$cacheDir = __DIR__ . '/../../../storage/cache/dictionary';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cacheKey = md5($word);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
$ttl = 86400;
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        echo json_encode(['success' => true, 'word' => $word, 'definition' => $cached['definition'] ?? '', 'source' => 'cache']);
        exit;
    }
}

$definition = '';
$url = 'https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($word);
$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 3,
        'header' => "Accept: application/json\r\n",
    ],
]);
$resp = @file_get_contents($url, false, $ctx);
if (is_string($resp) && $resp !== '') {
    $data = json_decode($resp, true);
    if (is_array($data) && isset($data[0]['meanings'][0]['definitions'][0]['definition'])) {
        $definition = (string) $data[0]['meanings'][0]['definitions'][0]['definition'];
    }
}
if ($definition === '') {
    $definition = 'No online definition available right now. Try context clues and word roots.';
}
@file_put_contents($cacheFile, json_encode(['word' => $word, 'definition' => $definition], JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true, 'word' => $word, 'definition' => $definition, 'source' => 'online']);
