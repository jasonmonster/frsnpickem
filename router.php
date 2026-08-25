<?php
// Dev-only router for `php -S` — serves real files as-is, routes everything
// else through index.php. Not used in production (nginx does this via
// try_files, see nginx.conf.example).
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}
require __DIR__ . '/index.php';
