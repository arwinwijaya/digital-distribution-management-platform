<?php

// Small cross-platform worker used by concurrency feature tests. Keeping the
// request in a separate PHP process prevents Laravel's test client from
// nesting one request inside the other.

if ($argc !== 3) {
    fwrite(STDERR, "Usage: http_request.php request.json response.json\n");
    exit(2);
}

$request = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
];
foreach (($request['headers'] ?? []) as $name => $value) {
    $headers[] = $name.': '.$value;
}

$options = [
    'http' => [
        'method' => $request['method'] ?? 'GET',
        'header' => implode("\r\n", $headers),
        'content' => $request['body'] ?? '',
        'ignore_errors' => true,
        'timeout' => 45,
    ],
];

$body = file_get_contents($request['url'], false, stream_context_create($options));
$status = 0;
foreach ($http_response_header ?? [] as $header) {
    if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
        $status = (int) $matches[1];
    }
}

file_put_contents($argv[2], json_encode([
    'status' => $status,
    'body' => $body === false ? '' : $body,
], JSON_THROW_ON_ERROR));
