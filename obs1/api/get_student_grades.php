<?php
session_start();
require_once '../db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Only students can view their grades
if ($user_type != 'student') {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Get student's grades
$sql = "SELECT c.course_code, c.title, g.total_grade, g.letter_grade
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.id
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = $user_id
        LIMIT 3";

$result = $conn->query($sql);

if ($result) {
    $grades = [];
    while ($row = $result->fetch_assoc()) {
        $grades[] = $row;
    }
    echo json_encode(['grades' => $grades]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
?>
