<?php
// image.php - Serves item images as lightweight independent binary assets
require_once __DIR__ . '/db.php';

$type = isset($_GET['type']) && $_GET['type'] === 'found' ? 'found' : 'lost';
$id   = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit();
}

$table    = ($type === 'found') ? 'found_items' : 'lost_items';
$idColumn = ($type === 'found') ? 'found_id' : 'lost_id';

$stmt = $conn->prepare("SELECT item_image FROM `{$table}` WHERE `{$idColumn}` = ? LIMIT 1");
if (!$stmt) {
    http_response_code(404);
    exit();
}

$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    exit();
}

$row = $res->fetch_assoc();
$stmt->close();

$itemImage = trim((string) ($row['item_image'] ?? ''));

if ($itemImage === '') {
    http_response_code(404);
    exit();
}

// 1. Data URI (Base64) stored in database
if (str_starts_with($itemImage, 'data:')) {
    if (preg_match('/^data:([^;]+);base64,(.+)$/s', $itemImage, $matches)) {
        $mime = $matches[1];
        $binary = base64_decode(trim($matches[2]));
        if ($binary !== false) {
            header("Content-Type: {$mime}");
            header("Content-Length: " . strlen($binary));
            header("Cache-Control: public, max-age=604800"); // 7 days cache
            echo $binary;
            exit();
        }
    }
}

// 2. Local file path on disk
$localFile = __DIR__ . '/' . ltrim($itemImage, '/');
if (file_exists($localFile) && is_file($localFile)) {
    $mime = mime_content_type($localFile) ?: 'image/jpeg';
    header("Content-Type: {$mime}");
    header("Content-Length: " . filesize($localFile));
    header("Cache-Control: public, max-age=604800");
    readfile($localFile);
    exit();
}

http_response_code(404);
exit();
