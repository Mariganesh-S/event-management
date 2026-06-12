<?php
require_once '../config.php';
if (!isset($_SESSION['admin_id'])) redirect('../login.php?role=admin');

$conn = getConnection();

// Check new columns
$has_new_cols = (bool)($conn->query("SHOW COLUMNS FROM events LIKE 'event_type'")->num_rows);
$has_team_tbl = (bool)($conn->query("SHOW TABLES LIKE 'team_members'")->num_rows);

// All events
$events_list = [];
$q = $has_new_cols
    ? "SELECT * FROM events ORDER BY event_type, event_name"
    : "SELECT *,'solo' AS event_type,1 AS min_team_size,1 AS max_team_size FROM events ORDER BY event_name";
$res = $conn->query($q);
while ($r = $res->fetch_assoc()) $events_list[] = $r;

// All judges
$judges_list = [];
$res = $conn->query("SELECT id, username FROM judges ORDER BY id");
while ($r = $res->fetch_assoc()) $judges_list[] = $r;

// ── Get results for one event ─────────────────────────────────
function getResults($conn, $eid, $judges_list, $has_team_tbl, $event_type) {
    // All participants in this event
    $parts = [];
    $res = $conn->query("
        SELECT p.id, p.name, p.college, p.department, p.student_id
        FROM participant_events pe
        JOIN participants p ON pe.participant_id = p.id
        WHERE pe.event_id = $eid
        ORDER BY p.name
    ");
    while ($r = $res->fetch_assoc()) $parts[] = $r;

    foreach ($parts as &$p) {
        $pid = $p['id'];

        // Team members for group events
        $p['members'] = [];
        if ($event_type === 'group' && $has_team_tbl) {
            $mr = $conn->query("
                SELECT member_name, member_student_id, member_department
                FROM team_members
                WHERE participant_id=$pid AND event_id=$eid
                ORDER BY id
            ");
            while ($m = $mr->fetch_assoc()) $p['members'][] = $m;
        }

        // Each judge's score
        $p['scores']          = [];
        $p['grand_total']     = 0;
        $p['grand_creativity']   = 0;
        $p['grand_performance']  = 0;
        $p['grand_presentation'] = 0;
        $p['judges_count']    = 0;

        foreach ($judges_list as $j) {
            $jid = $j['id'];
            $sr  = $conn->query("
                SELECT creativity, performance, presentation, total
                FROM scores
                WHERE participant_id=$pid AND event_id=$eid AND judge_id=$jid
                LIMIT 1
            ");
            $s = $sr ? $sr->fetch_assoc() : null;
            $p['scores'][$jid] = $s;
            if ($s) {
                $p['grand_total']        += (int)$s['total'];
                $p['grand_creativity']   += (int)$s['creativity'];
                $p['grand_performance']  += (int)$s['performance'];
                $p['grand_presentation'] += (int)$s['presentation'];
                $p['judges_count']++;
            }
        }
    }
    unset($p);

    // Sort by grand total DESC
    usort($parts, fn($a,$b) => $b['grand_total'] - $a['grand_total']);
    return $parts;
}

// Judges who scored in an event
function activeJudges($conn, $eid, $judges_list) {
    $active = [];
    foreach ($judges_list as $j) {
        $r = $conn->query("SELECT COUNT(*) as c FROM scores WHERE event_id=$eid AND judge_id={$j['id']}");
        if ($r && $r->fetch_assoc()['c'] > 0) $active[] = $j;
    }
    return $active;
}

$medals = ['🥇','🥈','🥉'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Results — Admin | EventSphere</title>
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
body{overflow-x:hidden}
/* ── section type divider ── */
.type-divider{display:flex;align-items:center;gap:14px;margin:32px 0 20px}
.type-divider-line{flex:1;height:1px;background:var(--border)}
.type-divider-label{display:inline-flex;align-items:center;gap:8px;font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;white-space:nowrap}
.type-divider-label.solo  {color:var(--success)}
.type-divider-label.group {color:#6495ed}

/* ── event result card ── */
.result-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;margin-bottom:24px;overflow:hidden}
.result-card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.result-card-header h3{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;margin-bottom:3px}
.result-card-header p{font-size:.78rem;color:var(--muted)}
.result-card-body{padding:0}

/* ── mini podium ── */
.mini-podium{display:flex;gap:10px;padding:16px 22px;background:rgba(245,197,24,.03);border-bottom:1px solid var(--border);flex-wrap:wrap}
.podium-chip{display:flex;align-items:center;gap:10px;background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:9px 14px;min-width:180px;flex:1}
.podium-chip.p1{border-color:rgba(245,197,24,.35);background:rgba(245,197,24,.06)}
.podium-chip.p2{border-color:rgba(192,192,192,.25)}
.podium-chip.p3{border-color:rgba(205,127,50,.25)}
.podium-medal{font-size:1.5rem;flex-shrink:0}
.podium-name{font-weight:700;font-size:.85rem}
.podium-sub{font-size:.74rem;color:var(--muted);margin-top:2px}
.podium-pts{font-size:.88rem;font-weight:700;color:var(--gold);margin-left:auto;flex-shrink:0}

/* ── table ── */
.tbl-wrap{overflow-x:auto}
.rtbl{width:100%;border-collapse:collapse;font-size:.81rem;min-width:700px}
.rtbl thead tr.r1{background:rgba(245,197,24,.06);border-bottom:1px solid var(--border)}
.rtbl thead tr.r2{background:rgba(245,197,24,.03);border-bottom:1px solid var(--border)}
.rtbl thead th{padding:9px 11px;text-align:center;font-size:.70rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);white-space:nowrap}
.rtbl thead th.left{text-align:left}
.rtbl tbody tr{border-bottom:1px solid rgba(255,255,255,.04)}
.rtbl tbody tr:hover{background:rgba(255,255,255,.025)}
.rtbl tbody td{padding:10px 11px;vertical-align:middle;color:rgba(255,255,255,.85);text-align:center;white-space:nowrap}
.rtbl tbody td.left{text-align:left}

/* judge columns */
.jth{background:rgba(100,149,237,.07);color:#6495ed!important;border-left:1px solid rgba(100,149,237,.12)}
.jtd{border-left:1px solid rgba(100,149,237,.08)}
.jtd-last{border-right:1px solid rgba(100,149,237,.08)}
.j-sub{font-weight:700;font-size:.82rem;color:#6495ed}

/* grand total columns */
.gth{background:rgba(245,197,24,.09);color:var(--gold)!important;border-left:2px solid rgba(245,197,24,.2)}
.gtd{background:rgba(245,197,24,.04);border-left:2px solid rgba(245,197,24,.12);font-weight:700;color:var(--gold)!important;font-size:.95rem}

/* participant info */
.p-name{font-weight:700;font-size:.88rem}
.p-sub{font-size:.72rem;color:var(--muted);margin-top:2px}

/* group team members */
.team-members-cell{text-align:left!important;white-space:normal!important;min-width:140px}
.member-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(100,149,237,.08);border:1px solid rgba(100,149,237,.18);border-radius:5px;padding:2px 8px;font-size:.70rem;color:#6495ed;margin:2px 2px 2px 0;white-space:nowrap}

/* no score */
.ns{color:rgba(255,255,255,.18);font-size:.85rem}

/* rank */
.rank-medal{font-size:1.2rem;line-height:1}
.rank-num{font-size:.80rem;color:var(--muted)}

/* section label */
.sec-lbl{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:6px;padding:5px 12px;font-size:.72rem;color:var(--muted);margin:12px 22px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}

/* empty */
.empty-msg{text-align:center;padding:36px;color:var(--muted);font-size:.88rem}
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
    <a href="participants.php"><span class="nav-icon">👥</span> Participants</a>
    <a href="results.php" class="active"><span class="nav-icon">🏆</span> Results</a>
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
    <h1>Event Results</h1>
    <p>Solo &amp; Group results — per judge scores + grand totals</p>
  </div>

  <?php
  // Split events by type
  $solo_events  = array_filter($events_list, fn($e)=>($e['event_type']??'solo')==='solo');
  $group_events = array_filter($events_list, fn($e)=>($e['event_type']??'solo')==='group');

  // Render a result card for one event
  function renderEvent($conn, $e, $judges_list, $has_team_tbl, $medals) {
      $eid        = (int)$e['id'];
      $event_type = $e['event_type'] ?? 'solo';
      $is_group   = ($event_type === 'group');

      $active_judges = activeJudges($conn, $eid, $judges_list);
      $jcount        = count($active_judges);
      $participants  = getResults($conn, $eid, $judges_list, $has_team_tbl, $event_type);
      $total_p       = count($participants);
      $any_score     = count(array_filter($participants, fn($p)=>$p['judges_count']>0));

      $type_badge = $is_group
          ? '<span style="background:rgba(100,149,237,.12);color:#6495ed;border:1px solid rgba(100,149,237,.25);border-radius:5px;padding:3px 9px;font-size:.70rem;font-weight:700;">👥 Group</span>'
          : '<span style="background:rgba(0,212,170,.1);color:var(--success);border:1px solid rgba(0,212,170,.2);border-radius:5px;padding:3px 9px;font-size:.70rem;font-weight:700;">🎤 Solo</span>';
  ?>
  <div class="result-card">

    <!-- Card Header -->
    <div class="result-card-header">
      <div>
        <h3>🏆 <?= htmlspecialchars($e['event_name']) ?> <?= $type_badge ?></h3>
        <p>
          <?= $total_p ?> participant<?= $total_p!=1?'s':'' ?>
          &bull; <?= $jcount ?> judge<?= $jcount!=1?'s':'' ?> scored
          &bull; Max score: <?= $jcount*90 ?> pts
        </p>
      </div>
      <span class="badge <?= $any_score?'badge-green':'badge-red' ?>">
        <?= $any_score ? 'Results Ready' : 'No Scores Yet' ?>
      </span>
    </div>

    <?php if (!$any_score): ?>
      <div class="empty-msg">⏳ No scores entered for this event yet.</div>
    <?php else: ?>

    <!-- Mini Podium -->
    <div class="mini-podium">
      <?php foreach(array_slice($participants,0,3) as $i=>$p):
        if (!$p['judges_count']) continue;
        $cls = ['p1','p2','p3'][$i]; ?>
      <div class="podium-chip <?= $cls ?>">
        <span class="podium-medal"><?= ['🥇','🥈','🥉'][$i] ?></span>
        <div>
          <div class="podium-name"><?= htmlspecialchars($p['name']) ?></div>
          <div class="podium-sub"><?= htmlspecialchars(mb_strimwidth($p['college'],0,28,'…')) ?></div>
          <?php if ($is_group && !empty($p['members'])): ?>
          <div class="podium-sub" style="color:#6495ed;margin-top:3px;">
            +<?= count($p['members']) ?> member<?= count($p['members'])>1?'s':'' ?>
          </div>
          <?php endif; ?>
        </div>
        <span class="podium-pts"><?= $p['grand_total'] ?>pts</span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Table Label -->
    <div class="sec-lbl">📋 Detailed Score Breakdown</div>

    <!-- Scores Table -->
    <div class="tbl-wrap">
      <table class="rtbl">
        <thead>
          <!-- Row 1: Group headers -->
          <tr class="r1">
            <th class="left" rowspan="2" style="width:36px;">Rank</th>
            <th class="left" rowspan="2" style="min-width:130px;">
              <?= $is_group ? 'Team Leader' : 'Participant' ?>
            </th>
            <?php if ($is_group): ?>
            <th class="left" rowspan="2" style="min-width:140px;">Team Members</th>
            <?php endif; ?>
            <th class="left" rowspan="2" style="min-width:100px;">College</th>

            <?php foreach($active_judges as $j): ?>
            <th class="jth" colspan="4">⚖️ <?= htmlspecialchars($j['username']) ?></th>
            <?php endforeach; ?>

            <th class="gth" colspan="4">⭐ Grand Total</th>
            <th rowspan="2" style="width:90px;text-align:center;color:var(--muted);font-size:.70rem;">📄 Cert</th>
          </tr>

          <!-- Row 2: Sub-columns -->
          <tr class="r2">
            <?php foreach($active_judges as $j): ?>
            <th class="jth" style="font-size:.65rem;">🎨 Creat.</th>
            <th class="jth" style="font-size:.65rem;">🎭 Perf.</th>
            <th class="jth" style="font-size:.65rem;">📊 Pres.</th>
            <th class="jth" style="font-size:.65rem;border-right:1px solid rgba(100,149,237,.15);">Sub</th>
            <?php endforeach; ?>
            <th class="gth" style="font-size:.65rem;">🎨</th>
            <th class="gth" style="font-size:.65rem;">🎭</th>
            <th class="gth" style="font-size:.65rem;">📊</th>
            <th class="gth" style="font-size:.65rem;">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($participants as $rank=>$p):
            $has = $p['judges_count']>0; ?>
          <tr>
            <!-- Rank -->
            <td>
              <?php if($rank<3 && $has): ?>
                <span class="rank-medal"><?= $medals[$rank] ?></span>
              <?php else: ?>
                <span class="rank-num"><?= $rank+1 ?></span>
              <?php endif; ?>
            </td>

            <!-- Name / Leader -->
            <td class="left">
              <div class="p-name"><?= htmlspecialchars($p['name']) ?></div>
              <div class="p-sub"><?= htmlspecialchars($p['department']) ?> · <?= htmlspecialchars($p['student_id']) ?></div>
              <?php if($is_group): ?>
              <div style="margin-top:3px;">
                <span style="font-size:.68rem;background:rgba(245,197,24,.12);color:var(--gold);padding:1px 7px;border-radius:50px;font-weight:700;">Leader</span>
              </div>
              <?php endif; ?>
            </td>

            <!-- Team members (group only) -->
            <?php if($is_group): ?>
            <td class="left team-members-cell">
              <?php if (!empty($p['members'])): ?>
                <?php foreach($p['members'] as $m): ?>
                <span class="member-chip">👤 <?= htmlspecialchars($m['member_name']) ?><?= $m['member_student_id'] ? ' · '.$m['member_student_id'] : '' ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="ns">No members</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>

            <!-- College -->
            <td class="left" style="font-size:.78rem;color:var(--muted);max-width:120px;overflow:hidden;text-overflow:ellipsis;">
              <?= htmlspecialchars(mb_strimwidth($p['college'],0,22,'…')) ?>
            </td>

            <!-- Per-judge scores -->
            <?php foreach($active_judges as $j):
              $s = $p['scores'][$j['id']] ?? null; ?>
            <td class="jtd"><?= $s ? $s['creativity']   : '<span class="ns">—</span>' ?></td>
            <td class="jtd"><?= $s ? $s['performance']  : '<span class="ns">—</span>' ?></td>
            <td class="jtd"><?= $s ? $s['presentation'] : '<span class="ns">—</span>' ?></td>
            <td class="jtd jtd-last">
              <?php if($s): ?>
                <span class="j-sub"><?= $s['total'] ?><span style="font-size:.68rem;color:var(--muted);font-weight:400;">/90</span></span>
              <?php else: ?>
                <span class="ns">—</span>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>

            <!-- Grand total -->
            <td class="gtd" style="font-size:.82rem;font-weight:400;color:rgba(255,255,255,.7)!important;">
              <?= $has ? $p['grand_creativity']   : '<span class="ns">—</span>' ?>
            </td>
            <td class="gtd" style="font-size:.82rem;font-weight:400;color:rgba(255,255,255,.7)!important;">
              <?= $has ? $p['grand_performance']  : '<span class="ns">—</span>' ?>
            </td>
            <td class="gtd" style="font-size:.82rem;font-weight:400;color:rgba(255,255,255,.7)!important;">
              <?= $has ? $p['grand_presentation'] : '<span class="ns">—</span>' ?>
            </td>
            <td class="gtd" style="font-size:1.05rem;">
              <?php if($has): ?>
                <?= $p['grand_total'] ?>
                <span style="font-size:.68rem;color:var(--muted);font-weight:400;">/<?= $jcount*90 ?></span>
              <?php else: ?>
                <span class="ns">—</span>
              <?php endif; ?>
            </td>

            <!-- Certificate buttons -->
            <td style="text-align:left;padding:8px;min-width:130px;">

              <?php
              $base_url = '../certificate.php?pid='.$p['id'].'&event_id='.$eid;
              $cert_style_winner = 'display:inline-flex;align-items:center;gap:3px;background:rgba(245,197,24,.12);border:1px solid rgba(245,197,24,.3);color:var(--gold);border-radius:5px;padding:3px 8px;font-size:.68rem;font-weight:700;text-decoration:none;margin:2px 0;white-space:nowrap;';
              $cert_style_part   = 'display:inline-flex;align-items:center;gap:3px;background:rgba(100,149,237,.1);border:1px solid rgba(100,149,237,.25);color:#6495ed;border-radius:5px;padding:3px 8px;font-size:.68rem;font-weight:600;text-decoration:none;margin:2px 0;white-space:nowrap;';
              ?>

              <!-- Leader certificate -->
              <div style="margin-bottom:4px;">
                <div style="font-size:.63rem;color:var(--muted);margin-bottom:3px;">
                  <?= $is_group ? '👑 Leader' : '👤 Participant' ?>
                </div>
                <?php if($rank < 3 && $has): ?>
                <a href="<?= $base_url ?>&type=winner" target="_blank" style="<?= $cert_style_winner ?>">
                  🏆 Winner
                </a><br>
                <?php endif; ?>
                <a href="<?= $base_url ?>&type=participation" target="_blank" style="<?= $cert_style_part ?>">
                  📄 Cert
                </a>
              </div>

              <?php if($is_group && !empty($p['members'])): ?>
              <!-- Team member certificates -->
              <div style="border-top:1px solid rgba(255,255,255,.06);padding-top:5px;margin-top:2px;">
                <div style="font-size:.63rem;color:var(--muted);margin-bottom:3px;">👥 Members</div>
                <?php foreach($p['members'] as $m):
                  $mn  = urlencode($m['member_name']);
                  $ms  = urlencode($m['member_student_id'] ?? '');
                  $md  = urlencode($m['member_department'] ?? '');
                  $murl = $base_url . '&member_name='.$mn.'&member_sid='.$ms.'&member_dept='.$md;
                ?>
                <div style="margin-bottom:3px;">
                  <span style="font-size:.65rem;color:rgba(255,255,255,.5);display:block;margin-bottom:2px;">
                    <?= htmlspecialchars(mb_strimwidth($m['member_name'],0,14,'…')) ?>
                  </span>
                  <?php if($rank < 3 && $has): ?>
                  <a href="<?= $murl ?>&type=winner" target="_blank" style="<?= $cert_style_winner ?>">
                    🏆 Winner
                  </a><br>
                  <?php endif; ?>
                  <a href="<?= $murl ?>&type=participation" target="_blank" style="<?= $cert_style_part ?>">
                    📄 Cert
                  </a>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div><!-- /tbl-wrap -->

    <?php endif; // any_score ?>
  </div><!-- /result-card -->
  <?php } // end renderEvent ?>

  <!-- ══════════════════════════════════
       SOLO EVENTS SECTION
  ══════════════════════════════════ -->
  <?php if (!empty($solo_events)): ?>
  <div class="type-divider">
    <div class="type-divider-line"></div>
    <div class="type-divider-label solo">🎤 Solo Event Results</div>
    <div class="type-divider-line"></div>
  </div>
  <?php foreach($solo_events as $e):
    renderEvent($conn, $e, $judges_list, $has_team_tbl, $medals);
  endforeach; ?>
  <?php endif; ?>

  <!-- ══════════════════════════════════
       GROUP EVENTS SECTION
  ══════════════════════════════════ -->
  <?php if (!empty($group_events)): ?>
  <div class="type-divider" style="margin-top:40px;">
    <div class="type-divider-line"></div>
    <div class="type-divider-label group">👥 Group Event Results</div>
    <div class="type-divider-line"></div>
  </div>
  <?php foreach($group_events as $e):
    renderEvent($conn, $e, $judges_list, $has_team_tbl, $medals);
  endforeach; ?>
  <?php endif; ?>

</main>
</div>
<?php $conn->close(); ?>
</body>
</html>
