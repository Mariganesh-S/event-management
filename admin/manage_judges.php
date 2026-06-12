<?php
require_once '../config.php';
if (!isset($_SESSION['admin_id'])) redirect('../login.php?role=admin');

$conn = getConnection();
$msg = $msg_type = '';

// ── Delete judge ──────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $conn->query("DELETE FROM judges WHERE id=$did");
    $msg = "Judge deleted."; $msg_type = "success";
    header("Location: manage_judges.php"); exit;
}

// ── Update assigned event ─────────────────────────────────────
if (isset($_GET['assign']) && isset($_GET['event'])) {
    $jid = (int)$_GET['assign'];
    $eid = (int)$_GET['event'];
    $ev  = $eid ?: 'NULL';
    $conn->query("UPDATE judges SET assigned_event=" . ($eid ? $eid : 'NULL') . " WHERE id=$jid");
    $msg = "Event assigned."; $msg_type = "success";
    header("Location: manage_judges.php"); exit;
}

// ── Add new judge ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_judge'])) {
    $username = clean($_POST['username'] ?? '');
    $password = clean($_POST['password'] ?? '');
    $event_id = (int)($_POST['event_id'] ?? 0);

    if (!$username || !$password) {
        $msg = "Username and Password are required."; $msg_type = "danger";
    } else {
        // Check duplicate
        $exist = $conn->query("SELECT id FROM judges WHERE username='" . mysqli_real_escape_string($conn,$username) . "'")->fetch_assoc();
        if ($exist) {
            $msg = "Username '$username' already exists."; $msg_type = "danger";
        } else {
            $ev_val = $event_id ?: 'NULL';
            $conn->query("INSERT INTO judges (username, password, assigned_event)
                VALUES ('" . mysqli_real_escape_string($conn,$username) . "',
                        '" . mysqli_real_escape_string($conn,$password) . "',
                        $ev_val)");
            $msg = "✅ Judge '$username' added!"; $msg_type = "success";
        }
    }
}

// ── Update judge password ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pwd'])) {
    $jid = (int)$_POST['judge_id'];
    $pwd = clean($_POST['new_password'] ?? '');
    if (!$pwd) {
        $msg = "New password required."; $msg_type = "danger";
    } else {
        $conn->query("UPDATE judges SET password='" . mysqli_real_escape_string($conn,$pwd) . "' WHERE id=$jid");
        $msg = "✅ Password updated!"; $msg_type = "success";
    }
}

// ── Fetch data ────────────────────────────────────────────────
$events = [];
$res = $conn->query("SELECT id, event_name FROM events ORDER BY event_name");
while ($r = $res->fetch_assoc()) $events[] = $r;

$judges = [];
$res = $conn->query("
    SELECT j.*, e.event_name,
           COUNT(DISTINCT s.id) as scores_given
    FROM judges j
    LEFT JOIN events e ON j.assigned_event = e.id
    LEFT JOIN scores s ON s.judge_id = j.id
    GROUP BY j.id
    ORDER BY j.id ASC
");
while ($r = $res->fetch_assoc()) $judges[] = $r;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Judges — Admin | EventSphere</title>
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
.page-grid{display:grid;grid-template-columns:340px 1fr;gap:22px;align-items:start}

/* ── Form card ── */
.form-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;position:sticky;top:24px}
.form-card-hd{padding:16px 20px;border-bottom:1px solid var(--border);background:rgba(245,197,24,.04);display:flex;align-items:center;gap:8px}
.form-card-hd h3{font-family:'Playfair Display',serif;font-size:.98rem;font-weight:600;margin:0}
.form-card-bd{padding:20px}
.form-card-bd select.form-control option{background:#0d1235;color:var(--white)}

/* ── Judge cards ── */
.judges-grid{display:flex;flex-direction:column;gap:14px}
.judge-card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:all .2s}
.judge-card:hover{border-color:rgba(245,197,24,.2);transform:translateY(-2px)}

.judge-header{display:flex;align-items:center;gap:14px;padding:16px 18px;border-bottom:1px solid var(--border)}
.judge-avatar{width:44px;height:44px;border-radius:50%;background:rgba(245,197,24,.1);border:1px solid rgba(245,197,24,.25);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--gold);flex-shrink:0}
.judge-name{font-weight:700;font-size:.95rem;margin-bottom:2px}
.judge-meta{font-size:.73rem;color:var(--muted)}
.judge-scores{margin-left:auto;text-align:right;flex-shrink:0}
.judge-scores .sc-num{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;color:var(--gold)}
.judge-scores .sc-lbl{font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

.judge-body{padding:14px 18px;display:flex;flex-direction:column;gap:10px}

/* Assign event select */
.assign-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.assign-row label{font-size:.75rem;color:var(--muted);flex-shrink:0;font-weight:600}
.assign-row select{flex:1;min-width:140px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:var(--white);border-radius:7px;padding:6px 10px;font-size:.80rem}
.assign-row select option{background:#0d1235}

/* Password row */
.pwd-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.pwd-row input{flex:1;min-width:120px;height:34px;font-size:.80rem}
.pwd-show{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--muted);cursor:pointer;flex-shrink:0}
.pwd-show input{width:14px;height:14px;accent-color:var(--gold)}

/* Delete btn */
.del-row{display:flex;justify-content:flex-end}

/* stats */
.stats-mini{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.stat-mini{background:var(--card-bg);border:1px solid var(--border);border-radius:8px;padding:10px 18px;text-align:center}
.stat-mini .val{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700}
.stat-mini .lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

/* empty */
.empty-state{text-align:center;padding:48px;color:var(--muted);background:var(--card-bg);border:1px solid var(--border);border-radius:14px}

/* mobile */
@media(max-width:960px){
    .mobile-nav{display:flex;align-items:center;justify-content:space-between;background:var(--card-bg);border-bottom:1px solid var(--border);padding:12px 16px;position:sticky;top:0;z-index:100}
    .mobile-nav-brand{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--gold);font-weight:700}
    .page-header,.stats-mini,.page-grid{padding-left:16px;padding-right:16px}
    .page-grid{grid-template-columns:1fr;gap:16px}
    .form-card{position:static}
    .page-header{padding-top:16px}
}
@media(max-width:480px){
    .judge-header{flex-wrap:wrap}
    .judge-scores{margin-left:0}
    .assign-row,.pwd-row{flex-direction:column;align-items:flex-start}
    .assign-row select,.pwd-row input{width:100%}
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
    <a href="manage_judges.php" class="active"><span class="nav-icon">⚖️</span> Manage Judges</a>
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
    <span class="mobile-nav-brand">⚖️ Manage Judges</span>
    <div style="display:flex;gap:8px">
      <a href="dashboard.php" class="btn btn-outline btn-sm">📊</a>
      <a href="../logout.php?role=admin" class="btn btn-outline btn-sm">🚪</a>
    </div>
  </div>

  <div class="page-header" style="margin-bottom:16px;">
    <h1>⚖️ Manage Judges</h1>
    <p>Add judges, assign events and manage passwords</p>
  </div>

  <?php if ($msg): ?>
  <div style="padding:0 0 14px">
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-mini">
    <div class="stat-mini">
      <div class="val" style="color:var(--gold)"><?= count($judges) ?></div>
      <div class="lbl">Total Judges</div>
    </div>
    <div class="stat-mini">
      <div class="val" style="color:var(--success)"><?= count(array_filter($judges, fn($j)=>$j['event_name'])) ?></div>
      <div class="lbl">Assigned</div>
    </div>
    <div class="stat-mini">
      <div class="val" style="color:#6495ed"><?= array_sum(array_column($judges,'scores_given')) ?></div>
      <div class="lbl">Total Scores</div>
    </div>
  </div>

  <div class="page-grid">

    <!-- ADD JUDGE FORM -->
    <div class="form-card">
      <div class="form-card-hd">
        <span style="font-size:1.1rem">➕</span>
        <h3>Add New Judge</h3>
      </div>
      <div class="form-card-bd">
        <form method="POST">
          <input type="hidden" name="add_judge" value="1">

          <div class="form-group">
            <label>Username <span class="req">*</span></label>
            <input type="text" name="username" class="form-control"
              placeholder="e.g. judge1, dr_kumar…" required
              autocomplete="off">
          </div>

          <div class="form-group">
            <label>Password <span class="req">*</span></label>
            <div style="position:relative">
              <input type="password" name="password" id="newPwd" class="form-control"
                placeholder="Set a password" required autocomplete="new-password">
              <button type="button" onclick="togglePwd('newPwd',this)"
                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:.85rem">
                👁️
              </button>
            </div>
          </div>

          <div class="form-group">
            <label>Assign Event <span style="color:var(--muted);font-size:.75rem">(optional)</span></label>
            <select name="event_id" class="form-control">
              <option value="0">— No event assigned —</option>
              <?php foreach ($events as $ev): ?>
              <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['event_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="background:rgba(245,197,24,.06);border:1px solid rgba(245,197,24,.15);border-radius:8px;padding:10px 14px;font-size:.78rem;color:var(--muted);margin-bottom:14px;line-height:1.6">
            💡 Judges can score <strong style="color:var(--white)">all events</strong> — assigned event is their default tab
          </div>

          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;height:44px">
            ➕ Add Judge
          </button>
        </form>
      </div>
    </div>

    <!-- JUDGES LIST -->
    <div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700">All Judges</div>
        <span class="badge badge-gold"><?= count($judges) ?> judges</span>
      </div>

      <?php if (empty($judges)): ?>
      <div class="empty-state">
        <div style="font-size:2.5rem;margin-bottom:12px">⚖️</div>
        <p>No judges yet. Add your first judge!</p>
      </div>
      <?php else: ?>
      <div class="judges-grid">
        <?php foreach ($judges as $j): ?>
        <div class="judge-card">

          <!-- Header -->
          <div class="judge-header">
            <div class="judge-avatar"><?= strtoupper(substr($j['username'],0,1)) ?></div>
            <div style="flex:1;min-width:0">
              <div class="judge-name"><?= htmlspecialchars($j['username']) ?></div>
              <div class="judge-meta">
                <?php if ($j['event_name']): ?>
                  🎯 <?= htmlspecialchars($j['event_name']) ?>
                <?php else: ?>
                  <span style="color:rgba(255,255,255,.3)">No event assigned</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="judge-scores">
              <div class="sc-num"><?= $j['scores_given'] ?></div>
              <div class="sc-lbl">Scores</div>
            </div>
          </div>

          <!-- Body -->
          <div class="judge-body">

            <!-- Assign event -->
            <form method="GET" action="manage_judges.php">
              <input type="hidden" name="assign" value="<?= $j['id'] ?>">
              <div class="assign-row">
                <label>🎯 Event:</label>
                <select name="event" onchange="this.form.submit()" class="form-control" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:var(--white);border-radius:7px;padding:6px 10px;font-size:.80rem">
                  <option value="0" <?= !$j['assigned_event']?'selected':'' ?>>— Not assigned —</option>
                  <?php foreach ($events as $ev): ?>
                  <option value="<?= $ev['id'] ?>" <?= $j['assigned_event']==$ev['id']?'selected':'' ?>>
                    <?= htmlspecialchars($ev['event_name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>

            <!-- Change password -->
            <form method="POST">
              <input type="hidden" name="update_pwd" value="1">
              <input type="hidden" name="judge_id" value="<?= $j['id'] ?>">
              <div class="pwd-row">
                <label style="font-size:.75rem;color:var(--muted);font-weight:600;flex-shrink:0">🔑 Password:</label>
                <input type="password" name="new_password" class="form-control"
                  id="pwd_<?= $j['id'] ?>"
                  placeholder="New password…" autocomplete="new-password">
                <button type="button" onclick="togglePwd('pwd_<?= $j['id'] ?>',this)"
                  style="background:none;border:1px solid rgba(255,255,255,.1);border-radius:6px;color:var(--muted);cursor:pointer;padding:6px 10px;font-size:.80rem;white-space:nowrap;flex-shrink:0">
                  👁️ Show
                </button>
                <button type="submit" class="btn btn-outline btn-sm" style="flex-shrink:0;height:34px">
                  💾 Save
                </button>
              </div>
            </form>

            <!-- Current password (shown) -->
            <div style="font-size:.73rem;color:var(--muted);background:rgba(255,255,255,.03);border-radius:6px;padding:6px 10px">
              Current password: <span id="cpwd_<?= $j['id'] ?>" style="font-family:monospace;color:rgba(255,255,255,.5)">••••••••</span>
              <button onclick="revealPwd(<?= $j['id'] ?>, '<?= htmlspecialchars(addslashes($j['password'])) ?>')"
                style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:.72rem;margin-left:4px">
                👁 Show
              </button>
            </div>

            <!-- Delete -->
            <div class="del-row">
              <a href="manage_judges.php?delete=<?= $j['id'] ?>"
                 class="btn btn-outline btn-sm"
                 style="color:var(--danger);border-color:rgba(255,71,87,.3)"
                 onclick="return confirm('Delete judge \'<?= htmlspecialchars(addslashes($j['username'])) ?>\'?\n<?= $j['scores_given']>0?"⚠️ This judge has given {$j['scores_given']} scores — deleting will NOT remove scores.":"This cannot be undone." ?>')">
                🗑️ Delete Judge
              </a>
            </div>

          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>
</div>

<script>
function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.textContent = '🙈 Hide';
    } else {
        inp.type = 'password';
        btn.textContent = '👁️ Show';
    }
}

function revealPwd(id, pwd) {
    const el  = document.getElementById('cpwd_' + id);
    const btn = event.target;
    if (el.textContent === '••••••••') {
        el.textContent = pwd;
        el.style.color = 'rgba(255,255,255,.85)';
        btn.textContent = '🙈 Hide';
    } else {
        el.textContent = '••••••••';
        el.style.color = 'rgba(255,255,255,.5)';
        btn.textContent = '👁 Show';
    }
}
</script>
</body>
</html>
