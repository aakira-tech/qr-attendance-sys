<?php
require_once '../config/db.php';
requireLogin();

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = 'message';
$editUser = null;

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
    if ($id !== $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$id]);
        $message = $stmt->rowCount() ? "User deleted." : "Cannot delete that user.";
        if (!$stmt->rowCount()) $messageType = 'error-message';
    } else {
        $message = "You cannot delete your own account.";
        $messageType = 'error-message';
    }
}

// ==== ADD / UPDATE ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $role      = $_POST['role'] ?? 'teacher';
    $edit_id   = $_POST['edit_id'] ?? null;
    $password  = $_POST['password'] ?? '';

    // Only allow valid roles
    if (!in_array($role, ['admin', 'teacher'])) {
        $role = 'teacher';
    }

    if ($full_name && $username) {
        try {
            if ($edit_id) {
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, role=?, password=? WHERE id=?");
                    $stmt->execute([$full_name, $username, $role, $hash, $edit_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, role=? WHERE id=?");
                    $stmt->execute([$full_name, $username, $role, $edit_id]);
                }
                $message = "User updated.";
            } else {
                if (!$password) {
                    $message = "Password is required for new users.";
                    $messageType = 'error-message';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$full_name, $username, $hash, $role]);
                    $message = "User '{$full_name}' created.";
                }
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error-message';
        }
    } else {
        $message = "Name and username are required.";
        $messageType = 'error-message';
    }
}

// ==== EDIT MODE ====
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editUser = $stmt->fetch();
}

// ==== LOAD USERS ====
$users = $pdo->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | QR Attendance</title>

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
                <h1>Users</h1>
            </div>
            <div class="topbar-profile">
                <div class="teacher-avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </header>

        <div class="page-header">
            <div>
                <p class="section-label">User Management</p>
                <h2>Manage Teacher Accounts</h2>
                <p>Create accounts for teachers. Assign them to a section via the Sections page.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <p class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <div class="form-card">
            <h3><?= $editUser ? 'Editing: ' . htmlspecialchars($editUser['full_name']) : 'Create New User' ?></h3>

            <form method="POST">
                <?php if ($editUser): ?>
                    <input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>">
                <?php endif; ?>

                <label>Full Name:</label>
                <input type="text" name="full_name" required
                       value="<?= htmlspecialchars($editUser['full_name'] ?? '') ?>">

                <label>Username:</label>
                <input type="text" name="username" required
                       value="<?= htmlspecialchars($editUser['username'] ?? '') ?>">

                <label>Password <?= $editUser ? '(leave blank to keep current)' : '' ?>:</label>
                <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>

                <label>Role:</label>
                <select name="role" required>
                    <option value="teacher" <?= ($editUser && $editUser['role'] === 'teacher') ? 'selected' : '' ?>>Teacher</option>
                    <option value="admin" <?= ($editUser && $editUser['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>

                <button type="submit" class="form-button"><?= $editUser ? 'Update User' : 'Create User' ?></button>
                <?php if ($editUser): ?>
                    <a href="users.php" class="form-cancel">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="record-card">
            <div class="record-header">
                <h3>Users (<?= count($users) ?>)</h3>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Section</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $secStmt = $pdo->prepare("SELECT section_name FROM sections WHERE adviser_id = ?");
                            $secStmt->execute([$u['id']]);
                            $sectionName = $secStmt->fetchColumn();
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span style="color:#176b45;font-weight:600;">Admin</span>
                                    <?php else: ?>
                                        <span style="color:#64748b;">Teacher</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($sectionName ?: '—') ?></td>
                                <td>
                                    <a href="?edit=<?= $u['id'] ?>">Edit</a>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        | <a href="?delete=<?= $u['id'] ?>" style="color:#dc2626;"
                                             onclick="return confirm('Delete this user?');">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>