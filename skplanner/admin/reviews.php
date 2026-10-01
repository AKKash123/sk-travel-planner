<?php
// ============================================
// SK Travel Planner — Admin User Reviews Management
// Manage live reviews: Accept, Decline, Filter, Delete
// ============================================
require_once __DIR__ . '/../config.php';
requireAdmin();

// Count totals for stats
$totalReviews    = (int)$pdo->query("SELECT COUNT(*) FROM user_reviews")->fetchColumn();
$pendingReviews  = (int)$pdo->query("SELECT COUNT(*) FROM user_reviews WHERE status = 'pending'")->fetchColumn();
$approvedReviews = (int)$pdo->query("SELECT COUNT(*) FROM user_reviews WHERE status = 'approved'")->fetchColumn();
$declinedReviews = (int)$pdo->query("SELECT COUNT(*) FROM user_reviews WHERE status = 'declined'")->fetchColumn();
$avgRating       = (float)($pdo->query("SELECT ROUND(AVG(rating), 1) FROM user_reviews WHERE status = 'approved'")->fetchColumn() ?: 0);

// Filters and search
$filter = clean($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'pending', 'approved', 'declined'], true)) {
    $filter = 'all';
}

$search = clean($_GET['search'] ?? '');
$sort   = clean($_GET['sort'] ?? 'newest');

$sortMap = [
    'newest'      => 'created_at DESC',
    'oldest'      => 'created_at ASC',
    'rating_high' => 'rating DESC, created_at DESC',
    'rating_low'  => 'rating ASC, created_at DESC',
];
$sortSql = $sortMap[$sort] ?? 'created_at DESC';

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$whereClauses = [];
$params       = [];

if ($filter === 'pending') {
    $whereClauses[] = "status = 'pending'";
} elseif ($filter === 'approved') {
    $whereClauses[] = "status = 'approved'";
} elseif ($filter === 'declined') {
    $whereClauses[] = "status = 'declined'";
}

if ($search !== '') {
    $whereClauses[] = "(user_name LIKE ? OR user_email LIKE ? OR user_location LIKE ? OR review_title LIKE ? OR review_text LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total matching
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_reviews {$whereSql}");
$countStmt->execute($params);
$filteredCount = (int)$countStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($filteredCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch reviews
$dataStmt = $pdo->prepare("SELECT * FROM user_reviews {$whereSql} ORDER BY {$sortSql} LIMIT {$perPage} OFFSET {$offset}");
$dataStmt->execute($params);
$reviews = $dataStmt->fetchAll();

// Preload review photos from review_images
$reviewImagesMap = [];
if (!empty($reviews)) {
    $revIds = array_column($reviews, 'id');
    $placeholders = implode(',', array_fill(0, count($revIds), '?'));
    $imgStmt = $pdo->prepare("SELECT * FROM review_images WHERE review_id IN ($placeholders) ORDER BY id ASC");
    $imgStmt->execute($revIds);
    while ($imgRow = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
        $reviewImagesMap[$imgRow['review_id']][] = $imgRow;
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User Reviews — <?= e(APP_NAME) ?> Admin</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/icons/favicon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/favicon-32.png">
    <link rel="icon" href="../assets/icons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/icons/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .admin-layout { display:flex; min-height:100vh; }
        .admin-sidebar {
            width:260px; background:var(--dark); color:var(--white);
            position:fixed; top:0; left:0; height:100vh;
            overflow-y:auto; z-index:900; flex-shrink:0;
        }
        .admin-sidebar-brand {
            display:flex; align-items:center; gap:12px;
            padding:20px 24px; font-family:'Playfair Display',serif;
            font-size:20px; font-weight:700;
            border-bottom:1px solid rgba(255,255,255,0.08);
        }
        .admin-sidebar-brand .brand-icon {
            width:36px; height:36px;
            background:linear-gradient(135deg,var(--accent),var(--accent-dark));
            border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:18px;
            overflow:hidden;
        }
        .admin-sidebar-brand .brand-icon img { width:100%; height:100%; object-fit:cover; }
        .admin-nav { list-style:none; padding:16px 0; }
        .admin-nav li a {
            display:flex; align-items:center; gap:12px;
            padding:12px 24px; color:rgba(255,255,255,0.65);
            font-size:15px; font-weight:500; transition:all 0.3s;
            text-decoration:none;
        }
        .admin-nav li a:hover, .admin-nav li a.active {
            color:var(--white); background:rgba(255,255,255,0.08);
        }
        .admin-nav li a i { width:20px; text-align:center; }
        .admin-nav .nav-divider {
            margin:16px 24px; border-top:1px solid rgba(255,255,255,0.08);
        }

        .admin-main { margin-left:260px; flex:1; background:var(--light); min-height:100vh; display:flex; flex-direction:column; }
        .admin-topbar {
            background:var(--white); padding:16px 30px;
            display:flex; align-items:center; justify-content:space-between;
            box-shadow:0 1px 6px rgba(0,0,0,0.06); position:sticky; top:0; z-index:100;
        }
        .admin-topbar h1 { font-size:22px; display:flex; align-items:center; gap:10px; margin:0; }
        .admin-topbar h1 i { color:var(--primary); }
        .admin-topbar-right { display:flex; align-items:center; gap:16px; }
        .admin-topbar-right .admin-name {
            font-size:14px; color:var(--text-muted);
            display:flex; align-items:center; gap:8px;
        }
        .admin-topbar-right .admin-name i { color:var(--primary); }

        .admin-content { padding:30px; flex:1; }

        /* Stats Cards */
        .stats-grid {
            display:grid; grid-template-columns:repeat(5,1fr); gap:18px; margin-bottom:28px;
        }
        .stat-card {
            background:var(--white); border-radius:var(--radius); padding:20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.04); position:relative; overflow:hidden;
            transition:transform 0.3s, box-shadow 0.3s;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,0.08); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; }
        .stat-card.c-all::before { background:var(--primary); }
        .stat-card.c-pending::before { background:#f59e0b; }
        .stat-card.c-approved::before { background:#10b981; }
        .stat-card.c-declined::before { background:#ef4444; }
        .stat-card.c-rating::before { background:#8b5cf6; }

        .stat-card .stat-icon {
            width:42px; height:42px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:18px; margin-bottom:10px;
        }
        .stat-card.c-all .stat-icon { background:rgba(217,108,63,0.12); color:var(--primary); }
        .stat-card.c-pending .stat-icon { background:rgba(245,158,11,0.14); color:#d97706; }
        .stat-card.c-approved .stat-icon { background:rgba(16,185,129,0.14); color:#059669; }
        .stat-card.c-declined .stat-icon { background:rgba(239,68,68,0.14); color:#dc2626; }
        .stat-card.c-rating .stat-icon { background:rgba(139,92,246,0.14); color:#7c3aed; }

        .stat-card .stat-value {
            font-family:'Playfair Display',serif; font-size:26px; font-weight:700; color:var(--dark);
        }
        .stat-card .stat-label { font-size:13px; color:var(--text-muted); margin-top:2px; font-weight:500; }

        /* Filter Tabs */
        .filter-tabs {
            display:flex; gap:8px; margin-bottom:20px; border-bottom:2px solid rgba(0,0,0,0.06); padding-bottom:12px;
            flex-wrap:wrap;
        }
        .filter-tab {
            display:inline-flex; align-items:center; gap:8px; padding:8px 16px;
            border-radius:24px; font-size:14px; font-weight:600; text-decoration:none;
            color:var(--text-muted); background:var(--white); transition:all 0.2s;
            box-shadow:0 1px 3px rgba(0,0,0,0.05);
        }
        .filter-tab:hover { color:var(--primary); background:rgba(217,108,63,0.08); }
        .filter-tab.active { background:var(--primary); color:#fff; box-shadow:0 3px 8px rgba(217,108,63,0.3); }
        .filter-tab .tab-badge {
            background:rgba(0,0,0,0.08); padding:2px 8px; border-radius:12px; font-size:12px;
        }
        .filter-tab.active .tab-badge { background:rgba(255,255,255,0.25); color:#fff; }

        /* Toolbar */
        .toolbar {
            background:var(--white); border-radius:var(--radius); padding:16px 20px;
            box-shadow:0 2px 10px rgba(0,0,0,0.04); margin-bottom:24px;
            display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
        }
        .search-box { position:relative; min-width:280px; }
        .search-box input {
            width:100%; padding:9px 14px 9px 38px;
            border:1.5px solid #e2e8f0; border-radius:8px;
            font-family:'Source Sans 3',sans-serif; font-size:14px;
            transition:border-color 0.2s; background:var(--white);
        }
        .search-box input:focus { outline:none; border-color:var(--primary); }
        .search-box i {
            position:absolute; left:12px; top:50%; transform:translateY(-50%);
            color:var(--text-muted); font-size:14px;
        }

        /* Review Cards / Table */
        .reviews-container { display:flex; flex-direction:column; gap:16px; }
        .review-row-card {
            background:var(--white); border-radius:12px; padding:22px 24px;
            box-shadow:0 2px 12px rgba(0,0,0,0.04); border-left:4px solid transparent;
            transition:box-shadow 0.25s, transform 0.2s;
        }
        .review-row-card:hover { box-shadow:0 6px 20px rgba(0,0,0,0.07); }
        .review-row-card.status-border-pending { border-left-color:#f59e0b; }
        .review-row-card.status-border-approved { border-left-color:#10b981; }
        .review-row-card.status-border-declined { border-left-color:#ef4444; }

        .review-card-header {
            display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;
            margin-bottom:12px;
        }
        .reviewer-info { display:flex; align-items:center; gap:14px; }
        .reviewer-avatar {
            width:46px; height:46px; border-radius:50%;
            background:linear-gradient(135deg, #f59e0b, #d97706);
            color:#fff; display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:17px; text-transform:uppercase; flex-shrink:0;
        }
        .reviewer-name { font-size:16px; font-weight:700; color:var(--dark); margin-bottom:2px; }
        .reviewer-meta {
            font-size:13px; color:var(--text-muted); display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        }
        .reviewer-meta span { display:inline-flex; align-items:center; gap:4px; }

        .review-header-right { display:flex; align-items:center; gap:12px; }

        /* Star Rating */
        .stars-display { color:#fbbf24; font-size:15px; display:inline-flex; gap:2px; }
        .stars-display .rating-val { font-size:13px; font-weight:700; color:var(--dark); margin-left:4px; }

        /* Status Badge */
        .status-badge {
            display:inline-flex; align-items:center; gap:6px;
            padding:5px 12px; border-radius:20px; font-size:12px;
            font-weight:700; letter-spacing:0.4px; text-transform:uppercase;
        }
        .status-badge.pending { background:#fef3c7; color:#b45309; }
        .status-badge.approved { background:#d1fae5; color:#065f46; }
        .status-badge.declined { background:#fee2e2; color:#991b1b; }
        .status-badge .dot { width:7px; height:7px; border-radius:50%; }
        .status-badge.pending .dot { background:#f59e0b; }
        .status-badge.approved .dot { background:#10b981; }
        .status-badge.declined .dot { background:#ef4444; }

        /* Review Content */
        .review-card-body { margin-bottom:16px; }
        .review-item-title { font-size:15px; font-weight:700; color:var(--dark); margin-bottom:6px; }
        .review-item-text { font-size:14px; line-height:1.65; color:#4a5568; margin:0; }

        /* Review Footer & Action Buttons */
        .review-card-footer {
            display:flex; align-items:center; justify-content:space-between;
            border-top:1px solid #f1f5f9; padding-top:14px; flex-wrap:wrap; gap:12px;
        }
        .review-date { font-size:12px; color:var(--text-muted); display:inline-flex; align-items:center; gap:5px; }

        .review-actions-group { display:flex; align-items:center; gap:8px; }
        .btn-act {
            display:inline-flex; align-items:center; gap:6px;
            padding:7px 14px; border-radius:8px; font-size:13px; font-weight:600;
            border:none; cursor:pointer; transition:all 0.2s; text-decoration:none;
        }
        .btn-act-accept {
            background:#10b981; color:#fff;
        }
        .btn-act-accept:hover { background:#059669; transform:translateY(-1px); }
        .btn-act-decline {
            background:#ef4444; color:#fff;
        }
        .btn-act-decline:hover { background:#dc2626; transform:translateY(-1px); }
        .btn-act-delete {
            background:#f1f5f9; color:#64748b;
        }
        .btn-act-delete:hover { background:#fee2e2; color:#ef4444; }

        .btn-act:disabled { opacity:0.6; cursor:not-allowed; }

        /* Toast notification */
        .toast-msg {
            position:fixed; bottom:24px; right:24px; z-index:9999;
            background:#1e293b; color:#fff; padding:14px 20px; border-radius:10px;
            box-shadow:0 8px 24px rgba(0,0,0,0.2); display:flex; align-items:center; gap:10px;
            font-size:14px; transform:translateY(100px); opacity:0; transition:all 0.3s cubic-bezier(0.16,1,0.3,1);
        }
        .toast-msg.show { transform:translateY(0); opacity:1; }

        /* Review Photos Gallery in Admin */
        .review-photos-section {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed #e2e8f0;
        }
        .review-thumbnails-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 8px;
        }
        .review-thumb-item {
            position: relative;
            width: 72px;
            height: 72px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e2e8f0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            background: #f8fafc;
            flex-shrink: 0;
            transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;
        }
        .review-thumb-item:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(217,108,63,0.18);
        }
        .review-thumb-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            display: block;
        }
        .review-thumb-item .btn-del-thumb {
            position: absolute;
            top: 3px;
            right: 3px;
            width: 20px;
            height: 20px;
            background: rgba(239, 68, 68, 0.92);
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0.85;
            transition: all 0.2s;
            box-shadow: 0 1px 4px rgba(0,0,0,0.25);
            padding: 0;
        }
        .review-thumb-item .btn-del-thumb:hover {
            background: #dc2626;
            opacity: 1;
            transform: scale(1.15);
        }
        .preview-mini-chip {
            position: relative;
            width: 50px;
            height: 50px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
        }
        .preview-mini-chip img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Responsive */
        @media(max-width:992px) {
            .stats-grid { grid-template-columns:repeat(3,1fr); }
        }
        @media(max-width:768px) {
            .admin-sidebar { display:none; }
            .admin-main { margin-left:0; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .review-card-header { flex-direction:column; align-items:flex-start; }
            .review-header-right { width:100%; justify-content:space-between; }
        }
        @media(max-width:480px) {
            .stats-grid { grid-template-columns:1fr; }
            .admin-content { padding:16px; }
        }
    </style>
</head>
<body>

<div class="admin-layout">

    <!-- ============ SIDEBAR ============ -->
    <?php include __DIR__ . '/sidebar-fragment.php'; ?>

    <!-- ============ MAIN ============ -->
    <div class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <h1><i class="fas fa-star" style="color:var(--accent);"></i> User Reviews Management</h1>
            <div class="admin-topbar-right">
                <span class="admin-name"><i class="fas fa-user-circle"></i> <?= e($_SESSION['admin_user']) ?></span>
                <a href="../index.php#reviews" target="_blank" class="btn btn-ghost btn-sm" title="View Public Reviews Section">
                    <i class="fas fa-external-link-alt"></i> View Live
                </a>
                <a href="logout.php" class="btn btn-ghost btn-sm" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="admin-content">

            <!-- Flash Message -->
            <?= flashMsg() ?>

            <!-- ============ STATS ============ -->
            <div class="stats-grid">
                <div class="stat-card c-all">
                    <div class="stat-icon"><i class="fas fa-comments"></i></div>
                    <div class="stat-value"><?= $totalReviews ?></div>
                    <div class="stat-label">Total Reviews</div>
                </div>
                <div class="stat-card c-pending">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?= $pendingReviews ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
                <div class="stat-card c-approved">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?= $approvedReviews ?></div>
                    <div class="stat-label">Accepted (Live)</div>
                </div>
                <div class="stat-card c-declined">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-value"><?= $declinedReviews ?></div>
                    <div class="stat-label">Declined</div>
                </div>
                <div class="stat-card c-rating">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-value"><?= $avgRating ?> <span style="font-size:14px;color:var(--text-muted);">/ 5</span></div>
                    <div class="stat-label">Avg Accepted Rating</div>
                </div>
            </div>

            <!-- ============ FILTER TABS ============ -->
            <div class="filter-tabs">
                <a href="reviews.php?filter=all<?= $search ? '&search=' . urlencode($search) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?>" 
                   class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> All Reviews 
                    <span class="tab-badge"><?= $totalReviews ?></span>
                </a>
                <a href="reviews.php?filter=pending<?= $search ? '&search=' . urlencode($search) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?>" 
                   class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> Pending 
                    <span class="tab-badge" style="<?= $pendingReviews > 0 ? 'background:#f59e0b;color:#fff;' : '' ?>"><?= $pendingReviews ?></span>
                </a>
                <a href="reviews.php?filter=approved<?= $search ? '&search=' . urlencode($search) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?>" 
                   class="filter-tab <?= $filter === 'approved' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Accepted 
                    <span class="tab-badge"><?= $approvedReviews ?></span>
                </a>
                <a href="reviews.php?filter=declined<?= $search ? '&search=' . urlencode($search) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?>" 
                   class="filter-tab <?= $filter === 'declined' ? 'active' : '' ?>">
                    <i class="fas fa-times-circle"></i> Declined 
                    <span class="tab-badge"><?= $declinedReviews ?></span>
                </a>
            </div>

            <!-- ============ TOOLBAR ============ -->
            <div class="toolbar">
                <div class="toolbar-left" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <form method="GET" action="reviews.php" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search reviews, names, or emails...">
                        </div>
                        <select name="sort" onchange="this.form.submit()" style="padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;background:#fff;cursor:pointer;">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="rating_high" <?= $sort === 'rating_high' ? 'selected' : '' ?>>Rating: High to Low</option>
                            <option value="rating_low" <?= $sort === 'rating_low' ? 'selected' : '' ?>>Rating: Low to High</option>
                        </select>
                        <?php if ($search): ?>
                            <a href="reviews.php?filter=<?= e($filter) ?>" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="toolbar-right">
                    <span style="font-size:13px;color:var(--text-muted);">
                        Showing <?= count($reviews) ?> of <?= $filteredCount ?> review<?= $filteredCount !== 1 ? 's' : '' ?>
                    </span>
                </div>
            </div>

            <!-- ============ REVIEWS LIST ============ -->
            <?php if (empty($reviews)): ?>
                <div style="background:#fff;border-radius:12px;padding:60px 20px;text-align:center;color:var(--text-muted);box-shadow:0 2px 10px rgba(0,0,0,0.04);">
                    <i class="fas fa-comment-slash" style="font-size:44px;color:#cbd5e1;margin-bottom:16px;"></i>
                    <h3 style="font-size:20px;color:var(--dark);margin-bottom:8px;">No reviews found</h3>
                    <p style="font-size:14px;margin-bottom:20px;">
                        <?= $search ? 'No reviews matching your search criteria.' : 'There are currently no reviews in this category.' ?>
                    </p>
                    <?php if ($search || $filter !== 'all'): ?>
                        <a href="reviews.php" class="btn btn-outline"><i class="fas fa-undo"></i> Show All Reviews</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="reviews-container">
                    <?php foreach ($reviews as $rev): 
                        $statusClass = $rev['status'] === 'approved' ? 'approved' : ($rev['status'] === 'declined' ? 'declined' : 'pending');
                        $initials = '';
                        $parts = explode(' ', trim($rev['user_name']));
                        foreach (array_slice($parts, 0, 2) as $p) {
                            $initials .= mb_substr($p, 0, 1);
                        }
                    ?>
                        <div class="review-row-card status-border-<?= $statusClass ?>" id="review-card-<?= (int)$rev['id'] ?>">
                            
                            <div class="review-card-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?= e($initials ?: 'U') ?>
                                    </div>
                                    <div>
                                        <div class="reviewer-name"><?= e($rev['user_name']) ?></div>
                                        <div class="reviewer-meta">
                                            <?php if (!empty($rev['user_location'])): ?>
                                                <span><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> <?= e($rev['user_location']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['user_email'])): ?>
                                                <span><i class="fas fa-envelope" style="color:#64748b;"></i> <?= e($rev['user_email']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="review-header-right">
                                    <div class="stars-display">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="<?= $s <= (int)$rev['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                        <?php endfor; ?>
                                        <span class="rating-val"><?= (int)$rev['rating'] ?>.0</span>
                                    </div>

                                    <span class="status-badge <?= $statusClass ?>" id="badge-<?= (int)$rev['id'] ?>">
                                        <span class="dot"></span>
                                        <span class="badge-text">
                                            <?= $rev['status'] === 'approved' ? 'Accepted' : ($rev['status'] === 'declined' ? 'Declined' : 'Pending') ?>
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <div class="review-card-body">
                                <?php if (!empty($rev['review_title'])): ?>
                                    <div class="review-item-title"><?= e($rev['review_title']) ?></div>
                                <?php endif; ?>
                                <p class="review-item-text"><?= nl2br(e($rev['review_text'])) ?></p>

                                <?php $revImages = $reviewImagesMap[$rev['id']] ?? []; ?>
                                <!-- Review Photos Section -->
                                <div class="review-photos-section" id="photos-sec-<?= (int)$rev['id'] ?>">
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span style="font-size:13px; font-weight:700; color:var(--dark); display:inline-flex; align-items:center; gap:6px;">
                                                <i class="fas fa-images" style="color:var(--primary);"></i> Attached Photos
                                            </span>
                                            <span class="photo-count-badge" id="photo-count-<?= (int)$rev['id'] ?>" style="background:rgba(217,108,63,0.12); color:var(--primary); font-size:11px; padding:2px 8px; border-radius:10px; font-weight:700;">
                                                <?= count($revImages) ?> photo<?= count($revImages) !== 1 ? 's' : '' ?>
                                            </span>
                                        </div>
                                        <button type="button" class="btn btn-ghost btn-sm" onclick="openUploadModal(<?= (int)$rev['id'] ?>)" style="font-size:12px; padding:4px 10px; display:inline-flex; align-items:center; gap:6px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                                            <i class="fas fa-plus" style="color:var(--primary);"></i> Add Photos
                                        </button>
                                    </div>

                                    <div class="review-thumbnails-grid" id="thumbnails-grid-<?= (int)$rev['id'] ?>">
                                        <?php if (!empty($revImages)): ?>
                                            <?php foreach ($revImages as $img): 
                                                $imgUrl = reviewImageUrl($img['image']) ?: ('../review_folder/' . rawurlencode($img['image']));
                                            ?>
                                                <div class="review-thumb-item" id="thumb-item-<?= (int)$img['id'] ?>">
                                                    <img src="<?= e($imgUrl) ?>" alt="Review Photo" onclick="previewImage('<?= e($imgUrl) ?>')" title="Click to view full photo">
                                                    <button type="button" class="btn-del-thumb" onclick="deleteSinglePhoto(<?= (int)$img['id'] ?>, <?= (int)$rev['id'] ?>)" title="Delete this photo from review">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="no-photos-msg" style="font-size:12px; color:var(--text-muted); font-style:italic;">No photos uploaded with this review.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="review-card-footer">
                                <div class="review-date">
                                    <i class="far fa-calendar-alt"></i> Submitted on <?= date('M d, Y · h:i A', strtotime($rev['created_at'])) ?>
                                </div>

                                <div class="review-actions-group" id="actions-<?= (int)$rev['id'] ?>">
                                    
                                    <!-- ACCEPT BUTTON -->
                                    <button type="button" 
                                            class="btn-act btn-act-accept" 
                                            onclick="handleReviewAction(<?= (int)$rev['id'] ?>, 'accept')"
                                            title="Accept and publish review on website"
                                            style="<?= $rev['status'] === 'approved' ? 'opacity:0.6;' : '' ?>">
                                        <i class="fas fa-check"></i> 
                                        <?= $rev['status'] === 'approved' ? 'Accepted' : 'Accept Review' ?>
                                    </button>

                                    <!-- DECLINE BUTTON -->
                                    <button type="button" 
                                            class="btn-act btn-act-decline" 
                                            onclick="handleReviewAction(<?= (int)$rev['id'] ?>, 'decline')"
                                            title="Decline review (hide from website)"
                                            style="<?= $rev['status'] === 'declined' ? 'opacity:0.6;' : '' ?>">
                                        <i class="fas fa-times"></i> 
                                        <?= $rev['status'] === 'declined' ? 'Declined' : 'Decline Review' ?>
                                    </button>

                                    <!-- DELETE BUTTON -->
                                    <button type="button" 
                                            class="btn-act btn-act-delete" 
                                            onclick="deleteReview(<?= (int)$rev['id'] ?>)" 
                                            title="Permanently Delete Review">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:28px;">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <a href="reviews.php?page=<?= $p ?>&filter=<?= e($filter) ?>&search=<?= urlencode($search) ?>&sort=<?= e($sort) ?>" 
                               style="padding:8px 14px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;
                                      background:<?= $p === $page ? 'var(--primary)' : '#fff' ?>;
                                      color:<?= $p === $page ? '#fff' : 'var(--text)' ?>;
                                      box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Toast notification element -->
<div id="toast" class="toast-msg">
    <i class="fas fa-check-circle" style="color:#10b981;font-size:18px;"></i>
    <span id="toast-text">Action completed successfully.</span>
</div>

<!-- Admin Upload Photos Modal -->
<div id="adminUploadModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(4px); z-index:99999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:480px; box-shadow:0 20px 40px rgba(0,0,0,0.2); overflow:hidden; animation:reviewModalPop 0.25s ease;">
        <div style="padding:18px 24px; background:linear-gradient(135deg, var(--dark), var(--dark-lighter)); color:#fff; display:flex; align-items:center; justify-content:space-between;">
            <h4 style="margin:0; font-size:16px; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-camera-retro" style="color:var(--accent);"></i> Add Photos to Review
            </h4>
            <button type="button" onclick="closeUploadModal()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; width:28px; height:28px; border-radius:50%; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center;" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="adminUploadForm" onsubmit="submitAdminUpload(event)" style="padding:22px 24px;">
            <input type="hidden" name="id" id="uploadModalReviewId" value="">
            <input type="hidden" name="action" value="upload_images">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="ajax" value="1">

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;">
                    Select Photos (JPG, PNG, WebP, GIF · Max 5MB each)
                </label>
                <input type="file" name="review_images[]" id="adminImagesInput" multiple accept="image/*" required style="width:100%; padding:12px; border:1.5px dashed #cbd5e1; border-radius:8px; font-size:13px; background:#f8fafc; cursor:pointer; box-sizing:border-box;" onchange="previewAdminSelectedImages(this)">
                <div id="adminFilePreviewStrip" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px;"></div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeUploadModal()" class="btn btn-ghost btn-sm" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="adminUploadBtn" class="btn btn-primary btn-sm" style="padding:8px 18px; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-cloud-upload-alt"></i> Upload Photos
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Admin Lightbox Modal -->
<div id="adminLightbox" onclick="closeAdminLightbox()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:999999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; cursor:zoom-out;">
    <button type="button" onclick="closeAdminLightbox()" style="position:absolute; top:20px; right:25px; background:rgba(255,255,255,0.2); border:none; color:#fff; width:40px; height:40px; border-radius:50%; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Close preview">
        <i class="fas fa-times"></i>
    </button>
    <img id="adminLightboxImg" src="" alt="Full Preview" style="max-width:90vw; max-height:85vh; border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,0.5); object-fit:contain;" onclick="event.stopPropagation()">
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

function showToast(msg, isSuccess = true) {
    const toast = document.getElementById('toast');
    const toastText = document.getElementById('toast-text');
    const icon = toast.querySelector('i');
    
    toastText.textContent = msg;
    if (isSuccess) {
        icon.className = 'fas fa-check-circle';
        icon.style.color = '#10b981';
    } else {
        icon.className = 'fas fa-exclamation-circle';
        icon.style.color = '#ef4444';
    }
    
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

function previewImage(url) {
    const lb = document.getElementById('adminLightbox');
    const img = document.getElementById('adminLightboxImg');
    img.src = url;
    lb.style.display = 'flex';
}

function closeAdminLightbox() {
    const lb = document.getElementById('adminLightbox');
    lb.style.display = 'none';
    document.getElementById('adminLightboxImg').src = '';
}

function deleteSinglePhoto(imageId, reviewId) {
    if (!confirm('Are you sure you want to remove this photo from the review? The image file will be deleted.')) {
        return;
    }

    const thumb = document.getElementById('thumb-item-' + imageId);
    if (thumb) {
        thumb.style.opacity = '0.5';
        thumb.style.pointerEvents = 'none';
    }

    const formData = new FormData();
    formData.append('action', 'delete_image');
    formData.append('image_id', imageId);
    formData.append('id', reviewId);
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('ajax', '1');

    fetch('review-action.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(data.message || 'Photo removed successfully.', true);
            if (thumb) {
                thumb.style.transform = 'scale(0.8)';
                thumb.style.opacity = '0';
                setTimeout(() => {
                    thumb.remove();
                    // Update badge
                    const badge = document.getElementById('photo-count-' + reviewId);
                    if (badge) {
                        badge.textContent = data.remaining_count + ' photo' + (data.remaining_count !== 1 ? 's' : '');
                    }
                    const grid = document.getElementById('thumbnails-grid-' + reviewId);
                    if (grid && data.remaining_count === 0) {
                        grid.innerHTML = '<span class="no-photos-msg" style="font-size:12px; color:var(--text-muted); font-style:italic;">No photos uploaded with this review.</span>';
                    }
                }, 200);
            }
        } else {
            if (thumb) {
                thumb.style.opacity = '1';
                thumb.style.pointerEvents = 'auto';
            }
            showToast(data.error || 'Failed to remove photo.', false);
        }
    })
    .catch(err => {
        if (thumb) {
            thumb.style.opacity = '1';
            thumb.style.pointerEvents = 'auto';
        }
        showToast('Network error while deleting photo.', false);
    });
}

function openUploadModal(reviewId) {
    document.getElementById('uploadModalReviewId').value = reviewId;
    document.getElementById('adminImagesInput').value = '';
    document.getElementById('adminFilePreviewStrip').innerHTML = '';
    document.getElementById('adminUploadModal').style.display = 'flex';
}

function closeUploadModal() {
    document.getElementById('adminUploadModal').style.display = 'none';
}

function previewAdminSelectedImages(input) {
    const strip = document.getElementById('adminFilePreviewStrip');
    strip.innerHTML = '';
    if (!input.files || input.files.length === 0) return;

    Array.from(input.files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'preview-mini-chip';
            div.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            strip.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

function submitAdminUpload(e) {
    e.preventDefault();
    const form = document.getElementById('adminUploadForm');
    const btn = document.getElementById('adminUploadBtn');
    const reviewId = document.getElementById('uploadModalReviewId').value;
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

    const formData = new FormData(form);

    fetch('review-action.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (data.ok) {
            showToast(data.message || 'Photos uploaded successfully!', true);
            closeUploadModal();

            // Append new thumbnails to the review card
            const grid = document.getElementById('thumbnails-grid-' + reviewId);
            const noMsg = grid ? grid.querySelector('.no-photos-msg') : null;
            if (noMsg) noMsg.remove();

            if (grid && data.uploaded && Array.isArray(data.uploaded)) {
                data.uploaded.forEach(item => {
                    const thumbDiv = document.createElement('div');
                    thumbDiv.className = 'review-thumb-item';
                    thumbDiv.id = 'thumb-item-' + item.id;
                    thumbDiv.innerHTML = `
                        <img src="${item.url}" alt="Review Photo" onclick="previewImage('${item.url}')" title="Click to view full photo">
                        <button type="button" class="btn-del-thumb" onclick="deleteSinglePhoto(${item.id}, ${reviewId})" title="Delete this photo from review">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    grid.appendChild(thumbDiv);
                });

                // Update count badge
                const allThumbs = grid.querySelectorAll('.review-thumb-item');
                const badge = document.getElementById('photo-count-' + reviewId);
                if (badge) {
                    badge.textContent = allThumbs.length + ' photo' + (allThumbs.length !== 1 ? 's' : '');
                }
            }
        } else {
            showToast(data.error || 'Failed to upload photos.', false);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showToast('Network error during photo upload.', false);
    });
}

function handleReviewAction(id, action) {
    const card = document.getElementById('review-card-' + id);
    const badge = document.getElementById('badge-' + id);
    const actionsGroup = document.getElementById('actions-' + id);
    
    // Disable buttons temporarily
    const buttons = actionsGroup.querySelectorAll('button');
    buttons.forEach(b => b.disabled = true);
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', action);
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('ajax', '1');

    fetch('review-action.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        buttons.forEach(b => b.disabled = false);
        if (data.ok) {
            showToast(data.message, true);
            
            // Update UI border
            card.classList.remove('status-border-pending', 'status-border-approved', 'status-border-declined');
            card.classList.add('status-border-' + data.status);
            
            // Update Badge
            badge.className = 'status-badge ' + data.status;
            badge.querySelector('.badge-text').textContent = data.status_label;
            
            // Update button styles
            const acceptBtn = actionsGroup.querySelector('.btn-act-accept');
            const declineBtn = actionsGroup.querySelector('.btn-act-decline');
            
            if (data.status === 'approved') {
                acceptBtn.innerHTML = '<i class="fas fa-check"></i> Accepted';
                acceptBtn.style.opacity = '0.6';
                declineBtn.innerHTML = '<i class="fas fa-times"></i> Decline Review';
                declineBtn.style.opacity = '1';
            } else if (data.status === 'declined') {
                acceptBtn.innerHTML = '<i class="fas fa-check"></i> Accept Review';
                acceptBtn.style.opacity = '1';
                declineBtn.innerHTML = '<i class="fas fa-times"></i> Declined';
                declineBtn.style.opacity = '0.6';
            }
        } else {
            showToast(data.error || 'Failed to update review status', false);
        }
    })
    .catch(err => {
        buttons.forEach(b => b.disabled = false);
        showToast('Network error: Could not complete action.', false);
    });
}

function deleteReview(id) {
    if (!confirm('Are you sure you want to permanently delete this review and all its photos? This action cannot be undone.')) {
        return;
    }
    
    const card = document.getElementById('review-card-' + id);
    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', 'delete');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('ajax', '1');

    fetch('review-action.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(data.message, true);
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            card.style.transition = 'all 0.3s';
            setTimeout(() => {
                card.remove();
            }, 300);
        } else {
            showToast(data.error || 'Failed to delete review.', false);
        }
    })
    .catch(err => {
        showToast('Network error while deleting.', false);
    });
}
</script>

</body>
</html>
