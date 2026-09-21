<?php
require_once '../config/db.php';
requireLogin();

$isAdmin = ($_SESSION['role'] === 'admin');

// Determine which section
$sectionId = $_GET['section'] ?? null;

if (!$isAdmin) {
    $stmt = $pdo->prepare("SELECT id FROM sections WHERE adviser_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $mySectionId = $stmt->fetchColumn();
    $sectionId = $mySectionId;
}

if (!$sectionId) {
    header("Location: sections.php");
    exit;
}

// Get section info
$stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
$stmt->execute([$sectionId]);
$section = $stmt->fetch();

if (!$section) die("Section not found.");

// Get students
$stmt = $pdo->prepare("SELECT * FROM students WHERE section_id = ? ORDER BY full_name");
$stmt->execute([$sectionId]);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print QR Cards — <?= htmlspecialchars($section['section_name']) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Poppins", Arial, sans-serif; }
        body { background: #f5f7f6; padding: 20px; }

        .no-print-toolbar {
            max-width: 900px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .no-print-toolbar h1 { font-size: 18px; color: #176b45; }
        .no-print-toolbar p { font-size: 12px; color: #64748b; margin-top: 3px; }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-primary { background: #176b45; color: white; }
        .btn-secondary { background: #94a3b8; color: white; margin-right: 8px; }

        .cards-grid {
            max-width: 900px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .qr-card {
            background: white;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            page-break-inside: avoid;
            min-height: 180px;
        }

        .qr-card .qr-img {
            width: 120px;
            height: 120px;
            flex-shrink: 0;
        }

        .qr-card .info {
            flex: 1;
            min-width: 0;
        }

        .qr-card .school {
            font-size: 10px;
            color: #176b45;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .qr-card .name {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .qr-card .meta {
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
        }

        .qr-card .meta strong {
            color: #1e293b;
        }

        .empty {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 12px;
            color: #64748b;
            max-width: 900px;
            margin: 0 auto;
        }

        @media print {
            body { background: white; padding: 0; }
            .no-print-toolbar { display: none; }
            .cards-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                padding: 10px;
                max-width: 100%;
            }
            .qr-card {
                border: 1px solid #999;
                min-height: 160px;
                padding: 15px;
            }
            @page {
                size: A4;
                margin: 10mm;
            }
        }

        @media (max-width: 700px) {
            .cards-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <div class="no-print-toolbar">
        <div>
            <h1>🖨 Print QR Cards</h1>
            <p>Section: <strong><?= htmlspecialchars($section['section_name']) ?></strong> · <?= count($students) ?> students</p>
        </div>
        <div>
            <a href="students.php" class="btn btn-secondary">← Back</a>
            <button onclick="window.print()" class="btn btn-primary">🖨 Print</button>
        </div>
    </div>

    <?php if (empty($students)): ?>
        <div class="empty">
            <p>No students in this section yet.</p>
            <p style="margin-top:10px;">
                <a href="students.php" style="color:#176b45;">Add students first</a> or
                <a href="import_students.php" style="color:#176b45;">import from CSV</a>.
            </p>
        </div>
    <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($students as $s): 
                $qrData = urlencode($s['qr_code']);
                $qrURL  = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=$qrData";
            ?>
                <div class="qr-card">
                    <img src="<?= $qrURL ?>" alt="QR" class="qr-img">
                    <div class="info">
                        <div class="school">Santo Rosario Elementary School</div>
                        <div class="name"><?= htmlspecialchars($s['full_name']) ?></div>
                        <div class="meta">
                            <strong>ID:</strong> <?= htmlspecialchars($s['student_id']) ?><br>
                            <?php if ($s['grade_level']): ?>
                                <strong>Grade:</strong> <?= htmlspecialchars($s['grade_level']) ?><br>
                            <?php endif; ?>
                            <strong>Section:</strong> <?= htmlspecialchars($section['section_name']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>