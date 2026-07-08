<?php
// =============================================
// Railway Database Configuration
// =============================================

define('DB_HOST', 'mysql.railway.internal');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', 'HCbyxEOtysKUJKSDNGLgRHVCkRqCCnkI');
define('DB_NAME', 'railway');

define('SITE_NAME', 'EventSphere');
define('SITE_TAGLINE', 'National Level Technical & Cultural Fest');

// Create connection
function getConnection() {

    $conn = new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        (int)DB_PORT
    );

    if ($conn->connect_error) {
        die("
        <div style='font-family:Arial;padding:30px;background:#ffe6e6;border:1px solid red;border-radius:8px'>
            <h2>❌ Database Connection Failed</h2>
            <p><strong>Error:</strong> {$conn->connect_error}</p>
        </div>
        ");
    }

    $conn->set_charset("utf8");
    return $conn;
}

// Start Session
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