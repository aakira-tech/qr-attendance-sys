<?php
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$qr_code = $_POST['qr_code'] ?? '';
if (!$qr_code) {
    echo json_encode(['success' => false, 'message' => 'No QR code received.']);
    exit;
}

// Find student
$stmt = $pdo->prepare("SELECT * FROM students WHERE qr_code = ?");
$stmt->execute([$qr_code]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'QR code not recognized.']);
    exit;
}

$today = date('Y-m-d');
$now   = date('H:i:s');

// Configurable rules (24-hour format)
$lateThreshold       = '07:30:00';  // After this = late
$minGapMinutes       = 1;           // Minimum minutes between time-in and time-out
$cooldownSeconds     = 20;          // Prevent duplicate scans within X seconds

// Check today's record
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND attendance_date = ?");
$stmt->execute([$student['id'], $today]);
$record = $stmt->fetch();

// === COOLDOWN CHECK ===
// If last scan was < cooldownSeconds ago, silently ignore it
if ($record) {
    $lastScan = $record['time_out'] ?: $record['time_in'];
    $lastScanTime = strtotime($today . ' ' . $lastScan);
    $secondsSinceLastScan = time() - $lastScanTime;

    if ($secondsSinceLastScan < $cooldownSeconds) {
        echo json_encode([
            'success' => false,
            'message' => "⏳ Please wait a moment before scanning again."
        ]);
        exit;
    }
}

if (!$record) {
    // === FIRST SCAN → TIME IN ===
    $status = ($now > $lateThreshold) ? 'late' : 'present';
    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, attendance_date, time_in, status, recorded_by)
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$student['id'], $today, $now, $status, $_SESSION['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => "✅ TIME IN: {$student['full_name']} at " . date('h:i A') . " ({$status})"
    ]);
} elseif (!$record['time_out']) {
    // === SECOND SCAN → TIME OUT (only if enough time has passed) ===
    $timeInSeconds = strtotime($today . ' ' . $record['time_in']);
    $minutesSinceTimeIn = (time() - $timeInSeconds) / 60;

    if ($minutesSinceTimeIn < $minGapMinutes) {
        echo json_encode([
            'success' => false,
            'message' => "⏳ {$student['full_name']} just timed in. Time-out available after {$minGapMinutes} minute(s)."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE attendance SET time_out = ? WHERE id = ?");
    $stmt->execute([$now, $record['id']]);

    echo json_encode([
        'success' => true,
        'message' => "👋 TIME OUT: {$student['full_name']} at " . date('h:i A')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "⚠️ {$student['full_name']} already completed attendance today."
    ]);
}