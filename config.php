<?php
// =============================================
// config.php - Database Configuration
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'event_management');

define('SITE_NAME', 'EventSphere');
define('SITE_TAGLINE', 'National Level Technical & Cultural Fest');

// Create connection
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("<div style='font-family:sans-serif;padding:40px;background:#fee;border:1px solid #f00;color:#900;border-radius:8px;'>
            <h2>⚠️ Database Connection Failed</h2>
            <p>" . $conn->connect_error . "</p>
            <p>Please ensure XAMPP MySQL is running and the database <strong>" . DB_NAME . "</strong> exists.</p>
            <p>Import <code>database/event.sql</code> in phpMyAdmin first.</p>
        </div>");
    }
    $conn->set_charset("utf8");
    return $conn;
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: sanitize input
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Helper: redirect
function redirect($url) {
    header("Location: $url");
    exit();
}
?>
