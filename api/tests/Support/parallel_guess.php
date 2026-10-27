<?php

use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$content = getenv('GUESS_PAYLOAD') ?: '{}';

$request = Request::create('/api/auth/station-signup', 'POST', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
    'REMOTE_ADDR' => '127.0.0.1',
], $content);

$response = $kernel->handle($request);

fwrite(STDOUT, $response->getStatusCode()."\n".$response->getContent());

$kernel->terminate($request, $response);
