<?php
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        header("Location: ../admin/dashboard.php");
        exit;
    } else {
        $error = "Invalid Teacher ID or Password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santo Rosario Elementary School | Attendance System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <section class="login-page">
        <div class="login-card">

            <div class="login-logo">
                <img src="../assets/img/logo.jpg" alt="Santo Rosario Elementary School Logo">
            </div>

            <h1>Santo Rosario</h1>
            <p class="login-school-name">Elementary School</p>

            <div class="login-divider"></div>

            <h2>Teacher Login</h2>
            <p class="login-description">
                Please enter your username and password to continue.
            </p>

            <form method="POST">
                <div class="input-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           placeholder="Enter your username" required autofocus>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password" required>
                </div>

                <?php if ($error): ?>
                    <div class="login-error show"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <button type="submit" class="login-button">Log In</button>
            </form>

            <p class="login-footer">
                Santo Rosario Elementary School<br>
                Attendance Monitoring System
            </p>

        </div>
    </section>
</body>
</html>