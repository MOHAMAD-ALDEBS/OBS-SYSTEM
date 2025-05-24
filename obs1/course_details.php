<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if course ID is provided
if (!isset($_GET['id'])) {
    header("Location: courses.php");
    exit;
}

$course_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if student is enrolled in this course (or if instructor teaches this course)
$access_granted = false;

if ($user_type == 'student') {
    $check_sql = "SELECT id FROM enrollments WHERE student_id = $user_id AND course_id = $course_id";
    $check_result = $conn->query($check_sql);
    if ($check_result->num_rows > 0) {
        $access_granted = true;
    }
} elseif ($user_type == 'instructor') {
    $check_sql = "SELECT id FROM courses WHERE instructor_id = $user_id AND id = $course_id";
    $check_result = $conn->query($check_sql);
    if ($check_result->num_rows > 0) {
        $access_granted = true;
    }
} elseif ($user_type == 'admin') {
    $access_granted = true;
}

if (!$access_granted) {
    header("Location: dashboard.php");
    exit;
}

// Get course details
$sql = "SELECT c.course_code, c.title, c.credits, c.description, u.name as instructor_name
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        WHERE c.id = $course_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: courses.php");
    exit;
}

$course = $result->fetch_assoc();

// Get enrolled students (only for instructors and admins)
$enrolled_students = [];
if ($user_type == 'instructor' || $user_type == 'admin') {
    $students_sql = "SELECT u.id, u.name
                    FROM enrollments e
                    JOIN users u ON e.student_id = u.id
                    WHERE e.course_id = $course_id
                    ORDER BY u.name";
    $students_result = $conn->query($students_sql);
    
    while ($row = $students_result->fetch_assoc()) {
        $enrolled_students[] = $row;
    }
}

// Get student's grade in this course (only for students)
$grade = null;
if ($user_type == 'student') {
    $grade_sql = "SELECT g.midterm, g.final, g.assignment, g.total_grade, g.letter_grade
                 FROM enrollments e
                 JOIN grades g ON e.id = g.enrollment_id
                 WHERE e.student_id = $user_id AND e.course_id = $course_id";
    $grade_result = $conn->query($grade_sql);
    
    if ($grade_result->num_rows > 0) {
        $grade = $grade_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $course['course_code']; ?> - Course Details</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2><?php echo $course['course_code']; ?>: <?php echo $course['title']; ?></h2>
            </div>
            
            <div class="course-details-card">
                <div class="course-info">
                    <div class="info-item">
                        <span class="label">Instructor:</span>
                        <span class="value"><?php echo $course['instructor_name']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Credits:</span>
                        <span class="value"><?php echo $course['credits']; ?></span>
                    </div>
                    <?php if (!empty($course['description'])): ?>
                    <div class="info-item">
                        <span class="label">Description:</span>
                        <span class="value"><?php echo $course['description']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($user_type == 'student' && $grade): ?>
                <div class="student-grade">
                    <h3>Your Grade</h3>
                    <div class="grade-details">
                        <div class="grade-item">
                            <span class="label">Midterm (40%):</span>
                            <span class="value"><?php echo is_null($grade['midterm']) ? '-' : $grade['midterm']; ?></span>
                        </div>
                        <div class="grade-item">
                            <span class="label">Final (50%):</span>
                            <span class="value"><?php echo is_null($grade['final']) ? '-' : $grade['final']; ?></span>
                        </div>
                        <div class="grade-item">
                            <span class="label">Assignment (10%):</span>
                            <span class="value"><?php echo is_null($grade['assignment']) ? '-' : $grade['assignment']; ?></span>
                        </div>
                        <div class="grade-item">
                            <span class="label">Total:</span>
                            <span class="value"><?php echo is_null($grade['total_grade']) ? '-' : $grade['total_grade']; ?></span>
                        </div>
                        <div class="grade-item">
                            <span class="label">Letter Grade:</span>
                            <span class="value letter-grade"><?php echo is_null($grade['letter_grade']) ? '-' : $grade['letter_grade']; ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($user_type == 'instructor' || $user_type == 'admin'): ?>
                <div class="enrolled-students">
                    <h3>Enrolled Students (<?php echo count($enrolled_students); ?>)</h3>
                    <?php if (!empty($enrolled_students)): ?>
                    <ul class="student-list">
                        <?php foreach ($enrolled_students as $student): ?>
                        <li><?php echo $student['name']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="no-data">No students enrolled in this course.</p>
                    <?php endif; ?>
                    
                    <?php if ($user_type == 'instructor'): ?>
                    <div class="instructor-actions">
                        <a href="manage_grades.php?course_id=<?php echo $course_id; ?>" class="btn secondary">Manage Grades</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="course-actions">
                    <a href="<?php echo $user_type == 'student' ? 'courses.php' : 'manage_courses.php'; ?>" class="btn primary">Back to Courses</a>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
