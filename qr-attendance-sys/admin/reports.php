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

$date = $_GET['date'] ?? date('Y-m-d');

// ==================== CSV EXPORT ====================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($isAdmin) {
        $stmt = $pdo->prepare("
            SELECT a.*, s.full_name, s.student_id, s.grade_level, sec.section_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE a.attendance_date = ?
            ORDER BY a.time_in
        ");
        $stmt->execute([$date]);
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, s.full_name, s.student_id, s.grade_level, sec.section_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE a.attendance_date = ?
              AND s.section_id = (SELECT id FROM sections WHERE adviser_id = ?)
            ORDER BY a.time_in
        ");
        $stmt->execute([$date, $_SESSION['user_id']]);
    }
    $exportRecords = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . $date . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Name', 'Grade', 'Section', 'Time In', 'Time Out', 'Status']);

    foreach ($exportRecords as $r) {
        fputcsv($out, [
            $r['student_id'],
            $r['full_name'],
            $r['grade_level'],
            $r['section_name'] ?? '—',
            $r['time_in'] ? date('h:i A', strtotime($r['time_in'])) : '—',
            $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '—',
            ucfirst($r['status'])
        ]);
    }
    fclose($out);
    exit;
}

// ==================== LOAD REPORT ====================
if ($isAdmin) {
    $stmt = $pdo->prepare("
        SELECT a.*, s.full_name, s.student_id, s.grade_level, sec.section_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE a.attendance_date = ?
        ORDER BY a.time_in
    ");
    $stmt->execute([$date]);
} else {
    $stmt = $pdo->prepare("
        SELECT a.*, s.full_name, s.student_id, s.grade_level, sec.section_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE a.attendance_date = ?
          AND s.section_id = (SELECT id FROM sections WHERE adviser_id = ?)
        ORDER BY a.time_in
    ");
    $stmt->execute([$date, $_SESSION['user_id']]);
}
$records = $stmt->fetchAll();

// Count stats
$presentCount = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$lateCount    = count(array_filter($records, fn($r) => $r['status'] === 'late'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | QR Attendance</title>

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
                <h1>Reports</h1>
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
                <p class="section-label">Attendance Records</p>
                <h2><?= date('F j, Y', strtotime($date)) ?></h2>
                <p>
                    <?= $presentCount ?> present · <?= $lateCount ?> late · <?= count($records) ?> total
                </p>
            </div>
            <a href="?date=<?= htmlspecialchars($date) ?>&export=csv" class="export-button">
                ⬇ Export CSV
            </a>
        </div>

        <div class="record-card">
            <div class="record-header">
                <h3>Attendance Record</h3>
                <form method="GET" style="display:flex;gap:10px;align-items:center;">
                    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
                    <button type="submit" class="export-button" style="padding:9px 15px;">Filter</button>
                </form>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Grade</th>
                            <th>Section</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="7" class="empty-record">
                                    No attendance records for this date.
                                </td>
                            </tr>
                        <?php else: foreach ($records as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['student_id']) ?></td>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= htmlspecialchars($r['grade_level']) ?></td>
                                <td><?= htmlspecialchars($r['section_name'] ?? '—') ?></td>
                                <td><?= $r['time_in'] ? date('h:i A', strtotime($r['time_in'])) : '—' ?></td>
                                <td><?= $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '—' ?></td>
                                <td>
                                    <?php if ($r['status'] === 'present'): ?>
                                        <span style="color:#16a34a;font-weight:600;">Present</span>
                                    <?php elseif ($r['status'] === 'late'): ?>
                                        <span style="color:#f59e0b;font-weight:600;">Late</span>
                                    <?php else: ?>
                                        <span style="color:#64748b;font-weight:600;">Absent</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>