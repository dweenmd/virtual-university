<?php
/**
 * Shared attendance finalization helpers.
 * Include this file (right after db.php) in:
 * teacher_dashboard.php, student_dashboard.php, live_status.php, update_live_status.php
 */

if (!function_exists('try_finalize_attendance')) {
    /**
     * Finalizes attendance for one live "meet" session (one row in online_class_tests).
     * - Present: every student who has a row in attendance_verifications for this ct_id
     * - Absent: every OTHER student enrolled in the course (has an academic_records row)
     *
     * Safe to call from many concurrent requests (student polling every 5s) — the
     * UPDATE ... WHERE attendance_finalized = 0 claim guarantees only ONE caller
     * actually performs the present/absent increment, so counts never double up.
     *
     * @param mysqli $conn
     * @param int $ct_id   online_class_tests.id
     * @param bool $force  if true, finalize immediately regardless of the 2-minute
     *                     window elapsed check (used when a teacher manually ends
     *                     a live class, or starts a new one, before natural expiry)
     */
    function try_finalize_attendance($conn, $ct_id, $force = false)
    {
        $ct_id = intval($ct_id);
        if ($ct_id <= 0)
            return;

        $time_condition = $force
            ? "attendance_started_at IS NOT NULL"
            : "attendance_started_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, attendance_started_at, NOW()) >= 120";

        // Atomic claim: only the request that flips 0 -> 1 proceeds further.
        $conn->query("
            UPDATE online_class_tests 
            SET attendance_finalized = 1 
            WHERE id = '$ct_id' 
              AND test_type = 'meet' 
              AND attendance_finalized = 0 
              AND $time_condition
        ");

        if ($conn->affected_rows !== 1) {
            return; // not due yet, already finalized, or lost the race to another request
        }

        $course_res = $conn->query("SELECT course_id FROM online_class_tests WHERE id = '$ct_id' LIMIT 1");
        if (!$course_res || $course_res->num_rows === 0)
            return;
        $course_id = intval($course_res->fetch_assoc()['course_id']);

        // Present: everyone who verified this session
        $conn->query("
            UPDATE academic_records ar
            JOIN attendance_verifications av ON av.student_id = ar.student_id AND av.ct_id = '$ct_id'
            SET ar.total_days = ar.total_days + 1, ar.present_days = ar.present_days + 1
            WHERE ar.course_id = '$course_id'
        ");

        // Absent: everyone else enrolled in the course
        $conn->query("
            UPDATE academic_records ar
            SET ar.total_days = ar.total_days + 1
            WHERE ar.course_id = '$course_id'
              AND ar.student_id NOT IN (
                  SELECT student_id FROM attendance_verifications WHERE ct_id = '$ct_id'
              )
        ");
    }
}

if (!function_exists('is_attendance_verified')) {
    /**
     * DB-backed check (replaces the old $_SESSION-based check) — whether a specific
     * student has already verified attendance for a specific live session (ct_id).
     * Strictly scoped by student_id, so one student verifying can NEVER affect
     * what any other student sees on their dashboard.
     */
    function is_attendance_verified($conn, $ct_id, $student_id)
    {
        $ct_id = intval($ct_id);
        $student_id = intval($student_id);
        $res = $conn->query("SELECT id FROM attendance_verifications WHERE ct_id = '$ct_id' AND student_id = '$student_id' LIMIT 1");
        return ($res && $res->num_rows > 0);
    }
}
