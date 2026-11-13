<?php

use Illuminate\Http\Request;

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$public = realpath(dirname(__DIR__).'/public');
$candidate = realpath($public.$uri);
if ($uri !== '/' && $candidate !== false && str_starts_with($candidate, $public.DIRECTORY_SEPARATOR) && is_file($candidate)) {
    return false;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require __DIR__.'/browser-qa-bootstrap.php';
$app->handleRequest(Request::capture());
