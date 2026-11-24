<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$keyword   = trim((string) ($_REQUEST['item_name'] ?? $_REQUEST['q'] ?? ''));
$category  = trim((string) ($_REQUEST['category'] ?? ''));
$status    = trim((string) ($_REQUEST['status'] ?? 'All'));
$location  = trim((string) ($_REQUEST['location'] ?? ''));

if ($status === '') {
    $status = 'All';
}

$results = [];
$likeKeyword  = '%' . $keyword . '%';
$likeLocation = '%' . $location . '%';

// 1. Query Lost Items
if ($status === 'All' || strcasecmp($status, 'Lost') === 0) {
    $sqlLost = "
        SELECT
            'lost' AS item_type,
            lost_id AS id,
            item_name,
            category,
            description,
            lost_date AS item_date,
            lost_location AS item_location,
            item_image,
            status
        FROM lost_items
        WHERE 1=1
    ";

    $params = [];
    $types  = '';

    if ($keyword !== '') {
        $sqlLost .= " AND (item_name LIKE ? OR description LIKE ? OR lost_location LIKE ?)";
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $types   .= 'sss';
    }

    if ($category !== '') {
        $sqlLost .= " AND category = ?";
        $params[] = $category;
        $types   .= 's';
    }

    if ($location !== '') {
        $sqlLost .= " AND lost_location LIKE ?";
        $params[] = $likeLocation;
        $types   .= 's';
    }

    $sqlLost .= " ORDER BY lost_date DESC, lost_id DESC LIMIT 50";

    $stmtLost = $conn->prepare($sqlLost);
    if ($stmtLost) {
        if (!empty($params)) {
            $stmtLost->bind_param($types, ...$params);
        }
        $stmtLost->execute();
        $resLost = $stmtLost->get_result();
        while ($row = $resLost->fetch_assoc()) {
            $results[] = $row;
        }
        $stmtLost->close();
    }
}

// 2. Query Found Items
if ($status === 'All' || strcasecmp($status, 'Found') === 0) {
    $sqlFound = "
        SELECT
            'found' AS item_type,
            found_id AS id,
            item_name,
            category,
            description,
            found_date AS item_date,
            found_location AS item_location,
            item_image,
            status
        FROM found_items
        WHERE 1=1
    ";

    $params = [];
    $types  = '';

    if ($keyword !== '') {
        $sqlFound .= " AND (item_name LIKE ? OR description LIKE ? OR found_location LIKE ?)";
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $types   .= 'sss';
    }

    if ($category !== '') {
        $sqlFound .= " AND category = ?";
        $params[] = $category;
        $types   .= 's';
    }

    if ($location !== '') {
        $sqlFound .= " AND found_location LIKE ?";
        $params[] = $likeLocation;
        $types   .= 's';
    }

    $sqlFound .= " ORDER BY found_date DESC, found_id DESC LIMIT 50";

    $stmtFound = $conn->prepare($sqlFound);
    if ($stmtFound) {
        if (!empty($params)) {
            $stmtFound->bind_param($types, ...$params);
        }
        $stmtFound->execute();
        $resFound = $stmtFound->get_result();
        while ($row = $resFound->fetch_assoc()) {
            $results[] = $row;
        }
        $stmtFound->close();
    }
}

// Sort combined results by date descending
if ($status === 'All' && !empty($results)) {
    usort($results, function ($a, $b) {
        return strcmp($b['item_date'] ?? '', $a['item_date'] ?? '');
    });
}

$categoriesList = ['Electronics', 'Documents', 'Accessories', 'Books or Stationery', 'Others'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Items - Lost & Found</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; padding-bottom: 60px; }
        .search-hero { background: linear-gradient(135deg, #003844 0%, #00232b 100%); color: #fff; padding: 40px 20px 30px; text-align: center; }
        .search-hero h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .search-hero p { color: #94a3b8; font-size: 14px; max-width: 500px; margin: 0 auto; }
        .search-container { max-width: 1000px; margin: -25px auto 0; padding: 0 16px; }
        .search-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .search-grid { display: grid; grid-template-columns: 2fr 1.2fr 1fr 1.2fr auto; gap: 12px; align-items: end; }
        @media (max-width: 860px) {
            .search-grid { grid-template-columns: 1fr 1fr; }
            .btn-search { grid-column: span 2; }
        }
        @media (max-width: 520px) {
            .search-grid { grid-template-columns: 1fr; }
            .btn-search { grid-column: span 1; }
        }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 14px; outline: none; transition: 0.2s; background: #fff; }
        .form-control:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15); }
        .btn-search { background: #0d9488; color: #fff; font-weight: 700; border: none; border-radius: 10px; padding: 12px 24px; font-size: 14px; cursor: pointer; transition: 0.2s; height: 44px; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-search:hover { background: #0f766e; }
        .search-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; font-size: 14px; color: #64748b; }
        .search-nav a { color: #0d9488; font-weight: 600; text-decoration: none; }
        .search-nav a:hover { text-decoration: underline; }
        .results-header { margin: 30px 0 16px; display: flex; justify-content: space-between; align-items: center; }
        .results-count { font-size: 16px; font-weight: 700; color: #0f172a; }
        .results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
        .item-card { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; transition: 0.2s; display: flex; flex-direction: column; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .item-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); border-color: #cbd5e1; }
        .card-img { width: 100%; height: 160px; background: #f1f5f9; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .card-img img { width: 100%; height: 100%; object-fit: cover; }
        .card-img .no-img { font-size: 12px; color: #94a3b8; font-weight: 600; }
        .type-badge { position: absolute; top: 12px; left: 12px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .type-lost { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .type-found { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .card-body { padding: 16px; display: flex; flex-direction: column; flex-grow: 1; }
        .card-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .card-meta { display: flex; flex-direction: column; gap: 5px; font-size: 13px; color: #64748b; margin-bottom: 12px; }
        .meta-row { display: flex; align-items: center; gap: 6px; }
        .card-desc { font-size: 13px; color: #475569; margin-bottom: 16px; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
        .card-footer { border-top: 1px solid #f1f5f9; padding-top: 12px; display: flex; justify-content: space-between; align-items: center; }
        .category-pill { background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 600; padding: 4px 9px; border-radius: 6px; }
        .btn-view { background: #003844; color: #fff; font-size: 12px; font-weight: 600; text-decoration: none; padding: 7px 14px; border-radius: 8px; transition: 0.2s; }
        .btn-view:hover { background: #0d9488; }
        .empty-state { background: #fff; border-radius: 16px; padding: 40px 20px; text-align: center; border: 1px dashed #cbd5e1; margin-top: 20px; }
        .empty-state h3 { font-size: 18px; color: #0f172a; margin-bottom: 6px; }
        .empty-state p { color: #64748b; font-size: 14px; max-width: 400px; margin: 0 auto 16px; }
        .empty-state a { color: #0d9488; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>

<div class="search-hero">
    <h1>Search Lost & Found Items</h1>
    <p>Find items by name, category, campus location, or description.</p>
</div>

<div class="search-container">
    <div class="search-card">
        <form action="search-item.php" method="GET">
            <div class="search-grid">
                <div class="form-group">
                    <label>Item Name or Keyword</label>
                    <input type="text" name="item_name" class="form-control" placeholder="e.g. Wallet, iPhone, ID Card..." value="<?php echo htmlspecialchars($keyword); ?>">
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categoriesList as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="All" <?php echo ($status === 'All') ? 'selected' : ''; ?>>All (Lost & Found)</option>
                        <option value="Lost" <?php echo (strcasecmp($status, 'Lost') === 0) ? 'selected' : ''; ?>>Lost Items Only</option>
                        <option value="Found" <?php echo (strcasecmp($status, 'Found') === 0) ? 'selected' : ''; ?>>Found Items Only</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g. Library, Cafeteria..." value="<?php echo htmlspecialchars($location); ?>">
                </div>

                <button type="submit" class="btn-search">
                    &#x1F50D; Search
                </button>
            </div>
        </form>

        <div class="search-nav">
            <div>
                <?php if ($keyword !== '' || $category !== '' || $location !== '' || $status !== 'All'): ?>
                    Filtered by: <strong><?php echo htmlspecialchars($keyword ?: 'Any item'); ?></strong>
                    &bull; <a href="search-item.php">Reset all filters</a>
                <?php endif; ?>
            </div>
            <a href="dashboard.php">&larr; Back to Dashboard</a>
        </div>
    </div>

    <div class="results-header">
        <div class="results-count">
            <?php echo count($results); ?> <?php echo count($results) === 1 ? 'Item Found' : 'Items Found'; ?>
        </div>
    </div>

    <?php if (!empty($results)): ?>
        <div class="results-grid">
            <?php foreach ($results as $item): ?>
                <?php
                    $isLost = ($item['item_type'] === 'lost');
                    $imgUrl = '';
                    if (!empty($item['item_image'])) {
                        $imgUrl = 'image.php?type=' . $item['item_type'] . '&id=' . (int) $item['id'];
                    }
                ?>
                <div class="item-card">
                    <div class="card-img">
                        <span class="type-badge <?php echo $isLost ? 'type-lost' : 'type-found'; ?>">
                            <?php echo $isLost ? '&#x1F534; Lost' : '&#x1F7E2; Found'; ?>
                        </span>
                        <?php if ($imgUrl !== ''): ?>
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" loading="lazy">
                        <?php else: ?>
                            <span class="no-img">&#x1F4E6; No Image Attached</span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                        
                        <div class="card-meta">
                            <div class="meta-row">
                                <span>&#x1F4CD;</span>
                                <span><?php echo htmlspecialchars($item['item_location'] ?: 'Location not specified'); ?></span>
                            </div>
                            <div class="meta-row">
                                <span>&#x1F4C5;</span>
                                <span><?php echo htmlspecialchars($item['item_date'] ?: 'Date unknown'); ?></span>
                            </div>
                        </div>

                        <p class="card-desc">
                            <?php echo htmlspecialchars($item['description'] ?: 'No description provided.'); ?>
                        </p>

                        <div class="card-footer">
                            <span class="category-pill">
                                <?php echo htmlspecialchars($item['category'] ?: 'General'); ?>
                            </span>
                            <a href="item-details.php?type=<?php echo urlencode($item['item_type']); ?>&id=<?php echo (int) $item['id']; ?>" class="btn-view">
                                View Details &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>No items matched your search</h3>
            <p>Try searching with broader keywords, different categories, or clearing your location filter.</p>
            <a href="search-item.php" class="btn-search" style="display:inline-flex; width:auto; padding:8px 20px; margin:0 auto;">
                View All Available Items
            </a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
