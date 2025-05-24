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
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php");
    exit;
}

// Get all announcements
$announcements_sql = "SELECT a.*, t.name as teacher_name, t.position as teacher_position,
                     t.department as teacher_department
                     FROM announcements a 
                     JOIN teachers t ON a.teacher_id = t.id 
                     ORDER BY a.created_at DESC";
$announcements = $conn->query($announcements_sql);

// Set page title
$page_title = isset($lang['announcements']) ? $lang['announcements'] : 'Announcements';
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
                <h2><?php echo isset($lang['announcements']) ? $lang['announcements'] : 'Announcements'; ?></h2>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><?php echo isset($lang['all_announcements']) ? $lang['all_announcements'] : 'All Announcements'; ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($announcements && $announcements->num_rows > 0): ?>
                        <div class="announcements-list">
                            <?php while($row = $announcements->fetch_assoc()): ?>
                                <div class="announcement-item">
                                    <div class="announcement-header">
                                        <h4 class="announcement-title"><?php echo htmlspecialchars($row['title']); ?></h4>
                                        <div class="announcement-info">
                                            <div class="announcement-teacher">
                                                <i class="fas fa-user-tie"></i>
                                                <span class="teacher-name"><?php echo htmlspecialchars($row['teacher_name']); ?></span>
                                                <span class="teacher-department"><?php echo htmlspecialchars($row['teacher_department']); ?></span>
                                            </div>
                                            <div class="announcement-date">
                                                <i class="fas fa-calendar-alt"></i> 
                                                <?php echo date('F j, Y', strtotime($row['created_at'])); ?>
                                                <span class="announcement-time">
                                                    <i class="fas fa-clock"></i> 
                                                    <?php echo date('g:i a', strtotime($row['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="announcement-content">
                                        <?php echo nl2br(htmlspecialchars($row['content'])); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-bullhorn fa-3x"></i>
                            <p><?php echo isset($lang['no_announcements_yet']) ? $lang['no_announcements_yet'] : 'There are no announcements yet.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
