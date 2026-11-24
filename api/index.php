<?php
// Vercel Serverless PHP Router
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = ltrim((string) $uri, '/');

// Default landing
if ($path === '' || $path === '/') {
    header("Location: login.html");
    exit();
}

$baseDir    = realpath(__DIR__ . '/..');
$targetFile = realpath($baseDir . '/' . $path);

// Security: Prevent directory traversal & protect sensitive configuration files
$blockedFiles = ['config.php', 'vercel.json', 'netlify.toml', 'Dockerfile', '.htaccess', '.env', '.dockerignore'];
if (
    !$targetFile ||
    strpos($targetFile, $baseDir) !== 0 ||
    in_array(basename($targetFile), $blockedFiles, true) ||
    strpos($targetFile, DIRECTORY_SEPARATOR . '.') !== false
) {
    http_response_code(404);
    die("File not found.");
}

// Execute PHP file in its expected root context
if (pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
    chdir($baseDir);
    $_SERVER['SCRIPT_FILENAME'] = $targetFile;
    $_SERVER['SCRIPT_NAME']     = '/' . $path;
    $_SERVER['PHP_SELF']        = '/' . $path;

    // Ensure 30-day session lifetime across Vercel serverless functions
    if (session_status() === PHP_SESSION_NONE) {
        @ini_set('session.gc_maxlifetime', '2592000');
        session_set_cookie_params([
            'lifetime' => 2592000,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) || !empty($_SERVER['VERCEL']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    require $targetFile;
    exit();
}

// Serve static assets if routed through serverless function
$mimes = [
    'css'  => 'text/css',
    'js'   => 'application/javascript',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'html' => 'text/html; charset=UTF-8',
    'svg'  => 'image/svg+xml'
];
$ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
if (isset($mimes[$ext])) {
    header('Content-Type: ' . $mimes[$ext]);
}
readfile($targetFile);
exit();
