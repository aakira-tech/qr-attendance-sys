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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR | QR Attendance</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">

    <script src="https://unpkg.com/html5-qrcode"></script>
</head>

<body>
<div class="main-website">
    <?php include '../includes/header.php'; ?>

    <main class="content">
        <header class="topbar">
            <div>
                <p class="topbar-label">Attendance Monitoring System</p>
                <h1>Scan Attendance</h1>
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
                <p class="section-label">QR Scanner</p>
                <h2>Scan Student QR Code</h2>
                <p>Point the camera at the student's QR code. Attendance is recorded automatically.</p>
            </div>
        </div>

        <div class="scanner-container">
            <div id="reader"></div>
            <p id="status" style="text-align:center;margin-top:15px;color:#64748b;font-size:13px;">
                Ready to scan...
            </p>
        </div>
    </main>
</div>

<!-- Notification popup -->
<div id="notification">
    <div id="notification-box"></div>
</div>

<script>
const notification = document.getElementById('notification');
const notificationBox = document.getElementById('notification-box');
const statusEl = document.getElementById('status');
let lastScanTime = 0;

function playBeep(success) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = success ? 900 : 300;
        gain.gain.value = 0.15;
        osc.start();
        osc.stop(ctx.currentTime + 0.15);
    } catch(e) {}
}

function showNotification(message, isSuccess) {
    notificationBox.innerHTML = message;
    notification.className = 'show ' + (isSuccess ? 'success' : 'error');
    playBeep(isSuccess);

    setTimeout(() => {
        notification.className = '';
    }, 3000);
}

function onScanSuccess(decodedText) {
    const now = Date.now();
    if (now - lastScanTime < 2000) return;
    lastScanTime = now;

    statusEl.textContent = 'Processing...';

    fetch('/api/record_attendance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'qr_code=' + encodeURIComponent(decodedText)
    })
    .then(res => res.json())
    .then(data => {
        showNotification(data.message, data.success);
        statusEl.textContent = data.success ? '✅ Last scan recorded' : '⚠️ Scan rejected';
    })
    .catch(err => {
        console.error(err);
        showNotification('❌ Connection error', false);
        statusEl.textContent = 'Error';
    });
}

const scanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
scanner.render(onScanSuccess);
</script>
</body>
</html>