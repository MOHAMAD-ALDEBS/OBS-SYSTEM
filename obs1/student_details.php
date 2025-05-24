<?php
session_start();
require_once 'db_config.php';

// Language selection
if (isset($_GET['lang']) && ($_GET['lang'] == 'en' || $_GET['lang'] == 'tr')) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

// Include language file
include_once 'lang/' . $_SESSION['lang'] . '.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$error = '';

// Check if student ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: students_list.php");
    exit;
}

$student_id = intval($_GET['id']);

// Get student information
$student_sql = "SELECT s.id, s.student_number, s.name, s.email, s.class_year, s.department, s.admission_date, s.gpa
                FROM students s
                WHERE s.id = $student_id";
$student_result = $conn->query($student_sql);

if (!$student_result || $student_result->num_rows == 0) {
    header("Location: students_list.php");
    exit;
}

$student = $student_result->fetch_assoc();

// Get courses this teacher is teaching
$teacher_courses_sql = "SELECT c.id, c.course_code, c.title, c.class_year, c.department
                       FROM courses c
                       WHERE c.teacher_id = $teacher_id";
$teacher_courses_result = $conn->query($teacher_courses_sql);

// Get courses where this student is enrolled with this teacher
$enrolled_courses_sql = "SELECT c.id, c.course_code, c.title, c.credits, c.department, c.class_year, 
                        e.id as enrollment_id,
                        g.midterm, g.final, g.assignment, g.total_grade, g.letter_grade
                        FROM enrollments e
                        JOIN courses c ON e.course_id = c.id
                        LEFT JOIN grades g ON e.id = g.enrollment_id
                        WHERE e.student_id = $student_id AND c.teacher_id = $teacher_id";
$enrolled_courses_result = $conn->query($enrolled_courses_sql);

$page_title = isset($lang['student_details']) ? $lang['student_details'] : 'Student Details';
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <div class="main-content">
        <!-- Operations Panel as cards on the left -->
        <div class="operations-column">
            <?php include 'includes/operations.php'; ?>
        </div>
        
        <!-- Main content on the right -->
        <div class="content-column">
            <div class="teacher-panel">
                <div class="teacher-panel-header">
                    <div class="back-nav">
                        <a href="students_list.php" class="back-link">
                            <i class="fas fa-arrow-left"></i> <?php echo isset($lang['back_to_list']) ? $lang['back_to_list'] : 'Back to Students List'; ?>
                        </a>
                    </div>
                    <h2><i class="fas fa-user-graduate"></i> <?php echo $page_title; ?></h2>
                </div>
                
                <!-- Student Profile Card -->
                <div class="panel-card student-profile-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-id-card"></i> <?php echo isset($lang['student_info']) ? $lang['student_info'] : 'Student Information'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <div class="student-profile">
                            <div class="student-profile-main">
                                <div class="student-avatar">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="student-details">
                                    <h3 class="student-name"><?php echo $student['name']; ?></h3>
                                    <div class="student-identifiers">
                                        <span class="id-badge"><?php echo $student['student_number']; ?></span>
                                        <span class="year-badge">Year <?php echo $student['class_year']; ?></span>
                                        <span class="dept-badge"><?php echo $student['department']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="student-stats">
                                <div class="stat-item">
                                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-label"><?php echo isset($lang['admission_date']) ? $lang['admission_date'] : 'Admission Date'; ?></div>
                                        <div class="stat-value"><?php echo date('d M Y', strtotime($student['admission_date'])); ?></div>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-label"><?php echo isset($lang['email_label']) ? $lang['email_label'] : 'Email'; ?></div>
                                        <div class="stat-value"><?php echo $student['email']; ?></div>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-label"><?php echo isset($lang['gpa']) ? $lang['gpa'] : 'GPA'; ?></div>
                                        <div class="stat-value"><?php echo number_format($student['gpa'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Student's Courses with Teacher Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-book"></i> <?php echo isset($lang['enrolled_in_your_courses']) ? $lang['enrolled_in_your_courses'] : 'Enrolled in Your Courses'; ?></h3>
                        <?php if ($enrolled_courses_result->num_rows > 0): ?>
                            <span class="course-count"><?php echo $enrolled_courses_result->num_rows; ?> <?php echo $enrolled_courses_result->num_rows > 1 ? (isset($lang['courses']) ? $lang['courses'] : 'courses') : (isset($lang['course']) ? $lang['course'] : 'course'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="panel-card-body">
                        <?php if ($enrolled_courses_result && $enrolled_courses_result->num_rows > 0): ?>
                            <div class="enrolled-courses-table-container">
                                <table class="enrolled-courses-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo isset($lang['course_code']) ? $lang['course_code'] : 'Course Code'; ?></th>
                                            <th><?php echo isset($lang['course_name']) ? $lang['course_name'] : 'Course Name'; ?></th>
                                            <th><?php echo isset($lang['credits']) ? $lang['credits'] : 'Credits'; ?></th>
                                            <th><?php echo isset($lang['midterm']) ? $lang['midterm'] : 'Midterm'; ?></th>
                                            <th><?php echo isset($lang['final']) ? $lang['final'] : 'Final'; ?></th>
                                            <th><?php echo isset($lang['assignment']) ? $lang['assignment'] : 'Assignment'; ?></th>
                                            <th><?php echo isset($lang['total']) ? $lang['total'] : 'Total'; ?></th>
                                            <th><?php echo isset($lang['letter_grade']) ? $lang['letter_grade'] : 'Grade'; ?></th>
                                            <th><?php echo isset($lang['action']) ? $lang['action'] : 'Action'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($course = $enrolled_courses_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="course-code-badge"><?php echo $course['course_code']; ?></span></td>
                                                <td><?php echo $course['title']; ?></td>
                                                <td><?php echo $course['credits']; ?></td>
                                                <td><?php echo !empty($course['midterm']) ? $course['midterm'] : '-'; ?></td>
                                                <td><?php echo !empty($course['final']) ? $course['final'] : '-'; ?></td>
                                                <td><?php echo !empty($course['assignment']) ? $course['assignment'] : '-'; ?></td>
                                                <td><?php echo !empty($course['total_grade']) ? number_format($course['total_grade'], 1) : '-'; ?></td>
                                                <td>
                                                    <?php if (!empty($course['letter_grade'])): ?>
                                                        <span class="letter-grade-badge <?php echo strtolower($course['letter_grade']); ?>"><?php echo $course['letter_grade']; ?></span>
                                                    <?php else: ?>
                                                        <span class="letter-grade-badge pending">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="teacher_grades.php?course_id=<?php echo $course['id']; ?>" class="btn table-action-btn">
                                                        <i class="fas fa-edit"></i> <?php echo isset($lang['manage_grades']) ? $lang['manage_grades'] : 'Manage Grades'; ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-book"></i></div>
                                <p><?php echo isset($lang['not_enrolled_in_your_courses']) ? $lang['not_enrolled_in_your_courses'] : 'This student is not enrolled in any of your courses.'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Other Available Courses Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-chalkboard-teacher"></i> <?php echo isset($lang['your_courses']) ? $lang['your_courses'] : 'Your Courses'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <?php
                        // Reset pointer to beginning of result set
                        if ($teacher_courses_result) {
                            $teacher_courses_result->data_seek(0);
                        }
                        
                        if ($teacher_courses_result && $teacher_courses_result->num_rows > 0):
                            // Create list of enrolled course IDs for comparison
                            $enrolled_course_ids = [];
                            if ($enrolled_courses_result && $enrolled_courses_result->num_rows > 0) {
                                $enrolled_courses_result->data_seek(0);
                                while ($enrolled = $enrolled_courses_result->fetch_assoc()) {
                                    $enrolled_course_ids[] = $enrolled['id'];
                                }
                            }
                        ?>
                            <div class="teacher-courses">
                                <?php 
                                $current_department = '';
                                while($course = $teacher_courses_result->fetch_assoc()): 
                                    // Show department header if changed
                                    if ($current_department !== $course['department']):
                                        $current_department = $course['department'];
                                ?>
                                        <div class="category-header">
                                            <span><?php echo $course['department']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="available-course-item">
                                        <div class="course-info">
                                            <span class="available-course-code"><?php echo $course['course_code']; ?></span>
                                            <span class="available-course-title"><?php echo $course['title']; ?></span>
                                        </div>
                                        <div class="course-meta">
                                            <span class="course-year">Year <?php echo $course['class_year']; ?></span>
                                            <span class="enrollment-status">
                                                <?php if (in_array($course['id'], $enrolled_course_ids)): ?>
                                                    <span class="status enrolled"><i class="fas fa-check-circle"></i> <?php echo isset($lang['enrolled']) ? $lang['enrolled'] : 'Enrolled'; ?></span>
                                                <?php else: ?>
                                                    <span class="status not-enrolled"><i class="fas fa-times-circle"></i> <?php echo isset($lang['not_enrolled']) ? $lang['not_enrolled'] : 'Not Enrolled'; ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                <p><?php echo isset($lang['no_courses_teaching']) ? $lang['no_courses_teaching'] : "You aren't teaching any courses yet."; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Teacher Panel Styles */
.teacher-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.teacher-panel-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 500;
    color: var(--text-primary);
    display: flex;
    align-items: center;
}

.teacher-panel-header h2 i {
    margin-right: 10px;
    color: var(--primary-color);
}

.back-nav {
    margin-bottom: 15px;
}

.back-link {
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
}

.back-link:hover {
    color: var(--primary-color);
}

.back-link i {
    margin-right: 5px;
}

/* Panel Card with Operation Style */
.panel-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.3s ease;
}

.panel-card:hover {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
}

.panel-card-header {
    padding: 15px 20px;
    background: linear-gradient(to right, #3949ab, #5c6bc0);
    color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
}

.panel-card-header h3 i {
    margin-right: 8px;
}

.course-count {
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 500;
}

.panel-card-body {
    padding: 0;
}

/* Student Profile Styling */
.student-profile {
    padding: 20px;
}

.student-profile-main {
    display: flex;
    align-items: center;
    gap: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 15px;
}

.student-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: #e3f2fd;
    color: #4dabf7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    flex-shrink: 0;
}

.student-details h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    font-weight: 600;
}

.student-identifiers {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.id-badge, .year-badge, .dept-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.id-badge {
    background-color: #e3f2fd;
    color: #1976d2;
}

.year-badge {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.dept-badge {
    background-color: #fff3e0;
    color: #f57c00;
}

.student-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 12px;
    background-color: #f8fafc;
    padding: 12px;
    border-radius: 8px;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e3f2fd;
    color: #4dabf7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.stat-value {
    font-weight: 600;
    font-size: 16px;
    color: var(--text-primary);
}

/* Enrolled Courses Table Styles */
.enrolled-courses-table-container {
    overflow-x: auto;
}

.enrolled-courses-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.enrolled-courses-table th,
.enrolled-courses-table td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    text-align: left;
    vertical-align: middle;
}

.enrolled-courses-table th {
    background-color: #f8fafc;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}

.enrolled-courses-table tbody tr:hover {
    background-color: #f8f9fa;
}

.enrolled-courses-table tbody tr:last-child td {
    border-bottom: none;
}

.letter-grade-badge {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 4px;
    color: white;
    font-weight: 600;
    text-align: center;
    min-width: 30px;
    font-size: 12px;
}

.table-action-btn {
    padding: 6px 10px;
    font-size: 12px;
    white-space: nowrap;
    border-radius: 4px;
    text-decoration: none;
    background-color: transparent;
    border: 1px solid var(--primary-color);
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}

.table-action-btn:hover {
    background-color: var(--primary-color);
    color: white;
}

.table-action-btn i {
    font-size: 11px;
}

/* Enrolled Courses Styling */
.enrolled-courses {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.course-item {
    padding: 20px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.course-item:last-child {
    border-bottom: none;
}

.course-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.course-title-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.course-code {
    padding: 5px 10px;
    background-color: #e3f2fd;
    color: #1976d2;
    border-radius: 5px;
    font-weight: 600;
    font-size: 14px;
}

.course-title {
    font-size: 17px;
    margin: 0;
    font-weight: 500;
}

.letter-grade {
    display: inline-block;
    padding: 8px 10px;
    border-radius: 5px;
    color: white;
    font-weight: 700;
    font-size: 16px;
    width: 36px;
    text-align: center;
}

.letter-grade.aa { background-color: #38a169; }
.letter-grade.ba { background-color: #68d391; }
.letter-grade.bb { background-color: #4299e1; }
.letter-grade.cb { background-color: #63b3ed; }
.letter-grade.cc { background-color: #a0aec0; }
.letter-grade.dc { background-color: #feb2b2; }
.letter-grade.dd { background-color: #fc8181; }
.letter-grade.fd { background-color: #f56565; }
.letter-grade.ff { background-color: #e53e3e; }
.letter-grade.pending { background-color: #cbd5e0; }

.course-grades {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.grade-item {
    background-color: #f8fafc;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
}

.grade-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.grade-value {
    font-weight: 600;
    font-size: 18px;
}

.grade-value.total {
    color: var(--primary-color);
}

.course-actions {
    display: flex;
    justify-content: flex-end;
}

/* Your Courses Styling */
.teacher-courses {
    display: flex;
    flex-direction: column;
}

.category-header {
    padding: 10px 20px;
    background-color: #f1f3f5;
    font-weight: 600;
    color: var(--primary-color);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    font-size: 14px;
}

.available-course-item {
    padding: 12px 20px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.available-course-item:last-of-type {
    border-bottom: none;
}

.course-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.available-course-code {
    font-weight: 600;
    color: var(--primary-color);
}

.course-meta {
    display: flex;
    align-items: center;
    gap: 15px;
}

.course-year {
    background-color: #e8f5e9;
    color: #2e7d32;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 500;
}

.status.enrolled {
    color: #38a169;
}

.status.not-enrolled {
    color: #e53e3e;
}

/* Button Styles */
.btn {
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn.outline {
    background-color: transparent;
    border: 1px solid var(--primary-color);
    color: var(--primary-color);
}

.btn.outline:hover {
    background-color: var(--primary-color);
    color: white;
}

/* Empty state */
.empty-state {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 48px;
    color: #e0e0e0;
    margin-bottom: 15px;
}

.empty-state p {
    font-size: 16px;
    margin: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .student-profile-main {
        flex-direction: column;
        text-align: center;
    }
    
    .student-identifiers {
        justify-content: center;
    }
    
    .student-stats {
        grid-template-columns: 1fr;
    }
    
    .course-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .course-grades {
        grid-template-columns: 1fr 1fr;
    }
    
    .available-course-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .course-meta {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
