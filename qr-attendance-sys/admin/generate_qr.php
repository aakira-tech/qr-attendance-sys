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

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, sec.section_name FROM students s LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) die("Student not found.");

// Security: teacher can only view their own students
if (!$isAdmin) {
    $check = $pdo->prepare("
        SELECT 1 FROM students 
        WHERE id = ? AND section_id = (SELECT id FROM sections WHERE adviser_id = ?)
    ");
    $check->execute([$id, $_SESSION['user_id']]);
    if (!$check->fetch()) {
        die("Access denied.");
    }
}

$qrData = urlencode($student['qr_code']);
$qrURL  = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=$qrData";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code — <?= htmlspecialchars($student['full_name']) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">

    <style>
        @media print {
            .sidebar, .topbar, .no-print {
                display: none !important;
            }
            .main-website { display: block !important; }
            .content { padding: 0 !important; }
            body { background: white !important; }
        }
    </style>
</head>

<body>
<div class="main-website">
    <?php include '../includes/header.php'; ?>

    <main class="content">
        <header class="topbar no-print">
            <div>
                <p class="topbar-label">Attendance Monitoring System</p>
                <h1>QR Code</h1>
            </div>
            <div class="topbar-profile">
                <div class="teacher-avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
                    <span><?= $isAdmin ? 'Administrator' : 'Teacher' ?></span>
                </div>
            </div>
        </header>

        <div class="page-header no-print">
            <div>
                <p class="section-label">Student QR Code</p>
                <h2><?= htmlspecialchars($student['full_name']) ?></h2>
                <p>Print this QR code and give it to the student for daily attendance scanning.</p>
            </div>
            <div>
                <button onclick="window.print()" class="export-button">🖨 Print</button>
                <a href="students.php" class="form-cancel" style="margin-left:8px;text-decoration:none;padding:10px 17px;display:inline-block;">← Back</a>
            </div>
        </div>

        <div class="form-card" style="text-align:center; max-width:520px; margin:0 auto;">
            <h3>Student ID Card</h3>

            <div style="background:#f8faf9;padding:30px;border-radius:12px;margin-bottom:20px;">
                <img src="<?= $qrURL ?>" alt="QR Code" style="display:block;margin:0 auto;width:100%;max-width:300px;">

                <p style="font-family:monospace;font-size:12px;color:#64748b;margin-top:15px;word-break:break-all;">
                    <?= htmlspecialchars($student['qr_code']) ?>
                </p>
            </div>

            <h2 style="font-size:20px;margin-bottom:8px;">
                <?= htmlspecialchars($student['full_name']) ?>
            </h2>
            <p style="color:#64748b;font-size:13px;">
                Student ID: <strong><?= htmlspecialchars($student['student_id']) ?></strong>
            </p>
            <?php if ($student['grade_level']): ?>
                <p style="color:#64748b;font-size:13px;">
                    Grade: <strong><?= htmlspecialchars($student['grade_level']) ?></strong>
                </p>
            <?php endif; ?>
            <?php if ($student['section_name']): ?>
                <p style="color:#64748b;font-size:13px;">
                    Section: <strong><?= htmlspecialchars($student['section_name']) ?></strong>
                </p>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>