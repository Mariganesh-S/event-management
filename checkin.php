<?php
// =============================================
// checkin.php — QR Code Scanner & Check-in
// Public page — no login required
// =============================================

require_once 'config.php';

// ── Email credentials ─────────────────────────────────────────
if (!defined('MAIL_USERNAME')) {
    define('MAIL_HOST',      'smtp.gmail.com');
    define('MAIL_PORT',      587);
    define('MAIL_USERNAME',  'theofficialbox234@gmail.com');
    define('MAIL_PASSWORD',  'ynra iuub kmho wyxa');
    define('MAIL_FROM',      'theofficialbox234@gmail.com');
    define('MAIL_FROM_NAME', 'EventSphere');
}

// ── AJAX: Mark attendance ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $conn = getConnection();

    // ── checkin action ────────────────────────────────────────
    if ($_POST['action'] === 'checkin' || $_POST['action'] === 'parse_qr') {
        // Resolve PID
        if ($_POST['action'] === 'parse_qr') {
            $raw = clean($_POST['qr_data'] ?? '');
            $pid = 0;
            if (preg_match('/PID:(\d+)/i', $raw, $m))        $pid = (int)$m[1];
            elseif (preg_match('/EVENTSPHERE:PID:(\d+)/i',$raw,$m)) $pid = (int)$m[1];
            elseif (is_numeric(trim($raw)))                   $pid = (int)trim($raw);
            if (!$pid) {
                echo json_encode(['success'=>false,'msg'=>'Invalid QR code — not an EventSphere ticket.']);
                $conn->close(); exit;
            }
        } else {
            $pid = (int)($_POST['pid'] ?? 0);
        }

        $note = clean($_POST['note'] ?? ($_POST['action']==='parse_qr' ? 'QR Scan' : ''));

        if (!$pid) {
            echo json_encode(['success'=>false,'msg'=>'Invalid participant ID.']);
            $conn->close(); exit;
        }

        // Fetch participant
        $stmt = $conn->prepare("
            SELECT p.*, GROUP_CONCAT(e.event_name SEPARATOR ', ') as events
            FROM participants p
            LEFT JOIN participant_events pe ON p.id=pe.participant_id
            LEFT JOIN events e ON pe.event_id=e.id
            WHERE p.id=? GROUP BY p.id
        ");
        $stmt->bind_param("i",$pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$p) {
            echo json_encode(['success'=>false,'msg'=>"Participant #$pid not found."]);
            $conn->close(); exit;
        }

        $existing = $conn->query("SELECT id,checked_at FROM checkins WHERE participant_id=$pid")->fetch_assoc();

        if ($existing) {
            echo json_encode([
                'success' => false,'already' => true,
                'msg'     => "Already checked in at ".date('h:i A',strtotime($existing['checked_at'])),
                'name'    => $p['name'],'college' => $p['college'],
                'events'  => $p['events'],'pid' => $pid,
            ]);
            $conn->close(); exit;
        }

        $conn->query("INSERT INTO checkins (participant_id, note) VALUES ($pid, '".mysqli_real_escape_string($conn,$note)."')");

        echo json_encode([
            'success'    => true,
            'msg'        => "Checked in successfully!",
            'name'       => $p['name'],
            'college'    => $p['college'],
            'department' => $p['department'],
            'student_id' => $p['student_id'],
            'events'     => $p['events'],
            'pid'        => $pid,
            'time'       => date('h:i A'),
        ]);
        $conn->close(); exit;
    }

    // ── recent check-ins ──────────────────────────────────────
    if ($_POST['action'] === 'recent') {
        $res  = $conn->query("
            SELECT c.*,p.name,p.college,p.student_id,
                   GROUP_CONCAT(e.event_name SEPARATOR ', ') as events
            FROM checkins c
            JOIN participants p ON c.participant_id=p.id
            LEFT JOIN participant_events pe ON p.id=pe.participant_id
            LEFT JOIN events e ON pe.event_id=e.id
            GROUP BY c.id ORDER BY c.checked_at DESC LIMIT 10
        ");
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['rows'=>$rows]);
        $conn->close(); exit;
    }

    // ── stats ─────────────────────────────────────────────────
    if ($_POST['action'] === 'stats') {
        $total   = (int)$conn->query("SELECT COUNT(*) as c FROM participants")->fetch_assoc()['c'];
        $checked = (int)$conn->query("SELECT COUNT(*) as c FROM checkins")->fetch_assoc()['c'];
        echo json_encode(['total'=>$total,'checked'=>$checked,'pending'=>$total-$checked]);
        $conn->close(); exit;
    }

    echo json_encode(['success'=>false,'msg'=>'Unknown action']);
    $conn->close(); exit;
}

// ── Create checkins table ─────────────────────────────────────
$conn = getConnection();
$conn->query("
    CREATE TABLE IF NOT EXISTS checkins (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        participant_id INT NOT NULL,
        note           VARCHAR(100) DEFAULT '',
        checked_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE
    )
");

$total_p   = (int)$conn->query("SELECT COUNT(*) as c FROM participants")->fetch_assoc()['c'];
$checked_p = (int)$conn->query("SELECT COUNT(*) as c FROM checkins")->fetch_assoc()['c'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>QR Check-in — EventSphere</title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box}
body{background:var(--navy)!important;color:var(--white)!important;overflow-x:hidden}

/* ══ SIDEBAR ══ */
.dashboard-layout{display:flex!important;min-height:100vh}
.sidebar{width:240px!important;position:fixed!important;top:0!important;left:0!important;height:100vh!important;display:flex!important;flex-direction:column!important;background:var(--card-bg)!important;border-right:1px solid var(--border)!important;z-index:100!important;overflow:hidden!important}
.sidebar-brand{flex-shrink:0!important;padding:24px 20px 16px!important}
.sidebar-nav{flex:1!important;overflow-y:auto!important;overflow-x:hidden!important;padding:4px 10px 8px!important;min-height:0!important}
.sidebar-nav::-webkit-scrollbar{width:4px}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(245,197,24,.3);border-radius:2px}
.sidebar-nav a{display:flex!important;align-items:center!important;gap:10px!important;padding:9px 10px!important;border-radius:8px!important;font-size:.875rem!important;font-weight:500!important;color:rgba(255,255,255,.7)!important;text-decoration:none!important;margin-bottom:2px!important;transition:all .15s!important;white-space:nowrap!important}
.sidebar-nav a:hover{background:rgba(255,255,255,.06)!important;color:var(--white)!important}
.sidebar-nav a.active{background:rgba(245,197,24,.12)!important;color:var(--gold)!important;font-weight:700!important}
.sidebar-footer{flex-shrink:0!important;padding:14px 16px!important;border-top:1px solid var(--border)!important;background:var(--card-bg)!important}
.main-content{margin-left:240px!important;width:calc(100% - 240px)!important;padding:28px 24px 60px!important;overflow-x:hidden!important;min-height:100vh!important}

/* ══ STATS ══ */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}
.stat-big{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:18px;text-align:center}
.stat-big .val{font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:var(--gold);line-height:1}
.stat-big .lbl{font-size:.74rem;color:var(--muted);margin-top:4px;text-transform:uppercase;letter-spacing:.5px}

/* ══ PROGRESS ══ */
.progress-wrap{margin-bottom:18px}
.progress-info{display:flex;justify-content:space-between;font-size:.78rem;color:var(--muted);margin-bottom:6px}
.progress-track{height:8px;background:rgba(255,255,255,.07);border-radius:50px;overflow:hidden}
.progress-fill{height:100%;border-radius:50px;background:linear-gradient(90deg,var(--success),var(--gold));transition:width .6s ease}

/* ══ MAIN GRID ══ */
.checkin-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}

/* ══ CARDS ══ */
.ci-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.ci-card-hd{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);background:rgba(245,197,24,.03)}
.ci-card-hd h3{font-family:'Playfair Display',serif;font-size:.95rem;font-weight:600;margin:0}
.ci-card-bd{padding:16px 18px}

/* ══ QR SCANNER ══ */
#qr-reader{width:100%!important;border-radius:0!important;overflow:hidden}
#qr-reader video{border-radius:0!important}

/* ══ RESULT BOX ══ */
.result-box{border-radius:10px;padding:14px 16px;display:none;margin:0 18px 14px;border:1px solid}
.result-box.success{background:rgba(0,212,170,.08);border-color:rgba(0,212,170,.3)}
.result-box.error{background:rgba(255,71,87,.08);border-color:rgba(255,71,87,.3)}
.result-box.already{background:rgba(245,197,24,.07);border-color:rgba(245,197,24,.25)}
.res-msg{font-weight:700;font-size:.9rem;margin-bottom:4px}
.res-name{font-size:1rem;font-weight:700;color:var(--white);margin-bottom:2px}
.res-meta{font-size:.78rem;color:var(--muted);line-height:1.7}

/* ══ MANUAL ENTRY ══ */
.pid-input{font-size:1.6rem;font-weight:700;font-family:'Playfair Display',serif;text-align:center;color:var(--gold);letter-spacing:3px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:10px;outline:none;width:100%;transition:border-color .2s}
.pid-input:focus{border-color:var(--gold)}
.btn-row{display:flex;gap:8px;margin-top:10px}
.btn-row .btn{flex:1;justify-content:center}

/* ══ RECENT LIST ══ */
.recent-list{max-height:280px;overflow-y:auto}
.recent-list::-webkit-scrollbar{width:3px}
.recent-list::-webkit-scrollbar-thumb{background:rgba(245,197,24,.3);border-radius:2px}
.recent-row{display:flex;align-items:center;gap:10px;padding:10px 18px;border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s}
.recent-row:hover{background:rgba(255,255,255,.025)}
.recent-avatar{width:36px;height:36px;border-radius:50%;background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;color:var(--success);flex-shrink:0}
.recent-name{font-weight:600;font-size:.84rem}
.recent-sub{font-size:.72rem;color:var(--muted)}
.recent-time{margin-left:auto;font-size:.70rem;color:var(--muted);flex-shrink:0}

/* ══ QR DISPLAY ══ */
.qr-display{text-align:center;padding:14px 0 4px;display:none}
.qr-display img{width:140px;height:140px;border-radius:10px;background:white;padding:6px;display:inline-block}
.qr-pid{font-size:.74rem;color:var(--muted);margin-top:6px}

/* ══ SCAN BTN ══ */
#scanBtn.scanning{background:var(--danger)!important;border-color:var(--danger)!important}

/* ══ HINT ══ */
.hint-bar{padding:10px 18px;font-size:.74rem;color:var(--muted);border-top:1px solid var(--border);background:rgba(255,255,255,.02)}

/* ══ MOBILE NAV ══ */
.mobile-nav{display:none}

/* ══ MOBILE RESPONSIVE ══ */
@media(max-width:900px){
  .sidebar{display:none!important}
  .main-content{margin-left:0!important;width:100%!important;padding:0 0 60px!important}
  .mobile-nav{
    display:flex!important;align-items:center;justify-content:space-between;
    background:var(--card-bg);border-bottom:1px solid var(--border);
    padding:12px 14px;position:sticky;top:0;z-index:100;
  }
  .mobile-nav-brand{font-family:'Playfair Display',serif;font-size:1rem;color:var(--gold);font-weight:700}
  .page-pad{padding:14px 14px 0}
  .stats-row{gap:10px}
  .stat-big{padding:14px 10px}
  .stat-big .val{font-size:1.6rem}
  .checkin-grid{grid-template-columns:1fr}
  .btn-row{flex-direction:column}
}

@media(max-width:480px){
  .stats-row{grid-template-columns:repeat(3,1fr)}
  .stat-big .val{font-size:1.3rem}
  .stat-big .lbl{font-size:.65rem}
  .pid-input{font-size:1.2rem}
}
</style>
</head>
<body>
<div class="dashboard-layout">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-name">EventSphere</div>
    <div class="brand-role">📱 Check-in</div>
  </div>
  <nav class="sidebar-nav">
    <a href="admin/dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="admin/participants.php"><span class="nav-icon">👥</span> Participants</a>
    <a href="admin/results.php"><span class="nav-icon">🏆</span> Results</a>
    <a href="admin/manage_events.php"><span class="nav-icon">🎯</span> Manage Events</a>
    <a href="admin/manage_judges.php"><span class="nav-icon">⚖️</span> Manage Judges</a>
    <a href="admin/alert.php"><span class="nav-icon">📢</span> Announcements</a>
    <a href="export.php"><span class="nav-icon">📥</span> Export</a>
    <a href="checkin.php" class="active"><span class="nav-icon">📱</span> QR Check-in</a>
    <a href="leaderboard.php" target="_blank"><span class="nav-icon">📺</span> Leaderboard</a>
    <a href="index.php"><span class="nav-icon">🏠</span> Home Page</a>
  </nav>
  <div class="sidebar-footer">
    <div style="font-size:.74rem;color:rgba(245,197,24,.6);margin-bottom:8px;">Public Check-in Page</div>
    <a href="login.php?role=admin" class="btn btn-outline btn-sm w-full" style="justify-content:center;">🛡️ Admin Login</a>
  </div>
</aside>

<main class="main-content">

  <!-- Mobile nav -->
  <div class="mobile-nav">
    <span class="mobile-nav-brand">📱 QR Check-in</span>
    <div style="display:flex;gap:6px">
      <a href="announcements.php" class="btn btn-outline btn-sm">📢</a>
      <a href="leaderboard.php"   class="btn btn-outline btn-sm">📺</a>
      <a href="login.php?role=admin" class="btn btn-outline btn-sm">🛡️</a>
    </div>
  </div>

  <div class="page-pad" style="padding:22px 24px 0">
    <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:18px">
      <div>
        <h1 style="margin-bottom:4px">📱 QR Check-in</h1>
        <p>Scan QR or enter Participant ID to mark attendance</p>
      </div>
      <div style="display:flex;gap:8px;flex-shrink:0">
        <a href="leaderboard.php" target="_blank" class="btn btn-outline btn-sm">📺 Live</a>
        <a href="login.php?role=admin" class="btn btn-outline btn-sm">🛡️ Admin</a>
      </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat-big">
        <div class="val" id="statTotal"><?= $total_p ?></div>
        <div class="lbl">Registered</div>
      </div>
      <div class="stat-big">
        <div class="val" style="color:var(--success)" id="statChecked"><?= $checked_p ?></div>
        <div class="lbl">Checked In</div>
      </div>
      <div class="stat-big">
        <div class="val" style="color:var(--danger)" id="statPending"><?= $total_p-$checked_p ?></div>
        <div class="lbl">Pending</div>
      </div>
    </div>

    <!-- PROGRESS BAR -->
    <div class="progress-wrap">
      <div class="progress-info">
        <span>Check-in progress</span>
        <span id="progressPct"><?= $total_p>0?round($checked_p/$total_p*100):0 ?>%</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill" id="progressBar"
          style="width:<?= $total_p>0?round($checked_p/$total_p*100):0 ?>%"></div>
      </div>
    </div>
  </div>

  <div style="padding:0 24px">

    <!-- TOP GRID: Scanner + Manual -->
    <div class="checkin-grid">

      <!-- QR SCANNER -->
      <div class="ci-card">
        <div class="ci-card-hd">
          <h3>📷 QR Scanner</h3>
          <button class="btn btn-primary btn-sm" id="scanBtn" onclick="toggleScanner()">
            Start Camera
          </button>
        </div>
        <div id="qr-reader" style="min-height:220px;background:rgba(0,0,0,.3)"></div>
        <div id="qr-result" class="result-box">
          <div class="res-msg" id="qrMsg"></div>
          <div class="res-name" id="qrName"></div>
          <div class="res-meta" id="qrMeta"></div>
        </div>
        <div class="hint-bar">💡 Point camera at participant QR code — auto check-in</div>
      </div>

      <!-- MANUAL + QR LOOKUP -->
      <div style="display:flex;flex-direction:column;gap:14px">

        <!-- Manual entry -->
        <div class="ci-card">
          <div class="ci-card-hd"><h3>⌨️ Manual Check-in</h3></div>
          <div class="ci-card-bd">
            <p style="font-size:.80rem;color:var(--muted);margin-bottom:12px">
              Enter Participant ID if QR scan fails
            </p>
            <input type="number" class="pid-input" id="manualPid"
              placeholder="ID" min="1"
              onkeydown="if(event.key==='Enter') manualCheckin()">
            <div class="btn-row">
              <button class="btn btn-primary" onclick="manualCheckin()">✅ Check In</button>
              <button class="btn btn-outline" onclick="document.getElementById('manualPid').value=''">✕</button>
            </div>
            <div id="manualResult" class="result-box" style="margin:12px 0 0"></div>
          </div>
        </div>

        <!-- QR Lookup -->
        <div class="ci-card">
          <div class="ci-card-hd"><h3>🔍 View Participant QR</h3></div>
          <div class="ci-card-bd">
            <p style="font-size:.80rem;color:var(--muted);margin-bottom:12px">
              Enter ID to view their QR ticket
            </p>
            <div style="display:flex;gap:8px">
              <input type="number" class="form-control" id="qrLookupId"
                placeholder="Participant ID" min="1" style="flex:1"
                onkeydown="if(event.key==='Enter') showQR()">
              <button class="btn btn-outline" onclick="showQR()">📱 Show</button>
            </div>
            <div class="qr-display" id="qrDisplay">
              <img id="qrImg" src="" alt="QR Code">
              <div class="qr-pid">Participant ID: <span id="qrPidLabel" style="color:var(--gold);font-weight:700"></span></div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- RECENT CHECK-INS -->
    <div class="ci-card" style="margin-bottom:20px">
      <div class="ci-card-hd">
        <h3>🕐 Recent Check-ins</h3>
        <span class="badge badge-green" id="recentCount"><?= $checked_p ?> total</span>
      </div>
      <div class="recent-list" id="recentList">
        <div style="text-align:center;padding:24px;color:var(--muted);font-size:.85rem">Loading…</div>
      </div>
    </div>

  </div><!-- /pad -->
</main>
</div>

<script>
let scanner = null, scanning = false, lastScan = '', scanCooldown = false;

// ── Toggle QR scanner ─────────────────────────────────────────
function toggleScanner() {
    const btn = document.getElementById('scanBtn');
    if (scanning) {
        scanner.stop().then(() => {
            scanner = null; scanning = false;
            btn.textContent = 'Start Camera';
            btn.classList.remove('scanning');
        });
        return;
    }
    btn.textContent = 'Stop Camera';
    btn.classList.add('scanning');
    scanner = new Html5Qrcode("qr-reader");
    scanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 240, height: 240 } },
        onQRSuccess,
        () => {}
    ).catch(err => {
        btn.textContent = 'Start Camera';
        btn.classList.remove('scanning');
        scanning = false;
        showBox('qr-result','error','❌ Camera access denied. Allow camera permission.','','');
    });
    scanning = true;
}

function onQRSuccess(text) {
    if (scanCooldown || text === lastScan) return;
    lastScan = text; scanCooldown = true;
    setTimeout(() => { scanCooldown = false; lastScan = ''; }, 3000);
    post('parse_qr', { qr_data: text }, (d) => {
        if (d.success) {
            showBox('qr-result','success','✅ '+d.msg, d.name, d.college+' · '+d.department+'\nEvents: '+d.events+'\n⏰ '+d.time);
            refresh(); playBeep(true);
        } else if (d.already) {
            showBox('qr-result','already','⚠️ '+d.msg, d.name, d.college);
            playBeep(false);
        } else {
            showBox('qr-result','error','❌ '+d.msg,'','');
        }
    });
}

// ── Manual check-in ───────────────────────────────────────────
function manualCheckin() {
    const pid = parseInt(document.getElementById('manualPid').value);
    if (!pid || pid < 1) {
        showBox('manualResult','error','⚠️ Enter a valid Participant ID','','');
        return;
    }
    post('checkin', { pid }, (d) => {
        if (d.success) {
            showBox('manualResult','success','✅ '+d.msg, d.name, d.college+' · '+d.department+'\nEvents: '+d.events+' · ⏰ '+d.time);
            document.getElementById('manualPid').value = '';
            refresh();
        } else if (d.already) {
            showBox('manualResult','already','⚠️ '+d.msg, d.name, d.college);
        } else {
            showBox('manualResult','error','❌ '+d.msg,'','');
        }
    });
}

// ── Show QR image ─────────────────────────────────────────────
function showQR() {
    const pid = parseInt(document.getElementById('qrLookupId').value);
    if (!pid || pid < 1) return;
    const disp = document.getElementById('qrDisplay');
    document.getElementById('qrImg').src = 'qr_generate.php?pid=' + pid + '&t=' + Date.now();
    document.getElementById('qrPidLabel').textContent = '#' + pid;
    disp.style.display = 'block';
}

// ── Result box helper ─────────────────────────────────────────
function showBox(id, type, msg, name, meta) {
    const box = document.getElementById(id);
    box.className = 'result-box ' + type;
    box.style.display = 'block';
    box.innerHTML = `
        <div class="res-msg" style="color:${type==='success'?'var(--success)':type==='already'?'var(--gold)':'var(--danger)'}">${msg}</div>
        ${name ? `<div class="res-name">${name}</div>` : ''}
        ${meta ? `<div class="res-meta" style="white-space:pre-line">${meta}</div>` : ''}
    `;
}

// ── Stats + Recent refresh ────────────────────────────────────
function refresh() { updateStats(); loadRecent(); }

function updateStats() {
    post('stats', {}, (d) => {
        document.getElementById('statTotal').textContent   = d.total;
        document.getElementById('statChecked').textContent = d.checked;
        document.getElementById('statPending').textContent = d.pending;
        const pct = d.total > 0 ? Math.round(d.checked/d.total*100) : 0;
        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progressPct').textContent = pct + '%';
        document.getElementById('recentCount').textContent = d.checked + ' total';
    });
}

function loadRecent() {
    post('recent', {}, (d) => {
        const list = document.getElementById('recentList');
        if (!d.rows || !d.rows.length) {
            list.innerHTML = '<div style="text-align:center;padding:24px;color:var(--muted);font-size:.85rem">No check-ins yet</div>';
            return;
        }
        list.innerHTML = d.rows.map(r => {
            const ini  = r.name.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2);
            const time = new Date(r.checked_at).toLocaleTimeString('en',{hour:'2-digit',minute:'2-digit'});
            return `<div class="recent-row">
                <div class="recent-avatar">${ini}</div>
                <div style="flex:1;min-width:0">
                    <div class="recent-name">${r.name}</div>
                    <div class="recent-sub">${r.college} · ${r.student_id}</div>
                    ${r.events?`<div class="recent-sub" style="color:var(--gold);font-size:.68rem">${r.events}</div>`:''}
                </div>
                <div class="recent-time">${time}</div>
            </div>`;
        }).join('');
    });
}

// ── POST helper ───────────────────────────────────────────────
function post(action, data, cb) {
    const body = new URLSearchParams({ action, ...data });
    fetch('checkin.php', { method:'POST', body })
        .then(r => r.json()).then(cb).catch(console.warn);
}

// ── Beep sound ────────────────────────────────────────────────
function playBeep(ok) {
    try {
        const ctx = new (window.AudioContext||window.webkitAudioContext)();
        const o   = ctx.createOscillator();
        const g   = ctx.createGain();
        o.connect(g); g.connect(ctx.destination);
        o.frequency.value = ok ? 880 : 440;
        o.type = 'sine';
        g.gain.setValueAtTime(.3, ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime+.3);
        o.start(); o.stop(ctx.currentTime+.3);
    } catch(e) {}
}

// ── Touch fix ─────────────────────────────────────────────────
document.querySelectorAll('button').forEach(btn => {
    btn.addEventListener('touchstart', e => { e.preventDefault(); btn.click(); }, { passive: false });
});

// ── Init ─────────────────────────────────────────────────────
loadRecent();
setInterval(updateStats, 5000);
</script>
</body>
</html>
