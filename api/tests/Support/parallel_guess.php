<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$content = getenv('GUESS_PAYLOAD') ?: '{}';
$ip = getenv('GUESS_IP') ?: '127.0.0.1';

$request = Request::create('/api/auth/station-signup', 'POST', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
    'REMOTE_ADDR' => $ip,
], $content);

$response = $kernel->handle($request);

fwrite(STDOUT, $response->getStatusCode()."\n".$response->getContent());

$kernel->terminate($request, $response);
