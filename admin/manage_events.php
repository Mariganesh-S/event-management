<?php
require_once '../config.php';
if (!isset($_SESSION['admin_id'])) redirect('../login.php?role=admin');

$conn = getConnection();

// Check if new columns exist
$has_type = (bool)$conn->query("SHOW COLUMNS FROM events LIKE 'event_type'")->num_rows;

// Add new columns if missing
if (!$has_type) {
    $conn->query("ALTER TABLE events ADD COLUMN event_type ENUM('solo','group') DEFAULT 'solo'");
    $conn->query("ALTER TABLE events ADD COLUMN min_team_size INT DEFAULT 1");
    $conn->query("ALTER TABLE events ADD COLUMN max_team_size INT DEFAULT 1");
    $conn->query("ALTER TABLE events ADD COLUMN max_participants INT DEFAULT 100");
    $conn->query("ALTER TABLE events ADD COLUMN description TEXT");
}

$msg = $msg_type = '';

// ── Delete event ──────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    // Check if participants registered
    $cnt = (int)$conn->query("SELECT COUNT(*) as c FROM participant_events WHERE event_id=$did")->fetch_assoc()['c'];
    if ($cnt > 0) {
        $msg = "Cannot delete — $cnt participant(s) registered for this event.";
        $msg_type = "danger";
    } else {
        $conn->query("DELETE FROM events WHERE id=$did");
        $msg = "Event deleted."; $msg_type = "success";
        header("Location: manage_events.php?msg=deleted"); exit;
    }
}

// ── Save new / update event ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    $edit_id    = (int)($_POST['edit_id'] ?? 0);
    $name       = clean($_POST['event_name']       ?? '');
    $desc       = clean($_POST['description']       ?? '');
    $type       = clean($_POST['event_type']        ?? 'solo');
    $max_p      = max(1, (int)($_POST['max_participants'] ?? 100));
    $min_team   = max(1, (int)($_POST['min_team_size']    ?? 1));
    $max_team   = max(1, (int)($_POST['max_team_size']    ?? 1));

    if ($type === 'solo') { $min_team = 1; $max_team = 1; }
    if ($max_team < $min_team) $max_team = $min_team;

    if (!$name) {
        $msg = "Event name is required."; $msg_type = "danger";
    } elseif ($edit_id) {
        // Update
        $stmt = $conn->prepare("UPDATE events SET event_name=?, description=?, event_type=?,
            max_participants=?, min_team_size=?, max_team_size=? WHERE id=?");
        $stmt->bind_param("sssiii i", $name, $desc, $type, $max_p, $min_team, $max_team, $edit_id);
        // Fix: use correct format
        $stmt->close();
        $conn->query("UPDATE events SET
            event_name='" . mysqli_real_escape_string($conn,$name) . "',
            description='" . mysqli_real_escape_string($conn,$desc) . "',
            event_type='$type',
            max_participants=$max_p,
            min_team_size=$min_team,
            max_team_size=$max_team
            WHERE id=$edit_id");
        $msg = "✅ Event updated!"; $msg_type = "success";
    } else {
        // Insert
        $conn->query("INSERT INTO events (event_name, description, event_type, max_participants, min_team_size, max_team_size)
            VALUES (
                '" . mysqli_real_escape_string($conn,$name) . "',
                '" . mysqli_real_escape_string($conn,$desc) . "',
                '$type', $max_p, $min_team, $max_team
            )");
        $msg = "✅ Event added!"; $msg_type = "success";
    }
}

// ── Fetch edit data ───────────────────────────────────────────
$edit_ev = null;
if (isset($_GET['edit'])) {
    $edit_ev = $conn->query("SELECT * FROM events WHERE id=" . (int)$_GET['edit'])->fetch_assoc();
}

// ── Fetch all events with participant count ───────────────────
$events = [];
$q = $has_type
    ? "SELECT e.*, COUNT(pe.participant_id) as reg_count
       FROM events e LEFT JOIN participant_events pe ON e.id=pe.event_id
       GROUP BY e.id ORDER BY e.event_type, e.event_name"
    : "SELECT e.*, 'solo' as event_type, 1 as min_team_size, 1 as max_team_size,
              COUNT(pe.participant_id) as reg_count
       FROM events e LEFT JOIN participant_events pe ON e.id=pe.event_id
       GROUP BY e.id ORDER BY e.event_name";
$res = $conn->query($q);
while ($r = $res->fetch_assoc()) $events[] = $r;

$conn->close();

// Stats
$total_events = count($events);
$solo_count   = count(array_filter($events, fn($e) => ($e['event_type']??'solo')==='solo'));
$group_count  = $total_events - $solo_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Events — Admin | EventSphere</title>
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
*,*::before,*::after{box-sizing:border-box}
body{background:var(--navy)!important;color:var(--white)!important}
/* ── Grid ── */
.page-grid{display:grid;grid-template-columns:380px 1fr;gap:22px;align-items:start}

/* ── Form card ── */
.form-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;position:sticky;top:24px}
.form-card-hd{padding:16px 20px;border-bottom:1px solid var(--border);background:rgba(245,197,24,.04);display:flex;align-items:center;gap:8px}
.form-card-hd h3{font-family:'Playfair Display',serif;font-size:.98rem;font-weight:600;margin:0}
.form-card-bd{padding:20px}

/* Event type toggle */
.type-toggle{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:4px}
.tt-opt input{display:none}
.tt-opt label{display:flex;align-items:center;justify-content:center;gap:6px;padding:11px;border-radius:8px;cursor:pointer;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.03);font-size:.84rem;font-weight:600;transition:all .2s}
.tt-opt.solo  input:checked+label{border-color:var(--success);background:rgba(0,212,170,.1);color:var(--success)}
.tt-opt.group input:checked+label{border-color:#6495ed;background:rgba(100,149,237,.1);color:#6495ed}

/* Team size fields */
.team-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.team-size-wrap{display:none}
.team-size-wrap.show{display:block}

/* ── Events table ── */
.ev-tbl-wrap{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.ev-tbl-hd{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);background:rgba(245,197,24,.04)}
.ev-tbl-hd h3{font-family:'Playfair Display',serif;font-size:.98rem;font-weight:600}
.ev-tbl-scroll{overflow-x:auto}

table.etbl{width:100%;border-collapse:collapse;font-size:.83rem;min-width:560px}
table.etbl thead th{padding:10px 14px;text-align:left;font-size:.71rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);background:rgba(245,197,24,.04);border-bottom:1px solid var(--border);white-space:nowrap}
table.etbl tbody tr{border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s}
table.etbl tbody tr:hover{background:rgba(255,255,255,.03)}
table.etbl tbody td{padding:12px 14px;vertical-align:middle;color:rgba(255,255,255,.85)}

/* type pill */
.type-pill{display:inline-flex;align-items:center;gap:4px;font-size:.70rem;font-weight:700;padding:2px 9px;border-radius:50px}
.type-pill.solo{background:rgba(0,212,170,.1);color:var(--success);border:1px solid rgba(0,212,170,.25)}
.type-pill.group{background:rgba(100,149,237,.1);color:#6495ed;border:1px solid rgba(100,149,237,.25)}

/* action btns */
.act-btns{display:flex;gap:5px}
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;border:1px solid rgba(255,255,255,.1);background:transparent;text-decoration:none;font-size:.82rem;cursor:pointer;color:var(--white);transition:all .15s}
.act-btn:hover{background:rgba(255,255,255,.07)}
.act-btn.del:hover{background:rgba(255,71,87,.15);border-color:var(--danger);color:var(--danger)}
.act-btn.edit-btn:hover{background:rgba(245,197,24,.12);border-color:var(--gold);color:var(--gold)}

/* reg bar */
.reg-bar{display:flex;align-items:center;gap:6px}
.reg-bar-bg{width:60px;height:5px;background:rgba(255,255,255,.07);border-radius:50px;overflow:hidden}
.reg-bar-fill{height:100%;border-radius:50px;background:linear-gradient(90deg,var(--success),var(--gold))}

/* stats mini */
.stats-mini{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.stat-mini{background:var(--card-bg);border:1px solid var(--border);border-radius:8px;padding:10px 18px;text-align:center}
.stat-mini .val{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700}
.stat-mini .lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

/* mobile */
@media(max-width:960px){
    .mobile-nav{display:flex;align-items:center;justify-content:space-between;background:var(--card-bg);border-bottom:1px solid var(--border);padding:12px 16px;position:sticky;top:0;z-index:100}
    .mobile-nav-brand{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--gold);font-weight:700}
    .page-header,.stats-mini,.page-grid{padding-left:16px;padding-right:16px}
    .page-grid{grid-template-columns:1fr;gap:16px}
    .form-card{position:static}
    .page-header{padding-top:16px}
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
    <a href="manage_events.php" class="active"><span class="nav-icon">🎯</span> Manage Events</a>
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

<main class="main-content">

  <div class="mobile-nav">
    <span class="mobile-nav-brand">🎯 Manage Events</span>
    <div style="display:flex;gap:8px">
      <a href="dashboard.php" class="btn btn-outline btn-sm">📊</a>
      <a href="../logout.php?role=admin" class="btn btn-outline btn-sm">🚪</a>
    </div>
  </div>

  <div class="page-header" style="margin-bottom:16px;">
    <h1>🎯 Manage Events</h1>
    <p>Add, edit or delete events for your fest</p>
  </div>

  <?php if ($msg): ?>
  <div style="padding:0 0 14px">
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-mini">
    <div class="stat-mini">
      <div class="val" style="color:var(--gold)"><?= $total_events ?></div>
      <div class="lbl">Total Events</div>
    </div>
    <div class="stat-mini">
      <div class="val" style="color:var(--success)"><?= $solo_count ?></div>
      <div class="lbl">Solo Events</div>
    </div>
    <div class="stat-mini">
      <div class="val" style="color:#6495ed"><?= $group_count ?></div>
      <div class="lbl">Group Events</div>
    </div>
  </div>

  <div class="page-grid">

    <!-- ADD / EDIT FORM -->
    <div class="form-card">
      <div class="form-card-hd">
        <span style="font-size:1.1rem"><?= $edit_ev ? '✏️' : '➕' ?></span>
        <h3><?= $edit_ev ? 'Edit Event' : 'Add New Event' ?></h3>
        <?php if ($edit_ev): ?>
        <a href="manage_events.php" class="btn btn-outline btn-sm" style="margin-left:auto;">✕ Cancel</a>
        <?php endif; ?>
      </div>
      <div class="form-card-bd">
        <form method="POST" id="evForm">
          <input type="hidden" name="save_event" value="1">
          <input type="hidden" name="edit_id" value="<?= $edit_ev ? $edit_ev['id'] : 0 ?>">

          <!-- Event name -->
          <div class="form-group">
            <label>Event Name <span class="req">*</span></label>
            <input type="text" name="event_name" class="form-control"
              placeholder="e.g. Solo Singing, Group Dance…"
              value="<?= htmlspecialchars($edit_ev['event_name'] ?? '') ?>" required>
          </div>

          <!-- Description -->
          <div class="form-group">
            <label>Description <span style="color:var(--muted);font-size:.75rem">(optional)</span></label>
            <textarea name="description" class="form-control" rows="2"
              placeholder="Brief description of the event…"
              style="resize:vertical"><?= htmlspecialchars($edit_ev['description'] ?? '') ?></textarea>
          </div>

          <!-- Event type -->
          <div class="form-group">
            <label>Event Type <span class="req">*</span></label>
            <div class="type-toggle">
              <div class="tt-opt solo">
                <input type="radio" name="event_type" id="type_solo" value="solo"
                  <?= ($edit_ev['event_type'] ?? 'solo')==='solo' ? 'checked' : '' ?>
                  onchange="onTypeChange()">
                <label for="type_solo">🎤 Solo</label>
              </div>
              <div class="tt-opt group">
                <input type="radio" name="event_type" id="type_group" value="group"
                  <?= ($edit_ev['event_type'] ?? '')==='group' ? 'checked' : '' ?>
                  onchange="onTypeChange()">
                <label for="type_group">👥 Group</label>
              </div>
            </div>
          </div>

          <!-- Max participants -->
          <div class="form-group">
            <label>Max Participants</label>
            <input type="number" name="max_participants" class="form-control"
              min="1" max="1000" value="<?= $edit_ev['max_participants'] ?? 100 ?>">
          </div>

          <!-- Team size (group only) -->
          <div class="team-size-wrap <?= ($edit_ev['event_type'] ?? 'solo')==='group' ? 'show' : '' ?>"
               id="teamSizeWrap">
            <div class="form-group">
              <label>Team Size</label>
              <div class="team-fields">
                <div>
                  <label style="font-size:.75rem;color:var(--muted)">Min Members</label>
                  <input type="number" name="min_team_size" class="form-control"
                    min="2" max="20" value="<?= $edit_ev['min_team_size'] ?? 2 ?>">
                </div>
                <div>
                  <label style="font-size:.75rem;color:var(--muted)">Max Members</label>
                  <input type="number" name="max_team_size" class="form-control"
                    min="2" max="20" value="<?= $edit_ev['max_team_size'] ?? 5 ?>">
                </div>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;height:44px;margin-top:6px">
            <?= $edit_ev ? '💾 Update Event' : '➕ Add Event' ?>
          </button>
        </form>
      </div>
    </div>

    <!-- EVENTS TABLE -->
    <div class="ev-tbl-wrap">
      <div class="ev-tbl-hd">
        <h3>All Events</h3>
        <span class="badge badge-gold"><?= $total_events ?> events</span>
      </div>

      <?php if (empty($events)): ?>
      <div style="text-align:center;padding:48px;color:var(--muted)">
        <div style="font-size:2.5rem;margin-bottom:12px">🎯</div>
        <p>No events yet. Add your first event!</p>
      </div>
      <?php else: ?>
      <div class="ev-tbl-scroll">
        <table class="etbl">
          <thead>
            <tr>
              <th>#</th>
              <th>Event Name</th>
              <th>Type</th>
              <th>Team Size</th>
              <th>Max</th>
              <th>Registered</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($events as $i => $ev):
            $type   = $ev['event_type'] ?? 'solo';
            $reg    = (int)$ev['reg_count'];
            $maxp   = (int)($ev['max_participants'] ?? 100);
            $pct    = $maxp > 0 ? min(100, round($reg/$maxp*100)) : 0;
          ?>
          <tr>
            <td style="color:var(--muted);font-size:.78rem"><?= $i+1 ?></td>
            <td>
              <strong><?= htmlspecialchars($ev['event_name']) ?></strong>
              <?php if (!empty($ev['description'])): ?>
              <div style="font-size:.72rem;color:var(--muted);margin-top:2px">
                <?= htmlspecialchars(mb_strimwidth($ev['description'],0,40,'…')) ?>
              </div>
              <?php endif; ?>
            </td>
            <td>
              <span class="type-pill <?= $type ?>">
                <?= $type==='group'?'👥':'🎤' ?> <?= ucfirst($type) ?>
              </span>
            </td>
            <td style="font-size:.80rem;color:var(--muted)">
              <?php if ($type==='group'): ?>
                <?= $ev['min_team_size'] ?>–<?= $ev['max_team_size'] ?> members
              <?php else: ?>
                <span style="color:rgba(255,255,255,.3)">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.82rem"><?= $maxp ?></td>
            <td>
              <div class="reg-bar">
                <div class="reg-bar-bg">
                  <div class="reg-bar-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <span style="font-size:.80rem;font-weight:600;color:<?= $reg>0?'var(--gold)':'var(--muted)' ?>">
                  <?= $reg ?>
                </span>
              </div>
            </td>
            <td>
              <div class="act-btns">
                <a href="manage_events.php?edit=<?= $ev['id'] ?>"
                   class="act-btn edit-btn" title="Edit">✏️</a>
                <a href="manage_events.php?delete=<?= $ev['id'] ?>"
                   class="act-btn del" title="Delete"
                   onclick="return confirm('Delete \'<?= htmlspecialchars(addslashes($ev['event_name'])) ?>\'?\n<?= $reg > 0 ? "⚠️ $reg participant(s) registered — cannot delete!" : "This cannot be undone." ?>')">🗑️</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>
</div>

<script>
function onTypeChange() {
    const isGroup = document.getElementById('type_group').checked;
    const wrap    = document.getElementById('teamSizeWrap');
    wrap.classList.toggle('show', isGroup);
    if (isGroup) {
        document.querySelector('[name=min_team_size]').value = 2;
        document.querySelector('[name=max_team_size]').value = 5;
    }
}
</script>
</body>
</html>
