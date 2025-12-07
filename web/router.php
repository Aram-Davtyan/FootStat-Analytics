<?php
// Router for PHP built-in server: serve static files, otherwise run Yii.
$file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
