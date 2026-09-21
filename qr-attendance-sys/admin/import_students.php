<?php
require_once '../config/db.php';
requireLogin();

$isAdmin = ($_SESSION['role'] === 'admin');
$message = '';
$messageType = 'message';
$errors = [];
$successCount = 0;
$skipCount = 0;

// Initials
$initials = '';
if (!empty($_SESSION['full_name'])) {
    $parts = explode(' ', $_SESSION['full_name']);
    foreach ($parts as $p) {
        if ($p !== '') $initials .= strtoupper($p[0]);
    }
    $initials = substr($initials, 0, 2);
}

// Determine which section this teacher owns
if ($isAdmin) {
    $sections = $pdo->query("SELECT * FROM sections ORDER BY section_name")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE adviser_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $mySection = $stmt->fetch();
    if (!$mySection) {
        header("Location: sections.php");
        exit;
    }
}

// ==================== HANDLE UPLOAD ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $section_id = $isAdmin ? ($_POST['section_id'] ?: null) : $mySection['id'];

    if (!$section_id) {
        $errors[] = "Please select a section.";
    }

    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error (code: {$file['error']})";
    }

    if (empty($errors)) {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $errors[] = "Could not open file.";
        } else {
            $rowNum = 0;
            $headerSkipped = false;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;

                // Skip first row if it looks like a header
                if (!$headerSkipped) {
                    $headerSkipped = true;
                    if (stripos($row[0] ?? '', 'student') !== false) {
                        continue;
                    }
                }

                $student_id  = trim($row[0] ?? '');
                $full_name   = trim($row[1] ?? '');
                $grade_level = trim($row[2] ?? '');

                if (!$student_id || !$full_name) {
                    $errors[] = "Row $rowNum: Missing student ID or name. Skipped.";
                    $skipCount++;
                    continue;
                }

                // Duplicate check
                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
                $stmt->execute([$student_id]);
                if ($stmt->fetch()) {
                    $errors[] = "Row $rowNum: Student ID '{$student_id}' already exists. Skipped.";
                    $skipCount++;
                    continue;
                }

                $qr_code = 'SRES-' . strtoupper(bin2hex(random_bytes(8)));

                try {
                    $stmt = $pdo->prepare("INSERT INTO students (student_id, full_name, grade_level, section_id, qr_code) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$student_id, $full_name, $grade_level, $section_id, $qr_code]);
                    $successCount++;
                } catch (PDOException $e) {
                    $errors[] = "Row $rowNum: " . $e->getMessage();
                    $skipCount++;
                }
            }
            fclose($handle);
        }

        if ($successCount > 0 && $skipCount === 0) {
            $message = "✅ All {$successCount} students imported successfully!";
        } elseif ($successCount > 0) {
            $message = "✅ {$successCount} imported, ⚠️ {$skipCount} skipped.";
            $messageType = 'warning-message';
        } elseif ($skipCount > 0) {
            $message = "⚠️ No students imported. {$skipCount} rows were skipped.";
            $messageType = 'error-message';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Students | QR Attendance</title>

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
                <h1>Import Students</h1>
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
                <p class="section-label">Bulk Student Import</p>
                <h2>Import from CSV</h2>
                <p>Upload your class list as a CSV file. QR codes will be generated automatically.</p>
            </div>
            <a href="students.php" class="form-cancel" style="text-decoration:none;padding:10px 17px;">← Back to Students</a>
        </div>

        <?php if ($message): ?>
            <p class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="form-card" style="max-width:800px;">
                <h3>⚠️ Import Warnings (<?= count($errors) ?>)</h3>
                <div style="max-height:300px;overflow-y:auto;font-size:13px;color:#64748b;">
                    <?php foreach (array_slice($errors, 0, 50) as $e): ?>
                        <div style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                    <?php if (count($errors) > 50): ?>
                        <div style="padding:10px 0;color:#94a3b8;">... and <?= count($errors) - 50 ?> more</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h3>Upload CSV File</h3>

            <form method="POST" enctype="multipart/form-data">
                <?php if ($isAdmin): ?>
                    <label>Section:</label>
                    <select name="section_id" required>
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
                        All students will be added to your section.
                    </p>
                <?php endif; ?>

                <label>CSV File:</label>
                <input type="file" name="csv_file" accept=".csv" required>

                <button type="submit" class="form-button">Upload & Import</button>
            </form>
        </div>

        <div class="form-card" style="max-width:800px;">
            <h3>📄 CSV Format Guide</h3>

            <p style="font-size:13px;color:#64748b;margin-bottom:15px;">
                Your CSV should have <strong>three columns</strong> in this order:
                <code>student_id</code>, <code>full_name</code>, <code>grade_level</code>
            </p>

            <div style="background:#f8faf9;padding:20px;border-radius:10px;overflow-x:auto;">
                <table style="min-width:auto;">
                    <thead>
                        <tr>
                            <th>student_id</th>
                            <th>full_name</th>
                            <th>grade_level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>123456789012</td><td>Dela Cruz, Juan</td><td>Grade 4</td></tr>
                        <tr><td>123456789013</td><td>Santos, Maria</td><td>Grade 4</td></tr>
                    </tbody>
                </table>
            </div>

            <p style="margin-top:20px;font-size:13px;">
                📥 <a href="download_template.php" style="color:#176b45;font-weight:600;">Download blank template (CSV)</a>
            </p>

            <p style="margin-top:15px;font-size:12px;color:#64748b;">
                <strong>Tips:</strong><br>
                • Save your Excel file as <strong>CSV (Comma delimited)</strong><br>
                • The first row can be a header — it will be skipped automatically<br>
                • Duplicate student IDs are safely skipped with a warning
            </p>
        </div>
    </main>
</div>
</body>
</html>