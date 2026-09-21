<?php
// Detect current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

// Build initials for avatar
$initials = '';
if (!empty($_SESSION['full_name'])) {
    $parts = explode(' ', $_SESSION['full_name']);
    foreach ($parts as $p) {
        if ($p !== '') $initials .= strtoupper($p[0]);
    }
    $initials = substr($initials, 0, 2);
}
?>
<aside class="sidebar">

    <div class="sidebar-logo">
        <img src="/assets/img/logo.jpg" alt="School Logo">
        <div>
            <h2>Santo Rosario</h2>
            <span>Elementary School</span>
        </div>
    </div>

    <nav class="navigation">
        <a href="/admin/dashboard.php"
           class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">⌂</span>
            <span>Home</span>
        </a>

        <?php if ($isAdmin): ?>
            <a href="/admin/sections.php"
               class="nav-item <?= $currentPage === 'sections.php' ? 'active' : '' ?>">
                <span class="nav-icon">▤</span>
                <span>Sections</span>
            </a>
            <a href="/admin/users.php"
               class="nav-item <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                <span class="nav-icon">☺</span>
                <span>Users</span>
            </a>
        <?php endif; ?>

        <a href="/admin/students.php"
           class="nav-item <?= $currentPage === 'students.php' ? 'active' : '' ?>">
            <span class="nav-icon">▣</span>
            <span>Students</span>
        </a>

        <a href="/scan/scan.php"
           class="nav-item <?= $currentPage === 'scan.php' ? 'active' : '' ?>">
            <span class="nav-icon">◎</span>
            <span>Scan</span>
        </a>

        <a href="/admin/reports.php"
           class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <span class="nav-icon">▦</span>
            <span>Reports</span>
        </a>

        <a href="/admin/about.php"
           class="nav-item <?= $currentPage === 'about.php' ? 'active' : '' ?>">
            <span class="nav-icon">ⓘ</span>
            <span>About Us</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="teacher-mini-profile">
            <div class="teacher-avatar"><?= htmlspecialchars($initials) ?></div>
            <div>
                <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></strong>
                <span><?= $isAdmin ? 'Administrator' : 'Teacher' ?></span>
            </div>
        </div>
        <a href="/auth/logout.php" class="logout-button">Logout</a>
    </div>

</aside>