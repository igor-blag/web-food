<?php
// Router for PHP built-in server
// Serves both food-app/ (admin) and food/ (public)

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files as-is
$file = __DIR__ . $uri;
if (is_file($file)) {
    // Let PHP serve static files (css, js, xlsx, etc.)
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        readfile($file);
        return true;
    }
    if ($ext === 'php') {
        return false; // let PHP handle it
    }
    return false;
}

// Directory index
if (is_dir($file) && is_file($file . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = $uri . 'index.php';
    require $file . '/index.php';
    return true;
}

return false;
