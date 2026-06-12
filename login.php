<?php
require_once 'config.php';

$role  = clean($_GET['role'] ?? 'admin'); // admin or judge
$error = '';

if ($role !== 'admin' && $role !== 'judge') $role = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = "Please enter username and password.";
    } else {
        $conn = getConnection();
        $table = ($role === 'admin') ? 'admins' : 'judges';
        $stmt  = $conn->prepare("SELECT * FROM $table WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if ($user && ($user['password'] === $password || password_verify($password, $user['password']))) {
            $_SESSION[$role . '_id']   = $user['id'];
            $_SESSION[$role . '_user'] = $user['username'];
            if ($role === 'judge') {
                $_SESSION['judge_event'] = $user['assigned_event'];
            }
            redirect($role . '/dashboard.php');
        } else {
            $error = "Invalid username or password.";
        }
    }
}

$icon  = ($role === 'admin') ? '🛡️' : '⚖️';
$title = ($role === 'admin') ? 'Admin Login' : 'Judge Login';
$hint  = ($role === 'admin') ? 'admin / admin123' : 'judge1 / judge123';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?> — EventSphere</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card animate-in">
    <div class="login-logo">
      <div class="icon"><?= $icon ?></div>
      <h2><?= $title ?></h2>
      <p>EventSphere Management System</p>
    </div>

    <hr class="divider">

    <?php if($error): ?>
      <div class="alert alert-danger mb-2">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" class="form-control" placeholder="Enter username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="Enter password"
          autocomplete="current-password" required>
      </div>

      <button type="submit" class="btn btn-primary w-full" style="justify-content:center;margin-top:4px;">
        Login to <?= ucfirst($role) ?> Panel →
      </button>
    </form>

    <div style="margin-top:20px;text-align:center;font-size:0.82rem;">
      <div class="alert alert-warning" style="margin-bottom:12px;font-size:0.8rem;">
        💡 Demo credentials: <strong><?= $hint ?></strong>
      </div>
      <?php if($role === 'admin'): ?>
        <a href="login.php?role=judge" style="color:var(--muted)">Switch to Judge Login →</a>
      <?php else: ?>
        <a href="login.php?role=admin" style="color:var(--muted)">Switch to Admin Login →</a>
      <?php endif; ?>
      <br><br>
      <a href="index.php" style="color:var(--muted)">← Back to Home</a>
    </div>
  </div>
</div>
</body>
</html>
