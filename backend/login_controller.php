<?php
// session_start() must be called at the very top of the file if using $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/db.php';
$error = "";

// Handle secure multi-role login routing logic
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $entered_password = $_POST['password'];
    $selected_role = mysqli_real_escape_string($conn, $_POST['role_selection']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND role=?");
    $stmt->bind_param("ss", $email, $selected_role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($entered_password === $user['password'] || password_verify($entered_password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'admin')
                header("Location: admin_dashboard.php");
            if ($user['role'] == 'teacher')
                header("Location: teacher_dashboard.php");
            if ($user['role'] == 'student')
                header("Location: student_dashboard.php");
            exit();
        } else {
            $error = "Invalid credentials for the selected destination portal layer!";
        }
    } else {
        $error = "Invalid credentials for the selected destination portal layer!";
    }
    $stmt->close();
}

// Fetch all currently 'LIVE NOW' *lecture/meet* sessions from the database.
$live_class_query = "SELECT t.*, c.course_code, c.title AS course_title 
                     FROM online_class_tests t 
                     JOIN courses c ON t.course_id = c.id 
                     WHERE t.status = 'LIVE NOW' AND t.test_type = 'meet'
                     ORDER BY t.id DESC";
$live_class_result = mysqli_query($conn, $live_class_query);
$total_live_classes = ($live_class_result) ? mysqli_num_rows($live_class_result) : 0;
?>
