<?php
// Shared admin sidebar fragment
 $current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <span class="brand-icon"><img src="../logo.jpg" alt="Logo"></span>
        SK Travel
    </div>
    <ul class="admin-nav">
        <li>
            <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="itinerary-form.php" class="<?= $current_page === 'itinerary-form.php' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle"></i> New Itinerary
            </a>
        </li>
        <li>
            <a href="backup.php" class="<?= $current_page === 'backup.php' ? 'active' : '' ?>">
                <i class="fas fa-database"></i> DB Backup
            </a>
        </li>
        <li>
            <a href="../index.php">
                <i class="fas fa-globe"></i> View Website
            </a>
        </li>
          <li>
            <a href="change-password.php" class="<?= $current_page === 'backup.php' ? 'active' : '' ?>">
                <i class="fas fa-key"></i> Password update
            </a>
        </li>
        <li style="margin-top:30px;border-top:1px solid rgba(255,255,255,0.08);padding-top:16px;">
            <a href="logout.php" style="color:rgba(255,255,255,0.45);">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</aside>