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

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$username = $_SESSION['username'];
$name = $_SESSION['name'];

// Get page title
$page_title = isset($lang['dashboard_title']) ? $lang['dashboard_title'] : 'Dashboard';
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
            <?php 
            // If user is student
            if ($user_type === 'student') {
                // Get student details
                $sql = "SELECT s.*, 
                        (SELECT COUNT(*) FROM enrollments WHERE student_id = s.id) as enrolled_courses,
                        (SELECT SUM(c.credits) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = s.id) as total_credits
                        FROM students s 
                        WHERE s.id = $user_id";
                $result = $conn->query($sql);
                $student = $result->fetch_assoc();
                
                // Get student's GPA
                $gpa_sql = "SELECT AVG(g.total_grade) as average_grade
                           FROM grades g
                           JOIN enrollments e ON g.enrollment_id = e.id
                           WHERE e.student_id = $user_id AND g.total_grade IS NOT NULL";
                $gpa_result = $conn->query($gpa_sql);
                $gpa_row = $gpa_result->fetch_assoc();
                $gpa = $gpa_row['average_grade'] ? number_format($gpa_row['average_grade'] / 25, 2) : '0.00';
            ?>
                <div class="dashboard-welcome">
                    <h2><?php echo isset($lang['welcome']) ? $lang['welcome'] : 'Welcome'; ?>, <?php echo $student['name']; ?>!</h2>
                </div>

                <!-- New Student Profile Card -->
                <div class="student-profile-card">
                    <div class="profile-header">
                        <div class="student-avatar">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="student-headline">
                            <h3><?php echo $student['name']; ?></h3>
                            <span class="student-id"><?php echo $student['student_number']; ?></span>
                        </div>
                    </div>
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-label"><?php echo isset($lang['class_year']) ? $lang['class_year'] : 'Class Year'; ?></div>
                            <div class="stat-value year-badge"><?php echo $student['class_year']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label"><?php echo isset($lang['gpa']) ? $lang['gpa'] : 'GPA'; ?></div>
                            <div class="stat-value gpa-value"><?php echo $gpa; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label"><?php echo isset($lang['department']) ? $lang['department'] : 'Department'; ?></div>
                            <div class="stat-value"><?php echo $student['department']; ?></div>
                        </div>
                    </div>
                    <div class="profile-footer">
                        <div class="enrolled-courses">
                            <span class="label"><?php echo isset($lang['enrolled_courses']) ? $lang['enrolled_courses'] : 'Enrolled Courses'; ?>:</span>
                            <span class="value"><?php echo $student['enrolled_courses'] ?: 0; ?></span>
                        </div>
                        <div class="total-credits">
                            <span class="label"><?php echo isset($lang['total_credits']) ? $lang['total_credits'] : 'Total Credits'; ?>:</span>
                            <span class="value"><?php echo $student['total_credits'] ?: 0; ?></span>
                        </div>
                        <a href="profile.php" class="profile-link">
                            <i class="fas fa-id-card"></i> <?php echo isset($lang['view_profile']) ? $lang['view_profile'] : 'View Profile'; ?>
                        </a>
                    </div>
                </div>
                
                <!-- Continue with existing cards -->
                <div class="dashboard-row">
                    <!-- ...existing code... -->
                </div>
            <?php
            } 
            
            // If user is teacher - SIMPLIFIED VERSION with only Teacher Information
            else if ($user_type === 'teacher') {
                // Get teacher details
                $sql = "SELECT * FROM teachers WHERE id = $user_id";
                $result = $conn->query($sql);
                $teacher = $result->fetch_assoc();
                
                // Count courses taught by this teacher
                $courses_sql = "SELECT COUNT(*) as course_count FROM courses WHERE teacher_id = $user_id";
                $courses_result = $conn->query($courses_sql);
                $course_count = $courses_result->fetch_assoc()['course_count'];
                
                // Count students enrolled in their courses
                $students_sql = "SELECT COUNT(DISTINCT e.student_id) as student_count 
                               FROM enrollments e
                               JOIN courses c ON e.course_id = c.id
                               WHERE c.teacher_id = $user_id";
                $students_result = $conn->query($students_sql);
                $student_count = $students_result->fetch_assoc()['student_count'];
            ?>
                <div class="dashboard-welcome">
                    <h2><?php echo isset($lang['welcome']) ? $lang['welcome'] : 'Welcome'; ?>, <?php echo $teacher['name']; ?>!</h2>
                </div>
                
                <!-- Only Teacher Information card remains -->
                <div class="dashboard-row">
                    <div class="dashboard-card teacher-info-card">
                        <div class="card-header">
                            <h3><?php echo isset($lang['teacher_info']) ? $lang['teacher_info'] : 'Teacher Information'; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="teacher-profile-container">
                                <div class="teacher-avatar">
                                    <i class="fas fa-user-tie"></i>
                                    <div class="teacher-status active"></div>
                                </div>
                                <div class="teacher-details">
                                    <div class="teacher-name"><?php echo $teacher['name']; ?></div>
                                    <div class="teacher-id"><?php echo $teacher['teacher_number']; ?></div>
                                    <div class="teacher-position">
                                        <?php echo "{$teacher['position']}, {$teacher['department']}"; ?>
                                    </div>
                                    <div class="teaching-stats">
                                        <div class="stat-item">
                                            <i class="fas fa-book"></i>
                                            <span><?php echo $course_count; ?> <?php echo isset($lang['courses']) ? $lang['courses'] : 'Courses'; ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <i class="fas fa-user-graduate"></i>
                                            <span><?php echo $student_count; ?> <?php echo isset($lang['students']) ? $lang['students'] : 'Students'; ?></span>
                                        </div>
                                    </div>
                                    <a href="profile.php" class="btn-link">
                                        <i class="fas fa-id-card"></i> <?php echo isset($lang['view_profile']) ? $lang['view_profile'] : 'View Profile'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            } 
            
            // If user is admin
            else if ($user_type === 'admin') {
                // ...existing admin dashboard code...
            }
            ?>
        </div>
    </div>
</div>

<style>
/* Teacher Information Card Styles */
.teacher-info-card {
    grid-column: 1 / -1;  /* Make the card span all columns */
    width: 100%;
    box-shadow: var(--shadow-medium);
    border-radius: var(--border-radius);
    background-color: white;
    overflow: hidden;
}

.teacher-profile-container {
    display: flex;
    align-items: center;
    padding: 25px;
}

.teacher-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background-color: #f0f4f8;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    margin-right: 30px;
    flex-shrink: 0;
}

.teacher-avatar i {
    font-size: 60px;
    color: var(--primary-color);
}

.teacher-status {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 3px solid white;
}

.teacher-status.active {
    background-color: #10b981;
}

.teacher-details {
    flex: 1;
}

.teacher-name {
    font-size: 28px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 10px;
}

.teacher-id {
    font-size: 16px;
    color: var(--text-secondary);
    margin-bottom: 10px;
    font-weight: 500;
    background-color: #f0f4f8;
    padding: 4px 12px;
    border-radius: 4px;
    display: inline-block;
}

.teacher-position {
    font-size: 18px;
    color: var(--text-secondary);
    margin-bottom: 25px;
}

.teaching-stats {
    display: flex;
    gap: 30px;
    margin-bottom: 25px;
    background-color: #f9fafb;
    padding: 15px 20px;
    border-radius: var(--border-radius);
}

.stat-item {
    display: flex;
    align-items: center;
    color: var(--text-secondary);
    font-size: 16px;
}

.stat-item i {
    margin-right: 10px;
    color: var(--primary-color);
    font-size: 18px;
}

.btn-link {
    display: inline-flex;
    align-items: center;
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: background-color 0.2s;
    font-size: 16px;
    padding: 10px 20px;
    border-radius: var(--border-radius-sm);
    background-color: var(--primary-color);
}

.btn-link i {
    margin-right: 10px;
}

.btn-link:hover {
    background-color: var(--primary-dark);
}

/* Student Profile Card Styles */
.student-profile-card {
    grid-column: 1 / -1;  /* Make the card span all columns */
    width: 100%;
    box-shadow: var(--shadow-medium);
    border-radius: var(--border-radius);
    background-color: white;
    overflow: hidden;
    margin-top: 20px;
}

.profile-header {
    display: flex;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eaeaea;
}

.student-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: #f0f4f8;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    margin-right: 20px;
    flex-shrink: 0;
}

.student-avatar i {
    font-size: 40px;
    color: var(--primary-color);
}

.student-headline {
    flex: 1;
}

.student-headline h3 {
    font-size: 22px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.student-id {
    font-size: 14px;
    color: var(--text-secondary);
    font-weight: 500;
    background-color: #f0f4f8;
    padding: 4px 10px;
    border-radius: 4px;
    display: inline-block;
    margin-top: 5px;
}

.profile-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    padding: 20px;
    border-bottom: 1px solid #eaeaea;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.stat-label {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.stat-value {
    font-size: 18px;
    font-weight: 500;
    color: var(--text-primary);
}

.year-badge {
    background-color: #e1f5fe;
    color: #01579b;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 14px;
    display: inline-block;
    margin-top: 5px;
}

.gpa-value {
    color: #d32f2f;
    font-weight: 600;
}

.profile-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
}

.enrolled-courses, .total-credits {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.label {
    font-size: 14px;
    color: var(--text-secondary);
}

.value {
    font-size: 16px;
    font-weight: 500;
    color: var(--text-primary);
}

.profile-link {
    display: inline-flex;
    align-items: center;
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: background-color 0.2s;
    font-size: 16px;
    padding: 10px 20px;
    border-radius: var(--border-radius-sm);
    background-color: var(--primary-color);
}

.profile-link i {
    margin-right: 8px;
}

.profile-link:hover {
    background-color: var(--primary-dark);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .teacher-profile-container {
        flex-direction: column;
        text-align: center;
    }
    
    .teacher-avatar {
        margin-right: 0;
        margin-bottom: 20px;
    }
    
    .teaching-stats {
        justify-content: center;
        flex-wrap: wrap;
    }

    .student-profile-card {
        padding: 15px;
    }

    .profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .student-avatar {
        margin-bottom: 10px;
    }

    .profile-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
