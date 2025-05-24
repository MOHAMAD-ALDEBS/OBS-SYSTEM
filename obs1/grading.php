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
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'teacher') {
    header("Location: index.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle grade submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_grades'])) {
    $enrollment_id = $conn->real_escape_string($_POST['enrollment_id']);
    $midterm = !empty($_POST['midterm']) ? $conn->real_escape_string($_POST['midterm']) : 'NULL';
    $final = !empty($_POST['final']) ? $conn->real_escape_string($_POST['final']) : 'NULL';
    $assignment = !empty($_POST['assignment']) ? $conn->real_escape_string($_POST['assignment']) : 'NULL';
    $comment = !empty($_POST['comment']) ? "'" . $conn->real_escape_string($_POST['comment']) . "'" : 'NULL';
    
    // Calculate total grade if both midterm and final are present
    $total_grade = 'NULL';
    $letter_grade = 'NULL';
    
    if (!empty($_POST['midterm']) && !empty($_POST['final'])) {
        // Default weights: midterm 30%, final 50%, assignment 20% (if present)
        $assignment_score = !empty($_POST['assignment']) ? floatval($_POST['assignment']) : 0;
        $assignment_weight = !empty($_POST['assignment']) ? 0.2 : 0;
        
        if ($assignment_weight > 0) {
            $midterm_weight = 0.3;
            $final_weight = 0.5;
        } else {
            // Adjust weights if no assignment
            $midterm_weight = 0.4;
            $final_weight = 0.6;
        }
        
        $calc_total = (floatval($_POST['midterm']) * $midterm_weight) + 
                      (floatval($_POST['final']) * $final_weight) + 
                      ($assignment_score * $assignment_weight);
        
        $total_grade = round($calc_total, 1);
        
        // Determine letter grade
        if ($total_grade >= 90) {
            $letter_grade = "'AA'";
        } elseif ($total_grade >= 85) {
            $letter_grade = "'BA'";
        } elseif ($total_grade >= 80) {
            $letter_grade = "'BB'";
        } elseif ($total_grade >= 75) {
            $letter_grade = "'CB'";
        } elseif ($total_grade >= 70) {
            $letter_grade = "'CC'";
        } elseif ($total_grade >= 60) {
            $letter_grade = "'DC'";
        } elseif ($total_grade >= 50) {
            $letter_grade = "'DD'";
        } else {
            $letter_grade = "'FF'";
        }
    }
    
    // Update or insert grades
    $check_sql = "SELECT id FROM grades WHERE enrollment_id = $enrollment_id";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        // Update existing record
        $sql = "UPDATE grades SET 
                midterm = $midterm,
                final = $final,
                assignment = $assignment,
                total_grade = $total_grade,
                letter_grade = $letter_grade,
                comment = $comment
                WHERE enrollment_id = $enrollment_id";
    } else {
        // Insert new record
        $sql = "INSERT INTO grades (enrollment_id, midterm, final, assignment, total_grade, letter_grade, comment)
                VALUES ($enrollment_id, $midterm, $final, $assignment, $total_grade, $letter_grade, $comment)";
    }
    
    if ($conn->query($sql)) {
        $message = isset($lang['grades_saved']) ? $lang['grades_saved'] : 'Grades saved successfully';
        $message_type = 'success';
    } else {
        $message = "Error: " . $conn->error;
        $message_type = 'error';
    }
}

// Get courses taught by this teacher
$courses_sql = "SELECT id, course_code, title FROM courses WHERE teacher_id = $teacher_id ORDER BY department, course_code";
$courses_result = $conn->query($courses_sql);

// Get course being viewed
$current_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// If no course is selected and teacher has courses, select the first one
if ($current_course_id == 0 && $courses_result && $courses_result->num_rows > 0) {
    $courses_result->data_seek(0);
    $first_course = $courses_result->fetch_assoc();
    $current_course_id = $first_course['id'];
    // Reset the pointer for later use
    $courses_result->data_seek(0);
}

// Get enrolled students for the current course
$students_sql = "";
if ($current_course_id > 0) {
    $students_sql = "SELECT 
                    s.id AS student_id, 
                    s.student_number,
                    s.name,
                    s.class_year,
                    s.department,
                    e.id AS enrollment_id,
                    g.midterm,
                    g.final,
                    g.assignment,
                    g.total_grade,
                    g.letter_grade,
                    g.comment
                FROM enrollments e
                JOIN students s ON e.student_id = s.id
                LEFT JOIN grades g ON g.enrollment_id = e.id
                WHERE e.course_id = $current_course_id
                ORDER BY s.name ASC";
}

$students_result = $students_sql ? $conn->query($students_sql) : null;

// Set page title
$page_title = isset($lang['grading']) ? $lang['grading'] : 'Grading';
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
                <h2><?php echo $page_title; ?></h2>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($courses_result && $courses_result->num_rows > 0): ?>
                <!-- Course selection tabs -->
                <div class="course-tabs">
                    <?php while ($course = $courses_result->fetch_assoc()): ?>
                        <a href="?course_id=<?php echo $course['id']; ?>" 
                           class="course-tab <?php echo ($current_course_id == $course['id']) ? 'active' : ''; ?>">
                            <?php echo $course['course_code']; ?>: <?php echo $course['title']; ?>
                        </a>
                    <?php endwhile; ?>
                </div>
                
                <?php if ($current_course_id > 0 && $students_result && $students_result->num_rows > 0): ?>
                    <!-- Grading Interface -->
                    <div class="card">
                        <div class="card-header">
                            <h3><?php echo isset($lang['student_grades']) ? $lang['student_grades'] : 'Student Grades'; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table grading-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo isset($lang['student_number']) ? $lang['student_number'] : 'Student Number'; ?></th>
                                            <th><?php echo isset($lang['student_name']) ? $lang['student_name'] : 'Student Name'; ?></th>
                                            <th><?php echo isset($lang['midterm']) ? $lang['midterm'] : 'Midterm'; ?></th>
                                            <th><?php echo isset($lang['final']) ? $lang['final'] : 'Final'; ?></th>
                                            <th><?php echo isset($lang['assignment']) ? $lang['assignment'] : 'Assignment'; ?></th>
                                            <th><?php echo isset($lang['total']) ? $lang['total'] : 'Total'; ?></th>
                                            <th><?php echo isset($lang['letter_grade']) ? $lang['letter_grade'] : 'Grade'; ?></th>
                                            <th><?php echo isset($lang['action']) ? $lang['action'] : 'Action'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($student = $students_result->fetch_assoc()): ?>
                                            <tr data-student-id="<?php echo $student['student_id']; ?>">
                                                <td><?php echo $student['student_number']; ?></td>
                                                <td>
                                                    <div class="student-name"><?php echo $student['name']; ?></div>
                                                    <div class="student-details">
                                                        <?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?> <?php echo $student['class_year']; ?> · 
                                                        <?php echo $student['department']; ?>
                                                    </div>
                                                </td>
                                                <form method="POST" class="grade-form">
                                                    <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                                    <td>
                                                        <input type="number" name="midterm" min="0" max="100" step="0.5" class="grade-input" 
                                                               value="<?php echo $student['midterm']; ?>" placeholder="-">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="final" min="0" max="100" step="0.5" class="grade-input" 
                                                               value="<?php echo $student['final']; ?>" placeholder="-">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="assignment" min="0" max="100" step="0.5" class="grade-input" 
                                                               value="<?php echo $student['assignment']; ?>" placeholder="-">
                                                    </td>
                                                    <td>
                                                        <?php if ($student['total_grade'] !== null): ?>
                                                            <span class="grade <?php echo getGradeClass($student['total_grade']); ?>">
                                                                <?php echo $student['total_grade']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="no-grade">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($student['letter_grade']): ?>
                                                            <span class="grade <?php echo strtolower($student['letter_grade']); ?>">
                                                                <?php echo $student['letter_grade']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="no-grade">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn outline btn-sm comment-btn" 
                                                                data-comment="<?php echo htmlspecialchars($student['comment'] ?? ''); ?>"
                                                                data-student="<?php echo htmlspecialchars($student['name']); ?>">
                                                            <i class="fas fa-comment"></i>
                                                        </button>
                                                        <button type="submit" name="submit_grades" class="btn primary btn-sm">
                                                            <i class="fas fa-save"></i> <?php echo isset($lang['save']) ? $lang['save'] : 'Save'; ?>
                                                        </button>
                                                        <div class="comment-container" style="display:none;">
                                                            <textarea name="comment" placeholder="<?php echo isset($lang['add_comment']) ? $lang['add_comment'] : 'Add a comment'; ?>"><?php echo $student['comment']; ?></textarea>
                                                        </div>
                                                    </td>
                                                </form>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Class Statistics Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3><?php echo isset($lang['class_statistics']) ? $lang['class_statistics'] : 'Class Statistics'; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid">
                                <div class="stat-box">
                                    <div class="stat-heading"><?php echo isset($lang['grade_distribution']) ? $lang['grade_distribution'] : 'Grade Distribution'; ?></div>
                                    <div class="grade-chart-container">
                                        <canvas id="gradeDistribution"></canvas>
                                    </div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-heading"><?php echo isset($lang['average_scores']) ? $lang['average_scores'] : 'Average Scores'; ?></div>
                                    <div class="grade-chart-container">
                                        <canvas id="averageScores"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($current_course_id > 0): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="no-data">
                                <i class="fas fa-user-graduate fa-3x"></i>
                                <p><?php echo isset($lang['no_students_enrolled']) ? $lang['no_students_enrolled'] : 'No students enrolled in this course yet.'; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="no-data">
                            <i class="fas fa-book fa-3x"></i>
                            <p><?php echo isset($lang['no_courses_teaching']) ? $lang['no_courses_teaching'] : 'You are not teaching any courses yet.'; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for Comments -->
<div id="commentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><?php echo isset($lang['student_feedback']) ? $lang['student_feedback'] : 'Student Feedback'; ?></h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <textarea id="modalComment" rows="5" placeholder="<?php echo isset($lang['add_comment']) ? $lang['add_comment'] : 'Add a comment for this student'; ?>"></textarea>
        </div>
        <div class="modal-footer">
            <button id="saveComment" class="btn primary">
                <?php echo isset($lang['save_comment']) ? $lang['save_comment'] : 'Save Comment'; ?>
            </button>
            <button id="cancelComment" class="btn outline">
                <?php echo isset($lang['cancel']) ? $lang['cancel'] : 'Cancel'; ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Grading specific styles */
.course-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.course-tab {
    padding: 10px 15px;
    background-color: #f0f4f8;
    border-radius: var(--border-radius-sm);
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s ease;
}

.course-tab:hover {
    background-color: #e1e8f0;
    color: var(--text-primary);
}

.course-tab.active {
    background-color: var(--primary-color);
    color: white;
}

.grading-table .student-details {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 3px;
}

.grade-input {
    width: 70px;
    padding: 8px;
    text-align: center;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: var(--border-radius-sm);
    background-color: #f9fafb;
}

.grade-input:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(79, 109, 245, 0.15);
    background-color: #fff;
}

.grade-form {
    display: contents; /* This makes the form not affect the table layout */
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.stat-box {
    background-color: #f9fafb;
    border-radius: var(--border-radius-sm);
    padding: 15px;
    box-shadow: var(--shadow-small);
}

.stat-heading {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 15px;
    color: var(--text-secondary);
    text-align: center;
}

.grade-chart-container {
    position: relative;
    height: 250px;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 0;
    border: 1px solid #888;
    width: 500px;
    max-width: 90%;
    box-shadow: var(--shadow-large);
    border-radius: var(--border-radius);
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e5e5;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: var(--text-primary);
}

.close-modal {
    color: #aaa;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
}

.close-modal:hover,
.close-modal:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

.modal-body {
    padding: 20px;
}

.modal-body textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: var(--border-radius-sm);
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
}

.modal-body textarea:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(79, 109, 245, 0.15);
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e5e5e5;
    text-align: right;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .course-tab {
        flex-grow: 1;
        text-align: center;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gather data for statistics
    const grades = {
        'AA': 0, 'BA': 0, 'BB': 0, 'CB': 0, 'CC': 0, 'DC': 0, 'DD': 0, 'FF': 0
    };
    
    const scores = {
        midterm: [],
        final: [],
        assignment: [],
        total: []
    };
    
    // Collect data from the table
    document.querySelectorAll('.grading-table tbody tr').forEach(row => {
        // Get grade data
        const letterGradeEl = row.querySelector('td:nth-child(7) span.grade');
        if (letterGradeEl) {
            const letterGrade = letterGradeEl.textContent.trim();
            if (grades.hasOwnProperty(letterGrade)) {
                grades[letterGrade]++;
            }
        }
        
        // Get score data
        const midtermInput = row.querySelector('input[name="midterm"]');
        const finalInput = row.querySelector('input[name="final"]');
        const assignmentInput = row.querySelector('input[name="assignment"]');
        const totalEl = row.querySelector('td:nth-child(6) span.grade');
        
        if (midtermInput && midtermInput.value) scores.midterm.push(parseFloat(midtermInput.value));
        if (finalInput && finalInput.value) scores.final.push(parseFloat(finalInput.value));
        if (assignmentInput && assignmentInput.value) scores.assignment.push(parseFloat(assignmentInput.value));
        if (totalEl) scores.total.push(parseFloat(totalEl.textContent.trim()));
    });
    
    // Calculate averages
    const averages = {
        midterm: calculateAverage(scores.midterm),
        final: calculateAverage(scores.final),
        assignment: calculateAverage(scores.assignment),
        total: calculateAverage(scores.total)
    };
    
    // Initialize charts if there is data
    if (Object.values(grades).some(val => val > 0)) {
        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeDistribution').getContext('2d');
        new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(grades),
                datasets: [{
                    label: '<?php echo isset($lang["number_of_students"]) ? addslashes($lang["number_of_students"]) : "Number of Students"; ?>',
                    data: Object.values(grades),
                    backgroundColor: [
                        '#10b981', // AA - Green
                        '#3b82f6', // BA - Blue
                        '#60a5fa', // BB - Light Blue
                        '#f59e0b', // CB - Amber
                        '#fbbf24', // CC - Light Amber
                        '#fb923c', // DC - Orange
                        '#f97316', // DD - Dark Orange
                        '#ef4444'  // FF - Red
                    ],
                    borderColor: '#f8fafc',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    // Average Scores Chart
    if (Object.values(averages).some(val => val > 0)) {
        const scoresCtx = document.getElementById('averageScores').getContext('2d');
        new Chart(scoresCtx, {
            type: 'bar',
            data: {
                labels: [
                    '<?php echo isset($lang["midterm"]) ? addslashes($lang["midterm"]) : "Midterm"; ?>',
                    '<?php echo isset($lang["final"]) ? addslashes($lang["final"]) : "Final"; ?>',
                    '<?php echo isset($lang["assignment"]) ? addslashes($lang["assignment"]) : "Assignment"; ?>',
                    '<?php echo isset($lang["total"]) ? addslashes($lang["total"]) : "Total"; ?>'
                ],
                datasets: [{
                    label: '<?php echo isset($lang["average_score"]) ? addslashes($lang["average_score"]) : "Average Score"; ?>',
                    data: [averages.midterm, averages.final, averages.assignment, averages.total],
                    backgroundColor: [
                        'rgba(79, 109, 245, 0.7)',
                        'rgba(79, 109, 245, 0.7)',
                        'rgba(79, 109, 245, 0.7)',
                        'rgba(79, 109, 245, 0.7)'
                    ],
                    borderColor: [
                        'rgba(79, 109, 245, 1)',
                        'rgba(79, 109, 245, 1)',
                        'rgba(79, 109, 245, 1)',
                        'rgba(79, 109, 245, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
    
    // Modal functionality for comments
    const modal = document.getElementById('commentModal');
    const commentBtns = document.querySelectorAll('.comment-btn');
    const closeModal = document.querySelector('.close-modal');
    const saveComment = document.getElementById('saveComment');
    const cancelComment = document.getElementById('cancelComment');
    const modalTitle = document.getElementById('modalTitle');
    const modalComment = document.getElementById('modalComment');
    let currentForm = null;
    let currentCommentField = null;
    
    commentBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const studentName = this.dataset.student;
            const comment = this.dataset.comment;
            const form = this.closest('form');
            const commentField = form.querySelector('textarea[name="comment"]');
            
            currentForm = form;
            currentCommentField = commentField;
            
            modalTitle.textContent = '<?php echo isset($lang["feedback_for"]) ? addslashes($lang["feedback_for"]) : "Feedback for"; ?> ' + studentName;
            modalComment.value = comment;
            modal.style.display = 'block';
        });
    });
    
    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    saveComment.addEventListener('click', function() {
        if (currentCommentField) {
            currentCommentField.value = modalComment.value;
            modal.style.display = 'none';
        }
    });
    
    cancelComment.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // Close the modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});

// Helper function to calculate average
function calculateAverage(arr) {
    if (arr.length === 0) return 0;
    return Math.round((arr.reduce((a, b) => a + b, 0) / arr.length) * 10) / 10;
}
</script>

<?php
// Helper function to determine grade class based on score
function getGradeClass($score) {
    if ($score >= 85) return 'excellent';
    if ($score >= 70) return 'good';
    if ($score >= 50) return 'average';
    return 'poor';
}
?>

<?php include 'includes/footer.php'; ?>
