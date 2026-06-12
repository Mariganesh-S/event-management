<?php
require_once '../config.php';
if (!isset($_SESSION['judge_id'])) redirect('../login.php?role=judge');

$conn     = getConnection();
$judge_id = (int)$_SESSION['judge_id'];
$judge    = $conn->query("SELECT * FROM judges WHERE id = $judge_id")->fetch_assoc();

$message = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_score'])) {
    $pid          = (int)$_POST['participant_id'];
    $eid          = (int)$_POST['event_id'];
    $creativity   = min(30, max(0, (int)$_POST['creativity']));
    $performance  = min(30, max(0, (int)$_POST['performance']));
    $presentation = min(30, max(0, (int)$_POST['presentation']));

    $existing = $conn->query(
        "SELECT id FROM scores WHERE participant_id=$pid AND event_id=$eid AND judge_id=$judge_id"
    )->fetch_assoc();

    if ($existing) {
        $conn->query("UPDATE scores SET creativity=$creativity,
            performance=$performance, presentation=$presentation WHERE id={$existing['id']}");
        $message = "Score updated!";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO scores (participant_id,event_id,judge_id,creativity,performance,presentation) VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param("iiiiii", $pid, $eid, $judge_id, $creativity, $performance, $presentation);
        $stmt->execute(); $stmt->close();
        $message = "Score saved!";
    }
    $msg_type = "success";
}

$sel_eid = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$sel_pid = (int)($_GET['pid'] ?? 0);

$events_list = [];
$res = $conn->query("SELECT * FROM events ORDER BY event_name");
while ($r = $res->fetch_assoc()) $events_list[] = $r;
if (!$sel_eid && count($events_list)) $sel_eid = $events_list[0]['id'];

$participants = [];
if ($sel_eid) {
    $res = $conn->query("
        SELECT p.id, p.name, p.college, p.department, p.student_id,
               s.creativity, s.performance, s.presentation, s.total, s.id AS score_id
        FROM participant_events pe
        JOIN participants p ON pe.participant_id = p.id
        LEFT JOIN scores s ON s.participant_id=p.id AND s.event_id=$sel_eid AND s.judge_id=$judge_id
        WHERE pe.event_id = $sel_eid ORDER BY p.name ASC
    ");
    while ($r = $res->fetch_assoc()) {
        $r['id'] = (int)$r['id'];
        $participants[] = $r;
    }
}

$sel_p = null;
foreach ($participants as $p) {
    if ($p['id'] == $sel_pid) { $sel_p = $p; break; }
}
if (!$sel_p && count($participants)) {
    foreach ($participants as $p) {
        if (!$p['score_id']) { $sel_p=$p; $sel_pid=$p['id']; break; }
    }
    if (!$sel_p) { $sel_p=$participants[0]; $sel_pid=$participants[0]['id']; }
}

$total_p  = count($participants);
$scored_p = count(array_filter($participants, fn($p) => $p['score_id']));
$pending_p = $total_p - $scored_p;

$next_pid = null; $found = false;
foreach ($participants as $p) {
    if ($found && !$p['score_id']) { $next_pid=$p['id']; break; }
    if ($p['id'] == $sel_pid) $found = true;
}
if (!$next_pid) foreach ($participants as $p) if (!$p['score_id'] && $p['id'] != $sel_pid) { $next_pid=$p['id']; break; }

$cur_event = '';
foreach ($events_list as $ev) if ($ev['id']==$sel_eid) { $cur_event=$ev['event_name']; break; }
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Score Entry — Judge | EventSphere</title>
<link rel="stylesheet" href="../css/style.css">
<style>
*,*::before,*::after{box-sizing:border-box}
body{overflow-x:hidden}
.dashboard-layout{display:flex;min-height:100vh}
.sidebar{width:240px;min-width:240px;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.main-content{flex:1;min-width:0;margin-left:0;padding:24px;overflow-x:hidden}

/* EVENT TABS */
.ev-tabs{display:flex;flex-wrap:wrap;gap:8px}
.ev-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:8px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:rgba(255,255,255,0.75);text-decoration:none;font-size:0.83rem;font-weight:500;white-space:nowrap;transition:all 0.15s}
.ev-tab:hover{border-color:rgba(245,197,24,0.45);color:var(--gold)}
.ev-tab.on{background:var(--gold);color:var(--navy);border-color:var(--gold);font-weight:700}

/* STATS */
.stats-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:16px 0}

/* TWO COLUMN */
.body-grid{display:grid;grid-template-columns:240px 1fr;gap:20px;align-items:start}

/* PARTICIPANT LIST */
.plist-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);padding:10px;max-height:520px;overflow-y:auto}
.plist-card::-webkit-scrollbar{width:4px}
.plist-card::-webkit-scrollbar-thumb{background:var(--gold);border-radius:2px}
.plist-head{font-size:0.70rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);padding:0 6px;margin-bottom:8px}
.plist-row{display:flex;align-items:center;justify-content:space-between;gap:6px;padding:9px 10px;border-radius:8px;text-decoration:none;color:var(--white);margin-bottom:3px;border:1px solid transparent;transition:all 0.14s}
.plist-row:hover{background:rgba(255,255,255,0.04)}
.plist-row.on{background:rgba(245,197,24,0.1);border-color:rgba(245,197,24,0.28)}
.plist-name{font-size:0.85rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.plist-meta{font-size:0.72rem;color:var(--muted);margin-top:1px}

/* FORM HEADER */
.form-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--border);margin-bottom:20px}
.form-header h3{font-family:'Playfair Display',serif;font-size:1.1rem;margin-bottom:4px}
.form-header p{font-size:0.82rem;color:var(--muted)}

/* SCORE STEPPERS */
.score-cols{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}
.score-col{text-align:center}
.score-col label{display:block;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:10px}
.stepper{display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.12);border-radius:12px;overflow:hidden}
.stn-btn{width:48px;height:58px;border:none;background:transparent;color:var(--white);font-size:1.5rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s;flex-shrink:0;user-select:none;-webkit-user-select:none;-webkit-tap-highlight-color:transparent}
.stn-btn:hover{background:rgba(245,197,24,0.15);color:var(--gold)}
.stn-btn:active{background:rgba(245,197,24,0.3);transform:scale(0.93)}
.stn-btn.dec{border-right:1px solid rgba(255,255,255,0.08)}
.stn-btn.inc{border-left:1px solid rgba(255,255,255,0.08)}
.stn-val{flex:1;text-align:center;font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:700;color:var(--gold);line-height:1;min-width:44px;user-select:none}
.stn-val.mx{color:#ff6b35}
.stn-val.zr{color:var(--muted)}
.score-col .cap{font-size:0.72rem;color:var(--muted);margin-top:8px}

/* QUICK BUTTONS */
.quick-btns{display:flex;gap:4px;justify-content:center;margin-top:8px;flex-wrap:wrap}
.qbtn{padding:3px 8px;border-radius:5px;font-size:0.71rem;font-weight:600;border:1px solid rgba(255,255,255,0.1);background:transparent;color:var(--muted);cursor:pointer;transition:all 0.15s}
.qbtn:hover{background:rgba(245,197,24,0.1);border-color:rgba(245,197,24,0.3);color:var(--gold)}
.qbtn:active{transform:scale(0.93)}

/* TOTAL BOX */
.total-box{background:rgba(245,197,24,0.07);border:1px solid rgba(245,197,24,0.18);border-radius:10px;padding:16px 20px;display:flex;align-items:center;gap:20px;margin-bottom:18px}
.total-box .left{text-align:center;min-width:90px}
.total-box .lbl{font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted)}
.total-box .num{font-family:'Playfair Display',serif;font-size:2.6rem;font-weight:700;color:var(--gold);line-height:1}
.total-box .sub{font-size:0.78rem;color:var(--muted)}
.total-box .right{flex:1}
.bar-track{background:rgba(255,255,255,0.08);border-radius:50px;height:8px;overflow:hidden;margin-top:6px}
.bar-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--accent));border-radius:50px;transition:width 0.3s}
.bar-pct{font-size:0.78rem;color:var(--muted);margin-top:4px}

/* ACTIONS */
.action-row{display:flex;gap:10px}
.action-row .btn-save{flex:1;justify-content:center}

/* GUIDE */
.guide-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;font-size:0.80rem}
.guide-col h4{color:var(--gold);font-size:0.82rem;margin-bottom:6px}
.guide-col p{color:var(--muted);line-height:1.8;margin:0}

/* TABLE */
.stbl-wrap{overflow-x:auto;border-radius:8px}
.stbl{width:100%;border-collapse:collapse;font-size:0.83rem;min-width:580px}
.stbl thead th{padding:10px 12px;text-align:left;font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.6px;color:var(--muted);background:rgba(245,197,24,0.04);border-bottom:1px solid var(--border);white-space:nowrap}
.stbl tbody td{padding:11px 12px;border-bottom:1px solid rgba(255,255,255,0.04);vertical-align:middle;color:rgba(255,255,255,0.85)}
.stbl tbody tr:hover{background:rgba(255,255,255,0.025)}

/* MOBILE */
.mobile-nav{display:none}

@media(max-width:900px){
  .sidebar{display:none}
  .main-content{padding:0 0 60px}
  .mobile-nav{
    display:flex;align-items:center;justify-content:space-between;
    background:var(--card-bg);border-bottom:1px solid var(--border);
    padding:12px 16px;position:sticky;top:0;z-index:100;
  }
  .mobile-nav-brand{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--gold);font-weight:700}
  .page-header{padding:16px 16px 0}
  .content-card-tabs{padding:0 16px}
  .main-pad{padding:0 16px}
  .body-grid{grid-template-columns:1fr}
  .plist-card{max-height:200px}
  .stats-3{gap:10px}
  .score-cols{grid-template-columns:repeat(3,1fr);gap:10px}
  .stn-btn{width:40px;height:52px;font-size:1.3rem}
  .stn-val{font-size:1.6rem}
  .total-box{flex-direction:column;text-align:center}
  .total-box .left{min-width:unset}
  .total-box .right{width:100%}
  .action-row{flex-direction:column}
  .guide-grid{grid-template-columns:1fr}
  .ev-tabs{gap:6px}
  .ev-tab{font-size:0.78rem;padding:6px 10px}
}

@media(max-width:480px){
  .score-cols{gap:6px}
  .stn-btn{width:36px;height:48px;font-size:1.2rem}
  .stn-val{font-size:1.4rem}
  .quick-btns{display:none}
  .total-box .num{font-size:2rem}
}
</style>
</head>
<body>
<div class="dashboard-layout">

<!-- SIDEBAR (desktop) -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-name">EventSphere</div>
    <div class="brand-role">⚖️ Judge Panel</div>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="score_entry.php" class="active"><span class="nav-icon">✏️</span> Enter Scores</a>
    <a href="../index.php"><span class="nav-icon">🏠</span> Home</a>
  </nav>
  <div class="sidebar-footer">
    <div style="font-size:.80rem;color:var(--muted);margin-bottom:3px;">Logged in as</div>
    <div style="font-weight:600;margin-bottom:12px;"><?= htmlspecialchars($judge['username']) ?></div>
    <a href="../logout.php?role=judge" class="btn btn-outline btn-sm w-full" style="justify-content:center;">🚪 Logout</a>
  </div>
</aside>

<main class="main-content">

  <!-- Mobile nav -->
  <div class="mobile-nav">
    <span class="mobile-nav-brand">⚖️ EventSphere — Score Entry</span>
    <div style="display:flex;gap:8px">
      <a href="dashboard.php" class="btn btn-outline btn-sm">📊</a>
      <a href="../logout.php?role=judge" class="btn btn-outline btn-sm">🚪</a>
    </div>
  </div>

  <div class="page-header main-pad" style="margin-bottom:16px;">
    <h1>Score Entry</h1>
    <p>Select event → participant → use + / − buttons to score</p>
  </div>

  <?php if ($message): ?>
  <div class="main-pad">
    <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:16px;">✅ <?= htmlspecialchars($message) ?></div>
  </div>
  <?php endif; ?>

  <!-- Event Tabs -->
  <div class="content-card content-card-tabs" style="padding:14px 18px;margin-bottom:16px;">
    <div style="font-size:0.70rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:10px;">Select Event</div>
    <div class="ev-tabs">
      <?php foreach ($events_list as $ev): ?>
      <a href="score_entry.php?event_id=<?= $ev['id'] ?>"
         class="ev-tab <?= $sel_eid==$ev['id']?'on':'' ?>">
        <?= htmlspecialchars($ev['event_name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-3 main-pad">
    <div class="stat-card">
      <div class="stat-card-icon icon-blue">👥</div>
      <div class="stat-card-info"><div class="value"><?= $total_p ?></div><div class="label">Participants</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-green">✅</div>
      <div class="stat-card-info"><div class="value"><?= $scored_p ?></div><div class="label">Scored</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-red">⏳</div>
      <div class="stat-card-info"><div class="value"><?= $pending_p ?></div><div class="label">Pending</div></div>
    </div>
  </div>

  <?php if ($sel_eid && count($participants) > 0): ?>
  <div class="body-grid main-pad">

    <!-- Participant list -->
    <div class="plist-card">
      <div class="plist-head">Participants (<?= $total_p ?>)</div>
      <?php foreach ($participants as $p): ?>
      <a href="score_entry.php?event_id=<?= $sel_eid ?>&pid=<?= $p['id'] ?>"
         class="plist-row <?= $p['id']==$sel_pid?'on':'' ?>">
        <div style="min-width:0;flex:1">
          <div class="plist-name"><?= htmlspecialchars($p['name']) ?></div>
          <div class="plist-meta"><?= htmlspecialchars($p['department']) ?> · <?= htmlspecialchars($p['student_id']) ?></div>
        </div>
        <?php if ($p['score_id']): ?>
          <span class="badge badge-green" style="font-size:0.68rem;flex-shrink:0;">✓ <?= $p['total'] ?>pts</span>
        <?php else: ?>
          <span class="badge badge-red"   style="font-size:0.68rem;flex-shrink:0;">Pending</span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Right col -->
    <div style="display:flex;flex-direction:column;gap:20px;min-width:0">

      <?php if ($sel_p): ?>
      <!-- SCORE FORM -->
      <div class="content-card" style="margin-bottom:0">
        <div class="form-header">
          <div>
            <h3><?= htmlspecialchars($sel_p['name']) ?></h3>
            <p><?= htmlspecialchars($sel_p['college']) ?> &bull; <?= htmlspecialchars($sel_p['department']) ?> &bull; ID: <?= htmlspecialchars($sel_p['student_id']) ?></p>
          </div>
          <?php if ($sel_p['score_id']): ?>
            <span class="badge badge-green">Already Scored</span>
          <?php else: ?>
            <span class="badge badge-red">Not Scored Yet</span>
          <?php endif; ?>
        </div>

        <form method="POST" id="scoreForm">
          <input type="hidden" name="save_score"     value="1">
          <input type="hidden" name="participant_id" value="<?= $sel_p['id'] ?>">
          <input type="hidden" name="event_id"       value="<?= $sel_eid ?>">
          <input type="hidden" name="creativity"   id="val_c"  value="<?= (int)($sel_p['creativity']   ?? 0) ?>">
          <input type="hidden" name="performance"  id="val_p"  value="<?= (int)($sel_p['performance']  ?? 0) ?>">
          <input type="hidden" name="presentation" id="val_pr" value="<?= (int)($sel_p['presentation'] ?? 0) ?>">

          <div style="font-size:0.73rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:14px;">
            Tap <strong style="color:var(--white)">+</strong> / <strong style="color:var(--white)">−</strong> to set score &nbsp;·&nbsp; Max <strong style="color:var(--white)">30</strong> per criterion
          </div>

          <div class="score-cols">

            <?php $cv = (int)($sel_p['creativity'] ?? 0); ?>
            <div class="score-col">
              <label>🎨 Creativity</label>
              <div class="stepper">
                <button type="button" class="stn-btn dec" onclick="chg('c',-1)">−</button>
                <div class="stn-val <?= $cv>=30?'mx':($cv==0?'zr':'') ?>" id="disp_c"><?= $cv ?></div>
                <button type="button" class="stn-btn inc" onclick="chg('c',+1)">+</button>
              </div>
              <div class="cap">out of 30</div>
              <div class="quick-btns">
                <?php foreach([0,10,15,20,25,30] as $q): ?>
                <button type="button" class="qbtn" onclick="set('c',<?= $q ?>)"><?= $q ?></button>
                <?php endforeach; ?>
              </div>
            </div>

            <?php $pv = (int)($sel_p['performance'] ?? 0); ?>
            <div class="score-col">
              <label>🎭 Performance</label>
              <div class="stepper">
                <button type="button" class="stn-btn dec" onclick="chg('p',-1)">−</button>
                <div class="stn-val <?= $pv>=30?'mx':($pv==0?'zr':'') ?>" id="disp_p"><?= $pv ?></div>
                <button type="button" class="stn-btn inc" onclick="chg('p',+1)">+</button>
              </div>
              <div class="cap">out of 30</div>
              <div class="quick-btns">
                <?php foreach([0,10,15,20,25,30] as $q): ?>
                <button type="button" class="qbtn" onclick="set('p',<?= $q ?>)"><?= $q ?></button>
                <?php endforeach; ?>
              </div>
            </div>

            <?php $prv = (int)($sel_p['presentation'] ?? 0); ?>
            <div class="score-col">
              <label>📊 Presentation</label>
              <div class="stepper">
                <button type="button" class="stn-btn dec" onclick="chg('pr',-1)">−</button>
                <div class="stn-val <?= $prv>=30?'mx':($prv==0?'zr':'') ?>" id="disp_pr"><?= $prv ?></div>
                <button type="button" class="stn-btn inc" onclick="chg('pr',+1)">+</button>
              </div>
              <div class="cap">out of 30</div>
              <div class="quick-btns">
                <?php foreach([0,10,15,20,25,30] as $q): ?>
                <button type="button" class="qbtn" onclick="set('pr',<?= $q ?>)"><?= $q ?></button>
                <?php endforeach; ?>
              </div>
            </div>

          </div>

          <!-- Total -->
          <div class="total-box">
            <div class="left">
              <div class="lbl">Total Score</div>
              <div class="num" id="totalNum"><?= $cv + $pv + $prv ?></div>
              <div class="sub">/ 90 pts</div>
            </div>
            <div class="right">
              <div style="font-size:0.78rem;color:var(--muted);margin-bottom:4px;">Score progress</div>
              <div class="bar-track"><div class="bar-fill" id="barFill" style="width:0%"></div></div>
              <div class="bar-pct" id="barPct">0%</div>
            </div>
          </div>

          <div class="action-row">
            <button type="submit" class="btn btn-primary btn-lg btn-save">
              💾 <?= $sel_p['score_id'] ? 'Update Score' : 'Save Score' ?>
            </button>
            <?php if ($next_pid): ?>
            <a href="score_entry.php?event_id=<?= $sel_eid ?>&pid=<?= $next_pid ?>"
               class="btn btn-outline btn-lg" style="white-space:nowrap;">Next ⟶</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- GUIDE -->
      <div class="content-card" style="margin-bottom:0">
        <div style="font-size:0.88rem;font-weight:600;margin-bottom:14px;">📋 Scoring Guide</div>
        <div class="guide-grid">
          <div class="guide-col"><h4>🎨 Creativity</h4><p>26–30 Exceptional<br>20–25 Very Good<br>15–19 Good<br>10–14 Average<br>0–9 Below Avg</p></div>
          <div class="guide-col"><h4>🎭 Performance</h4><p>26–30 Outstanding<br>20–25 Excellent<br>15–19 Good<br>10–14 Fair<br>0–9 Needs Work</p></div>
          <div class="guide-col"><h4>📊 Presentation</h4><p>26–30 Professional<br>20–25 Polished<br>15–19 Adequate<br>10–14 Basic<br>0–9 Incomplete</p></div>
        </div>
      </div>

      <!-- SCORES TABLE -->
      <div class="content-card" style="margin-bottom:0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border)">
          <h3 style="font-family:'Playfair Display',serif;font-size:1.05rem;">All Scores — <?= htmlspecialchars($cur_event) ?></h3>
          <span class="badge badge-gold"><?= $scored_p ?> / <?= $total_p ?> scored</span>
        </div>
        <div class="stbl-wrap">
          <table class="stbl">
            <thead>
              <tr>
                <th>#</th><th>Name</th><th>College</th>
                <th style="text-align:center">🎨</th>
                <th style="text-align:center">🎭</th>
                <th style="text-align:center">📊</th>
                <th style="text-align:center">Total</th>
                <th style="text-align:center">Status</th>
                <th style="text-align:center">Edit</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($participants as $idx => $p): ?>
              <tr>
                <td style="color:var(--muted);font-size:0.78rem"><?= $idx+1 ?></td>
                <td>
                  <strong><?= htmlspecialchars($p['name']) ?></strong>
                  <div style="font-size:0.72rem;color:var(--muted)"><?= htmlspecialchars($p['student_id']) ?></div>
                </td>
                <td style="font-size:0.79rem;color:var(--muted)"><?= htmlspecialchars(mb_strimwidth($p['college'],0,20,'…')) ?></td>
                <td style="text-align:center"><?= $p['score_id'] ? $p['creativity'].'/30'   : '<span style="color:var(--muted)">—</span>' ?></td>
                <td style="text-align:center"><?= $p['score_id'] ? $p['performance'].'/30'  : '<span style="color:var(--muted)">—</span>' ?></td>
                <td style="text-align:center"><?= $p['score_id'] ? $p['presentation'].'/30' : '<span style="color:var(--muted)">—</span>' ?></td>
                <td style="text-align:center">
                  <?php if($p['score_id']): ?>
                    <strong style="color:var(--gold)"><?= $p['total'] ?></strong><span style="color:var(--muted);font-size:0.70rem">/90</span>
                  <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
                </td>
                <td style="text-align:center">
                  <span class="badge <?= $p['score_id']?'badge-green':'badge-red' ?>" style="font-size:0.68rem">
                    <?= $p['score_id']?'✓':'—' ?>
                  </span>
                </td>
                <td style="text-align:center">
                  <button type="button"
                    class="btn btn-outline btn-sm"
                    style="padding:4px 10px;font-size:0.77rem;cursor:pointer"
                    onclick="window.location.href='score_entry.php?event_id=<?= $sel_eid ?>&pid=<?= $p['id'] ?>'">
                    <?= $p['score_id']?'✏️':'➕' ?>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <?php elseif ($sel_eid): ?>
  <div class="content-card main-pad">
    <div style="text-align:center;padding:48px;color:var(--muted)">⚠️ No participants registered for this event yet.</div>
  </div>
  <?php endif; ?>

</main>
</div>

<script>
const S = {
  c:  parseInt(document.getElementById('val_c').value)  || 0,
  p:  parseInt(document.getElementById('val_p').value)  || 0,
  pr: parseInt(document.getElementById('val_pr').value) || 0,
};

function chg(k, d) {
  S[k] = Math.min(30, Math.max(0, S[k] + d));
  upd(k);
}

function set(k, v) {
  S[k] = Math.min(30, Math.max(0, v));
  upd(k);
}

function upd(k) {
  const v   = S[k];
  const el  = document.getElementById('disp_' + k);
  el.textContent = v;
  el.className   = 'stn-val' + (v >= 30 ? ' mx' : v === 0 ? ' zr' : '');
  document.getElementById('val_' + k).value = v;
  calc();
}

function calc() {
  const t   = S.c + S.p + S.pr;
  const pct = +(t / 90 * 100).toFixed(1);
  document.getElementById('totalNum').textContent   = t;
  document.getElementById('barFill').style.width    = pct + '%';
  document.getElementById('barPct').textContent     = pct + '%';
}

document.querySelectorAll('.stn-btn, .qbtn').forEach(btn => {
  btn.addEventListener('touchstart', e => { e.preventDefault(); btn.click(); }, { passive: false });
});

const activeRow = document.querySelector('.plist-row.on');
if (activeRow) activeRow.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

calc();
</script>
</body>
</html>
