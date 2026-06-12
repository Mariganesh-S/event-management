<?php
// =============================================
// mailer.php — Email with QR Code
// Sends registration confirmation + QR ticket
// =============================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// ── CONFIGURE YOUR GMAIL ─────────────────────
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USERNAME',  'theofficialbox234@gmail.com');  // ← Your Gmail
define('MAIL_PASSWORD',  'ynra iuub kmho wyxa');   // ← 16-digit App Password
define('MAIL_FROM',      'theofficialbox234@gmail.com');  // ← Same Gmail
define('MAIL_FROM_NAME', 'EventSphere');
// ─────────────────────────────────────────────

/**
 * Generate QR code as base64 PNG
 * Works with phpqrcode OR Google Charts API fallback
 */
function generateQRBase64(int $pid, string $name, string $student_id, string $college): string {
    $qr_data = "EVENTSPHERE|PID:{$pid}|NAME:{$name}|ID:{$student_id}|COLLEGE:{$college}";

    // Try phpqrcode first
    $qrlib = __DIR__ . '/phpqrcode/qrlib.php';
    if (file_exists($qrlib)) {
        require_once $qrlib;
        ob_start();
        QRcode::png($qr_data, false, QR_ECLEVEL_M, 8, 2);
        $img_data = ob_get_clean();
        return base64_encode($img_data);
    }

    // Fallback: Google Charts QR API
    $url      = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_data) . '&choe=UTF-8';
    $img_data = @file_get_contents($url);
    if ($img_data) return base64_encode($img_data);

    // Last fallback: simple placeholder
    return '';
}

/**
 * Send registration confirmation email with QR code
 */
function sendRegistrationMail(array $data): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($data['email'], $data['name']);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // Generate QR code
        $qr_base64 = generateQRBase64(
            $data['pid'],
            $data['name'],
            $data['student_id'],
            $data['college']
        );

        // Embed QR as inline image
        $qr_cid = '';
        if ($qr_base64) {
            $qr_cid = 'qrcode_' . $data['pid'];
            $mail->addStringEmbeddedImage(
                base64_decode($qr_base64),
                $qr_cid,
                'qr_ticket.png',
                'base64',
                'image/png'
            );
        }

        $mail->isHTML(true);
        $mail->Subject = '🎉 Registration Confirmed — EventSphere | Participant #' . $data['pid'];
        $mail->Body    = buildEmailHTML($data, $qr_cid);
        $mail->AltBody = buildEmailText($data);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Build HTML email with QR code section
 */
function buildEmailHTML(array $d, string $qr_cid = ''): string {
    $name       = htmlspecialchars($d['name']);
    $pid        = $d['pid'];
    $college    = htmlspecialchars($d['college']);
    $dept       = htmlspecialchars($d['department']);
    $student_id = htmlspecialchars($d['student_id']);
    $events     = $d['events'];

    // Event rows
    $event_rows = '';
    $icons = ['🎤','💃','💻','📷','🗣️','🎬','🎵','🎭'];
    foreach ($events as $i => $ev) {
        $icon = $icons[$i % count($icons)];
        $event_rows .= "
        <tr>
          <td style='padding:10px 16px;border-bottom:1px solid #1e2a5a;font-size:1.1rem;width:40px;'>{$icon}</td>
          <td style='padding:10px 16px;border-bottom:1px solid #1e2a5a;color:#e2e8ff;font-weight:500;'>
            " . htmlspecialchars($ev) . "
          </td>
          <td style='padding:10px 16px;border-bottom:1px solid #1e2a5a;text-align:right;'>
            <span style='background:#0f1f5c;color:#f5c518;border:1px solid rgba(245,197,24,0.3);
                         border-radius:50px;padding:3px 12px;font-size:0.75rem;font-weight:700;'>
              ✓ Registered
            </span>
          </td>
        </tr>";
    }

    // QR section
    $qr_section = '';
    if ($qr_cid) {
        $qr_section = "
        <tr>
          <td style='background:#0d1540;padding:0 40px 28px;'>
            <table width='100%' cellpadding='0' cellspacing='0'
              style='background:#080f2e;border:1px solid rgba(245,197,24,0.2);border-radius:12px;overflow:hidden;'>
              <tr>
                <td style='padding:16px 20px;border-bottom:1px solid #1e2a5a;'>
                  <span style='color:#f5c518;font-size:0.75rem;font-weight:700;
                                text-transform:uppercase;letter-spacing:1px;'>
                    📱 Your QR Entry Ticket
                  </span>
                </td>
              </tr>
              <tr>
                <td style='padding:20px;text-align:center;'>
                  <img src='cid:{$qr_cid}'
                    alt='QR Code Ticket'
                    style='width:160px;height:160px;border-radius:10px;
                           background:white;padding:8px;display:block;margin:0 auto;'>
                  <div style='color:rgba(255,255,255,0.5);font-size:0.78rem;margin-top:12px;'>
                    Show this QR code at the event entrance
                  </div>
                  <div style='color:#f5c518;font-size:0.82rem;font-weight:700;margin-top:4px;'>
                    Participant ID: #<span style='letter-spacing:2px;'>{$pid}</span>
                  </div>
                  <div style='color:rgba(255,255,255,0.3);font-size:0.70rem;margin-top:8px;'>
                    One scan per participant — valid for event day only
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>";
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#05091a;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#05091a;padding:40px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#0a1535 0%,#0f1f6a 100%);
               border-radius:16px 16px 0 0;padding:36px 40px;text-align:center;
               border-bottom:2px solid #f5c518;">
      <div style="font-size:2rem;font-weight:900;color:#f5c518;
                  letter-spacing:2px;font-family:Georgia,serif;">EventSphere</div>
      <div style="color:rgba(255,255,255,0.5);font-size:0.8rem;
                  margin-top:4px;letter-spacing:1px;">
        NATIONAL LEVEL TECHNICAL & CULTURAL FEST
      </div>
    </td>
  </tr>

  <!-- Hero -->
  <tr>
    <td style="background:#0d1540;padding:36px 40px;text-align:center;">
      <div style="font-size:3rem;margin-bottom:8px;">🎉</div>
      <h1 style="color:#ffffff;font-size:1.6rem;font-weight:700;
                 margin:0 0 8px;font-family:Georgia,serif;">
        You're In, {$name}!
      </h1>
      <p style="color:rgba(255,255,255,0.55);margin:0;font-size:0.95rem;line-height:1.6;">
        Your registration for EventSphere has been confirmed.<br>
        Get ready to compete and shine! ✨
      </p>
    </td>
  </tr>

  <!-- Participant ID -->
  <tr>
    <td style="background:#0d1540;padding:0 40px 28px;">
      <div style="background:linear-gradient(135deg,#0a1535,#0f1f6a);
                  border:1px solid rgba(245,197,24,0.3);border-radius:12px;
                  padding:20px 24px;text-align:center;">
        <div style="color:rgba(255,255,255,0.45);font-size:0.72rem;
                    text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;">
          Your Participant ID
        </div>
        <div style="color:#f5c518;font-size:2.4rem;font-weight:900;
                    font-family:Georgia,serif;line-height:1;">
          #<span style="letter-spacing:3px;">{$pid}</span>
        </div>
        <div style="color:rgba(255,255,255,0.3);font-size:0.72rem;margin-top:6px;">
          Quote this ID for any queries
        </div>
      </div>
    </td>
  </tr>

  <!-- QR Code Section -->
  {$qr_section}

  <!-- Details -->
  <tr>
    <td style="background:#0d1540;padding:0 40px 28px;">
      <table width="100%" cellpadding="0" cellspacing="0"
        style="background:#080f2e;border:1px solid #1e2a5a;border-radius:10px;overflow:hidden;">
        <tr>
          <td colspan="2" style="padding:14px 16px;border-bottom:1px solid #1e2a5a;">
            <span style="color:#f5c518;font-size:0.75rem;font-weight:700;
                          text-transform:uppercase;letter-spacing:1px;">👤 Your Details</span>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 16px;color:rgba(255,255,255,0.4);font-size:0.82rem;
                     width:40%;border-bottom:1px solid #1e2a5a;">Name</td>
          <td style="padding:10px 16px;color:#e2e8ff;font-weight:600;font-size:0.88rem;
                     border-bottom:1px solid #1e2a5a;">{$name}</td>
        </tr>
        <tr>
          <td style="padding:10px 16px;color:rgba(255,255,255,0.4);font-size:0.82rem;
                     border-bottom:1px solid #1e2a5a;">College</td>
          <td style="padding:10px 16px;color:#e2e8ff;font-size:0.88rem;
                     border-bottom:1px solid #1e2a5a;">{$college}</td>
        </tr>
        <tr>
          <td style="padding:10px 16px;color:rgba(255,255,255,0.4);font-size:0.82rem;
                     border-bottom:1px solid #1e2a5a;">Department</td>
          <td style="padding:10px 16px;color:#e2e8ff;font-size:0.88rem;
                     border-bottom:1px solid #1e2a5a;">{$dept}</td>
        </tr>
        <tr>
          <td style="padding:10px 16px;color:rgba(255,255,255,0.4);font-size:0.82rem;">Student ID</td>
          <td style="padding:10px 16px;color:#e2e8ff;font-size:0.88rem;">{$student_id}</td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Registered Events -->
  <tr>
    <td style="background:#0d1540;padding:0 40px 28px;">
      <table width="100%" cellpadding="0" cellspacing="0"
        style="background:#080f2e;border:1px solid #1e2a5a;border-radius:10px;overflow:hidden;">
        <tr>
          <td colspan="3" style="padding:14px 16px;border-bottom:1px solid #1e2a5a;">
            <span style="color:#f5c518;font-size:0.75rem;font-weight:700;
                          text-transform:uppercase;letter-spacing:1px;">🎯 Registered Events</span>
          </td>
        </tr>
        {$event_rows}
      </table>
    </td>
  </tr>

  <!-- Important Notes -->
  <tr>
    <td style="background:#0d1540;padding:0 40px 28px;">
      <div style="background:rgba(245,197,24,0.07);
                  border:1px solid rgba(245,197,24,0.2);
                  border-radius:10px;padding:16px 20px;">
        <div style="color:#f5c518;font-size:0.78rem;font-weight:700;
                    text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
          📌 Important Notes
        </div>
        <ul style="color:rgba(255,255,255,0.6);font-size:0.84rem;
                   line-height:1.9;margin:0;padding-left:18px;">
          <li>Arrive <strong style="color:#f5c518;">30 minutes early</strong> before your event</li>
          <li>Show the <strong style="color:#f5c518;">QR code</strong> above at the entrance for check-in</li>
          <li>Carry your college ID and this email</li>
          <li>Quote Participant ID <strong style="color:#f5c518;">#${pid}</strong> for any queries</li>
          <li>Contact the organizing committee for any issues</li>
        </ul>
      </div>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#080f2e;border-radius:0 0 16px 16px;
               padding:24px 40px;text-align:center;border-top:1px solid #1e2a5a;">
      <p style="color:rgba(255,255,255,0.25);font-size:0.75rem;margin:0;line-height:1.6;">
        This is an automated confirmation from EventSphere.<br>
        Please do not reply to this email.
      </p>
      <p style="color:rgba(245,197,24,0.4);font-size:0.78rem;margin:12px 0 0;font-weight:600;">
        EventSphere — Compete · Perform · Shine ✨
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Plain text fallback
 */
function buildEmailText(array $d): string {
    $events = implode(', ', $d['events']);
    return "Hi {$d['name']},\n\n"
         . "Your EventSphere registration is CONFIRMED!\n\n"
         . "Participant ID : #{$d['pid']}\n"
         . "Name           : {$d['name']}\n"
         . "College        : {$d['college']}\n"
         . "Department     : {$d['department']}\n"
         . "Student ID     : {$d['student_id']}\n"
         . "Events         : {$events}\n\n"
         . "Show your QR code (attached) at the event entrance for check-in.\n"
         . "Arrive 30 minutes early. Carry your college ID.\n\n"
         . "— EventSphere Team";
}
