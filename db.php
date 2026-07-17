<?php
// Force PHP timezone to Bangladesh — MUST be set before any date/time function runs
date_default_timezone_set('Asia/Dhaka');

// Database connection config
// NOTE: keep real credentials out of this file when committing to a public repo.
// Consider moving $password (and other secrets) into a separate config file
// that is excluded via .gitignore, or into environment variables.
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    // Local XAMPP setup
    $host = "localhost";
    $username = "root";
    $password = "";
    $dbname = "virtual_university";
    $port = 3306; // default XAMPP MySQL port
} else {
    // InfinityFree live server
    $host = "sql103.infinityfree.com";
    $username = "if0_42374690";
    $password = ""; // <-- put the real password 
    $dbname = "if0_42374690_virtual_university";
    $port = 3306; // InfinityFree MySQL port
}
// Connect to the database (only once)
$conn = new mysqli($host, $username, $password, $dbname, $port);
// If the connection fails, show an error message
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
// Ensure correct charset (avoids garbled text issues)
$conn->set_charset("utf8mb4");

// Force MySQL session timezone to Bangladesh too, so NOW() / TIMESTAMPDIFF()
// (used in attendance window logic) match PHP's time exactly.
$conn->query("SET time_zone = '+06:00'");

// Start a session to track who logs in (Student, Teacher, or Admin)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>