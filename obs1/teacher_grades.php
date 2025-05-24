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
$message = '';
$message_type = '';

// Process grade submissions if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_grades'])) {
    $enrollment_ids = isset($_POST['enrollment_id']) ? $_POST['enrollment_id'] : [];
    $midterms = isset($_POST['midterm']) ? $_POST['midterm'] : [];
    $finals = isset($_POST['final']) ? $_POST['final'] : [];
    $assignments = isset($_POST['assignment']) ? $_POST['assignment'] : [];
    
    $success_count = 0;
    $error_count = 0;
    
    // Verify teacher has authority to grade these students first
    $course_id = intval($_GET['course_id']);
    $auth_check_sql = "SELECT id FROM courses WHERE id = $course_id AND teacher_id = $teacher_id";
    $auth_result = $conn->query($auth_check_sql);
    
    if ($auth_result && $auth_result->num_rows > 0) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            foreach ($enrollment_ids as $index => $enrollment_id) {
                $enrollment_id = intval($enrollment_id);
                $midterm = !empty($midterms[$index]) ? floatval($midterms[$index]) : null;
                $final = !empty($finals[$index]) ? floatval($finals[$index]) : null;
                $assignment = !empty($assignments[$index]) ? floatval($assignments[$index]) : null;
                
                // Calculate total grade if both midterm and final are present
                $total_grade = null;
                $letter_grade = null;
                
                if (!is_null($midterm) && !is_null($final)) {
                    // Calculation: 40% midterm + 50% final + 10% assignment (if exists)
                    $midterm_weight = 0.4;
                    $final_weight = 0.5;
                    $assignment_weight = 0.1;
                    
                    if (!is_null($assignment)) {
                        $total_grade = ($midterm * $midterm_weight) + ($final * $final_weight) + ($assignment * $assignment_weight);
                    } else {
                        // Adjust weights if no assignment (45% midterm, 55% final)
                        $total_grade = ($midterm * 0.45) + ($final * 0.55);
                    }
                    
                    // Determine letter grade
                    if ($total_grade >= 90) $letter_grade = 'AA';
                    else if ($total_grade >= 85) $letter_grade = 'BA';
                    else if ($total_grade >= 80) $letter_grade = 'BB';
                    else if ($total_grade >= 75) $letter_grade = 'CB';
                    else if ($total_grade >= 70) $letter_grade = 'CC';
                    else if ($total_grade >= 65) $letter_grade = 'DC';
                    else if ($total_grade >= 60) $letter_grade = 'DD';
                    else if ($total_grade >= 50) $letter_grade = 'FD';
                    else $letter_grade = 'FF';
                }
                
                // Update grades
                $sql = "UPDATE grades SET 
                       midterm = " . (is_null($midterm) ? "NULL" : $midterm) . ", 
                       final = " . (is_null($final) ? "NULL" : $final) . ", 
                       assignment = " . (is_null($assignment) ? "NULL" : $assignment) . ", 
                       total_grade = " . (is_null($total_grade) ? "NULL" : $total_grade) . ", 
                       letter_grade = " . (is_null($letter_grade) ? "NULL" : "'$letter_grade'") . " 
                       WHERE enrollment_id = $enrollment_id";
                
                if ($conn->query($sql)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            // Commit transaction if all went well
            $conn->commit();
            
            if ($error_count == 0) {
                $message = isset($lang['grades_saved']) ? $lang['grades_saved'] : 'Grades saved successfully';
                $message_type = 'success';
            } else {
                $message = "Some grades could not be saved. Saved: $success_count, Failed: $error_count";
                $message_type = 'warning';
            }
        } catch (Exception $e) {
            // Roll back transaction on error
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = isset($lang['not_authorized']) ? $lang['not_authorized'] : 'You are not authorized to perform this action';
        $message_type = 'error';
    }
}

// Get courses taught by this teacher with enrolled students
$course_sql = "SELECT c.id, c.course_code, c.title, c.class_year, c.department,
              (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as student_count
              FROM courses c 
              WHERE c.teacher_id = $teacher_id
              ORDER BY c.class_year, c.department, c.course_code";
$course_result = $conn->query($course_sql);

// Get selected course from query string
$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;

// Get enrolled students for selected course
$students_result = null;
if ($selected_course) {
    // Verify teacher has access to this course
    $access_sql = "SELECT id FROM courses WHERE id = $selected_course AND teacher_id = $teacher_id";
    $access_result = $conn->query($access_sql);
    
    if ($access_result && $access_result->num_rows > 0) {
        $students_sql = "SELECT s.id, s.name, s.student_number, s.class_year, s.department, 
                       e.id as enrollment_id,
                       g.midterm, g.final, g.assignment, g.total_grade, g.letter_grade
                       FROM enrollments e 
                       JOIN students s ON e.student_id = s.id 
                       LEFT JOIN grades g ON e.id = g.enrollment_id 
                       WHERE e.course_id = $selected_course 
                       ORDER BY s.name";
        $students_result = $conn->query($students_sql);
        
        // Get course info
        $course_info_sql = "SELECT course_code, title, department, class_year FROM courses WHERE id = $selected_course";
        $course_info_result = $conn->query($course_info_sql);
        $course_info = $course_info_result->fetch_assoc();
    } else {
        $message = isset($lang['not_authorized']) ? $lang['not_authorized'] : 'You are not authorized to access this course';
        $message_type = 'error';
    }
}

$page_title = isset($lang['student_grades']) ? $lang['student_grades'] : 'Student Grades';
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
            <a href="dashboard.php" class="back-button">
                <i class="fas fa-arrow-left"></i> <?php echo isset($lang['back_to_dashboard']) ? $lang['back_to_dashboard'] : 'Back to Dashboard'; ?>
            </a>
            
            <div class="page-header">
                <h2><i class="fas fa-chart-line"></i> <?php echo $page_title; ?></h2>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <!-- Course Selection Simplification -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> <?php echo isset($lang['select_course']) ? $lang['select_course'] : 'Select Course'; ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($course_result && $course_result->num_rows > 0): ?>
                        <div class="course-tabs">
                            <?php while($course = $course_result->fetch_assoc()): ?>
                                <a href="teacher_grades.php?course_id=<?php echo $course['id']; ?>" class="course-tab <?php echo ($selected_course == $course['id']) ? 'active' : ''; ?>">
                                    <div class="course-tab-code"><?php echo $course['course_code']; ?></div>
                                    <div class="course-tab-details">
                                        <div class="course-tab-name"><?php echo $course['title']; ?></div>
                                        <div class="course-tab-meta">
                                            <span class="course-students-count"><?php echo $course['student_count']; ?> <?php echo isset($lang['students']) ? $lang['students'] : 'Students'; ?></span>
                                            <span class="course-year-badge">Year <?php echo $course['class_year']; ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-book-open fa-3x"></i>
                            <p><?php echo isset($lang['no_courses_teaching']) ? $lang['no_courses_teaching'] : 'You are not teaching any courses yet.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($selected_course && isset($course_info)): ?>
                <!-- Course Info and Grading Form -->
                <form method="POST" action="teacher_grades.php?course_id=<?php echo $selected_course; ?>" id="grade-form">
                    <div class="card">
                        <div class="card-header course-header">
                            <div class="course-header-info">
                                <span class="course-code-badge"><?php echo $course_info['course_code']; ?></span>
                                <h3 class="course-title"><?php echo $course_info['title']; ?></h3>
                            </div>
                            <div class="course-header-meta">
                                <span class="department-badge"><?php echo $course_info['department']; ?></span>
                                <span class="year-badge">Year <?php echo $course_info['class_year']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($students_result && $students_result->num_rows > 0): ?>
                                <div class="grading-controls">
                                    <div class="grading-formula">
                                        <strong><?php echo isset($lang['grade_formula']) ? $lang['grade_formula'] : 'Grade formula:'; ?></strong> 
                                        40% <?php echo isset($lang['midterm']) ? $lang['midterm'] : 'Midterm'; ?> + 
                                        50% <?php echo isset($lang['final']) ? $lang['final'] : 'Final'; ?> + 
                                        10% <?php echo isset($lang['assignment']) ? $lang['assignment'] : 'Assignment'; ?>
                                    </div>
                                    <button type="submit" name="save_grades" class="btn primary save-all-btn">
                                        <i class="fas fa-save"></i> <?php echo isset($lang['save_all_grades']) ? $lang['save_all_grades'] : 'Save All Grades'; ?>
                                    </button>
                                </div>
                                
                                <div class="grades-table-container">
                                    <table class="grades-table">
                                        <thead>
                                            <tr>
                                                <th class="student-col"><?php echo isset($lang['student']) ? $lang['student'] : 'Student'; ?></th>
                                                <th class="grade-col"><?php echo isset($lang['midterm']) ? $lang['midterm'] : 'Midterm'; ?></th>
                                                <th class="grade-col"><?php echo isset($lang['final']) ? $lang['final'] : 'Final'; ?></th>
                                                <th class="grade-col"><?php echo isset($lang['assignment']) ? $lang['assignment'] : 'Assignment'; ?></th>
                                                <th class="grade-col"><?php echo isset($lang['total']) ? $lang['total'] : 'Total'; ?></th>
                                                <th class="letter-col"><?php echo isset($lang['letter_grade']) ? $lang['letter_grade'] : 'Grade'; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $index = 0; while($student = $students_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="student-info-cell">
                                                        <input type="hidden" name="enrollment_id[<?php echo $index; ?>]" value="<?php echo $student['enrollment_id']; ?>">
                                                        <div class="student-info">
                                                            <div class="student-name"><?php echo $student['name']; ?></div>
                                                            <div class="student-number"><?php echo $student['student_number']; ?></div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="number" name="midterm[<?php echo $index; ?>]" value="<?php echo $student['midterm']; ?>" min="0" max="100" step="0.5" class="grade-input">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="final[<?php echo $index; ?>]" value="<?php echo $student['final']; ?>" min="0" max="100" step="0.5" class="grade-input">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="assignment[<?php echo $index; ?>]" value="<?php echo $student['assignment']; ?>" min="0" max="100" step="0.5" class="grade-input">
                                                    </td>
                                                    <td>
                                                        <div class="calculated-grade"><?php echo $student['total_grade'] ? number_format($student['total_grade'], 1) : '-'; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="letter-grade-badge <?php echo strtolower($student['letter_grade'] ?? ''); ?>">
                                                            <?php echo $student['letter_grade'] ?: '-'; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php $index++; endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="save_grades" class="btn primary">
                                        <i class="fas fa-save"></i> <?php echo isset($lang['save_all_grades']) ? $lang['save_all_grades'] : 'Save All Grades'; ?>
                                    </button>
                                    <button type="reset" class="btn outline">
                                        <i class="fas fa-undo"></i> <?php echo isset($lang['reset']) ? $lang['reset'] : 'Reset Changes'; ?>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-user-graduate fa-3x"></i>
                                    <p><?php echo isset($lang['no_students_enrolled']) ? $lang['no_students_enrolled'] : 'No students enrolled in this course yet.'; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                
                <!-- Simple Class Statistics -->
                <?php if ($students_result && $students_result->num_rows > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> <?php echo isset($lang['class_statistics']) ? $lang['class_statistics'] : 'Class Statistics'; ?></h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            // Calculate statistics
                            $stats_sql = "SELECT 
                                        COUNT(*) as student_count,
                                        ROUND(AVG(g.midterm), 1) as avg_midterm,
                                        ROUND(AVG(g.final), 1) as avg_final,
                                        ROUND(AVG(g.total_grade), 1) as avg_total,
                                        ROUND(MIN(g.total_grade), 1) as min_grade,
                                        ROUND(MAX(g.total_grade), 1) as max_grade,
                                        SUM(CASE WHEN g.letter_grade = 'AA' THEN 1 ELSE 0 END) as aa_count,
                                        SUM(CASE WHEN g.letter_grade = 'BA' THEN 1 ELSE 0 END) as ba_count,
                                        SUM(CASE WHEN g.letter_grade = 'BB' THEN 1 ELSE 0 END) as bb_count,
                                        SUM(CASE WHEN g.letter_grade = 'CB' THEN 1 ELSE 0 END) as cb_count,
                                        SUM(CASE WHEN g.letter_grade = 'CC' THEN 1 ELSE 0 END) as cc_count,
                                        SUM(CASE WHEN g.letter_grade = 'DC' THEN 1 ELSE 0 END) as dc_count,
                                        SUM(CASE WHEN g.letter_grade = 'DD' THEN 1 ELSE 0 END) as dd_count,
                                        SUM(CASE WHEN g.letter_grade = 'FD' THEN 1 ELSE 0 END) as fd_count,
                                        SUM(CASE WHEN g.letter_grade = 'FF' THEN 1 ELSE 0 END) as ff_count
                                        FROM grades g 
                                        JOIN enrollments e ON g.enrollment_id = e.id 
                                        WHERE e.course_id = $selected_course";
                            $stats_result = $conn->query($stats_sql);
                            $stats = $stats_result->fetch_assoc();
                            ?>
                            
                            <div class="stats-container">
                                <div class="stats-summary">
                                    <div class="stat-box">
                                        <div class="stat-label"><?php echo isset($lang['students']) ? $lang['students'] : 'Students'; ?></div>
                                        <div class="stat-value"><?php echo $stats['student_count']; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-label"><?php echo isset($lang['avg_midterm']) ? $lang['avg_midterm'] : 'Avg. Midterm'; ?></div>
                                        <div class="stat-value"><?php echo $stats['avg_midterm'] ?: '-'; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-label"><?php echo isset($lang['avg_final']) ? $lang['avg_final'] : 'Avg. Final'; ?></div>
                                        <div class="stat-value"><?php echo $stats['avg_final'] ?: '-'; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-label"><?php echo isset($lang['avg_total']) ? $lang['avg_total'] : 'Avg. Total'; ?></div>
                                        <div class="stat-value"><?php echo $stats['avg_total'] ?: '-'; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-label"><?php echo isset($lang['min_grade']) ? $lang['min_grade'] : 'Min Grade'; ?></div>
                                        <div class="stat-value"><?php echo $stats['min_grade'] ?: '-'; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-label"><?php echo isset($lang['max_grade']) ? $lang['max_grade'] : 'Max Grade'; ?></div>
                                        <div class="stat-value"><?php echo $stats['max_grade'] ?: '-'; ?></div>
                                    </div>
                                </div>
                                
                                <div class="grade-distribution-container">
                                    <h4><?php echo isset($lang['grade_distribution']) ? $lang['grade_distribution'] : 'Grade Distribution'; ?></h4>
                                    <div class="grade-distribution">
                                        <?php
                                        $letter_grades = ['AA', 'BA', 'BB', 'CB', 'CC', 'DC', 'DD', 'FD', 'FF'];
                                        foreach ($letter_grades as $grade) {
                                            $grade_count = $stats[strtolower($grade) . '_count'] ?? 0;
                                            $percentage = $stats['student_count'] > 0 ? ($grade_count / $stats['student_count']) * 100 : 0;
                                        ?>
                                            <div class="grade-bar-container">
                                                <div class="grade-bar-label"><?php echo $grade; ?></div>
                                                <div class="grade-bar-wrapper">
                                                    <div class="grade-bar grade-<?php echo strtolower($grade); ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <div class="grade-bar-count"><?php echo $grade_count; ?></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif ($course_result && $course_result->num_rows > 0): ?>
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    <?php echo isset($lang['select_course_grades']) ? $lang['select_course_grades'] : 'Please select a course to view and manage grades'; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Simplified Teacher Grades Styles */
.course-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}

.course-tab {
    flex: 1;
    min-width: 200px;
    padding: 12px;
    background-color: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
}

.course-tab:hover {
    box-shadow: var(--shadow-small);
    transform: translateY(-2px);
}

.course-tab.active {
    border-color: var(--primary-color);
    background-color: rgba(var(--primary-color-rgb), 0.05);
    box-shadow: 0 0 0 1px var(--primary-color);
}

.course-tab-code {
    background-color: var(--primary-color);
    color: white;
    padding: 5px 8px;
    border-radius: 5px;
    font-weight: 600;
    font-size: 14px;
    margin-right: 12px;
}

.course-tab-details {
    flex: 1;
}

.course-tab-name {
    font-weight: 500;
    font-size: 15px;
    margin-bottom: 4px;
}

.course-tab-meta {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.course-students-count {
    font-weight: 500;
}

.course-year-badge {
    background-color: #e8f5e9;
    color: #2e7d32;
    padding: 2px 6px;
    border-radius: 4px;
}

.course-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.course-header-info {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.course-code-badge {
    background-color: var(--primary-color);
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-weight: 600;
    font-size: 15px;
}

.course-title {
    margin: 0;
    font-size: 18px;
}

.course-header-meta {
    display: flex;
    gap: 10px;
}

.department-badge,
.year-badge {
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 13px;
}

.department-badge {
    background-color: #e3f2fd;
    color: #1976d2;
}

.year-badge {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.grading-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.grading-formula {
    font-size: 14px;
    color: var(--text-secondary);
}

.save-all-btn {
    white-space: nowrap;
}

.grades-table-container {
    overflow-x: auto;
}

.grades-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.grades-table th,
.grades-table td {
    padding: 10px;
    border-bottom: 1px solid #edf2f7;
    vertical-align: middle;
}

.grades-table th {
    background-color: #f8fafd;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
}

.grades-table tr:hover {
    background-color: #f9fafb;
}

.grades-table .student-col {
    width: 25%;
}

.grades-table .grade-col {
    width: 12%;
}

.grades-table .letter-col {
    width: 8%;
}

.student-info-cell {
    padding: 12px 10px;
}

.student-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.student-name {
    font-weight: 500;
}

.student-number {
    font-size: 12px;
    color: var(--text-secondary);
}

.grade-input {
    width: 70px;
    padding: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    text-align: center;
    transition: all 0.2s ease;
}

.grade-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.1);
    outline: none;
}

.calculated-grade {
    font-weight: 600;
    text-align: center;
}

.letter-grade-badge {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 4px;
    font-weight: 700;
    text-align: center;
    width: 30px;
    color: white;
    background-color: #718096;
}

.letter-grade-badge.aa { background-color: #38a169; }
.letter-grade-badge.ba { background-color: #68d391; }
.letter-grade-badge.bb { background-color: #4299e1; }
.letter-grade-badge.cb { background-color: #63b3ed; }
.letter-grade-badge.cc { background-color: #a0aec0; }
.letter-grade-badge.dc { background-color: #feb2b2; }
.letter-grade-badge.dd { background-color: #fc8181; }
.letter-grade-badge.fd { background-color: #f56565; }
.letter-grade-badge.ff { background-color: #e53e3e; }

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

/* Statistics styles */
.stats-container {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 15px;
}

.stat-box {
    background-color: #f8fafc;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}

.stat-label {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 8px;
}

.stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
}

.grade-distribution-container {
    background-color: #f8fafc;
    border-radius: 8px;
    padding: 20px;
}

.grade-distribution-container h4 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
    font-weight: 600;
    text-align: center;
}

.grade-distribution {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.grade-bar-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.grade-bar-label {
    width: 30px;
    font-weight: 600;
    text-align: center;
}

.grade-bar-wrapper {
    flex: 1;
    height: 24px;
    background-color: #edf2f7;
    border-radius: 4px;
    overflow: hidden;
}

.grade-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}

.grade-bar.grade-aa { background-color: #38a169; }
.grade-bar.grade-ba { background-color: #68d391; }
.grade-bar.grade-bb { background-color: #4299e1; }
.grade-bar.grade-cb { background-color: #63b3ed; }
.grade-bar.grade-cc { background-color: #a0aec0; }
.grade-bar.grade-dc { background-color: #feb2b2; }
.grade-bar.grade-dd { background-color: #fc8181; }
.grade-bar.grade-fd { background-color: #f56565; }
.grade-bar.grade-ff { background-color: #e53e3e; }

.grade-bar-count {
    width: 30px;
    text-align: right;
    font-weight: 600;
}

.info-message {
    background-color: #ebf8ff;
    padding: 15px;
    border-radius: 8px;
    color: #3182ce;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    margin-bottom: 20px;
}

.info-message i {
    font-size: 20px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .course-tabs {
        flex-direction: column;
    }
    
    .course-tab {
        width: 100%;
    }
    
    .course-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .grading-controls {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .stats-summary {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
}
</style>

<script>
// Simple JS for enhancing the grade input UX
document.addEventListener('DOMContentLoaded', function() {
    // Highlight grade inputs when focused
    const gradeInputs = document.querySelectorAll('.grade-input');
    
    gradeInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.select(); // Select all text when focused for easier editing
        });
    });

    // Add warning before resetting form
    const resetButton = document.querySelector('button[type="reset"]');
    if (resetButton) {
        resetButton.addEventListener('click', function(e) {
            if (!confirm('<?php echo isset($lang['confirm_reset']) ? $lang['confirm_reset'] : 'Are you sure you want to reset all changes?'; ?>')) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
