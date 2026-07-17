<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

// Only admins may access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Detect whether this request came in via AJAX (fetch) - if so, respond with JSON only, no HTML
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($is_ajax) {
    header('Content-Type: application/json');
}

$message = "";

try {

    // Admin action: stop a live class
    if (isset($_POST['stop_live'])) {
        $course_id = intval($_POST['course_id']);
        $sql_stop = "UPDATE online_class_tests SET status='completed' WHERE course_id='$course_id' AND status='LIVE NOW'";
        if ($conn->query($sql_stop)) {
            echo json_encode(['status' => 'success', 'message' => '🔴 Live Session Terminated successfully!', 'course_id' => $course_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Could not terminate the session: ' . $conn->error]);
        }
        exit();
    }

    // Student enrollment action
    if (isset($_POST['add_student'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $password = mysqli_real_escape_string($conn, $_POST['password'] ?? '');
        $id_no = mysqli_real_escape_string($conn, $_POST['id_no'] ?? '');
        $semester = intval($_POST['semester'] ?? 0);

        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check && $check->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => '❌ Error: Email already registered!']);
            exit();
        }

        $sql = "INSERT INTO users (name, email, password, id_no, role, semester) VALUES ('$name', '$email', '$password', '$id_no', 'student', '$semester')";
        if ($conn->query($sql)) {
            echo json_encode([
                'status' => 'success',
                'message' => "🟢 Student '$name' enrolled successfully!",
                'student' => [
                    'id' => $conn->insert_id,
                    'name' => htmlspecialchars($name),
                    'email' => htmlspecialchars($email),
                    'id_no' => htmlspecialchars($id_no),
                    'semester' => $semester,
                    'active_courses' => 0,
                    'completed_courses' => 0,
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        exit();
    }

    // Course creation action
    if (isset($_POST['add_course'])) {
        $course_code = mysqli_real_escape_string($conn, $_POST['course_code'] ?? '');
        $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
        $semester = intval($_POST['semester'] ?? 0);
        $teacher_id = intval($_POST['teacher_id'] ?? 0);

        $sql = "INSERT INTO courses (course_code, title, semester, teacher_id) VALUES ('$course_code', '$title', '$semester', '$teacher_id')";
        if ($conn->query($sql)) {
            $teacher_name = '';
            $t_lookup = $conn->query("SELECT name FROM users WHERE id='$teacher_id'");
            if ($t_lookup && $t_lookup->num_rows > 0) {
                $teacher_name = $t_lookup->fetch_assoc()['name'];
            }
            echo json_encode([
                'status' => 'success',
                'message' => "🟢 Course '$course_code' deployed successfully!",
                'course' => [
                    'id' => $conn->insert_id,
                    'course_code' => htmlspecialchars($course_code),
                    'title' => htmlspecialchars($title),
                    'semester' => $semester,
                    'teacher_name' => htmlspecialchars($teacher_name),
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        exit();
    }

    // Teacher creation action
    if (isset($_POST['add_teacher'])) {
        $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
        $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
        $password = mysqli_real_escape_string($conn, $_POST['password'] ?? '');

        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check && $check->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => '❌ Error: Email already registered!']);
            exit();
        }

        // id_no/semester are included with safe defaults in case those columns are NOT NULL for other roles.
        // FIX: use a unique placeholder instead of a blank string — if id_no has a UNIQUE constraint (as it
        // typically does for student registration numbers), inserting '' for every teacher causes a
        // "Duplicate entry '' for key 'id_no'" database error starting from the 2nd teacher onward.
        $teacher_id_no = mysqli_real_escape_string($conn, 'T-' . uniqid());
        $sql = "INSERT INTO users (name, email, password, id_no, role, semester) VALUES ('$name', '$email', '$password', '$teacher_id_no', 'teacher', 0)";
        if ($conn->query($sql)) {
            echo json_encode([
                'status' => 'success',
                'message' => "🟢 Teacher '$name' added successfully!",
                'teacher' => [
                    'id' => $conn->insert_id,
                    'name' => htmlspecialchars($name),
                    'email' => htmlspecialchars($email),
                    'course_count' => 0,
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        exit();
    }

    // Admin action: permanently remove a teacher (any courses they taught become Unassigned, not deleted)
    if (isset($_POST['remove_teacher'])) {
        $teacher_id = intval($_POST['teacher_id']);
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE courses SET teacher_id = NULL WHERE teacher_id = '$teacher_id'");
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $conn->commit();
            if ($affected > 0) {
                echo json_encode(['status' => 'success', 'message' => '🟢 Teacher removed successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Teacher not found.']);
            }
        } catch (Throwable $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }

    // New action: student semester GPA entry & update logic
    if (isset($_POST['submit_gpa'])) {
        $student_id = intval($_POST['student_id'] ?? 0);
        $semester_no = intval($_POST['semester_no'] ?? 0);
        $gpa = floatval($_POST['gpa'] ?? 0);

        // ON DUPLICATE KEY UPDATE ensures a given student's specific semester result gets updated instead of duplicated
        $stmt = $conn->prepare("INSERT INTO student_cgpa_records (student_id, semester_no, gpa) VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE gpa = ?");
        $stmt->bind_param("iidd", $student_id, $semester_no, $gpa, $gpa);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => "🟢 GPA Updated Successfully for Student ID: $student_id (Semester $semester_no)!"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "❌ Error updating GPA: " . $conn->error]);
        }
        $stmt->close();
        exit();
    }

    // Admin action: assign or unassign a teacher for a course
    if (isset($_POST['assign_teacher'])) {
        $course_id = intval($_POST['course_id']);
        $teacher_id = isset($_POST['teacher_id']) ? trim($_POST['teacher_id']) : '';

        if ($teacher_id === '') {
            // Unassign: set teacher_id to NULL
            $stmt = $conn->prepare("UPDATE courses SET teacher_id = NULL WHERE id = ?");
            $stmt->bind_param("i", $course_id);
            $teacher_name = '';
        } else {
            $teacher_id = intval($teacher_id);
            $stmt = $conn->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $teacher_id, $course_id);
            $t_lookup = $conn->query("SELECT name FROM users WHERE id='$teacher_id' AND role='teacher'");
            $teacher_name = ($t_lookup && $t_lookup->num_rows > 0) ? $t_lookup->fetch_assoc()['name'] : '';
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => '🟢 Faculty assignment updated.', 'teacher_name' => htmlspecialchars($teacher_name)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        exit();
    }

    // Admin action: archive a student — moves them to "Old Students", does NOT delete from the database
    if (isset($_POST['archive_student'])) {
        $student_id = intval($_POST['student_id']);
        $stmt = $conn->prepare("UPDATE users SET archived = 1 WHERE id = ? AND role = 'student'");
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => '🗄️ Student moved to Old Students archive.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Could not archive student (not found).']);
        }
        $stmt->close();
        exit();
    }

    // Admin action: restore a previously archived student back to the active directory
    if (isset($_POST['restore_student'])) {
        $student_id = intval($_POST['student_id']);
        $stmt = $conn->prepare("UPDATE users SET archived = 0 WHERE id = ? AND role = 'student'");
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => '🟢 Student restored to active directory.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Could not restore student (not found).']);
        }
        $stmt->close();
        exit();
    }

    // Admin action: edit/update a student's own info (name, email, id_no, semester, optional password)
    if (isset($_POST['update_student'])) {
        $student_id = intval($_POST['student_id']);
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        $id_no = mysqli_real_escape_string($conn, trim($_POST['id_no']));
        $semester = intval($_POST['semester']);
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        $check = $conn->query("SELECT id FROM users WHERE email='$email' AND id != $student_id");
        if ($check && $check->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => '❌ Email already used by another account.']);
            exit();
        }

        if ($password !== '') {
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, id_no=?, semester=?, password=? WHERE id=? AND role='student'");
            $stmt->bind_param("sssisi", $name, $email, $id_no, $semester, $password, $student_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, id_no=?, semester=? WHERE id=? AND role='student'");
            $stmt->bind_param("sssii", $name, $email, $id_no, $semester, $student_id);
        }

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => '🟢 Student information updated.',
                'student' => [
                    'id' => $student_id,
                    'name' => htmlspecialchars($name),
                    'email' => htmlspecialchars($email),
                    'id_no' => htmlspecialchars($id_no),
                    'semester' => $semester,
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        exit();
    }

    // Admin action: promote a student to the next semester
    if (isset($_POST['promote_semester'])) {
        $student_id = intval($_POST['student_id']);
        $stmt = $conn->prepare("UPDATE users SET semester = semester + 1 WHERE id = ? AND role = 'student' AND semester < 8");
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            $new_sem = $conn->query("SELECT semester FROM users WHERE id='$student_id'")->fetch_assoc()['semester'];
            echo json_encode(['status' => 'success', 'message' => '🟢 Student promoted successfully.', 'new_semester' => (int) $new_sem]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        exit();
    }

    // Admin action: demote a student to the previous semester
    if (isset($_POST['demote_semester'])) {
        $student_id = intval($_POST['student_id']);
        $stmt = $conn->prepare("UPDATE users SET semester = semester - 1 WHERE id = ? AND role = 'student' AND semester > 1");
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            $new_sem = $conn->query("SELECT semester FROM users WHERE id='$student_id'")->fetch_assoc()['semester'];
            echo json_encode(['status' => 'success', 'message' => '🟡 Student demoted successfully.', 'new_semester' => (int) $new_sem]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        exit();
    }

    // Admin action: enroll (assign) a student into a course — becomes an "active" academic record
    if (isset($_POST['enroll_course'])) {
        $student_id = intval($_POST['student_id']);
        $course_id = intval($_POST['course_id']);

        $exists = $conn->query("SELECT id, status FROM academic_records WHERE student_id='$student_id' AND course_id='$course_id' LIMIT 1");
        if ($exists && $exists->num_rows > 0) {
            $row = $exists->fetch_assoc();
            if ($row['status'] === 'completed') {
                $conn->query("UPDATE academic_records SET status='active' WHERE id='{$row['id']}'");
                echo json_encode(['status' => 'success', 'message' => '🟢 Course re-activated for student.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Student is already enrolled in this course.']);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO academic_records (student_id, course_id, ct_marks, total_days, present_days, status) VALUES (?, ?, 0, 0, 0, 'active')");
            $stmt->bind_param("ii", $student_id, $course_id);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => '🟢 Course assigned to student.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            }
            $stmt->close();
        }
        exit();
    }

    // Admin action: archive (mark completed) a student's course, freeing them up for the next semester
    if (isset($_POST['archive_course'])) {
        $student_id = intval($_POST['student_id']);
        $course_id = intval($_POST['course_id']);
        $stmt = $conn->prepare("UPDATE academic_records SET status='completed' WHERE student_id=? AND course_id=?");
        $stmt->bind_param("ii", $student_id, $course_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => '🟢 Course archived as completed.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        exit();
    }

    // AJAX fetch: a student's active/completed/available courses (for the "Manage Courses" modal)
    if (isset($_POST['get_student_courses'])) {
        $student_id = intval($_POST['student_id']);

        $active = [];
        $active_q = $conn->query("
        SELECT c.id, c.course_code, c.title, c.semester
        FROM academic_records ar JOIN courses c ON c.id = ar.course_id
        WHERE ar.student_id='$student_id' AND ar.status='active'
        ORDER BY c.semester ASC, c.course_code ASC
    ");
        while ($active_q && $r = $active_q->fetch_assoc())
            $active[] = $r;

        $completed = [];
        $completed_q = $conn->query("
        SELECT c.id, c.course_code, c.title, c.semester
        FROM academic_records ar JOIN courses c ON c.id = ar.course_id
        WHERE ar.student_id='$student_id' AND ar.status='completed'
        ORDER BY c.semester ASC, c.course_code ASC
    ");
        while ($completed_q && $r = $completed_q->fetch_assoc())
            $completed[] = $r;

        $available = [];
        $available_q = $conn->query("
        SELECT c.id, c.course_code, c.title, c.semester
        FROM courses c
        WHERE c.id NOT IN (
            SELECT course_id FROM academic_records WHERE student_id='$student_id' AND status IN ('active','completed')
        )
        ORDER BY c.semester ASC, c.course_code ASC
    ");
        while ($available_q && $r = $available_q->fetch_assoc())
            $available[] = $r;

        echo json_encode(['status' => 'success', 'active' => $active, 'completed' => $completed, 'available' => $available]);
        exit();
    }

    // Admin action: permanently remove a course (Active Course Allocation Hub) along with its dependent records
    if (isset($_POST['remove_course'])) {
        $course_id = intval($_POST['course_id']);
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM online_class_tests WHERE course_id='$course_id'");
            $conn->query("DELETE FROM academic_records WHERE course_id='$course_id'");
            $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $conn->commit();
            if ($affected > 0) {
                echo json_encode(['status' => 'success', 'message' => '🟢 Course removed successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Course not found.']);
            }
        } catch (Throwable $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }

} catch (Throwable $e) {
    if ($is_ajax) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    } else {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Fetch counter data (for dashboard stats)
$count_students = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='student'")->fetch_assoc()['total'];
$count_teachers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='teacher'")->fetch_assoc()['total'];
$count_live = $conn->query("SELECT COUNT(*) AS total FROM online_class_tests WHERE status='LIVE NOW'")->fetch_assoc()['total'];

$teachers_list = $conn->query("SELECT id, name FROM users WHERE role='teacher' ORDER BY name ASC");

// Full student directory (with active/completed course counts) — powers the Students Directory table
$students_list = $conn->query("
    SELECT u.id, u.name, u.email, u.id_no, u.semester,
        (SELECT COUNT(*) FROM academic_records ar WHERE ar.student_id = u.id AND ar.status = 'active') AS active_courses,
        (SELECT COUNT(*) FROM academic_records ar WHERE ar.student_id = u.id AND ar.status = 'completed') AS completed_courses,
        (SELECT ROUND(AVG(scr.gpa), 2) FROM student_cgpa_records scr WHERE scr.student_id = u.id) AS cgpa
    FROM users u
    WHERE u.role = 'student' AND u.archived = 0
    ORDER BY u.semester ASC, u.name ASC
");
$students_array = [];
if ($students_list) {
    while ($s = $students_list->fetch_assoc()) {
        $s['cgpa'] = $s['cgpa'] !== null ? (float) $s['cgpa'] : null;
        $students_array[] = $s;
    }
}

// Old Students (archived) — kept in the database, just hidden from the active directory
$old_students_list = $conn->query("
    SELECT u.id, u.name, u.email, u.id_no, u.semester
    FROM users u
    WHERE u.role = 'student' AND u.archived = 1
    ORDER BY u.name ASC
");
$old_students_array = [];
if ($old_students_list) {
    while ($s = $old_students_list->fetch_assoc()) {
        $old_students_array[] = $s;
    }
}

// Semester-wise student headcount (for the dropdown stat card)
$sem_counts = array_fill(1, 8, 0);
$sem_count_q = $conn->query("SELECT semester, COUNT(*) AS c FROM users WHERE role='student' GROUP BY semester");
if ($sem_count_q) {
    while ($row = $sem_count_q->fetch_assoc()) {
        if (isset($sem_counts[(int) $row['semester']])) {
            $sem_counts[(int) $row['semester']] = (int) $row['c'];
        }
    }
}

// Plain array copy of teachers (used by JS for dynamically-rendered <select> elements)
$teachers_array = [];
$teachers_for_js = $conn->query("SELECT id, name FROM users WHERE role='teacher' ORDER BY name ASC");
if ($teachers_for_js) {
    while ($t = $teachers_for_js->fetch_assoc()) {
        $teachers_array[] = $t;
    }
}

// Faculty directory (with assigned course counts) — powers the Faculty Directory table
$teachers_directory = $conn->query("
    SELECT u.id, u.name, u.email,
        (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = u.id) AS course_count
    FROM users u
    WHERE u.role = 'teacher'
    ORDER BY u.name ASC
");
$teachers_directory_array = [];
if ($teachers_directory) {
    while ($t = $teachers_directory->fetch_assoc()) {
        $teachers_directory_array[] = $t;
    }
}

// UNIX_TIMESTAMP(oct.created_at) is pulled in a subquery here for dynamic live-timer tracking
$courses_list = $conn->query("
    SELECT c.*, u.name AS teacher_name,
    (SELECT COUNT(*) FROM online_class_tests oct WHERE oct.course_id = c.id AND oct.status = 'LIVE NOW') AS is_live,
    (SELECT UNIX_TIMESTAMP(oct.created_at) FROM online_class_tests oct WHERE oct.course_id = c.id AND oct.status = 'LIVE NOW' LIMIT 1) AS start_timestamp
    FROM courses c 
    LEFT JOIN users u ON c.teacher_id = u.id 
    ORDER BY c.semester ASC, c.course_code ASC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrix Admin Panel — Virtual Varsity</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --maroon: #73001a;
            --maroon-deep: #3d0510;
            --maroon-line: #a31d34;
            --gold: #a3781f;
            --gold-soft: #c9a227;
            --gold-deep: #856016;

            --bg-app: #f7f4ee;
            --bg-sidebar: #3d0510;
            --bg-sidebar-hover: #5a0e1f;
            --bg-card: #ffffff;
            --border-card: #e2d9c8;
            --text-main: #251b16;
            --text-muted: #7c7166;
            --input-bg: #faf7f0;
            --input-border: #e2d9c8;
            --gold-tint: #faf3e2;
            --shadow-card: 0 1px 3px rgba(61, 5, 16, 0.07), 0 1px 2px rgba(61, 5, 16, 0.05);
            --shadow-card-lg: 0 10px 26px rgba(61, 5, 16, 0.09), 0 2px 6px rgba(61, 5, 16, 0.06);
            --emerald: #0f9d68;
            --emerald-soft: #e5f7ef;
            --rose: #c0341f;
            --rose-soft: #fbeae7;
        }

        [data-theme="dark"] {
            --bg-app: #1c1210;
            --bg-sidebar: #240409;
            --bg-sidebar-hover: #3d0a14;
            --bg-card: #271a17;
            --border-card: #3d2c26;
            --text-main: #f3e9e2;
            --text-muted: #b7a297;
            --gold: #d9ac52;
            --gold-deep: #e8c581;
            --gold-tint: #322114;
            --input-bg: #2c1e1b;
            --input-border: #3d2c26;
            --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.35);
            --shadow-card-lg: 0 10px 30px rgba(0, 0, 0, 0.5);
            --emerald: #34d399;
            --emerald-soft: #10281f;
            --rose: #f0806e;
            --rose-soft: #2c1512;
        }

        * {
            transition: background-color .2s ease, border-color .2s ease, color .2s ease;
        }

        body {
            background: var(--bg-app);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            font-size: 15px;
        }

        .font-display {
            font-family: 'Fraunces', serif;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
        }

        .card-lg-shadow {
            box-shadow: var(--shadow-card-lg);
        }

        .text-main {
            color: var(--text-main);
        }

        .text-muted-c {
            color: var(--text-muted);
        }

        .input-field {
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            color: var(--text-main);
        }

        .input-field::placeholder {
            color: var(--text-muted);
        }

        .input-field:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(163, 120, 31, 0.18);
        }

        .gold-accent {
            color: var(--gold-deep);
        }

        .gold-bg {
            background: linear-gradient(135deg, var(--gold-soft) 0%, var(--gold-deep) 100%);
        }

        .gold-soft-bg {
            background: var(--gold-tint);
        }

        .emerald-soft-bg {
            background: var(--emerald-soft);
        }

        .emerald-text {
            color: var(--emerald);
        }

        .rose-soft-bg {
            background: var(--rose-soft);
        }

        .rose-text {
            color: var(--rose);
        }

        /* ---------- Sidebar ---------- */
        .sidebar-shell {
            background: linear-gradient(180deg, var(--bg-sidebar) 0%, #2a0309 100%);
        }

        .sidebar-brand-ring {
            box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.18);
        }

        .sidebar-link {
            color: #d9c3bc;
            border: 1px solid transparent;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }

        .sidebar-link.active {
            background: rgba(201, 162, 39, 0.14);
            color: #fff;
            border-color: rgba(201, 162, 39, 0.35);
        }

        .sidebar-link.active .sidebar-icon-box {
            background: var(--gold-soft);
            color: #3d0510;
        }

        .sidebar-icon-box {
            background: rgba(255, 255, 255, 0.06);
            color: #e9d9b8;
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .sidebar-divider {
            border-color: rgba(255, 255, 255, 0.08);
        }

        table.data-table thead {
            background: var(--input-bg);
        }

        table.data-table tbody tr {
            border-color: var(--border-card);
        }

        table.data-table tbody tr:hover {
            background: var(--gold-tint);
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-card);
            border-radius: 8px;
        }

        @keyframes toast-in {
            from {
                transform: translateX(120%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes count-fade {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-count {
            animation: count-fade .5s ease both;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }

        .tab-btn.active {
            background: var(--maroon);
            color: #fff;
        }

        .tab-btn:not(.active) {
            color: var(--text-muted);
        }

        .tab-btn:not(.active):hover {
            background: var(--input-bg);
        }

        #mobile-sidebar-backdrop {
            backdrop-filter: blur(2px);
        }
    </style>
</head>

<body class="antialiased">

    <!-- Toast notification container -->
    <div id="toast-container" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-[min(90vw,360px)]"></div>

    <!-- Custom confirm modal (replaces native confirm()) -->
    <div id="confirm-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/50 p-4">
        <div class="card card-lg-shadow rounded-2xl p-7 max-w-sm w-full space-y-4">
            <h3 class="font-display text-lg font-semibold text-main" id="confirm-modal-title">Are you sure?</h3>
            <p class="text-sm text-muted-c leading-relaxed" id="confirm-modal-message"></p>
            <div class="flex justify-end gap-2.5 pt-1">
                <button onclick="closeConfirmModal()"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold border border-current text-muted-c hover:opacity-70 transition cursor-pointer">Cancel</button>
                <button id="confirm-modal-confirm-btn"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition cursor-pointer"
                    style="background: var(--rose);">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Mobile sidebar overlay -->
    <div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"
        onclick="toggleMobileSidebar(false)"></div>

    <div class="min-h-screen flex flex-col md:flex-row">

        <!-- Mobile topbar -->
        <div class="md:hidden flex items-center justify-between px-4 py-3.5" style="background: var(--bg-sidebar);">
            <button onclick="toggleMobileSidebar(true)" class="text-white text-xl cursor-pointer"
                aria-label="Open menu">☰</button>
            <span class="text-white text-sm font-bold tracking-wide font-display">Virtual Varsity · Admin</span>
            <button onclick="toggleTheme()" id="mobile-theme-btn" class="text-white text-lg cursor-pointer"
                aria-label="Toggle theme">🌙</button>
        </div>

        <aside id="sidebar"
            class="sidebar-shell fixed md:sticky inset-y-0 md:inset-y-auto md:top-0 left-0 z-40 w-72 h-screen p-6 flex flex-col justify-between shadow-xl transform -translate-x-full md:translate-x-0 transition-transform duration-300 overflow-y-auto">
            <div class="space-y-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3 text-white">
                        <div class="sidebar-brand-ring h-11 w-11 rounded-xl flex items-center justify-center text-xl font-bold font-display"
                            style="background: linear-gradient(135deg, var(--gold-soft), var(--gold-deep)); color:#3d0510;">
                            V
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-white tracking-wide font-display">Virtual Varsity</h2>
                            <span class="text-[11px] font-mono tracking-widest uppercase"
                                style="color: var(--gold-soft);">Admin Matrix</span>
                        </div>
                    </div>
                    <button onclick="toggleMobileSidebar(false)" class="md:hidden text-white/60 text-xl cursor-pointer"
                        aria-label="Close menu">✕</button>
                </div>

                <div>
                    <p class="px-3 mb-2 text-[10px] font-bold uppercase tracking-widest" style="color:#a98d84;">
                        Navigation</p>
                    <nav class="space-y-1.5">
                        <a href="#overview" onclick="toggleMobileSidebar(false)"
                            class="sidebar-link active flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition">
                            <span class="sidebar-icon-box">📊</span> <span>Core Dashboard</span>
                        </a>
                        <a href="#student-section" onclick="toggleMobileSidebar(false)"
                            class="sidebar-link flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition">
                            <span class="sidebar-icon-box">🎓</span> <span>Students Directory</span>
                        </a>
                        <a href="#teacher-section" onclick="toggleMobileSidebar(false)"
                            class="sidebar-link flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition">
                            <span class="sidebar-icon-box">👨‍🏫</span> <span>Faculty Directory</span>
                        </a>
                        <a href="#old-student-section" onclick="toggleMobileSidebar(false)"
                            class="sidebar-link flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition">
                            <span class="sidebar-icon-box">🗄️</span> <span>Old Students</span>
                        </a>
                        <a href="#course-section" onclick="toggleMobileSidebar(false)"
                            class="sidebar-link flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition">
                            <span class="sidebar-icon-box">📚</span> <span>Course Allocation</span>
                        </a>
                    </nav>
                </div>

                <div class="pt-5 border-t sidebar-divider">
                    <div class="flex items-center gap-3 px-1">
                        <div class="h-9 w-9 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                            style="background: rgba(255,255,255,0.08); color: var(--gold-soft);">
                            <?php echo strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)); ?>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-white truncate">
                                <?php echo htmlspecialchars($_SESSION['name'] ?? 'Administrator'); ?>
                            </p>
                            <p class="text-[11px] truncate" style="color:#a98d84;">System Administrator</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t sidebar-divider space-y-3">
                <button onclick="toggleTheme()" id="desktop-theme-btn"
                    class="hidden md:flex items-center justify-center space-x-2 w-full bg-white/5 hover:bg-white/10 text-white/80 text-sm font-bold py-3 rounded-xl transition border border-white/10 cursor-pointer">
                    <span id="theme-icon" class="text-base">🌙</span> <span id="theme-label">Dark Mode</span>
                </button>
                <a href="index.php"
                    class="flex items-center justify-center space-x-2 w-full text-sm font-bold py-3 rounded-xl transition shadow-sm"
                    style="background: rgba(192, 52, 31, 0.16); color: #f0a99b; border: 1px solid rgba(192, 52, 31, 0.3);">
                    <span class="text-base">🚪</span> <span>Exit Admin Panel</span>
                </a>
            </div>
        </aside>

        <main class="flex-1 p-5 md:p-10 max-w-7xl mx-auto w-full space-y-8" id="overview">

            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 border-b pb-5"
                style="border-color: var(--border-card);">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight font-display text-main">System Control
                        Panel</h1>
                    <p class="text-sm text-muted-c mt-1">Manage your campus live sessions, course curriculum, and
                        inventory.</p>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-xs font-mono input-field px-3.5 py-2 rounded-xl font-semibold">Role:
                        Central_Admin</span>
                </div>
            </div>

            <!-- Stats cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="card card-lg-shadow p-6 rounded-2xl flex items-center space-x-4 animate-count">
                    <div class="p-3.5 rounded-xl text-2xl gold-soft-bg gold-accent">👥</div>
                    <div>
                        <span class="block text-xs font-bold text-muted-c uppercase tracking-wider">Total
                            Students</span>
                        <span class="text-3xl font-black text-main font-display"
                            data-countup="<?php echo (int) $count_students; ?>">0</span>
                    </div>
                </div>
                <div class="card card-lg-shadow p-6 rounded-2xl flex items-center space-x-4 animate-count"
                    style="animation-delay:.05s;">
                    <div class="p-3.5 rounded-xl text-2xl gold-soft-bg gold-accent">👨‍🏫</div>
                    <div>
                        <span class="block text-xs font-bold text-muted-c uppercase tracking-wider">Active
                            Faculty</span>
                        <span id="stat-faculty-count" class="text-3xl font-black text-main font-display"
                            data-countup="<?php echo (int) $count_teachers; ?>">0</span>
                    </div>
                </div>
                <div class="card card-lg-shadow p-6 rounded-2xl flex items-center space-x-4 animate-count <?php echo $count_live > 0 ? 'ring-2 ring-red-500/30' : ''; ?>"
                    style="animation-delay:.1s;">
                    <div
                        class="p-3.5 rounded-xl text-2xl rose-soft-bg rose-text <?php echo $count_live > 0 ? 'animate-pulse' : ''; ?>">
                        📡</div>
                    <div>
                        <span class="block text-xs font-bold text-muted-c uppercase tracking-wider">Live Rooms
                            Now</span>
                        <span class="text-3xl font-black text-main flex items-center space-x-2 font-display">
                            <span data-countup="<?php echo (int) $count_live; ?>">0</span>
                            <?php if ($count_live > 0)
                                echo '<span class="h-2.5 w-2.5 rounded-full bg-red-500 animate-ping"></span>'; ?>
                        </span>
                    </div>
                </div>

                <!-- Semester-wise student headcount, selectable via dropdown -->
                <div class="card card-lg-shadow p-6 rounded-2xl animate-count" style="animation-delay:.15s;">
                    <div class="flex items-center justify-between mb-2">
                        <span class="block text-xs font-bold text-muted-c uppercase tracking-wider">Semester
                            Headcount</span>
                        <span class="text-xl gold-accent">🎯</span>
                    </div>
                    <select id="semester-stat-select" onchange="renderSemesterStat()"
                        class="w-full input-field p-2 rounded-lg text-sm mb-2">
                        <?php for ($i = 1; $i <= 8; $i++)
                            echo "<option value='$i'>Semester $i</option>"; ?>
                    </select>
                    <span id="semester-stat-count" class="text-2xl font-black text-main font-display">0</span>
                    <span class="text-xs text-muted-c"> students</span>
                    <div id="semester-stat-list" class="mt-2 max-h-24 overflow-y-auto space-y-0.5 text-xs text-muted-c">
                    </div>
                </div>
            </div>

            <!-- Tab switcher: Add Student / Add Course / Publish GPA -->
            <div class="card card-lg-shadow rounded-2xl overflow-hidden">
                <div class="flex flex-wrap border-b" style="border-color: var(--border-card);">
                    <button onclick="switchAdminTab('student')" id="tab-btn-student"
                        class="tab-btn active flex-1 min-w-[140px] text-center py-3.5 text-sm font-bold transition cursor-pointer">🎓
                        Enroll
                        Student</button>
                    <button onclick="switchAdminTab('course')" id="tab-btn-course"
                        class="tab-btn flex-1 min-w-[140px] text-center py-3.5 text-sm font-bold transition cursor-pointer">📚
                        Deploy
                        Course</button>
                    <button onclick="switchAdminTab('gpa')" id="tab-btn-gpa"
                        class="tab-btn flex-1 min-w-[140px] text-center py-3.5 text-sm font-bold transition cursor-pointer">🏅
                        Publish
                        GPA</button>
                    <button onclick="switchAdminTab('teacher')" id="tab-btn-teacher"
                        class="tab-btn flex-1 min-w-[140px] text-center py-3.5 text-sm font-bold transition cursor-pointer">👨‍🏫
                        Add
                        Teacher</button>
                </div>

                <div class="p-6 sm:p-7">
                    <!-- Student enrollment form -->
                    <form id="form-student" class="admin-tab-panel space-y-5" data-toast="Enrolling student...">
                        <div class="mb-1">
                            <h3 class="text-sm font-black uppercase gold-accent tracking-wider font-display">Student
                                Matrix
                                Registration</h3>
                            <p class="text-xs text-muted-c mt-0.5">Submit a new student record to the database</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Full Name</label>
                            <input type="text" name="name" required placeholder="e.g., Dween Mohammad"
                                class="w-full input-field p-3 rounded-xl text-sm transition">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Institutional
                                    Email</label>
                                <input type="email" name="email" required placeholder="student0@vu.edu"
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Student ID(Reg)
                                    No</label>
                                <input type="text" name="id_no" required placeholder="e.g., S-0123"
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Password</label>
                                <input type="password" name="password" required placeholder="••••••••"
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Active
                                    Semester</label>
                                <select name="semester" required
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>{$i}th Semester</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="add_student"
                            class="w-full gold-bg hover:opacity-90 font-bold py-3.5 rounded-xl text-sm transition shadow-md cursor-pointer"
                            style="color:#3d0510;">
                            Enroll Student Node
                        </button>
                    </form>

                    <!-- Course creation form -->
                    <form id="form-course" class="admin-tab-panel hidden space-y-5">
                        <div class="mb-1">
                            <h3 class="text-sm font-black uppercase gold-accent tracking-wider font-display">Course
                                Curriculum
                                Node</h3>
                            <p class="text-xs text-muted-c mt-0.5">Map a new course and assign a faculty member</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Course
                                    Code</label>
                                <input type="text" name="course_code" required placeholder="e.g., CSE-3112"
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Target
                                    Semester</label>
                                <select name="semester" required
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>Semester $i</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Course Curriculum
                                Title</label>
                            <input type="text" name="title" required placeholder="e.g., Software Engineering Lab"
                                class="w-full input-field p-3 rounded-xl text-sm transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Assign Faculty
                                Teacher</label>
                            <select name="teacher_id" required
                                class="w-full input-field p-3 rounded-xl text-sm transition">
                                <option value="">-- Select a Faculty Member --</option>
                                <?php
                                if ($teachers_list->num_rows > 0) {
                                    while ($t = $teachers_list->fetch_assoc()) {
                                        echo "<option value='" . $t['id'] . "'>👨‍🏫 " . htmlspecialchars($t['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="add_course"
                            class="w-full gold-bg hover:opacity-90 font-bold py-3.5 rounded-xl text-sm transition shadow-md cursor-pointer"
                            style="color:#3d0510;">
                            Deploy Course Blueprint
                        </button>
                    </form>

                    <!-- GPA form -->
                    <form id="form-gpa" class="admin-tab-panel hidden space-y-5">
                        <div class="mb-1">
                            <h3 class="text-sm font-black uppercase gold-accent tracking-wider font-display">Academic
                                Performance Node</h3>
                            <p class="text-xs text-muted-c mt-0.5">Insert or update a student's semester GPA</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Select
                                    Student</label>
                                <select name="student_id" required id="gpa-student-select"
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                                    <option value="">-- Select a Student --</option>
                                    <?php
                                    foreach ($students_array as $s) {
                                        echo "<option value='" . $s['id'] . "'>#" . $s['id'] . " — " . htmlspecialchars($s['name']) . " (" . htmlspecialchars($s['id_no']) . ", Sem " . (int) $s['semester'] . ")</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Target
                                    Semester</label>
                                <select name="semester_no" required
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>Semester $i</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Earned
                                    GPA</label>
                                <input type="number" step="0.01" min="0.00" max="4.00" name="gpa" required
                                    placeholder="e.g., 3.85"
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                            </div>
                        </div>
                        <button type="submit" name="submit_gpa"
                            class="w-full gold-bg hover:opacity-90 font-bold py-3.5 rounded-xl text-sm transition shadow-md cursor-pointer"
                            style="color:#3d0510;">
                            Publish Semester Result
                        </button>
                    </form>

                    <!-- Teacher creation form -->
                    <form id="form-teacher" class="admin-tab-panel hidden space-y-5">
                        <div class="mb-1">
                            <h3 class="text-sm font-black uppercase gold-accent tracking-wider font-display">Faculty
                                Onboarding Node</h3>
                            <p class="text-xs text-muted-c mt-0.5">Submit a new teacher record to the database</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Full Name</label>
                            <input type="text" name="name" required placeholder="e.g., Dr. Farhana Islam"
                                class="w-full input-field p-3 rounded-xl text-sm transition">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Institutional
                                    Email</label>
                                <input type="email" name="email" required placeholder="teacher@varsity.edu"
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Password</label>
                                <input type="password" name="password" required placeholder="••••••••"
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                            </div>
                        </div>
                        <button type="submit" name="add_teacher"
                            class="w-full gold-bg hover:opacity-90 font-bold py-3.5 rounded-xl text-sm transition shadow-md cursor-pointer"
                            style="color:#3d0510;">
                            Onboard Faculty Member
                        </button>
                    </form>
                </div>
            </div>

            <!-- Course table -->
            <div id="course-section" class="card card-lg-shadow rounded-2xl overflow-hidden">
                <div class="p-5 sm:p-6 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    style="border-color: var(--border-card);">
                    <h4 class="text-sm font-black uppercase text-muted-c tracking-wider font-display">🖥️ Active Course
                        Allocation Hub</h4>
                    <input type="text" id="course-search" placeholder="🔍 Search by code, title, or faculty..."
                        oninput="filterCourseTable()"
                        class="input-field px-4 py-2.5 rounded-xl text-sm w-full sm:w-80 transition">
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse data-table" id="course-table">
                        <thead>
                            <tr class="border-b text-muted-c font-bold uppercase tracking-wider text-xs"
                                style="border-color: var(--border-card);">
                                <th class="p-4">Course Code</th>
                                <th class="p-4">Course Title</th>
                                <th class="p-4">Semester map</th>
                                <th class="p-4">Faculty Member</th>
                                <th class="p-4 text-center">Live Monitoring / Command</th>
                                <th class="p-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" id="course-table-body" style="border-color: var(--border-card);">
                            <?php
                            if ($courses_list->num_rows > 0) {
                                while ($c = $courses_list->fetch_assoc()) {
                                    $row_live_class = $c['is_live'] > 0 ? 'bg-red-50/30 border-l-4 border-l-red-500' : '';
                                    $search_blob = strtolower($c['course_code'] . ' ' . $c['title'] . ' ' . ($c['teacher_name'] ?? ''));
                                    ?>
                                    <tr class="transition <?php echo $row_live_class; ?>"
                                        data-search="<?php echo htmlspecialchars($search_blob); ?>">
                                        <td class="p-4 font-mono font-bold gold-accent text-sm">
                                            <?php echo htmlspecialchars($c['course_code']); ?>
                                        </td>
                                        <td class="p-4 font-semibold text-main"><?php echo htmlspecialchars($c['title']); ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="input-field font-bold px-2.5 py-1 rounded-md text-xs">Sem
                                                <?php echo $c['semester']; ?></span>
                                        </td>
                                        <td class="p-4" data-teacher-cell data-course-id="<?php echo $c['id']; ?>">
                                            <select onchange="assignTeacher(<?php echo $c['id']; ?>, this)"
                                                class="input-field text-xs font-medium px-2.5 py-1.5 rounded-lg transition cursor-pointer">
                                                <option value="">— Unassigned —</option>
                                                <?php
                                                foreach ($teachers_array as $t) {
                                                    $sel = ($c['teacher_id'] == $t['id']) ? 'selected' : '';
                                                    echo "<option value='{$t['id']}' $sel>👨‍🏫 " . htmlspecialchars($t['name']) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td class="p-4 text-center" data-live-cell data-course-id="<?php echo $c['id']; ?>">
                                            <?php if ($c['is_live'] > 0 && !empty($c['start_timestamp'])) { ?>
                                                <div class="flex items-center justify-center space-x-3">
                                                    <span data-start="<?php echo $c['start_timestamp']; ?>"
                                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-black bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/10 animate-pulse run-live-badge">
                                                        🟢 ON AIR
                                                    </span>
                                                    <button type="button" onclick="confirmTerminate(<?php echo $c['id']; ?>)"
                                                        class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition shadow-sm cursor-pointer">
                                                        Terminate
                                                    </button>
                                                </div>
                                            <?php } else { ?>
                                                <span class="text-muted-c text-sm font-medium">Idle Mode</span>
                                            <?php } ?>
                                        </td>
                                        <td class="p-4 text-center">
                                            <button type="button"
                                                onclick="confirmRemoveCourse(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['course_code'])); ?>')"
                                                class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">🗑️
                                                Remove</button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr id='no-courses-row'><td colspan='6' class='text-center p-8 text-muted-c italic text-sm'>No data mapping loops registered yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <p id="no-search-results" class="hidden text-center p-8 text-muted-c italic text-sm">No courses
                        match your search.</p>
                </div>
            </div>

            <!-- Faculty Directory -->
            <div id="teacher-section" class="card card-lg-shadow rounded-2xl overflow-hidden">
                <div class="p-5 sm:p-6 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    style="border-color: var(--border-card);">
                    <h4 class="text-sm font-black uppercase text-muted-c tracking-wider font-display">👨‍🏫 Faculty
                        Directory</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse data-table">
                        <thead>
                            <tr class="border-b text-muted-c font-bold uppercase tracking-wider text-xs"
                                style="border-color: var(--border-card);">
                                <th class="p-4">Teacher</th>
                                <th class="p-4">Courses Assigned</th>
                                <th class="p-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" id="teacher-table-body" style="border-color: var(--border-card);">
                            <?php
                            if (count($teachers_directory_array) > 0) {
                                foreach ($teachers_directory_array as $t) {
                                    ?>
                                    <tr data-teacher-row="<?php echo $t['id']; ?>">
                                        <td class="p-4">
                                            <p class="font-semibold text-main"><?php echo htmlspecialchars($t['name']); ?></p>
                                            <p class="text-xs text-muted-c"><?php echo htmlspecialchars($t['email']); ?></p>
                                        </td>
                                        <td class="p-4 text-xs">
                                            <span class="emerald-text font-bold"><?php echo (int) $t['course_count']; ?></span>
                                            <span class="text-muted-c"> course(s) assigned</span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <button type="button"
                                                onclick="confirmRemoveTeacher(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars(addslashes($t['name'])); ?>')"
                                                class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">🗑️
                                                Remove</button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr id='no-teachers-row'><td colspan='3' class='text-center p-8 text-muted-c italic text-sm'>No faculty members yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Students Directory -->
            <div id="student-section" class="card card-lg-shadow rounded-2xl overflow-hidden">
                <div class="p-5 sm:p-6 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    style="border-color: var(--border-card);">
                    <h4 class="text-sm font-black uppercase text-muted-c tracking-wider font-display">🎓 Students
                        Directory</h4>
                    <div class="flex flex-col sm:flex-row gap-2.5 w-full sm:w-auto">
                        <select id="student-semester-filter" onchange="filterStudentTable()"
                            class="input-field px-3 py-2.5 rounded-xl text-sm transition">
                            <option value="">All Semesters</option>
                            <?php for ($i = 1; $i <= 8; $i++)
                                echo "<option value='$i'>Semester $i</option>"; ?>
                        </select>
                        <input type="text" id="student-search" placeholder="🔍 Search by name, email, or ID..."
                            oninput="filterStudentTable()"
                            class="input-field px-4 py-2.5 rounded-xl text-sm w-full sm:w-72 transition">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse data-table">
                        <thead>
                            <tr class="border-b text-muted-c font-bold uppercase tracking-wider text-xs"
                                style="border-color: var(--border-card);">
                                <th class="p-4">Student</th>
                                <th class="p-4">ID No</th>
                                <th class="p-4">Semester</th>
                                <th class="p-4">CGPA</th>
                                <th class="p-4">Courses</th>
                                <th class="p-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" id="student-table-body" style="border-color: var(--border-card);">
                            <?php
                            if (count($students_array) > 0) {
                                foreach ($students_array as $s) {
                                    $search_blob = strtolower($s['name'] . ' ' . $s['email'] . ' ' . $s['id_no'] . ' ' . $s['id']);
                                    $cgpa_display = $s['cgpa'] !== null ? number_format($s['cgpa'], 2) : '—';
                                    ?>
                                    <tr class="transition" data-search="<?php echo htmlspecialchars($search_blob); ?>"
                                        data-semester="<?php echo (int) $s['semester']; ?>"
                                        data-student-row="<?php echo $s['id']; ?>">
                                        <td class="p-4">
                                            <p class="font-semibold text-main" data-field-name>
                                                <?php echo htmlspecialchars($s['name']); ?>
                                            </p>
                                            <p class="text-xs text-muted-c" data-field-email>
                                                <?php echo htmlspecialchars($s['email']); ?>
                                            </p>
                                            <p class="text-[11px] font-mono gold-accent font-bold mt-0.5" data-field-sqlid>
                                                SQL ID: #<?php echo (int) $s['id']; ?>
                                            </p>
                                        </td>
                                        <td class="p-4 font-mono text-xs text-muted-c" data-field-idno>
                                            <?php echo htmlspecialchars($s['id_no']); ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="input-field font-bold px-2.5 py-1 rounded-md text-xs"
                                                data-sem-badge>Sem
                                                <?php echo (int) $s['semester']; ?></span>
                                        </td>
                                        <td class="p-4">
                                            <span class="font-bold gold-accent text-sm"
                                                data-field-cgpa><?php echo $cgpa_display; ?></span>
                                        </td>
                                        <td class="p-4 text-xs">
                                            <span class="emerald-text font-bold"><?php echo (int) $s['active_courses']; ?>
                                                active</span>
                                            <span class="text-muted-c"> · <?php echo (int) $s['completed_courses']; ?>
                                                archived</span>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center justify-center gap-2 flex-wrap">
                                                <button type="button"
                                                    onclick='openEditStudent(<?php echo json_encode(["id" => $s['id'], "name" => $s['name'], "email" => $s['email'], "id_no" => $s['id_no'], "semester" => (int) $s['semester']]); ?>)'
                                                    class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">✏️
                                                    Edit</button>
                                                <button type="button"
                                                    onclick="openManageCourses(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>')"
                                                    class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">📚
                                                    Manage</button>
                                                <button type="button"
                                                    onclick="confirmPromote(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>')"
                                                    class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">⬆️
                                                    Promote</button>
                                                <button type="button"
                                                    onclick="confirmDemote(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>')"
                                                    class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">⬇️
                                                    Demote</button>
                                                <button type="button"
                                                    onclick="confirmArchiveStudent(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>')"
                                                    class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">🗄️
                                                    Archive</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr id='no-students-row'><td colspan='6' class='text-center p-8 text-muted-c italic text-sm'>No students enrolled yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <p id="no-student-search-results" class="hidden text-center p-8 text-muted-c italic text-sm">No
                        students match your search.</p>
                </div>
            </div>

            <!-- Old Students (Archived) Directory -->
            <div id="old-student-section" class="card card-lg-shadow rounded-2xl overflow-hidden">
                <div class="p-5 sm:p-6 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    style="border-color: var(--border-card);">
                    <div>
                        <h4 class="text-sm font-black uppercase text-muted-c tracking-wider font-display">🗄️ Old
                            Students (Archived)</h4>
                        <p class="text-xs text-muted-c mt-0.5">These accounts and records are kept — not deleted —
                            and can be restored any time.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse data-table">
                        <thead>
                            <tr class="border-b text-muted-c font-bold uppercase tracking-wider text-xs"
                                style="border-color: var(--border-card);">
                                <th class="p-4">Student</th>
                                <th class="p-4">ID No</th>
                                <th class="p-4">Last Semester</th>
                                <th class="p-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" id="old-student-table-body" style="border-color: var(--border-card);">
                            <?php
                            if (count($old_students_array) > 0) {
                                foreach ($old_students_array as $s) {
                                    ?>
                                    <tr data-old-student-row="<?php echo $s['id']; ?>">
                                        <td class="p-4">
                                            <p class="font-semibold text-main"><?php echo htmlspecialchars($s['name']); ?></p>
                                            <p class="text-xs text-muted-c"><?php echo htmlspecialchars($s['email']); ?></p>
                                        </td>
                                        <td class="p-4 font-mono text-xs text-muted-c">
                                            <?php echo htmlspecialchars($s['id_no']); ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="input-field font-bold px-2.5 py-1 rounded-md text-xs">Sem
                                                <?php echo (int) $s['semester']; ?></span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <button type="button"
                                                onclick="confirmRestoreStudent(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>')"
                                                class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">♻️
                                                Restore</button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr id='no-old-students-row'><td colspan='4' class='text-center p-8 text-muted-c italic text-sm'>No archived students.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Manage Courses modal -->
            <div id="manage-courses-modal"
                class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/50 p-4">
                <div
                    class="card card-lg-shadow rounded-2xl p-6 sm:p-7 max-w-lg w-full space-y-5 max-h-[85vh] overflow-y-auto">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-display text-lg font-semibold text-main">Manage Courses</h3>
                            <p class="text-xs text-muted-c mt-0.5" id="manage-courses-student-name"></p>
                        </div>
                        <button onclick="closeManageCourses()"
                            class="text-muted-c hover:opacity-70 text-xl cursor-pointer leading-none">✕</button>
                    </div>

                    <div>
                        <h4 class="text-xs font-black uppercase gold-accent tracking-wider mb-2">🟢 Active Courses</h4>
                        <div id="manage-active-list" class="space-y-2 text-sm"></div>
                    </div>

                    <div>
                        <h4 class="text-xs font-black uppercase text-muted-c tracking-wider mb-2">🗄️ Archived
                            (Completed)</h4>
                        <div id="manage-completed-list" class="space-y-2 text-sm"></div>
                    </div>

                    <div class="pt-2 border-t" style="border-color: var(--border-card);">
                        <h4 class="text-xs font-black uppercase gold-accent tracking-wider mb-2">➕ Assign New Course
                        </h4>
                        <div class="flex flex-col sm:flex-row gap-2.5">
                            <select id="manage-available-select"
                                class="w-full input-field p-2.5 rounded-xl text-sm"></select>
                            <button type="button" onclick="enrollSelectedCourse()"
                                class="gold-bg hover:opacity-90 font-bold px-4 py-2.5 rounded-xl text-sm transition shadow-md cursor-pointer whitespace-nowrap"
                                style="color:#3d0510;">Assign</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Student modal -->
            <div id="edit-student-modal"
                class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/50 p-4">
                <div class="card card-lg-shadow rounded-2xl p-6 sm:p-7 max-w-md w-full space-y-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-display text-lg font-semibold text-main">Edit Student</h3>
                            <p class="text-xs text-muted-c mt-0.5">Update this student's account information</p>
                        </div>
                        <button onclick="closeEditStudent()"
                            class="text-muted-c hover:opacity-70 text-xl cursor-pointer leading-none">✕</button>
                    </div>
                    <form id="form-edit-student" class="space-y-4">
                        <input type="hidden" id="edit-student-id">
                        <div>
                            <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Full Name</label>
                            <input type="text" id="edit-student-name" required
                                class="w-full input-field p-3 rounded-xl text-sm transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Institutional
                                Email</label>
                            <input type="email" id="edit-student-email" required
                                class="w-full input-field p-3 rounded-xl text-sm transition">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Student ID
                                    No</label>
                                <input type="text" id="edit-student-idno" required
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">Semester</label>
                                <select id="edit-student-semester" required
                                    class="w-full input-field p-3 rounded-xl text-sm transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>Semester $i</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-muted-c uppercase mb-1.5">New Password
                                <span class="normal-case font-medium">(leave blank to keep unchanged)</span></label>
                            <input type="password" id="edit-student-password" placeholder="••••••••"
                                class="w-full input-field p-3 rounded-xl text-sm transition">
                        </div>
                        <div class="flex justify-end gap-2.5 pt-1">
                            <button type="button" onclick="closeEditStudent()"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold border border-current text-muted-c hover:opacity-70 transition cursor-pointer">Cancel</button>
                            <button type="submit"
                                class="gold-bg hover:opacity-90 font-bold px-5 py-2.5 rounded-xl text-sm transition shadow-md cursor-pointer"
                                style="color:#3d0510;">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>

    <script>
        /* ---------- Theme (dark/light) toggle ---------- */
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            const icon = theme === 'dark' ? '☀️' : '🌙';
            const label = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
            document.getElementById('theme-icon').textContent = icon;
            document.getElementById('theme-label').textContent = label;
            document.getElementById('mobile-theme-btn').textContent = icon;
        }
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('vv_theme', next);
            applyTheme(next);
        }
        (function initTheme() {
            const saved = localStorage.getItem('vv_theme') ||
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            applyTheme(saved);
        })();

        /* ---------- Mobile sidebar ---------- */
        function toggleMobileSidebar(open) {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('mobile-sidebar-backdrop');
            if (open) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        /* ---------- Toast notifications ---------- */
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const borderColor = type === 'success' ? '#16a34a' : '#dc2626';
            toast.className = 'card card-lg-shadow rounded-xl p-4 text-sm font-semibold text-main shadow-lg';
            toast.style.cssText = `border-left: 4px solid ${borderColor}; animation: toast-in .3s ease;`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(120%)';
                toast.style.transition = 'all .3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        /* ---------- Tab switcher ---------- */
        function switchAdminTab(name) {
            document.querySelectorAll('.admin-tab-panel').forEach(p => p.classList.add('hidden'));
            document.getElementById(`form-${name}`).classList.remove('hidden');
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(`tab-btn-${name}`).classList.add('active');
        }

        /* ---------- Count-up stats animation ---------- */
        document.querySelectorAll('[data-countup]').forEach(el => {
            const target = parseInt(el.getAttribute('data-countup'), 10) || 0;
            let current = 0;
            const step = Math.max(1, Math.ceil(target / 30));
            const timer = setInterval(() => {
                current += step;
                if (current >= target) { current = target; clearInterval(timer); }
                el.textContent = current;
            }, 20);
        });

        /* ---------- Course table search/filter ---------- */
        function filterCourseTable() {
            const query = document.getElementById('course-search').value.trim().toLowerCase();
            const rows = document.querySelectorAll('#course-table-body tr[data-search]');
            let visibleCount = 0;
            rows.forEach(row => {
                const matches = row.getAttribute('data-search').includes(query);
                row.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });
            document.getElementById('no-search-results').classList.toggle('hidden', visibleCount !== 0 || rows.length === 0);
        }

        /* ---------- Custom confirm modal ---------- */
        let _confirmCallback = null;
        function openConfirmModal(message, onConfirm) {
            document.getElementById('confirm-modal-message').textContent = message;
            _confirmCallback = onConfirm;
            const modal = document.getElementById('confirm-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeConfirmModal() {
            const modal = document.getElementById('confirm-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            _confirmCallback = null;
        }
        document.getElementById('confirm-modal-confirm-btn').addEventListener('click', () => {
            if (_confirmCallback) _confirmCallback();
            closeConfirmModal();
        });

        /* ---------- Terminate live class (AJAX, no page reload) ---------- */
        function confirmTerminate(courseId) {
            openConfirmModal('Force terminate this faculty live stream? Students will lose access to the live session immediately.', () => {
                terminateLive(courseId);
            });
        }

        async function terminateLive(courseId) {
            const formData = new FormData();
            formData.append('course_id', courseId);
            formData.append('stop_live', '1');
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const result = await response.json();
                if (result.status === 'success') {
                    const cell = document.querySelector(`[data-live-cell][data-course-id="${courseId}"]`);
                    if (cell) cell.innerHTML = '<span class="text-muted-c text-sm font-medium">Idle Mode</span>';
                    showToast(result.message.replace(/^[^\w]*/, ''), 'success');
                } else {
                    showToast(result.message || 'Something went wrong.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while terminating session.', 'error');
            }
        }

        /* ---------- AJAX form submission (Student / Course / GPA) - no page reload ----------
           FIXED VERSION — two bugs fixed here:

           1) THE MAIN BUG (this is what was breaking Enroll Student / Deploy Course / Publish GPA):
              Each submit button was written as <button type="submit" name="add_student"> etc.
              When a form is submitted the *normal* (native) way, the browser automatically
              includes that button's name=value pair in the POST data. But here the submit event
              is intercepted with e.preventDefault(), and `new FormData(form)` does NOT
              automatically include the submitter button's name/value in that case.
              Result: $_POST['add_student'] / $_POST['add_course'] / $_POST['submit_gpa'] were
              NEVER set on the PHP side, so none of the "isset($_POST[...])" blocks ran. PHP fell
              through to the bottom of the file and rendered the full HTML dashboard page instead
              of a JSON reply — while the Content-Type header still said "application/json" — so
              response.json() / JSON.parse() failed with "Server returned an invalid response".
              FIX: explicitly formData.append(actionName, '1') before sending.

           2) Reads the raw response text first and JSON.parse()'s it manually, instead of
              calling response.json() directly, so if the server ever returns non-JSON again,
              the raw text is logged to the console instead of a generic failure.
        ---------------------------------------------------------------------------------- */
        function setupAjaxForm(formId, actionName) {
            const form = document.getElementById(formId);
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';
                submitBtn.classList.add('opacity-60', 'cursor-not-allowed');

                const formData = new FormData(form);
                // Manually include the action flag the PHP side checks with isset($_POST[...]).
                formData.append(actionName, '1');

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });

                    const rawText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(rawText);
                    } catch (parseErr) {
                        console.error('Server did not return valid JSON. Raw response below:');
                        console.error(rawText);
                        showToast('Server returned an invalid response — open the browser console (F12) to see the raw output.', 'error');
                        return;
                    }

                    showToast((result.message || 'Done.').replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                    if (result.status === 'success') {
                        form.reset();
                        if (formId === 'form-course' && result.course) {
                            addCourseRowToTable(result.course);
                        }
                        if (formId === 'form-student' && result.student) {
                            addStudentRowToTable(result.student);
                        }
                        if (formId === 'form-teacher' && result.teacher) {
                            addTeacherRowToTable(result.teacher);
                        }
                    }
                } catch (err) {
                    console.error(err);
                    showToast('Network error. Please try again.', 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
            });
        }
        setupAjaxForm('form-student', 'add_student');
        setupAjaxForm('form-course', 'add_course');
        setupAjaxForm('form-gpa', 'submit_gpa');
        setupAjaxForm('form-teacher', 'add_teacher');

        const teachersData = <?php echo json_encode($teachers_array); ?>;
        const studentsData = <?php echo json_encode($students_array); ?>;
        const oldStudentsData = <?php echo json_encode($old_students_array); ?>;

        function addStudentRowToTable(s) {
            const noRow = document.getElementById('no-students-row');
            if (noRow) noRow.remove();

            const gpaSelect = document.getElementById('gpa-student-select');
            if (gpaSelect) {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = `#${s.id} — ${s.name} (${s.id_no}, Sem ${s.semester})`;
                gpaSelect.appendChild(opt);
            }

            studentsData.push(s);

            const tbody = document.getElementById('student-table-body');
            const tr = document.createElement('tr');
            const searchBlob = `${s.name} ${s.email} ${s.id_no} ${s.id}`.toLowerCase();
            tr.className = 'transition';
            tr.setAttribute('data-search', searchBlob);
            tr.setAttribute('data-semester', s.semester);
            tr.setAttribute('data-student-row', s.id);
            const cgpaDisplay = (s.cgpa !== null && s.cgpa !== undefined) ? Number(s.cgpa).toFixed(2) : '—';
            tr.innerHTML = `
                <td class="p-4">
                    <p class="font-semibold text-main" data-field-name>${s.name}</p>
                    <p class="text-xs text-muted-c" data-field-email>${s.email}</p>
                    <p class="text-[11px] font-mono gold-accent font-bold mt-0.5" data-field-sqlid>SQL ID: #${s.id}</p>
                </td>
                <td class="p-4 font-mono text-xs text-muted-c" data-field-idno>${s.id_no}</td>
                <td class="p-4"><span class="input-field font-bold px-2.5 py-1 rounded-md text-xs" data-sem-badge>Sem ${s.semester}</span></td>
                <td class="p-4"><span class="font-bold gold-accent text-sm" data-field-cgpa>${cgpaDisplay}</span></td>
                <td class="p-4 text-xs"><span class="emerald-text font-bold">${s.active_courses || 0} active</span><span class="text-muted-c"> · ${s.completed_courses || 0} archived</span></td>
                <td class="p-4">
                    <div class="flex items-center justify-center gap-2 flex-wrap">
                        <button type="button" onclick='openEditStudent(${JSON.stringify({ id: s.id, name: s.name, email: s.email, id_no: s.id_no, semester: s.semester })})' class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">✏️ Edit</button>
                        <button type="button" onclick="openManageCourses(${s.id}, '${s.name.replace(/'/g, "\\'")}')" class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">📚 Manage</button>
                        <button type="button" onclick="confirmPromote(${s.id}, '${s.name.replace(/'/g, "\\'")}')" class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">⬆️ Promote</button>
                        <button type="button" onclick="confirmDemote(${s.id}, '${s.name.replace(/'/g, "\\'")}')" class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">⬇️ Demote</button>
                        <button type="button" onclick="confirmArchiveStudent(${s.id}, '${s.name.replace(/'/g, "\\'")}')" class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">🗄️ Archive</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
            renderSemesterStat();
        }

        /* ---------- Add a new teacher row + keep every teacher <select> in sync ---------- */
        function addTeacherRowToTable(t) {
            const noRow = document.getElementById('no-teachers-row');
            if (noRow) noRow.remove();

            teachersData.push(t);

            // Add option to the "Deploy Course" form's teacher select
            const courseFormSelect = document.querySelector('#form-course select[name="teacher_id"]');
            if (courseFormSelect) {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = `👨‍🏫 ${t.name}`;
                courseFormSelect.appendChild(opt);
            }

            // Add option to every existing course row's inline "assign teacher" select
            document.querySelectorAll('[data-teacher-cell] select').forEach(sel => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = `👨‍🏫 ${t.name}`;
                sel.appendChild(opt);
            });

            // Bump the "Active Faculty" stat card
            const statEl = document.getElementById('stat-faculty-count');
            if (statEl) statEl.textContent = (parseInt(statEl.textContent, 10) || 0) + 1;

            const tbody = document.getElementById('teacher-table-body');
            const tr = document.createElement('tr');
            tr.setAttribute('data-teacher-row', t.id);
            tr.innerHTML = `
                <td class="p-4">
                    <p class="font-semibold text-main">${t.name}</p>
                    <p class="text-xs text-muted-c">${t.email}</p>
                </td>
                <td class="p-4 text-xs">
                    <span class="emerald-text font-bold">${t.course_count || 0}</span>
                    <span class="text-muted-c"> course(s) assigned</span>
                </td>
                <td class="p-4 text-center">
                    <button type="button" onclick="confirmRemoveTeacher(${t.id}, '${t.name.replace(/'/g, "\\'")}')" class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">🗑️ Remove</button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        /* ---------- Remove a teacher (courses they taught become Unassigned, not deleted) ---------- */
        function confirmRemoveTeacher(teacherId, name) {
            openConfirmModal(`Permanently remove ${name} from the faculty roster? Any courses currently assigned to them will switch to "— Unassigned —". This cannot be undone.`, () => {
                removeTeacher(teacherId);
            });
        }
        async function removeTeacher(teacherId) {
            const formData = new FormData();
            formData.append('remove_teacher', '1');
            formData.append('teacher_id', teacherId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    removeTeacherEverywhere(teacherId);
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while removing teacher.', 'error');
            }
        }
        function removeTeacherEverywhere(teacherId) {
            // Drop from the in-memory teacher list
            const idx = teachersData.findIndex(t => t.id == teacherId);
            if (idx > -1) teachersData.splice(idx, 1);

            // Remove the directory row
            const row = document.querySelector(`tr[data-teacher-row="${teacherId}"]`);
            if (row) row.remove();
            const tbody = document.getElementById('teacher-table-body');
            if (tbody && tbody.children.length === 0) {
                tbody.innerHTML = "<tr id='no-teachers-row'><td colspan='3' class='text-center p-8 text-muted-c italic text-sm'>No faculty members yet.</td></tr>";
            }

            // Remove the option from the "Deploy Course" form's teacher select
            const courseFormSelect = document.querySelector('#form-course select[name="teacher_id"]');
            if (courseFormSelect) {
                Array.from(courseFormSelect.options).forEach(opt => { if (opt.value == teacherId) opt.remove(); });
            }

            // Remove the option from every course row's inline "assign teacher" select,
            // and reset it to "— Unassigned —" if that teacher was the one selected
            document.querySelectorAll('[data-teacher-cell] select').forEach(sel => {
                const wasSelected = sel.value == teacherId;
                Array.from(sel.options).forEach(opt => { if (opt.value == teacherId) opt.remove(); });
                if (wasSelected) sel.value = '';
            });

            // Drop the "Active Faculty" stat card
            const statEl = document.getElementById('stat-faculty-count');
            if (statEl) statEl.textContent = Math.max(0, (parseInt(statEl.textContent, 10) || 0) - 1);
        }

        function buildTeacherSelectHTML(courseId, selectedTeacherName) {
            let opts = `<option value="">— Unassigned —</option>`;
            teachersData.forEach(t => {
                const sel = (t.name === selectedTeacherName) ? 'selected' : '';
                opts += `<option value="${t.id}" ${sel}>👨‍🏫 ${t.name}</option>`;
            });
            return `<select onchange="assignTeacher(${courseId}, this)" class="input-field text-xs font-medium px-2.5 py-1.5 rounded-lg transition cursor-pointer">${opts}</select>`;
        }

        function addCourseRowToTable(course) {
            const noRow = document.getElementById('no-courses-row');
            if (noRow) noRow.remove();

            const tbody = document.getElementById('course-table-body');
            const tr = document.createElement('tr');
            const searchBlob = `${course.course_code} ${course.title} ${course.teacher_name}`.toLowerCase();
            tr.className = 'transition';
            tr.setAttribute('data-search', searchBlob);
            tr.innerHTML = `
                <td class="p-4 font-mono font-bold gold-accent text-sm">${course.course_code}</td>
                <td class="p-4 font-semibold text-main">${course.title}</td>
                <td class="p-4"><span class="input-field font-bold px-2.5 py-1 rounded-md text-xs">Sem ${course.semester}</span></td>
                <td class="p-4" data-teacher-cell data-course-id="${course.id || ''}">${buildTeacherSelectHTML(course.id || '', course.teacher_name)}</td>
                <td class="p-4 text-center"><span class="text-muted-c text-sm font-medium">Idle Mode</span></td>
                <td class="p-4 text-center"><button type="button" onclick="confirmRemoveCourse(${course.id || ''}, '${(course.course_code || '').replace(/'/g, "\\'")}')" class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">🗑️ Remove</button></td>
            `;
            tbody.appendChild(tr);
        }

        /* ---------- Inline teacher assign/unassign (course table) ---------- */
        async function assignTeacher(courseId, selectEl) {
            const teacherId = selectEl.value;
            const formData = new FormData();
            formData.append('assign_teacher', '1');
            formData.append('course_id', courseId);
            formData.append('teacher_id', teacherId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
            } catch (err) {
                console.error(err);
                showToast('Network error while updating faculty assignment.', 'error');
            }
        }

        /* ---------- Remove a course entirely (Active Course Allocation Hub) ---------- */
        function confirmRemoveCourse(courseId, code) {
            openConfirmModal(`Permanently remove course ${code}? This also deletes its enrollment and live-session records. This cannot be undone.`, () => {
                removeCourse(courseId);
            });
        }
        async function removeCourse(courseId) {
            const formData = new FormData();
            formData.append('remove_course', '1');
            formData.append('course_id', courseId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    const cell = document.querySelector(`[data-teacher-cell][data-course-id="${courseId}"]`);
                    const row = cell ? cell.closest('tr') : null;
                    if (row) row.remove();
                    const tbody = document.getElementById('course-table-body');
                    if (tbody && tbody.children.length === 0) {
                        tbody.innerHTML = "<tr id='no-courses-row'><td colspan='6' class='text-center p-8 text-muted-c italic text-sm'>No data mapping loops registered yet.</td></tr>";
                    }
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while removing course.', 'error');
            }
        }

        /* ---------- Students Directory: search + semester filter ---------- */
        function filterStudentTable() {
            const query = document.getElementById('student-search').value.trim().toLowerCase();
            const semester = document.getElementById('student-semester-filter').value;
            const rows = document.querySelectorAll('#student-table-body tr[data-search]');
            let visibleCount = 0;
            rows.forEach(row => {
                const matchesQuery = row.getAttribute('data-search').includes(query);
                const matchesSem = !semester || row.getAttribute('data-semester') === semester;
                const visible = matchesQuery && matchesSem;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });
            document.getElementById('no-student-search-results').classList.toggle('hidden', visibleCount !== 0 || rows.length === 0);
        }

        /* ---------- Semester headcount stat card ---------- */
        function renderSemesterStat() {
            const sem = document.getElementById('semester-stat-select').value;
            const matches = studentsData.filter(s => String(s.semester) === sem);
            document.getElementById('semester-stat-count').textContent = matches.length;
            const list = document.getElementById('semester-stat-list');
            list.innerHTML = matches.map(s => `<div>${s.name} <span class="font-mono">(${s.id_no})</span></div>`).join('') || '<div class="italic">No students in this semester.</div>';
        }
        document.addEventListener('DOMContentLoaded', renderSemesterStat);

        /* ---------- Promote student to next semester ---------- */
        function confirmPromote(studentId, name) {
            openConfirmModal(`Promote ${name} to their next semester? Their current semester's courses will remain visible, but new courses must be assigned separately via "Manage".`, () => {
                promoteStudent(studentId);
            });
        }
        async function promoteStudent(studentId) {
            const formData = new FormData();
            formData.append('promote_semester', '1');
            formData.append('student_id', studentId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    const row = document.querySelector(`tr[data-student-row="${studentId}"]`);
                    if (row) {
                        row.setAttribute('data-semester', result.new_semester);
                        row.querySelector('[data-sem-badge]').textContent = `Sem ${result.new_semester}`;
                    }
                    const rec = studentsData.find(s => s.id == studentId);
                    if (rec) rec.semester = result.new_semester;
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while promoting student.', 'error');
            }
        }

        /* ---------- Demote student to previous semester ---------- */
        function confirmDemote(studentId, name) {
            openConfirmModal(`Demote ${name} back to their previous semester? This does not remove any of their assigned courses.`, () => {
                demoteStudent(studentId);
            });
        }
        async function demoteStudent(studentId) {
            const formData = new FormData();
            formData.append('demote_semester', '1');
            formData.append('student_id', studentId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    const row = document.querySelector(`tr[data-student-row="${studentId}"]`);
                    if (row) {
                        row.setAttribute('data-semester', result.new_semester);
                        row.querySelector('[data-sem-badge]').textContent = `Sem ${result.new_semester}`;
                    }
                    const rec = studentsData.find(s => s.id == studentId);
                    if (rec) rec.semester = result.new_semester;
                    renderSemesterStat();
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while demoting student.', 'error');
            }
        }

        /* ---------- Edit student info (name, email, ID No, semester, password) ---------- */
        function openEditStudent(s) {
            document.getElementById('edit-student-id').value = s.id;
            document.getElementById('edit-student-name').value = s.name;
            document.getElementById('edit-student-email').value = s.email;
            document.getElementById('edit-student-idno').value = s.id_no;
            document.getElementById('edit-student-semester').value = s.semester;
            document.getElementById('edit-student-password').value = '';
            const modal = document.getElementById('edit-student-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeEditStudent() {
            const modal = document.getElementById('edit-student-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        document.getElementById('form-edit-student').addEventListener('submit', async (e) => {
            e.preventDefault();
            const studentId = document.getElementById('edit-student-id').value;
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            const formData = new FormData();
            formData.append('update_student', '1');
            formData.append('student_id', studentId);
            formData.append('name', document.getElementById('edit-student-name').value);
            formData.append('email', document.getElementById('edit-student-email').value);
            formData.append('id_no', document.getElementById('edit-student-idno').value);
            formData.append('semester', document.getElementById('edit-student-semester').value);
            formData.append('password', document.getElementById('edit-student-password').value);

            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    applyStudentEditToRow(result.student);
                    closeEditStudent();
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while updating student.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
        function applyStudentEditToRow(s) {
            const row = document.querySelector(`tr[data-student-row="${s.id}"]`);
            if (row) {
                row.querySelector('[data-field-name]').textContent = s.name;
                row.querySelector('[data-field-email]').textContent = s.email;
                row.querySelector('[data-field-idno]').textContent = s.id_no;
                row.querySelector('[data-sem-badge]').textContent = `Sem ${s.semester}`;
                row.setAttribute('data-semester', s.semester);
                row.setAttribute('data-search', `${s.name} ${s.email} ${s.id_no} ${s.id}`.toLowerCase());
            }
            const rec = studentsData.find(r => r.id == s.id);
            if (rec) { rec.name = s.name; rec.email = s.email; rec.id_no = s.id_no; rec.semester = s.semester; }
            renderSemesterStat();
        }

        /* ---------- Archive student (move to Old Students, NOT a delete) ---------- */
        function confirmArchiveStudent(studentId, name) {
            openConfirmModal(`Move ${name} to the Old Students archive? Their account and academic records are preserved (not deleted) and can be restored any time.`, () => {
                archiveStudent(studentId);
            });
        }
        async function archiveStudent(studentId) {
            const formData = new FormData();
            formData.append('archive_student', '1');
            formData.append('student_id', studentId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    moveStudentRowToArchive(studentId);
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while archiving student.', 'error');
            }
        }
        function moveStudentRowToArchive(studentId) {
            const rec = studentsData.find(s => s.id == studentId);
            const row = document.querySelector(`tr[data-student-row="${studentId}"]`);
            if (row) row.remove();
            const idx = studentsData.findIndex(s => s.id == studentId);
            if (idx > -1) studentsData.splice(idx, 1);
            renderSemesterStat();

            if (!rec) return;
            oldStudentsData.push(rec);
            const noOldRow = document.getElementById('no-old-students-row');
            if (noOldRow) noOldRow.remove();
            const tbody = document.getElementById('old-student-table-body');
            const tr = document.createElement('tr');
            tr.setAttribute('data-old-student-row', studentId);
            tr.innerHTML = `
                <td class="p-4">
                    <p class="font-semibold text-main">${rec.name}</p>
                    <p class="text-xs text-muted-c">${rec.email}</p>
                </td>
                <td class="p-4 font-mono text-xs text-muted-c">${rec.id_no}</td>
                <td class="p-4"><span class="input-field font-bold px-2.5 py-1 rounded-md text-xs">Sem ${rec.semester}</span></td>
                <td class="p-4 text-center">
                    <button type="button" onclick="confirmRestoreStudent(${studentId}, '${rec.name.replace(/'/g, "\\'")}')" class="input-field text-xs font-bold px-3 py-1.5 rounded-lg transition cursor-pointer">♻️ Restore</button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        /* ---------- Restore an archived student back to the active directory ---------- */
        function confirmRestoreStudent(studentId, name) {
            openConfirmModal(`Restore ${name} back to the active Students Directory?`, () => {
                restoreStudent(studentId);
            });
        }
        async function restoreStudent(studentId) {
            const formData = new FormData();
            formData.append('restore_student', '1');
            formData.append('student_id', studentId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    moveStudentRowToActive(studentId);
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while restoring student.', 'error');
            }
        }
        function moveStudentRowToActive(studentId) {
            const row = document.querySelector(`tr[data-old-student-row="${studentId}"]`);
            const rec = oldStudentsData.find(s => s.id == studentId);
            if (row) row.remove();
            const oIdx = oldStudentsData.findIndex(s => s.id == studentId);
            if (oIdx > -1) oldStudentsData.splice(oIdx, 1);

            const oldTbody = document.getElementById('old-student-table-body');
            if (oldTbody && oldTbody.children.length === 0) {
                oldTbody.innerHTML = "<tr id='no-old-students-row'><td colspan='4' class='text-center p-8 text-muted-c italic text-sm'>No archived students.</td></tr>";
            }

            if (rec) {
                rec.active_courses = rec.active_courses || 0;
                rec.completed_courses = rec.completed_courses || 0;
                addStudentRowToTable(rec);
            }
        }

        /* ---------- Manage Courses modal ---------- */
        let _manageCoursesStudentId = null;
        async function openManageCourses(studentId, name) {
            _manageCoursesStudentId = studentId;
            document.getElementById('manage-courses-student-name').textContent = name;
            document.getElementById('manage-active-list').innerHTML = '<p class="text-xs text-muted-c italic">Loading...</p>';
            document.getElementById('manage-completed-list').innerHTML = '';
            document.getElementById('manage-available-select').innerHTML = '';

            const modal = document.getElementById('manage-courses-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            const formData = new FormData();
            formData.append('get_student_courses', '1');
            formData.append('student_id', studentId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                if (result.status !== 'success') { showToast('Could not load course data.', 'error'); return; }

                const activeEl = document.getElementById('manage-active-list');
                activeEl.innerHTML = result.active.length ? result.active.map(c => `
                    <div class="flex items-center justify-between input-field p-2.5 rounded-lg">
                        <span><span class="font-mono font-bold gold-accent">${c.course_code}</span> — ${c.title} <span class="text-xs text-muted-c">(Sem ${c.semester})</span></span>
                        <button onclick="archiveCourse(${c.id})" class="text-xs font-bold px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-700 cursor-pointer">Mark Completed</button>
                    </div>`).join('') : '<p class="text-xs text-muted-c italic">No active courses.</p>';

                const completedEl = document.getElementById('manage-completed-list');
                completedEl.innerHTML = result.completed.length ? result.completed.map(c => `
                    <div class="flex items-center justify-between input-field p-2.5 rounded-lg opacity-70">
                        <span><span class="font-mono font-bold">${c.course_code}</span> — ${c.title} <span class="text-xs text-muted-c">(Sem ${c.semester})</span></span>
                        <span class="text-xs font-bold text-muted-c">🗄️ Archived</span>
                    </div>`).join('') : '<p class="text-xs text-muted-c italic">Nothing archived yet.</p>';

                const availEl = document.getElementById('manage-available-select');
                availEl.innerHTML = result.available.length
                    ? result.available.map(c => `<option value="${c.id}">${c.course_code} — ${c.title} (Sem ${c.semester})</option>`).join('')
                    : '<option value="">No unassigned courses left</option>';
            } catch (err) {
                console.error(err);
                showToast('Network error while loading course data.', 'error');
            }
        }
        function closeManageCourses() {
            const modal = document.getElementById('manage-courses-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            _manageCoursesStudentId = null;
        }

        async function archiveCourse(courseId) {
            const formData = new FormData();
            formData.append('archive_course', '1');
            formData.append('student_id', _manageCoursesStudentId);
            formData.append('course_id', courseId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    openManageCourses(_manageCoursesStudentId, document.getElementById('manage-courses-student-name').textContent);
                    refreshStudentCourseCounts(_manageCoursesStudentId);
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while archiving course.', 'error');
            }
        }

        async function enrollSelectedCourse() {
            const courseId = document.getElementById('manage-available-select').value;
            if (!courseId) { showToast('No course selected.', 'error'); return; }
            const formData = new FormData();
            formData.append('enroll_course', '1');
            formData.append('student_id', _manageCoursesStudentId);
            formData.append('course_id', courseId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    openManageCourses(_manageCoursesStudentId, document.getElementById('manage-courses-student-name').textContent);
                    refreshStudentCourseCounts(_manageCoursesStudentId);
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while assigning course.', 'error');
            }
        }

        // Refresh the "X active · Y archived" text on the directory row after a modal change
        async function refreshStudentCourseCounts(studentId) {
            const formData = new FormData();
            formData.append('get_student_courses', '1');
            formData.append('student_id', studentId);
            try {
                const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const result = await response.json();
                if (result.status !== 'success') return;
                const row = document.querySelector(`tr[data-student-row="${studentId}"] td:nth-child(4)`);
                if (row) {
                    row.innerHTML = `<span class="emerald-text font-bold">${result.active.length} active</span><span class="text-muted-c"> · ${result.completed.length} archived</span>`;
                }
            } catch (err) {
                console.error(err);
            }
        }

        /* ---------- ON AIR live timer ---------- */
        document.addEventListener('DOMContentLoaded', () => {
            const liveBadges = document.querySelectorAll('.run-live-badge');

            liveBadges.forEach((el) => {
                const timerSpan = document.createElement('span');
                timerSpan.className = "ml-2 font-mono text-xs bg-slate-900 text-white px-2 py-0.5 rounded-md tracking-wider font-bold";
                el.appendChild(timerSpan);

                const startTime = parseInt(el.getAttribute('data-start'), 10);

                function updateLiveTimer() {
                    const now = Math.floor(Date.now() / 1000);
                    const totalSecondsPassed = now - startTime;

                    if (totalSecondsPassed < 0) {
                        timerSpan.innerText = "00:00";
                        return;
                    }

                    const hours = Math.floor(totalSecondsPassed / 3600);
                    const minutes = Math.floor((totalSecondsPassed % 3600) / 60);
                    const seconds = totalSecondsPassed % 60;

                    if (hours > 0) {
                        timerSpan.innerText = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    } else {
                        timerSpan.innerText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }
                }

                updateLiveTimer();
                setInterval(updateLiveTimer, 1000);
            });

            <?php if ($message && !$is_ajax): ?>
            showToast(<?php echo json_encode(preg_replace('/^[^\w]*/u', '', strip_tags($message))); ?>, <?php echo strpos($message, '❌') === 0 ? "'error'" : "'success'"; ?>);
        <?php endif; ?>
        });
    </script>
</body>


</html>