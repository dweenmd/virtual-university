<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "virtual_university";
$port = 3306; // fresh XAMPP install ke default MySQL port eta
/*
$host = "sql103.infinityfree.com";
$username = "if0_42374690";
$password = "32mecproject123";
$dbname = "if0_42374690_virtual_university";
*/

// Connect to our automated MySQL database
$conn = new mysqli($host, $username, $password, $dbname, $port);

// If the connection drops for some reason, show an error message
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Start a session to track who logs in (Student, Teacher, or Admin)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>