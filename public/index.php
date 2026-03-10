<?php

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Contracts\Http\Kernel $kernel */
$kernel = require_once __DIR__.'/../bootstrap/app.php';

$response = tap($kernel->handle(
    $request = Illuminate\Http\Request::capture()
))->send();

$kernel->terminate($request, $response);
