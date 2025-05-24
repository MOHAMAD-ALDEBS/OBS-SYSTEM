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
$message = '';
$message_type = '';

// Get student information
$student_sql = "SELECT name, student_number, class_year, department FROM students WHERE id = $student_id";
$student_result = $conn->query($student_sql);
$student = $student_result->fetch_assoc();

// Process enrollment request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_enrollment'])) {
    $course_id = $conn->real_escape_string($_POST['course_id']);
    
    // Check if already enrolled or requested
    $check_enrollment_sql = "SELECT id FROM enrollments WHERE student_id = $student_id AND course_id = $course_id";
    $check_enrollment = $conn->query($check_enrollment_sql);
    
    $check_request_sql = "SELECT id FROM enrollment_requests WHERE student_id = $student_id AND course_id = $course_id AND status != 'rejected'";
    $check_request = $conn->query($check_request_sql);
    
    if ($check_enrollment && $check_enrollment->num_rows > 0) {
        $message = isset($lang['already_enrolled']) ? $lang['already_enrolled'] : "You are already enrolled in this course.";
        $message_type = 'error';
    } else if ($check_request && $check_request->num_rows > 0) {
        $message = isset($lang['request_exists']) ? $lang['request_exists'] : "You've already requested enrollment for this course";
        $message_type = 'error';
    } else {
        // Create enrollment request
        $sql = "INSERT INTO enrollment_requests (student_id, course_id, status) VALUES ($student_id, $course_id, 'pending')";
        if ($conn->query($sql) === TRUE) {
            $message = isset($lang['request_sent']) ? $lang['request_sent'] : "Enrollment request sent successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
    }
}

// Cancel a pending request
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $request_id = intval($_GET['cancel']);
    
    // Verify that the request belongs to this student
    $check_sql = "SELECT id FROM enrollment_requests WHERE id = $request_id AND student_id = $student_id AND status = 'pending'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result && $check_result->num_rows > 0) {
        $cancel_sql = "DELETE FROM enrollment_requests WHERE id = $request_id";
        if ($conn->query($cancel_sql) === TRUE) {
            $message = isset($lang['request_cancelled']) ? $lang['request_cancelled'] : "Enrollment request cancelled successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
    }
}

// Get courses available for student's class year
$courses_sql = "SELECT c.id, c.course_code, c.title, c.credits, c.class_year, c.department, c.description, t.name AS instructor_name
               FROM courses c 
               JOIN teachers t ON c.teacher_id = t.id
               WHERE c.class_year = {$student['class_year']}
               ORDER BY c.department, c.course_code";
$courses_result = $conn->query($courses_sql);

// Get student's pending requests
$pending_sql = "SELECT r.id, r.request_date, c.course_code, c.title, c.department, t.name AS instructor_name
               FROM enrollment_requests r
               JOIN courses c ON r.course_id = c.id
               JOIN teachers t ON c.teacher_id = t.id
               WHERE r.student_id = $student_id AND r.status = 'pending'
               ORDER BY r.request_date DESC";
$pending_result = $conn->query($pending_sql);

// Get student's approved requests
$approved_sql = "SELECT r.id, r.request_date, r.review_date, c.course_code, c.title, c.department, t.name AS instructor_name, 
                t2.name AS reviewer_name
                FROM enrollment_requests r
                JOIN courses c ON r.course_id = c.id
                JOIN teachers t ON c.teacher_id = t.id
                LEFT JOIN teachers t2 ON r.reviewed_by = t2.id
                WHERE r.student_id = $student_id AND r.status = 'approved'
                ORDER BY r.review_date DESC";
$approved_result = $conn->query($approved_sql);

// Get student's rejected requests
$rejected_sql = "SELECT r.id, r.request_date, r.review_date, c.course_code, c.title, c.department, t.name AS instructor_name, 
               t2.name AS reviewer_name
               FROM enrollment_requests r
               JOIN courses c ON r.course_id = c.id
               JOIN teachers t ON c.teacher_id = t.id
               LEFT JOIN teachers t2 ON r.reviewed_by = t2.id
               WHERE r.student_id = $student_id AND r.status = 'rejected'
               ORDER BY r.review_date DESC";
$rejected_result = $conn->query($rejected_sql);

$page_title = isset($lang['request_course']) ? $lang['request_course'] : "Request Courses";
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <div class="main-content">
        <!-- Operations Panel on the left -->
        <div class="operations-column">
            <?php include 'includes/operations.php'; ?>
        </div>
        
        <!-- Main content on the right -->
        <div class="content-column">
            <div class="student-panel">
                <div class="student-panel-header">
                    <h2><i class="fas fa-plus-circle"></i> <?php echo $page_title; ?></h2>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pending Requests Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><?php echo isset($lang['pending_requests']) ? $lang['pending_requests'] : 'Pending Requests'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <?php if ($pending_result && $pending_result->num_rows > 0): ?>
                            <table class="requests-table">
                                <thead>
                                    <tr>
                                        <th><?php echo isset($lang['course_code']) ? $lang['course_code'] : 'Course Code'; ?></th>
                                        <th><?php echo isset($lang['course_name']) ? $lang['course_name'] : 'Course Name'; ?></th>
                                        <th><?php echo isset($lang['instructor']) ? $lang['instructor'] : 'Instructor'; ?></th>
                                        <th><?php echo isset($lang['request_date']) ? $lang['request_date'] : 'Request Date'; ?></th>
                                        <th><?php echo isset($lang['action']) ? $lang['action'] : 'Action'; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($request = $pending_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="course-code-badge"><?php echo $request['course_code']; ?></span></td>
                                            <td><?php echo $request['title']; ?></td>
                                            <td><?php echo $request['instructor_name']; ?></td>
                                            <td><?php echo date('d M Y', strtotime($request['request_date'])); ?></td>
                                            <td>
                                                <a href="?cancel=<?php echo $request['id']; ?>" class="action-btn cancel-btn" onclick="return confirm('<?php echo isset($lang['confirm_delete']) ? $lang['confirm_delete'] : 'Are you sure you want to cancel this request?'; ?>')">
                                                    <i class="fas fa-times"></i> <?php echo isset($lang['cancel']) ? $lang['cancel'] : 'Cancel'; ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-clock"></i></div>
                                <p><?php echo isset($lang['no_pending_requests']) ? $lang['no_pending_requests'] : 'You have no pending course enrollment requests'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Available Courses Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-book"></i> <?php echo isset($lang['available_courses']) ? $lang['available_courses'] : 'Available Courses'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <?php if ($courses_result && $courses_result->num_rows > 0): ?>
                            <table class="courses-table">
                                <thead>
                                    <tr>
                                        <th><?php echo isset($lang['course_code']) ? $lang['course_code'] : 'Course Code'; ?></th>
                                        <th><?php echo isset($lang['course_name']) ? $lang['course_name'] : 'Course Name'; ?></th>
                                        <th><?php echo isset($lang['department']) ? $lang['department'] : 'Department'; ?></th>
                                        <th><?php echo isset($lang['credits']) ? $lang['credits'] : 'Credits'; ?></th>
                                        <th><?php echo isset($lang['instructor']) ? $lang['instructor'] : 'Instructor'; ?></th>
                                        <th><?php echo isset($lang['action']) ? $lang['action'] : 'Action'; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($course = $courses_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="course-code-badge"><?php echo $course['course_code']; ?></span></td>
                                            <td><?php echo $course['title']; ?></td>
                                            <td><?php echo $course['department']; ?></td>
                                            <td><?php echo $course['credits']; ?></td>
                                            <td><?php echo $course['instructor_name']; ?></td>
                                            <td>
                                                <?php
                                                // Check if student is already enrolled or has a pending request
                                                $enrolled_check_sql = "SELECT id FROM enrollments WHERE student_id = $student_id AND course_id = {$course['id']}";
                                                $enrolled_check = $conn->query($enrolled_check_sql);
                                                
                                                $request_check_sql = "SELECT id FROM enrollment_requests WHERE student_id = $student_id AND course_id = {$course['id']} AND status != 'rejected'";
                                                $request_check = $conn->query($request_check_sql);
                                                
                                                if ($enrolled_check && $enrolled_check->num_rows > 0):
                                                ?>
                                                    <span class="status-badge enrolled">
                                                        <i class="fas fa-check-circle"></i> <?php echo isset($lang['already_enrolled']) ? $lang['already_enrolled'] : 'Already Enrolled'; ?>
                                                    </span>
                                                <?php elseif ($request_check && $request_check->num_rows > 0): ?>
                                                    <span class="status-badge pending">
                                                        <i class="fas fa-clock"></i> <?php echo isset($lang['pending']) ? $lang['pending'] : 'Pending'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <form method="post" action="" class="request-form">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <button type="submit" name="request_enrollment" class="btn primary request-btn">
                                                            <i class="fas fa-plus-circle"></i> <?php echo isset($lang['request_enrollment']) ? $lang['request_enrollment'] : 'Request Enrollment'; ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-book"></i></div>
                                <p><?php echo sprintf(isset($lang['no_courses_for_year']) ? $lang['no_courses_for_year'] : 'No courses available for Year %d', $student['class_year']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Past Requests Tabs -->
                <div class="panel-card">
                    <div class="tabs-header">
                        <button class="tab-btn active" data-tab="approved">
                            <i class="fas fa-check-circle"></i> <?php echo isset($lang['approved_requests']) ? $lang['approved_requests'] : 'Approved Requests'; ?>
                            <span class="tab-count"><?php echo $approved_result->num_rows; ?></span>
                        </button>
                        <button class="tab-btn" data-tab="rejected">
                            <i class="fas fa-times-circle"></i> <?php echo isset($lang['rejected_requests']) ? $lang['rejected_requests'] : 'Rejected Requests'; ?>
                            <span class="tab-count"><?php echo $rejected_result->num_rows; ?></span>
                        </button>
                    </div>
                    <div class="panel-card-body">
                        <div class="tab-content active" id="approved-tab">
                            <?php if ($approved_result && $approved_result->num_rows > 0): ?>
                                <table class="requests-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo isset($lang['course_code']) ? $lang['course_code'] : 'Course Code'; ?></th>
                                            <th><?php echo isset($lang['course_name']) ? $lang['course_name'] : 'Course Name'; ?></th>
                                            <th><?php echo isset($lang['instructor']) ? $lang['instructor'] : 'Instructor'; ?></th>
                                            <th><?php echo isset($lang['reviewed_by']) ? $lang['reviewed_by'] : 'Reviewed By'; ?></th>
                                            <th><?php echo isset($lang['review_date']) ? $lang['review_date'] : 'Review Date'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($request = $approved_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="course-code-badge"><?php echo $request['course_code']; ?></span></td>
                                                <td><?php echo $request['title']; ?></td>
                                                <td><?php echo $request['instructor_name']; ?></td>
                                                <td><?php echo $request['reviewer_name']; ?></td>
                                                <td><?php echo date('d M Y', strtotime($request['review_date'])); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                                    <p><?php echo isset($lang['no_approved_requests']) ? $lang['no_approved_requests'] : 'You have no approved course enrollment requests'; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-content" id="rejected-tab">
                            <?php if ($rejected_result && $rejected_result->num_rows > 0): ?>
                                <table class="requests-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo isset($lang['course_code']) ? $lang['course_code'] : 'Course Code'; ?></th>
                                            <th><?php echo isset($lang['course_name']) ? $lang['course_name'] : 'Course Name'; ?></th>
                                            <th><?php echo isset($lang['instructor']) ? $lang['instructor'] : 'Instructor'; ?></th>
                                            <th><?php echo isset($lang['reviewed_by']) ? $lang['reviewed_by'] : 'Reviewed By'; ?></th>
                                            <th><?php echo isset($lang['review_date']) ? $lang['review_date'] : 'Review Date'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($request = $rejected_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="course-code-badge"><?php echo $request['course_code']; ?></span></td>
                                                <td><?php echo $request['title']; ?></td>
                                                <td><?php echo $request['instructor_name']; ?></td>
                                                <td><?php echo $request['reviewer_name']; ?></td>
                                                <td><?php echo date('d M Y', strtotime($request['review_date'])); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state-tab">
                                    <div class="empty-icon-tab"><i class="fas fa-times-circle"></i></div>
                                    <p><?php echo isset($lang['no_rejected_requests']) ? $lang['no_rejected_requests'] : 'You have no rejected course enrollment requests'; ?></p>
                                    <small class="empty-note"><?php echo isset($lang['request_course_note']) ? $lang['request_course_note'] : 'Rejected requests will appear here'; ?></small>
                                </div>
                            <?php endif; ?>
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

.badge-counter {
    background-color: white;
    color: var(--primary-color);
    font-size: 12px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
}

.panel-card-body {
    padding: 0;
}

/* Message styles */
.message {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
}

.message.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.message:before {
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    margin-right: 10px;
}

.message.success:before {
    content: '\f058';
}

.message.error:before {
    content: '\f057';
}

/* Requests Table Styles */
.requests-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.requests-table th, 
.requests-table td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    text-align: left;
}

.requests-table th {
    background-color: #f8fafc;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}

.requests-table tbody tr:hover {
    background-color: #f8f9fa;
}

.requests-table tbody tr:last-child td {
    border-bottom: none;
}

.course-code-badge {
    display: inline-block;
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 12px;
}

.action-btn {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s;
}

.action-btn i {
    margin-right: 5px;
}

.action-btn.cancel-btn {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    background-color: #fff0f0;
    color: #dc3545;
    border: 1px solid #ffcccc;
    white-space: nowrap;
}

.action-btn.cancel-btn:hover {
    background-color: #ffeeee;
    border-color: #dc3545;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
}

.action-btn.cancel-btn:active {
    transform: translateY(0);
}

.action-btn.cancel-btn i {
    margin-right: 6px;
    font-size: 11px;
}

/* Improved Tab Styling */
.tabs-header {
    display: flex;
    background: linear-gradient(to right, #3949ab, #5c6bc0);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.tab-btn {
    padding: 12px 16px;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    flex: 1;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tab-btn i {
    margin-right: 8px;
}

.tab-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.tab-btn.active {
    color: white;
    background-color: rgba(255, 255, 255, 0.2);
    border-bottom: 3px solid white;
}

.tab-count {
    background-color: rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 12px;
    padding: 2px 6px;
    font-size: 12px;
    margin-left: 6px;
    display: inline-flex;
    min-width: 20px;
    justify-content: center;
}

.tab-content {
    display: none;
    padding: 0;
}

.tab-content.active {
    display: block;
}

/* Courses Table Styles */
.courses-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.courses-table th, 
.courses-table td {
    padding: 12px 10px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    text-align: left;
    vertical-align: middle;
}

.courses-table th {
    background-color: #f8fafc;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}

.courses-table tbody tr {
    transition: background-color 0.15s ease;
}

.courses-table tbody tr:hover {
    background-color: #f8f9fa;
}

.courses-table tbody tr:last-child td {
    border-bottom: none;
}

.course-code-badge {
    display: inline-block;
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 5px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 13px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.status-badge.enrolled {
    background-color: rgba(32, 156, 238, 0.1);
    color: #209cee;
}

.status-badge.pending {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.status-badge i {
    margin-right: 5px;
}

.request-form {
    margin: 0;
}

.request-btn {
    padding: 6px 10px;
    font-size: 12px;
    white-space: nowrap;
    border-radius: 4px;
}

/* Empty state styling to ensure consistency */
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
    font-size: 15px;
    margin: 0;
    color: #64748b;
}

/* Empty state for tabs */
.empty-state-tab {
    padding: 30px 20px;
    text-align: center;
    color: var(--text-muted);
    background-color: #fafafa;
    border-radius: 6px;
    margin: 15px;
}

.empty-icon-tab {
    font-size: 40px;
    color: #e0e0e0;
    margin-bottom: 10px;
}

.empty-state-tab p {
    font-size: 15px;
    margin: 0 0 8px;
    color: #666;
}

.empty-note {
    display: block;
    font-size: 13px;
    color: #999;
}

/* Responsive adjustments for tables */
@media (max-width: 768px) {
    .requests-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .courses-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

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

.badge-counter {
    background-color: white;
    color: var(--primary-color);
    font-size: 12px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
}

.panel-card-body {
    padding: 0;
}

/* Message styles */
.message {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
}

.message.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.message:before {
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    margin-right: 10px;
}

.message.success:before {
    content: '\f058';
}

.message.error:before {
    content: '\f057';
}

/* Requests Table Styles */
.requests-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.requests-table th, 
.requests-table td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    text-align: left;
}

.requests-table th {
    background-color: #f8fafc;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}

.requests-table tbody tr:hover {
    background-color: #f8f9fa;
}

.requests-table tbody tr:last-child td {
    border-bottom: none;
}

.course-code-badge {
    display: inline-block;
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 12px;
}

.action-btn {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s;
}

.action-btn i {
    margin-right: 5px;
}

.action-btn.cancel-btn {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    background-color: #fff0f0;
    color: #dc3545;
    border: 1px solid #ffcccc;
    white-space: nowrap;
}

.action-btn.cancel-btn:hover {
    background-color: #ffeeee;
    border-color: #dc3545;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
}

.action-btn.cancel-btn:active {
    transform: translateY(0);
}

.action-btn.cancel-btn i {
    margin-right: 6px;
    font-size: 11px;
}

/* Improved Tab Styling */
.tabs-header {
    display: flex;
    background: linear-gradient(to right, #3949ab, #5c6bc0);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.tab-btn {
    padding: 12px 16px;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    flex: 1;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tab-btn i {
    margin-right: 8px;
}

.tab-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.tab-btn.active {
    color: white;
    background-color: rgba(255, 255, 255, 0.2);
    border-bottom: 3px solid white;
}

.tab-count {
    background-color: rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 12px;
    padding: 2px 6px;
    font-size: 12px;
    margin-left: 6px;
    display: inline-flex;
    min-width: 20px;
    justify-content: center;
}

.tab-content {
    display: none;
    padding: 0;
}

.tab-content.active {
    display: block;
}

/* Courses Table Styles */
.courses-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.courses-table th, 
.courses-table td {
    padding: 12px 10px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    text-align: left;
    vertical-align: middle;
}

.courses-table th {
    background-color: #f8fafc;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}

.courses-table tbody tr {
    transition: background-color 0.15s ease;
}

.courses-table tbody tr:hover {
    background-color: #f8f9fa;
}

.courses-table tbody tr:last-child td {
    border-bottom: none;
}

.course-code-badge {
    display: inline-block;
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 5px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 13px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.status-badge.enrolled {
    background-color: rgba(32, 156, 238, 0.1);
    color: #209cee;
}

.status-badge.pending {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.status-badge i {
    margin-right: 5px;
}

.request-form {
    margin: 0;
}

.request-btn {
    padding: 6px 10px;
    font-size: 12px;
    white-space: nowrap;
    border-radius: 4px;
}

/* Empty state styling to ensure consistency */
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
    font-size: 15px;
    margin: 0;
    color: #64748b;
}

/* Empty state for tabs */
.empty-state-tab {
    padding: 30px 20px;
    text-align: center;
    color: var(--text-muted);
    background-color: #fafafa;
    border-radius: 6px;
    margin: 15px;
}

.empty-icon-tab {
    font-size: 40px;
    color: #e0e0e0;
    margin-bottom: 10px;
}

.empty-state-tab p {
    font-size: 15px;
    margin: 0 0 8px;
    color: #666;
}

.empty-note {
    display: block;
    font-size: 13px;
    color: #999;
}

/* Responsive adjustments for tables */
@media (max-width: 768px) {
    .requests-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .courses-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

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

.badge-counter {
    background-color: white;
    color: var(--primary-color);
    font-size: 12px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
}

.panel-card-body {
    padding: 0;
}

/* Message styles */
.message {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
}

.message.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.message:before {
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    margin-right: 10px;
}

.message.success:before {
    content: '\f058';
}

.message.error:before {
    content: '\f057';
}

/* Requests Table Styles */
.requests-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.requests-table th, 
.requests-table td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    text-align: left;
}

.requests-table th {
    background-color: #f8fafc;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}

.requests-table tbody tr:hover {
    background-color: #f8f9fa;
}

.requests-table tbody tr:last-child td {
    border-bottom: none;
}

.course-code-badge {
    display: inline-block;
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 12px;
}

.action-btn {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s;
}

.action-btn i {
    margin-right: 5px;
}

.action-btn.cancel-btn {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    background-color: #fff0f0;
    color: #dc3545;
    border: 1px solid #ffcccc;
    white-space: nowrap;
}

.action-btn.cancel-btn:hover {
    background-color: #ffeeee;
    border-color: #dc3545;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
}

.action-btn.cancel-btn:active {
    transform: translateY(0);
}

.action-btn.cancel-btn i {
    margin-right: 6px;
    font-size: 11px;
}

/* Improved Tab Styling */
.tabs-header {
    display: flex;
    background: linear-gradient(to right, #3949ab, #5c6bc0);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.tab-btn {
    padding: 12px 16px;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    flex: 1;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tab-btn i {
    margin-right: 8px;
}

.tab-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.tab-btn.active {
    color: white;
    background-color: rgba(255, 255, 255, 0.2);
    border-bottom: 3px solid white;
}

.tab-count {
    background-color: rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 12px;
    padding: 2px 6px;
    font-size: 12px;
    margin-left: 6px;
    display: inline-flex;
    min-width: 20px;
    justify-content: center;
}

.tab-content {
    display: none;
    padding: 0;
}

.tab-content.active {
    display: block;
}

/* Courses Table Styles */
.courses-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.courses-table th, 
.courses-table td {
    padding: 12px 10px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    text-align: left;
    vertical-align: middle;
}

.courses-table th {
    background-color: #f8fafc;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}

.courses-table tbody tr {
    transition: background-color 0.15s ease;
}

.courses-table tbody tr:hover {
    background-color: #f8f9fa;
}

.courses-table tbody tr:last-child td {
    border-bottom: none;
}

.course-code-badge {
    display: inline-block;
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 5px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 13px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.status-badge.enrolled {
    background-color: rgba(32, 156, 238, 0.1);
    color: #209cee;
}

.status-badge.pending {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.status-badge i {
    margin-right: 5px;
}

.request-form {
    margin: 0;
}

.request-btn {
    padding: 6px 10px;
    font-size: 12px;
    white-space: nowrap;
    border-radius: 4px;
}

/* Empty state for tabs */
.empty-state-tab {
    padding: 30px 20px;
    text-align: center;
    color: var(--text-muted);
    background-color: #fafafa;
    border-radius: 6px;
    margin: 15px;
}

.empty-icon-tab {
    font-size: 40px;
    color: #e0e0e0;
    margin-bottom: 10px;
}

.empty-state-tab p {
    font-size: 15px;
    margin: 0 0 8px;
    color: #666;
}

.empty-note {
    display: block;
    font-size: 13px;
    color: #999;
}

/* Responsive adjustments for tables */
@media (max-width: 768px) {
    .requests-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .courses-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

/* Compact Request Cards for Approved/Rejected */
.compact-request-cards {
    display: flex;
    flex-direction: column;
}

.compact-request-card {
    padding: 10px 16px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    transition: background-color 0.2s;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.compact-request-card:hover {
    background-color: rgba(0, 0, 0, 0.01);
}

.request-main-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.request-course-info {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.course-code {
    font-weight: 600;
    color: var(--primary-color);
    font-size: 14px;
}

.course-title {
    font-size: 14px;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
}

.request-date-badge {
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.request-date-badge.green {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.request-date-badge.red {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.request-reviewer {
    font-size: 12px;
    color: var(--text-muted);
    padding-left: 2px;
}

.request-reviewer i {
    margin-right: 4px;
}

/* Animation for compact items */
.compact-request-card {
    animation: fadeInUp 0.3s ease forwards;
    opacity: 0;
}

.compact-request-card:nth-child(1) { animation-delay: 0.05s; }
.compact-request-card:nth-child(2) { animation-delay: 0.1s; }
.compact-request-card:nth-child(3) { animation-delay: 0.15s; }
.compact-request-card:nth-child(4) { animation-delay: 0.2s; }
.compact-request-card:nth-child(5) { animation-delay: 0.25s; }

@media (max-width: 576px) {
    .request-course-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
    
    .request-main-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .request-date-badge {
        align-self: flex-start;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId + '-tab').classList.add('active');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
