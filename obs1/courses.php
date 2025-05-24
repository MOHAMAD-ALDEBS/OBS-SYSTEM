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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Check if user is a student
if ($_SESSION['user_type'] != 'student') {
    header("Location: dashboard.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get student information
$student_sql = "SELECT name, student_number, class_year, department FROM students WHERE id = $student_id";
$student_result = $conn->query($student_sql);
$student = $student_result->fetch_assoc();

// Handle course enrollment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enroll'])) {
    $course_id = $conn->real_escape_string($_POST['course_id']);
    
    // Check if already enrolled
    $check_sql = "SELECT id FROM enrollments WHERE student_id = $student_id AND course_id = $course_id";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        $message = isset($lang['already_enrolled']) ? $lang['already_enrolled'] : "You are already enrolled in this course.";
        $message_type = 'error';
    } else {
        // Enroll in the course
        $sql = "INSERT INTO enrollments (student_id, course_id) VALUES ($student_id, $course_id)";
        if ($conn->query($sql) === TRUE) {
            // Create empty grade entry
            $enrollment_id = $conn->insert_id;
            $grade_sql = "INSERT INTO grades (enrollment_id) VALUES ($enrollment_id)";
            $conn->query($grade_sql);
            
            $message = isset($lang['enrollment_success']) ? $lang['enrollment_success'] : "Successfully enrolled in the course!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
    }
}

// Get student's credit count
$credit_sql = "SELECT SUM(c.credits) as total_credits 
              FROM courses c 
              JOIN enrollments e ON c.id = e.course_id 
              WHERE e.student_id = $student_id";
$credit_result = $conn->query($credit_sql);
$credit_info = $credit_result->fetch_assoc();
$total_credits = $credit_info['total_credits'] ? $credit_info['total_credits'] : 0;

// Get student's enrolled courses
$enrolled_sql = "SELECT c.id, c.course_code, c.title, c.credits, c.department, c.class_year, 
                 t.name AS instructor_name, t.teacher_number, t.position, c.description
                FROM courses c
                JOIN enrollments e ON c.id = e.course_id
                JOIN teachers t ON c.teacher_id = t.id
                WHERE e.student_id = $student_id
                ORDER BY c.department, c.course_code";
$enrolled_result = $conn->query($enrolled_sql);

$page_title = isset($lang['my_courses']) ? $lang['my_courses'] : "My Courses";
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
                    <h2><i class="fas fa-book"></i> <?php echo $page_title; ?></h2>
                </div>
                
                <!-- Course Summary Card (Operations Style) -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><?php echo isset($lang['course_summary']) ? $lang['course_summary'] : 'Course Summary'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon courses-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $enrolled_result->num_rows; ?></div>
                                    <div class="stat-label"><?php echo isset($lang['enrolled_courses']) ? $lang['enrolled_courses'] : 'Enrolled Courses'; ?></div>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon credits-icon">
                                    <i class="fas fa-award"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $total_credits; ?></div>
                                    <div class="stat-label"><?php echo isset($lang['total_credits']) ? $lang['total_credits'] : 'Total Credits'; ?></div>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon year-icon">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $student['class_year']; ?></div>
                                    <div class="stat-label"><?php echo isset($lang['class_year']) ? $lang['class_year'] : 'Class Year'; ?></div>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon dept-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value-text"><?php echo $student['department']; ?></div>
                                    <div class="stat-label"><?php echo isset($lang['department']) ? $lang['department'] : 'Department'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Courses List (Operations Style) -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><?php echo isset($lang['my_courses']) ? $lang['my_courses'] : 'My Courses'; ?></h3>
                        <a href="request_course.php" class="header-action-btn">
                            <i class="fas fa-plus-circle"></i> <?php echo isset($lang['request_course']) ? $lang['request_course'] : 'Request Course'; ?>
                        </a>
                    </div>
                    <div class="panel-card-body">
                        <?php if ($enrolled_result->num_rows > 0): ?>
                            <?php 
                            $current_department = '';
                            while($course = $enrolled_result->fetch_assoc()): 
                                // Show department header if changed
                                if ($current_department !== $course['department']):
                                    $current_department = $course['department'];
                            ?>
                                    <div class="category-header">
                                        <span><?php echo $current_department; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <a href="course_details.php?id=<?php echo $course['id']; ?>" class="item-card">
                                    <div class="item-icon">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="item-content">
                                        <div class="item-header">
                                            <div class="item-title"><?php echo $course['course_code'] . ' - ' . $course['title']; ?></div>
                                        </div>
                                        <div class="item-metadata">
                                            <span class="item-meta"><i class="fas fa-chalkboard-teacher"></i> <?php echo $course['instructor_name']; ?></span>
                                            <span class="item-meta"><i class="fas fa-graduation-cap"></i> <?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?> <?php echo $course['class_year']; ?></span>
                                            <span class="item-meta"><i class="fas fa-award"></i> <?php echo $course['credits']; ?> <?php echo isset($lang['credits']) ? $lang['credits'] : 'Credits'; ?></span>
                                        </div>
                                    </div>
                                    <div class="item-action">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-book"></i></div>
                                <p><?php echo isset($lang['no_courses_enrolled']) ? $lang['no_courses_enrolled'] : 'You are not enrolled in any courses yet.'; ?></p>
                                <a href="request_course.php" class="btn primary">
                                    <?php echo isset($lang['browse_courses']) ? $lang['browse_courses'] : 'Browse Available Courses'; ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Student Courses Panel - Matching Operations Panel Design */
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
}

.header-action-btn {
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    text-decoration: none;
    display: flex;
    align-items: center;
    transition: background-color 0.2s;
}

.header-action-btn:hover {
    background-color: rgba(255, 255, 255, 0.3);
}

.header-action-btn i {
    margin-right: 5px;
}

.panel-card-body {
    padding: 0;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    padding: 20px;
}

.stat-card {
    padding: 15px;
    display: flex;
    align-items: center;
    border-radius: 8px;
    background-color: #f8fafc;
    border: 1px solid rgba(0, 0, 0, 0.03);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 20px;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
}

.courses-icon {
    background-color: rgba(77, 171, 247, 0.1);
    color: #4dabf7;
}

.credits-icon {
    background-color: rgba(94, 53, 177, 0.1);
    color: #5e35b1;
}

.year-icon {
    background-color: rgba(76, 175, 80, 0.1);
    color: #4caf50;
}

.dept-icon {
    background-color: rgba(245, 124, 0, 0.1);
    color: #f57c00;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.stat-value-text {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-label {
    font-size: 14px;
    color: var(--text-secondary);
}

/* Category Headers */
.category-header {
    padding: 10px 20px;
    background-color: #f1f3f5;
    font-weight: 600;
    color: var(--primary-color);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    font-size: 14px;
}

/* Item Cards */
.item-card {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
    position: relative;
}

.item-card:last-child {
    border-bottom: none;
}

.item-card:hover {
    background-color: rgba(0, 0, 0, 0.02);
    padding-left: 18px;
}

.item-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: rgba(77, 171, 247, 0.1);
    color: #4dabf7;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
    transition: transform 0.2s;
}

.item-card:hover .item-icon {
    transform: scale(1.1) rotate(5deg);
}

.item-content {
    flex: 1;
    min-width: 0; /* Prevent flexbox overflow */
}

.item-header {
    margin-bottom: 5px;
}

.item-title {
    font-weight: 500;
    font-size: 15px;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.item-meta {
    font-size: 13px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
}

.item-meta i {
    margin-right: 5px;
    font-size: 12px;
}

.item-action {
    color: #c5cae9;
    transition: transform 0.2s, color 0.2s;
    margin-left: 10px;
}

.item-card:hover .item-action {
    color: var(--primary-color);
    transform: translateX(3px);
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

/* Animation for items */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.item-card {
    animation: fadeInUp 0.3s ease forwards;
    opacity: 0;
}

.item-card:nth-child(2) { animation-delay: 0.05s; }
.item-card:nth-child(3) { animation-delay: 0.1s; }
.item-card:nth-child(4) { animation-delay: 0.15s; }
.item-card:nth-child(5) { animation-delay: 0.2s; }
.item-card:nth-child(6) { animation-delay: 0.25s; }
.item-card:nth-child(7) { animation-delay: 0.3s; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 10px;
        padding: 15px;
    }
    
    .item-metadata {
        flex-direction: column;
        gap: 5px;
    }
    
    .panel-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .header-action-btn {
        align-self: flex-start;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
