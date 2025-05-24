<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user is an instructor
if ($_SESSION['user_type'] != 'instructor') {
    header("Location: dashboard.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];
$message = '';

// Handle form submission for updating grades
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_grades'])) {
    $enrollment_ids = $_POST['enrollment_id'];
    $midterms = $_POST['midterm'];
    $finals = $_POST['final'];
    $assignments = $_POST['assignment'];
    
    $success = true;
    
    foreach ($enrollment_ids as $index => $enrollment_id) {
        // Calculate total grade (40% midterm + 50% final + 10% assignment)
        $midterm = !empty($midterms[$index]) ? floatval($midterms[$index]) : null;
        $final = !empty($finals[$index]) ? floatval($finals[$index]) : null;
        $assignment = !empty($assignments[$index]) ? floatval($assignments[$index]) : null;
        
        $total_grade = null;
        $letter_grade = null;
        
        // Only calculate if all components are present
        if (!is_null($midterm) && !is_null($final) && !is_null($assignment)) {
            $total_grade = ($midterm * 0.4) + ($final * 0.5) + ($assignment * 0.1);
            
            // Determine letter grade
            if ($total_grade >= 90) {
                $letter_grade = 'A';
            } elseif ($total_grade >= 85) {
                $letter_grade = 'A-';
            } elseif ($total_grade >= 80) {
                $letter_grade = 'B+';
            } elseif ($total_grade >= 75) {
                $letter_grade = 'B';
            } elseif ($total_grade >= 70) {
                $letter_grade = 'B-';
            } elseif ($total_grade >= 65) {
                $letter_grade = 'C+';
            } elseif ($total_grade >= 60) {
                $letter_grade = 'C';
            } elseif ($total_grade >= 55) {
                $letter_grade = 'C-';
            } elseif ($total_grade >= 50) {
                $letter_grade = 'D+';
            } elseif ($total_grade >= 45) {
                $letter_grade = 'D';
            } else {
                $letter_grade = 'F';
            }
        }
        
        // Update the grades in the database
        $update_sql = "UPDATE grades SET 
                      midterm = " . (is_null($midterm) ? "NULL" : $midterm) . ",
                      final = " . (is_null($final) ? "NULL" : $final) . ",
                      assignment = " . (is_null($assignment) ? "NULL" : $assignment) . ",
                      total_grade = " . (is_null($total_grade) ? "NULL" : $total_grade) . ",
                      letter_grade = " . (is_null($letter_grade) ? "NULL" : "'$letter_grade'") . "
                      WHERE enrollment_id = $enrollment_id";
                      
        if (!$conn->query($update_sql)) {
            $success = false;
            $message = "Error updating grades: " . $conn->error;
            break;
        }
    }
    
    if ($success) {
        $message = "Grades have been updated successfully!";
    }
}

// Get course selection
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Get instructor's courses
$courses_sql = "SELECT id, course_code, title FROM courses WHERE instructor_id = $instructor_id";
$courses_result = $conn->query($courses_sql);

// Get student enrollments for the selected course
$students_sql = "";
if ($course_id > 0) {
    $students_sql = "SELECT e.id AS enrollment_id, u.name, g.midterm, g.final, g.assignment, g.total_grade, g.letter_grade
                    FROM enrollments e
                    JOIN users u ON e.student_id = u.id
                    LEFT JOIN grades g ON e.id = g.enrollment_id
                    JOIN courses c ON e.course_id = c.id
                    WHERE c.id = $course_id AND c.instructor_id = $instructor_id
                    ORDER BY u.name";
    $students_result = $conn->query($students_sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades - Student Information System</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/grades.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2>Manage Grades</h2>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="course-selector">
                <h3>Select Course</h3>
                <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="course-select-form">
                    <select name="course_id" id="course-select">
                        <option value="0">-- Select a Course --</option>
                        <?php while($course = $courses_result->fetch_assoc()): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo ($course_id == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo $course['course_code'] . ': ' . $course['title']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
            
            <?php if ($course_id > 0): ?>
                <div class="grades-section">
                    <h3>Student Grades</h3>
                    
                    <?php if (isset($students_result) && $students_result->num_rows > 0): ?>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?course_id=$course_id"; ?>" id="update-grades-form">
                            <div class="grades-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Midterm (40%)</th>
                                            <th>Final (50%)</th>
                                            <th>Assignment (10%)</th>
                                            <th>Total</th>
                                            <th>Letter Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($student = $students_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $student['name']; ?></td>
                                                <td>
                                                    <input type="hidden" name="enrollment_id[]" value="<?php echo $student['enrollment_id']; ?>">
                                                    <input type="number" name="midterm[]" min="0" max="100" step="0.1" class="grade-input" value="<?php echo $student['midterm']; ?>">
                                                </td>
                                                <td>
                                                    <input type="number" name="final[]" min="0" max="100" step="0.1" class="grade-input" value="<?php echo $student['final']; ?>">
                                                </td>
                                                <td>
                                                    <input type="number" name="assignment[]" min="0" max="100" step="0.1" class="grade-input" value="<?php echo $student['assignment']; ?>">
                                                </td>
                                                <td><?php echo is_null($student['total_grade']) ? '-' : number_format($student['total_grade'], 1); ?></td>
                                                <td><?php echo is_null($student['letter_grade']) ? '-' : $student['letter_grade']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_grades" class="btn primary">Update Grades</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="no-data">No students are enrolled in this course.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="info-message">
                    <p>Please select a course to manage grades.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
