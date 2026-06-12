<?php
// =============================================
// leaderboard_data.php — AJAX Data Endpoint
// Returns leaderboard HTML for selected event
// Called by leaderboard.php every 10 seconds
// =============================================

require_once 'config.php';
$conn = getConnection();

$eid = (int)($_GET['event_id'] ?? 0);
if (!$eid) { echo '<div class="no-scores"><div class="icon">⚠️</div><p>No event selected.</p></div>'; exit; }

// Event info
$ev = $conn->query("SELECT * FROM events WHERE id=$eid")->fetch_assoc();
if (!$ev) { echo '<div class="no-scores"><div class="icon">⚠️</div><p>Event not found.</p></div>'; exit; }

// Judges who scored in this event
$judges = [];
$res = $conn->query("SELECT DISTINCT j.id, j.username FROM scores s JOIN judges j ON s.judge_id=j.id WHERE s.event_id=$eid ORDER BY j.id");
while ($r = $res->fetch_assoc()) $judges[] = $r;
$jcount = count($judges);

// Leaderboard: participants with total scores
$participants = [];
$res = $conn->query("
    SELECT p.id, p.name, p.college, p.department, p.student_id,
           SUM(s.creativity)   AS total_c,
           SUM(s.performance)  AS total_p,
           SUM(s.presentation) AS total_pr,
           SUM(s.total)        AS grand_total,
           COUNT(s.id)         AS score_count
    FROM participant_events pe
    JOIN participants p ON pe.participant_id = p.id
    LEFT JOIN scores s ON s.participant_id = p.id AND s.event_id = $eid
    WHERE pe.event_id = $eid
    GROUP BY p.id
    ORDER BY grand_total DESC, p.name ASC
");
while ($r = $res->fetch_assoc()) $participants[] = $r;

$conn->close();

$total_p  = count($participants);
$max_score = $jcount * 90;
$scored    = count(array_filter($participants, fn($p) => $p['grand_total'] > 0));

// Medals
$medals = ['🥇','🥈','🥉'];
$medal_class = ['rank1','rank2','rank3'];

// Initial letter for avatar
function initials($name) {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $i .= strtoupper(substr($parts[1], 0, 1));
    return $i;
}
?>

<!-- Event heading -->
<div style="margin-bottom:24px;">
  <div class="event-title">🏆 <?= htmlspecialchars($ev['event_name']) ?></div>
  <div class="event-subtitle">
    <?= $total_p ?> participant<?= $total_p!=1?'s':'' ?> &bull;
    <?= $jcount ?> judge<?= $jcount!=1?'s':'' ?> scoring &bull;
    Max <?= $max_score ?> pts &bull;
    <?= $scored ?> scored
    &bull; <span style="color:var(--success);font-size:0.8rem;">● Live</span>
  </div>
</div>

<?php if ($scored === 0): ?>
<!-- No scores yet -->
<div class="no-scores">
  <div class="icon">⏳</div>
  <p>Judges are scoring — results will appear here automatically.</p>
  <p style="margin-top:6px;font-size:0.8rem;">Page refreshes every 10 seconds.</p>
</div>

<?php else: ?>

<!-- ── PODIUM (top 3) ── -->
<?php
$top3 = array_filter($participants, fn($p) => $p['grand_total'] > 0);
$top3 = array_slice(array_values($top3), 0, 3);
?>
<?php if (count($top3) >= 2): ?>
<div class="podium-row">
  <?php
  // Reorder: 2nd, 1st, 3rd for visual podium
  $order = count($top3) >= 3 ? [1,0,2] : [0,1];
  foreach($order as $i):
    if (!isset($top3[$i])) continue;
    $p   = $top3[$i];
    $cls = $medal_class[$i];
    $pct = $max_score > 0 ? round($p['grand_total'] / $max_score * 100) : 0;
  ?>
  <div class="podium-col">
    <span class="podium-medal"><?= $medals[$i] ?></span>
    <div class="podium-avatar <?= $cls ?>"><?= initials($p['name']) ?></div>
    <div class="podium-name"><?= htmlspecialchars($p['name']) ?></div>
    <div class="podium-college"><?= htmlspecialchars(mb_strimwidth($p['college'],0,28,'…')) ?></div>
    <div class="podium-score <?= $cls ?>"><?= number_format((float)$p['grand_total'],0) ?></div>
    <div style="font-size:0.72rem;color:var(--muted);margin-bottom:4px;">/ <?= $max_score ?> pts</div>
    <div class="podium-bar <?= $cls ?>"></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── FULL LEADERBOARD TABLE ── -->
<div class="lb-card">
  <div class="lb-card-header">
    <h3>Full Standings</h3>
    <span style="font-size:0.78rem;color:var(--muted);">
      Updated: <?= date('H:i:s') ?>
    </span>
  </div>
  <table class="lb-table">
    <thead>
      <tr>
        <th style="width:48px;text-align:center;">Rank</th>
        <th>Participant</th>
        <th>College</th>
        <th style="width:80px;text-align:center;">🎨</th>
        <th style="width:80px;text-align:center;">🎭</th>
        <th style="width:80px;text-align:center;">📊</th>
        <th style="min-width:200px;">Score</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($participants as $rank => $p):
        $has     = (float)$p['grand_total'] > 0;
        $pct     = ($max_score > 0 && $has) ? round($p['grand_total'] / $max_score * 100) : 0;
        $rowCls  = $rank < 3 && $has ? 'top'.($rank+1) : '';
      ?>
      <tr class="<?= $rowCls ?>">

        <!-- Rank -->
        <td class="rank-cell">
          <?php if($rank < 3 && $has): ?>
            <?= $medals[$rank] ?>
          <?php else: ?>
            <span class="rank-num"><?= $rank+1 ?></span>
          <?php endif; ?>
        </td>

        <!-- Name -->
        <td>
          <div style="font-weight:700;font-size:0.88rem;"><?= htmlspecialchars($p['name']) ?></div>
          <div style="font-size:0.73rem;color:var(--muted);">
            <?= htmlspecialchars($p['department']) ?> &bull; <?= htmlspecialchars($p['student_id']) ?>
          </div>
        </td>

        <!-- College -->
        <td style="font-size:0.80rem;color:var(--muted);max-width:140px;">
          <?= htmlspecialchars(mb_strimwidth($p['college'],0,24,'…')) ?>
        </td>

        <!-- Creativity -->
        <td style="text-align:center;">
          <?php if($has): ?>
            <span style="font-size:0.88rem;"><?= (int)$p['total_c'] ?></span>
            <span style="font-size:0.70rem;color:var(--muted);">/<?= $jcount*30 ?></span>
          <?php else: ?>
            <span style="color:rgba(255,255,255,0.18);">—</span>
          <?php endif; ?>
        </td>

        <!-- Performance -->
        <td style="text-align:center;">
          <?php if($has): ?>
            <span style="font-size:0.88rem;"><?= (int)$p['total_p'] ?></span>
            <span style="font-size:0.70rem;color:var(--muted);">/<?= $jcount*30 ?></span>
          <?php else: ?>
            <span style="color:rgba(255,255,255,0.18);">—</span>
          <?php endif; ?>
        </td>

        <!-- Presentation -->
        <td style="text-align:center;">
          <?php if($has): ?>
            <span style="font-size:0.88rem;"><?= (int)$p['total_pr'] ?></span>
            <span style="font-size:0.70rem;color:var(--muted);">/<?= $jcount*30 ?></span>
          <?php else: ?>
            <span style="color:rgba(255,255,255,0.18);">—</span>
          <?php endif; ?>
        </td>

        <!-- Score bar + total -->
        <td>
          <?php if($has): ?>
          <div class="score-bar-wrap">
            <div class="score-bar-bg">
              <div class="score-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="score-val">
              <?= number_format((float)$p['grand_total'],0) ?>
              <span style="font-size:0.68rem;color:var(--muted);font-weight:400;">/<?= $max_score ?></span>
            </span>
          </div>
          <?php else: ?>
            <span style="color:rgba(255,255,255,0.2);font-size:0.82rem;">Not scored yet</span>
          <?php endif; ?>
        </td>

      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php endif; // scored > 0 ?>
