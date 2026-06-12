<?php
// =============================================
// export.php — Participants Data Export
// Supports: CSV, Excel (HTML table format)
// Admin only
// =============================================

require_once 'config.php';
if (!isset($_SESSION['admin_id'])) redirect('login.php?role=admin');

$conn   = getConnection();
$format = clean($_GET['format']   ?? 'csv');   // csv | excel
$event  = (int)($_GET['event_id'] ?? 0);        // 0 = all events
$type   = clean($_GET['type']     ?? 'all');    // all | scored | unscored

// ── Fetch events for filter ───────────────────────────────────
$events_list = [];
$res = $conn->query("SELECT id, event_name FROM events ORDER BY event_name");
while ($r = $res->fetch_assoc()) $events_list[] = $r;

// ── Build query ───────────────────────────────────────────────
$where = "WHERE 1=1";
if ($event) $where .= " AND pe.event_id = $event";
if ($type === 'scored')   $where .= " AND EXISTS (SELECT 1 FROM scores s WHERE s.participant_id=p.id" . ($event?" AND s.event_id=$event":"") . ")";
if ($type === 'unscored') $where .= " AND NOT EXISTS (SELECT 1 FROM scores s WHERE s.participant_id=p.id" . ($event?" AND s.event_id=$event":"") . ")";

$query = "
    SELECT p.id, p.name, p.phone, p.email, p.college, p.department, p.student_id,
           p.registered_at,
           GROUP_CONCAT(DISTINCT e.event_name ORDER BY e.event_name SEPARATOR ' | ') AS events,
           COALESCE(SUM(s.total),0) AS total_score,
           COUNT(DISTINCT s.judge_id) AS judges_scored
    FROM participants p
    JOIN participant_events pe ON p.id = pe.participant_id
    JOIN events e ON pe.event_id = e.id
    LEFT JOIN scores s ON s.participant_id = p.id " . ($event ? "AND s.event_id = $event" : "") . "
    $where
    GROUP BY p.id
    ORDER BY p.registered_at DESC
";

$result = $conn->query($query);
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;
$conn->close();

// Event name for filename
$event_label = 'All_Events';
foreach ($events_list as $ev) {
    if ($ev['id'] == $event) {
        $event_label = preg_replace('/\s+/', '_', $ev['event_name']);
        break;
    }
}

$filename  = 'EventSphere_Participants_' . $event_label . '_' . date('d-M-Y');
$col_heads = ['#ID', 'Name', 'Phone', 'Email', 'College', 'Department', 'Student ID', 'Registered Events', 'Total Score', 'Judges Scored', 'Registered At'];

// ══════════════════════════════════════════════
// CSV EXPORT
// ══════════════════════════════════════════════
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

    // Header row
    fputcsv($out, $col_heads);

    // Data rows
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['name'],
            $r['phone'],
            $r['email'],
            $r['college'],
            $r['department'],
            $r['student_id'],
            $r['events'],
            $r['total_score'],
            $r['judges_scored'],
            date('d M Y H:i', strtotime($r['registered_at'])),
        ]);
    }
    fclose($out);
    exit;
}

// ══════════════════════════════════════════════
// EXCEL EXPORT (HTML table — opens in Excel)
// ══════════════════════════════════════════════
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
               xmlns:x="urn:schemas-microsoft-com:office:excel"
               xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8">
    <style>
      body{font-family:Arial,sans-serif;font-size:11pt}
      table{border-collapse:collapse;width:100%}
      th{
        background:#0a0f2c;color:#f5c518;
        font-weight:bold;font-size:11pt;
        border:1px solid #ccc;padding:8px 12px;
        text-align:left;
      }
      td{border:1px solid #ddd;padding:6px 10px;font-size:10pt;vertical-align:top;}
      tr:nth-child(even) td{background:#f8f8ff}
      tr:hover td{background:#fffbe6}
      .title-row td{
        background:#0a0f2c;color:#f5c518;
        font-size:14pt;font-weight:bold;
        border:none;padding:10px 12px;
      }
      .sub-row td{
        background:#1a2a6c;color:#ffffff;
        font-size:9pt;border:none;padding:4px 12px;
      }
      .gold{color:#b8860b;font-weight:bold}
      .score-cell{text-align:center;font-weight:bold;color:#0a0f2c}
    </style></head><body>';

    echo '<table>';

    // Title
    echo '<tr class="title-row"><td colspan="' . count($col_heads) . '">
        EventSphere — Participants Export
    </td></tr>';
    echo '<tr class="sub-row"><td colspan="' . count($col_heads) . '">
        Generated: ' . date('d F Y, H:i') . ' &nbsp;|&nbsp;
        Event: ' . ($event_label === 'All_Events' ? 'All Events' : str_replace('_',' ',$event_label)) . ' &nbsp;|&nbsp;
        Total: ' . count($rows) . ' participants
    </td></tr>';

    // Empty row
    echo '<tr><td colspan="' . count($col_heads) . '" style="border:none;height:8px;"></td></tr>';

    // Header
    echo '<tr>';
    foreach ($col_heads as $h) {
        echo '<th>' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr>';

    // Data
    foreach ($rows as $i => $r) {
        $bg = ($i % 2 === 0) ? '' : 'background:#f8f8ff';
        echo '<tr style="' . $bg . '">';
        echo '<td style="text-align:center;color:#555">' . $r['id'] . '</td>';
        echo '<td><b>' . htmlspecialchars($r['name']) . '</b></td>';
        echo '<td>' . htmlspecialchars($r['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($r['email']) . '</td>';
        echo '<td>' . htmlspecialchars($r['college']) . '</td>';
        echo '<td>' . htmlspecialchars($r['department']) . '</td>';
        echo '<td style="font-family:monospace">' . htmlspecialchars($r['student_id']) . '</td>';
        echo '<td style="color:#1a2a6c">' . htmlspecialchars($r['events']) . '</td>';
        echo '<td class="score-cell">' . ($r['total_score'] > 0 ? $r['total_score'] : '—') . '</td>';
        echo '<td style="text-align:center">' . $r['judges_scored'] . '</td>';
        echo '<td style="color:#666;font-size:9pt">' . date('d M Y H:i', strtotime($r['registered_at'])) . '</td>';
        echo '</tr>';
    }

    // Summary row
    echo '<tr>';
    echo '<td colspan="8" style="text-align:right;font-weight:bold;background:#f0f0f0;border-top:2px solid #999;">
        Total Participants:
    </td>';
    echo '<td style="text-align:center;font-weight:bold;background:#f0f0f0;border-top:2px solid #999;">' . count($rows) . '</td>';
    echo '<td colspan="2" style="background:#f0f0f0;border-top:2px solid #999;"></td>';
    echo '</tr>';

    echo '</table></body></html>';
    exit;
}

// ══════════════════════════════════════════════
// EXPORT UI PAGE (if no format selected)
// ══════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Export Participants — Admin | EventSphere</title>
<link rel="stylesheet" href="css/style.css">
<style>
*,*::before,*::after{box-sizing:border-box}
.sidebar{width:240px}
.main-content{margin-left:240px;width:calc(100% - 240px);padding:28px;overflow-x:hidden}

.export-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}

/* Format card */
.format-card{
    background:var(--card-bg);border:2px solid var(--border);
    border-radius:14px;padding:28px;text-align:center;
    cursor:pointer;transition:all 0.2s;position:relative;overflow:hidden;
}
.format-card::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,transparent 60%,rgba(245,197,24,0.04));
    pointer-events:none;
}
.format-card:hover{border-color:var(--gold);transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,0.3)}
.format-card.csv:hover{border-color:var(--success)}
.format-card .icon{font-size:2.8rem;margin-bottom:14px;display:block}
.format-card h3{font-family:'Playfair Display',serif;font-size:1.2rem;margin-bottom:8px}
.format-card p{font-size:0.82rem;color:var(--muted);line-height:1.6;margin-bottom:18px}

/* Filter section */
.filter-section{
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:14px;padding:24px;margin-bottom:24px;
}
.filter-section h3{
    font-family:'Playfair Display',serif;font-size:1rem;
    margin-bottom:18px;padding-bottom:12px;
    border-bottom:1px solid var(--border);
}
.filter-row{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:6px;flex:1;min-width:160px}
.filter-group label{font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);font-weight:600}

/* Preview table */
.preview-section{
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:14px;overflow:hidden;
}
.preview-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;border-bottom:1px solid var(--border);
    background:rgba(245,197,24,0.04);
}
.preview-header h3{font-family:'Playfair Display',serif;font-size:1rem}
.preview-wrap{overflow-x:auto;max-height:340px;overflow-y:auto}
table{width:100%;border-collapse:collapse;font-size:0.81rem}
thead th{
    padding:10px 12px;text-align:left;
    font-size:0.71rem;font-weight:700;text-transform:uppercase;
    letter-spacing:0.6px;color:var(--muted);
    background:rgba(245,197,24,0.04);
    border-bottom:1px solid var(--border);
    white-space:nowrap;position:sticky;top:0;
}
tbody tr{border-bottom:1px solid rgba(255,255,255,0.04)}
tbody tr:hover{background:rgba(255,255,255,0.025)}
tbody td{padding:10px 12px;color:rgba(255,255,255,0.82);vertical-align:middle}
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
    <a href="admin/dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="admin/participants.php"><span class="nav-icon">👥</span> Participants</a>
    <a href="admin/results.php"><span class="nav-icon">🏆</span> Results</a>
    <a href="export.php" class="active"><span class="nav-icon">📥</span> Export</a>
    <a href="index.php"><span class="nav-icon">🏠</span> Home Page</a>
  </nav>
  <div class="sidebar-footer">
    <div style="font-size:.80rem;color:var(--muted);margin-bottom:10px;">
      Logged in as <strong style="color:var(--white)"><?= htmlspecialchars($_SESSION['admin_user']) ?></strong>
    </div>
    <a href="logout.php?role=admin" class="btn btn-outline btn-sm w-full" style="justify-content:center;">🚪 Logout</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main-content">

  <div class="page-header">
    <h1>Export Participants</h1>
    <p>Download participant data as CSV or Excel</p>
  </div>

  <!-- FILTERS -->
  <div class="filter-section">
    <h3>🔧 Filter Options</h3>
    <form method="GET" id="filterForm">
      <div class="filter-row">
        <div class="filter-group">
          <label>Event</label>
          <select name="event_id" class="form-control" onchange="this.form.submit()">
            <option value="0" <?= $event==0?'selected':'' ?>>All Events</option>
            <?php foreach($events_list as $ev): ?>
            <option value="<?= $ev['id'] ?>" <?= $event==$ev['id']?'selected':'' ?>>
              <?= htmlspecialchars($ev['event_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select name="type" class="form-control" onchange="this.form.submit()">
            <option value="all"      <?= $type=='all'?'selected':''      ?>>All Participants</option>
            <option value="scored"   <?= $type=='scored'?'selected':''   ?>>Scored Only</option>
            <option value="unscored" <?= $type=='unscored'?'selected':'' ?>>Not Scored Yet</option>
          </select>
        </div>
        <div class="filter-group" style="justify-content:flex-end;">
          <label>&nbsp;</label>
          <span class="badge badge-gold" style="padding:8px 16px;font-size:0.85rem;">
            <?= count($rows) ?> participants found
          </span>
        </div>
      </div>
    </form>
  </div>

  <!-- EXPORT FORMAT CARDS -->
  <div class="export-grid">

    <!-- CSV -->
    <div class="format-card csv" onclick="downloadFile('csv')">
      <span class="icon">📄</span>
      <h3>CSV Download</h3>
      <p>Comma-separated values — opens in Excel, Google Sheets, any spreadsheet app. Lightweight & universal.</p>
      <button type="button" class="btn btn-success w-full" style="justify-content:center;">
        ⬇️ Download CSV
      </button>
    </div>

    <!-- Excel -->
    <div class="format-card" onclick="downloadFile('excel')">
      <span class="icon">📊</span>
      <h3>Excel Download</h3>
      <p>Formatted Excel file with colored headers, EventSphere branding, and summary row. Professional look!</p>
      <button type="button" class="btn btn-primary w-full" style="justify-content:center;">
        ⬇️ Download Excel (.xls)
      </button>
    </div>

  </div>

  <!-- PREVIEW TABLE -->
  <div class="preview-section">
    <div class="preview-header">
      <h3>👀 Preview — First <?= min(10, count($rows)) ?> of <?= count($rows) ?> rows</h3>
      <div style="display:flex;gap:8px;">
        <a href="?format=csv&event_id=<?= $event ?>&type=<?= $type ?>"
           class="btn btn-success btn-sm">⬇️ CSV</a>
        <a href="?format=excel&event_id=<?= $event ?>&type=<?= $type ?>"
           class="btn btn-primary btn-sm">⬇️ Excel</a>
      </div>
    </div>
    <div class="preview-wrap">
      <?php if (empty($rows)): ?>
        <div style="text-align:center;padding:40px;color:var(--muted);">No participants found for selected filters.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#ID</th><th>Name</th><th>Phone</th><th>Email</th>
            <th>College</th><th>Dept</th><th>Student ID</th>
            <th>Events</th><th>Score</th><th>Registered</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach(array_slice($rows,0,10) as $r): ?>
          <tr>
            <td><span class="badge badge-blue" style="font-size:.74rem">#<?= $r['id'] ?></span></td>
            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
            <td><?= htmlspecialchars($r['phone']) ?></td>
            <td style="font-size:.78rem"><?= htmlspecialchars($r['email']) ?></td>
            <td style="font-size:.78rem"><?= htmlspecialchars(mb_strimwidth($r['college'],0,20,'…')) ?></td>
            <td><?= htmlspecialchars($r['department']) ?></td>
            <td><code style="font-size:.78rem"><?= htmlspecialchars($r['student_id']) ?></code></td>
            <td>
              <?php foreach(array_filter(explode(' | ',$r['events']??'')) as $ev): ?>
                <span class="badge badge-gold" style="font-size:.68rem;margin:1px"><?= htmlspecialchars($ev) ?></span>
              <?php endforeach; ?>
            </td>
            <td style="text-align:center">
              <?php if($r['total_score'] > 0): ?>
                <strong style="color:var(--gold)"><?= $r['total_score'] ?></strong>
              <?php else: ?>
                <span style="color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.76rem;color:var(--muted)"><?= date('d M Y', strtotime($r['registered_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if(count($rows) > 10): ?>
        <div style="text-align:center;padding:12px;color:var(--muted);font-size:.80rem;">
          ... and <?= count($rows)-10 ?> more rows in the downloaded file
        </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

</main>
</div>

<script>
function downloadFile(format) {
    const event = document.querySelector('[name=event_id]').value;
    const type  = document.querySelector('[name=type]').value;
    window.location.href = `export.php?format=${format}&event_id=${event}&type=${type}`;
}
</script>
</body>
</html>
