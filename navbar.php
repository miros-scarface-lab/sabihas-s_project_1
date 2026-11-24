<?php
/**
 * Campus Lost & Found - Unified Professional Navigation System
 * Provides top navbar and three-dot dashboard drawer across all pages.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$clf_user_id = $_SESSION['user_id'] ?? null;
$clf_full_name = $_SESSION['full_name'] ?? 'User';
$clf_student_id = $_SESSION['student_id'] ?? '';
$clf_is_admin = isset($_SESSION['admin_username']);
$clf_current_page = basename($_SERVER['PHP_SELF'] ?? '');

// Unread notifications count
$clf_unread_count = 0;
if ($clf_user_id && isset($conn) && $conn instanceof mysqli) {
    $n_stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND receiver = 'user' AND is_read = 0");
    if ($n_stmt) {
        $n_stmt->bind_param("i", $clf_user_id);
        $n_stmt->execute();
        $n_res = $n_stmt->get_result();
        if ($n_res) {
            $n_row = $n_res->fetch_assoc();
            $clf_unread_count = (int) ($n_row['c'] ?? 0);
        }
        $n_stmt->close();
    }
}
?>

<!-- ==========================================
     PROFESSIONAL NAVIGATION SYSTEM (CSS)
========================================== -->
<style>
/* Reset box sizing for navbar scope */
.clf-navbar-wrap, .clf-navbar-wrap * {
    box-sizing: border-box;
}

.clf-navbar-wrap {
    width: 100%;
    position: sticky;
    top: 0;
    z-index: 1000;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.clf-navbar-inner {
    max-width: 1350px;
    margin: 0 auto;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

/* Left side */
.clf-nav-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Three-dot button */
.clf-three-dots-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #f1f5f9;
    color: #003844;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.2s ease;
    user-select: none;
}

.clf-three-dots-btn:hover {
    background: #003844;
    color: #ffffff;
    border-color: #003844;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 56, 68, 0.15);
}

.clf-three-dots-btn:active {
    transform: translateY(0);
}

.clf-three-dots-btn svg {
    display: block;
    width: 18px;
    height: 18px;
    stroke: currentColor;
    fill: none;
}

.clf-three-dots-btn span.clf-dots-label {
    display: inline-block;
}

@media (max-width: 640px) {
    .clf-three-dots-btn span.clf-dots-label {
        display: none;
    }
    .clf-three-dots-btn {
        padding: 8px 10px;
    }
}

/* Brand */
.clf-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: #003844;
}

.clf-brand:hover {
    text-decoration: none;
}

.clf-brand-logo {
    height: 38px;
    width: auto;
    border-radius: 8px;
    object-contain: contain;
    display: block;
}

.clf-brand-title {
    font-size: 16px;
    font-weight: 800;
    color: #003844;
    letter-spacing: -0.3px;
    line-height: 1.2;
    white-space: nowrap;
}

@media (max-width: 500px) {
    .clf-brand-title {
        font-size: 14px;
    }
}

/* Nav links (desktop) */
.clf-nav-center {
    display: flex;
    align-items: center;
    gap: 4px;
}

@media (max-width: 991px) {
    .clf-nav-center {
        display: none;
    }
}

.clf-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 13px;
    border-radius: 9px;
    font-size: 13.5px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.clf-nav-link:hover {
    color: #003844;
    background: #f1f5f9;
    text-decoration: none;
}

.clf-nav-link.active {
    color: #ffffff;
    background: #0fa4af;
    font-weight: 700;
}

.clf-nav-link svg {
    width: 15px;
    height: 15px;
    stroke: currentColor;
    fill: none;
}

/* Right side */
.clf-nav-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Notification Bell */
.clf-bell-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
    text-decoration: none;
    transition: all 0.2s ease;
}

.clf-bell-link:hover {
    background: #f1f5f9;
    color: #003844;
}

.clf-bell-link svg {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    fill: none;
}

.clf-bell-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ef4444;
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    min-width: 17px;
    height: 17px;
    padding: 0 4px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
}

/* User pill */
.clf-user-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 10px;
    background: #f0fdfa;
    border: 1px solid #ccfbf1;
    border-radius: 20px;
}

@media (max-width: 640px) {
    .clf-user-pill {
        display: none;
    }
}

.clf-user-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #0fa4af;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
}

.clf-user-name {
    font-size: 13px;
    font-weight: 700;
    color: #003844;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ==========================================
     DASHBOARD DRAWER / SLIDE-IN (CSS)
========================================== */
.clf-drawer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 20, 26, 0.65);
    backdrop-filter: blur(3px);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.clf-drawer-backdrop.active {
    opacity: 1;
    visibility: visible;
}

.clf-drawer {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 300px;
    max-width: 85vw;
    background: #003844;
    color: #ffffff;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.3);
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    overflow-y: auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.clf-drawer.active {
    transform: translateX(0);
}

.clf-drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.clf-drawer-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.clf-drawer-brand img {
    height: 34px;
    width: auto;
    border-radius: 8px;
}

.clf-drawer-brand-text {
    font-size: 14px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: 0.3px;
}

.clf-drawer-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #ffffff;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
    transition: background 0.2s ease;
}

.clf-drawer-close:hover {
    background: rgba(255, 255, 255, 0.25);
}

.clf-drawer-user-info {
    padding: 16px 20px;
    background: rgba(0, 0, 0, 0.18);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    gap: 12px;
}

.clf-drawer-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #0fa4af;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(15, 164, 175, 0.4);
}

.clf-drawer-user-details h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
}

.clf-drawer-user-details p {
    margin: 2px 0 0;
    font-size: 11px;
    color: rgba(204, 251, 241, 0.7);
}

.clf-drawer-nav {
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.clf-drawer-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    border-radius: 10px;
    color: rgba(204, 251, 241, 0.85);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.clf-drawer-link:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
    text-decoration: none;
    transform: translateX(3px);
}

.clf-drawer-link.active {
    background: #0fa4af;
    color: #ffffff;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(15, 164, 175, 0.35);
}

.clf-drawer-link svg {
    width: 17px;
    height: 17px;
    stroke: currentColor;
    fill: none;
}

.clf-drawer-badge {
    margin-left: auto;
    background: #ef4444;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 10px;
}

.clf-drawer-footer {
    padding: 16px 20px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.clf-drawer-footer img {
    width: 110px;
    height: auto;
    border-radius: 12px;
    margin: 0 auto 10px;
    display: block;
    opacity: 0.9;
}

.clf-drawer-footer p {
    font-size: 11px;
    color: rgba(204, 251, 241, 0.75);
    font-style: italic;
    margin: 0 0 12px;
}

.clf-drawer-logout {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 9px;
    background: rgba(225, 29, 72, 0.15);
    border: 1px solid rgba(244, 63, 94, 0.3);
    color: #fecdd3;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}

.clf-drawer-logout:hover {
    background: #e11d48;
    color: #ffffff;
    text-decoration: none;
}
</style>

<!-- ==========================================
     NAVBAR HTML
========================================== -->
<header class="clf-navbar-wrap">
    <div class="clf-navbar-inner">

        <!-- Left: Three Dots + Logo -->
        <div class="clf-nav-left">
            <button
                type="button"
                id="clfThreeDotsBtn"
                class="clf-three-dots-btn"
                aria-label="Open Dashboard Menu"
                title="Tap three dots to view Dashboard Menu"
            >
                <svg viewBox="0 0 24 24" stroke-width="2.8">
                    <circle cx="12" cy="5" r="1.5"></circle>
                    <circle cx="12" cy="12" r="1.5"></circle>
                    <circle cx="12" cy="19" r="1.5"></circle>
                </svg>
                <span class="clf-dots-label">Dashboard</span>
            </button>

            <a href="dashboard.php" class="clf-brand">
                <img src="images/new-logo.jpeg" alt="Logo" class="clf-brand-logo">
                <span class="clf-brand-title">Campus Lost &amp; Found</span>
            </a>
        </div>

        <!-- Center: Quick Nav Links -->
        <nav class="clf-nav-center">
            <a href="dashboard.php" class="clf-nav-link <?= ($clf_current_page === 'dashboard.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="add-lost-item.php" class="clf-nav-link <?= ($clf_current_page === 'add-lost-item.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                <span>Add Lost</span>
            </a>
            <a href="add-found-item.php" class="clf-nav-link <?= ($clf_current_page === 'add-found-item.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                <span>Add Found</span>
            </a>
            <a href="search-item.php" class="clf-nav-link <?= ($clf_current_page === 'search-item.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <span>Search</span>
            </a>
            <a href="my-matches.php" class="clf-nav-link <?= ($clf_current_page === 'my-matches.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                <span>My Matches</span>
            </a>
            <a href="view-items.php" class="clf-nav-link <?= ($clf_current_page === 'view-items.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M15 2H9a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1Z"/></svg>
                <span>All Items</span>
            </a>
        </nav>

        <!-- Right: Notifications & User -->
        <div class="clf-nav-right">
            <a href="notifications.php" class="clf-bell-link" title="Notifications">
                <svg viewBox="0 0 24 24" stroke-width="2">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                </svg>
                <?php if ($clf_unread_count > 0): ?>
                    <span class="clf-bell-badge"><?= $clf_unread_count ?></span>
                <?php endif; ?>
            </a>

            <div class="clf-user-pill" title="<?= htmlspecialchars($clf_full_name) ?>">
                <div class="clf-user-avatar">
                    <?= strtoupper(substr($clf_full_name, 0, 1)) ?>
                </div>
                <span class="clf-user-name">
                    <?= htmlspecialchars($clf_full_name) ?>
                </span>
            </div>
        </div>

    </div>
</header>

<!-- ==========================================
     DASHBOARD DRAWER OVERLAY
========================================== -->
<div id="clfDrawerBackdrop" class="clf-drawer-backdrop" aria-hidden="true"></div>

<aside id="clfDashboardDrawer" class="clf-drawer" aria-label="Dashboard Menu Drawer">
    <div>
        <!-- Drawer Header -->
        <div class="clf-drawer-header">
            <div class="clf-drawer-brand">
                <img src="images/new-logo.jpeg" alt="Logo">
                <span class="clf-drawer-brand-text">CAMPUS LOST &amp; FOUND</span>
            </div>
            <button type="button" id="clfDrawerCloseBtn" class="clf-drawer-close" aria-label="Close menu">
                ✕
            </button>
        </div>

        <!-- User Info -->
        <div class="clf-drawer-user-info">
            <div class="clf-drawer-user-avatar">
                <?= strtoupper(substr($clf_full_name, 0, 1)) ?>
            </div>
            <div class="clf-drawer-user-details">
                <h4><?= htmlspecialchars($clf_full_name) ?></h4>
                <p><?= htmlspecialchars($clf_student_id !== '' ? 'ID: ' . $clf_student_id : 'Member') ?></p>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="clf-drawer-nav">
            <a href="dashboard.php" class="clf-drawer-link <?= ($clf_current_page === 'dashboard.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                <span>Dashboard</span>
            </a>

            <a href="add-lost-item.php" class="clf-drawer-link <?= ($clf_current_page === 'add-lost-item.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                <span>Add Lost Item</span>
            </a>

            <a href="add-found-item.php" class="clf-drawer-link <?= ($clf_current_page === 'add-found-item.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                <span>Add Found Item</span>
            </a>

            <a href="search-item.php" class="clf-drawer-link <?= ($clf_current_page === 'search-item.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <span>Search Item</span>
            </a>

            <a href="view-items.php" class="clf-drawer-link <?= ($clf_current_page === 'view-items.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M15 2H9a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1Z"/></svg>
                <span>View All Items</span>
            </a>

            <a href="my-matches.php" class="clf-drawer-link <?= ($clf_current_page === 'my-matches.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                <span>My Matches</span>
            </a>

            <a href="notifications.php" class="clf-drawer-link <?= ($clf_current_page === 'notifications.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                <span>Notifications</span>
                <?php if ($clf_unread_count > 0): ?>
                    <span class="clf-drawer-badge"><?= $clf_unread_count ?></span>
                <?php endif; ?>
            </a>
        </nav>
    </div>

    <!-- Drawer Footer -->
    <div class="clf-drawer-footer">
        <img src="images/box.jpeg" alt="Lost Box Artwork">
        <p>"Let's help return<br>what truly matters."</p>
        <a href="logout.php" class="clf-drawer-logout">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
            <span>Log Out</span>
        </a>
    </div>
</aside>

<!-- ==========================================
     DRAWER TOGGLE SCRIPT
========================================== -->
<script>
(function() {
    const dotsBtn = document.getElementById('clfThreeDotsBtn');
    const drawer = document.getElementById('clfDashboardDrawer');
    const backdrop = document.getElementById('clfDrawerBackdrop');
    const closeBtn = document.getElementById('clfDrawerCloseBtn');

    function openDrawer() {
        if (!drawer || !backdrop) return;
        drawer.classList.add('active');
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        if (!drawer || !backdrop) return;
        drawer.classList.remove('active');
        backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (dotsBtn) {
        dotsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (drawer.classList.contains('active')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeDrawer);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeDrawer);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('active')) {
            closeDrawer();
        }
    });
})();
</script>
