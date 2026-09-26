<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

 $message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token mismatch.';
    } else {
        // Generate SQL dump
        $backup  = "-- SK Travel Planner Database Backup\n";
        $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Server: " . DB_HOST . "\n\n";
        $backup .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $backup .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $backup .= "SET NAMES utf8mb4;\n\n";

        try {
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // CREATE TABLE statement
                $stmt2 = $pdo->query("SHOW CREATE TABLE `$table`");
                $createRow = $stmt2->fetch(PDO::FETCH_NUM);
                $backup .= "-- ----------------------------------------\n";
                $backup .= "-- Table: $table\n";
                $backup .= "-- ----------------------------------------\n\n";
                $backup .= "DROP TABLE IF EXISTS `$table`;\n";
                $backup .= $createRow[1] . ";\n\n";

                // Data rows
                $stmt3 = $pdo->query("SELECT * FROM `$table`");
                $rows = $stmt3->fetchAll(PDO::FETCH_NUM);

                if (count($rows) > 0) {
                    $cols = $stmt3->columnCount();
                    $backup .= "INSERT INTO `$table` VALUES\n";
                    $rowParts = [];

                    foreach ($rows as $row) {
                        $vals = [];
                        for ($i = 0; $i < $cols; $i++) {
                            if ($row[$i] === null) {
                                $vals[] = 'NULL';
                            } else {
                                $vals[] = $pdo->quote($row[$i]);
                            }
                        }
                        $rowParts[] = "  (" . implode(", ", $vals) . ")";
                    }

                    $backup .= implode(",\n", $rowParts) . ";\n\n";
                } else {
                    $backup .= "-- (No data in this table)\n\n";
                }
            }

            $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";

            // Save to file
            $filename = 'sk_travel_backup_' . date('Y-m-d_His') . '.sql';
            $filepath = sys_get_temp_dir() . '/' . $filename;
            file_put_contents($filepath, $backup);

            // Download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);

            // Clean up temp file
            unlink($filepath);
            exit;

        } catch (PDOException $e) {
            $message = 'Backup failed: ' . $e->getMessage();
        }
    }
}

// Fetch existing backups info
 $tableCount = $pdo->query("SHOW TABLES")->rowCount();
 $totalItins = $pdo->query("SELECT COUNT(*) FROM itineraries")->fetchColumn();
 $totalAdmins = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup — <?= e(APP_NAME) ?> Admin</title>
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <link
            href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
        >

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/icons/favicon-16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/favicon-32.png">
        <link rel="icon" href="../assets/icons/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="../assets/icons/apple-touch-icon.png">
</head>
<body>

<div class="admin-layout">
    <?php include 'sidebar-fragment.php'; ?>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1><i class="fas fa-database"></i> Database Backup</h1>
        </div>

        <div class="admin-content">
            <?php if ($message): ?>
                <div class="alert alert-danger"><?= e($message) ?></div>
            <?php endif; ?>

            <div class="form-card">
                <h2 style="margin-bottom:20px;"><i class="fas fa-shield-alt" style="color:var(--primary);"></i> Backup & Safety</h2>

                <p style="color:var(--text-muted);margin-bottom:24px;">
                    Download a complete SQL dump of your database. This backup includes all table structures and data.
                    You can restore it using phpMyAdmin or the MySQL command line.
                </p>

                <!-- Database Stats -->
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:30px;">
                    <div style="background:var(--light);border-radius:var(--radius-sm);padding:20px;text-align:center;">
                        <div style="font-size:28px;font-weight:700;color:var(--dark);"><?= (int)$tableCount ?></div>
                        <div style="font-size:13px;color:var(--text-muted);">Tables</div>
                    </div>
                    <div style="background:var(--light);border-radius:var(--radius-sm);padding:20px;text-align:center;">
                        <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= (int)$totalItins ?></div>
                        <div style="font-size:13px;color:var(--text-muted);">Itineraries</div>
                    </div>
                    <div style="background:var(--light);border-radius:var(--radius-sm);padding:20px;text-align:center;">
                        <div style="font-size:28px;font-weight:700;color:var(--accent);"><?= (int)$totalAdmins ?></div>
                        <div style="font-size:13px;color:var(--text-muted);">Admin Users</div>
                    </div>
                </div>

                <form method="POST">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-accent btn-lg">
                        <i class="fas fa-download"></i> Generate & Download Backup
                    </button>
                </form>

                <div style="margin-top:30px;padding:20px;background:var(--light);border-radius:var(--radius-sm);font-size:14px;color:var(--text-muted);">
                    <h4 style="color:var(--dark);margin-bottom:8px;"><i class="fas fa-info-circle"></i> Restore Instructions</h4>
                    <p style="margin-bottom:6px;">1. Open phpMyAdmin or MySQL CLI</p>
                    <p style="margin-bottom:6px;">2. Create/select the database: <code style="background:white;padding:2px 6px;border-radius:4px;">sk_travel_planner</code></p>
                    <p style="margin-bottom:6px;">3. Import the downloaded .sql file</p>
                    <p>4. All tables and data will be restored</p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>