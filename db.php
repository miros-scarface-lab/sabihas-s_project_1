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
        $pathDb = ltrim($parsed['path'], '/');
        // 'sys' is the internal MySQL maintenance schema. If default 'sys' was copied, use application database.
        $dbName = ($pathDb === 'sys' || empty($pathDb)) ? 'lost_and_found_db' : $pathDb;
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
        @$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $conn->ssl_set(null, null, null, null, null);
        @$conn->real_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort, null, MYSQLI_CLIENT_SSL);
        
        // If the database does not exist yet (Error 1049: ER_BAD_DB_ERROR), connect to server and auto-create it
        if ($conn->connect_errno === 1049) {
            $conn = mysqli_init();
            @$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
            $conn->ssl_set(null, null, null, null, null);
            if (@$conn->real_connect($dbHost, $dbUser, $dbPass, null, $dbPort, null, MYSQLI_CLIENT_SSL)) {
                $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
                if (!empty($safeDbName)) {
                    $conn->query("CREATE DATABASE IF NOT EXISTS `$safeDbName`");
                    $conn->select_db($safeDbName);
                }
            }
        }
    }
} else {
    $conn = @new mysqli(
        $dbHost,
        $dbUser,
        $dbPass,
        $dbName,
        $dbPort
    );

    if ($conn->connect_errno === 1049) {
        $conn = @new mysqli($dbHost, $dbUser, $dbPass, null, $dbPort);
        if (!$conn->connect_error) {
            $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
            if (!empty($safeDbName)) {
                $conn->query("CREATE DATABASE IF NOT EXISTS `$safeDbName`");
                $conn->select_db($safeDbName);
            }
        }
    }
}

// Auto-bootstrap tables from db.sql if database was just created or empty
if ($conn && !$conn->connect_error) {
    $tableCheck = @$conn->query("SHOW TABLES LIKE 'users'");
    if ($tableCheck && $tableCheck->num_rows === 0) {
        $sqlFile = __DIR__ . '/db.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            if (!empty($sqlContent)) {
                $conn->multi_query($sqlContent);
                while ($conn->more_results() && $conn->next_result()) {
                    // Flush multi-statement results
                }
            }
        }
    }
    if ($tableCheck) {
        $tableCheck->close();
    }
    // Ensure item_image column supports base64 data URIs for serverless deployments
    @$conn->query("ALTER TABLE lost_items MODIFY COLUMN item_image MEDIUMTEXT NULL");
    @$conn->query("ALTER TABLE found_items MODIFY COLUMN item_image MEDIUMTEXT NULL");

    // Ensure matches table has description_score and claim_status
    $descColCheck = @$conn->query("SHOW COLUMNS FROM `matches` LIKE 'description_score'");
    if ($descColCheck && $descColCheck->num_rows === 0) {
        @$conn->query("ALTER TABLE `matches` ADD COLUMN `description_score` INT DEFAULT 0 AFTER `item_name_score`");
    }
    if ($descColCheck) {
        $descColCheck->close();
    }

    $claimColCheck = @$conn->query("SHOW COLUMNS FROM `matches` LIKE 'claim_status'");
    if ($claimColCheck && $claimColCheck->num_rows === 0) {
        @$conn->query("ALTER TABLE `matches` ADD COLUMN `claim_status` VARCHAR(50) DEFAULT 'Not Claimed' AFTER `status`");
    }
    if ($claimColCheck) {
        $claimColCheck->close();
    }
    // Clean up any default 'None' or empty values so Claim button appears
    @$conn->query("UPDATE `matches` SET `claim_status` = 'Not Claimed' WHERE `claim_status` IS NULL OR `claim_status` = '' OR `claim_status` = 'None'");

    // Ensure session persistence table exists for cloud / serverless deployments
    @$conn->query("CREATE TABLE IF NOT EXISTS `php_sessions` (
      `id` VARCHAR(128) PRIMARY KEY,
      `data` MEDIUMTEXT NOT NULL,
      `timestamp` INT UNSIGNED NOT NULL,
      INDEX (`timestamp`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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


// SECURE SESSION & PERSISTENCE (30 Days)
function configureSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        @ini_set('session.gc_maxlifetime', '2592000'); // 30 days
        session_set_cookie_params([
            'lifetime' => 2592000, // 30 days (avoids dropping on browser/tab close)
            'path'     => '/',
            'secure'   => isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

function getAuthSigningKey(): string
{
    global $dbPass, $dbHost;
    return hash('sha256', ($dbPass ?? 'secret') . ($dbHost ?? 'lostfound') . 'lf_salt_2026');
}

function setUserRememberCookie(int $userId): void
{
    $expires = time() + 2592000; // 30 days
    $sig = hash_hmac('sha256', "user:{$userId}:{$expires}", getAuthSigningKey());
    $payload = base64_encode("{$userId}:{$expires}:{$sig}");
    setcookie('lostfound_auth', $payload, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function setAdminRememberCookie(string $username): void
{
    $expires = time() + 2592000; // 30 days
    $sig = hash_hmac('sha256', "admin:{$username}:{$expires}", getAuthSigningKey());
    $payload = base64_encode("{$username}:{$expires}:{$sig}");
    setcookie('lostfound_admin_auth', $payload, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearUserRememberCookie(): void
{
    setcookie('lostfound_auth', '', [
        'expires'  => time() - 86400,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearAdminRememberCookie(): void
{
    setcookie('lostfound_admin_auth', '', [
        'expires'  => time() - 86400,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function restoreSessionFromRememberCookie(mysqli $conn): void
{
    // Auto-restore regular user session if serverless container restarted
    if (empty($_SESSION['user_id']) && !empty($_COOKIE['lostfound_auth'])) {
        $raw = base64_decode((string) $_COOKIE['lostfound_auth'], true);
        if ($raw && substr_count($raw, ':') === 2) {
            [$userId, $expires, $sig] = explode(':', $raw, 3);
            if ((int) $expires > time()) {
                $expected = hash_hmac('sha256', "user:{$userId}:{$expires}", getAuthSigningKey());
                if (hash_equals($expected, $sig)) {
                    $uStmt = $conn->prepare("SELECT user_id, full_name, student_id, email, department, role FROM users WHERE user_id = ? LIMIT 1");
                    if ($uStmt) {
                        $uIdInt = (int) $userId;
                        $uStmt->bind_param("i", $uIdInt);
                        $uStmt->execute();
                        $uRes = $uStmt->get_result();
                        if ($u = $uRes->fetch_assoc()) {
                            $_SESSION['user_id']    = $u['user_id'];
                            $_SESSION['full_name']  = $u['full_name'];
                            $_SESSION['student_id'] = $u['student_id'];
                            $_SESSION['department'] = $u['department'];
                            $_SESSION['role']       = $u['role'];
                        }
                        $uStmt->close();
                    }
                }
            }
        }
    }

    // Auto-restore admin session if serverless container restarted
    if (empty($_SESSION['admin_username']) && !empty($_COOKIE['lostfound_admin_auth'])) {
        $raw = base64_decode((string) $_COOKIE['lostfound_admin_auth'], true);
        if ($raw && substr_count($raw, ':') === 2) {
            [$username, $expires, $sig] = explode(':', $raw, 3);
            if ((int) $expires > time()) {
                $expected = hash_hmac('sha256', "admin:{$username}:{$expires}", getAuthSigningKey());
                if (hash_equals($expected, $sig)) {
                    $aStmt = $conn->prepare("SELECT username FROM admins WHERE username = ? LIMIT 1");
                    if ($aStmt) {
                        $aStmt->bind_param("s", $username);
                        $aStmt->execute();
                        $aRes = $aStmt->get_result();
                        if ($a = $aRes->fetch_assoc()) {
                            $_SESSION['admin_username'] = $a['username'];
                        }
                        $aStmt->close();
                    }
                }
            }
        }
    }
}

// Automatically configure session lifetime and restore session if available
configureSecureSession();
if (session_status() === PHP_SESSION_ACTIVE) {
    restoreSessionFromRememberCookie($conn);
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
