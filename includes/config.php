<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Leave empty for XAMPP default
define('DB_NAME', 'antenatal_db');



// Create connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Helper function for sanitizing input
function sanitize($data)
{
    global $conn;
    return mysqli_real_escape_string($conn, trim(htmlspecialchars($data)));
}

// Helper function for redirecting
function redirect($url)
{
    header("Location: $url");
    exit();
}

// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Check if admin is logged in
function isAdminLoggedIn()
{
    return isset($_SESSION['admin_id']);
}

// Get current user data
function getCurrentUser()
{
    global $conn;
    if (!isLoggedIn())
        return null;

    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = $conn->query($query);

    return $result ? $result->fetch_assoc() : null;
}

// Get current admin data
function getCurrentAdmin()
{
    global $conn;
    if (!isAdminLoggedIn())
        return null;

    $admin_id = $_SESSION['admin_id'];
    $query = "SELECT * FROM admin_users WHERE id = $admin_id";
    $result = $conn->query($query);

    return $result ? $result->fetch_assoc() : null;
}


?>