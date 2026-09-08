<?php

// Minimal Laravel HTTP server router for concurrent feature tests. The project
// has no public/ front controller; each race participant runs this router in a
// separate PHP built-in-server process against the same test database.

ini_set('display_errors', '1');
error_reporting(E_ALL);
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/api/health') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'healthy']);
    exit;
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$request = Illuminate\Http\Request::capture();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
