<?php
declare(strict_types=1);

// ============================================================
// SK Travel Planner — Admin Travel Gallery Management
// Upload multiple images, organize by destination, edit, sort,
// toggle visibility, and batch manage travel photos.
// ============================================================

require_once __DIR__ . '/../config.php';
requireAdmin();

// Count statistics
$totalPhotos     = (int)$pdo->query("SELECT COUNT(*) FROM gallery_images")->fetchColumn();
$activePhotos    = (int)$pdo->query("SELECT COUNT(*) FROM gallery_images WHERE status = 1")->fetchColumn();
$inactivePhotos  = $totalPhotos - $activePhotos;
$destCount       = (int)$pdo->query("SELECT COUNT(DISTINCT destination) FROM gallery_images WHERE destination IS NOT NULL AND destination != ''")->fetchColumn();

// Fetch distinct destinations for filter and datalist autocomplete
$destListStmt = $pdo->query("SELECT DISTINCT destination FROM gallery_images WHERE destination IS NOT NULL AND destination != '' ORDER BY destination ASC");
$existingDestinations = $destListStmt->fetchAll(PDO::FETCH_COLUMN);

// Also fetch distinct destinations from itineraries for suggestions
try {
    $itinDestStmt = $pdo->query("SELECT DISTINCT destination FROM itineraries WHERE destination IS NOT NULL AND destination != ''");
    while ($iDest = $itinDestStmt->fetchColumn()) {
        $parts = array_map('trim', explode(',', (string)$iDest));
        foreach ($parts as $p) {
            if ($p !== '' && !in_array($p, $existingDestinations, true)) {
                $existingDestinations[] = $p;
            }
        }
    }
    sort($existingDestinations);
} catch (Throwable $e) {}

// Filters and Search
$search   = clean($_GET['search'] ?? '');
$dest     = clean($_GET['dest'] ?? 'all');
$filter   = clean($_GET['filter'] ?? 'all'); // all, active, inactive
$sort     = clean($_GET['sort'] ?? 'sort_order'); // sort_order, newest, oldest, title_asc, dest_asc
$viewMode = clean($_GET['view'] ?? 'grid'); // grid, table

$whereClauses = [];
$params       = [];

if ($search !== '') {
    $whereClauses[] = "(title LIKE ? OR destination LIKE ? OR description LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($dest !== '' && $dest !== 'all') {
    $whereClauses[] = "destination = ?";
    $params[] = $dest;
}

if ($filter === 'active') {
    $whereClauses[] = "status = 1";
} elseif ($filter === 'inactive') {
    $whereClauses[] = "status = 0";
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sortMap = [
    'sort_order' => 'sort_order ASC, created_at DESC',
    'newest'     => 'created_at DESC',
    'oldest'     => 'created_at ASC',
    'title_asc'  => 'title ASC',
    'dest_asc'   => 'destination ASC, title ASC',
];
$sortSql = $sortMap[$sort] ?? 'sort_order ASC, created_at DESC';

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 18;
$offset  = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM gallery_images {$whereSql}");
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($filteredTotal / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$dataStmt = $pdo->prepare("SELECT * FROM gallery_images {$whereSql} ORDER BY {$sortSql} LIMIT {$perPage} OFFSET {$offset}");
$dataStmt->execute($params);
$photos = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

$queryParams = http_build_query([
    'search' => $search,
    'dest'   => $dest,
    'filter' => $filter,
    'sort'   => $sort,
    'view'   => $viewMode,
]);

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Gallery Management — <?= e(APP_NAME) ?> Admin</title>
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
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-sidebar {
            width: 260px; background: var(--dark); color: var(--white);
            position: fixed; top: 0; left: 0; height: 100vh;
            overflow-y: auto; z-index: 900; flex-shrink: 0;
        }
        .admin-sidebar-brand {
            display: flex; align-items: center; gap: 12px;
            padding: 20px 24px; font-family: 'Playfair Display', serif;
            font-size: 20px; font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .admin-sidebar-brand .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;
            overflow: hidden;
        }
        .admin-sidebar-brand .brand-icon img { width: 100%; height: 100%; object-fit: cover; }
        .admin-nav { list-style: none; padding: 16px 0; }
        .admin-nav li a {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 24px; color: rgba(255,255,255,0.65);
            font-size: 15px; font-weight: 500; transition: all 0.3s;
            text-decoration: none;
        }
        .admin-nav li a:hover, .admin-nav li a.active {
            color: var(--white); background: rgba(255,255,255,0.08);
        }
        .admin-nav li a i { width: 20px; text-align: center; }
        .admin-nav .nav-divider {
            margin: 16px 24px; border-top: 1px solid rgba(255,255,255,0.08);
        }

        .admin-main { margin-left: 260px; flex: 1; background: var(--light); min-height: 100vh; display: flex; flex-direction: column; }
        .admin-topbar {
            background: var(--white); padding: 16px 30px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 100;
        }
        .admin-topbar h1 { font-size: 22px; display: flex; align-items: center; gap: 10px; margin: 0; }
        .admin-topbar h1 i { color: var(--primary); }
        .admin-topbar-right { display: flex; align-items: center; gap: 16px; }
        .admin-topbar-right .admin-name {
            font-size: 14px; color: var(--text-muted);
            display: flex; align-items: center; gap: 8px;
        }
        .admin-topbar-right .admin-name i { color: var(--primary); }

        .admin-content { padding: 30px; flex: 1; }

        /* Stats Cards */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--white); border-radius: var(--radius); padding: 22px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04); position: relative; overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        }
        .stat-card:nth-child(1)::before { background: var(--primary); }
        .stat-card:nth-child(2)::before { background: var(--success); }
        .stat-card:nth-child(3)::before { background: var(--warning); }
        .stat-card:nth-child(4)::before { background: var(--accent); }

        .stat-card .stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; margin-bottom: 12px;
        }
        .stat-card:nth-child(1) .stat-icon { background: rgba(217,108,63,0.12); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(62,142,104,0.12); color: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(217,154,43,0.12); color: var(--warning); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(244,185,66,0.15); color: var(--accent-dark); }

        .stat-card .stat-value {
            font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 700; color: var(--dark);
        }
        .stat-card .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

        /* Upload Panel */
        .upload-card {
            background: var(--white); border-radius: var(--radius);
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 30px;
            border: 1px solid rgba(217,108,63,0.15); overflow: hidden;
        }
        .upload-header {
            background: linear-gradient(135deg, var(--dark), var(--dark-lighter));
            color: var(--white); padding: 18px 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .upload-header h2 {
            font-size: 18px; color: var(--white); margin: 0;
            display: flex; align-items: center; gap: 10px;
        }
        .upload-header .badge {
            background: var(--accent); color: var(--dark);
            padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;
        }
        .upload-body { padding: 24px; }

        .dropzone-wrap {
            border: 2px dashed #cbd5e1; border-radius: 14px;
            padding: 32px 20px; text-align: center; background: #faf8f5;
            cursor: pointer; transition: all 0.25s ease; position: relative;
        }
        .dropzone-wrap:hover, .dropzone-wrap.dragover {
            border-color: var(--primary); background: #fff5eb; transform: scale(1.005);
        }
        .dropzone-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: rgba(217,108,63,0.1); color: var(--primary);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 28px; margin-bottom: 12px;
        }
        .dropzone-title { font-size: 17px; font-weight: 700; color: var(--dark); margin-bottom: 4px; }
        .dropzone-sub { font-size: 13px; color: var(--text-muted); }

        .preview-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px; margin-top: 18px; max-height: 280px; overflow-y: auto; padding: 4px;
        }
        .preview-item {
            position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden;
            border: 2px solid #e2e8f0; background: #000; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .preview-item .remove-btn {
            position: absolute; top: 4px; right: 4px;
            width: 22px; height: 22px; border-radius: 50%;
            background: rgba(220,53,69,0.9); color: #fff; border: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; cursor: pointer;
        }
        .preview-item .size-tag {
            position: absolute; bottom: 4px; left: 4px; right: 4px;
            background: rgba(0,0,0,0.65); color: #fff; font-size: 10px;
            padding: 2px 4px; border-radius: 4px; text-align: center;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .upload-meta-grid {
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 20px;
        }
        .form-group label {
            display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--dark);
        }
        .form-control {
            width: 100%; padding: 10px 14px; border: 1.5px solid var(--border);
            border-radius: 8px; font-family: inherit; font-size: 14px;
            background: var(--white); transition: border-color 0.25s; box-sizing: border-box;
        }
        .form-control:focus { outline: none; border-color: var(--primary); }

        .progress-bar-wrap {
            display: none; margin-top: 18px; height: 8px;
            background: #e2e8f0; border-radius: 999px; overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%; width: 0; background: linear-gradient(90deg, var(--accent), var(--primary));
            transition: width 0.2s ease;
        }

        /* Toolbar */
        .toolbar {
            background: var(--white); border-radius: var(--radius); padding: 18px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        }
        .toolbar-left, .toolbar-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

        .search-box { position: relative; min-width: 240px; }
        .search-box input {
            width: 100%; padding: 9px 14px 9px 38px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: inherit; font-size: 14px; background: var(--white);
        }
        .search-box input:focus { outline: none; border-color: var(--primary); }
        .search-box i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 14px;
        }

        .filter-select {
            padding: 9px 32px 9px 14px; border: 1.5px solid var(--border); border-radius: 8px;
            font-family: inherit; font-size: 14px; background: var(--white); cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%236B7280'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
        }

        .view-toggle { display: flex; border: 1.5px solid var(--border); border-radius: 8px; overflow: hidden; }
        .view-btn {
            background: var(--white); border: 0; padding: 8px 14px;
            color: var(--text-muted); cursor: pointer; font-size: 14px; transition: all 0.2s;
        }
        .view-btn.active { background: var(--primary); color: #fff; }

        /* Bulk Action Bar */
        .bulk-bar {
            background: #fff8f0; border: 1.5px solid #fed7aa; border-radius: 10px;
            padding: 12px 20px; margin-bottom: 20px; display: none;
            align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
        }
        .bulk-bar.active { display: flex; }
        .bulk-count { font-weight: 700; color: var(--dark); font-size: 14px; }
        .bulk-actions { display: flex; align-items: center; gap: 10px; }

        /* Grid View */
        .gallery-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 22px; margin-bottom: 30px;
        }
        .photo-card {
            background: var(--white); border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.06);
            display: flex; flex-direction: column; transition: transform 0.25s, box-shadow 0.25s;
            position: relative;
        }
        .photo-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.12); }
        .photo-thumb-wrap {
            position: relative; aspect-ratio: 16/11; background: #e2e8f0; overflow: hidden;
        }
        .photo-thumb-wrap img {
            width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease;
        }
        .photo-card:hover .photo-thumb-wrap img { transform: scale(1.05); }

        .photo-select-box {
            position: absolute; top: 10px; left: 10px; z-index: 5;
            background: rgba(255,255,255,0.9); border-radius: 6px; padding: 4px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .photo-select-box input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }

        .photo-dest-badge {
            position: absolute; top: 10px; right: 10px;
            background: rgba(41,33,58,0.85); color: #fff;
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
            backdrop-filter: blur(4px); z-index: 4;
        }
        .photo-zoom-btn {
            position: absolute; inset: 0; background: rgba(0,0,0,0.4);
            opacity: 0; display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 24px; cursor: pointer; transition: opacity 0.25s;
            border: 0;
        }
        .photo-thumb-wrap:hover .photo-zoom-btn { opacity: 1; }

        .photo-card-body {
            padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 6px;
        }
        .photo-title {
            font-size: 16px; font-weight: 700; color: var(--dark);
            margin: 0; line-height: 1.35;
        }
        .photo-desc {
            font-size: 13px; color: var(--text-muted); line-height: 1.5;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .photo-meta {
            display: flex; align-items: center; justify-content: space-between;
            font-size: 12px; color: #94a3b8; margin-top: auto; padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }

        .photo-card-foot {
            padding: 12px 16px; background: #faf8f5; border-top: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
        }

        /* Toggle switch */
        .toggle-switch {
            position: relative; display: inline-flex; align-items: center; gap: 8px;
            cursor: pointer; font-size: 12px; font-weight: 600;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .toggle-slider {
            width: 38px; height: 22px; background: #cbd5e1; border-radius: 999px;
            position: relative; transition: background 0.3s;
        }
        .toggle-slider::before {
            content: ''; position: absolute; top: 2px; left: 2px; width: 18px; height: 18px;
            background: #fff; border-radius: 50%; transition: transform 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle-switch input:checked + .toggle-slider { background: var(--success); }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(16px); }

        .btn-group { display: flex; gap: 6px; }

        /* Table View */
        .table-card {
            background: var(--white); border-radius: var(--radius);
            box-shadow: 0 2px 12px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 30px;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 14px 18px; text-align: left; font-size: 14px; }
        .data-table th { background: #faf8f5; color: var(--dark); font-weight: 700; border-bottom: 2px solid #e2e8f0; }
        .data-table td { border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .data-table tr:hover td { background: #fdfbf7; }
        .table-thumb {
            width: 64px; height: 48px; border-radius: 8px; object-fit: cover; cursor: pointer;
        }

        /* Empty State */
        .empty-box {
            background: var(--white); border-radius: 16px; padding: 60px 20px;
            text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .empty-box i { font-size: 56px; color: var(--accent); margin-bottom: 16px; }
        .empty-box h3 { font-size: 20px; margin-bottom: 8px; color: var(--dark); }
        .empty-box p { font-size: 14px; color: var(--text-muted); max-width: 440px; margin: 0 auto 20px; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px;
            border: none; border-radius: 8px; font-family: inherit; font-size: 14px;
            font-weight: 600; cursor: pointer; transition: all 0.25s; text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-accent { background: var(--accent); color: var(--dark); }
        .btn-accent:hover { background: var(--accent-dark); color: #fff; }
        .btn-outline { background: transparent; color: var(--primary); border: 1.5px solid var(--primary); }
        .btn-outline:hover { background: var(--primary); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #b02a37; }
        .btn-ghost { background: transparent; color: var(--text-muted); }
        .btn-ghost:hover { background: #f1f5f9; color: var(--dark); }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 6px; margin: 30px 0; }
        .page-link {
            padding: 8px 14px; border: 1.5px solid var(--border); border-radius: 8px;
            background: var(--white); color: var(--dark); font-weight: 600; text-decoration: none;
            font-size: 14px; transition: all 0.2s;
        }
        .page-link:hover, .page-link.active { background: var(--primary); border-color: var(--primary); color: #fff; }

        /* Modals & Lightbox */
        .modal-ov {
            display: none; position: fixed; inset: 0; background: rgba(27,40,56,0.75);
            z-index: 9999; align-items: center; justify-content: center; padding: 20px;
            backdrop-filter: blur(4px);
        }
        .modal-ov.show { display: flex; }
        .modal-card {
            background: #fff; border-radius: 18px; width: 100%; max-width: 540px;
            overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.3); animation: pop 0.25s ease;
        }
        @keyframes pop { from{opacity:0; transform:scale(0.95) translateY(10px);} to{opacity:1; transform:none;} }
        .modal-head {
            background: linear-gradient(135deg, var(--dark), var(--dark-lighter));
            color: #fff; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between;
        }
        .modal-head h3 { font-size: 18px; color: #fff; margin: 0; }
        .modal-close {
            background: rgba(255,255,255,0.15); border: 0; color: #fff;
            width: 32px; height: 32px; border-radius: 50%; cursor: pointer;
        }
        .modal-body { padding: 24px; max-height: 80vh; overflow-y: auto; }

        .lightbox-ov {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9);
            z-index: 99999; align-items: center; justify-content: center; padding: 30px;
            cursor: zoom-out;
        }
        .lightbox-ov.show { display: flex; }
        .lightbox-ov img {
            max-width: 90vw; max-height: 85vh; border-radius: 12px; object-fit: contain;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        }

        /* Toast */
        .toast-notify {
            position: fixed; bottom: 24px; right: 24px; z-index: 10000;
            background: var(--dark); color: #fff; padding: 12px 22px;
            border-radius: 10px; font-size: 14px; font-weight: 500;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25); transform: translateY(100px);
            opacity: 0; transition: all 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .toast-notify.show { transform: translateY(0); opacity: 1; }

        @media(max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .upload-meta-grid { grid-template-columns: 1fr 1fr; }
        }
        @media(max-width: 900px) {
            .admin-sidebar { width: 200px; }
            .admin-main { margin-left: 200px; }
        }
        @media(max-width: 768px) {
            .admin-sidebar { display: none; }
            .admin-main { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .upload-meta-grid { grid-template-columns: 1fr; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .toolbar-left, .toolbar-right { width: 100%; }
            .search-box { min-width: 100%; }
        }
    </style>
</head>
<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <?php include __DIR__ . '/sidebar-fragment.php'; ?>

    <!-- MAIN -->
    <div class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <h1><i class="fas fa-images"></i> Travel Gallery</h1>
            <div class="admin-topbar-right">
                <span class="admin-name"><i class="fas fa-user-circle"></i> <?= e($_SESSION['admin_user']) ?></span>
                <a href="../index.php#gallery" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-external-link-alt"></i> View Live Gallery</a>
                <a href="logout.php" class="btn btn-ghost btn-sm" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="admin-content">

            <!-- Flash Message -->
            <?= flashMsg() ?>

            <!-- STATS CARDS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-camera"></i></div>
                    <div class="stat-value"><?= (int)$totalPhotos ?></div>
                    <div class="stat-label">Total Gallery Photos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-eye"></i></div>
                    <div class="stat-value"><?= (int)$activePhotos ?></div>
                    <div class="stat-label">Active (Visible on Site)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-eye-slash"></i></div>
                    <div class="stat-value"><?= (int)$inactivePhotos ?></div>
                    <div class="stat-label">Inactive (Hidden)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-map-marked-alt"></i></div>
                    <div class="stat-value"><?= (int)$destCount ?></div>
                    <div class="stat-label">Destinations Represented</div>
                </div>
            </div>

            <!-- UPLOAD PANEL (MULTIPLE PHOTOS) -->
            <div class="upload-card">
                <div class="upload-header">
                    <h2><i class="fas fa-cloud-upload-alt"></i> Upload New Travel Photos</h2>
                    <span class="badge"><i class="fas fa-bolt"></i> Multi-Upload Ready</span>
                </div>
                <div class="upload-body">
                    <form action="gallery-action.php" method="POST" enctype="multipart/form-data" id="multiUploadForm">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="upload_multiple">

                        <!-- Drag and drop zone -->
                        <div class="dropzone-wrap" id="dropzone" tabindex="0">
                            <input type="file" name="gallery_images[]" id="fileInput" multiple
                                   accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
                            <div class="dropzone-icon"><i class="fas fa-images"></i></div>
                            <div class="dropzone-title">Click to browse or drag & drop multiple travel pictures here</div>
                            <div class="dropzone-sub">Upload as many photos as you want · JPG, PNG, WebP or GIF · max 5 MB each</div>
                        </div>

                        <!-- Client-side thumbnail previews -->
                        <div class="preview-grid" id="previewGrid" style="display:none;"></div>

                        <!-- Progress Bar -->
                        <div class="progress-bar-wrap" id="progressBarWrap">
                            <div class="progress-bar-fill" id="progressBarFill"></div>
                        </div>

                        <!-- Batch Settings -->
                        <div class="upload-meta-grid">
                            <div class="form-group">
                                <label for="batchDest"><i class="fas fa-map-marker-alt"></i> Destination / Category</label>
                                <input type="text" id="batchDest" name="destination" class="form-control"
                                       placeholder="e.g. Darjeeling, Sikkim, Kerala, Bali..." list="destSuggestions">
                                <datalist id="destSuggestions">
                                    <?php foreach ($existingDestinations as $ed): ?>
                                        <option value="<?= e($ed) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label for="batchBaseTitle"><i class="fas fa-tag"></i> Base Title / Caption <small>(Optional)</small></label>
                                <input type="text" id="batchBaseTitle" name="base_title" class="form-control"
                                       placeholder="Leave blank for automatic smart naming">
                            </div>
                            <div class="form-group">
                                <label for="batchStatus"><i class="fas fa-toggle-on"></i> Initial Visibility</label>
                                <select id="batchStatus" name="status" class="form-control">
                                    <option value="1">Active (Immediately Visible)</option>
                                    <option value="0">Inactive (Draft / Hidden)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:14px;">
                            <label for="batchDesc"><i class="fas fa-align-left"></i> Story / Description <small>(Optional notes applied to uploaded batch)</small></label>
                            <textarea id="batchDesc" name="description" class="form-control" rows="2"
                                      placeholder="e.g. Captured during our morning expedition..."></textarea>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; flex-wrap:wrap; gap:12px;">
                            <button type="button" class="btn btn-ghost btn-sm" id="toggleUrlAddBtn">
                                <i class="fas fa-link"></i> Add Single Photo via URL instead
                            </button>
                            <div style="display:flex; gap:10px;">
                                <button type="button" class="btn btn-ghost" id="clearUploadBtn" style="display:none;">Clear Selection</button>
                                <button type="submit" class="btn btn-primary" id="uploadSubmitBtn">
                                    <i class="fas fa-cloud-upload-alt"></i> Upload Photos to Gallery
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- URL / Single Add Accordion -->
                    <div id="urlAddSection" style="display:none; margin-top:24px; padding-top:20px; border-top:1.5px dashed #cbd5e1;">
                        <h4 style="font-size:15px; margin-bottom:14px; color:var(--dark);"><i class="fas fa-plus-circle"></i> Add Single Photo via Image URL</h4>
                        <form action="gallery-action.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="add_single">
                            <div class="upload-meta-grid">
                                <div class="form-group">
                                    <label>Photo Title *</label>
                                    <input type="text" name="title" class="form-control" required placeholder="e.g. Sunset at Tiger Hill">
                                </div>
                                <div class="form-group">
                                    <label>Destination</label>
                                    <input type="text" name="destination" class="form-control" placeholder="e.g. Darjeeling" list="destSuggestions">
                                </div>
                                <div class="form-group">
                                    <label>Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" value="0">
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:12px;">
                                <label>Image URL (e.g. Unsplash, Cloudinary, etc.)</label>
                                <input type="url" name="image_url" class="form-control" placeholder="https://images.unsplash.com/...">
                            </div>
                            <div class="form-group" style="margin-top:12px;">
                                <label>Short Description</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Brief caption..."></textarea>
                            </div>
                            <div style="display:flex; justify-content:flex-end; margin-top:14px;">
                                <button type="submit" class="btn btn-accent"><i class="fas fa-plus"></i> Save Photo</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            <!-- TOOLBAR & FILTERS -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="view" value="<?= e($viewMode) ?>">

                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search photos or places...">
                        </div>

                        <select name="dest" class="filter-select" onchange="this.form.submit()">
                            <option value="all">All Destinations (<?= (int)$totalPhotos ?>)</option>
                            <?php foreach ($existingDestinations as $ed): ?>
                                <option value="<?= e($ed) ?>" <?= $dest === $ed ? 'selected' : '' ?>><?= e($ed) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="filter" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        </select>

                        <select name="sort" class="filter-select" onchange="this.form.submit()">
                            <option value="sort_order" <?= $sort === 'sort_order' ? 'selected' : '' ?>>Sort Order</option>
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title (A-Z)</option>
                            <option value="dest_asc" <?= $sort === 'dest_asc' ? 'selected' : '' ?>>Destination (A-Z)</option>
                        </select>

                        <?php if ($search !== '' || $dest !== 'all' || $filter !== 'all' || $sort !== 'sort_order'): ?>
                            <a href="gallery.php?view=<?= e($viewMode) ?>" class="btn btn-ghost btn-sm" title="Clear Filters">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="toolbar-right">
                    <div class="view-toggle">
                        <a href="gallery.php?<?= e($queryParams) ?>&view=grid" class="view-btn <?= $viewMode === 'grid' ? 'active' : '' ?>" title="Grid View">
                            <i class="fas fa-th-large"></i>
                        </a>
                        <a href="gallery.php?<?= e($queryParams) ?>&view=table" class="view-btn <?= $viewMode === 'table' ? 'active' : '' ?>" title="Table View">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- BULK ACTIONS BAR -->
            <div class="bulk-bar" id="bulkBar">
                <div class="bulk-count"><span id="selectedCount">0</span> photos selected</div>
                <div class="bulk-actions">
                    <button type="button" class="btn btn-sm btn-ghost" id="selectAllBtn"><i class="fas fa-check-double"></i> Select All</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="triggerBulkAction('activate')"><i class="fas fa-eye"></i> Set Active</button>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="triggerBulkAction('deactivate')"><i class="fas fa-eye-slash"></i> Set Inactive</button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="triggerBulkAction('delete')"><i class="fas fa-trash-alt"></i> Delete Selected</button>
                </div>
            </div>

            <!-- GALLERY LISTING -->
            <?php if (empty($photos)): ?>
                <div class="empty-box">
                    <i class="fas fa-images"></i>
                    <h3>No gallery photos found</h3>
                    <p>
                        <?= ($search !== '' || $dest !== 'all' || $filter !== 'all') ? 'Try adjusting your search filters.' : 'Upload pictures using the drag-and-drop box above to build your travel gallery.' ?>
                    </p>
                    <?php if ($search !== '' || $dest !== 'all' || $filter !== 'all'): ?>
                        <a href="gallery.php" class="btn btn-primary"><i class="fas fa-sync"></i> View All Photos</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>

                <?php if ($viewMode === 'grid'): ?>
                    <!-- GRID VIEW -->
                    <div class="gallery-grid">
                        <?php foreach ($photos as $photo):
                            $pUrl = galleryImageUrl($photo['image'], '../');
                            $isActive = (int)$photo['status'] === 1;
                        ?>
                            <div class="photo-card" id="card-<?= (int)$photo['id'] ?>">
                                <div class="photo-thumb-wrap">
                                    <div class="photo-select-box">
                                        <input type="checkbox" class="photo-check" value="<?= (int)$photo['id'] ?>" onchange="updateBulkBar()">
                                    </div>
                                    <?php if (!empty($photo['destination'])): ?>
                                        <span class="photo-dest-badge"><i class="fas fa-map-marker-alt"></i> <?= e($photo['destination']) ?></span>
                                    <?php endif; ?>
                                    <img src="<?= e($pUrl) ?>" alt="<?= e($photo['title']) ?>" loading="lazy">
                                    <button type="button" class="photo-zoom-btn" onclick="openLightbox('<?= e($pUrl) ?>')" title="Zoom full screen">
                                        <i class="fas fa-search-plus"></i>
                                    </button>
                                </div>
                                <div class="photo-card-body">
                                    <h4 class="photo-title"><?= e($photo['title']) ?></h4>
                                    <?php if (!empty($photo['description'])): ?>
                                        <p class="photo-desc"><?= e($photo['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="photo-meta">
                                        <span><i class="fas fa-sort-numeric-down"></i> Order: <?= (int)$photo['sort_order'] ?></span>
                                        <span><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($photo['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="photo-card-foot">
                                    <label class="toggle-switch" title="Toggle visibility on website">
                                        <input type="checkbox" <?= $isActive ? 'checked' : '' ?> onchange="toggleStatus(<?= (int)$photo['id'] ?>, this)">
                                        <span class="toggle-slider"></span>
                                        <span class="toggle-label"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                                    </label>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-ghost btn-sm" onclick='openEditModal(<?= json_encode($photo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit photo details">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="openDeleteModal(<?= (int)$photo['id'] ?>, '<?= e(addslashes($photo['title'])) ?>')" title="Delete photo">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <!-- TABLE VIEW -->
                    <div class="table-card">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="tableHeaderCheck" onchange="toggleAllChecks(this)"></th>
                                    <th style="width:80px;">Photo</th>
                                    <th>Title &amp; Description</th>
                                    <th>Destination</th>
                                    <th style="width:90px;">Sort Order</th>
                                    <th style="width:130px;">Status</th>
                                    <th style="width:110px;">Created</th>
                                    <th style="width:100px; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($photos as $photo):
                                    $pUrl = galleryImageUrl($photo['image'], '../');
                                    $isActive = (int)$photo['status'] === 1;
                                ?>
                                    <tr id="row-<?= (int)$photo['id'] ?>">
                                        <td>
                                            <input type="checkbox" class="photo-check" value="<?= (int)$photo['id'] ?>" onchange="updateBulkBar()">
                                        </td>
                                        <td>
                                            <img src="<?= e($pUrl) ?>" alt="<?= e($photo['title']) ?>" class="table-thumb" onclick="openLightbox('<?= e($pUrl) ?>')">
                                        </td>
                                        <td>
                                            <strong><?= e($photo['title']) ?></strong>
                                            <?php if (!empty($photo['description'])): ?>
                                                <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?= e($photo['description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($photo['destination'])): ?>
                                                <span style="background:rgba(217,108,63,0.1); color:var(--primary); padding:3px 8px; border-radius:6px; font-weight:600; font-size:12px;">
                                                    <?= e($photo['destination']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int)$photo['sort_order'] ?></td>
                                        <td>
                                            <label class="toggle-switch">
                                                <input type="checkbox" <?= $isActive ? 'checked' : '' ?> onchange="toggleStatus(<?= (int)$photo['id'] ?>, this)">
                                                <span class="toggle-slider"></span>
                                                <span class="toggle-label"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                                            </label>
                                        </td>
                                        <td style="font-size:12px; color:#64748b;"><?= date('M d, Y', strtotime($photo['created_at'])) ?></td>
                                        <td style="text-align:right;">
                                            <div class="btn-group" style="justify-content:flex-end;">
                                                <button type="button" class="btn btn-ghost btn-sm" onclick='openEditModal(<?= json_encode($photo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="openDeleteModal(<?= (int)$photo['id'] ?>, '<?= e(addslashes($photo['title'])) ?>')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="gallery.php?<?= e($queryParams) ?>&page=<?= $page - 1 ?>" class="page-link">&laquo; Prev</a>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === 1 || $p === $totalPages || abs($p - $page) <= 2): ?>
                                <a href="gallery.php?<?= e($queryParams) ?>&page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                            <?php elseif ($p === 2 || $p === $totalPages - 1): ?>
                                <span class="page-link" style="border:none;background:transparent;">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="gallery.php?<?= e($queryParams) ?>&page=<?= $page + 1 ?>" class="page-link">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- EDIT PHOTO MODAL -->
<div class="modal-ov" id="editModal">
    <div class="modal-card">
        <div class="modal-head">
            <h3><i class="fas fa-edit"></i> Edit Photo Details</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form action="gallery-action.php" method="POST" enctype="multipart/form-data" id="editPhotoForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId" value="0">

                <div style="text-align:center; margin-bottom:18px;">
                    <img id="editImagePreview" src="" alt="Preview" style="max-height:160px; border-radius:10px; object-fit:cover; box-shadow:0 2px 10px rgba(0,0,0,0.1); margin:0 auto;">
                </div>

                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" id="editTitle" name="title" class="form-control" required>
                </div>

                <div class="upload-meta-grid" style="grid-template-columns:1fr 1fr; margin-top:12px;">
                    <div class="form-group">
                        <label>Destination</label>
                        <input type="text" id="editDestination" name="destination" class="form-control" list="destSuggestions">
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" id="editSortOrder" name="sort_order" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-group" style="margin-top:12px;">
                    <label>Visibility Status</label>
                    <select id="editStatus" name="status" class="form-control">
                        <option value="1">Active (Visible)</option>
                        <option value="0">Inactive (Hidden)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top:12px;">
                    <label>Description / Story</label>
                    <textarea id="editDescription" name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group" style="margin-top:14px; padding-top:14px; border-top:1px solid #f1f5f9;">
                    <label><i class="fas fa-exchange-alt"></i> Replace Image File <small>(Optional)</small></label>
                    <input type="file" name="image_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label>Or Replace with Image URL <small>(Optional)</small></label>
                    <input type="url" id="editImageUrl" name="image_url" class="form-control" placeholder="https://...">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-ov" id="deleteModal">
    <div class="modal-card" style="max-width:400px; text-align:center;">
        <div class="modal-body" style="padding:32px 24px;">
            <div style="width:60px; height:60px; border-radius:50%; background:rgba(220,53,69,0.12); color:var(--danger); display:inline-flex; align-items:center; justify-content:center; font-size:28px; margin-bottom:16px;">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 style="font-size:18px; margin-bottom:8px; color:var(--dark);">Delete Photo?</h3>
            <p style="font-size:14px; color:var(--text-muted); margin-bottom:24px;" id="deleteModalText">
                Are you sure you want to permanently delete this photo?
            </p>
            <form action="gallery-action.php" method="POST" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId" value="0">
                <div style="display:flex; justify-content:center; gap:10px;">
                    <button type="button" class="btn btn-ghost" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- BULK ACTION FORM -->
<form action="gallery-action.php" method="POST" id="bulkActionForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="bulk_action">
    <input type="hidden" name="bulk_type" id="bulkTypeInput" value="">
    <input type="hidden" name="ids" id="bulkIdsInput" value="">
</form>

<!-- LIGHTBOX -->
<div class="lightbox-ov" id="lightbox" onclick="closeLightbox()">
    <img id="lightboxImg" src="" alt="Zoomed Travel Photo">
</div>

<!-- TOAST -->
<div class="toast-notify" id="toastNotify">
    <i class="fas fa-check-circle" style="color:#10b981;"></i>
    <span id="toastMsg">Action completed successfully.</span>
</div>

<!-- SCRIPT -->
<script>
    const CSRF_TOKEN = <?= json_encode($csrf) ?>;
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const previewGrid = document.getElementById('previewGrid');
    const clearUploadBtn = document.getElementById('clearUploadBtn');
    let selectedFiles = [];

    // Dropzone handlers
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleNewFiles(Array.from(e.dataTransfer.files));
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files && fileInput.files.length > 0) {
            handleNewFiles(Array.from(fileInput.files));
        }
    });

    function handleNewFiles(files) {
        files.forEach(file => {
            if (file.type.startsWith('image/')) {
                selectedFiles.push(file);
            }
        });
        syncInputFiles();
        renderPreviews();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        syncInputFiles();
        renderPreviews();
    }

    function syncInputFiles() {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        fileInput.files = dt.files;
    }

    function renderPreviews() {
        previewGrid.innerHTML = '';
        if (selectedFiles.length === 0) {
            previewGrid.style.display = 'none';
            clearUploadBtn.style.display = 'none';
            return;
        }
        previewGrid.style.display = 'grid';
        clearUploadBtn.style.display = 'inline-flex';

        selectedFiles.forEach((file, idx) => {
            const item = document.createElement('div');
            item.className = 'preview-item';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-btn';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.onclick = (e) => {
                e.stopPropagation();
                removeFile(idx);
            };

            const sizeTag = document.createElement('div');
            sizeTag.className = 'size-tag';
            sizeTag.textContent = (file.size / (1024 * 1024)).toFixed(1) + ' MB';

            item.appendChild(img);
            item.appendChild(removeBtn);
            item.appendChild(sizeTag);
            previewGrid.appendChild(item);
        });
    }

    clearUploadBtn.addEventListener('click', () => {
        selectedFiles = [];
        syncInputFiles();
        renderPreviews();
    });

    // Toggle URL Add
    document.getElementById('toggleUrlAddBtn').addEventListener('click', function() {
        const sec = document.getElementById('urlAddSection');
        sec.style.display = sec.style.display === 'none' ? 'block' : 'none';
        if (sec.style.display === 'block') {
            sec.scrollIntoView({ behavior: 'smooth' });
        }
    });

    // Lightbox
    function openLightbox(url) {
        document.getElementById('lightboxImg').src = url;
        document.getElementById('lightbox').classList.add('show');
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('show');
    }

    // Toggle Status via AJAX
    async function toggleStatus(id, checkbox) {
        const label = checkbox.closest('.toggle-switch').querySelector('.toggle-label');
        const originalState = !checkbox.checked;
        const form = new FormData();
        form.append('csrf_token', CSRF_TOKEN);
        form.append('action', 'toggle_status');
        form.append('id', id);
        form.append('ajax', '1');

        try {
            const res = await fetch('gallery-action.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: form
            });
            const data = await res.json();
            if (data.ok) {
                label.textContent = data.status_label;
                showToast(data.message);
            } else {
                checkbox.checked = originalState;
                showToast(data.error || 'Failed to update status.');
            }
        } catch (err) {
            checkbox.checked = originalState;
            showToast('Network error updating status.');
        }
    }

    // Edit Modal
    function openEditModal(photo) {
        document.getElementById('editId').value = photo.id;
        document.getElementById('editTitle').value = photo.title || '';
        document.getElementById('editDestination').value = photo.destination || '';
        document.getElementById('editSortOrder').value = photo.sort_order || 0;
        document.getElementById('editStatus').value = photo.status;
        document.getElementById('editDescription').value = photo.description || '';
        document.getElementById('editImageUrl').value = photo.image.startsWith('http') ? photo.image : '';

        const imgUrl = photo.image.startsWith('http') ? photo.image : ('../' + photo.image);
        document.getElementById('editImagePreview').src = imgUrl;

        document.getElementById('editModal').classList.add('show');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
    }

    // Delete Modal
    function openDeleteModal(id, title) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteModalText').textContent = `Are you sure you want to permanently delete "${title}"?`;
        document.getElementById('deleteModal').classList.add('show');
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
    }

    // Bulk selection handlers
    function updateBulkBar() {
        const checks = document.querySelectorAll('.photo-check:checked');
        const count = checks.length;
        document.getElementById('selectedCount').textContent = count;
        const bulkBar = document.getElementById('bulkBar');
        if (count > 0) {
            bulkBar.classList.add('active');
        } else {
            bulkBar.classList.remove('active');
        }
    }

    function toggleAllChecks(headerCheck) {
        const checks = document.querySelectorAll('.photo-check');
        checks.forEach(c => c.checked = headerCheck.checked);
        updateBulkBar();
    }

    document.getElementById('selectAllBtn').addEventListener('click', function() {
        const checks = document.querySelectorAll('.photo-check');
        const allChecked = Array.from(checks).every(c => c.checked);
        checks.forEach(c => c.checked = !allChecked);
        updateBulkBar();
    });

    function triggerBulkAction(type) {
        const checked = document.querySelectorAll('.photo-check:checked');
        if (checked.length === 0) return;

        if (type === 'delete' && !confirm(`Are you sure you want to permanently delete ${checked.length} selected photos?`)) {
            return;
        }

        const ids = Array.from(checked).map(c => c.value);
        document.getElementById('bulkTypeInput').value = type;
        document.getElementById('bulkIdsInput').value = ids.join(',');
        document.getElementById('bulkActionForm').submit();
    }

    // Toast
    function showToast(msg) {
        const t = document.getElementById('toastNotify');
        document.getElementById('toastMsg').textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3500);
    }
</script>

</body>
</html>
