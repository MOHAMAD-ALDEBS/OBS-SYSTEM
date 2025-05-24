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

// Get teacher information
$teacher_sql = "SELECT name, teacher_number, department, position FROM teachers WHERE id = $teacher_id";
$teacher_result = $conn->query($teacher_sql);
$teacher = $teacher_result->fetch_assoc();

// Get teacher's courses
$courses_sql = "SELECT c.id, c.course_code, c.title, c.credits, c.description, c.class_year, c.department, 
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as student_count,
               (SELECT COUNT(*) FROM enrollment_requests er WHERE er.course_id = c.id AND er.status = 'pending') as pending_count
               FROM courses c 
               WHERE c.teacher_id = $teacher_id 
               ORDER BY c.department, c.class_year, c.course_code";
$courses_result = $conn->query($courses_sql);

// Get stats for all courses
$course_stats = array();
if ($courses_result->num_rows > 0) {
    while ($course = $courses_result->fetch_assoc()) {
        $course_id = $course['id'];
        $course_stats[$course_id] = [
            'student_count' => $course['student_count'],
            'pending_requests' => $course['pending_count']
        ];
    }
    
    // Reset the result pointer
    $courses_result->data_seek(0);
}

$page_title = isset($lang['my_courses']) ? $lang['my_courses'] : 'My Courses';
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
                    <h2><i class="fas fa-chalkboard-teacher"></i> <?php echo $page_title; ?></h2>
                </div>
                
                <!-- Teacher Info Card (Operations Style) -->
                <div class="panel-card teacher-info">
                    <div class="panel-card-header">
                        <h3><?php echo isset($lang['teacher_info']) ? $lang['teacher_info'] : 'Teacher Information'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <div class="teacher-profile">
                            <div class="profile-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="profile-details">
                                <h3><?php echo $teacher['name']; ?></h3>
                                <div class="profile-metadata">
                                    <span class="badge badge-primary"><?php echo $teacher['teacher_number']; ?></span>
                                    <span class="badge badge-secondary"><?php echo $teacher['position']; ?></span>
                                    <span class="badge badge-info"><?php echo $teacher['department']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards (Operations Style) -->
                <div class="stats-grid">
                    <div class="panel-card stat-card">
                        <div class="stat-icon courses-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $courses_result->num_rows; ?></div>
                            <div class="stat-label"><?php echo isset($lang['teaching_courses']) ? $lang['teaching_courses'] : 'Teaching Courses'; ?></div>
                        </div>
                    </div>
                    
                    <div class="panel-card stat-card">
                        <div class="stat-icon students-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value">
                                <?php 
                                    $total_students = 0;
                                    foreach ($course_stats as $stat) {
                                        $total_students += $stat['student_count'];
                                    }
                                    echo $total_students;
                                ?>
                            </div>
                            <div class="stat-label"><?php echo isset($lang['total_students']) ? $lang['total_students'] : 'Total Students'; ?></div>
                        </div>
                    </div>
                    
                    <div class="panel-card stat-card">
                        <div class="stat-icon requests-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value">
                                <?php 
                                    $total_pending = 0;
                                    foreach ($course_stats as $stat) {
                                        $total_pending += $stat['pending_requests'];
                                    }
                                    echo $total_pending;
                                ?>
                            </div>
                            <div class="stat-label"><?php echo isset($lang['pending_requests']) ? $lang['pending_requests'] : 'Pending Requests'; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Courses List (Operations Style) -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><?php echo isset($lang['teaching_courses']) ? $lang['teaching_courses'] : 'Teaching Courses'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <?php if ($courses_result->num_rows > 0): ?>
                            <?php 
                            $current_department = '';
                            while($course = $courses_result->fetch_assoc()): 
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
                                            <span class="item-meta"><i class="fas fa-graduation-cap"></i> <?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?> <?php echo $course['class_year']; ?></span>
                                            <span class="item-meta"><i class="fas fa-award"></i> <?php echo $course['credits']; ?> <?php echo isset($lang['credits']) ? $lang['credits'] : 'Credits'; ?></span>
                                            <span class="item-meta"><i class="fas fa-users"></i> <?php echo $course['student_count']; ?> <?php echo isset($lang['students']) ? $lang['students'] : 'Students'; ?></span>
                                            <?php if ($course['pending_count'] > 0): ?>
                                                <span class="item-meta pending"><i class="fas fa-hourglass-half"></i> <?php echo $course['pending_count']; ?> <?php echo isset($lang['pending']) ? $lang['pending'] : 'Pending'; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="item-action">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-chalkboard"></i></div>
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
/* Teacher Panel - Matching Operations Panel Design */
.teacher-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.teacher-panel-header h2 {
    margin: 0 0 20px 0;
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
}

.panel-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    letter-spacing: 0.3px;
}

.panel-card-body {
    padding: 0;
}

/* Teacher Profile Card */
.teacher-profile {
    display: flex;
    align-items: center;
    padding: 20px;
}

.profile-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(to bottom right, #3949ab, #5c6bc0);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    flex-shrink: 0;
}

.profile-icon i {
    font-size: 24px;
}

.profile-details h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 500;
}

.profile-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge-primary {
    background-color: rgba(77, 171, 247, 0.1);
    color: #4dabf7;
}

.badge-secondary {
    background-color: rgba(94, 53, 177, 0.1);
    color: #5e35b1;
}

.badge-info {
    background-color: rgba(245, 124, 0, 0.1);
    color: #f57c00;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    padding: 20px;
    display: flex;
    align-items: center;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 20px;
    flex-shrink: 0;
    transition: transform 0.2s;
}

.panel-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
}

.courses-icon {
    background-color: rgba(77, 171, 247, 0.1);
    color: #4dabf7;
}

.students-icon {
    background-color: rgba(94, 53, 177, 0.1);
    color: #5e35b1;
}

.requests-icon {
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

.item-meta.pending {
    color: #f57c00;
    font-weight: 500;
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
    margin-bottom: 10px;
}

.empty-state p {
    font-size: 16px;
    margin: 0;
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
        gap: 15px;
    }
    
    .item-metadata {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
