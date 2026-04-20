<?php

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Block direct access to Vite build manifest — it leaks source file paths
// and hashed bundle names useful to an attacker (audit #14). Vite does not
// need it served to clients in prod (the compiled HTML already references
// hashed assets). Match both with and without leading slash collapsing.
$blockedPaths = [
    '/build/manifest.json',
    '/build/manifest',
    '/build/.vite/manifest.json',
];
foreach ($blockedPaths as $blocked) {
    if ($uri === $blocked) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header_remove('X-Powered-By');
        echo "Not Found";
        return true;
    }
}

// Apache mod_rewrite emulation for the built-in PHP server.
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
