<?php
// =============================================
// leaderboard.php — Live Score Leaderboard
// Public page — no login required
// Auto-refreshes every 10 seconds via AJAX
// =============================================

require_once 'config.php';
$conn = getConnection();

// Fetch all events
$events = [];
$res = $conn->query("SELECT id, event_name FROM events ORDER BY event_name");
while ($r = $res->fetch_assoc()) $events[] = $r;

// Selected event
$sel_eid = (int)($_GET['event_id'] ?? ($events[0]['id'] ?? 0));

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Live Leaderboard — EventSphere</title>
<link rel="stylesheet" href="css/style.css">
<style>
*,*::before,*::after{box-sizing:border-box}
body{overflow-x:hidden;background:var(--navy)}

/* ── TOP BAR ── */
.lb-topbar{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 32px;
    background:rgba(10,15,44,0.97);
    border-bottom:1px solid rgba(245,197,24,0.2);
    position:sticky;top:0;z-index:100;
    flex-wrap:wrap;gap:12px;
}
.lb-brand{
    font-family:'Playfair Display',serif;
    font-size:1.4rem;font-weight:900;color:var(--gold);
}
.lb-brand span{color:var(--white)}

/* LIVE badge */
.live-badge{
    display:inline-flex;align-items:center;gap:7px;
    background:rgba(255,71,87,0.12);
    border:1px solid rgba(255,71,87,0.35);
    color:#ff4757;border-radius:50px;
    padding:5px 14px;font-size:0.78rem;font-weight:700;
    letter-spacing:1px;text-transform:uppercase;
}
.live-dot{
    width:8px;height:8px;background:#ff4757;
    border-radius:50%;animation:pulse-dot 1.2s ease infinite;
}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(0.7)}}

/* Countdown */
.countdown{
    font-size:0.78rem;color:var(--muted);
    display:flex;align-items:center;gap:6px;
}
.countdown-ring{
    width:28px;height:28px;
    transform:rotate(-90deg);
}
.countdown-circle{
    fill:none;stroke:rgba(245,197,24,0.2);stroke-width:3;
}
.countdown-progress{
    fill:none;stroke:var(--gold);stroke-width:3;
    stroke-dasharray:69.1;stroke-linecap:round;
    transition:stroke-dashoffset 1s linear;
}

/* Event tabs */
.event-tabs{
    display:flex;gap:8px;flex-wrap:wrap;
    padding:20px 32px 0;
}
.ev-tab{
    padding:7px 16px;border-radius:8px;
    border:1px solid rgba(255,255,255,0.1);
    background:transparent;color:var(--muted);
    font-size:0.83rem;font-weight:500;
    cursor:pointer;text-decoration:none;
    transition:all 0.15s;font-family:'DM Sans',sans-serif;
    white-space:nowrap;
}
.ev-tab:hover{border-color:rgba(245,197,24,0.35);color:var(--gold);}
.ev-tab.active{background:var(--gold);color:var(--navy);border-color:var(--gold);font-weight:700;}

/* Main layout */
.lb-main{padding:24px 32px 60px;}

/* Event title */
.event-title{
    font-family:'Playfair Display',serif;
    font-size:1.6rem;font-weight:700;
    color:var(--white);margin-bottom:6px;
}
.event-subtitle{font-size:0.85rem;color:var(--muted);margin-bottom:28px;}

/* ── PODIUM ── */
.podium-row{
    display:flex;align-items:flex-end;justify-content:center;
    gap:16px;margin-bottom:40px;flex-wrap:wrap;
}
.podium-col{text-align:center;flex:1;max-width:220px;min-width:140px;}
.podium-avatar{
    width:64px;height:64px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:1.5rem;font-weight:800;
    margin:0 auto 10px;font-family:'Playfair Display',serif;
    border:3px solid transparent;
    transition:all 0.4s;
}
.podium-avatar.rank1{background:rgba(245,197,24,0.15);border-color:var(--gold);color:var(--gold);}
.podium-avatar.rank2{background:rgba(192,192,192,0.1);border-color:#c0c0c0;color:#c0c0c0;}
.podium-avatar.rank3{background:rgba(205,127,50,0.1);border-color:#cd7f32;color:#cd7f32;}

.podium-medal{font-size:2rem;display:block;margin-bottom:6px;}
.podium-name{font-weight:700;font-size:0.95rem;color:var(--white);margin-bottom:3px;}
.podium-college{font-size:0.75rem;color:var(--muted);margin-bottom:8px;}
.podium-score{
    font-family:'Playfair Display',serif;
    font-size:1.5rem;font-weight:700;
}
.podium-score.rank1{color:var(--gold);}
.podium-score.rank2{color:#c0c0c0;}
.podium-score.rank3{color:#cd7f32;}

.podium-bar{
    border-radius:8px 8px 0 0;
    margin-top:12px;
    transition:height 0.6s ease;
}
.podium-bar.rank1{height:80px;background:rgba(245,197,24,0.15);border:1px solid rgba(245,197,24,0.3);}
.podium-bar.rank2{height:60px;background:rgba(192,192,192,0.08);border:1px solid rgba(192,192,192,0.2);}
.podium-bar.rank3{height:44px;background:rgba(205,127,50,0.08);border:1px solid rgba(205,127,50,0.2);}

/* ── LEADERBOARD TABLE ── */
.lb-card{
    background:var(--card-bg);
    border:1px solid var(--border);
    border-radius:14px;
    overflow:hidden;
}
.lb-card-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;
    border-bottom:1px solid var(--border);
    background:rgba(245,197,24,0.04);
}
.lb-card-header h3{
    font-family:'Playfair Display',serif;
    font-size:1rem;font-weight:600;
}

.lb-table{width:100%;border-collapse:collapse;font-size:0.86rem;}
.lb-table thead th{
    padding:10px 16px;text-align:left;
    font-size:0.72rem;font-weight:700;
    text-transform:uppercase;letter-spacing:0.7px;
    color:var(--muted);
    background:rgba(245,197,24,0.04);
    border-bottom:1px solid var(--border);
    white-space:nowrap;
}
.lb-table thead th.right{text-align:right;}
.lb-table tbody tr{
    border-bottom:1px solid rgba(255,255,255,0.04);
    transition:background 0.15s;
}
.lb-table tbody tr:hover{background:rgba(255,255,255,0.025);}
.lb-table tbody tr.top1{background:rgba(245,197,24,0.06);}
.lb-table tbody tr.top2{background:rgba(192,192,192,0.03);}
.lb-table tbody tr.top3{background:rgba(205,127,50,0.03);}

.lb-table tbody td{
    padding:13px 16px;
    vertical-align:middle;
    color:rgba(255,255,255,0.85);
}
.lb-table tbody td.right{text-align:right;}

/* rank cell */
.rank-cell{font-size:1.2rem;width:48px;text-align:center;}
.rank-num{
    font-size:0.82rem;color:var(--muted);
    font-weight:600;
}

/* score bar inside table */
.score-bar-wrap{display:flex;align-items:center;gap:10px;}
.score-bar-bg{
    flex:1;height:6px;
    background:rgba(255,255,255,0.07);
    border-radius:50px;overflow:hidden;
    min-width:60px;
}
.score-bar-fill{
    height:100%;border-radius:50px;
    transition:width 0.6s ease;
    background:linear-gradient(90deg,var(--gold),#ff6b35);
}
.score-val{
    font-family:'Playfair Display',serif;
    font-size:1rem;font-weight:700;
    color:var(--gold);white-space:nowrap;
    min-width:60px;text-align:right;
}

/* no scores */
.no-scores{
    text-align:center;padding:60px 20px;
    color:var(--muted);font-size:0.9rem;
}
.no-scores .icon{font-size:2.5rem;margin-bottom:12px;}

/* spinner overlay */
#refresh-indicator{
    position:fixed;bottom:24px;right:24px;
    background:rgba(10,15,44,0.9);
    border:1px solid rgba(245,197,24,0.2);
    border-radius:50px;
    padding:8px 18px;
    font-size:0.78rem;color:var(--muted);
    display:flex;align-items:center;gap:8px;
    opacity:0;transition:opacity 0.3s;
    z-index:200;
}
#refresh-indicator.show{opacity:1;}

/* update flash */
@keyframes flash-row{
    0%{background:rgba(245,197,24,0.15)}
    100%{background:transparent}
}
.updated{animation:flash-row 0.8s ease}

/* admin link */
.admin-link{
    display:inline-flex;align-items:center;gap:6px;
    color:var(--muted);font-size:0.78rem;text-decoration:none;
    padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);
    transition:all 0.15s;
}
.admin-link:hover{color:var(--gold);border-color:rgba(245,197,24,0.2);}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="lb-topbar">
  <div class="lb-brand">Event<span>Sphere</span></div>

  <div class="live-badge">
    <span class="live-dot"></span> Live Leaderboard
  </div>

  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <div class="countdown">
      <svg class="countdown-ring" viewBox="0 0 24 24">
        <circle class="countdown-circle" cx="12" cy="12" r="11"/>
        <circle class="countdown-progress" id="countRing" cx="12" cy="12" r="11"/>
      </svg>
      <span id="countText">Refreshing in 10s</span>
    </div>
    <a href="login.php?role=admin" class="admin-link">🛡️ Admin</a>
  </div>
</div>

<!-- EVENT TABS -->
<div class="event-tabs">
  <?php foreach($events as $ev): ?>
  <a href="leaderboard.php?event_id=<?= $ev['id'] ?>"
     class="ev-tab <?= $sel_eid==$ev['id']?'active':'' ?>">
    <?= htmlspecialchars($ev['event_name']) ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- MAIN -->
<div class="lb-main">
  <div id="lb-content">
    <!-- Filled by AJAX -->
    <div class="no-scores"><div class="icon">⏳</div><p>Loading...</p></div>
  </div>
</div>

<!-- Refresh indicator -->
<div id="refresh-indicator">🔄 Updating scores...</div>

<!-- Hidden: current event id -->
<input type="hidden" id="currentEventId" value="<?= $sel_eid ?>">

<script>
const eventId   = document.getElementById('currentEventId').value;
const indicator = document.getElementById('refresh-indicator');
const countText = document.getElementById('countText');
const countRing = document.getElementById('countRing');
const INTERVAL  = 10; // seconds
const CIRCUM    = 2 * Math.PI * 11; // ~69.1

let countdown = INTERVAL;
let prevData  = '';

// ── Fetch leaderboard data via AJAX ──────────────────────────
async function fetchLeaderboard(showIndicator = false) {
    if (showIndicator) {
        indicator.classList.add('show');
    }
    try {
        const res  = await fetch('leaderboard_data.php?event_id=' + eventId + '&t=' + Date.now());
        const html = await res.text();

        if (html !== prevData) {
            document.getElementById('lb-content').innerHTML = html;
            prevData = html;
        }
    } catch(e) {
        console.warn('Leaderboard fetch error:', e);
    }
    setTimeout(() => indicator.classList.remove('show'), 600);
}

// ── Countdown ring ───────────────────────────────────────────
function updateCountdown() {
    countdown--;
    if (countdown <= 0) {
        countdown = INTERVAL;
        fetchLeaderboard(true);
    }
    const offset = CIRCUM * (1 - countdown / INTERVAL);
    countRing.style.strokeDashoffset = offset;
    countText.textContent = `Refreshing in ${countdown}s`;
}

// ── Init ─────────────────────────────────────────────────────
countRing.style.strokeDasharray = CIRCUM;
countRing.style.strokeDashoffset = 0;

fetchLeaderboard(); // Initial load
setInterval(updateCountdown, 1000);
</script>
</body>
</html>
