<?php
session_start();

$host = 'sql207.infinityfree.com'; // Replace with your real hostname
$dbname = 'if0_42914799_qr_attendance';
$username = 'if0_42914799';
$password = 'OdRpWWLP90WkA';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
// Role constants — use these everywhere
define('ROLE_ADMIN',   'admin');
define('ROLE_TEACHER', 'teacher');

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
}
?>