<?php
require_once '../config/db.php';
requireLogin();

$isAdmin = ($_SESSION['role'] === 'admin');
$message = '';
$messageType = 'message';
$editSection = null;

// Initials
$initials = '';
if (!empty($_SESSION['full_name'])) {
    $parts = explode(' ', $_SESSION['full_name']);
    foreach ($parts as $p) {
        if ($p !== '') $initials .= strtoupper($p[0]);
    }
    $initials = substr($initials, 0, 2);
}

// ==== DELETE ====
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($isAdmin) {
        $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Section deleted.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ? AND adviser_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $message = $stmt->rowCount() ? "Your section was deleted." : "You cannot delete that section.";
        if (!$stmt->rowCount()) $messageType = 'error-message';
    }
}

// ==== ADD / UPDATE ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['section_name']);
    $edit_id = $_POST['edit_id'] ?? null;
    $adviser_id = $isAdmin ? ($_POST['adviser_id'] ?: null) : $_SESSION['user_id'];

    if ($name) {
        try {
            if ($edit_id) {
                if ($isAdmin) {
                    $stmt = $pdo->prepare("UPDATE sections SET section_name = ?, adviser_id = ? WHERE id = ?");
                    $stmt->execute([$name, $adviser_id, $edit_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE sections SET section_name = ? WHERE id = ? AND adviser_id = ?");
                    $stmt->execute([$name, $edit_id, $_SESSION['user_id']]);
                }
                $message = "Section updated.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO sections (section_name, adviser_id) VALUES (?, ?)");
                $stmt->execute([$name, $adviser_id]);
                $message = "Section '{$name}' added!";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error-message';
        }
    } else {
        $message = "Section name is required.";
        $messageType = 'error-message';
    }
}

// ==== EDIT MODE ====
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSection = $stmt->fetch();

    if (!$isAdmin && $editSection && $editSection['adviser_id'] != $_SESSION['user_id']) {
        $message = "You cannot edit that section.";
        $messageType = 'error-message';
        $editSection = null;
    }
}

// ==== LOAD SECTIONS ====
if ($isAdmin) {
    $sections = $pdo->query("
        SELECT s.*, u.full_name AS adviser_name,
               (SELECT COUNT(*) FROM students WHERE section_id = s.id) AS student_count
        FROM sections s
        LEFT JOIN users u ON s.adviser_id = u.id
        ORDER BY s.section_name
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name AS adviser_name,
               (SELECT COUNT(*) FROM students WHERE section_id = s.id) AS student_count
        FROM sections s
        LEFT JOIN users u ON s.adviser_id = u.id
        WHERE s.adviser_id = ?
        ORDER BY s.section_name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $sections = $stmt->fetchAll();
}

// ==== ADVISERS LIST (admin only) ====
$advisers = [];
if ($isAdmin) {
    $advisers = $pdo->query("
        SELECT u.id, u.full_name,
               (SELECT section_name FROM sections WHERE adviser_id = u.id LIMIT 1) AS current_section
        FROM users u
        WHERE u.role IN ('teacher', 'admin')
        ORDER BY u.full_name
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isAdmin ? 'All Sections' : 'My Section' ?> | QR Attendance</title>

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
                <h1><?= $isAdmin ? 'Sections' : 'My Section' ?></h1>
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
                <p class="section-label">Class Sections</p>
                <h2><?= $isAdmin ? 'Manage Sections' : 'My Section' ?></h2>
                <p><?= $isAdmin ? 'Create sections and assign advisers.' : 'Edit your section name below.' ?></p>
            </div>
        </div>

        <?php if ($message): ?>
            <p class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if (!$isAdmin && count($sections) > 0 && !$editSection): ?>
            <p class="message">You already own the section below. You can edit its name if needed.</p>
        <?php else: ?>
            <div class="form-card">
                <h3><?= $editSection ? 'Editing: ' . htmlspecialchars($editSection['section_name']) : 'Add New Section' ?></h3>

                <form method="POST">
                    <?php if ($editSection): ?>
                        <input type="hidden" name="edit_id" value="<?= $editSection['id'] ?>">
                    <?php endif; ?>

                    <label>Section Name:</label>
                    <input type="text" name="section_name" required
                           placeholder="e.g. Sampaguita"
                           value="<?= htmlspecialchars($editSection['section_name'] ?? '') ?>">

                    <?php if ($isAdmin): ?>
                        <label>Adviser:</label>
                        <select name="adviser_id">
                            <option value="">-- No Adviser --</option>
                            <?php foreach ($advisers as $a): ?>
                                <option value="<?= $a['id'] ?>"
                                    <?= ($editSection && $editSection['adviser_id'] == $a['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['full_name']) ?>
                                    <?= $a['current_section'] ? ' (currently: ' . htmlspecialchars($a['current_section']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <p style="padding:12px;background:#f1f5f9;border-radius:9px;font-size:13px;color:#64748b;margin-bottom:15px;">
                            You will be assigned as adviser automatically.
                        </p>
                    <?php endif; ?>

                    <button type="submit" class="form-button"><?= $editSection ? 'Update Section' : 'Add Section' ?></button>
                    <?php if ($editSection): ?>
                        <a href="sections.php" class="form-cancel">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <div class="record-card">
            <div class="record-header">
                <h3>Sections (<?= count($sections) ?>)</h3>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Adviser</th>
                            <th>Students</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sections)): ?>
                            <tr><td colspan="4" class="empty-record">No sections yet.</td></tr>
                        <?php else: foreach ($sections as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['section_name']) ?></td>
                                <td><?= htmlspecialchars($s['adviser_name'] ?? '—') ?></td>
                                <td><?= $s['student_count'] ?></td>
                                <td>
                                    <a href="?edit=<?= $s['id'] ?>">Edit</a> |
                                    <a href="?delete=<?= $s['id'] ?>" style="color:#dc2626;"
                                       onclick="return confirm('Delete this section?\n\nAll students in it will remain but become unassigned.');">
                                        Delete
                                    </a>
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