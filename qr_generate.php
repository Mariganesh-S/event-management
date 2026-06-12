<?php
// =============================================
// qr_generate.php — QR Code Image Generator
// Usage: qr_generate.php?pid=5
// Returns: PNG image of QR code
// =============================================

require_once 'config.php';
// Public — no auth needed (just returns image)

$pid = (int)($_GET['pid'] ?? 0);
if (!$pid) {
    http_response_code(400);
    exit('Invalid participant ID');
}

// Check phpqrcode library
$qrlib = __DIR__ . '/phpqrcode/qrlib.php';
if (!file_exists($qrlib)) {
    // Fallback: use Google Charts QR API (no library needed)
    $data = urlencode("EVENTSPHERE:PID:$pid");
    header("Location: https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$data&choe=UTF-8");
    exit;
}

require_once $qrlib;

// QR data — encode participant info
$conn = getConnection();
$stmt = $conn->prepare("
    SELECT p.*, GROUP_CONCAT(e.event_name SEPARATOR ', ') as events
    FROM participants p
    LEFT JOIN participant_events pe ON p.id = pe.participant_id
    LEFT JOIN events e ON pe.event_id = e.id
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->bind_param("i", $pid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$p) { http_response_code(404); exit('Participant not found'); }

// QR content
$qr_data = "EVENTSPHERE|PID:{$p['id']}|NAME:{$p['name']}|ID:{$p['student_id']}|COLLEGE:{$p['college']}";

// Output QR as PNG
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

QRcode::png(
    $qr_data,
    false,      // file — false = output directly
    QR_ECLEVEL_M,
    8,          // pixel size per module
    2           // margin
);
exit;
