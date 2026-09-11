<?php

// Test-only provider boundary. It records each keyed request in a shared file
// so separate PostgreSQL workers can prove the provider-call count.
$callFile = getenv('WHATSAPP_PROVIDER_CALL_FILE');
if (! is_string($callFile) || $callFile === '') {
    http_response_code(500);
    echo json_encode(['error' => 'missing call file']);
    exit;
}

$key = '';
foreach (getallheaders() ?: [] as $name => $value) {
    if (strcasecmp($name, 'Idempotency-Key') === 0) {
        $key = (string) $value;
        break;
    }
}
file_put_contents($callFile, $key.PHP_EOL, FILE_APPEND | LOCK_EX);
header('Content-Type: application/json');
echo json_encode(['messages' => [['id' => 'provider-'.substr(hash('sha256', $key), 0, 12)]]]);
