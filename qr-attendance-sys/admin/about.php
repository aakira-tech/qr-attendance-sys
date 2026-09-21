<?php
require_once '../config/db.php';
requireLogin();

$isAdmin = ($_SESSION['role'] === 'admin');

// Initials
$initials = '';
if (!empty($_SESSION['full_name'])) {
    $parts = explode(' ', $_SESSION['full_name']);
    foreach ($parts as $p) {
        if ($p !== '') $initials .= strtoupper($p[0]);
    }
    $initials = substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | QR Attendance</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
<div class="main-website">
    <?php include '../includes/header.php'; ?>

    <main class="content">
        <header class="topbar">
            <div>
                <p class="topbar-label">Attendance Monitoring System</p>
                <h1>About Us</h1>
            </div>
            <div class="topbar-profile">
                <div class="teacher-avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
                    <span><?= $isAdmin ? 'Administrator' : 'Teacher' ?></span>
                </div>
            </div>
        </header>

        <div class="about-card">
            <div class="about-logo">
                <img src="/assets/img/logo.jpg" alt="Santo Rosario Elementary School Logo">
            </div>

            <h2>Santo Rosario Elementary School</h2>
            <p class="about-subtitle">Attendance Monitoring System</p>

            <div class="about-divider"></div>

            <p>
                This system is designed to help teachers efficiently monitor and manage 
                student attendance using a QR-based attendance system. Students carry a 
                printed QR code and simply scan it at the classroom door — no phone, app, 
                or manual roll call required.
            </p>

            <div class="about-info">
                <div>
                    <strong>School</strong>
                    <span>Santo Rosario Elementary School</span>
                </div>

                <div>
                    <strong>System</strong>
                    <span>Student Attendance Monitoring</span>
                </div>

                <div>
                    <strong>Access</strong>
                    <span>Authorized Teachers</span>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>