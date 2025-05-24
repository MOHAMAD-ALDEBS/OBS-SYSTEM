<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied";
    exit;
}

$teacher_id = $_SESSION['user_id'];

// Language selection
if (isset($_SESSION['lang']) && ($_SESSION['lang'] == 'en' || $_SESSION['lang'] == 'tr')) {
    $lang_file = 'lang/' . $_SESSION['lang'] . '.php';
    if (file_exists($lang_file)) {
        include_once $lang_file;
    }
}

// Get search parameters
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$dept_filter = isset($_GET['department']) ? $conn->real_escape_string($_GET['department']) : '';
$year_filter = isset($_GET['year']) ? $conn->real_escape_string($_GET['year']) : '';

// Build SQL query
$sql_parts = ["SELECT s.id, s.student_number, s.name, s.class_year, s.department, s.email
              FROM students s"];
$where_clauses = [];

if (!empty($search_term)) {
    // Focus primarily on student_number search
    $where_clauses[] = "s.student_number LIKE '%$search_term%'";
}

if (!empty($dept_filter)) {
    $where_clauses[] = "s.department = '$dept_filter'";
}

if (!empty($year_filter)) {
    $where_clauses[] = "s.class_year = '$year_filter'";
}

if (!empty($where_clauses)) {
    $sql_parts[] = "WHERE " . implode(' AND ', $where_clauses);
}

$sql_parts[] = "ORDER BY s.student_number";
$sql = implode(' ', $sql_parts);

// Execute query
$students_result = $conn->query($sql);

if ($students_result && $students_result->num_rows > 0) {
    while($student = $students_result->fetch_assoc()) {
        ?>
        <a href="student_details.php?id=<?php echo $student['id']; ?>" class="item-card student-item">
            <div class="item-icon student-icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="item-content">
                <div class="item-header">
                    <div class="item-title"><?php echo $student['name']; ?></div>
                    <div class="student-number"><?php echo $student['student_number']; ?></div>
                </div>
                <div class="item-metadata">
                    <span class="item-meta"><i class="fas fa-envelope"></i> <?php echo $student['email']; ?></span>
                    <span class="item-meta"><i class="fas fa-graduation-cap"></i> <?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?> <?php echo $student['class_year']; ?></span>
                    <span class="item-meta"><i class="fas fa-building"></i> <?php echo $student['department']; ?></span>
                    
                    <!-- Check if student is enrolled in any of this teacher's courses -->
                    <?php
                    $enrolled_sql = "SELECT c.course_code FROM enrollments e 
                                   JOIN courses c ON e.course_id = c.id 
                                   WHERE e.student_id = {$student['id']} AND c.teacher_id = $teacher_id 
                                   LIMIT 3";
                    $enrolled_result = $conn->query($enrolled_sql);
                    
                    if ($enrolled_result && $enrolled_result->num_rows > 0):
                        $courses = [];
                        while ($course = $enrolled_result->fetch_assoc()) {
                            $courses[] = $course['course_code'];
                        }
                        $more_courses = '';
                        if ($enrolled_result->num_rows > 3) {
                            $more_courses = '...';
                        }
                    ?>
                        <span class="item-meta enrolled"><i class="fas fa-check-circle"></i> <?php echo implode(', ', $courses) . $more_courses; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="item-action">
                <i class="fas fa-chevron-right"></i>
            </div>
        </a>
        <?php
    }
} else {
    ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-search"></i></div>
        <p><?php echo isset($lang['no_students_found']) ? $lang['no_students_found'] : 'No students found matching your criteria.'; ?></p>
    </div>
    <?php
}
?>
