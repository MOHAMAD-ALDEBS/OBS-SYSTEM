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

// Process request approvals or rejections
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve']) && isset($_POST['request_id'])) {
        $request_id = $conn->real_escape_string($_POST['request_id']);
        
        // Get request details
        $request_sql = "SELECT student_id, course_id FROM enrollment_requests WHERE id = $request_id AND status = 'pending'";
        $request_result = $conn->query($request_sql);
        
        if ($request_result && $request_result->num_rows > 0) {
            $request = $request_result->fetch_assoc();
            $student_id = $request['student_id'];
            $course_id = $request['course_id'];
            
            // Check if teacher is authorized to approve this course request
            $auth_sql = "SELECT id FROM courses WHERE id = $course_id AND teacher_id = $teacher_id";
            $auth_result = $conn->query($auth_sql);
            
            if ($auth_result && $auth_result->num_rows > 0) {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Update request status
                    $update_sql = "UPDATE enrollment_requests 
                                  SET status = 'approved', 
                                      reviewed_by = $teacher_id, 
                                      review_date = NOW() 
                                  WHERE id = $request_id";
                    $conn->query($update_sql);
                    
                    // Add enrollment
                    $enroll_sql = "INSERT INTO enrollments (student_id, course_id) 
                                  VALUES ($student_id, $course_id)";
                    $conn->query($enroll_sql);
                    
                    // Create empty grade record
                    $enrollment_id = $conn->insert_id;
                    $grade_sql = "INSERT INTO grades (enrollment_id) VALUES ($enrollment_id)";
                    $conn->query($grade_sql);
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $message = isset($lang['request_approved']) ? $lang['request_approved'] : 'Request approved successfully';
                    $message_type = 'success';
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
        } else {
            $message = "Invalid request";
            $message_type = 'error';
        }
    } elseif (isset($_POST['reject']) && isset($_POST['request_id'])) {
        $request_id = $conn->real_escape_string($_POST['request_id']);
        
        // Get request details
        $request_sql = "SELECT course_id FROM enrollment_requests WHERE id = $request_id AND status = 'pending'";
        $request_result = $conn->query($request_sql);
        
        if ($request_result && $request_result->num_rows > 0) {
            $request = $request_result->fetch_assoc();
            $course_id = $request['course_id'];
            
            // Check if teacher is authorized to reject this course request
            $auth_sql = "SELECT id FROM courses WHERE id = $course_id AND teacher_id = $teacher_id";
            $auth_result = $conn->query($auth_sql);
            
            if ($auth_result && $auth_result->num_rows > 0) {
                // Update request status
                $update_sql = "UPDATE enrollment_requests 
                              SET status = 'rejected', 
                                  reviewed_by = $teacher_id, 
                                  review_date = NOW() 
                              WHERE id = $request_id";
                              
                if ($conn->query($update_sql) === TRUE) {
                    $message = isset($lang['request_rejected']) ? $lang['request_rejected'] : 'Request rejected successfully';
                    $message_type = 'success';
                } else {
                    $message = "Error: " . $conn->error;
                    $message_type = 'error';
                }
            } else {
                $message = isset($lang['not_authorized']) ? $lang['not_authorized'] : 'You are not authorized to perform this action';
                $message_type = 'error';
            }
        } else {
            $message = "Invalid request";
            $message_type = 'error';
        }
    }
}

// Get teacher's courses with pending requests
$courses_sql = "SELECT c.id, c.course_code, c.title, c.class_year,
               (SELECT COUNT(*) FROM enrollment_requests er WHERE er.course_id = c.id AND er.status = 'pending') as pending_count
               FROM courses c
               WHERE c.teacher_id = $teacher_id
               HAVING pending_count > 0
               ORDER BY pending_count DESC, c.course_code";
$courses_result = $conn->query($courses_sql);

// Get pending enrollment requests for teacher's courses
$requests_sql = "SELECT er.id, er.student_id, er.course_id, er.request_date, 
                 s.name as student_name, s.student_number, s.class_year as student_year, s.department, 
                 c.course_code, c.title as course_title
                 FROM enrollment_requests er
                 JOIN students s ON er.student_id = s.id
                 JOIN courses c ON er.course_id = c.id
                 WHERE c.teacher_id = $teacher_id AND er.status = 'pending'
                 ORDER BY er.request_date";
$requests_result = $conn->query($requests_sql);

// Get recent processed requests
$processed_sql = "SELECT er.id, er.student_id, er.course_id, er.request_date, er.status, er.review_date,
                 s.name as student_name, s.student_number, 
                 c.course_code, c.title as course_title,
                 t.name as reviewer_name
                 FROM enrollment_requests er
                 JOIN students s ON er.student_id = s.id
                 JOIN courses c ON er.course_id = c.id
                 JOIN teachers t ON er.reviewed_by = t.id
                 WHERE c.teacher_id = $teacher_id AND er.status IN ('approved', 'rejected')
                 ORDER BY er.review_date DESC
                 LIMIT 10";
$processed_result = $conn->query($processed_sql);

$page_title = isset($lang['manage_requests']) ? $lang['manage_requests'] : 'Manage Course Requests';
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
                <h2><i class="fas fa-clipboard-list"></i> <?php echo $page_title; ?></h2>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <!-- Pending Requests Summary -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> <?php echo isset($lang['pending_requests']) ? $lang['pending_requests'] : 'Pending Requests'; ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($courses_result->num_rows > 0): ?>
                        <div class="courses-with-requests">
                            <?php while ($course = $courses_result->fetch_assoc()): ?>
                                <div class="course-request-summary">
                                    <div class="course-info">
                                        <div class="course-code"><?php echo $course['course_code']; ?></div>
                                        <div class="course-title"><?php echo $course['title']; ?></div>
                                        <div class="course-year"><?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?> <?php echo $course['class_year']; ?></div>
                                    </div>
                                    <div class="request-count">
                                        <span class="count"><?php echo $course['pending_count']; ?></span>
                                        <span class="label"><?php echo isset($lang['pending_requests']) ? $lang['pending_requests'] : 'Pending Requests'; ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-inbox fa-3x"></i>
                            <p><?php echo isset($lang['no_requests_to_review']) ? $lang['no_requests_to_review'] : 'No enrollment requests to review'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Request Details -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list-alt"></i> <?php echo isset($lang['pending_requests']) ? $lang['pending_requests'] : 'Pending Requests'; ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($requests_result->num_rows > 0): ?>
                        <div class="requests-table-wrapper">
                            <table class="requests-table">
                                <thead>
                                    <tr>
                                        <th><?php echo isset($lang['student_name']) ? $lang['student_name'] : 'Student Name'; ?></th>
                                        <th><?php echo isset($lang['student_number']) ? $lang['student_number'] : 'Student Number'; ?></th>
                                        <th><?php echo isset($lang['course_code']) ? $lang['course_code'] : 'Course Code'; ?></th>
                                        <th><?php echo isset($lang['request_date']) ? $lang['request_date'] : 'Request Date'; ?></th>
                                        <th><?php echo isset($lang['action']) ? $lang['action'] : 'Action'; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($request = $requests_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="student-name"><?php echo $request['student_name']; ?></div>
                                                <div class="student-meta">
                                                    <span><?php echo $request['department']; ?></span>
                                                    <span>• <?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?> <?php echo $request['student_year']; ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo $request['student_number']; ?></td>
                                            <td>
                                                <div class="course-code"><?php echo $request['course_code']; ?></div>
                                                <div class="course-name"><?php echo $request['course_title']; ?></div>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($request['request_date'])); ?><br><?php echo date('H:i', strtotime($request['request_date'])); ?></td>
                                            <td class="actions">
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <button type="submit" name="approve" class="btn approve">
                                                        <?php echo isset($lang['approve']) ? $lang['approve'] : 'Approve'; ?>
                                                    </button>
                                                </form>
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <button type="submit" name="reject" class="btn reject">
                                                        <?php echo isset($lang['reject']) ? $lang['reject'] : 'Reject'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-clipboard-check fa-3x"></i>
                            <p><?php echo isset($lang['no_requests_to_review']) ? $lang['no_requests_to_review'] : 'No enrollment requests to review'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recently Processed Requests -->
            <?php if ($processed_result && $processed_result->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> <?php echo isset($lang['recently_processed']) ? $lang['recently_processed'] : 'Recently Processed Requests'; ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="requests-table-wrapper">
                            <table class="requests-table processed-table">
                                <thead>
                                    <tr>
                                        <th><?php echo isset($lang['student_name']) ? $lang['student_name'] : 'Student Name'; ?></th>
                                        <th><?php echo isset($lang['course_code']) ? $lang['course_code'] : 'Course Code'; ?></th>
                                        <th><?php echo isset($lang['status']) ? $lang['status'] : 'Status'; ?></th>
                                        <th><?php echo isset($lang['reviewed_by']) ? $lang['reviewed_by'] : 'Reviewed By'; ?></th>
                                        <th><?php echo isset($lang['review_date']) ? $lang['review_date'] : 'Review Date'; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($request = $processed_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $request['student_name']; ?></td>
                                            <td>
                                                <div class="course-code"><?php echo $request['course_code']; ?></div>
                                                <div class="course-name"><?php echo $request['course_title']; ?></div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($request['status']); ?>">
                                                    <?php echo isset($lang[$request['status']]) ? $lang[$request['status']] : ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $request['reviewer_name']; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['review_date'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Course request specific styles */
.courses-with-requests {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.course-request-summary {
    background-color: #f8fafd;
    border-radius: var(--border-radius);
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: calc(50% - 8px);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.course-info {
    flex: 1;
}

.course-code {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 5px;
    color: var(--text-primary);
}

.course-title {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.course-year {
    font-size: 13px;
    color: var(--text-muted);
    display: inline-block;
    background-color: #eef2f7;
    padding: 2px 8px;
    border-radius: 12px;
}

.request-count {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-dark);
    border-radius: var(--border-radius);
    padding: 8px 15px;
    min-width: 90px;
}

.request-count .count {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
}

.request-count .label {
    font-size: 11px;
    margin-top: 4px;
    text-align: center;
    line-height: 1.2;
}

.requests-table-wrapper {
    overflow-x: auto;
}

.requests-table {
    width: 100%;
    border-collapse: collapse;
}

.requests-table th, 
.requests-table td {
    padding: 12px 15px;
    text-align: left;
    vertical-align: top;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.requests-table th {
    background-color: #f9fafb;
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 13px;
}

.requests-table tbody tr:hover {
    background-color: #f9fafb;
}

.student-meta {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 3px;
}

.student-meta span {
    display: inline-block;
}

.course-name {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 2px;
}

.actions {
    display: flex;
    gap: 8px;
}

.inline-form {
    display: inline;
}

.btn {
    padding: 6px 12px;
    border-radius: var(--border-radius-sm);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn.approve {
    background-color: #d1fae5;
    color: #047857;
}

.btn.approve:hover {
    background-color: #10b981;
    color: white;
}

.btn.reject {
    background-color: #fee2e2;
    color: #b91c1c;
}

.btn.reject:hover {
    background-color: #ef4444;
    color: white;
}

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.pending {
    background-color: #fef3c7;
    color: #92400e;
}

.status-badge.approved {
    background-color: #d1fae5;
    color: #047857;
}

.status-badge.rejected {
    background-color: #fee2e2;
    color: #b91c1c;
}

.processed-table td {
    font-size: 14px;
}

/* Responsive layout */
@media (max-width: 768px) {
    .course-request-summary {
        width: 100%;
    }
    
    .actions {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
