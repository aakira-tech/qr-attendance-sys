<?php
require_once '../config/db.php';
requireLogin();

$isAdmin = ($_SESSION['role'] === 'admin');
$message = '';

// Initials
$initials = '';
if (!empty($_SESSION['full_name'])) {
    $parts = explode(' ', $_SESSION['full_name']);
    foreach ($parts as $p) {
        if ($p !== '') $initials .= strtoupper($p[0]);
    }
    $initials = substr($initials, 0, 2);
}

// ==================== DELETE STUDENT ====================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if ($isAdmin) {
        $check = $pdo->prepare("SELECT id FROM students WHERE id = ?");
        $check->execute([$id]);

        if ($check->fetch()) {
            // Manually delete attendance first (cascade fallback)
            $pdo->prepare("DELETE FROM attendance WHERE student_id = ?")->execute([$id]);

            // Then delete the student
            $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);

            $message = "Student and their attendance records deleted.";
        } else {
            $message = "Student not found.";
        }
    } else {
        // Teacher: verify ownership
        $check = $pdo->prepare("
            SELECT id FROM students 
            WHERE id = ? AND section_id = (SELECT id FROM sections WHERE adviser_id = ?)
        ");
        $check->execute([$id, $_SESSION['user_id']]);

        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM attendance WHERE student_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
            $message = "Student and their attendance records deleted.";
        } else {
            $message = "You cannot delete that student.";
        }
    }
}

// ==================== LOAD STUDENTS ====================
if ($isAdmin) {
    $students = $pdo->query("
        SELECT s.*, sec.section_name 
        FROM students s 
        LEFT JOIN sections sec ON s.section_id = sec.id 
        ORDER BY s.full_name
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE adviser_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $section = $stmt->fetch();

    if ($section) {
        $stmt = $pdo->prepare("
            SELECT s.*, sec.section_name 
            FROM students s 
            LEFT JOIN sections sec ON s.section_id = sec.id 
            WHERE s.section_id = ?
            ORDER BY s.full_name
        ");
        $stmt->execute([$section['id']]);
        $students = $stmt->fetchAll();
    } else {
        $students = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students | QR Attendance</title>

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
                <h1>Students</h1>
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
                <p class="section-label">Student Records</p>
                <h2><?= $isAdmin ? 'All Students' : 'My Students' ?></h2>
                <p>Manage the list of students registered for QR attendance.</p>
            </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="add_student.php" class="export-button">+ Add Student</a>
            <a href="import_students.php" class="export-button" style="background:#0f4f33;">📥 Import CSV</a>
        <?php if (isset($section) && $section): ?>
            <a href="print_qr_cards.php?section=<?= $section['id'] ?>" target="_blank" class="export-button" style="background:#94a3b8;">🖨 Print QR Cards</a>
        <?php elseif ($isAdmin): ?>
            <a href="sections.php" class="export-button" style="background:#94a3b8;">🖨 Print QR Cards</a>
        <?php endif; ?>
         </div>
        </div>

        <?php if ($message): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if (!$isAdmin && empty($students) && !isset($section)): ?>
            <div class="record-card">
                <p class="empty-record">
                    You don't have a section yet. 
                    <a href="sections.php">Create your section</a> first.
                </p>
            </div>
        <?php else: ?>
            <div class="record-card">
                <div class="record-header">
                    <h3>Registered Students (<?= count($students) ?>)</h3>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Grade</th>
                                <th>Section</th>
                                <th>QR</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="6" class="empty-record">
                                        No students yet. Click "+ Add Student" to begin.
                                    </td>
                                </tr>
                            <?php else: foreach ($students as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['student_id']) ?></td>
                                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                                    <td><?= htmlspecialchars($s['grade_level']) ?></td>
                                    <td><?= htmlspecialchars($s['section_name'] ?? '—') ?></td>
                                    <td><code><?= htmlspecialchars($s['qr_code']) ?></code></td>
                                    <td>
                                        <a href="generate_qr.php?id=<?= $s['id'] ?>">View QR</a> |
                                        <a href="?delete=<?= $s['id'] ?>" 
                                           style="color:#dc2626;"
                                           onclick="return confirm('Delete this student?\n\nName: <?= htmlspecialchars(addslashes($s['full_name'])) ?>\n\nAll attendance records will also be deleted. This cannot be undone.');">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>