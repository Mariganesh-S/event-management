<?php
require_once 'config.php';
$conn = getConnection();

// Check new columns exist
$has_new_cols = (bool)($conn->query("SHOW COLUMNS FROM events LIKE 'event_type'")->num_rows);
$has_team_tbl = (bool)($conn->query("SHOW TABLES LIKE 'team_members'")->num_rows);

// Fetch events
$solo_events = $group_events = $all_events = [];
$q = $has_new_cols
    ? "SELECT * FROM events ORDER BY event_name"
    : "SELECT id,event_name,description,max_participants,'solo' AS event_type,1 AS min_team_size,1 AS max_team_size,created_at FROM events ORDER BY event_name";

$res = $conn->query($q);
while ($r = $res->fetch_assoc()) {
    $all_events[] = $r;
    if (($r['event_type']??'solo')==='group') $group_events[]=$r;
    else $solo_events[]=$r;
}
$event_map = [];
foreach ($all_events as $e) $event_map[(int)$e['id']] = $e;

$total_participants = (int)$conn->query("SELECT COUNT(*) as c FROM participants")->fetch_assoc()['c'];

// ── POST handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name       = clean($_POST['name']       ?? '');
    $phone      = clean($_POST['phone']      ?? '');
    $email      = clean($_POST['email']      ?? '');
    $college    = clean($_POST['college']    ?? '');
    $department = clean($_POST['department'] ?? '');
    $student_id = clean($_POST['student_id'] ?? '');
    $sel_events = array_unique(array_map('intval',
                  array_filter($_POST['events'] ?? [], fn($v)=>$v>0)));
    $error = '';

    // 1. Basic
    if (!$name||!$phone||!$email||!$college||!$department||!$student_id)
        $error = "Please fill in all required fields.";
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL))
        $error = "Please enter a valid email address.";
    elseif (!$sel_events)
        $error = "Please select at least 1 event.";
    elseif (count($sel_events)>2)
        $error = "You can register for a maximum of 2 events only.";

    // 2. Duplicate email
    if (!$error) {
        $chk = $conn->prepare("SELECT id FROM participants WHERE email=? LIMIT 1");
        $chk->bind_param("s",$email); $chk->execute(); $chk->store_result();
        if ($chk->num_rows>0) $error="This email is already registered.";
        $chk->close();
    }

    // 3. Group team validation — KEY FIX: use string key always
    if (!$error && $has_new_cols && $has_team_tbl) {
        foreach ($sel_events as $eid) {
            $ev = $event_map[$eid] ?? null;
            if (!$ev || ($ev['event_type']??'solo')!=='group') continue;
            $min  = max(1,(int)($ev['min_team_size']??1));
            if ($min<=1) continue;
            $need = $min-1;

            // ALWAYS use string key — $_POST keys are always strings
            $raw    = (array)($_POST['team_members'][(string)$eid] ?? []);
            $filled = array_values(array_filter(array_map('trim',$raw)));

            if (count($filled) < $need) {
                $have  = count($filled);
                $error = "'{$ev['event_name']}' needs {$min} members total "
                       . "(you + {$need} more). You filled {$have} — please fill all required fields.";
                break;
            }
        }
    }

    // 4. Save
    if (!$error) {
        $ins = $conn->prepare("INSERT INTO participants (name,phone,email,college,department,student_id) VALUES (?,?,?,?,?,?)");
        $ins->bind_param("ssssss",$name,$phone,$email,$college,$department,$student_id);
        if ($ins->execute()) {
            $pid=(int)$conn->insert_id;
            $ins->close();
            foreach ($sel_events as $eid) {
                $pe=$conn->prepare("INSERT IGNORE INTO participant_events (participant_id,event_id) VALUES (?,?)");
                $pe->bind_param("ii",$pid,$eid); $pe->execute(); $pe->close();

                if ($has_team_tbl) {
                    $ev=$event_map[$eid]??null;
                    if ($ev && ($ev['event_type']??'solo')==='group') {
                        $key    = (string)$eid;
                        $mnames = array_map('trim',(array)($_POST['team_members'][$key]??[]));
                        $mids   = (array)($_POST['team_member_ids'][$key]??[]);
                        $mdepts = (array)($_POST['team_member_depts'][$key]??[]);
                        foreach ($mnames as $idx=>$mn) {
                            if (!trim($mn)) continue;
                            $mn=clean($mn); $mi=clean($mids[$idx]??''); $md=clean($mdepts[$idx]??'');
                            $tm=$conn->prepare("INSERT INTO team_members (participant_id,event_id,member_name,member_student_id,member_department) VALUES (?,?,?,?,?)");
                            $tm->bind_param("iisss",$pid,$eid,$mn,$mi,$md);
                            $tm->execute(); $tm->close();
                        }
                    }
                }
            }
            // ── Send confirmation email ──────────────────
            $mail_sent = false;
            if (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
                require_once __DIR__ . '/mailer.php';
                $ev_names = [];
                foreach ($sel_events as $eid) {
                    $ev = $event_map[$eid] ?? null;
                    if ($ev) $ev_names[] = $ev['event_name'];
                }
                $mail_sent = sendRegistrationMail([
                    'name'       => $name,
                    'email'      => $email,
                    'pid'        => $pid,
                    'college'    => $college,
                    'department' => $department,
                    'student_id' => $student_id,
                    'events'     => $ev_names,
                ]);
            }

            $conn->close();
            $mp = $mail_sent ? '&mail=1' : '&mail=0';
            redirect('index.php?registered='.$pid.$mp); exit;
        } else {
            $error="DB Error: ".$conn->error; $ins->close();
        }
    }

    if ($error) {
        $_SESSION['reg_error']    = $error;
        $_SESSION['reg_formdata'] = $_POST;
        $conn->close();
        redirect('index.php#register'); exit;
    }
}

// Flash messages
$success=$error=''; $old=[];
if (isset($_GET['registered'])) {
    $pid  = (int)$_GET['registered'];
    $mail = $_GET['mail'] ?? '-1';
    $mail_note = '';
    if ($mail === '1')
        $mail_note = ' A confirmation email has been sent to your inbox. 📧';
    elseif ($mail === '0')
        $mail_note = ' (Email could not be sent — check mailer.php credentials.)';

    // Build success with QR code
    $qr_url = 'qr_generate.php?pid=' . $pid;
    $success = "
        <div style='display:flex;align-items:center;gap:20px;flex-wrap:wrap;'>
          <div style='flex:1;min-width:220px;'>
            <div style='font-size:1.1rem;font-weight:700;margin-bottom:8px;'>🎉 Registration Successful!</div>
            <div>Your Participant ID is <strong style='color:var(--gold);font-size:1.2rem;'>#$pid</strong></div>
            <div style='font-size:0.82rem;color:var(--muted);margin-top:6px;'>Show this QR code at the event entrance for check-in.$mail_note</div>
          </div>
          <div style='text-align:center;flex-shrink:0;'>
            <img src='$qr_url' alt='QR Code'
              style='width:110px;height:110px;border-radius:10px;background:white;padding:6px;display:block;'>
            <div style='font-size:0.72rem;color:var(--muted);margin-top:5px;'>Your QR Ticket</div>
          </div>
        </div>";
}
if (isset($_SESSION['reg_error'])) {
    $error=$_SESSION['reg_error'];
    $old=$_SESSION['reg_formdata']??[];
    unset($_SESSION['reg_error'],$_SESSION['reg_formdata']);
}
$post_events=array_map('intval',array_filter($old['events']??[],fn($v)=>$v>0));
$conn->close();

function old($k,$d='') { global $old; return htmlspecialchars($old[$k]??$d); }
function oldM($eid,$arr,$idx) {
    global $old;
    $v=$old[$arr][(string)$eid][$idx]??$old[$arr][$eid][$idx]??'';
    return htmlspecialchars($v);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= SITE_NAME ?> — <?= SITE_TAGLINE ?></title>
<link rel="stylesheet" href="css/style.css">
<style>
/* tabs */
.cat-tabs{display:flex;border-radius:10px;overflow:hidden;border:1px solid var(--border);margin-bottom:20px;width:fit-content}
.cat-tab{padding:10px 26px;font-size:.88rem;font-weight:600;cursor:pointer;background:transparent;border:none;color:var(--muted);transition:all .2s;font-family:'DM Sans',sans-serif}
.cat-tab.active{background:var(--gold);color:var(--navy)}
.cat-tab:hover:not(.active){background:rgba(245,197,24,.08);color:var(--gold)}
/* panels */
.events-panel{display:none}.events-panel.show{display:block}
/* cards */
.ev-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(205px,1fr));gap:12px}
.ev-card-wrap input[type=checkbox]{display:none}
.ev-card{border:1.5px solid rgba(255,255,255,.1);border-radius:10px;padding:14px 16px;cursor:pointer;transition:all .2s;display:block;background:rgba(255,255,255,.03)}
.ev-card:hover{border-color:rgba(245,197,24,.35);background:rgba(245,197,24,.04)}
input[type=checkbox]:checked+.ev-card{border-color:var(--gold);background:rgba(245,197,24,.1)}
input[type=checkbox]:disabled+.ev-card{opacity:.4;pointer-events:none}
.ev-card-top{display:flex;justify-content:space-between;gap:8px;margin-bottom:6px}
.ev-card-name{font-weight:600;font-size:.88rem;line-height:1.3}
.ev-check{width:18px;height:18px;border:2px solid rgba(255,255,255,.25);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;color:var(--navy);transition:all .2s}
input[type=checkbox]:checked+.ev-card .ev-check{background:var(--gold);border-color:var(--gold)}
.ev-card-desc{font-size:.76rem;color:var(--muted);line-height:1.5}
.ev-card-meta{margin-top:8px;display:flex;gap:6px;flex-wrap:wrap}
.ev-meta-pill{font-size:.68rem;padding:2px 8px;border-radius:50px;font-weight:600;background:rgba(255,255,255,.06);color:var(--muted);border:1px solid rgba(255,255,255,.08)}
.ev-meta-pill.solo{background:rgba(0,212,170,.1);color:var(--success);border-color:rgba(0,212,170,.2)}
.ev-meta-pill.group{background:rgba(100,149,237,.1);color:#6495ed;border-color:rgba(100,149,237,.2)}

/* team section */
.team-section{display:none;margin-top:0;animation:fadeIn .25s ease}
.team-section.show{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

.team-card{background:rgba(100,149,237,.06);border:1px solid rgba(100,149,237,.22);border-radius:12px;padding:20px;margin-bottom:16px}
.team-card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid rgba(100,149,237,.15);margin-bottom:16px;flex-wrap:wrap}
.team-card-title{font-size:1rem;font-weight:700;color:var(--white);margin-bottom:3px}
.team-card-desc{font-size:.78rem;color:var(--muted)}

/* leader row */
.leader-row{display:flex;align-items:center;gap:12px;background:rgba(245,197,24,.07);border:1px solid rgba(245,197,24,.18);border-radius:8px;padding:10px 14px;margin-bottom:14px}
.member-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;flex-shrink:0}
.num-gold{background:var(--gold);color:var(--navy)}
.num-blue{background:rgba(100,149,237,.25);color:#6495ed;border:1px solid rgba(100,149,237,.3)}

/* member header row */
.mem-header{display:grid;grid-template-columns:1fr 110px 120px 34px;gap:8px;margin-bottom:6px;padding:0 2px}
.mem-header span{font-size:.70rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:600}

/* member input row */
.member-row{display:grid;grid-template-columns:1fr 110px 120px 34px;gap:8px;align-items:center;margin-bottom:8px}
.member-row input{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:9px 12px;color:var(--white);font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;width:100%;transition:border-color .2s}
.member-row input:focus{border-color:#6495ed;background:rgba(100,149,237,.05)}
.member-row input::placeholder{color:rgba(255,255,255,.2)}
.member-row input.req-field{border-color:rgba(100,149,237,.35)}

.req-tag{width:34px;height:34px;display:flex;align-items:center;justify-content:center;font-size:.65rem;color:var(--danger);font-weight:700;background:rgba(255,71,87,.08);border-radius:6px;border:1px solid rgba(255,71,87,.2)}

.btn-add-member{background:transparent;border:1px dashed rgba(100,149,237,.4);color:#6495ed;border-radius:7px;padding:8px 16px;font-size:.80rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;width:100%;margin-top:4px;text-align:center}
.btn-add-member:hover{background:rgba(100,149,237,.08);border-style:solid}

.btn-remove-member{width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center;background:transparent;border:1px solid rgba(255,71,87,.25);color:var(--danger);border-radius:6px;font-size:1.1rem;cursor:pointer;transition:all .15s}
.btn-remove-member:hover{background:rgba(255,71,87,.12);border-color:var(--danger)}

.team-footer-note{display:flex;align-items:center;justify-content:space-between;margin-top:12px;padding-top:10px;border-top:1px solid rgba(100,149,237,.12);font-size:.75rem;color:var(--muted);flex-wrap:wrap;gap:6px}
.team-footer-note span{color:#6495ed;font-weight:600}

/* counter */
.sel-counter{display:flex;align-items:center;gap:12px;font-size:.82rem;color:var(--muted);margin-top:14px;padding:10px 14px;background:rgba(255,255,255,.03);border-radius:8px;border:1px solid rgba(255,255,255,.06);flex-wrap:wrap}
.sel-counter strong{color:var(--gold);font-size:1rem}

@media(max-width:600px){
  .member-row,.mem-header{grid-template-columns:1fr 34px}
  .member-row input:nth-child(2),.member-row input:nth-child(3),
  .mem-header span:nth-child(2),.mem-header span:nth-child(3){display:none}
}
</style>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="nav-brand">Event<span>Sphere</span></a>
  <div class="nav-links">
    <a href="#register" class="btn btn-primary btn-sm">Register Now</a>
    <a href="my_result.php">🎓 My Result</a>
    <a href="announcements.php">📢 Alerts</a>
    <a href="leaderboard.php" target="_blank">📺 Live</a>
    <a href="login.php?role=admin">Admin</a>
    <a href="login.php?role=judge">Judge</a>
  </div>
</nav>

<section class="hero">
  <div class="hero-grid"></div>
  <div class="hero-content">
    <div class="hero-badge">✦ Registrations Open</div>
    <h1>Welcome to <span class="highlight"><?= SITE_NAME ?></span></h1>
    <p><?= SITE_TAGLINE ?> — Compete, Perform and Shine on the biggest stage.</p>
    <div class="hero-stats">
      <div class="stat-item"><div class="stat-num"><?= count($solo_events) ?>+</div><div class="stat-label">Solo Events</div></div>
      <div class="stat-item"><div class="stat-num"><?= count($group_events) ?>+</div><div class="stat-label">Group Events</div></div>
      <div class="stat-item"><div class="stat-num"><?= $total_participants ?>+</div><div class="stat-label">Registered</div></div>
    </div>
    <a href="#register" class="btn btn-primary btn-lg">Register Now ↓</a> &nbsp;
    <a href="#events"   class="btn btn-outline btn-lg">View Events</a>
  </div>
</section>

<!-- Events showcase -->
<section class="section" id="events" style="padding-top:60px;">
  <div class="section-header">
    <h2>Our <span>Events</span></h2>
    <p><?= count($solo_events) ?> Solo + <?= count($group_events) ?> Group events — register for up to 2</p>
  </div>
  <?php if($solo_events): ?>
  <div style="margin-bottom:36px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
      <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;">🎤 Solo Events</h3>
      <span class="ev-meta-pill solo">Individual</span>
    </div>
    <div class="events-showcase">
      <?php $si=['🎤','💃','😄','🎭','📜','💻','📷','🗣️','🎨','🧠'];
      foreach($solo_events as $i=>$e): ?>
      <div class="event-card">
        <div class="event-icon"><?= $si[$i%count($si)] ?></div>
        <h3><?= htmlspecialchars($e['event_name']) ?></h3>
        <p><?= htmlspecialchars($e['description']??'') ?></p>
        <div style="margin-top:10px;font-size:.78rem;color:var(--muted);">Max <?= $e['max_participants'] ?> participants</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php if($group_events): ?>
  <div>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
      <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;">👥 Group Events</h3>
      <span class="ev-meta-pill group">Team</span>
    </div>
    <div class="events-showcase">
      <?php $gi=['💃','🎵','🎪','🎬','🎸','👗','🎭','🗺️'];
      foreach($group_events as $i=>$e): ?>
      <div class="event-card">
        <div class="event-icon"><?= $gi[$i%count($gi)] ?></div>
        <h3><?= htmlspecialchars($e['event_name']) ?></h3>
        <p><?= htmlspecialchars($e['description']??'') ?></p>
        <div style="margin-top:10px;font-size:.78rem;color:var(--muted);">Team: <?= $e['min_team_size'] ?>–<?= $e['max_team_size'] ?> members</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</section>

<!-- Registration -->
<section class="section" id="register">
  <div class="section-header">
    <h2>Event <span>Registration</span></h2>
    <p>Fill details · Select up to 2 events · Add team members for group events</p>
  </div>

  <div class="form-card animate-in" style="max-width:880px;">

    <?php if(!$has_new_cols): ?>
    <div style="background:rgba(255,165,0,.08);border:1px solid rgba(255,165,0,.3);color:#ffa500;border-radius:8px;padding:12px 16px;font-size:.84rem;margin-bottom:16px;">
      ⚠️ Run <strong>upgrade.sql</strong> in phpMyAdmin to enable group events.
    </div>
    <?php endif; ?>

    <?php if($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
    <?php if($error):   ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" action="index.php" id="regForm" novalidate>

      <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:14px;">👤 Personal Details</div>

      <div class="form-row">
        <div class="form-group">
          <label>Full Name <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="Your full name" value="<?= old('name') ?>" required>
        </div>
        <div class="form-group">
          <label>Phone Number <span class="req">*</span></label>
          <input type="tel" name="phone" class="form-control" placeholder="+91 9876543210" value="<?= old('phone') ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Email Address <span class="req">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="you@college.edu" value="<?= old('email') ?>" required>
        </div>
        <div class="form-group">
          <label>College Name <span class="req">*</span></label>
          <input type="text" name="college" class="form-control" placeholder="Your college name" value="<?= old('college') ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Department <span class="req">*</span></label>
          <input type="text" name="department" class="form-control" placeholder="e.g. Computer Science" value="<?= old('department') ?>" required>
        </div>
        <div class="form-group">
          <label>Student ID <span class="req">*</span></label>
          <input type="text" name="student_id" class="form-control" placeholder="e.g. 21CS001" value="<?= old('student_id') ?>" required>
        </div>
      </div>

      <hr class="divider">

      <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:14px;">
        🎯 Select Events <small style="text-transform:none;letter-spacing:0;font-size:.75rem;">— Max 2</small>
      </div>

      <!-- Tabs -->
      <div class="cat-tabs">
        <button type="button" class="cat-tab active" id="tab-solo"  onclick="switchTab('solo',this)">🎤 Solo (<?= count($solo_events) ?>)</button>
        <?php if($group_events): ?>
        <button type="button" class="cat-tab"         id="tab-group" onclick="switchTab('group',this)">👥 Group (<?= count($group_events) ?>)</button>
        <?php endif; ?>
      </div>

      <!-- SOLO -->
      <div class="events-panel show" id="panel-solo">
        <div class="ev-cards">
          <?php foreach($solo_events as $e): $eid=(int)$e['id']; ?>
          <div class="ev-card-wrap">
            <input type="checkbox" class="ev-checkbox" name="events[]" value="<?= $eid ?>"
              id="ev_<?= $eid ?>" data-type="solo" data-eid="<?= $eid ?>"
              <?= in_array($eid,$post_events)?'checked':'' ?>>
            <label class="ev-card" for="ev_<?= $eid ?>">
              <div class="ev-card-top"><span class="ev-card-name"><?= htmlspecialchars($e['event_name']) ?></span><span class="ev-check">✓</span></div>
              <div class="ev-card-desc"><?= htmlspecialchars(mb_strimwidth($e['description']??'',0,65,'…')) ?></div>
              <div class="ev-card-meta"><span class="ev-meta-pill solo">Solo</span><span class="ev-meta-pill">Max <?= $e['max_participants'] ?></span></div>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- GROUP -->
      <?php if($group_events): ?>
      <div class="events-panel" id="panel-group">

        <!-- Event selection cards -->
        <div class="ev-cards" style="margin-bottom:24px;">
          <?php foreach($group_events as $e):
            $eid=(int)$e['id'];
            $min=max(1,(int)($e['min_team_size']??1));
            $max=max($min,(int)($e['max_team_size']??$min));
          ?>
          <div class="ev-card-wrap">
            <input type="checkbox" class="ev-checkbox" name="events[]" value="<?= $eid ?>"
              id="ev_<?= $eid ?>" data-type="group" data-eid="<?= $eid ?>"
              data-min="<?= $min ?>" data-max="<?= $max ?>"
              <?= in_array($eid,$post_events)?'checked':'' ?>>
            <label class="ev-card" for="ev_<?= $eid ?>">
              <div class="ev-card-top"><span class="ev-card-name"><?= htmlspecialchars($e['event_name']) ?></span><span class="ev-check">✓</span></div>
              <div class="ev-card-desc"><?= htmlspecialchars(mb_strimwidth($e['description']??'',0,65,'…')) ?></div>
              <div class="ev-card-meta">
                <span class="ev-meta-pill group">Group</span>
                <span class="ev-meta-pill">👥 <?= $min ?>–<?= $max ?> members</span>
              </div>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Team forms — each event has own card below -->
        <?php foreach($group_events as $e):
          $eid    = (int)$e['id'];
          $min    = max(1,(int)($e['min_team_size']??1));
          $max    = max($min,(int)($e['max_team_size']??$min));
          $need   = $min-1; // additional members needed (leader=1)
          $prefill = max(1,$need);
          $isChk  = in_array($eid,$post_events);
        ?>
        <div class="team-section <?= $isChk?'show':'' ?>" id="team_<?= $eid ?>">
          <div class="team-card">

            <!-- Team card header -->
            <div class="team-card-header">
              <div>
                <div class="team-card-title">👥 <?= htmlspecialchars($e['event_name']) ?> — Team Details</div>
                <div class="team-card-desc"><?= htmlspecialchars($e['description']??'') ?></div>
              </div>
              <div style="display:flex;gap:6px;flex-wrap:wrap;flex-shrink:0;">
                <span class="ev-meta-pill group" style="padding:4px 10px;font-size:.73rem;">Group</span>
                <span class="ev-meta-pill" style="padding:4px 10px;font-size:.73rem;color:#6495ed;border-color:rgba(100,149,237,.3);">
                  <?= $min ?>–<?= $max ?> members
                </span>
              </div>
            </div>

            <!-- Leader row -->
            <div class="leader-row">
              <div class="member-num num-gold">1</div>
              <div style="flex:1;">
                <div style="font-weight:600;font-size:.85rem;color:var(--gold);">Team Leader — You</div>
                <div style="font-size:.74rem;color:var(--muted);">Your name & details from the personal info above</div>
              </div>
              <span style="font-size:.70rem;background:rgba(245,197,24,.15);color:var(--gold);padding:3px 10px;border-radius:50px;font-weight:700;">LEADER</span>
            </div>

            <!-- Column headers -->
            <div class="mem-header">
              <span>Member Name <?= $need>0?'<span style="color:var(--danger)">*</span>':'' ?></span>
              <span>Student ID</span>
              <span>Department</span>
              <span></span>
            </div>

            <!-- Member input rows -->
            <div id="members_<?= $eid ?>">
              <?php for($m=0;$m<$prefill;$m++):
                $isReq = ($m < $need); ?>
              <div class="member-row">
                <div style="display:flex;align-items:center;gap:6px;">
                  <div class="member-num num-blue" style="flex-shrink:0;"><?= $m+2 ?></div>
                  <input type="text"
                    name="team_members[<?= $eid ?>][]"
                    placeholder="Member <?= $m+2 ?> full name<?= $isReq?' *':' (optional)' ?>"
                    class="<?= $isReq?'req-field':'' ?>"
                    value="<?= oldM($eid,'team_members',$m) ?>"
                    style="margin:0;">
                </div>
                <input type="text" name="team_member_ids[<?= $eid ?>][]"   placeholder="Student ID"  value="<?= oldM($eid,'team_member_ids',$m) ?>">
                <input type="text" name="team_member_depts[<?= $eid ?>][]" placeholder="Department"  value="<?= oldM($eid,'team_member_depts',$m) ?>">
                <?php if($m>=$need): ?>
                  <button type="button" class="btn-remove-member" onclick="removeMember(this,<?= $eid ?>)" title="Remove">×</button>
                <?php else: ?>
                  <div class="req-tag" title="Required">REQ</div>
                <?php endif; ?>
              </div>
              <?php endfor; ?>
            </div>

            <!-- Add button + note -->
            <?php if($max>$min): ?>
            <button type="button" class="btn-add-member"
              id="add_<?= $eid ?>" onclick="addMember(<?= $eid ?>,<?= $max ?>,<?= $need ?>)">
              + Add Optional Member
            </button>
            <?php endif; ?>

            <div class="team-footer-note">
              <div>Required members: <span><?= $need ?></span> &nbsp;|&nbsp; Max additional: <span><?= $max-1 ?></span></div>
              <div style="color:var(--muted);">Total with you: <?= $min ?>–<?= $max ?> people</div>
            </div>

          </div><!-- /team-card -->
        </div><!-- /team-section -->
        <?php endforeach; ?>

      </div><!-- /panel-group -->
      <?php endif; ?>

      <!-- Counter -->
      <div class="sel-counter">
        <span>Selected: <strong id="selCount">0</strong> / 2</span>
        <span id="maxWarn" style="color:var(--danger);display:none;">⚠️ Max 2 events!</span>
        <span id="selNames" style="font-size:.78rem;color:var(--gold);"></span>
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-full" style="justify-content:center;margin-top:16px;">
        🚀 Complete Registration
      </button>
    </form>
  </div>
</section>

<footer>
  <p><?= SITE_NAME ?> &mdash; <?= SITE_TAGLINE ?></p>
  <p style="margin-top:8px;"><a href="login.php?role=admin">Admin Panel</a> &bull; <a href="login.php?role=judge">Judge Panel</a></p>
</footer>

<script>
function switchTab(type,btn){
    document.querySelectorAll('.cat-tab').forEach(t=>t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.events-panel').forEach(p=>p.classList.remove('show'));
    document.getElementById('panel-'+type)?.classList.add('show');
}

const checkboxes=document.querySelectorAll('.ev-checkbox');
const selCountEl=document.getElementById('selCount');
const maxWarnEl=document.getElementById('maxWarn');
const selNamesEl=document.getElementById('selNames');

function updateCheckboxes(){
    const checked=[...checkboxes].filter(c=>c.checked);
    const count=checked.length;
    selCountEl.textContent=count;
    maxWarnEl.style.display=count>2?'inline':'none';
    selNamesEl.textContent=count>0
        ? checked.map(c=>c.closest('.ev-card-wrap').querySelector('.ev-card-name').textContent).join(' + ')
        : '';
    checkboxes.forEach(cb=>{
        cb.disabled=!cb.checked&&count>=2;
        if(cb.dataset.type==='group'){
            document.getElementById('team_'+cb.dataset.eid)?.classList.toggle('show',cb.checked);
        }
    });
}

checkboxes.forEach(cb=>cb.addEventListener('change',updateCheckboxes));

function addMember(eid,maxSize,needCount){
    const container=document.getElementById('members_'+eid);
    const rows=container.querySelectorAll('.member-row');
    if(rows.length>=maxSize-1) return;
    const num=rows.length+2;
    const row=document.createElement('div');
    row.className='member-row';
    row.innerHTML=`
        <div style="display:flex;align-items:center;gap:6px;">
          <div class="member-num num-blue" style="flex-shrink:0;">${num}</div>
          <input type="text" name="team_members[${eid}][]" placeholder="Member ${num} full name (optional)" style="margin:0;">
        </div>
        <input type="text" name="team_member_ids[${eid}][]"   placeholder="Student ID">
        <input type="text" name="team_member_depts[${eid}][]" placeholder="Department">
        <button type="button" class="btn-remove-member" onclick="removeMember(this,${eid})" title="Remove">×</button>`;
    container.appendChild(row);
    if(container.querySelectorAll('.member-row').length>=maxSize-1){
        const a=document.getElementById('add_'+eid);
        if(a) a.style.display='none';
    }
}

function removeMember(btn,eid){
    btn.closest('.member-row').remove();
    const a=document.getElementById('add_'+eid);
    if(a) a.style.display='';
    // Renumber
    document.querySelectorAll(`#members_${eid} .member-row`).forEach((r,i)=>{
        const nb=r.querySelector('.num-blue');
        const inp=r.querySelector('input[type=text]');
        if(nb) nb.textContent=i+2;
        if(inp && inp.placeholder.startsWith('Member')) inp.placeholder=`Member ${i+2} full name`;
    });
}

document.querySelectorAll('a[href^="#"]').forEach(a=>{
    a.addEventListener('click',e=>{
        e.preventDefault();
        document.querySelector(a.getAttribute('href'))?.scrollIntoView({behavior:'smooth'});
    });
});

// Init
updateCheckboxes();
const preGrp=[...document.querySelectorAll('.ev-checkbox[data-type="group"]:checked')];
if(preGrp.length>0) switchTab('group',document.getElementById('tab-group'));
</script>
</body>
</html>
