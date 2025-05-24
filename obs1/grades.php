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

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Get student information
$student_sql = "SELECT name, student_number, class_year, department, gpa FROM students WHERE id = $student_id";
$student_result = $conn->query($student_sql);
$student = $student_result->fetch_assoc();

// Get student grades for all enrolled courses
$grades_sql = "SELECT c.id, c.course_code, c.title, c.credits, c.department, c.class_year,
              t.name AS instructor_name, g.midterm, g.final, g.assignment, 
              g.total_grade, g.letter_grade, e.enrollment_date
              FROM courses c
              JOIN enrollments e ON c.id = e.course_id
              JOIN teachers t ON c.teacher_id = t.id
              LEFT JOIN grades g ON e.id = g.enrollment_id
              WHERE e.student_id = $student_id
              ORDER BY c.class_year ASC, c.department, c.course_code";
$grades_result = $conn->query($grades_sql);

// Group courses by class year
$courses_by_year = [];
$completed_courses = 0;
$ongoing_courses = 0;
$total_credits = 0;
$earned_credits = 0;
$grade_points = 0;

if ($grades_result->num_rows > 0) {
    while ($row = $grades_result->fetch_assoc()) {
        $year = $row['class_year'];
        if (!isset($courses_by_year[$year])) {
            $courses_by_year[$year] = [];
        }
        $courses_by_year[$year][] = $row;
        
        $total_credits += $row['credits'];
        
        // If course has a letter grade, it's completed
        if (!empty($row['letter_grade'])) {
            $completed_courses++;
            $earned_credits += $row['credits'];
            
            // Calculate grade points based on letter grade
            switch ($row['letter_grade']) {
                case 'AA': $grade_points += (4.0 * $row['credits']); break;
                case 'BA': $grade_points += (3.5 * $row['credits']); break;
                case 'BB': $grade_points += (3.0 * $row['credits']); break;
                case 'CB': $grade_points += (2.5 * $row['credits']); break;
                case 'CC': $grade_points += (2.0 * $row['credits']); break;
                case 'DC': $grade_points += (1.5 * $row['credits']); break;
                case 'DD': $grade_points += (1.0 * $row['credits']); break;
                case 'FD': $grade_points += (0.5 * $row['credits']); break;
                case 'FF': $grade_points += (0.0 * $row['credits']); break;
            }
        } else {
            $ongoing_courses++;
        }
    }
}

// Calculate GPA
$gpa = ($earned_credits > 0) ? round(($grade_points / $earned_credits), 2) : 0;

$page_title = isset($lang['grades']) ? $lang['grades'] : "Grades";
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
            <div class="student-panel">
                <div class="student-panel-header">
                    <h2><i class="fas fa-chart-line"></i> <?php echo $page_title; ?></h2>
                </div>
                
                <!-- Student Profile and GPA Summary Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-user-graduate"></i> <?php echo isset($lang['grades_summary']) ? $lang['grades_summary'] : 'Grades Summary'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <div class="student-profile-summary">
                            <div class="profile-section">
                                <div class="student-info-header">
                                    <div class="student-avatar">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="student-details">
                                        <h3><?php echo $student['name']; ?></h3>
                                        <div class="student-meta">
                                            <span class="student-number"><?php echo $student['student_number']; ?></span>
                                            <span class="student-department"><?php echo $student['department']; ?></span>
                                            <span class="student-year"><?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?> <?php echo $student['class_year']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stats-section">
                                <div class="stats-grid">
                                    <div class="stat-box">
                                        <div class="stat-value highlight"><?php echo number_format($gpa, 2); ?></div>
                                        <div class="stat-label"><?php echo isset($lang['gpa']) ? $lang['gpa'] : 'GPA'; ?></div>
                                    </div>
                                    
                                    <div class="stat-box">
                                        <div class="stat-value"><?php echo $completed_courses; ?></div>
                                        <div class="stat-label"><?php echo isset($lang['completed_courses']) ? $lang['completed_courses'] : 'Completed Courses'; ?></div>
                                    </div>
                                    
                                    <div class="stat-box">
                                        <div class="stat-value"><?php echo $ongoing_courses; ?></div>
                                        <div class="stat-label"><?php echo isset($lang['ongoing_courses']) ? $lang['ongoing_courses'] : 'Ongoing Courses'; ?></div>
                                    </div>
                                    
                                    <div class="stat-box">
                                        <div class="stat-value"><?php echo $earned_credits; ?> / <?php echo $total_credits; ?></div>
                                        <div class="stat-label"><?php echo isset($lang['total_credits']) ? $lang['total_credits'] : 'Total Credits'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grades by Year Cards -->
                <?php foreach ($courses_by_year as $year => $courses): ?>
                    <div class="panel-card">
                        <div class="panel-card-header">
                            <h3><i class="fas fa-graduation-cap"></i> <?php echo isset($lang['year_courses']) ? sprintf($lang['year_courses'], $year) : "Year $year Courses"; ?></h3>
                            <span class="course-count"><?php echo count($courses); ?> <?php echo count($courses) > 1 ? (isset($lang['courses']) ? $lang['courses'] : 'courses') : (isset($lang['course']) ? $lang['course'] : 'course'); ?></span>
                        </div>
                        <div class="panel-card-body">
                            <div class="grades-table-container">
                                <table class="grades-table">
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><span class="course-code-badge"><?php echo $course['course_code']; ?></span></td>
                                                <td class="course-title"><?php echo $course['title']; ?> <span class="instructor"><?php echo $course['instructor_name']; ?></span></td>
                                                <td><?php echo $course['credits']; ?></td>
                                                <td><?php echo !empty($course['midterm']) ? $course['midterm'] : '-'; ?></td>
                                                <td><?php echo !empty($course['final']) ? $course['final'] : '-'; ?></td>
                                                <td><?php echo !empty($course['assignment']) ? $course['assignment'] : '-'; ?></td>
                                                <td><?php echo !empty($course['total_grade']) ? number_format($course['total_grade'], 1) : '-'; ?></td>
                                                <td>
                                                    <?php if (!empty($course['letter_grade'])): ?>
                                                        <span class="letter-grade <?php echo strtolower($course['letter_grade']); ?>"><?php echo $course['letter_grade']; ?></span>
                                                    <?php else: ?>
                                                        <span class="ongoing-badge">Ongoing</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($courses_by_year) == 0): ?>
                    <div class="panel-card">
                        <div class="panel-card-header">
                            <h3><i class="fas fa-info-circle"></i> <?php echo isset($lang['no_courses_enrolled']) ? $lang['no_courses_enrolled'] : 'No Courses Enrolled'; ?></h3>
                        </div>
                        <div class="panel-card-body">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-book"></i></div>
                                <p><?php echo isset($lang['no_courses_enrolled']) ? $lang['no_courses_enrolled'] : 'You are not enrolled in any courses yet.'; ?></p>
                                <a href="request_course.php" class="btn primary">
                                    <?php echo isset($lang['browse_courses']) ? $lang['browse_courses'] : 'Browse Available Courses'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Grading Information Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-info-circle"></i> <?php echo isset($lang['grading_info']) ? $lang['grading_info'] : 'Grading Information'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <div class="grading-info">
                            <div class="grading-section">
                                <h4><?php echo isset($lang['letter_grades']) ? $lang['letter_grades'] : 'Letter Grades'; ?></h4>
                                <div class="letter-grades-grid">
                                    <div class="letter-grade-item">
                                        <div class="letter-grade aa">AA</div>
                                        <div class="point">4.0</div>
                                        <div class="range">90-100</div>
                                    </div>
                                    <div class="letter-grade-item">
                                        <div class="letter-grade ba">BA</div>
                                        <div class="point">3.5</div>
                                        <div class="range">85-89</div>
                                    </div>
                                    <div class="letter-grade-item">
                                        <div class="letter-grade bb">BB</div>
                                        <div class="point">3.0</div>
                                        <div class="range">80-84</div>
                                    </div>
                                    <div class="letter-grade-item">
                                        <div class="letter-grade cb">CB</div>
                                        <div class="point">2.5</div>
                                        <div class="range">75-79</div>
                                    </div>
                                    <div class="letter-grade-item">
                                        <div class="letter-grade cc">CC</div>
                                        <div class="point">2.0</div>
                                        <div class="range">70-74</div>
                                    </div>
                                    <div class="letter-grade-item">
                                        <div class="letter-grade dc">DC</div>
                                        <div class="point">1.5</div>
                                        <div class="range">65-69</div>
                                    </div>
                                    <div class="letter-grade-item">
                                        <div class="letter-grade dd">DD</div>
                                        <div class="point">1.0</div>
                                        <div class="range">60-64</div>
                                    </div>
                                    <div class="letter-grade-item">
                                        <div class="letter-grade fd">FD</div>
                                        <div class="point">0.5</div>
                                        <div class="range">50-59</div>
                                    </div>
                                    <div class="letter-grade-item">
                                        <div class="letter-grade ff">FF</div>
                                        <div class="point">0.0</div>
                                        <div class="range">0-49</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grading-section">
                                <h4><?php echo isset($lang['grade_calculation']) ? $lang['grade_calculation'] : 'Grade Calculation'; ?></h4>
                                <div class="grade-formula">
                                    <p><?php echo isset($lang['grade_formula']) ? $lang['grade_formula'] : 'Your total grade is calculated as:'; ?></p>
                                    <div class="formula-breakdown">
                                        <div class="formula-item">
                                            <span class="percent">40%</span>
                                            <span class="component"><?php echo isset($lang['midterm']) ? $lang['midterm'] : 'Midterm'; ?></span>
                                        </div>
                                        <div class="formula-item">
                                            <span class="plus">+</span>
                                        </div>
                                        <div class="formula-item">
                                            <span class="percent">50%</span>
                                            <span class="component"><?php echo isset($lang['final']) ? $lang['final'] : 'Final'; ?></span>
                                        </div>
                                        <div class="formula-item">
                                            <span class="plus">+</span>
                                        </div>
                                        <div class="formula-item">
                                            <span class="percent">10%</span>
                                            <span class="component"><?php echo isset($lang['assignment']) ? $lang['assignment'] : 'Assignment'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Student Panel - Matching Operations Panel Design */
.student-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.student-panel-header h2 {
    margin: 0 0 20px 0;
    font-size: 24px;
    font-weight: 500;
    color: var(--text-primary);
    display: flex;
    align-items: center;
}

.student-panel-header h2 i {
    margin-right: 10px;
    color: var(--primary-color);
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

/* Student Profile Summary */
.student-profile-summary {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.student-info-header {
    display: flex;
    align-items: center;
    gap: 15px;
    background-color: #f8fafc;
    padding: 15px;
    border-radius: 8px;
}

.student-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #e3f2fd;
    color: #4dabf7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.student-details h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
}

.student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.student-meta span {
    font-size: 13px;
    padding: 4px 8px;
    border-radius: 4px;
}

.student-number {
    background-color: #e3f2fd;
    color: #1976d2;
}

.student-department {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.student-year {
    background-color: #fff3e0;
    color: #f57c00;
}

/* Stats Section */
.stats-section {
    padding: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.stat-box {
    background-color: #f8fafc;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    border: 1px solid rgba(0, 0, 0, 0.03);
    transition: transform 0.2s;
}

.stat-box:hover {
    transform: translateY(-3px);
}

.stat-value {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 5px;
    color: var(--text-primary);
}

.stat-value.highlight {
    color: #4dabf7;
}

.stat-label {
    font-size: 13px;
    color: var(--text-secondary);
}

/* Grades Table */
.grades-table-container {
    overflow-x: auto;
}

.grades-table {
    width: 100%;
    border-collapse: collapse;
}

.grades-table th,
.grades-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.grades-table th {
    background-color: #f8fafc;
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 13px;
}

.grades-table tr:hover {
    background-color: #f8fafc;
}

.grades-table tr:last-child td {
    border-bottom: none;
}

.course-code-badge {
    display: inline-block;
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 5px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 12px;
    white-space: nowrap;
}

.course-title {
    max-width: 200px;
}

.instructor {
    display: block;
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 3px;
}

.letter-grade {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 4px;
    color: white;
    font-weight: 600;
    text-align: center;
    min-width: 30px;
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

.ongoing-badge {
    background-color: #f0f2f5;
    color: #707075;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

/* Grading Info Section */
.grading-info {
    padding: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
}

.grading-section {
    flex: 1;
    min-width: 300px;
}

.grading-section h4 {
    font-size: 16px;
    margin: 0 0 15px 0;
    color: var(--text-primary);
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.letter-grades-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
}

.letter-grade-item {
    background-color: #f8fafc;
    border-radius: 6px;
    padding: 10px;
    text-align: center;
    border: 1px solid rgba(0, 0, 0, 0.03);
}

.letter-grade-item .letter-grade {
    margin-bottom: 5px;
}

.letter-grade-item .point {
    font-weight: 600;
    margin-bottom: 3px;
}

.letter-grade-item .range {
    font-size: 12px;
    color: var(--text-secondary);
}

.grade-formula {
    background-color: #f8fafc;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, 0.03);
}

.grade-formula p {
    margin: 0 0 15px 0;
    font-weight: 500;
}

.formula-breakdown {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

.formula-item {
    text-align: center;
    padding: 0 5px;
}

.formula-item .percent {
    display: block;
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 5px;
}

.formula-item .component {
    display: block;
    font-size: 13px;
    color: var(--text-secondary);
}

.formula-item .plus {
    font-size: 20px;
    font-weight: 500;
    color: var(--text-muted);
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
    margin: 0 0 20px;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn.primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.btn.primary:hover {
    background-color: var(--primary-dark);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .student-info-header {
        flex-direction: column;
        text-align: center;
    }
    
    .student-meta {
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .grades-table {
        min-width: 700px;
    }
    
    .grades-table-container {
        overflow-x: auto;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .grading-section {
        min-width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
