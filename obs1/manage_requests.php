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

// If language_label key is missing, provide a default value
$language_label = isset($lang['language_label']) ? $lang['language_label'] : 'Language:';
$manage_requests = isset($lang['manage_requests']) ? $lang['manage_requests'] : 'Manage Course Requests';

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle request approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['approve']) || isset($_POST['reject'])) {
        $request_id = $conn->real_escape_string($_POST['request_id']);
        $status = isset($_POST['approve']) ? 'approved' : 'rejected';
        
        // Update request status
        $sql = "UPDATE enrollment_requests 
                SET status = '$status', 
                    reviewed_by = $teacher_id, 
                    review_date = NOW()
                WHERE id = $request_id";
                
        if ($conn->query($sql)) {
            // If approved, add student to course
            if ($status === 'approved') {
                $get_request = "SELECT student_id, course_id FROM enrollment_requests WHERE id = $request_id";
                $result = $conn->query($get_request);
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $student_id = $row['student_id'];
                    $course_id = $row['course_id'];
                    
                    // Add enrollment
                    $enroll_sql = "INSERT INTO enrollments (student_id, course_id) VALUES ($student_id, $course_id)";
                    if ($conn->query($enroll_sql)) {
                        // Create empty grade entry
                        $enrollment_id = $conn->insert_id;
                        $grade_sql = "INSERT INTO grades (enrollment_id) VALUES ($enrollment_id)";
                        $conn->query($grade_sql);
                    }
                }
                $message = isset($lang['request_approved']) ? $lang['request_approved'] : 'Request approved successfully';
            } else {
                $message = isset($lang['request_rejected']) ? $lang['request_rejected'] : 'Request rejected successfully';
            }
            $message_type = 'success';
        } else {
            $message = "Error updating request: " . $conn->error;
            $message_type = 'error';
        }
    }
}

// Get pending requests
$pending_sql = "SELECT er.*, c.course_code, c.title, c.credits, c.department, 
               s.name AS student_name, s.student_number, s.class_year
               FROM enrollment_requests er
               JOIN courses c ON er.course_id = c.id
               JOIN students s ON er.student_id = s.id
               WHERE er.status = 'pending'
               ORDER BY er.request_date ASC";
$pending_result = $conn->query($pending_sql);

$page_title = $manage_requests;
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
                <h2><?php echo $manage_requests; ?></h2>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><?php echo isset($lang['pending_requests']) ? $lang['pending_requests'] : 'Pending Requests'; ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($pending_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><?php echo isset($lang['student_name']) ? $lang['student_name'] : 'Student'; ?></th>
                                        <th><?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?></th>
                                        <th><?php echo isset($lang['course_code']) ? $lang['course_code'] : 'Course Code'; ?></th>
                                        <th><?php echo isset($lang['course_name']) ? $lang['course_name'] : 'Course Name'; ?></th>
                                        <th><?php echo isset($lang['request_date']) ? $lang['request_date'] : 'Request Date'; ?></th>
                                        <th><?php echo isset($lang['action']) ? $lang['action'] : 'Action'; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $pending_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['student_name'] . ' (' . $row['student_number'] . ')'; ?></td>
                                            <td><?php echo $row['class_year']; ?></td>
                                            <td><?php echo $row['course_code']; ?></td>
                                            <td><?php echo $row['title']; ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($row['request_date'])); ?></td>
                                            <td>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                    <div class="button-group">
                                                        <button type="submit" name="approve" class="btn primary">
                                                            <i class="fas fa-check"></i> <?php echo isset($lang['approve']) ? $lang['approve'] : 'Approve'; ?>
                                                        </button>
                                                        <button type="submit" name="reject" class="btn danger">
                                                            <i class="fas fa-times"></i> <?php echo isset($lang['reject']) ? $lang['reject'] : 'Reject'; ?>
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="no-data"><?php echo isset($lang['no_requests_to_review']) ? $lang['no_requests_to_review'] : 'No enrollment requests to review'; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
