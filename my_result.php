<?php
// =============================================
// my_result.php — Student Result Portal
// Public page — no login required
// Student enters email + student ID to view
// =============================================

require_once 'config.php';
$conn = getConnection();

$student   = null;
$events    = [];
$scores    = [];
$checkin   = null;
$error     = '';
$searched  = false;

// ── Handle search ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = clean($_POST['email']      ?? '');
    $student_id = clean($_POST['student_id'] ?? '');
    $searched   = true;

    if (!$email || !$student_id) {
        $error = "Please enter both Email and Student ID.";
    } else {
        // Find participant
        $stmt = $conn->prepare(
            "SELECT * FROM participants WHERE email = ? AND student_id = ? LIMIT 1"
        );
        $stmt->bind_param("ss", $email, $student_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) {
            $error = "No registration found. Please check your Email and Student ID.";
        } else {
            $pid = $student['id'];

            // Registered events
            $res = $conn->query("
                SELECT e.id, e.event_name, e.event_type,
                       e.min_team_size, e.max_team_size
                FROM participant_events pe
                JOIN events e ON pe.event_id = e.id
                WHERE pe.participant_id = $pid
                ORDER BY e.event_name
            ");
            while ($r = $res->fetch_assoc()) $events[] = $r;

            // Scores + rank for each event
            foreach ($events as &$ev) {
                $eid = $ev['id'];

                // This student's total score in this event
                $sc = $conn->query("
                    SELECT SUM(s.creativity)   AS tc,
                           SUM(s.performance)  AS tp,
                           SUM(s.presentation) AS tpr,
                           SUM(s.total)        AS grand,
                           COUNT(s.id)         AS judge_count
                    FROM scores s
                    WHERE s.participant_id=$pid AND s.event_id=$eid
                ")->fetch_assoc();

                $ev['score']       = $sc;
                $ev['has_score']   = $sc && (int)$sc['grand'] > 0;

                // Rank: how many participants scored higher?
                if ($ev['has_score']) {
                    $rank_res = $conn->query("
                        SELECT COUNT(DISTINCT participant_id) + 1 AS rank
                        FROM (
                            SELECT participant_id, SUM(total) AS grand
                            FROM scores WHERE event_id=$eid
                            GROUP BY participant_id
                            HAVING grand > {$sc['grand']}
                        ) AS higher
                    ")->fetch_assoc();
                    $ev['rank']       = (int)($rank_res['rank'] ?? 1);
                    $ev['rank_label'] = match($ev['rank']) {
                        1 => '🥇 1st Place',
                        2 => '🥈 2nd Place',
                        3 => '🥉 3rd Place',
                        default => $ev['rank'] . 'th Place'
                    };

                    // Total judges in this event
                    $jcount = (int)$conn->query("
                        SELECT COUNT(DISTINCT judge_id) as c FROM scores WHERE event_id=$eid
                    ")->fetch_assoc()['c'];
                    $ev['max_score'] = $jcount * 90;
                    $ev['pct']       = $ev['max_score'] > 0
                        ? round($sc['grand'] / $ev['max_score'] * 100)
                        : 0;
                }

                // Team members (group events)
                $ev['members'] = [];
                if (($ev['event_type'] ?? 'solo') === 'group') {
                    $has_team = $conn->query("SHOW TABLES LIKE 'team_members'")->num_rows > 0;
                    if ($has_team) {
                        $mr = $conn->query("
                            SELECT member_name, member_student_id, member_department
                            FROM team_members
                            WHERE participant_id=$pid AND event_id=$eid
                        ");
                        while ($m = $mr->fetch_assoc()) $ev['members'][] = $m;
                    }
                }
            }
            unset($ev);

            // Check-in status
            $has_ci = $conn->query("SHOW TABLES LIKE 'checkins'")->num_rows > 0;
            if ($has_ci) {
                $checkin = $conn->query("
                    SELECT * FROM checkins WHERE participant_id=$pid LIMIT 1
                ")->fetch_assoc();
            }
        }
    }
}

$conn->close();

// Helpers
function rankColor($rank) {
    return match($rank) { 1=>'#f5c518', 2=>'#c0c0c0', 3=>'#cd7f32', default=>'#6495ed' };
}
function initials($n) {
    $p = explode(' ', trim($n));
    return strtoupper(substr($p[0],0,1) . (isset($p[1]) ? substr($p[1],0,1) : ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Result — EventSphere</title>
<link rel="stylesheet" href="css/style.css">
<style>
*,*::before,*::after{box-sizing:border-box}
body{min-height:100vh;overflow-x:hidden}

/* ── Page layout ── */
.page-wrap{
    max-width:760px;margin:0 auto;
    padding:80px 20px 60px;
}

/* ── Search card ── */
.search-card{
    background:var(--card-bg);
    border:1px solid var(--border);
    border-radius:16px;padding:36px;
    position:relative;overflow:hidden;
    margin-bottom:28px;
}
.search-card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
    background:linear-gradient(90deg,var(--gold),var(--accent),var(--gold));
}
.search-title{
    font-family:'Playfair Display',serif;
    font-size:1.4rem;font-weight:700;
    margin-bottom:4px;
}
.search-sub{font-size:0.85rem;color:var(--muted);margin-bottom:24px}

.search-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:560px){.search-row{grid-template-columns:1fr}}

/* ── Student profile card ── */
.profile-card{
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:14px;overflow:hidden;margin-bottom:20px;
}
.profile-header{
    background:linear-gradient(135deg,#0a1535,#0f1f6a);
    padding:24px 28px;display:flex;align-items:center;gap:20px;
    flex-wrap:wrap;
}
.profile-avatar{
    width:64px;height:64px;border-radius:50%;
    background:rgba(245,197,24,0.15);
    border:2px solid rgba(245,197,24,0.4);
    display:flex;align-items:center;justify-content:center;
    font-family:'Playfair Display',serif;
    font-size:1.4rem;font-weight:700;color:var(--gold);
    flex-shrink:0;
}
.profile-name{
    font-family:'Playfair Display',serif;
    font-size:1.3rem;font-weight:700;margin-bottom:4px;
}
.profile-sub{font-size:0.82rem;color:rgba(255,255,255,0.55)}
.profile-pid{
    margin-left:auto;
    background:rgba(245,197,24,0.1);
    border:1px solid rgba(245,197,24,0.25);
    border-radius:8px;padding:8px 16px;text-align:center;flex-shrink:0;
}
.profile-pid .pid-num{
    font-family:'Playfair Display',serif;
    font-size:1.4rem;font-weight:700;color:var(--gold);
}
.profile-pid .pid-lbl{font-size:0.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px}

.profile-details{
    display:grid;grid-template-columns:repeat(2,1fr);
    gap:0;
}
.profile-detail-item{
    padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04);
    border-right:1px solid rgba(255,255,255,0.04);
}
.profile-detail-item:nth-child(even){border-right:none}
.profile-detail-item:last-child,.profile-detail-item:nth-last-child(2){border-bottom:none}
.detail-lbl{font-size:0.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px}
.detail-val{font-size:0.88rem;font-weight:600}

/* Check-in status */
.checkin-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:4px 12px;border-radius:50px;font-size:0.78rem;font-weight:700;
    margin-left:auto;
}
.checkin-badge.done{background:rgba(0,212,170,0.1);border:1px solid rgba(0,212,170,0.3);color:var(--success)}
.checkin-badge.pending{background:rgba(255,71,87,0.08);border:1px solid rgba(255,71,87,0.2);color:var(--danger)}

/* ── Event result card ── */
.event-card{
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:14px;overflow:hidden;margin-bottom:16px;
    transition:all 0.2s;
}
.event-card:hover{border-color:rgba(245,197,24,0.2);transform:translateY(-2px)}

.event-card-header{
    padding:16px 20px;display:flex;align-items:center;
    justify-content:space-between;gap:12px;flex-wrap:wrap;
    border-bottom:1px solid var(--border);
}
.event-name{
    font-family:'Playfair Display',serif;font-size:1rem;font-weight:600;
}
.event-type-pill{
    font-size:0.70rem;font-weight:700;padding:2px 9px;border-radius:50px;
}
.type-solo{background:rgba(0,212,170,0.1);color:var(--success);border:1px solid rgba(0,212,170,0.2)}
.type-group{background:rgba(100,149,237,0.1);color:#6495ed;border:1px solid rgba(100,149,237,0.2)}

/* Score section */
.score-section{padding:18px 20px}

/* Score bars */
.score-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.score-bar-label{font-size:0.78rem;color:var(--muted);width:90px;flex-shrink:0}
.score-bar-bg{flex:1;height:8px;background:rgba(255,255,255,0.07);border-radius:50px;overflow:hidden}
.score-bar-fill{height:100%;border-radius:50px;transition:width 0.6s ease}
.fill-c {background:linear-gradient(90deg,#1D9E75,#5DCAA5)}
.fill-p {background:linear-gradient(90deg,#185FA5,#378ADD)}
.fill-pr{background:linear-gradient(90deg,#534AB7,#7F77DD)}
.score-bar-val{font-size:0.80rem;font-weight:600;color:var(--white);min-width:40px;text-align:right}

/* Total + Rank */
.score-summary{
    display:flex;align-items:center;justify-content:space-between;
    gap:12px;margin-top:16px;padding-top:14px;
    border-top:1px solid rgba(255,255,255,0.06);
    flex-wrap:wrap;
}
.total-score{
    display:flex;align-items:baseline;gap:6px;
}
.total-num{
    font-family:'Playfair Display',serif;
    font-size:2rem;font-weight:700;color:var(--gold);
}
.total-denom{font-size:0.82rem;color:var(--muted)}

.rank-badge{
    display:flex;align-items:center;gap:8px;
    background:rgba(245,197,24,0.07);
    border:1px solid rgba(245,197,24,0.2);
    border-radius:10px;padding:8px 16px;
}
.rank-icon{font-size:1.5rem}
.rank-text{font-size:0.88rem;font-weight:700}
.rank-sub{font-size:0.72rem;color:var(--muted)}

/* Progress bar overall */
.overall-pct{
    display:flex;align-items:center;gap:8px;
    font-size:0.78rem;color:var(--muted);margin-top:6px;
}
.pct-bar-bg{flex:1;height:5px;background:rgba(255,255,255,0.07);border-radius:50px;overflow:hidden}
.pct-bar-fill{height:100%;border-radius:50px;background:linear-gradient(90deg,var(--gold),#ff6b35)}

/* No score yet */
.no-score{
    padding:20px;text-align:center;color:var(--muted);font-size:0.85rem;
}
.no-score .icon{font-size:2rem;margin-bottom:8px}

/* Team members */
.team-wrap{
    padding:0 20px 16px;
    border-top:1px solid rgba(255,255,255,0.04);
    padding-top:14px;
}
.team-title{font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:8px}
.team-member{
    display:inline-flex;align-items:center;gap:6px;
    background:rgba(100,149,237,0.08);border:1px solid rgba(100,149,237,0.18);
    border-radius:6px;padding:4px 10px;font-size:0.78rem;color:#6495ed;
    margin:3px;
}

/* QR section */
.qr-section{
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:14px;padding:24px;text-align:center;margin-bottom:20px;
}
.qr-section h3{font-family:'Playfair Display',serif;font-size:1rem;margin-bottom:6px}
.qr-section p{font-size:0.82rem;color:var(--muted);margin-bottom:16px}
.qr-img{
    width:150px;height:150px;border-radius:10px;
    background:white;padding:8px;display:inline-block;
}

/* Section heading */
.section-heading{
    font-family:'Playfair Display',serif;
    font-size:1.1rem;font-weight:700;
    margin-bottom:14px;color:var(--white);
    display:flex;align-items:center;gap:8px;
}

/* Not found state */
.not-found{
    text-align:center;padding:48px 20px;
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:14px;
}
.not-found .icon{font-size:3rem;margin-bottom:12px}
.not-found h3{font-family:'Playfair Display',serif;font-size:1.2rem;margin-bottom:8px}
.not-found p{color:var(--muted);font-size:0.85rem}

@media(max-width:560px){
    .profile-header{flex-direction:column;align-items:flex-start}
    .profile-pid{margin-left:0}
    .profile-details{grid-template-columns:1fr}
    .profile-detail-item{border-right:none}
    .score-summary{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="index.php" class="nav-brand">Event<span>Sphere</span></a>
  <div class="nav-links">
    <a href="index.php">🏠 Register</a>
    <a href="leaderboard.php" target="_blank">📺 Leaderboard</a>
    <a href="login.php?role=admin">Admin</a>
  </div>
</nav>

<div class="page-wrap">

  <!-- SEARCH CARD -->
  <div class="search-card">
    <div class="search-title">🎓 My Result</div>
    <div class="search-sub">Enter your registered Email and Student ID to view your scores and rank</div>

    <?php if ($error): ?>
      <div class="alert alert-danger" style="margin-bottom:18px;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="#" id="searchForm">
      <div class="search-row">
        <div class="form-group" style="margin-bottom:0">
          <label>Email Address <span class="req">*</span></label>
          <input type="email" name="email" class="form-control"
            placeholder="you@college.edu"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Student ID <span class="req">*</span></label>
          <input type="text" name="student_id" class="form-control"
            placeholder="e.g. 21CS001"
            value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-full"
        style="justify-content:center;margin-top:16px;">
        🔍 View My Result
      </button>
    </form>
  </div>

  <?php if ($student): ?>

  <!-- PROFILE CARD -->
  <div class="section-heading">👤 Your Registration</div>
  <div class="profile-card">
    <div class="profile-header">
      <div class="profile-avatar"><?= initials($student['name']) ?></div>
      <div>
        <div class="profile-name"><?= htmlspecialchars($student['name']) ?></div>
        <div class="profile-sub"><?= htmlspecialchars($student['college']) ?></div>
        <div class="profile-sub"><?= htmlspecialchars($student['department']) ?></div>
      </div>
      <div class="profile-pid">
        <div class="pid-num">#<?= $student['id'] ?></div>
        <div class="pid-lbl">Participant ID</div>
      </div>
    </div>

    <div class="profile-details">
      <div class="profile-detail-item">
        <div class="detail-lbl">Phone</div>
        <div class="detail-val"><?= htmlspecialchars($student['phone']) ?></div>
      </div>
      <div class="profile-detail-item">
        <div class="detail-lbl">Student ID</div>
        <div class="detail-val"><?= htmlspecialchars($student['student_id']) ?></div>
      </div>
      <div class="profile-detail-item">
        <div class="detail-lbl">Registered On</div>
        <div class="detail-val"><?= date('d M Y, h:i A', strtotime($student['registered_at'])) ?></div>
      </div>
      <div class="profile-detail-item">
        <div class="detail-lbl">Check-in Status</div>
        <div class="detail-val">
          <?php if ($checkin): ?>
            <span class="checkin-badge done">
              ✅ Checked In — <?= date('h:i A', strtotime($checkin['checked_at'])) ?>
            </span>
          <?php else: ?>
            <span class="checkin-badge pending">⏳ Not Checked In</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- QR TICKET -->
  <div class="qr-section">
    <h3>📱 Your QR Entry Ticket</h3>
    <p>Show this at the event entrance for check-in</p>
    <img src="qr_generate.php?pid=<?= $student['id'] ?>"
         alt="QR Code" class="qr-img">
    <div style="font-size:0.75rem;color:var(--muted);margin-top:10px;">
      Participant #<?= $student['id'] ?> — <?= htmlspecialchars($student['name']) ?>
    </div>
  </div>

  <!-- EVENT RESULTS -->
  <?php if (!empty($events)): ?>
  <div class="section-heading">🏆 Your Event Results</div>

  <?php foreach ($events as $ev):
    $type_lbl  = ($ev['event_type'] ?? 'solo') === 'group' ? 'Group' : 'Solo';
    $type_cls  = ($ev['event_type'] ?? 'solo') === 'group' ? 'type-group' : 'type-solo';
    $has_score = $ev['has_score'] ?? false;
    $sc        = $ev['score'] ?? null;
  ?>
  <div class="event-card">

    <!-- Event header -->
    <div class="event-card-header">
      <div>
        <div class="event-name"><?= htmlspecialchars($ev['event_name']) ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span class="event-type-pill <?= $type_cls ?>"><?= $type_lbl ?></span>
        <?php if ($has_score): ?>
          <span class="badge badge-green" style="font-size:0.72rem;">✓ Scored</span>
        <?php else: ?>
          <span class="badge badge-red" style="font-size:0.72rem;">⏳ Awaiting Score</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Score section -->
    <div class="score-section">
      <?php if ($has_score && $sc): ?>

        <!-- Bar charts -->
        <?php
        $jmax = $ev['max_score'] / 3; // per-criterion max
        ?>
        <div class="score-bar-row">
          <span class="score-bar-label">🎨 Creativity</span>
          <div class="score-bar-bg">
            <div class="score-bar-fill fill-c"
              style="width:<?= $jmax>0 ? round($sc['tc']/$jmax*100) : 0 ?>%"></div>
          </div>
          <span class="score-bar-val"><?= (int)$sc['tc'] ?><span style="color:var(--muted);font-size:0.70rem;">/<?= (int)$jmax ?></span></span>
        </div>
        <div class="score-bar-row">
          <span class="score-bar-label">🎭 Performance</span>
          <div class="score-bar-bg">
            <div class="score-bar-fill fill-p"
              style="width:<?= $jmax>0 ? round($sc['tp']/$jmax*100) : 0 ?>%"></div>
          </div>
          <span class="score-bar-val"><?= (int)$sc['tp'] ?><span style="color:var(--muted);font-size:0.70rem;">/<?= (int)$jmax ?></span></span>
        </div>
        <div class="score-bar-row">
          <span class="score-bar-label">📊 Presentation</span>
          <div class="score-bar-bg">
            <div class="score-bar-fill fill-pr"
              style="width:<?= $jmax>0 ? round($sc['tpr']/$jmax*100) : 0 ?>%"></div>
          </div>
          <span class="score-bar-val"><?= (int)$sc['tpr'] ?><span style="color:var(--muted);font-size:0.70rem;">/<?= (int)$jmax ?></span></span>
        </div>

        <!-- Overall progress -->
        <div class="overall-pct">
          <span>Overall</span>
          <div class="pct-bar-bg">
            <div class="pct-bar-fill" style="width:<?= $ev['pct'] ?>%"></div>
          </div>
          <span style="color:var(--white);font-weight:600;"><?= $ev['pct'] ?>%</span>
        </div>

        <!-- Total + Rank -->
        <div class="score-summary">
          <div class="total-score">
            <span class="total-num"><?= (int)$sc['grand'] ?></span>
            <span class="total-denom">/ <?= $ev['max_score'] ?> pts</span>
          </div>

          <div class="rank-badge">
            <span class="rank-icon"><?= explode(' ', $ev['rank_label'])[0] ?></span>
            <div>
              <div class="rank-text" style="color:<?= rankColor($ev['rank']) ?>">
                <?= implode(' ', array_slice(explode(' ', $ev['rank_label']), 1)) ?>
              </div>
              <div class="rank-sub">in <?= htmlspecialchars($ev['event_name']) ?></div>
            </div>
          </div>
        </div>

        <!-- Judge count note -->
        <div style="font-size:0.74rem;color:var(--muted);margin-top:8px;text-align:right;">
          Scored by <?= (int)$sc['judge_count'] ?> judge<?= $sc['judge_count']!=1?'s':'' ?>
        </div>

      <?php else: ?>
        <div class="no-score">
          <div class="icon">⏳</div>
          <div>Judges haven't scored this event yet.</div>
          <div style="font-size:0.78rem;margin-top:4px;">Check back after the event!</div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Team members (group events) -->
    <?php if (!empty($ev['members'])): ?>
    <div class="team-wrap">
      <div class="team-title">👥 Your Team Members</div>
      <?php foreach ($ev['members'] as $m): ?>
      <span class="team-member">
        👤 <?= htmlspecialchars($m['member_name']) ?>
        <?= $m['member_student_id'] ? ' · ' . htmlspecialchars($m['member_student_id']) : '' ?>
      </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- /event-card -->
  <?php endforeach; ?>

  <?php else: ?>
  <div class="not-found">
    <div class="icon">📋</div>
    <h3>No Events Found</h3>
    <p>You haven't registered for any events yet.</p>
  </div>
  <?php endif; ?>

  <?php elseif ($searched && !$error): ?>
  <!-- Should not reach here — error handled above -->
  <?php endif; ?>

</div><!-- /page-wrap -->

<footer>
  <p><?= SITE_NAME ?> &mdash; <?= SITE_TAGLINE ?></p>
  <p style="margin-top:8px;">
    <a href="index.php">Register</a> &bull;
    <a href="leaderboard.php">Leaderboard</a> &bull;
    <a href="login.php?role=admin">Admin</a>
  </p>
</footer>

</body>
</html>
