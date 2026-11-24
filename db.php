<?php

function isLocalRequest(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $host = explode(':', $host)[0];

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}


function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }

    // Always HTTPS on Vercel
    if (!empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL'])) {
        return true;
    }

    return false;
}


// HTTPS REDIRECT (only on traditional servers when not already HTTPS)
if (!isLocalRequest() && !isHttpsRequest() && empty($_ENV['VERCEL']) && empty($_SERVER['VERCEL'])) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    header('Location: https://' . $host . $uri, true, 301);
    exit();
}


// DATABASE CONFIGURATION (credentials live in config.php / environment)
$dbConfig = require __DIR__ . '/config.php';
$dbEnv    = isLocalRequest() ? $dbConfig['local'] : $dbConfig['live'];

// 1. Support full database connection strings (e.g. from Railway, PlanetScale, Supabase, Aiven)
$databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: (
    $_ENV['MYSQL_URL'] ?? $_SERVER['MYSQL_URL'] ?? getenv('MYSQL_URL') ?: ''
);

$dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: $dbEnv['host'];
$dbUser = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: $dbEnv['user'];
$dbPass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: $dbEnv['pass'];
$dbName = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: $dbEnv['name'];
$dbPort = (int) ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);

if (!empty($databaseUrl)) {
    $parsed = parse_url($databaseUrl);
    if (!empty($parsed['host'])) {
        $dbHost = $parsed['host'];
    }
    if (!empty($parsed['user'])) {
        $dbUser = urldecode($parsed['user']);
    }
    if (isset($parsed['pass'])) {
        $dbPass = urldecode($parsed['pass']);
    }
    if (!empty($parsed['path'])) {
        $dbName = ltrim($parsed['path'], '/');
    }
    if (!empty($parsed['port'])) {
        $dbPort = (int) $parsed['port'];
    }
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $urlQuery);
        if (!empty($urlQuery['ssl']) || !empty($urlQuery['sslmode'])) {
            $useSslExplicit = true;
        }
    }
}

// Support host:port syntax in DB_HOST (common in cloud databases like Railway, Aiven, TiDB)
if (strpos((string) $dbHost, ':') !== false) {
    $hostParts = explode(':', (string) $dbHost, 2);
    $dbHost    = $hostParts[0];
    if (!empty($hostParts[1]) && is_numeric($hostParts[1])) {
        $dbPort = (int) $hostParts[1];
    }
}

$useSsl = !empty($_ENV['DB_SSL']) ||
          !empty($_SERVER['DB_SSL']) ||
          getenv('DB_SSL') === 'true' ||
          getenv('DB_SSL') === '1' ||
          !empty($useSslExplicit) ||
          str_contains((string) $dbHost, 'tidbcloud.com') ||
          str_contains((string) $dbHost, 'aivencloud.com');

unset($dbConfig, $dbEnv);


// DATABASE CONNECTION
mysqli_report(MYSQLI_REPORT_OFF);

if ($useSsl) {
    $conn = mysqli_init();
    if ($conn) {
        // Allow connections to verified cloud MySQL providers with TLS
        @$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $conn->ssl_set(null, null, null, null, null);
        @$conn->real_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort, null, MYSQLI_CLIENT_SSL);
    }
} else {
    $conn = @new mysqli(
        $dbHost,
        $dbUser,
        $dbPass,
        $dbName,
        $dbPort
    );
}

if (!$conn || $conn->connect_error) {
    $errorMsg = $conn ? $conn->connect_error : 'Failed to initialize mysqli';
    error_log("DB connection failed: " . $errorMsg);

    // Friendly diagnostic when running on cloud deployment without configured credentials
    if ($dbHost === 'CHANGE_ME_HOST' || empty($dbHost) || $dbHost === 'localhost') {
        die("
        <div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:25px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; box-shadow:0 4px 15px rgba(0,0,0,0.05);'>
            <h2 style='color:#b91c1c; margin-top:0;'>Database Connection Setup Required</h2>
            <p>Your web application is deployed on the cloud (Vercel), but the remote MySQL database credentials are not set yet.</p>
            <p>To connect your database, open your <b>Vercel Project Settings &rarr; Environment Variables</b> and add:</p>
            <ul style='background:#f8fafc; padding:15px 30px; border-radius:8px; font-family:monospace;'>
                <li><b>DB_HOST</b> = your-remote-db-host</li>
                <li><b>DB_USER</b> = your-db-username</li>
                <li><b>DB_PASS</b> = your-db-password</li>
                <li><b>DB_NAME</b> = your-database-name</li>
                <li><b>DB_PORT</b> = 3306 (or your custom port)</li>
            </ul>
            <p style='color:#64748b; font-size:13px;'>Or provide a single <code>DATABASE_URL</code> (e.g., from Railway, Aiven, or TiDB Cloud).</p>
        </div>");
    }

    die("Database connection failed: " . htmlspecialchars($errorMsg) . ". Please verify your database credentials and verify that your cloud database allows connections from all IP addresses (0.0.0.0/0).");
}

$conn->set_charset("utf8mb4");


// SECURE SESSION
function configureSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'secure' => isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}


// PASSWORD VERIFICATION AND UPGRADE
function verifyPasswordAndUpgrade(
    string $password,
    ?string $storedPassword,
    mysqli $conn,
    string $table,
    string $idColumn,
    $idValue
): bool {
    if ($storedPassword === null || $storedPassword === '') {
        return false;
    }

    $info = password_get_info($storedPassword);
    if (!empty($info['algo'])) {
        return password_verify($password, $storedPassword);
    }

    if (!hash_equals($storedPassword, $password)) {
        return false;
    }

    $allowedTables = ['users', 'admins'];
    $allowedColumns = ['user_id', 'username'];
    if (!in_array($table, $allowedTables, true) || !in_array($idColumn, $allowedColumns, true)) {
        return false;
    }

    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $sql = "UPDATE {$table} SET password = ? WHERE {$idColumn} = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        if (is_int($idValue)) {
            $stmt->bind_param('si', $hashedPassword, $idValue);
        } else {
            $stmt->bind_param('ss', $hashedPassword, $idValue);
        }

        $stmt->execute();
        $stmt->close();
    }

    return true;
}

// HTML ESCAPING
function escapeHtml($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}


// CSRF PROTECTION
function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
           escapeHtml(csrfToken()) . '">';
}

function csrfVerify(): void
{
    $sent = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals(csrfToken(), $sent)) {
        http_response_code(403);
        die("Invalid or missing security token. Go back, refresh the page and try again.");
    }
}

// Requires a POST request with a valid CSRF token
function requirePostWithCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die("Invalid request method.");
    }

    csrfVerify();
}


// ADMIN CHECK
function requireAdmin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['admin_username'])) {
        header("Location: admin-login.php");
        exit();
    }
}


// Turns every <a data-post> link into a POST request with a CSRF token.
// Print it just before </body> on pages that have such links.
function csrfPostScript(): string
{
    $token = json_encode(csrfToken());

    return '<script>
document.addEventListener("click", function (event) {
    var link = event.target.closest("a[data-post]");
    if (!link || event.defaultPrevented) { return; }
    event.preventDefault();

    var url  = new URL(link.getAttribute("href"), window.location.href);
    var form = document.createElement("form");
    form.method = "POST";
    form.action = url.pathname;

    url.searchParams.forEach(function (value, name) {
        var input = document.createElement("input");
        input.type = "hidden"; input.name = name; input.value = value;
        form.appendChild(input);
    });

    var csrf = document.createElement("input");
    csrf.type = "hidden"; csrf.name = "csrf_token"; csrf.value = ' . $token . ';
    form.appendChild(csrf);

    document.body.appendChild(form);
    form.submit();
});
</script>';
}

?>
