<?php
require_once '../config.php';
if (!isset($_SESSION['admin_id'])) redirect('../login.php?role=admin');

// ── PHPMailer — manual path ───────────────────────────────────
// Download PHPMailer from https://github.com/PHPMailer/PHPMailer
// Place the src/ folder at: your_project/phpmailer/src/
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once '../phpmailer/src/Exception.php';
require_once '../phpmailer/src/PHPMailer.php';
require_once '../phpmailer/src/SMTP.php';

// ── Gmail SMTP config — change these ─────────────────────────
define('SMTP_USER', 'theofficialbox234@gmail.com');   // Your Gmail address
define('SMTP_PASS', 'ynra iuub kmho wyxa'); // Gmail App Password (not account password)
define('SMTP_FROM', 'theofficialbox234@gmail.com');
define('SMTP_NAME', 'EventSphere');

$conn = getConnection();

// Create table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS announcements (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        title      VARCHAR(200) NOT NULL,
        message    TEXT NOT NULL,
        type       ENUM('info','warning','success','danger') DEFAULT 'info',
        event_id   INT DEFAULT NULL,
        is_active  TINYINT(1) DEFAULT 1,
        emails_sent INT DEFAULT 0,
        created_by VARCHAR(50) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
    )
");

$msg = $msg_type = '';

// ── Delete ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM announcements WHERE id=" . (int)$_GET['delete']);
    $msg = "Announcement deleted."; $msg_type = "success";
}

// ── Toggle active ─────────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $conn->query("UPDATE announcements SET is_active = 1 - is_active WHERE id=$tid");
    $msg = "Status updated."; $msg_type = "success";
}

// ── Send announcement + email ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_alert'])) {
    $title    = clean($_POST['title']   ?? '');
    $message  = clean($_POST['message'] ?? '');
    $type     = clean($_POST['type']    ?? 'info');
    $event_id = (int)($_POST['event_id'] ?? 0);
    $by       = htmlspecialchars($_SESSION['admin_user'] ?? 'admin');
    $send_email = isset($_POST['send_email']);

    if (!$title || !$message) {
        $msg = "Please fill in Title and Message."; $msg_type = "danger";
    } else {
        $valid_types = ['info','warning','success','danger'];
        if (!in_array($type, $valid_types)) $type = 'info';

        // Save to DB
        $stmt = $conn->prepare(
            "INSERT INTO announcements (title, message, type, event_id, created_by) VALUES (?, ?, ?, ?, ?)"
        );
        $ev_id = $event_id ?: null;
        $stmt->bind_param("sssis", $title, $message, $type, $ev_id, $by);

        if ($stmt->execute()) {
            $ann_id     = $conn->insert_id;
            $email_sent = 0;
            $email_fail = 0;

            // Send emails if checkbox checked
            if ($send_email) {
                // Fetch participant emails
                if ($event_id) {
                    $eq = "SELECT DISTINCT p.email, p.name FROM participants p
                           JOIN participant_events pe ON p.id = pe.participant_id
                           WHERE pe.event_id = $event_id AND p.email != ''";
                } else {
                    $eq = "SELECT email, name FROM participants WHERE email != ''";
                }
                $eres = $conn->query($eq);
                $recipients = [];
                while ($r = $eres->fetch_assoc()) $recipients[] = $r;

                if (count($recipients) > 0) {
                    foreach ($recipients as $rec) {
                        try {
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = SMTP_USER;
                            $mail->Password   = SMTP_PASS;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;

                            $mail->setFrom(SMTP_FROM, SMTP_NAME);
                            $mail->addAddress($rec['email'], $rec['name']);
                            $mail->isHTML(true);
                            $mail->Subject = "[EventSphere] $title";
                            $mail->Body    = emailTemplate($title, $message, $type, $event_id ? ($event_id) : null, $conn);
                            $mail->AltBody = "$title\n\n$message";
                            $mail->send();
                            $email_sent++;
                        } catch (Exception $e) {
                            $email_fail++;
                        }
                    }
                    // Update sent count
                    $conn->query("UPDATE announcements SET emails_sent=$email_sent WHERE id=$ann_id");
                }

                if ($email_fail > 0)
                    $msg = "✅ Saved! Emails: $email_sent sent, $email_fail failed. Check Gmail App Password.";
                elseif ($email_sent > 0)
                    $msg = "✅ Announcement sent! $email_sent email(s) delivered.";
                else
                    $msg = "✅ Saved! No participant emails found.";
            } else {
                $msg = "✅ Announcement saved (no email sent).";
            }
            $msg_type = "success";
        } else {
            $msg = "Failed to save announcement."; $msg_type = "danger";
        }
        $stmt->close();
    }
}

// ── Email HTML template ───────────────────────────────────────
function emailTemplate($title, $message, $type, $event_id, $conn) {
    $colors = [
        'info'    => '#6495ed',
        'warning' => '#f5c518',
        'success' => '#00d4aa',
        'danger'  => '#ff4757',
    ];
    $icons = ['info'=>'ℹ️','warning'=>'⚠️','success'=>'✅','danger'=>'🚨'];
    $color = $colors[$type] ?? '#6495ed';
    $icon  = $icons[$type]  ?? 'ℹ️';

    $event_label = '';
    if ($event_id) {
        $er = $conn->query("SELECT event_name FROM events WHERE id=$event_id");
        if ($er && $ev = $er->fetch_assoc()) $event_label = $ev['event_name'];
    }

    $msg_html = nl2br(htmlspecialchars($message));
    $ev_line  = $event_label
        ? "<p style='margin:0 0 6px;font-size:13px;color:#888;'>🎯 Event: <strong style='color:#fff;'>".htmlspecialchars($event_label)."</strong></p>"
        : "<p style='margin:0 0 6px;font-size:13px;color:#888;'>📢 All Participants</p>";

    return "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#0d1235;font-family:Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#0d1235;padding:32px 16px;'>
    <tr><td align='center'>
      <table width='560' cellpadding='0' cellspacing='0' style='background:#151b3a;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);max-width:560px;width:100%;'>
        <!-- Header -->
        <tr>
          <td style='background:{$color}18;border-bottom:1px solid rgba(255,255,255,0.08);padding:24px 28px;'>
            <p style='margin:0;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#888;margin-bottom:6px;'>EventSphere Announcement</p>
            <h1 style='margin:0;font-size:20px;color:#fff;'>{$icon} ".htmlspecialchars($title)."</h1>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style='padding:24px 28px;'>
            {$ev_line}
            <div style='background:rgba(255,255,255,0.04);border-left:4px solid {$color};border-radius:0 8px 8px 0;padding:14px 18px;margin:14px 0;'>
              <p style='margin:0;font-size:15px;color:rgba(255,255,255,0.85);line-height:1.7;'>{$msg_html}</p>
            </div>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style='padding:16px 28px;border-top:1px solid rgba(255,255,255,0.06);'>
            <p style='margin:0;font-size:12px;color:#555;'>This is an automated message from EventSphere. Please do not reply.</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";
}

// ── Fetch events ──────────────────────────────────────────────
$events = [];
$res = $conn->query("SELECT id, event_name FROM events ORDER BY event_name");
while ($r = $res->fetch_assoc()) $events[] = $r;

// ── Fetch announcements ───────────────────────────────────────
$announcements = [];
$res = $conn->query("
    SELECT a.*, e.event_name
    FROM announcements a
    LEFT JOIN events e ON a.event_id = e.id
    ORDER BY a.created_at DESC
");
while ($r = $res->fetch_assoc()) $announcements[] = $r;

$conn->close();

$type_cfg = [
    'info'    => ['color'=>'#6495ed','bg'=>'rgba(100,149,237,.1)','border'=>'rgba(100,149,237,.3)','icon'=>'ℹ️', 'label'=>'Info'],
    'warning' => ['color'=>'#f5c518','bg'=>'rgba(245,197,24,.08)','border'=>'rgba(245,197,24,.25)','icon'=>'⚠️', 'label'=>'Warning'],
    'success' => ['color'=>'#00d4aa','bg'=>'rgba(0,212,170,.08)', 'border'=>'rgba(0,212,170,.25)', 'icon'=>'✅','label'=>'Success'],
    'danger'  => ['color'=>'#ff4757','bg'=>'rgba(255,71,87,.08)', 'border'=>'rgba(255,71,87,.25)', 'icon'=>'🚨','label'=>'Urgent'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Announcements — Admin | EventSphere</title>
<link rel="stylesheet" href="../css/style.css">
<style>
*,*::before,*::after{box-sizing:border-box}
body{background:var(--navy)!important;color:var(--white)!important}
.sidebar{width:240px}
.main-content{margin-left:240px;width:calc(100% - 240px);padding:28px 24px 60px;overflow-x:hidden}

.alert-grid{display:grid;grid-template-columns:420px 1fr;gap:24px;align-items:start}

.compose-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;position:sticky;top:24px}
.compose-header{padding:18px 22px;border-bottom:1px solid var(--border);background:rgba(245,197,24,.04)}
.compose-header h3{font-family:'Playfair Display',serif;font-size:1rem;font-weight:600}
.compose-body{padding:22px}

.type-selector{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:6px}
.type-opt input{display:none}
.type-opt label{
    display:flex;flex-direction:column;align-items:center;gap:4px;
    padding:10px 6px;border-radius:8px;cursor:pointer;
    border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.03);
    font-size:.75rem;font-weight:600;transition:all .2s;text-align:center;
}
.type-opt label .t-icon{font-size:1.2rem}
.type-opt.t-info    input:checked+label{border-color:#6495ed;background:rgba(100,149,237,.12);color:#6495ed;border-width:2px}
.type-opt.t-warning input:checked+label{border-color:var(--gold);background:rgba(245,197,24,.1);color:var(--gold);border-width:2px}
.type-opt.t-success input:checked+label{border-color:var(--success);background:rgba(0,212,170,.1);color:var(--success);border-width:2px}
.type-opt.t-danger  input:checked+label{border-color:var(--danger);background:rgba(255,71,87,.1);color:var(--danger);border-width:2px}

.compose-body textarea.form-control{resize:vertical;min-height:100px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:var(--white)}
.compose-body textarea.form-control:focus{border-color:var(--gold)}
.compose-body select.form-control option{background:#0d1235}

/* Email toggle */
.email-toggle{
    display:flex;align-items:center;gap:10px;
    background:rgba(0,212,170,.06);border:1px solid rgba(0,212,170,.2);
    border-radius:10px;padding:12px 14px;margin:14px 0;cursor:pointer;
}
.email-toggle input{width:16px;height:16px;cursor:pointer;accent-color:var(--success)}
.email-toggle-label{font-size:.84rem;font-weight:600;color:var(--success)}
.email-toggle-sub{font-size:.74rem;color:var(--muted);margin-top:2px}

.preview-box{border-radius:10px;padding:14px 16px;margin-top:14px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);display:none}
.preview-box.show{display:block}

.ann-card{border-radius:12px;padding:18px 20px;margin-bottom:14px;border:1px solid rgba(255,255,255,.08);background:var(--card-bg);transition:all .2s}
.ann-card:hover{transform:translateY(-1px);border-color:rgba(255,255,255,.15)}
.ann-card.inactive{opacity:.5}
.ann-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:10px}
.ann-icon{font-size:1.4rem;flex-shrink:0;margin-top:2px}
.ann-title{font-weight:700;font-size:.92rem;margin-bottom:3px}
.ann-meta{font-size:.74rem;color:var(--muted)}
.ann-msg{font-size:.84rem;line-height:1.6;padding:10px 14px;border-radius:8px;background:rgba(255,255,255,.03);border-left:3px solid;margin-bottom:12px}
.ann-footer{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ann-badge{font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:50px;border:1px solid}
.ann-actions{margin-left:auto;display:flex;gap:6px}

.public-link-card{background:rgba(0,212,170,.06);border:1px solid rgba(0,212,170,.2);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.public-link-url{font-family:monospace;font-size:.80rem;color:var(--success);background:rgba(0,0,0,.2);border-radius:6px;padding:6px 12px;border:1px solid rgba(0,212,170,.2);flex:1;min-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* SMTP config notice */
.smtp-notice{background:rgba(245,197,24,.06);border:1px solid rgba(245,197,24,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.80rem;color:var(--muted)}
.smtp-notice strong{color:var(--gold)}

@media(max-width:900px){
    .sidebar{display:none}
    .main-content{margin-left:0;width:100%;padding:16px 14px 60px}
    .alert-grid{grid-template-columns:1fr}
    .compose-card{position:static}
    .type-selector{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>
<div class="dashboard-layout">

<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-name">EventSphere</div>
    <div class="brand-role">🛡️ Admin Panel</div>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="participants.php"><span class="nav-icon">👥</span> Participants</a>
    <a href="results.php"><span class="nav-icon">🏆</span> Results</a>
    <a href="manage_events.php"><span class="nav-icon">🎯</span> Manage Events</a>
    <a href="manage_judges.php"><span class="nav-icon">⚖️</span> Manage Judges</a>
    <a href="alert.php" class="active"><span class="nav-icon">📢</span> Announcements</a>
    <a href="../export.php"><span class="nav-icon">📥</span> Export</a>
    <a href="../checkin.php"><span class="nav-icon">📱</span> QR Check-in</a>
    <a href="../leaderboard.php" target="_blank"><span class="nav-icon">📺</span> Leaderboard</a>
    <a href="../index.php"><span class="nav-icon">🏠</span> Home Page</a>
  </nav>
  <div class="sidebar-footer">
    <div style="font-size:.80rem;color:var(--muted);margin-bottom:10px;">
      Logged in as <strong style="color:var(--white)"><?= htmlspecialchars($_SESSION['admin_user']) ?></strong>
    </div>
    <a href="../logout.php?role=admin" class="btn btn-outline btn-sm w-full" style="justify-content:center;">🚪 Logout</a>
  </div>
</aside>

<main class="main-content">

  <div class="page-header">
    <h1>📢 Announcements</h1>
    <p>Send event alerts and email notifications to participants</p>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:16px;"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- SMTP setup notice -->
 <!-- <div class="smtp-notice">
    <strong>⚙️ Gmail Setup Required:</strong> Open <code>alert.php</code> and set
    <code>SMTP_USER</code> and <code>SMTP_PASS</code> at the top.
    Use a <strong>Gmail App Password</strong> (not your account password) —
    <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--gold);">generate one here</a>.
    Also ensure PHPMailer is at <code>../phpmailer/src/</code>.
  </div> -->

  <!-- Public link -->
  <div class="public-link-card">
    <div>
      <div style="font-weight:700;margin-bottom:4px;color:var(--success)">📺 Participant Alert Screen</div>
      <div style="font-size:.78rem;color:var(--muted)">Share this link — announcements appear automatically</div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <span class="public-link-url" id="pubUrl">
        <?= (isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '') ?>/event_management_system/announcements.php
      </span>
      <button class="btn btn-success btn-sm" onclick="copyLink()">📋 Copy</button>
      <a href="../announcements.php" target="_blank" class="btn btn-outline btn-sm">🔗 Open</a>
    </div>
  </div>

  <div class="alert-grid">

    <!-- COMPOSE -->
    <div class="compose-card">
      <div class="compose-header">
        <h3>📝 New Announcement</h3>
      </div>
      <div class="compose-body">
        <form method="POST">
          <input type="hidden" name="send_alert" value="1">

          <div class="form-group">
            <label>Alert Type</label>
            <div class="type-selector">
              <?php foreach ($type_cfg as $key => $cfg): ?>
              <div class="type-opt t-<?= $key ?>">
                <input type="radio" name="type" id="type_<?= $key ?>" value="<?= $key ?>"
                  <?= ($key==='info'?'checked':'') ?> onchange="updatePreview()">
                <label for="type_<?= $key ?>">
                  <span class="t-icon"><?= $cfg['icon'] ?></span>
                  <?= $cfg['label'] ?>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-group">
            <label>Target</label>
            <select name="event_id" class="form-control" onchange="updatePreview()">
              <option value="0">📢 All Participants (Broadcast)</option>
              <?php foreach ($events as $ev): ?>
              <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['event_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Title <span style="color:var(--danger)">*</span></label>
            <input type="text" name="title" class="form-control"
              placeholder="e.g. Solo Singing — Starting in 15 minutes!"
              oninput="updatePreview()" maxlength="200">
          </div>

          <div class="form-group">
            <label>Message <span style="color:var(--danger)">*</span></label>
            <textarea name="message" class="form-control"
              placeholder="e.g. All participants please report to Hall A immediately."
              oninput="updatePreview()"></textarea>
          </div>

          <!-- Email toggle -->
          <label class="email-toggle">
            <input type="checkbox" name="send_email" id="sendEmailChk" value="1">
            <div>
              <div class="email-toggle-label">📧 Also send email to participants</div>
              <div class="email-toggle-sub">Requires Gmail SMTP config above</div>
            </div>
          </label>

          <!-- Live preview -->
          <div class="preview-box" id="previewBox">
            <div style="font-size:.70rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:8px;">Preview</div>
            <div id="previewAlert" style="border-radius:10px;padding:14px 16px;border-left:4px solid;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <span id="previewIcon" style="font-size:1.1rem;"></span>
                <strong id="previewTitle" style="font-size:.92rem;"></strong>
              </div>
              <div id="previewMsg" style="font-size:.82rem;color:var(--muted);line-height:1.6;"></div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-lg w-full" style="justify-content:center;margin-top:16px;">
            🚀 Send Announcement
          </button>
        </form>
      </div>
    </div>

    <!-- ANNOUNCEMENTS LIST -->
    <div>
      <div style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
        <span>Sent Announcements</span>
        <span class="badge badge-gold"><?= count($announcements) ?></span>
      </div>

      <?php if (empty($announcements)): ?>
      <div style="text-align:center;padding:48px;color:var(--muted);background:var(--card-bg);border:1px solid var(--border);border-radius:14px;">
        <div style="font-size:2.5rem;margin-bottom:12px;">📢</div>
        <p>No announcements yet. Send your first alert!</p>
      </div>

      <?php else:
        foreach ($announcements as $a):
          $cfg = $type_cfg[$a['type']] ?? $type_cfg['info'];
      ?>
      <div class="ann-card <?= !$a['is_active']?'inactive':'' ?>"
           style="border-left:4px solid <?= $cfg['color'] ?>;">
        <div class="ann-header">
          <span class="ann-icon"><?= $cfg['icon'] ?></span>
          <div style="flex:1;min-width:0">
            <div class="ann-title"><?= htmlspecialchars($a['title']) ?></div>
            <div class="ann-meta">
              <?= date('d M Y, h:i A', strtotime($a['created_at'])) ?>
              &bull; by <?= htmlspecialchars($a['created_by']) ?>
              <?php if ($a['event_name']): ?>
                &bull; 🎯 <?= htmlspecialchars($a['event_name']) ?>
              <?php else: ?>
                &bull; 📢 All Participants
              <?php endif; ?>
              <?php if (!empty($a['emails_sent']) && $a['emails_sent'] > 0): ?>
                &bull; <span style="color:var(--success)">📧 <?= $a['emails_sent'] ?> email(s) sent</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="ann-msg" style="border-color:<?= $cfg['color'] ?>;color:rgba(255,255,255,.8);">
          <?= nl2br(htmlspecialchars($a['message'])) ?>
        </div>

        <div class="ann-footer">
          <span class="ann-badge" style="color:<?= $cfg['color'] ?>;border-color:<?= $cfg['color'] ?>;background:<?= $cfg['bg'] ?>">
            <?= $cfg['icon'] ?> <?= $cfg['label'] ?>
          </span>
          <span class="badge <?= $a['is_active']?'badge-green':'badge-red' ?>" style="font-size:.70rem">
            <?= $a['is_active']?'● Active':'○ Hidden' ?>
          </span>
          <div class="ann-actions">
            <a href="alert.php?toggle=<?= $a['id'] ?>" class="btn btn-outline btn-sm">
              <?= $a['is_active']?'👁️ Hide':'👁️ Show' ?>
            </a>
            <a href="alert.php?delete=<?= $a['id'] ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this announcement?')">🗑️</a>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>
</main>
</div>

<script>
const typeConfig = <?= json_encode($type_cfg) ?>;

function updatePreview() {
    const type  = document.querySelector('input[name=type]:checked')?.value || 'info';
    const title = document.querySelector('input[name=title]').value.trim();
    const msg   = document.querySelector('textarea[name=message]').value.trim();
    const cfg   = typeConfig[type];
    const box   = document.getElementById('previewBox');
    if (!title && !msg) { box.classList.remove('show'); return; }
    box.classList.add('show');
    const alert = document.getElementById('previewAlert');
    alert.style.background  = cfg.bg;
    alert.style.borderColor = cfg.color;
    document.getElementById('previewIcon').textContent  = cfg.icon;
    document.getElementById('previewTitle').textContent = title || 'Title…';
    document.getElementById('previewTitle').style.color = cfg.color;
    document.getElementById('previewMsg').textContent   = msg || 'Message…';
}

function copyLink() {
    const url = document.getElementById('pubUrl').textContent.trim();
    navigator.clipboard.writeText(url).then(() => {
        const btn = event.target;
        btn.textContent = '✅ Copied!';
        setTimeout(() => btn.textContent = '📋 Copy', 2000);
    });
}
</script>
</body>
</html>
