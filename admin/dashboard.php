<?php
require_once '../config.php';
if (!isset($_SESSION['admin_id'])) redirect('../login.php?role=admin');

$conn = getConnection();

// Check new columns
$has_new_cols = (bool)($conn->query("SHOW COLUMNS FROM events LIKE 'event_type'")->num_rows);

// ── Stats ─────────────────────────────────────────────────────
$total_p       = (int)$conn->query("SELECT COUNT(*) as c FROM participants")->fetch_assoc()['c'];
$total_e       = (int)$conn->query("SELECT COUNT(*) as c FROM events")->fetch_assoc()['c'];
$total_reg     = (int)$conn->query("SELECT COUNT(*) as c FROM participant_events")->fetch_assoc()['c'];
$total_scored  = (int)$conn->query("SELECT COUNT(DISTINCT participant_id) as c FROM scores")->fetch_assoc()['c'];
$total_judges  = (int)$conn->query("SELECT COUNT(*) as c FROM judges")->fetch_assoc()['c'];

// ── Chart 1: Event-wise participant count ─────────────────────
$chart1_labels = [];
$chart1_data   = [];
$chart1_types  = [];

if ($has_new_cols) {
    $q = "SELECT e.event_name, e.event_type, COUNT(pe.participant_id) as cnt
          FROM events e
          LEFT JOIN participant_events pe ON e.id = pe.event_id
          GROUP BY e.id ORDER BY cnt DESC";
} else {
    $q = "SELECT e.event_name, 'solo' as event_type, COUNT(pe.participant_id) as cnt
          FROM events e
          LEFT JOIN participant_events pe ON e.id = pe.event_id
          GROUP BY e.id ORDER BY cnt DESC";
}
$res = $conn->query($q);
while ($r = $res->fetch_assoc()) {
    $chart1_labels[] = $r['event_name'];
    $chart1_data[]   = (int)$r['cnt'];
    $chart1_types[]  = $r['event_type'];
}

// ── Chart 2: Solo vs Group participant split ──────────────────
$solo_count  = 0;
$group_count = 0;
if ($has_new_cols) {
    $res2 = $conn->query("
        SELECT e.event_type, COUNT(pe.participant_id) as cnt
        FROM events e
        LEFT JOIN participant_events pe ON e.id = pe.event_id
        GROUP BY e.event_type
    ");
    while ($r = $res2->fetch_assoc()) {
        if ($r['event_type'] === 'group') $group_count = (int)$r['cnt'];
        else                               $solo_count  = (int)$r['cnt'];
    }
} else {
    $solo_count = $total_reg;
}

// ── Chart 3: Top 5 scored events (avg total score) ───────────
$chart3_labels = [];
$chart3_data   = [];
$res3 = $conn->query("
    SELECT e.event_name, AVG(s.total) as avg_score, COUNT(DISTINCT s.participant_id) as p_count
    FROM scores s
    JOIN events e ON s.event_id = e.id
    GROUP BY s.event_id
    ORDER BY avg_score DESC
    LIMIT 6
");
while ($r = $res3->fetch_assoc()) {
    $chart3_labels[] = mb_strimwidth($r['event_name'], 0, 18, '…');
    $chart3_data[]   = round((float)$r['avg_score'], 1);
}

// ── Chart 4: Daily registrations (last 7 days) ───────────────
$chart4_labels = [];
$chart4_data   = [];
for ($i = 6; $i >= 0; $i--) {
    $date   = date('Y-m-d', strtotime("-$i days"));
    $label  = date('d M', strtotime("-$i days"));
    $cnt    = $conn->query("SELECT COUNT(*) as c FROM participants WHERE DATE(registered_at)='$date'")->fetch_assoc()['c'];
    $chart4_labels[] = $label;
    $chart4_data[]   = (int)$cnt;
}

// ── Event-wise table data ─────────────────────────────────────
$event_table = [];
if ($has_new_cols) {
    $tq = "SELECT e.event_name, e.event_type, e.max_participants,
                  COUNT(pe.participant_id) as cnt
           FROM events e
           LEFT JOIN participant_events pe ON e.id = pe.event_id
           GROUP BY e.id ORDER BY cnt DESC";
} else {
    $tq = "SELECT e.event_name, 'solo' as event_type, e.max_participants,
                  COUNT(pe.participant_id) as cnt
           FROM events e
           LEFT JOIN participant_events pe ON e.id = pe.event_id
           GROUP BY e.id ORDER BY cnt DESC";
}
$res4 = $conn->query($tq);
while ($r = $res4->fetch_assoc()) $event_table[] = $r;

// ── Recent registrations ──────────────────────────────────────
$recent = $conn->query("
    SELECT p.id, p.name, p.college, p.registered_at,
           GROUP_CONCAT(e.event_name SEPARATOR '||') as events
    FROM participants p
    LEFT JOIN participant_events pe ON p.id = pe.participant_id
    LEFT JOIN events e ON pe.event_id = e.id
    GROUP BY p.id
    ORDER BY p.registered_at DESC
    LIMIT 6
");

$conn->close();

// JSON for charts
$j1_labels = json_encode($chart1_labels);
$j1_data   = json_encode($chart1_data);
$j1_types  = json_encode($chart1_types);
$j3_labels = json_encode($chart3_labels);
$j3_data   = json_encode($chart3_data);
$j4_labels = json_encode($chart4_labels);
$j4_data   = json_encode($chart4_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard — EventSphere</title>
<link rel="stylesheet" href="../css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
/* ══════════════════════════════════════
   SIDEBAR — fixed, flex column, nav scrolls, logout always visible
══════════════════════════════════════ */
.dashboard-layout{display:flex!important;min-height:100vh}
.sidebar{
  width:240px!important;
  position:fixed!important;
  top:0!important;left:0!important;
  height:100vh!important;
  display:flex!important;
  flex-direction:column!important;
  background:var(--card-bg)!important;
  border-right:1px solid var(--border)!important;
  z-index:100!important;
  overflow:hidden!important;
}
.sidebar-brand{
  flex-shrink:0!important;
  padding:24px 20px 16px!important;
}
.sidebar-nav{
  flex:1!important;
  overflow-y:auto!important;
  overflow-x:hidden!important;
  padding:4px 10px 8px!important;
  min-height:0!important;
}
.sidebar-nav::-webkit-scrollbar{width:4px}
.sidebar-nav::-webkit-scrollbar-track{background:rgba(255,255,255,.03)}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(245,197,24,.3);border-radius:2px}
.sidebar-nav a{
  display:flex!important;
  align-items:center!important;
  gap:10px!important;
  padding:9px 10px!important;
  border-radius:8px!important;
  font-size:.875rem!important;
  font-weight:500!important;
  color:rgba(255,255,255,.7)!important;
  text-decoration:none!important;
  margin-bottom:2px!important;
  transition:all .15s!important;
  white-space:nowrap!important;
}
.sidebar-nav a:hover{background:rgba(255,255,255,.06)!important;color:var(--white)!important}
.sidebar-nav a.active{background:rgba(245,197,24,.12)!important;color:var(--gold)!important;font-weight:700!important}
.sidebar-footer{
  flex-shrink:0!important;
  padding:14px 16px!important;
  border-top:1px solid var(--border)!important;
  background:var(--card-bg)!important;
}
.main-content{
  margin-left:240px!important;
  width:calc(100% - 240px)!important;
  min-height:100vh!important;
  overflow-x:hidden!important;
}
/* Mobile */
.mobile-nav{display:none}
@media(max-width:900px){
  .sidebar{display:none!important}
  .main-content{margin-left:0!important;width:100%!important}
  .mobile-nav{display:flex!important;align-items:center;justify-content:space-between;
    background:var(--card-bg);border-bottom:1px solid var(--border);
    padding:12px 16px;position:sticky;top:0;z-index:100}
  .mobile-nav-brand{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--gold);font-weight:700}
}
*,*::before,*::after{box-sizing:border-box}
body{background:var(--navy)!important;color:var(--white)!important;overflow-x:hidden}

/* chart cards */
.chart-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:22px;position:relative}
.chart-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.chart-card-header h3{font-family:'Playfair Display',serif;font-size:1rem;font-weight:600}
.chart-card-header p{font-size:.75rem;color:var(--muted);margin-top:2px}
.chart-wrap{position:relative;width:100%}

/* charts grid */
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.charts-grid-3{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px}

/* progress bar */
.prog-bar-wrap{margin-bottom:12px}
.prog-bar-info{display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;font-size:.82rem}
.prog-bar-info .ev-name{font-weight:500;color:rgba(255,255,255,.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:65%}
.prog-bar-info .ev-cnt{font-weight:700;color:var(--gold);flex-shrink:0}
.prog-track{height:8px;background:rgba(255,255,255,.07);border-radius:50px;overflow:hidden}
.prog-fill{height:100%;border-radius:50px;transition:width .6s ease}
.prog-fill.solo {background:linear-gradient(90deg,#1D9E75,#5DCAA5)}
.prog-fill.group{background:linear-gradient(90deg,#185FA5,#378ADD)}
.prog-fill.default{background:linear-gradient(90deg,var(--gold),#ff6b35)}

/* stat cards */
.stats-grid-5{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:20px}

/* type badge */
.type-pill{display:inline-block;font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:50px}
.type-pill.solo {background:rgba(0,212,170,.12);color:var(--success);border:1px solid rgba(0,212,170,.2)}
.type-pill.group{background:rgba(100,149,237,.12);color:#6495ed;border:1px solid rgba(100,149,237,.2)}

@media(max-width:1100px){
  .charts-grid,.charts-grid-3{grid-template-columns:1fr}
  .stats-grid-5{grid-template-columns:repeat(3,1fr)}
}
</style>
</head>
<body>
<div class="dashboard-layout">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-name">EventSphere</div>
    <div class="brand-role">🛡️ Admin Panel</div>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="active"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="participants.php"><span class="nav-icon">👥</span> Participants</a>
    <a href="results.php"><span class="nav-icon">🏆</span> Results</a>
    <a href="manage_events.php"><span class="nav-icon">🎯</span> Manage Events</a>
    <a href="manage_judges.php"><span class="nav-icon">⚖️</span> Manage Judges</a>
    <a href="alert.php"><span class="nav-icon">📢</span> Announcements</a>
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

<!-- MAIN -->
<main class="main-content">

  <div class="page-header">
    <h1>Dashboard</h1>
    <p>Live overview of EventSphere registrations, scores and activity</p>
  </div>

  <!-- ── STATS ROW ── -->
  <div class="stats-grid-5">
    <div class="stat-card">
      <div class="stat-card-icon icon-gold">👥</div>
      <div class="stat-card-info"><div class="value"><?= $total_p ?></div><div class="label">Participants</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-blue">🎯</div>
      <div class="stat-card-info"><div class="value"><?= $total_e ?></div><div class="label">Events</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-green">📋</div>
      <div class="stat-card-info"><div class="value"><?= $total_reg ?></div><div class="label">Registrations</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-purple">⚖️</div>
      <div class="stat-card-info"><div class="value"><?= $total_judges ?></div><div class="label">Judges</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-red">⭐</div>
      <div class="stat-card-info"><div class="value"><?= $total_scored ?></div><div class="label">Scored</div></div>
    </div>
  </div>

  <!-- ── ROW 1: Bar chart + Doughnut ── -->
  <div class="charts-grid">

    <!-- Chart 1: Event-wise participant count (Bar) -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3>📊 Event-wise Participant Count</h3>
          <p>Number of participants registered per event</p>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0;">
          <span style="display:flex;align-items:center;gap:4px;font-size:.72rem;color:var(--success);">
            <span style="width:10px;height:10px;background:rgba(29,158,117,.7);border-radius:2px;display:inline-block;"></span>Solo
          </span>
          <span style="display:flex;align-items:center;gap:4px;font-size:.72rem;color:#6495ed;">
            <span style="width:10px;height:10px;background:rgba(55,138,221,.7);border-radius:2px;display:inline-block;"></span>Group
          </span>
        </div>
      </div>
      <div class="chart-wrap" style="height:260px;">
        <canvas id="barChart"></canvas>
      </div>
    </div>

    <!-- Chart 2: Solo vs Group doughnut -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3>🥧 Solo vs Group Split</h3>
          <p>Registration distribution by event type</p>
        </div>
      </div>
      <div class="chart-wrap" style="height:200px;">
        <canvas id="doughnutChart"></canvas>
      </div>
      <div style="display:flex;justify-content:center;gap:24px;margin-top:14px;">
        <div style="text-align:center;">
          <div style="font-size:1.4rem;font-weight:700;color:var(--success);"><?= $solo_count ?></div>
          <div style="font-size:.75rem;color:var(--muted);">Solo Registrations</div>
        </div>
        <div style="width:1px;background:var(--border);"></div>
        <div style="text-align:center;">
          <div style="font-size:1.4rem;font-weight:700;color:#6495ed;"><?= $group_count ?></div>
          <div style="font-size:.75rem;color:var(--muted);">Group Registrations</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── ROW 2: Line chart + Avg scores ── -->
  <div class="charts-grid">

    <!-- Chart 4: Daily registrations (Line) -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3>📈 Daily Registrations — Last 7 Days</h3>
          <p>New participants registered each day</p>
        </div>
      </div>
      <div class="chart-wrap" style="height:220px;">
        <canvas id="lineChart"></canvas>
      </div>
    </div>

    <!-- Chart 3: Avg score per event (Horizontal bar) -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3>🏆 Avg Score per Event</h3>
          <p>Average judge score (out of 90)</p>
        </div>
      </div>
      <?php if (empty($chart3_data)): ?>
        <div style="text-align:center;padding:40px;color:var(--muted);font-size:.88rem;">⏳ No scores entered yet</div>
      <?php else: ?>
      <div class="chart-wrap" style="height:220px;">
        <canvas id="hbarChart"></canvas>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── ROW 3: Progress bars + Recent registrations ── -->
  <div class="charts-grid">

    <!-- Progress bars: event fill rate -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3>🎯 Event Fill Rate</h3>
          <p>Participants registered vs max capacity</p>
        </div>
      </div>
      <?php
      $max_cnt = max(array_column($event_table, 'cnt') ?: [1]);
      foreach($event_table as $ev):
        $cnt  = (int)$ev['cnt'];
        $max  = (int)$ev['max_participants'];
        $pct  = $max > 0 ? min(100, round($cnt / $max * 100)) : 0;
        $type = $ev['event_type'] ?? 'solo';
        $fillClass = $type === 'group' ? 'group' : 'solo';
      ?>
      <div class="prog-bar-wrap">
        <div class="prog-bar-info">
          <span class="ev-name">
            <span class="type-pill <?= $type ?>"><?= $type === 'group' ? 'G' : 'S' ?></span>
            &nbsp;<?= htmlspecialchars($ev['event_name']) ?>
          </span>
          <span class="ev-cnt"><?= $cnt ?> / <?= $max ?></span>
        </div>
        <div class="prog-track">
          <div class="prog-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Recent registrations -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3>🕐 Recent Registrations</h3>
          <p>Latest participants to sign up</p>
        </div>
        <a href="participants.php" class="btn btn-outline btn-sm">All →</a>
      </div>
      <?php if (!$recent || $recent->num_rows === 0): ?>
        <div style="text-align:center;padding:30px;color:var(--muted);font-size:.88rem;">No registrations yet</div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php while($row = $recent->fetch_assoc()):
          $evs = array_filter(explode('||', $row['events'] ?? ''));
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.04);">
          <div style="width:36px;height:36px;background:rgba(245,197,24,.1);border:1px solid rgba(245,197,24,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;color:var(--gold);flex-shrink:0;">
            <?= strtoupper(substr($row['name'],0,1)) ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= htmlspecialchars($row['name']) ?>
            </div>
            <div style="font-size:.73rem;color:var(--muted);margin-top:1px;">
              <?= htmlspecialchars(mb_strimwidth($row['college'],0,24,'…')) ?>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <?php foreach($evs as $ev): ?>
            <span style="display:inline-block;background:rgba(245,197,24,.1);color:var(--gold);border-radius:4px;padding:1px 6px;font-size:.68rem;font-weight:600;margin:1px;">
              <?= htmlspecialchars(mb_strimwidth($ev,0,14,'…')) ?>
            </span>
            <?php endforeach; ?>
            <div style="font-size:.70rem;color:var(--muted);margin-top:3px;">
              <?= date('d M, H:i', strtotime($row['registered_at'])) ?>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="chart-card">
    <div class="chart-card-header"><h3>⚡ Quick Actions</h3></div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <a href="participants.php" class="btn btn-primary">👥 Manage Participants</a>
      <a href="results.php"      class="btn btn-outline">🏆 View Results</a>
        <a href="../leaderboard.php" class="btn btn-outline" target="_blank">📺 Live Leaderboard</a>
      <a href="../index.php"     class="btn btn-outline">🏠 Registration Page</a>
      <a href="../login.php?role=judge" class="btn btn-outline">⚖️ Judge Panel</a>
    </div>
  </div>

</main>
</div>

<script>
// ── Chart defaults ──────────────────────────────────────────
Chart.defaults.color          = 'rgba(255,255,255,0.45)';
Chart.defaults.borderColor    = 'rgba(255,255,255,0.06)';
Chart.defaults.font.family    = "'DM Sans', sans-serif";
Chart.defaults.font.size      = 11;

const gold   = 'rgba(245,197,24,';
const teal   = 'rgba(29,158,117,';
const blue   = 'rgba(55,138,221,';
const accent = 'rgba(255,107,53,';

// ── DATA from PHP ───────────────────────────────────────────
const labels1 = <?= $j1_labels ?>;
const data1   = <?= $j1_data ?>;
const types1  = <?= $j1_types ?>;

const labels3 = <?= $j3_labels ?>;
const data3   = <?= $j3_data ?>;

const labels4 = <?= $j4_labels ?>;
const data4   = <?= $j4_data ?>;

// ── Chart 1: Horizontal Bar — event wise count ──────────────
const barColors = types1.map(t =>
    t === 'group' ? blue+'0.75)' : teal+'0.75)'
);
const barBorders = types1.map(t =>
    t === 'group' ? blue+'1)' : teal+'1)'
);

new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: labels1,
        datasets: [{
            label: 'Participants',
            data: data1,
            backgroundColor: barColors,
            borderColor: barBorders,
            borderWidth: 1,
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.parsed.x} participant${ctx.parsed.x!==1?'s':''}`,
                    title: ctx => ctx[0].label
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { stepSize: 1, color: 'rgba(255,255,255,0.35)' },
                grid:  { color: 'rgba(255,255,255,0.05)' }
            },
            y: {
                ticks: {
                    color: 'rgba(255,255,255,0.65)',
                    font: { size: 11 },
                    callback: val => {
                        const lbl = labels1[val];
                        return lbl && lbl.length > 20 ? lbl.slice(0,18)+'…' : lbl;
                    }
                },
                grid: { display: false }
            }
        }
    }
});

// ── Chart 2: Doughnut — solo vs group ───────────────────────
const soloCount  = <?= $solo_count ?>;
const groupCount = <?= $group_count ?>;

new Chart(document.getElementById('doughnutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Solo Events', 'Group Events'],
        datasets: [{
            data: [soloCount, groupCount],
            backgroundColor: [teal+'0.75)', blue+'0.75)'],
            borderColor:     [teal+'1)',    blue+'1)'],
            borderWidth: 2,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: 'rgba(255,255,255,0.55)',
                    padding: 16,
                    font: { size: 11 },
                    usePointStyle: true,
                    pointStyleWidth: 10
                }
            },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.parsed} registrations (${Math.round(ctx.parsed/(soloCount+groupCount||1)*100)}%)`
                }
            }
        }
    }
});

// ── Chart 3: Horizontal bar — avg scores ────────────────────
<?php if (!empty($chart3_data)): ?>
new Chart(document.getElementById('hbarChart'), {
    type: 'bar',
    data: {
        labels: labels3,
        datasets: [{
            label: 'Avg Score',
            data: data3,
            backgroundColor: gold+'0.7)',
            borderColor:     gold+'1)',
            borderWidth: 1,
            borderRadius: 5,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: { label: ctx => ` Avg: ${ctx.parsed.x} / 90` }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                max: 90,
                ticks: { color: 'rgba(255,255,255,0.35)' },
                grid:  { color: 'rgba(255,255,255,0.05)' }
            },
            y: {
                ticks: { color: 'rgba(255,255,255,0.65)', font: { size: 11 } },
                grid:  { display: false }
            }
        }
    }
});
<?php endif; ?>

// ── Chart 4: Line — daily registrations ─────────────────────
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: labels4,
        datasets: [{
            label: 'Registrations',
            data: data4,
            borderColor: gold+'1)',
            backgroundColor: gold+'0.08)',
            borderWidth: 2.5,
            pointBackgroundColor: gold+'1)',
            pointBorderColor: 'var(--navy)',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.parsed.y} new registration${ctx.parsed.y!==1?'s':''}`
                }
            }
        },
        scales: {
            x: {
                ticks: { color: 'rgba(255,255,255,0.45)' },
                grid:  { color: 'rgba(255,255,255,0.04)' }
            },
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, color: 'rgba(255,255,255,0.35)' },
                grid:  { color: 'rgba(255,255,255,0.05)' }
            }
        }
    }
});
</script>
</body>
</html>
