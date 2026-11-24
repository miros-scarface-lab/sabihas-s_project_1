<?php

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

$status = "Connected successfully!";
$version = $conn->server_info ?? 'Unknown';
$database = $dbName;
$host = $dbHost;

// Fetch list of tables
$tables = [];
$res = $conn->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }
    $res->close();
}

// Check admin user existence
$adminCheck = "Not found";
$resAdmin = $conn->query("SELECT username FROM admins LIMIT 1");
if ($resAdmin && $resAdmin->num_rows > 0) {
    $row = $resAdmin->fetch_assoc();
    $adminCheck = "Found (Username: " . htmlspecialchars($row['username']) . ")";
    $resAdmin->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; padding: 40px 20px; }
        .card { max-width: 550px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .badge-success { display: inline-block; background: #dcfce7; color: #15803d; font-weight: 600; padding: 6px 14px; border-radius: 20px; font-size: 14px; }
        h1 { margin: 15px 0 20px; font-size: 22px; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        td { padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        td.label { color: #64748b; font-weight: 500; width: 40%; }
        td.value { color: #0f172a; font-weight: 600; font-family: monospace; }
        ul { margin: 5px 0 0; padding-left: 18px; }
        .btn { display: inline-block; margin-top: 20px; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-size: 14px; font-weight: 500; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge-success">&#x2714; Database Connected</span>
        <h1>Connection Status Test</h1>
        
        <table>
            <tr>
                <td class="label">Host</td>
                <td class="value"><?php echo htmlspecialchars((string) $host); ?></td>
            </tr>
            <tr>
                <td class="label">Database Name</td>
                <td class="value"><?php echo htmlspecialchars((string) $database); ?></td>
            </tr>
            <tr>
                <td class="label">MySQL Version</td>
                <td class="value"><?php echo htmlspecialchars((string) $version); ?></td>
            </tr>
            <tr>
                <td class="label">Default Admin</td>
                <td class="value"><?php echo $adminCheck; ?></td>
            </tr>
            <tr>
                <td class="label">Tables Found (<?php echo count($tables); ?>)</td>
                <td class="value">
                    <?php if (!empty($tables)): ?>
                        <ul>
                            <?php foreach ($tables as $table): ?>
                                <li><?php echo htmlspecialchars($table); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <span style="color:#e11d48;">No tables found yet</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <a href="index.html" class="btn">Go to Home Page &rarr;</a>
    </div>
</body>
</html>
