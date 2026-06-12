<?php
// =============================================
// certificate.php — EventSphere PDF Certificate
// URL: certificate.php?pid=5&event_id=2&type=winner
//      certificate.php?pid=5&event_id=2&type=participation
// =============================================

require_once 'config.php';

if (!isset($_SESSION['admin_id'])) redirect('login.php?role=admin');

if (!file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    die("<div style='font-family:sans-serif;padding:40px;background:#fee;border:1px solid red;color:#900;border-radius:8px;'>
        <h2>⚠️ FPDF Not Found</h2>
        <p>Place <code>fpdf/fpdf.php</code> inside <code>event_management_system/</code></p>
    </div>");
}

require_once __DIR__ . '/fpdf/fpdf.php';

// ── DEBUG mode ────────────────────────────────────────────────
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    $s1 = __DIR__ . '/assets/sign_coordinator.png';
    $s2 = __DIR__ . '/assets/sign_principal.png';
    echo "<div style='font-family:monospace;padding:20px;background:#111;color:#0f0;'>";
    echo "<h3 style='color:#ff0;'>🔍 Debug</h3>";
    echo "<p>Path: <b>" . __DIR__ . "</b></p>";
    echo "<p>sign_coordinator.png: <b style='color:" . (file_exists($s1)?'#0f0':'#f00') . "'>" . (file_exists($s1)?'✅ FOUND':'❌ NOT FOUND → '.$s1) . "</b></p>";
    echo "<p>sign_principal.png: <b style='color:" . (file_exists($s2)?'#0f0':'#f00') . "'>" . (file_exists($s2)?'✅ FOUND':'❌ NOT FOUND → '.$s2) . "</b></p>";
    echo "</div>"; exit;
}

$conn = getConnection();
$pid  = (int)($_GET['pid']      ?? 0);
$eid  = (int)($_GET['event_id'] ?? 0);
$type = clean($_GET['type']     ?? 'participation');

if (!$pid || !$eid) die("Invalid parameters.");

$stmt = $conn->prepare("SELECT * FROM participants WHERE id = ?");
$stmt->bind_param("i", $pid);
$stmt->execute();
$participant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$participant) die("Participant not found.");

$ev_row = $conn->query("SELECT * FROM events WHERE id = $eid")->fetch_assoc();
if (!$ev_row) die("Event not found.");

// Rank for winner
$rank = 1; $rank_label = '1st Place';
if ($type === 'winner') {
    $ranked = $conn->query("
        SELECT participant_id, SUM(total) as grand_total
        FROM scores WHERE event_id=$eid
        GROUP BY participant_id ORDER BY grand_total DESC
    ");
    $r = 0;
    while ($row = $ranked->fetch_assoc()) {
        $r++;
        if ((int)$row['participant_id'] === $pid) { $rank = $r; break; }
    }
    $rank_label = match($rank) { 1=>'1st Place', 2=>'2nd Place', 3=>'3rd Place', default=>$rank.'th Place' };
}

$conn->close();

// ── Team member override ──────────────────────────────────────
// If member_name is passed, generate certificate FOR that member
// (using leader's participant record for event/rank lookup)
$is_member   = isset($_GET['member_name']) && trim($_GET['member_name']) !== '';
$name        = $is_member ? clean($_GET['member_name'])    : $participant['name'];
$college     = $is_member ? ($participant['college'])       : $participant['college'];
$department  = $is_member ? clean($_GET['member_dept'] ?? $participant['department']) : $participant['department'];
$student_id_cert = $is_member ? clean($_GET['member_sid'] ?? '') : ($participant['student_id'] ?? '');
$event_name  = $ev_row['event_name'];

// ═══════════════════════════════════════════════════
// COLOR SCHEME — Professional White Certificate
// ═══════════════════════════════════════════════════
// BG:         White  (255,255,255)
// Border:     Gold   (180,140,20)
// Title:      Dark Navy (10,20,80)
// Name:       Deep Gold (160,120,10)
// Body text:  Dark Gray (50,50,50)
// Italic:     Medium Gray (90,80,60)
// Accent:     Gold (180,140,20)

class CertPDF extends FPDF {
    public string $cert_type  = 'participation';
    public string $rank_label = '';
    public string $sign_path  = '';

    function Header() {
        // White background
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, 297, 210, 'F');

        // Outer gold border
        $this->SetDrawColor(180, 140, 20);
        $this->SetLineWidth(2);
        $this->Rect(7, 7, 283, 196);

        // Inner thin border
        $this->SetLineWidth(0.5);
        $this->SetDrawColor(200, 165, 40);
        $this->Rect(10, 10, 277, 190);

        // Top gold bar
        $this->SetFillColor(180, 140, 20);
        $this->Rect(7, 7, 283, 3, 'F');
        $this->Rect(7, 200, 283, 3, 'F');

        // Corner decorations (diamond ◆)
        $this->SetFont('Times', 'B', 16);
        $this->SetTextColor(180, 140, 20);
        foreach([[7,7],[278,7],[7,197],[278,197]] as [$x,$y]) {
            $this->SetXY($x-2, $y-1);
            $this->Cell(8, 8, chr(183), 0, 0, 'C');
        }

        // Organization name — top center
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(140, 110, 30);
        $this->SetXY(0, 13);
        $this->Cell(297, 5, 'NATIONAL LEVEL TECHNICAL & CULTURAL FEST', 0, 1, 'C');

        // Horizontal gold divider
        $this->SetDrawColor(200, 165, 40);
        $this->SetLineWidth(0.4);
        $this->Line(35, 20, 262, 20);

        // EVENTSPHERE — main title
        $this->SetFont('Times', 'B', 30);
        $this->SetTextColor(10, 20, 80); // Deep navy
        $this->SetXY(0, 21);
        $this->Cell(297, 16, 'EventSphere', 0, 1, 'C');

        // Bottom divider of header
        $this->SetDrawColor(180, 140, 20);
        $this->SetLineWidth(0.6);
        $this->Line(35, 38, 262, 38);
    }

    function Footer() {
        // Top line of footer area
        $this->SetDrawColor(180, 140, 20);
        $this->SetLineWidth(0.5);
        $this->Line(35, 172, 262, 172);

        // ── LEFT: Event Coordinator ──────────────────────
        $sign1 = $this->sign_path . '/assets/sign_coordinator.png';
        if (file_exists($sign1)) {
            // White box behind image (removes grey bg)
            $this->SetFillColor(255, 255, 255);
            $this->Rect(33, 160, 62, 14, 'F');
            // Signature image
            $this->Image($sign1, 36, 161, 56, 12);
        } else {
            // Dotted fallback line
            $this->SetDrawColor(150, 120, 30);
            $this->SetLineWidth(0.3);
            $this->Line(35, 174, 100, 174);
        }

        // Label
        $this->SetXY(28, 174);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(10, 20, 80);
        $this->Cell(75, 4, 'Event Coordinator', 0, 1, 'C');
        $this->SetX(28);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(100, 85, 30);
        $this->Cell(75, 3, 'EventSphere', 0, 1, 'C');

        // ── CENTER: Date ──────────────────────────────────
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(80, 70, 50);
        $this->SetXY(111, 175);
        $this->Cell(75, 5, 'Date: ' . date('d F Y'), 0, 0, 'C');

        // Seal circle placeholder (center)
        $this->SetDrawColor(200, 165, 40);
        $this->SetLineWidth(0.4);
        $this->Circle(148.5, 178, 8);
        $this->SetFont('Helvetica', '', 5);
        $this->SetTextColor(150, 120, 30);
        $this->SetXY(140, 175.5);
        $this->Cell(17, 5, 'OFFICIAL', 0, 0, 'C');
        $this->SetXY(140, 179);
        $this->Cell(17, 4, 'SEAL', 0, 0, 'C');

        // ── RIGHT: Principal ──────────────────────────────
        $sign2 = $this->sign_path . '/assets/sign_principal.png';
        if (file_exists($sign2)) {
            // White box behind image
            $this->SetFillColor(255, 255, 255);
            $this->Rect(202, 160, 62, 14, 'F');
            // Signature image
            $this->Image($sign2, 205, 161, 56, 12);
        } else {
            $this->SetDrawColor(150, 120, 30);
            $this->SetLineWidth(0.3);
            $this->Line(197, 174, 262, 174);
        }

        // Label
        $this->SetXY(194, 174);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(10, 20, 80);
        $this->Cell(75, 4, 'Principal / Director', 0, 1, 'C');
        $this->SetX(194);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(100, 85, 30);
        $this->Cell(75, 3, 'Institution', 0, 1, 'C');

        // Bottom note
        $this->SetFont('Helvetica', 'I', 6.5);
        $this->SetTextColor(130, 110, 60);
        $this->SetXY(0, 192);
        $this->Cell(297, 4, 'This certificate is issued by EventSphere and is valid as an official participation record.', 0, 0, 'C');
    }

    // Circle helper (FPDF doesn't have one built-in)
    function Circle($x, $y, $r, $style='D') {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    function Ellipse($x, $y, $rx, $ry, $style='D') {
        if ($style=='F') $op='f';
        elseif ($style=='FD'||$style=='DF') $op='B';
        else $op='S';
        $lx = 4/3*(M_SQRT2-1)*$rx;
        $ly = 4/3*(M_SQRT2-1)*$ry;
        $k  = $this->k;
        $h  = $this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$rx)*$k, ($h-$y)*$k,
            ($x+$rx)*$k, ($h-($y-$ly))*$k,
            ($x+$lx)*$k, ($h-($y-$ry))*$k,
            $x*$k, ($h-($y-$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$lx)*$k, ($h-($y-$ry))*$k,
            ($x-$rx)*$k, ($h-($y-$ly))*$k,
            ($x-$rx)*$k, ($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$rx)*$k, ($h-($y+$ly))*$k,
            ($x-$lx)*$k, ($h-($y+$ry))*$k,
            $x*$k, ($h-($y+$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x+$lx)*$k, ($h-($y+$ry))*$k,
            ($x+$rx)*$k, ($h-($y+$ly))*$k,
            ($x+$rx)*$k, ($h-$y)*$k, $op));
    }
}

// ── Build PDF ─────────────────────────────────────────────────
$pdf = new CertPDF('L', 'mm', 'A4');
$pdf->cert_type  = $type;
$pdf->rank_label = $rank_label;
$pdf->sign_path  = __DIR__;
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// ── Certificate type heading ──────────────────────────────────
if ($type === 'winner') {
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(10, 20, 80);
    $pdf->SetXY(0, 40);
    $pdf->Cell(297, 7, 'CERTIFICATE OF ACHIEVEMENT', 0, 1, 'C');

    // Gold underline below heading
    $pdf->SetDrawColor(180, 140, 20);
    $pdf->SetLineWidth(0.4);
    $tw = $pdf->GetStringWidth('CERTIFICATE OF ACHIEVEMENT');
    $pdf->Line((297-$tw)/2, 47, (297+$tw)/2, 47);
} else {
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(10, 20, 80);
    $pdf->SetXY(0, 40);
    $pdf->Cell(297, 7, 'CERTIFICATE OF PARTICIPATION', 0, 1, 'C');

    $pdf->SetDrawColor(180, 140, 20);
    $pdf->SetLineWidth(0.4);
    $tw = $pdf->GetStringWidth('CERTIFICATE OF PARTICIPATION');
    $pdf->Line((297-$tw)/2, 47, (297+$tw)/2, 47);
}

// ── "This is to certify that" ─────────────────────────────────
$pdf->SetFont('Times', 'I', 12);
$pdf->SetTextColor(90, 75, 40);
$pdf->SetXY(0, 50);
$pdf->Cell(297, 8, 'This is to certify that', 0, 1, 'C');

// ── Participant Name ──────────────────────────────────────────
$pdf->SetFont('Times', 'B', 36);
$pdf->SetTextColor(160, 120, 10); // Rich dark gold
$pdf->SetXY(0, 59);
$pdf->Cell(297, 20, $name, 0, 1, 'C');

// Elegant underline
$nameW = $pdf->GetStringWidth($name);
$cx    = (297 - $nameW) / 2;
$pdf->SetDrawColor(180, 140, 20);
$pdf->SetLineWidth(0.6);
$pdf->Line($cx, 79, $cx + $nameW, 79);

// ── College | Department ──────────────────────────────────────
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(60, 50, 30);
$pdf->SetXY(0, 81);
$cert_sub = $college . '   |   ' . $department;
if (!empty($student_id_cert)) $cert_sub .= '   |   ' . $student_id_cert;
$pdf->Cell(297, 6, $cert_sub, 0, 1, 'C');

// ── Connector phrase ─────────────────────────────────────────
$pdf->SetFont('Times', 'I', 13);
$pdf->SetTextColor(90, 75, 40);
$pdf->SetXY(0, 91);
if ($type === 'winner') {
    $pdf->Cell(297, 7, 'has achieved', 0, 1, 'C');
} else {
    $pdf->Cell(297, 7, 'has successfully participated in', 0, 1, 'C');
}

// ── Winner Rank Badge ─────────────────────────────────────────
if ($type === 'winner') {
    // Gold bordered rank box
    $pdf->SetFillColor(255, 248, 220); // Light gold fill
    $pdf->SetDrawColor(180, 140, 20);
    $pdf->SetLineWidth(1);
    $pdf->Rect(97, 99, 103, 20, 'DF');

    // Rank label
    $pdf->SetFont('Times', 'B', 20);
    $pdf->SetTextColor(140, 100, 5);
    $pdf->SetXY(0, 102);
    $pdf->Cell(297, 13, chr(183) . '  ' . $rank_label . ' Winner  ' . chr(183), 0, 1, 'C');

    $yNext = 122;
} else {
    $yNext = 100;
}

// ── "in the event" ───────────────────────────────────────────
$pdf->SetFont('Times', 'I', 13);
$pdf->SetTextColor(90, 75, 40);
$pdf->SetXY(0, $yNext);
$pdf->Cell(297, 7, 'in the event', 0, 1, 'C');

// ── Event Name ───────────────────────────────────────────────
$pdf->SetFont('Times', 'B', 24);
$pdf->SetTextColor(10, 20, 80); // Deep navy for event
$pdf->SetXY(0, $yNext + 7);
$pdf->Cell(297, 13, $event_name, 0, 1, 'C');

// Event underline
$evW = $pdf->GetStringWidth($event_name);
$ex  = (297 - $evW) / 2;
$pdf->SetDrawColor(160, 130, 30);
$pdf->SetLineWidth(0.3);
$pdf->Line($ex, $yNext + 20, $ex + $evW, $yNext + 20);

// ── Organised by ─────────────────────────────────────────────
$pdf->SetFont('Helvetica', 'I', 9);
$pdf->SetTextColor(110, 90, 40);
$pdf->SetXY(0, $yNext + 23);
$pdf->Cell(297, 6, 'organised by EventSphere  —  ' . SITE_TAGLINE, 0, 1, 'C');

// ── Decorative separator ──────────────────────────────────────
$pdf->SetFont('Times', '', 13);
$pdf->SetTextColor(180, 140, 20);
$pdf->SetXY(0, $yNext + 31);
$pdf->Cell(297, 6, chr(183) . '   ' . chr(183) . '   ' . chr(183), 0, 1, 'C');

// ── Output ───────────────────────────────────────────────────
$filename = 'EventSphere_Certificate_'
          . preg_replace('/[^A-Za-z0-9_]/', '_', $name)
          . '_' . $type . '.pdf';
$pdf->Output('D', $filename);
exit;
