<?php
require_once '../config.php';
if (!isset($_SESSION['judge_id'])) redirect('../login.php?role=judge');

$conn = getConnection();
$judge_id = (int)$_SESSION['judge_id'];

// Get judge's assigned event
$judge = $conn->query("SELECT j.*, e.event_name FROM judges j LEFT JOIN events e ON j.assigned_event = e.id WHERE j.id = $judge_id")->fetch_assoc();

$assigned_event_id   = $judge['assigned_event'] ?? null;
$assigned_event_name = $judge['event_name'] ?? 'Not Assigned';

// Participants in assigned event
$participants_scored = $participants_pending = 0;
if ($assigned_event_id) {
    $participants_in_event = $conn->query("
        SELECT COUNT(DISTINCT participant_id) as c
        FROM participant_events WHERE event_id = $assigned_event_id
    ")->fetch_assoc()['c'];

    $participants_scored = $conn->query("
        SELECT COUNT(DISTINCT participant_id) as c
        FROM scores WHERE event_id = $assigned_event_id AND judge_id = $judge_id
    ")->fetch_assoc()['c'];

    $participants_pending = $participants_in_event - $participants_scored;
}

// Recent scores by this judge
$recent_scores = $conn->query("
    SELECT s.*, p.name, p.college
    FROM scores s
    JOIN participants p ON s.participant_id = p.id
    WHERE s.judge_id = $judge_id
    ORDER BY s.scored_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Judge Dashboard — EventSphere</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="dashboard-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-name">EventSphere</div>
      <div class="brand-role">⚖️ Judge Panel</div>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="active"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="score_entry.php"><span class="nav-icon">✏️</span> Enter Scores</a>
      <a href="../index.php"><span class="nav-icon">🏠</span> Home</a>
    </nav>
    <div class="sidebar-footer">
      <div style="font-size:0.82rem;color:var(--muted);margin-bottom:4px;">Logged in as</div>
      <div style="font-weight:600;margin-bottom:10px;"><?= htmlspecialchars($judge['username']) ?></div>
      <div style="font-size:0.78rem;color:var(--gold);margin-bottom:12px;">
        📌 Assigned: <?= htmlspecialchars($assigned_event_name) ?>
      </div>
      <a href="../logout.php?role=judge" class="btn btn-outline btn-sm w-full" style="justify-content:center;">🚪 Logout</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="page-header">
      <h1>Judge Dashboard</h1>
      <p>You are assigned to judge: <strong style="color:var(--gold)"><?= htmlspecialchars($assigned_event_name) ?></strong></p>
    </div>

    <?php if (!$assigned_event_id): ?>
      <div class="alert alert-warning">⚠️ You have not been assigned to any event yet. Please contact the admin.</div>
    <?php else: ?>

    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-icon icon-blue">🎯</div>
        <div class="stat-card-info">
          <div class="value"><?= $participants_in_event ?? 0 ?></div>
          <div class="label">Total Participants</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon icon-green">✅</div>
        <div class="stat-card-info">
          <div class="value"><?= $participants_scored ?></div>
          <div class="label">Scored</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon icon-red">⏳</div>
        <div class="stat-card-info">
          <div class="value"><?= $participants_pending ?></div>
          <div class="label">Pending</div>
        </div>
      </div>
    </div>

    <!-- QUICK ACTION -->
    <div class="content-card">
      <div class="content-card-header">
        <h3>Quick Actions</h3>
      </div>
      <a href="score_entry.php" class="btn btn-primary btn-lg">
        ✏️ Enter / Update Scores
      </a>
    </div>

    <!-- RECENT SCORES -->
    <div class="content-card">
      <div class="content-card-header">
        <h3>Your Recent Scores</h3>
        <a href="score_entry.php" class="btn btn-outline btn-sm">Score Entry →</a>
      </div>
      <?php if($recent_scores->num_rows === 0): ?>
        <p style="color:var(--muted);padding:20px 0;">No scores entered yet.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Participant</th>
              <th>College</th>
              <th>Creativity</th>
              <th>Performance</th>
              <th>Presentation</th>
              <th>Total</th>
              <th>Scored At</th>
            </tr>
          </thead>
          <tbody>
            <?php while($s = $recent_scores->fetch_assoc()): ?>
            <tr>
              <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
              <td style="font-size:0.82rem;"><?= htmlspecialchars(substr($s['college'],0,22)) ?>...</td>
              <td><?= $s['creativity'] ?>/30</td>
              <td><?= $s['performance'] ?>/30</td>
              <td><?= $s['presentation'] ?>/30</td>
              <td><strong style="color:var(--gold)"><?= $s['total'] ?>/90</strong></td>
              <td style="font-size:0.8rem;color:var(--muted);"><?= date('d M, H:i', strtotime($s['scored_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <?php endif; ?>
  </main>
</div>
<?php $conn->close(); ?>
</body>
</html>
