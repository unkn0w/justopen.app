<?php

require_once __DIR__ . '/engine.php';

$response = jo_route_request($_SERVER, $_GET, $_POST);

http_response_code((int) ($response['status'] ?? 200));

foreach (($response['headers'] ?? []) as $name => $value) {
    header($name . ': ' . $value);
}

if (($response['type'] ?? '') === 'redirect' && isset($response['location'])) {
    header('Location: ' . $response['location'], true, (int) ($response['status'] ?? 302));
    exit;
}

echo (string) ($response['body'] ?? '');
