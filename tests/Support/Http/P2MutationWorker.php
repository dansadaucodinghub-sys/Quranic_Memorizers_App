<?php

declare(strict_types=1);

use Nyholm\Psr7\ServerRequest;
use Qmdb\Bootstrap\ApplicationFactory;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$encoded = stream_get_contents(STDIN);
$payload = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
if (!is_array($payload)) {
    throw new RuntimeException('Mutation worker payload is invalid.');
}
$path = $payload['path'] ?? null;
$fields = $payload['fields'] ?? null;
$cookies = $payload['cookies'] ?? null;
$headers = $payload['headers'] ?? null;
$peer = $payload['peer'] ?? null;
if (!is_string($path) || !is_array($fields) || !is_array($cookies) || !is_array($headers) || !is_string($peer)) {
    throw new RuntimeException('Mutation worker payload shape is invalid.');
}

$request = new ServerRequest(
    'POST',
    $path,
    $headers,
    http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
    '1.1',
    ['REMOTE_ADDR' => $peer],
);
$response = ApplicationFactory::fromCurrentProcess()
    ->createHttpRuntime()
    ->handle($request->withCookieParams($cookies));

echo json_encode([
    'status' => $response->getStatusCode(),
    'location' => $response->getHeaderLine('Location'),
], JSON_THROW_ON_ERROR);
