<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$publicPath = __DIR__.DIRECTORY_SEPARATOR.'public';
$publicRealPath = realpath($publicPath);
$requestedFile = realpath($publicPath.$uri);

if ($uri !== '/' && $publicRealPath !== false && $requestedFile !== false && str_starts_with($requestedFile, $publicRealPath) && is_file($requestedFile)) {
    return false;
}

require_once $publicPath.DIRECTORY_SEPARATOR.'index.php';