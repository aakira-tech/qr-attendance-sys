<?php
require_once '../config/db.php';
requireLogin();

$isAdmin = ($_SESSION['role'] === 'admin');
$message = '';
$messageType = 'message'; 

$initials = '';
if (!empty($_SESSION['full_name'])) {
    $parts = explode(' ', $_SESSION['full_name']);
    foreach ($parts as $p) {
        if ($p !== '') $initials .= strtoupper($p[0]);
    }
    $initials = substr($initials, 0, 2);
}

if ($isAdmin) {
    $sections = $pdo->query("SELECT * FROM sections ORDER BY section_name")->fetchAll();
    $autoSectionId = null;
} else {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE adviser_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $mySection = $stmt->fetch();

    if (!$mySection) {
        header("Location: sections.php");
        exit;
    }
    $autoSectionId = $mySection['id'];
    $sections = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id  = trim($_POST['student_id']);
    $full_name   = trim($_POST['full_name']);
    $grade_level = trim($_POST['grade_level']);
    $section_id  = $isAdmin ? ($_POST['section_id'] ?: null) : $autoSectionId;

    // Validation
    if (empty($student_id) || empty($full_name)) {
        $message = "Student ID and Full Name are required.";
        $messageType = 'error-message';
    } else {
        // Duplicate check
        $check = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
        $check->execute([$student_id]);

        if ($check->fetch()) {
            $message = "A student with that ID already exists.";
            $messageType = 'error-message';
        } else {
            $qr_code = 'SRES-' . strtoupper(bin2hex(random_bytes(8)));

            try {
                $stmt = $pdo->prepare("INSERT INTO students (student_id, full_name, grade_level, section_id, qr_code)
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $full_name, $grade_level, $section_id, $qr_code]);
                $message = "Student added! QR token: <strong>" . htmlspecialchars($qr_code) . "</strong>";
                $messageType = 'message';
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error-message';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student | QR Attendance</title>

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
                <h1>Add Student</h1>
            </div>
            <div class="topbar-profile">
                <div class="teacher-avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
                    <span><?= $isAdmin ? 'Administrator' : 'Teacher' ?></span>
                </div>
            </div>
        </header>

        <div class="page-header">
            <div>
                <p class="section-label">Student Registration</p>
                <h2>Add New Student</h2>
                <p>Register a new student to the attendance system. A QR code will be auto-generated.</p>
            </div>
            <a href="students.php" class="export-button" style="background:#94a3b8;">← Back to Students</a>
        </div>

        <?php if ($message): ?>
            <p class="<?= $messageType ?>"><?= $message ?></p>
        <?php endif; ?>

        <div class="form-card">
            <h3>Student Information</h3>

            <form method="POST">
                <label>Student ID / LRN:</label>
                <input type="text" name="student_id" placeholder="e.g. 123456789012" required autofocus>

                <label>Full Name:</label>
                <input type="text" name="full_name" placeholder="e.g. Juan Dela Cruz" required>

                <label>Grade Level:</label>
                <input type="text" name="grade_level" placeholder="e.g. Grade 4">

                <?php if ($isAdmin): ?>
                    <label>Section:</label>
                    <select name="section_id">
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['section_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <label>Section:</label>
                    <input type="text" value="<?= htmlspecialchars($mySection['section_name']) ?>" disabled
                           style="background:#f1f5f9;cursor:not-allowed;">
                    <p style="font-size:12px;color:#64748b;margin-top:-10px;margin-bottom:15px;">
                        Auto-assigned to your section.
                    </p>
                <?php endif; ?>

                <button type="submit" class="form-button">Add Student</button>
                <a href="students.php" class="form-cancel">Cancel</a>
            </form>
        </div>
    </main>
</div>
</body>
</html>
