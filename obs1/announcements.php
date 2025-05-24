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

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$message = '';
$message_type = '';

// Handle announcement creation (for teachers)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $user_type == 'teacher') {
    if (isset($_POST['create_announcement'])) {
        $title = $conn->real_escape_string($_POST['title']);
        $content = $conn->real_escape_string($_POST['content']);
        
        if (!empty($title) && !empty($content)) {
            $sql = "INSERT INTO announcements (title, content, author_id, author_type)
                    VALUES ('$title', '$content', $user_id, '$user_type')";
                    
            if ($conn->query($sql)) {
                $message = isset($lang['announcement_success']) ? $lang['announcement_success'] : 'Announcement published successfully';
                $message_type = 'success';
            } else {
                $message = "Error: " . $conn->error;
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['delete_announcement'])) {
        $announcement_id = $conn->real_escape_string($_POST['announcement_id']);
        
        // Check if the announcement belongs to this teacher
        $check_sql = "SELECT id FROM announcements WHERE id = $announcement_id AND author_id = $user_id AND author_type = 'teacher'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $sql = "DELETE FROM announcements WHERE id = $announcement_id";
            if ($conn->query($sql)) {
                $message = isset($lang['announcement_deleted']) ? $lang['announcement_deleted'] : 'Announcement deleted successfully';
                $message_type = 'success';
            } else {
                $message = "Error: " . $conn->error;
                $message_type = 'error';
            }
        } else {
            $message = isset($lang['not_authorized']) ? $lang['not_authorized'] : 'You are not authorized to perform this action';
            $message_type = 'error';
        }
    }
}

// Get all announcements for students and teachers
$announcements_sql = "SELECT a.*, 
                     COALESCE(t.name, 'Admin') as author_name,
                     COALESCE(t.position, '') as author_position
                     FROM announcements a
                     LEFT JOIN teachers t ON a.author_id = t.id AND a.author_type = 'teacher'
                     ORDER BY a.publish_date DESC";
$all_announcements = $conn->query($announcements_sql);

// For teachers, get their own announcements
$my_announcements = null;
if ($user_type == 'teacher') {
    $my_announcements_sql = "SELECT * FROM announcements 
                           WHERE author_id = $user_id AND author_type = 'teacher'
                           ORDER BY publish_date DESC";
    $my_announcements = $conn->query($my_announcements_sql);
}

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
                <h2><i class="fas fa-bullhorn"></i> <?php echo $page_title; ?></h2>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($user_type == 'teacher'): ?>
                <!-- Teacher can create announcements -->
                <div class="card announcement-create-card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> <?php echo isset($lang['create_announcement']) ? $lang['create_announcement'] : 'Create New Announcement'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="announcement-form">
                            <div class="form-group">
                                <label for="title"><?php echo isset($lang['title']) ? $lang['title'] : 'Title'; ?></label>
                                <input type="text" id="title" name="title" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="content"><?php echo isset($lang['content']) ? $lang['content'] : 'Content'; ?></label>
                                <textarea id="content" name="content" rows="5" class="form-control" required></textarea>
                            </div>
                            <button type="submit" name="create_announcement" class="btn primary">
                                <i class="fas fa-bullhorn"></i> <?php echo isset($lang['publish']) ? $lang['publish'] : 'Publish'; ?>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Teacher's own announcements -->
                <div class="card announcements-card my-announcements">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard-list"></i> <?php echo isset($lang['my_announcements']) ? $lang['my_announcements'] : 'My Announcements'; ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if ($my_announcements && $my_announcements->num_rows > 0): ?>
                            <div class="announcements-list">
                                <?php while ($announcement = $my_announcements->fetch_assoc()): ?>
                                    <div class="announcement-item">
                                        <div class="announcement-header">
                                            <h4><?php echo $announcement['title']; ?></h4>
                                            <div class="announcement-actions">
                                                <form method="POST" onsubmit="return confirm('<?php echo isset($lang['confirm_delete']) ? $lang['confirm_delete'] : 'Are you sure you want to delete this announcement?'; ?>');">
                                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                    <button type="submit" name="delete_announcement" class="btn-icon danger">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="announcement-content">
                                            <?php echo nl2br($announcement['content']); ?>
                                        </div>
                                        <div class="announcement-footer">
                                            <div class="announcement-date">
                                                <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($announcement['publish_date'])); ?>
                                                <i class="fas fa-clock ml-2"></i> <?php echo date('g:i a', strtotime($announcement['publish_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-inbox fa-3x"></i>
                                <p><?php echo isset($lang['no_announcements']) ? $lang['no_announcements'] : 'You have not published any announcements yet.'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- All announcements (visible to all users) -->
            <div class="card announcements-card all-announcements">
                <div class="card-header">
                    <h3><i class="fas fa-bullhorn"></i> <?php echo isset($lang['all_announcements']) ? $lang['all_announcements'] : 'All Announcements'; ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($all_announcements && $all_announcements->num_rows > 0): ?>
                        <div class="announcements-list">
                            <?php while ($announcement = $all_announcements->fetch_assoc()): ?>
                                <div class="announcement-item">
                                    <div class="announcement-header">
                                        <div class="announcement-title-wrapper">
                                            <h4><?php echo $announcement['title']; ?></h4>
                                            <span class="announcement-badge"><?php echo date('M j', strtotime($announcement['publish_date'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="announcement-content">
                                        <?php echo nl2br($announcement['content']); ?>
                                    </div>
                                    <div class="announcement-footer">
                                        <div class="announcement-author">
                                            <i class="fas fa-user-circle"></i> 
                                            <?php 
                                                echo $announcement['author_name'];
                                                if (!empty($announcement['author_position'])) {
                                                    echo " <span class='author-position'>({$announcement['author_position']})</span>";
                                                }
                                            ?>
                                        </div>
                                        <div class="announcement-date">
                                            <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($announcement['publish_date'])); ?>
                                            <i class="fas fa-clock ml-2"></i> <?php echo date('g:i a', strtotime($announcement['publish_date'])); ?>
                                        </div>
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

<style>
/* Enhanced Announcements styles */
.announcements-card {
    margin-bottom: 30px;
    transition: all 0.3s ease;
}

.announcement-create-card {
    border-left: 4px solid #10b981;
}

.my-announcements {
    border-left: 4px solid #3b82f6;
}

.all-announcements {
    border-left: 4px solid #6366f1;
}

.announcement-form .form-group {
    margin-bottom: 20px;
}

.announcement-form label {
    display: block;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--text-primary);
}

.announcement-form .form-control {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: var(--border-radius-sm);
    font-size: 15px;
    transition: all 0.3s ease;
}

.announcement-form .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.1);
    outline: none;
}

.announcement-form textarea {
    resize: vertical;
    min-height: 120px;
}

.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.announcement-item {
    background-color: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.announcement-item:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.announcement-header {
    padding: 15px 20px;
    background-color: #f8fafd;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.announcement-title-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.announcement-badge {
    background-color: var(--primary-color);
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.announcement-header h4 {
    margin: 0;
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
}

.announcement-content {
    padding: 20px;
    line-height: 1.6;
    color: var(--text-secondary);
    font-size: 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.announcement-footer {
    padding: 12px 20px;
    background-color: #fcfdfe;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: var(--text-muted);
    flex-wrap: wrap;
}

.announcement-author, 
.announcement-date {
    display: flex;
    align-items: center;
}

.announcement-author {
    font-weight: 500;
}

.author-position {
    font-weight: normal;
    color: var(--text-muted);
    font-style: italic;
    margin-left: 3px;
}

.announcement-author i, 
.announcement-date i {
    margin-right: 6px;
    color: var(--primary-color);
}

.announcement-date i.ml-2 {
    margin-left: 10px;
}

.announcement-actions {
    display: flex;
    gap: 10px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    background-color: rgba(0, 0, 0, 0.03);
    color: var(--text-secondary);
}

.btn-icon:hover {
    background-color: rgba(0, 0, 0, 0.06);
    transform: scale(1.05);
}

.btn-icon.danger {
    color: #e53935;
}

.btn-icon.danger:hover {
    background-color: rgba(229, 57, 53, 0.1);
}

/* No data state for announcements */
.no-data {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.no-data i {
    color: #e2e8f0;
    margin-bottom: 15px;
}

.no-data p {
    font-size: 15px;
}

/* Card header enhancements */
.card-header h3 {
    display: flex;
    align-items: center;
}

.card-header i {
    margin-right: 8px;
    color: var(--primary-color);
}

.page-header h2 {
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header h2 i {
    color: var(--primary-color);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .announcement-footer {
        flex-direction: column;
        gap: 6px;
        align-items: flex-start;
    }
    
    .announcement-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .announcement-actions {
        align-self: flex-end;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
