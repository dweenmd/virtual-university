<?php
$host = "localhost";
$username = "root";
$password = "";

// ১. MySQL সার্ভারের সাথে কানেক্ট করা
$conn = new mysqli($host, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ২. ডাটাবেজ তৈরি করা (যদি না থাকে)
$sql = "CREATE DATABASE IF NOT EXISTS virtual_university";
if ($conn->query($sql) === TRUE) {
    echo "<b>Database 'virtual_university' checked/created successfully!</b><br><br>";
}

// ডাটাবেজ সিলেক্ট করা
$conn->select_db("virtual_university");

// ৩. প্রোজেক্টের সব টেবিল ডিফাইন করা (ফরেন কি এর সিকোয়েন্স ঠিক রেখে)
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('student', 'teacher', 'admin') NOT NULL,
        id_no VARCHAR(50) NOT NULL UNIQUE,
        semester INT DEFAULT 1
    )",
    "courses" => "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(20) NOT NULL UNIQUE,
        title VARCHAR(100) NOT NULL,
        semester INT NOT NULL DEFAULT 1,
        teacher_id INT,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    "academic_records" => "CREATE TABLE IF NOT EXISTS academic_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        ct_marks INT DEFAULT 0,
        total_days INT DEFAULT 0,
        present_days INT DEFAULT 0,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )",
    "videos" => "CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        video_url TEXT NOT NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )",
    "online_class_tests" => "CREATE TABLE IF NOT EXISTS online_class_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        status ENUM('LIVE NOW', 'completed') NOT NULL DEFAULT 'LIVE NOW',
        zoom_link TEXT,
        test_type ENUM('meet', 'mcq', 'pdf') NOT NULL,
        attendance_token VARCHAR(10) NULL,
        option_a VARCHAR(255) NULL,
        option_b VARCHAR(255) NULL,
        option_c VARCHAR(255) NULL,
        option_d VARCHAR(255) NULL,
        pdf_question TEXT NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )",
    "quiz_submissions" => "CREATE TABLE IF NOT EXISTS quiz_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ct_id INT NOT NULL,
        student_id INT NOT NULL,
        student_name VARCHAR(100) NOT NULL,
        answers TEXT NULL,
        pdf_submission TEXT NULL,
        FOREIGN KEY (ct_id) REFERENCES online_class_tests(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    )"
];

// লুপ চালিয়ে টেবিলগুলো তৈরি করা
foreach ($tables as $name => $query) {
    if ($conn->query($query) === TRUE) {
        echo "Table '$name' checked/created successfully!<br>";
    } else {
        echo "Error creating table $name: " . $conn->error . "<br>";
    }
}

echo "<br>";

// ৪. ডেমো লগইন অ্যাকাউন্ট তৈরি করা
$checkUser = $conn->query("SELECT * FROM users LIMIT 1");
if ($checkUser->num_rows == 0) {
    $conn->query("INSERT INTO users (name, email, password, role, id_no, semester) VALUES 
        ('Admin User', 'admin@uni.com', 'admin123', 'admin', 'ADMIN-01', 1),
        ('Teacher John', 'teacher@uni.com', 'teacher123', 'teacher', 'T-01', 1),
        ('Student Ratul', 'student@uni.com', 'student123', 'student', 'S-01', 5)");

    echo "<b>Default test accounts created successfully!</b><br>";
    echo "🔑 Admin: admin@uni.com (admin123)<br>";
    echo "🔑 Teacher: teacher@uni.com (teacher123)<br>";
    echo "🔑 Student: student@uni.com (student123)<br>";
}

$conn->close();
?>