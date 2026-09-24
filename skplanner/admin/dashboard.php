<?php
// ============================================
// SK Travel Planner — Admin Dashboard
// Full data table with images, stats, search,
// filter, pagination, and all CRUD actions
// ============================================
require_once __DIR__ . '/../config.php';
requireAdmin();

// ============================================
// Fetch stats
// ============================================
 $totalItins   = $pdo->query("SELECT COUNT(*) FROM itineraries")->fetchColumn();
 $activeItins  = $pdo->query("SELECT COUNT(*) FROM itineraries WHERE status = 1")->fetchColumn();
 $inactiveItins = $totalItins - $activeItins;
 $totalImages  = $pdo->query("SELECT COUNT(*) FROM itineraries WHERE image IS NOT NULL AND image != ''")->fetchColumn();
 $totalRevenue = $pdo->query("SELECT COALESCE(SUM(price),0) FROM itineraries WHERE status = 1")->fetchColumn();

// Recent activity
 $recentItins = $pdo->query("SELECT title, created_at FROM itineraries ORDER BY created_at DESC LIMIT 5")->fetchAll();

// ============================================
// Search, Filter, Sort, Pagination
// ============================================
 $search    = trim($_GET['search'] ?? '');
 $filter    = $_GET['filter'] ?? 'all';    // all, active, inactive
 $sort      = $_GET['sort'] ?? 'newest';   // newest, oldest, price_high, price_low, name_asc, name_desc
 $page      = max(1, (int)($_GET['page'] ?? 1));
 $perPage   = 8;
 $offset    = ($page - 1) * $perPage;

// Build query
 $whereClauses = [];
 $params = [];

if ($search !== '') {
    $whereClauses[] = "(title LIKE ? OR destination LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filter === 'active') {
    $whereClauses[] = "status = 1";
} elseif ($filter === 'inactive') {
    $whereClauses[] = "status = 0";
}

 $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Sort
 $sortMap = [
    'newest'     => 'created_at DESC',
    'oldest'     => 'created_at ASC',
    'price_high' => 'price DESC',
    'price_low'  => 'price ASC',
    'name_asc'   => 'title ASC',
    'name_desc'  => 'title DESC',
];
 $sortSQL = $sortMap[$sort] ?? 'created_at DESC';

// Count filtered
 $countStmt = $pdo->prepare("SELECT COUNT(*) FROM itineraries {$whereSQL}");
 $countStmt->execute($params);
 $filteredTotal = $countStmt->fetchColumn();
 $totalPages = max(1, ceil($filteredTotal / $perPage));

// Clamp page
if ($page > $totalPages) $page = $totalPages;
 $offset = ($page - 1) * $perPage;

// Fetch itineraries
 $dataStmt = $pdo->prepare("SELECT * FROM itineraries {$whereSQL} ORDER BY {$sortSQL} LIMIT {$perPage} OFFSET {$offset}");
 $dataStmt->execute($params);
 $itineraries = $dataStmt->fetchAll();

// Build query string for pagination (preserve search/filter/sort)
 $queryParams = http_build_query([
    'search' => $search,
    'filter' => $filter,
    'sort'   => $sort,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= e(APP_NAME) ?> Admin</title>
     <link rel="icon" type="image/png" sizes="32x32" href="../logo.jpg">
        <link rel="icon" type="image/png" sizes="16x16" href="../logo.jpg">
        <link rel="icon" type="image/x-icon" href="../logo.jpg">

        <!-- Apple Touch Icon -->
        <link rel="apple-touch-icon" sizes="180x180" href="../logo.jpg">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================
           Dashboard Extended Styles
           ============================================ */

        /* ---- Layout ---- */
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
        }
        .admin-nav { list-style:none; padding:16px 0; }
        .admin-nav li a {
            display:flex; align-items:center; gap:12px;
            padding:12px 24px; color:rgba(255,255,255,0.65);
            font-size:15px; font-weight:500; transition:all 0.3s;
        }
        .admin-nav li a:hover, .admin-nav li a.active {
            color:var(--white); background:rgba(255,255,255,0.08);
        }
        .admin-nav li a i { width:20px; text-align:center; }
        .admin-nav .nav-divider {
            margin:16px 24px; border-top:1px solid rgba(255,255,255,0.08);
        }

        .admin-main { margin-left:260px; flex:1; background:var(--light); min-height:100vh; }

        .admin-topbar {
            background:var(--white); padding:16px 30px;
            display:flex; align-items:center; justify-content:space-between;
            box-shadow:0 1px 6px rgba(0,0,0,0.06); position:sticky; top:0; z-index:100;
        }
        .admin-topbar h1 { font-size:22px; display:flex; align-items:center; gap:10px; }
        .admin-topbar h1 i { color:var(--primary); }
        .admin-topbar-right { display:flex; align-items:center; gap:16px; }
        .admin-topbar-right .admin-name {
            font-size:14px; color:var(--text-muted);
            display:flex; align-items:center; gap:8px;
        }
        .admin-topbar-right .admin-name i { color:var(--primary); }

        .admin-content { padding:30px; }

        /* ---- Stats Cards ---- */
        .stats-grid {
            display:grid; grid-template-columns:repeat(5,1fr); gap:20px; margin-bottom:30px;
        }
        .stat-card {
            background:var(--white); border-radius:var(--radius); padding:22px 20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.04); position:relative; overflow:hidden;
            transition:transform 0.3s, box-shadow 0.3s;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,0.08); }
        .stat-card::before {
            content:''; position:absolute; top:0; left:0; right:0; height:4px;
        }
        .stat-card:nth-child(1)::before { background:var(--primary); }
        .stat-card:nth-child(2)::before { background:var(--success); }
        .stat-card:nth-child(3)::before { background:var(--danger); }
        .stat-card:nth-child(4)::before { background:var(--accent); }
        .stat-card:nth-child(5)::before { background:var(--warning); }

        .stat-card .stat-icon {
            width:44px; height:44px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:18px; margin-bottom:12px;
        }
        .stat-card:nth-child(1) .stat-icon { background:rgba(13,115,119,0.1); color:var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background:rgba(46,139,87,0.1); color:var(--success); }
        .stat-card:nth-child(3) .stat-icon { background:rgba(220,53,69,0.1); color:var(--danger); }
        .stat-card:nth-child(4) .stat-icon { background:rgba(232,145,45,0.1); color:var(--accent); }
        .stat-card:nth-child(5) .stat-icon { background:rgba(212,160,23,0.1); color:var(--warning); }

        .stat-card .stat-value {
            font-family:'Playfair Display',serif; font-size:28px; font-weight:700; color:var(--dark);
        }
        .stat-card .stat-label { font-size:13px; color:var(--text-muted); margin-top:2px; }

        /* ---- Toolbar ---- */
        .toolbar {
            background:var(--white); border-radius:var(--radius); padding:20px 24px;
            box-shadow:0 2px 12px rgba(0,0,0,0.04); margin-bottom:24px;
            display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
        }
        .toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .toolbar-right { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }

        .search-box {
            position:relative; min-width:280px;
        }
        .search-box input {
            width:100%; padding:10px 16px 10px 40px;
            border:2px solid var(--border); border-radius:8px;
            font-family:'Source Sans 3',sans-serif; font-size:14px;
            transition:border-color 0.3s; background:var(--white);
        }
        .search-box input:focus { outline:none; border-color:var(--primary); }
        .search-box i {
            position:absolute; left:14px; top:50%; transform:translateY(-50%);
            color:var(--text-muted); font-size:14px;
        }

        .filter-select {
            padding:10px 36px 10px 14px; border:2px solid var(--border); border-radius:8px;
            font-family:'Source Sans 3',sans-serif; font-size:14px; font-weight:500;
            background:var(--white); cursor:pointer; appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%236B7280'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 12px center;
        }
        .filter-select:focus { outline:none; border-color:var(--primary); }

        .sort-select {
            padding:10px 36px 10px 14px; border:2px solid var(--border); border-radius:8px;
            font-family:'Source Sans 3',sans-serif; font-size:14px; font-weight:500;
            background:var(--white); cursor:pointer; appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%236B7280'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 12px center;
        }
        .sort-select:focus { outline:none; border-color:var(--primary); }

        /* ---- Data Table ---- */
        .table-wrapper {
            background:var(--white); border-radius:var(--radius); overflow:hidden;
            box-shadow:0 2px 12px rgba(0,0,0,0.04);
        }
        .data-table { width:100%; border-collapse:collapse; }
        .data-table thead { background:var(--dark); }
        .data-table th {
            padding:14px 18px; text-align:left; font-size:13px; font-weight:600;
            color:rgba(255,255,255,0.85); letter-spacing:0.3px; text-transform:uppercase;
            white-space:nowrap;
        }
        .data-table td {
            padding:16px 18px; border-bottom:1px solid var(--light-darker);
            font-size:14px; vertical-align:middle;
        }
        .data-table tbody tr { transition:background 0.2s; }
        .data-table tbody tr:hover { background:rgba(13,115,119,0.03); }
        .data-table tbody tr:last-child td { border-bottom:none; }

        /* Image thumbnail */
        .img-thumb {
            width:72px; height:48px; border-radius:8px; object-fit:cover;
            border:2px solid var(--light-darker); transition:transform 0.3s;
            cursor:pointer; background:var(--light);
        }
        .img-thumb:hover { transform:scale(1.1); border-color:var(--primary); }
        .img-placeholder {
            width:72px; height:48px; border-radius:8px; background:var(--light);
            display:flex; align-items:center; justify-content:center;
            color:var(--border); font-size:18px; border:2px solid var(--light-darker);
        }

        /* Title cell */
        .title-cell { max-width:200px; }
        .title-cell .itin-title {
            font-weight:600; color:var(--dark); font-size:15px;
            display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden;
            margin-bottom:2px;
        }
        .title-cell .itin-dest {
            font-size:12px; color:var(--primary); font-weight:500;
            display:flex; align-items:center; gap:4px;
        }

        /* Price */
        .price-cell { font-family:'Playfair Display',serif; font-weight:700; color:var(--primary-dark); font-size:16px; }

        /* Duration */
        .duration-badge {
            display:inline-flex; align-items:center; gap:4px;
            padding:4px 10px; background:rgba(13,115,119,0.08); border-radius:6px;
            font-size:13px; font-weight:600; color:var(--primary);
        }

        /* Status badge */
        .status-badge {
            display:inline-flex; align-items:center; gap:6px;
            padding:5px 14px; border-radius:20px; font-size:12px;
            font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
        }
        .status-active { background:rgba(46,139,87,0.1); color:#155724; }
        .status-inactive { background:rgba(220,53,69,0.1); color:#721c24; }
        .status-badge .status-dot {
            width:6px; height:6px; border-radius:50%;
        }
        .status-active .status-dot { background:var(--success); }
        .status-inactive .status-dot { background:var(--danger); }

        /* Action buttons */
        .action-btns { display:flex; gap:6px; flex-wrap:nowrap; }
        .action-btn {
            display:inline-flex; align-items:center; justify-content:center;
            width:32px; height:32px; border-radius:8px; border:none;
            cursor:pointer; transition:all 0.2s; font-size:13px;
        }
        .action-btn.view { background:rgba(13,115,119,0.08); color:var(--primary); }
        .action-btn.view:hover { background:var(--primary); color:var(--white); }
        .action-btn.edit { background:rgba(232,145,45,0.08); color:var(--accent); }
        .action-btn.edit:hover { background:var(--accent); color:var(--white); }
        .action-btn.toggle { background:rgba(46,139,87,0.08); color:var(--success); }
        .action-btn.toggle:hover { background:var(--success); color:var(--white); }
        .action-btn.toggle.off { background:rgba(220,53,69,0.08); color:var(--danger); }
        .action-btn.toggle.off:hover { background:var(--danger); color:var(--white); }
        .action-btn.del { background:rgba(220,53,69,0.06); color:var(--danger); }
        .action-btn.del:hover { background:var(--danger); color:var(--white); }
        .action-btn.pdf { background:rgba(212,160,23,0.08); color:var(--warning); }
        .action-btn.pdf:hover { background:var(--warning); color:var(--white); }

        /* ---- Pagination ---- */
        .pagination-bar {
            padding:16px 24px; display:flex; align-items:center; justify-content:space-between;
            border-top:1px solid var(--light-darker); font-size:14px; color:var(--text-muted);
        }
        .pagination-info span { font-weight:600; color:var(--dark); }
        .pagination-controls { display:flex; align-items:center; gap:4px; }
        .page-btn {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:36px; height:36px; border-radius:8px; border:2px solid transparent;
            font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s;
            background:var(--light); color:var(--text-muted);
            font-family:'Source Sans 3',sans-serif; text-decoration:none;
        }
        .page-btn:hover { border-color:var(--primary); color:var(--primary); }
        .page-btn.active { background:var(--primary); color:var(--white); border-color:var(--primary); }
        .page-btn.disabled { opacity:0.4; cursor:default; pointer-events:none; }

        /* ---- Empty State ---- */
        .empty-state {
            text-align:center; padding:60px 20px; color:var(--text-muted);
        }
        .empty-state i { font-size:48px; color:var(--border); margin-bottom:16px; display:block; }
        .empty-state h3 { font-size:18px; color:var(--dark); margin-bottom:6px; }
        .empty-state p { font-size:14px; margin-bottom:20px; }

        /* ---- Recent Activity ---- */
        .activity-section {
            margin-top:30px; display:grid; grid-template-columns:1fr 1fr; gap:24px;
        }
        .activity-card {
            background:var(--white); border-radius:var(--radius); padding:24px;
            box-shadow:0 2px 12px rgba(0,0,0,0.04);
        }
        .activity-card h3 {
            font-size:16px; margin-bottom:16px; display:flex; align-items:center; gap:8px;
        }
        .activity-card h3 i { color:var(--primary); }
        .activity-item {
            display:flex; align-items:center; gap:12px; padding:10px 0;
            border-bottom:1px solid var(--light-darker); font-size:14px;
        }
        .activity-item:last-child { border-bottom:none; }
        .activity-item .act-icon {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0;
        }
        .activity-item .act-icon.new { background:rgba(46,139,87,0.1); color:var(--success); }
        .activity-item .act-info { flex:1; }
        .activity-item .act-title { font-weight:600; color:var(--dark); font-size:14px; }
        .activity-item .act-time { font-size:12px; color:var(--text-muted); }

        /* Quick stats row in activity */
        .quick-stat {
            display:flex; align-items:center; gap:10px; padding:12px 0;
            border-bottom:1px solid var(--light-darker);
        }
        .quick-stat:last-child { border-bottom:none; }
        .quick-stat .qs-icon {
            width:36px; height:36px; border-radius:10px;
            display:flex; align-items:center; justify-content:center; font-size:16px;
        }
        .quick-stat .qs-value { font-family:'Playfair Display',serif; font-size:20px; font-weight:700; color:var(--dark); }
        .quick-stat .qs-label { font-size:12px; color:var(--text-muted); }

        /* ---- Image Lightbox ---- */
        .lightbox {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85);
            z-index:9999; align-items:center; justify-content:center; padding:40px;
            cursor:pointer;
        }
        .lightbox.show { display:flex; animation:fadeIn 0.3s ease; }
        .lightbox img {
            max-width:90%; max-height:90vh; border-radius:12px;
            box-shadow:0 20px 60px rgba(0,0,0,0.5);
        }
        .lightbox .close-btn {
            position:absolute; top:20px; right:20px;
            width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,0.15);
            color:white; border:none; font-size:18px; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
        }
        @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }

        /* ---- Delete Confirm Modal ---- */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6);
            z-index:9998; align-items:center; justify-content:center;
        }
        .modal-overlay.show { display:flex; animation:fadeIn 0.2s ease; }
        .modal-box {
            background:var(--white); border-radius:16px; padding:32px;
            width:90%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,0.3);
            text-align:center;
        }
        .modal-box .modal-icon {
            width:56px; height:56px; border-radius:50%;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:24px; color:white; margin-bottom:16px;
            background:linear-gradient(135deg,var(--danger),#b02a37);
        }
        .modal-box h3 { font-size:18px; margin-bottom:8px; color:var(--dark); }
        .modal-box p { font-size:14px; color:var(--text-muted); margin-bottom:24px; line-height:1.6; }
        .modal-box .modal-btns { display:flex; gap:10px; justify-content:center; }

        /* ---- Buttons ---- */
        .btn {
            display:inline-flex; align-items:center; gap:8px; padding:10px 20px;
            border:none; border-radius:8px; font-family:'Source Sans 3',sans-serif;
            font-size:14px; font-weight:600; cursor:pointer; transition:all 0.3s;
            text-decoration:none;
        }
        .btn-primary { background:var(--primary); color:var(--white); }
        .btn-primary:hover { background:var(--primary-dark); color:var(--white); }
        .btn-accent { background:var(--accent); color:var(--white); }
        .btn-accent:hover { background:var(--accent-dark); color:var(--white); }
        .btn-outline { background:transparent; color:var(--primary); border:2px solid var(--primary); }
        .btn-outline:hover { background:var(--primary); color:var(--white); }
        .btn-danger { background:var(--danger); color:var(--white); }
        .btn-danger:hover { background:#b02a37; color:var(--white); }
        .btn-ghost { background:transparent; color:var(--text-muted); }
        .btn-ghost:hover { background:var(--light); color:var(--dark); }

        /* ---- Flash ---- */
        .alert { padding:14px 18px; border-radius:8px; margin-bottom:20px; font-weight:500; font-size:14px; }
        .alert-success { background:#d4edda; color:#155724; border-left:4px solid var(--success); }
        .alert-danger { background:#f8d7da; color:#721c24; border-left:4px solid var(--danger); }

        /* ---- Responsive ---- */
        @media(max-width:1100px) {
            .stats-grid { grid-template-columns:repeat(3,1fr); }
        }
        @media(max-width:900px) {
            .admin-sidebar { width:200px; }
            .admin-main { margin-left:200px; }
            .stats-grid { grid-template-columns:repeat(2,1fr); }
            .activity-section { grid-template-columns:1fr; }
        }
        @media(max-width:768px) {
            .admin-sidebar { display:none; }
            .admin-main { margin-left:0; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .toolbar { flex-direction:column; align-items:stretch; }
            .toolbar-left, .toolbar-right { width:100%; }
            .search-box { min-width:100%; }
            .data-table { font-size:13px; }
            .data-table th, .data-table td { padding:10px 12px; }
            .img-thumb, .img-placeholder { width:56px; height:38px; }
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
    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <span class="brand-icon"><img src="../logo.jpg" alt="Logo"></span> SK Travel
        </div>
        <ul class="admin-nav">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="itinerary-form.php"><i class="fas fa-plus-circle"></i> New Itinerary</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> DB Backup</a></li>
            <div class="nav-divider"></div>
            <li><a href="../index.php"><i class="fas fa-globe"></i> View Website</a></li>
            <li><a href="change-password.php"><i class="fas fa-key"></i> Password update</a></li>
            <div class="nav-divider"></div>
            <li><a href="logout.php" style="color:rgba(255,255,255,0.4);"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <!-- ============ MAIN ============ -->
    <div class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            <div class="admin-topbar-right">
                <span class="admin-name"><i class="fas fa-user-circle"></i> <?= e($_SESSION['admin_user']) ?></span>
                <a href="logout.php" class="btn btn-ghost btn-sm"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="admin-content">

            <!-- Flash Message -->
            <?= flashMsg() ?>

            <!-- ============ STATS ============ -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-suitcase"></i></div>
                    <div class="stat-value"><?= (int)$totalItins ?></div>
                    <div class="stat-label">Total Itineraries</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?= (int)$activeItins ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
                    <div class="stat-value"><?= (int)$inactiveItins ?></div>
                    <div class="stat-label">Inactive</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-images"></i></div>
                    <div class="stat-value"><?= (int)$totalImages ?></div>
                    <div class="stat-label">With Images</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
                    <div class="stat-value"><?= e(formatPrice($totalRevenue)) ?></div>
                    <div class="stat-label">Total Value (Active)</div>
                </div>
            </div>

            <!-- ============ TOOLBAR ============ -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <form method="GET" id="filter-form" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search title or destination..." id="search-input">
                        </div>
                        <select name="filter" class="filter-select" id="filter-select">
                            <option value="all" <?= $filter==='all'?'selected':'' ?>>All Status</option>
                            <option value="active" <?= $filter==='active'?'selected':'' ?>>Active Only</option>
                            <option value="inactive" <?= $filter==='inactive'?'selected':'' ?>>Inactive Only</option>
                        </select>
                        <select name="sort" class="sort-select" id="sort-select">
                            <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest First</option>
                            <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest First</option>
                            <option value="price_high" <?= $sort==='price_high'?'selected':'' ?>>Price: High to Low</option>
                            <option value="price_low" <?= $sort==='price_low'?'selected':'' ?>>Price: Low to High</option>
                            <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>>Name: A to Z</option>
                            <option value="name_desc" <?= $sort==='name_desc'?'selected':'' ?>>Name: Z to A</option>
                        </select>
                        <?php if ($search || $filter !== 'all'): ?>
                            <a href="dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="toolbar-right">
                    <span style="font-size:13px;color:var(--text-muted);">
                        <?= (int)$filteredTotal ?> result<?= $filteredTotal!=1?'s':'' ?>
                    </span>
                    <a href="itinerary-form.php" class="btn btn-accent"><i class="fas fa-plus"></i> Add Itinerary</a>
                </div>
            </div>

            <!-- ============ DATA TABLE ============ -->
            <?php if (empty($itineraries)): ?>
                <div class="table-wrapper">
                    <div class="empty-state">
                        <?php if ($search || $filter !== 'all'): ?>
                            <i class="fas fa-search"></i>
                            <h3>No results found</h3>
                            <p>Try adjusting your search or filter criteria.</p>
                            <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-undo"></i> Reset Filters</a>
                        <?php else: ?>
                            <i class="fas fa-suitcase-rolling"></i>
                            <h3>No itineraries yet</h3>
                            <p>Create your first travel itinerary to get started.</p>
                            <a href="itinerary-form.php" class="btn btn-accent"><i class="fas fa-plus"></i> Create Itinerary</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="width:90px;">Image</th>
                                <th>Title</th>
                                <th>Duration</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th style="width:180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itineraries as $idx => $itin): ?>
                                <?php
                                    $hasImg = !empty($itin['image']) && file_exists(UPLOAD_DIR . $itin['image']);
                                    $imgSrc = $hasImg ? UPLOAD_URL . $itin['image'] : '';
                                    $days = json_decode($itin['day_plan'], true) ?? [];
                                    $rowNum = $offset + $idx + 1;
                                ?>
                                <tr>
                                    <!-- Row Number -->
                                    <td style="font-weight:600;color:var(--text-muted);"><?= $rowNum ?></td>

                                    <!-- Image -->
                                    <td>
                                        <?php if ($hasImg): ?>
                                            <img src="<?= e($imgSrc) ?>"
                                                 class="img-thumb"
                                                 alt="<?= e($itin['title']) ?>"
                                                 onclick="openLightbox('<?= e($imgSrc) ?>')"
                                                 loading="lazy">
                                        <?php else: ?>
                                            <div class="img-placeholder"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Title + Destination -->
                                    <td>
                                        <div class="title-cell">
                                            <div class="itin-title"><?= e($itin['title']) ?></div>
                                            <div class="itin-dest">
                                                <i class="fas fa-map-marker-alt"></i> <?= e($itin['destination']) ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Duration -->
                                    <td>
                                        <span class="duration-badge">
                                            <i class="fas fa-calendar-alt"></i> <?= (int)$itin['duration_days'] ?>d
                                        </span>
                                    </td>

                                    <!-- Price -->
                                    <td class="price-cell"><?= e(formatPrice($itin['price'])) ?></td>

                                    <!-- Status -->
                                    <td>
                                        <span class="status-badge <?= $itin['status'] ? 'status-active' : 'status-inactive' ?>">
                                            <span class="status-dot"></span>
                                            <?= $itin['status'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>

                                    <!-- Created -->
                                    <td style="font-size:13px;color:var(--text-muted);white-space:nowrap;">
                                        <?= date('M j, Y', strtotime($itin['created_at'])) ?>
                                    </td>

                                    <!-- Actions -->
                                    <td>
                                        <div class="action-btns">
                                            <a href="../detail.php?id=<?= (int)$itin['id'] ?>"
                                               class="action-btn view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="itinerary-form.php?id=<?= (int)$itin['id'] ?>"
                                               class="action-btn edit" title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <!-- <a href="../download-pdf.php?id=<?= (int)$itin['id'] ?>"
                                               class="action-btn pdf" title="Download PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a> -->
                                            <a href="itinerary-action.php?action=toggle&id=<?= (int)$itin['id'] ?>"
                                               class="action-btn toggle <?= $itin['status'] ? '' : 'off' ?>"
                                               title="<?= $itin['status'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $itin['status'] ? 'eye' : 'eye-slash' ?>"></i>
                                            </a>
                                            <button type="button"
                                                    class="action-btn del"
                                                    title="Delete"
                                                    onclick="confirmDelete(<?= (int)$itin['id'] ?>, '<?= e(addslashes($itin['title'])) ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-bar">
                            <div class="pagination-info">
                                Showing <span><?= $offset + 1 ?></span>–<span><?= min($offset + $perPage, $filteredTotal) ?></span>
                                of <span><?= (int)$filteredTotal ?></span> itineraries
                            </div>
                            <div class="pagination-controls">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page-1 ?>&<?= e($queryParams) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                                <?php else: ?>
                                    <span class="page-btn disabled"><i class="fas fa-chevron-left"></i></span>
                                <?php endif; ?>

                                <?php
                                    $startP = max(1, $page - 2);
                                    $endP   = min($totalPages, $page + 2);
                                    if ($startP > 1) echo '<a href="?page=1&'.e($queryParams).'" class="page-btn">1</a>';
                                    if ($startP > 2) echo '<span class="page-btn" style="border:none;background:none;cursor:default;">...</span>';
                                    for ($p = $startP; $p <= $endP; $p++) {
                                        $active = ($p == $page) ? 'active' : '';
                                        echo '<a href="?page='.$p.'&'.e($queryParams).'" class="page-btn '.$active.'">'.$p.'</a>';
                                    }
                                    if ($endP < $totalPages - 1) echo '<span class="page-btn" style="border:none;background:none;cursor:default;">...</span>';
                                    if ($endP < $totalPages) echo '<a href="?page='.$totalPages.'&'.e($queryParams).'" class="page-btn">'.$totalPages.'</a>';
                                ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page+1 ?>&<?= e($queryParams) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                                <?php else: ?>
                                    <span class="page-btn disabled"><i class="fas fa-chevron-right"></i></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ============ BOTTOM SECTION ============ -->
            <div class="activity-section">
                <!-- Recent Itineraries -->
                <div class="activity-card">
                    <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                    <?php if (empty($recentItins)): ?>
                        <p style="color:var(--text-muted);font-size:14px;padding:12px 0;">No itineraries created yet.</p>
                    <?php else: ?>
                        <?php foreach ($recentItins as $r): ?>
                            <div class="activity-item">
                                <div class="act-icon new"><i class="fas fa-plus"></i></div>
                                <div class="act-info">
                                    <div class="act-title"><?= e($r['title']) ?></div>
                                    <div class="act-time"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Quick Overview -->
                <div class="activity-card">
                    <h3><i class="fas fa-chart-pie"></i> Quick Overview</h3>
                    <div class="quick-stat">
                        <div class="qs-icon" style="background:rgba(13,115,119,0.1);color:var(--primary);"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="qs-value"><?= $totalItins > 0 ? round(($activeItins/$totalItins)*100) : 0 ?>%</div>
                            <div class="qs-label">Active Rate</div>
                        </div>
                    </div>
                    <div class="quick-stat">
                        <div class="qs-icon" style="background:rgba(232,145,45,0.1);color:var(--accent);"><i class="fas fa-camera"></i></div>
                        <div>
                            <div class="qs-value"><?= $totalItins > 0 ? round(($totalImages/$totalItins)*100) : 0 ?>%</div>
                            <div class="qs-label">Have Images</div>
                        </div>
                    </div>
                    <div class="quick-stat">
                        <div class="qs-icon" style="background:rgba(46,139,87,0.1);color:var(--success);"><i class="fas fa-rupee-sign"></i></div>
                        <div>
                            <div class="qs-value"><?= $activeItins > 0 ? e(formatPrice(round($totalRevenue/$activeItins))) : '₹0' ?></div>
                            <div class="qs-label">Average Price (Active)</div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<!-- ============ IMAGE LIGHTBOX ============ -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="close-btn" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
    <img src="" id="lightbox-img" alt="Preview" onclick="event.stopPropagation();">
</div>

<!-- ============ DELETE CONFIRM MODAL ============ -->
<div class="modal-overlay" id="delete-modal">
    <div class="modal-box" onclick="event.stopPropagation();">
        <div class="modal-icon"><i class="fas fa-trash-alt"></i></div>
        <h3>Delete Itinerary?</h3>
        <p id="delete-msg">This action permanently removes the itinerary and its image file. This cannot be undone.</p>
        <div class="modal-btns">
            <button class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
            <a href="" id="delete-link" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete</a>
        </div>
    </div>
</div>

<!-- ============ JAVASCRIPT ============ -->
<script>
// ---- Filter form auto-submit ----
document.getElementById('filter-select').addEventListener('change', function() {
    document.getElementById('filter-form').submit();
});
document.getElementById('sort-select').addEventListener('change', function() {
    document.getElementById('filter-form').submit();
});

// ---- Search debounce ----
var searchTimer;
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimer);
    var val = this.value;
    searchTimer = setTimeout(function() {
        var url = new URL(window.location.href);
        if (val) url.searchParams.set('search', val);
        else url.searchParams.delete('search');
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }, 600);
});

// ---- Image Lightbox ----
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightbox();
        closeDeleteModal();
    }
});

// ---- Delete Confirmation ----
function confirmDelete(id, title) {
    document.getElementById('delete-msg').textContent =
        'Are you sure you want to delete "' + title + '"? This action permanently removes the itinerary and its image file.';
    document.getElementById('delete-link').href =
        'itinerary-action.php?action=delete&id=' + id;
    document.getElementById('delete-modal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
    document.getElementById('delete-modal').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('delete-modal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>

</body>
</html>