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

header('Content-Type: application/json');

if ($user_type == 'student') {
    // Get student's enrolled courses
    $sql = "SELECT c.course_code, c.title, c.credits, u.name as instructor
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            JOIN users u ON c.instructor_id = u.id
            WHERE e.student_id = $user_id
            LIMIT 3";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
        echo json_encode(['courses' => $courses]);
    } else {
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
    }
} else if ($user_type == 'instructor') {
    // Get courses taught by instructor
    $sql = "SELECT c.course_code, c.title, c.credits, 
            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as student_count
            FROM courses c
            WHERE c.instructor_id = $user_id
            LIMIT 3";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
        echo json_encode(['courses' => $courses]);
    } else {
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
    }
} else {
    echo json_encode(['error' => 'Invalid user type']);
}
?>
