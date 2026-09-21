<?php
require_once '../config/db.php';
requireLogin();

$today = date('Y-m-d');
$isAdmin = ($_SESSION['role'] === 'admin');

$initials = '';
if (!empty($_SESSION['full_name'])) {
    $parts = explode(' ', $_SESSION['full_name']);
    foreach ($parts as $p) {
        if ($p !== '') $initials .= strtoupper($p[0]);
    }
    $initials = substr($initials, 0, 2);
}

if ($isAdmin) {
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    $presentStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE attendance_date = ? AND status IN ('present','late')");
    $presentStmt->execute([$today]);
    $presentCount = $presentStmt->fetchColumn();

    $absentCount = $totalStudents - $presentCount;

    $lateStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE attendance_date = ? AND status = 'late'");
    $lateStmt->execute([$today]);
    $lateCount = $lateStmt->fetchColumn();
} else {
    // Teacher: only their section
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE section_id = (SELECT id FROM sections WHERE adviser_id = ?)");
    $totalStmt->execute([$_SESSION['user_id']]);
    $totalStudents = $totalStmt->fetchColumn();

    $presentStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.student_id) 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.attendance_date = ?
          AND a.status IN ('present','late')
          AND s.section_id = (SELECT id FROM sections WHERE adviser_id = ?)
    ");
    $presentStmt->execute([$today, $_SESSION['user_id']]);
    $presentCount = $presentStmt->fetchColumn();

    $absentCount = $totalStudents - $presentCount;

    $lateStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.student_id) 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.attendance_date = ?
          AND a.status = 'late'
          AND s.section_id = (SELECT id FROM sections WHERE adviser_id = ?)
    ");
    $lateStmt->execute([$today, $_SESSION['user_id']]);
    $lateCount = $lateStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | QR Attendance</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- cachebuster: <?= time() ?> -->
</head>

<body>
<div class="main-website">
    <?php include '../includes/header.php'; ?>

    <main class="content">
        <header class="topbar">
            <div>
                <p class="topbar-label">Attendance Monitoring System</p>
                <h1>Home</h1>
            </div>
            <div class="topbar-profile">
                <div class="teacher-avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
                    <span><?= $isAdmin ? 'Administrator' : 'Teacher' ?></span>
                </div>
            </div>
        </header>

        <section class="welcome-section">
            <p class="section-label">Welcome back!</p>
            <h2>Good day, <?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?>!</h2>
            <p>Manage and monitor student attendance through the school attendance system.</p>
        </section>

        <a href="/scan/scan.php" style="text-decoration:none;">
            <div class="scan-card">
                <div class="scan-content">
                    <div class="scan-icon">QR</div>
                    <div>
                        <h2>Scan for Attendance</h2>
                        <p>Scan the student's QR code using your device camera to record attendance.</p>
                        <span class="scan-button">Scan for Attendance</span>
                    </div>
                </div>
            </div>
        </a>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <span class="card-label">Today's Attendance</span>
                <strong><?= $presentCount ?></strong>
                <p>Students recorded today</p>
            </div>

            <div class="dashboard-card">
                <span class="card-label">Present</span>
                <strong><?= $presentCount ?></strong>
                <p>Students present</p>
            </div>

            <div class="dashboard-card">
                <span class="card-label">Absent</span>
                <strong><?= max(0, $absentCount) ?></strong>
                <p>Students absent</p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
