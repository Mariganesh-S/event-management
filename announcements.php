<?php
// =============================================
// announcements.php — Public Alert Screen
// Participants see live announcements
// Auto-refreshes every 15 seconds
// No login required
// =============================================

require_once 'config.php';
$conn = getConnection();

// Create table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info','warning','success','danger') DEFAULT 'info',
        event_id INT DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_by VARCHAR(50) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
    )
");

// ── AJAX: return announcements as JSON ────────────────────────
if (isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    $rows = [];
    $res = $conn->query("
        SELECT a.*, e.event_name
        FROM announcements a
        LEFT JOIN events e ON a.event_id = e.id
        WHERE a.is_active = 1
        ORDER BY a.created_at DESC
    ");
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $conn->close();
    echo json_encode(['count'=>count($rows), 'items'=>$rows, 'ts'=>time()]);
    exit;
}

$conn->close();

$type_cfg = [
    'info'    => ['color'=>'#6495ed','bg'=>'rgba(100,149,237,.1)','border'=>'rgba(100,149,237,.3)','icon'=>'ℹ️','label'=>'Info'],
    'warning' => ['color'=>'#f5c518','bg'=>'rgba(245,197,24,.08)','border'=>'rgba(245,197,24,.3)','icon'=>'⚠️','label'=>'Warning'],
    'success' => ['color'=>'#00d4aa','bg'=>'rgba(0,212,170,.08)','border'=>'rgba(0,212,170,.3)','icon'=>'✅','label'=>'Notice'],
    'danger'  => ['color'=>'#ff4757','bg'=>'rgba(255,71,87,.1)','border'=>'rgba(255,71,87,.3)','icon'=>'🚨','label'=>'Urgent'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Announcements — EventSphere</title>
<link rel="stylesheet" href="css/style.css">
<style>
/* ── Reset & base ── */
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0;background:var(--navy);color:var(--white);min-height:100vh}

/* ── Top bar ── */
.topbar{
    position:sticky;top:0;z-index:100;
    background:rgba(10,15,44,.97);
    backdrop-filter:blur(10px);
    border-bottom:1px solid rgba(245,197,24,.2);
    padding:12px 20px;
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    flex-wrap:wrap;
}
.topbar-brand{
    font-family:'Playfair Display',serif;
    font-size:1.2rem;font-weight:900;
    color:var(--gold);letter-spacing:1px;
}
.topbar-title{
    font-size:.78rem;color:var(--muted);
    margin-top:2px;letter-spacing:.5px;
}
.live-badge{
    display:flex;align-items:center;gap:6px;
    background:rgba(255,71,87,.1);border:1px solid rgba(255,71,87,.35);
    border-radius:50px;padding:5px 14px;
    font-size:.76rem;font-weight:700;color:#ff4757;letter-spacing:1px;
    white-space:nowrap;
}
.live-dot{width:8px;height:8px;border-radius:50%;background:#ff4757;animation:blink 1s ease infinite}
@keyframes blink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.7)}}

/* Timer ring */
.timer-wrap{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--muted)}
.timer-svg{transform:rotate(-90deg);width:28px;height:28px}
.timer-bg{fill:none;stroke:rgba(245,197,24,.12);stroke-width:3}
.timer-fill{fill:none;stroke:var(--gold);stroke-width:3;stroke-linecap:round;stroke-dasharray:72;transition:stroke-dashoffset 1s linear}

/* ── Page body ── */
.page-body{
    max-width:720px;margin:0 auto;
    padding:24px 16px 80px;
}

/* ── Announcement card ── */
.ann-card{
    border-radius:14px;padding:20px 22px;
    margin-bottom:16px;
    border-left:5px solid;
    transition:all .3s;
    animation:slideIn .4s ease both;
}
@keyframes slideIn{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}

.ann-top{display:flex;align-items:flex-start;gap:12px;margin-bottom:10px}
.ann-icon{font-size:1.6rem;flex-shrink:0;line-height:1;margin-top:2px}
.ann-title{
    font-family:'Playfair Display',serif;
    font-size:1.05rem;font-weight:700;
    margin-bottom:3px;line-height:1.3;
}
.ann-target{
    font-size:.72rem;color:var(--muted);
    display:flex;align-items:center;gap:4px;
}
.ann-target .pill{
    display:inline-block;border-radius:50px;
    padding:1px 8px;font-size:.68rem;font-weight:700;
    border:1px solid rgba(255,255,255,.1);
    background:rgba(255,255,255,.06);
}
.ann-msg{
    font-size:.90rem;line-height:1.7;
    color:rgba(255,255,255,.85);
    margin-bottom:12px;
}
.ann-footer-row{
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:8px;
    padding-top:10px;border-top:1px solid rgba(255,255,255,.06);
}
.ann-type-badge{
    display:inline-flex;align-items:center;gap:5px;
    font-size:.72rem;font-weight:700;
    padding:3px 10px;border-radius:50px;border:1px solid;
}
.ann-time{font-size:.72rem;color:var(--muted)}

/* ── NEW badge (for recent) ── */
.new-badge{
    display:inline-block;
    background:var(--danger);color:white;
    font-size:.60rem;font-weight:700;
    padding:2px 6px;border-radius:4px;
    margin-left:6px;vertical-align:middle;
    animation:pulse-badge .8s ease infinite;
}
@keyframes pulse-badge{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}

/* ── Empty state ── */
.empty-state{
    text-align:center;padding:80px 20px;
    color:var(--muted);
}
.empty-icon{font-size:3.5rem;margin-bottom:16px;animation:float 3s ease infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.empty-title{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;margin-bottom:8px;color:rgba(255,255,255,.5)}
.empty-sub{font-size:.84rem}

/* ── Update toast ── */
.toast{
    position:fixed;bottom:24px;right:16px;
    background:rgba(0,212,170,.12);border:1px solid rgba(0,212,170,.3);
    color:#00d4aa;border-radius:8px;
    padding:8px 16px;font-size:.78rem;font-weight:600;
    opacity:0;transform:translateY(8px);
    transition:all .3s;z-index:999;
    pointer-events:none;
}
.toast.show{opacity:1;transform:translateY(0)}

/* ── Count badge ── */
.count-pill{
    display:inline-flex;align-items:center;gap:4px;
    background:rgba(245,197,24,.1);border:1px solid rgba(245,197,24,.25);
    border-radius:50px;padding:4px 12px;
    font-size:.78rem;color:var(--gold);font-weight:600;
    margin-bottom:16px;
}

/* ── Floating admin link ── */
.fab{
    position:fixed;bottom:20px;left:16px;
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:50px;padding:8px 16px;
    font-size:.75rem;color:var(--muted);text-decoration:none;
    display:flex;align-items:center;gap:6px;
    transition:all .2s;z-index:50;
}
.fab:hover{border-color:rgba(245,197,24,.3);color:var(--gold)}

/* ── Loading skeleton ── */
.skeleton{
    background:linear-gradient(90deg,rgba(255,255,255,.04) 25%,rgba(255,255,255,.08) 50%,rgba(255,255,255,.04) 75%);
    background-size:200% 100%;
    animation:shimmer 1.5s infinite;
    border-radius:8px;height:20px;margin-bottom:8px;
}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* ── Mobile ── */
@media(max-width:480px){
    .topbar{padding:10px 14px}
    .topbar-brand{font-size:1rem}
    .ann-card{padding:16px}
    .ann-title{font-size:.95rem}
    .ann-msg{font-size:.85rem}
    .timer-wrap span{display:none}
}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <div>
    <div class="topbar-brand">⚡ EventSphere</div>
    <div class="topbar-title">📢 Live Announcements</div>
  </div>
  <div class="live-badge">
    <span class="live-dot"></span> LIVE
  </div>
  <div class="timer-wrap">
    <svg class="timer-svg" viewBox="0 0 24 24">
      <circle class="timer-bg" cx="12" cy="12" r="11"/>
      <circle class="timer-fill" id="timerRing" cx="12" cy="12" r="11" style="stroke-dashoffset:0"/>
    </svg>
    <span id="timerText">15s</span>
  </div>
</div>

<!-- PAGE BODY -->
<div class="page-body">

  <!-- Count -->
  <div id="countWrap" style="display:none">
    <div class="count-pill" id="countPill">📢 <span id="countNum">0</span> active announcement(s)</div>
  </div>

  <!-- Announcements container -->
  <div id="annContainer">
    <!-- Loading skeleton -->
    <div id="skeleton" style="background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:20px">
      <div class="skeleton" style="width:40%;height:14px"></div>
      <div class="skeleton" style="width:70%;height:22px;margin-top:10px"></div>
      <div class="skeleton" style="width:90%;height:14px;margin-top:8px"></div>
      <div class="skeleton" style="width:60%;height:14px;margin-top:6px"></div>
    </div>
  </div>

  <!-- No announcements -->
  <div id="emptyState" style="display:none">
    <div class="empty-state">
      <div class="empty-icon">📢</div>
      <div class="empty-title">No Announcements Yet</div>
      <div class="empty-sub">Stay tuned! Event updates will appear here automatically.</div>
    </div>
  </div>

</div>

<!-- Toast -->
<div class="toast" id="toast">🔄 Updated!</div>

<!-- Admin link -->
<a href="login.php?role=admin" class="fab">🛡️ Admin</a>

<script>
const INTERVAL = 15;
const CIRCUM   = 2 * Math.PI * 11; // ~69.1

const timerRing  = document.getElementById('timerRing');
const timerText  = document.getElementById('timerText');
const toast      = document.getElementById('toast');
const container  = document.getElementById('annContainer');
const emptyState = document.getElementById('emptyState');
const countWrap  = document.getElementById('countWrap');
const countPill  = document.getElementById('countPill');
const countNum   = document.getElementById('countNum');
const skeleton   = document.getElementById('skeleton');

timerRing.style.strokeDasharray = CIRCUM;

let countdown = INTERVAL;
let prevCount = -1;
let toastTimer;

const typeCfg = <?= json_encode($type_cfg) ?>;

// ── Fetch announcements ───────────────────────────────────────
async function fetchAnnouncements(first = false) {
    try {
        const res  = await fetch('announcements.php?fetch=1&t=' + Date.now());
        const data = await res.json();
        renderAnnouncements(data, first);
    } catch(e) { console.warn(e); }
}

// ── Render ────────────────────────────────────────────────────
function renderAnnouncements(data, first) {
    skeleton.style.display = 'none';

    if (data.count === 0) {
        container.innerHTML = '';
        emptyState.style.display = 'block';
        countWrap.style.display  = 'none';
        return;
    }

    emptyState.style.display = 'none';
    countWrap.style.display  = 'block';
    countNum.textContent     = data.count;

    // Detect new items
    const isNew = !first && data.count !== prevCount;
    prevCount = data.count;

    // Build HTML
    const now = Date.now() / 1000;
    let html = '';

    data.items.forEach((a, idx) => {
        const cfg      = typeCfg[a.type] || typeCfg.info;
        const isRecent = (now - new Date(a.created_at).getTime()/1000) < 120; // 2 min
        const timeStr  = formatTime(a.created_at);

        html += `
        <div class="ann-card" style="background:${cfg.bg};border-left-color:${cfg.color};border:1px solid ${cfg.border};border-left-width:5px;"
             data-id="${a.id}">
          <div class="ann-top">
            <span class="ann-icon">${cfg.icon}</span>
            <div style="flex:1;min-width:0">
              <div class="ann-title" style="color:${cfg.color}">
                ${escHtml(a.title)}
                ${isRecent ? '<span class="new-badge">NEW</span>' : ''}
              </div>
              <div class="ann-target">
                ${a.event_name
                    ? `<span class="pill">🎯 ${escHtml(a.event_name)}</span>`
                    : `<span class="pill">📢 All Participants</span>`}
              </div>
            </div>
          </div>
          <div class="ann-msg">${escHtml(a.message).replace(/\n/g,'<br>')}</div>
          <div class="ann-footer-row">
            <span class="ann-type-badge" style="color:${cfg.color};border-color:${cfg.color};background:${cfg.bg}">
              ${cfg.icon} ${cfg.label}
            </span>
            <span class="ann-time">🕐 ${timeStr}</span>
          </div>
        </div>`;
    });

    container.innerHTML = html;

    // Show update toast if new items
    if (isNew) showToast();
}

// ── Time format ───────────────────────────────────────────────
function formatTime(ts) {
    const d   = new Date(ts);
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60)  return 'just now';
    if (diff < 3600) return Math.floor(diff/60) + ' min ago';
    return d.toLocaleTimeString('en',{hour:'2-digit',minute:'2-digit'});
}

// ── Escape HTML ───────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Toast ─────────────────────────────────────────────────────
function showToast() {
    clearTimeout(toastTimer);
    toast.classList.add('show');
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
}

// ── Countdown ─────────────────────────────────────────────────
function tick() {
    countdown--;
    if (countdown <= 0) {
        countdown = INTERVAL;
        fetchAnnouncements(false);
    }
    const offset = CIRCUM * (countdown / INTERVAL);
    timerRing.style.strokeDashoffset = CIRCUM - offset;
    timerText.textContent = countdown + 's';
}

// ── Init ─────────────────────────────────────────────────────
fetchAnnouncements(true);
setInterval(tick, 1000);
</script>
</body>
</html>
