<?php
require_once '../config.php';
if (!isset($_SESSION['admin_id'])) redirect('../login.php?role=admin');

$conn = getConnection();
$message = $msg_type = '';

// ── Delete ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $conn->query("DELETE FROM participants WHERE id=$did");
    $message = "Participant #$did deleted.";
    $msg_type = "success";
}

// ── Edit save ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_id'])) {
    $eid  = (int)$_POST['edit_id'];
    $name = clean($_POST['name']);
    $ph   = clean($_POST['phone']);
    $em   = clean($_POST['email']);
    $col  = clean($_POST['college']);
    $dep  = clean($_POST['department']);
    $sid  = clean($_POST['student_id']);
    $stmt = $conn->prepare("UPDATE participants SET name=?,phone=?,email=?,college=?,department=?,student_id=? WHERE id=?");
    $stmt->bind_param("ssssssi",$name,$ph,$em,$col,$dep,$sid,$eid);
    if ($stmt->execute()) { $message="Participant #$eid updated."; $msg_type="success"; }
    else { $message="Update failed."; $msg_type="danger"; }
    $stmt->close();
}

// ── Check columns & tables ────────────────────────────────────
$has_type     = (bool)$conn->query("SHOW COLUMNS FROM events LIKE 'event_type'")->num_rows;
$has_team_tbl = (bool)$conn->query("SHOW TABLES LIKE 'team_members'")->num_rows;

// ── Filters ───────────────────────────────────────────────────
$search       = clean($_GET['search']   ?? '');
$event_filter = (int)($_GET['event_id'] ?? 0);
$type_filter  = clean($_GET['ptype']    ?? 'all'); // all | solo | group

// ── All events for filter dropdown ────────────────────────────
$all_events = [];
$res = $conn->query("SELECT id, event_name" . ($has_type ? ", event_type" : ", 'solo' as event_type") . " FROM events ORDER BY event_name");
while ($r = $res->fetch_assoc()) $all_events[] = $r;

// ── Build participant query ────────────────────────────────────
$where = "WHERE 1=1";
if ($search)       $where .= " AND (p.name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR p.college LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR p.email LIKE '%".mysqli_real_escape_string($conn,$search)."%')";
if ($event_filter) $where .= " AND pe.event_id=$event_filter";

// Type filter (solo/group)
if ($type_filter === 'solo' && $has_type) {
    $where .= " AND EXISTS (SELECT 1 FROM participant_events pe2 JOIN events e2 ON pe2.event_id=e2.id WHERE pe2.participant_id=p.id AND e2.event_type='solo')";
} elseif ($type_filter === 'group' && $has_type) {
    $where .= " AND EXISTS (SELECT 1 FROM participant_events pe2 JOIN events e2 ON pe2.event_id=e2.id WHERE pe2.participant_id=p.id AND e2.event_type='group')";
}

$query = "
    SELECT p.id, p.name, p.phone, p.email, p.college, p.department, p.student_id, p.registered_at,
           GROUP_CONCAT(DISTINCT e.id   ORDER BY e.event_name SEPARATOR '||') AS event_ids,
           GROUP_CONCAT(DISTINCT e.event_name ORDER BY e.event_name SEPARATOR '||') AS event_names,
           GROUP_CONCAT(DISTINCT " . ($has_type ? "e.event_type" : "'solo'") . " ORDER BY e.event_name SEPARATOR '||') AS event_types
    FROM participants p
    JOIN participant_events pe ON p.id=pe.participant_id
    JOIN events e ON pe.event_id=e.id
    $where
    GROUP BY p.id
    ORDER BY p.registered_at DESC
";

$participants = $conn->query($query);

// ── Edit modal data ────────────────────────────────────────────
$edit_p = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_p = $conn->query("SELECT * FROM participants WHERE id=$eid")->fetch_assoc();
}

// ── Stats ─────────────────────────────────────────────────────
$total_all   = (int)$conn->query("SELECT COUNT(DISTINCT p.id) as c FROM participants p JOIN participant_events pe ON p.id=pe.participant_id")->fetch_assoc()['c'];
$total_solo  = $has_type ? (int)$conn->query("SELECT COUNT(DISTINCT p.id) as c FROM participants p JOIN participant_events pe ON p.id=pe.participant_id JOIN events e ON pe.event_id=e.id WHERE e.event_type='solo'")->fetch_assoc()['c'] : $total_all;
$total_group = $has_type ? (int)$conn->query("SELECT COUNT(DISTINCT p.id) as c FROM participants p JOIN participant_events pe ON p.id=pe.participant_id JOIN events e ON pe.event_id=e.id WHERE e.event_type='group'")->fetch_assoc()['c'] : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Participants — Admin | EventSphere</title>
<link rel="stylesheet" href="../css/style.css">
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
/* ── Base ── */
*,*::before,*::after{box-sizing:border-box}
body{background:var(--navy)!important;color:var(--white)!important}
/* ── Type tabs ── */
.type-tabs{display:flex;gap:0;border-radius:10px;overflow:hidden;border:1px solid var(--border);width:fit-content;margin-bottom:0}
.type-tab{padding:9px 20px;font-size:0.85rem;font-weight:600;cursor:pointer;background:transparent;border:none;color:var(--muted);transition:all .2s;font-family:'DM Sans',sans-serif;text-decoration:none;display:flex;align-items:center;gap:6px}
.type-tab:hover{background:rgba(245,197,24,.08);color:var(--gold)}
.type-tab.active{background:var(--gold);color:var(--navy)}
.type-tab .cnt{background:rgba(0,0,0,.2);border-radius:50px;padding:1px 7px;font-size:.72rem}

/* ── Filter bar ── */
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.filter-bar .form-control{height:40px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:var(--white)}
.filter-bar .form-control:focus{border-color:var(--gold);background:rgba(245,197,24,.05)}
.filter-bar select.form-control option{background:#0d1235;color:var(--white)}
.filter-bar input{flex:1;min-width:180px;max-width:320px}
.filter-bar select{width:190px}
.filter-bar .btn{height:40px;padding:0 18px;display:flex;align-items:center}

/* ── Stats row ── */
.stats-mini{display:flex;gap:12px;flex-wrap:wrap}
.stat-mini{background:var(--card-bg);border:1px solid var(--border);border-radius:8px;padding:10px 16px;display:flex;align-items:center;gap:10px}
.stat-mini .val{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700}
.stat-mini .lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

/* ── Table wrapper ── */
.tbl-outer{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.tbl-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);background:rgba(245,197,24,.03)}
.tbl-head h3{font-family:'Playfair Display',serif;font-size:1rem;font-weight:600}
.tbl-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}

/* ── Table ── */
.ptbl{width:100%;border-collapse:collapse;font-size:.83rem;min-width:960px;table-layout:fixed}
.ptbl colgroup col:nth-child(1){width:54px}
.ptbl colgroup col:nth-child(2){width:130px}
.ptbl colgroup col:nth-child(3){width:108px}
.ptbl colgroup col:nth-child(4){width:160px}
.ptbl colgroup col:nth-child(5){width:130px}
.ptbl colgroup col:nth-child(6){width:100px}
.ptbl colgroup col:nth-child(7){width:80px}
.ptbl colgroup col:nth-child(8){width:180px}
.ptbl colgroup col:nth-child(9){width:88px}
.ptbl colgroup col:nth-child(10){width:76px}

.ptbl thead tr{background:rgba(245,197,24,.05);border-bottom:1px solid var(--border)}
.ptbl thead th{padding:11px 10px;text-align:left;font-size:.71rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);white-space:nowrap}
.ptbl tbody tr{border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s}
.ptbl tbody tr:hover{background:rgba(255,255,255,.03)}
.ptbl tbody td{padding:11px 10px;vertical-align:middle;color:rgba(255,255,255,.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:0}
.ptbl tbody td.ev-cell{white-space:normal;overflow:visible;max-width:none;padding:8px 10px}
.ptbl tbody td.act-cell{white-space:nowrap;overflow:visible;max-width:none}

/* ── Event badges ── */
.ev-tag{display:inline-flex;align-items:center;gap:4px;border-radius:5px;padding:2px 8px;font-size:.70rem;font-weight:700;margin:2px 2px 2px 0;white-space:nowrap}
.ev-tag.solo{background:rgba(0,212,170,.1);color:var(--success);border:1px solid rgba(0,212,170,.2)}
.ev-tag.group{background:rgba(100,149,237,.1);color:#6495ed;border:1px solid rgba(100,149,237,.2)}
.ev-tag.def{background:rgba(245,197,24,.1);color:var(--gold);border:1px solid rgba(245,197,24,.15)}

/* ── Action buttons ── */
.act-btns{display:flex;gap:5px}
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;border:1px solid rgba(255,255,255,.1);background:transparent;text-decoration:none;font-size:.82rem;cursor:pointer;color:var(--white);transition:all .15s}
.act-btn:hover{background:rgba(255,255,255,.07)}
.act-btn.del:hover{background:rgba(255,71,87,.15);border-color:var(--danger)}

/* ── Section divider ── */
.section-divider{display:flex;align-items:center;gap:12px;padding:10px 20px;background:rgba(245,197,24,.04);border-bottom:1px solid var(--border)}
.section-divider span{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px}
.section-divider .cnt-badge{background:rgba(245,197,24,.15);color:var(--gold);border:1px solid rgba(245,197,24,.25);border-radius:50px;padding:2px 10px;font-size:.70rem;font-weight:700}

/* ── Team members tooltip row ── */
.team-members-row{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}
.team-chip{display:inline-flex;align-items:center;gap:4px;background:rgba(100,149,237,.08);border:1px solid rgba(100,149,237,.15);border-radius:4px;padding:1px 7px;font-size:.68rem;color:#6495ed}

/* ── Empty state ── */
.empty-state{text-align:center;padding:48px 20px;color:var(--muted)}
.empty-state .icon{font-size:2.5rem;margin-bottom:12px}

/* ── Modal ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;display:flex;align-items:center;justify-content:center;padding:20px}
.modal{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:36px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--border)}
.modal-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.2rem;padding:4px 8px;border-radius:4px;transition:color .2s}
.modal-close:hover{color:var(--white)}
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
    <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="participants.php" class="active"><span class="nav-icon">👥</span> Participants</a>
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
    <h1>Participants</h1>
    <p>View, search, edit and manage all registered participants</p>
  </div>

  <?php if ($message): ?>
  <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:16px;">
    <?= $msg_type==='success'?'✅':'⚠️' ?> <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats-mini" style="margin-bottom:16px;">
    <div class="stat-mini">
      <div>
        <div class="val" style="color:var(--gold)"><?= $total_all ?></div>
        <div class="lbl">Total</div>
      </div>
    </div>
    <div class="stat-mini">
      <div>
        <div class="val" style="color:var(--success)"><?= $total_solo ?></div>
        <div class="lbl">Solo</div>
      </div>
    </div>
    <?php if ($has_type): ?>
    <div class="stat-mini">
      <div>
        <div class="val" style="color:#6495ed"><?= $total_group ?></div>
        <div class="lbl">Group</div>
      </div>
    </div>
    <?php endif; ?>
    <div style="margin-left:auto;display:flex;gap:8px;">
      <a href="../export.php?format=csv"   class="btn btn-success btn-sm">⬇️ CSV</a>
      <a href="../export.php?format=excel" class="btn btn-primary btn-sm">⬇️ Excel</a>
    </div>
  </div>

  <!-- FILTERS + TYPE TABS -->
  <div class="content-card" style="padding:16px 20px;margin-bottom:16px;">
    <form method="GET" id="filterForm">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">

        <!-- Type tabs -->
        <div class="type-tabs">
          <a href="?<?= http_build_query(array_merge($_GET,['ptype'=>'all','event_id'=>0])) ?>"
             class="type-tab <?= $type_filter==='all'?'active':'' ?>">
            All <span class="cnt"><?= $total_all ?></span>
          </a>
          <a href="?<?= http_build_query(array_merge($_GET,['ptype'=>'solo','event_id'=>0])) ?>"
             class="type-tab <?= $type_filter==='solo'?'active':'' ?>">
            🎤 Solo <span class="cnt"><?= $total_solo ?></span>
          </a>
          <?php if ($has_type): ?>
          <a href="?<?= http_build_query(array_merge($_GET,['ptype'=>'group','event_id'=>0])) ?>"
             class="type-tab <?= $type_filter==='group'?'active':'' ?>">
            👥 Group <span class="cnt"><?= $total_group ?></span>
          </a>
          <?php endif; ?>
        </div>

        <!-- Clear -->
        <?php if ($search || $event_filter || $type_filter!=='all'): ?>
        <a href="participants.php" class="btn btn-outline btn-sm">✕ Clear</a>
        <?php endif; ?>
      </div>

      <!-- Search + Event filter -->
      <div class="filter-bar">
        <input type="text" name="search" class="form-control"
          placeholder="🔍 Search name, college, email…"
          value="<?= htmlspecialchars($search) ?>">
        <select name="event_id" class="form-control">
          <option value="0" <?= !$event_filter?'selected':'' ?>>All Events</option>
          <?php foreach ($all_events as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= $event_filter==$ev['id']?'selected':'' ?>>
            <?= ($has_type ? ($ev['event_type']==='group'?'👥 ':'🎤 ') : '') . htmlspecialchars($ev['event_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="ptype" value="<?= htmlspecialchars($type_filter) ?>">
        <button type="submit" class="btn btn-primary">Filter</button>
      </div>
    </form>
  </div>

  <!-- TABLE -->
  <div class="tbl-outer">
    <div class="tbl-head">
      <h3>
        <?php
        if ($type_filter==='solo')       echo '🎤 Solo Participants';
        elseif ($type_filter==='group')  echo '👥 Group Participants';
        else                             echo '👥 All Participants';
        ?>
      </h3>
      <span class="badge badge-gold"><?= $participants->num_rows ?> found</span>
    </div>

    <?php if ($participants->num_rows === 0): ?>
    <div class="empty-state">
      <div class="icon">🔍</div>
      <p>No participants found for selected filters.</p>
    </div>

    <?php else:
      // Collect rows and separate solo/group
      $all_rows = [];
      while ($p = $participants->fetch_assoc()) $all_rows[] = $p;

      // Separate solo & group
      $solo_rows  = [];
      $group_rows = [];
      foreach ($all_rows as $p) {
          $types = explode('||', $p['event_types'] ?? '');
          $has_group = in_array('group', $types);
          $has_solo  = in_array('solo', $types);
          if ($has_group) $group_rows[] = $p;
          elseif ($has_solo) $solo_rows[] = $p;
          else $solo_rows[] = $p; // fallback
      }
      if ($type_filter==='solo')       { $sections=[['Solo Participants','solo',$solo_rows]]; }
      elseif ($type_filter==='group')  { $sections=[['Group Participants','group',$group_rows]]; }
      else                             { $sections=[['Solo Participants','solo',$solo_rows],['Group Participants','group',$group_rows]]; }
    ?>

    <div class="tbl-scroll">
      <table class="ptbl">
        <colgroup><col><col><col><col><col><col><col><col><col><col></colgroup>
        <thead>
          <tr>
            <th>#ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>College</th>
            <th>Department</th>
            <th>Stud. ID</th>
            <th>Events</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>

        <?php foreach ($sections as [$section_title, $section_type, $rows]):
          if (empty($rows)) continue;
        ?>

        <!-- Section divider -->
        <tr style="background:transparent;">
          <td colspan="10" style="padding:0;border:none;">
            <div class="section-divider">
              <span style="color:<?= $section_type==='group'?'#6495ed':'var(--success)' ?>">
                <?= $section_type==='group'?'👥':'🎤' ?> <?= $section_title ?>
              </span>
              <span class="cnt-badge"><?= count($rows) ?></span>
            </div>
          </td>
        </tr>

        <?php foreach ($rows as $p):
          $eids   = explode('||', $p['event_ids']   ?? '');
          $enames = explode('||', $p['event_names']  ?? '');
          $etypes = explode('||', $p['event_types']  ?? '');

          // Build URL params for edit/delete
          $q = http_build_query(array_filter(['search'=>$search,'event_id'=>$event_filter,'ptype'=>$type_filter]));
        ?>
        <tr>
          <td title="#<?= $p['id'] ?>">
            <span class="badge badge-blue" style="font-size:.74rem">#<?= $p['id'] ?></span>
          </td>
          <td title="<?= htmlspecialchars($p['name']) ?>">
            <strong><?= htmlspecialchars($p['name']) ?></strong>
          </td>
          <td title="<?= htmlspecialchars($p['phone']) ?>"><?= htmlspecialchars($p['phone']) ?></td>
          <td title="<?= htmlspecialchars($p['email']) ?>" style="font-size:.78rem"><?= htmlspecialchars($p['email']) ?></td>
          <td title="<?= htmlspecialchars($p['college']) ?>" style="font-size:.78rem"><?= htmlspecialchars($p['college']) ?></td>
          <td title="<?= htmlspecialchars($p['department']) ?>"><?= htmlspecialchars($p['department']) ?></td>
          <td title="<?= htmlspecialchars($p['student_id']) ?>">
            <code style="font-size:.78rem"><?= htmlspecialchars($p['student_id']) ?></code>
          </td>

          <!-- Events + team members -->
          <td class="ev-cell">
            <?php foreach ($enames as $i => $en):
              if (!$en) continue;
              $et  = $etypes[$i] ?? 'solo';
              $cls = $et==='group' ? 'group' : ($has_type ? 'solo' : 'def');
            ?>
            <span class="ev-tag <?= $cls ?>">
              <?= $et==='group'?'👥':'🎤' ?> <?= htmlspecialchars($en) ?>
            </span>
            <?php endforeach; ?>

            <?php
            // Team members for group participants
            if ($has_team_tbl && $section_type==='group'):
              $pid2 = $p['id'];
              // Get team members for this participant
              $conn2 = getConnection();
              $tmr = $conn2->query("
                  SELECT tm.member_name, tm.member_student_id, e.event_name
                  FROM team_members tm
                  JOIN events e ON tm.event_id=e.id
                  WHERE tm.participant_id=$pid2
                  ORDER BY tm.event_id, tm.id
              ");
              $members = [];
              while ($tm = $tmr->fetch_assoc()) $members[] = $tm;
              $conn2->close();
              if (!empty($members)):
            ?>
            <div class="team-members-row">
              <?php foreach ($members as $tm): ?>
              <span class="team-chip">
                👤 <?= htmlspecialchars($tm['member_name']) ?>
                <?= $tm['member_student_id'] ? ' · '.$tm['member_student_id'] : '' ?>
              </span>
              <?php endforeach; ?>
            </div>
            <?php endif; endif; ?>
          </td>

          <td style="font-size:.76rem;color:var(--muted)">
            <?= date('d M Y', strtotime($p['registered_at'])) ?>
          </td>

          <td class="act-cell">
            <div class="act-btns">
              <a href="participants.php?edit=<?= $p['id'] ?>&<?= $q ?>"
                 class="act-btn" title="Edit">✏️</a>
              <a href="participants.php?delete=<?= $p['id'] ?>&<?= $q ?>"
                 class="act-btn del" title="Delete"
                 onclick="return confirm('Delete participant #<?= $p['id'] ?>?\nThis cannot be undone.')">🗑️</a>
            </div>
          </td>
        </tr>
        <?php endforeach; // rows ?>
        <?php endforeach; // sections ?>

        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<!-- EDIT MODAL -->
<?php if ($edit_p): ?>
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h3 style="font-family:'Playfair Display',serif;">Edit Participant #<?= $edit_p['id'] ?></h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="edit_id" value="<?= $edit_p['id'] ?>">
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_p['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit_p['phone']) ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_p['email']) ?>" required>
      </div>
      <div class="form-group">
        <label>College</label>
        <input type="text" name="college" class="form-control" value="<?= htmlspecialchars($edit_p['college']) ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Department</label>
          <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($edit_p['department']) ?>" required>
        </div>
        <div class="form-group">
          <label>Student ID</label>
          <input type="text" name="student_id" class="form-control" value="<?= htmlspecialchars($edit_p['student_id']) ?>" required>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center">💾 Save</button>
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function closeModal() {
  document.getElementById('editModal').remove();
  history.replaceState(null,'','participants.php<?= $q ? '?'.$q : '' ?>');
}
</script>
<?php endif; ?>

</body>
</html>
