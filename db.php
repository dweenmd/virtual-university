<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "virtual_university";

// Connect to our automated MySQL database
$conn = new mysqli($host, $username, $password, $dbname);

// If the connection drops for some reason, show an error message
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Start a session to track who logs in (Student, Teacher, or Admin)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>