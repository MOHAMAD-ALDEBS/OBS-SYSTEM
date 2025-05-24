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

// Get user information based on user type
if ($user_type == 'student') {
    $user_sql = "SELECT * FROM students WHERE id = $user_id";
} else if ($user_type == 'teacher') {
    $user_sql = "SELECT * FROM teachers WHERE id = $user_id";
} else {
    header("Location: dashboard.php");
    exit;
}

$user_result = $conn->query($user_sql);
$user = $user_result->fetch_assoc();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        // Common fields
        $email = $conn->real_escape_string($_POST['email']);
        
        // User type specific fields
        if ($user_type == 'student') {
            $department = $conn->real_escape_string($_POST['department']);
            $sql = "UPDATE students SET email = '$email', department = '$department' WHERE id = $user_id";
        } else if ($user_type == 'teacher') {
            $department = $conn->real_escape_string($_POST['department']);
            $position = $conn->real_escape_string($_POST['position']);
            $sql = "UPDATE teachers SET email = '$email', department = '$department', position = '$position' WHERE id = $user_id";
        }
        
        if ($conn->query($sql) === TRUE) {
            $message = isset($lang['profile_updated']) ? $lang['profile_updated'] : "Profile updated successfully";
            
            // Refresh user data
            $user_result = $conn->query($user_sql);
            $user = $user_result->fetch_assoc();
        } else {
            $message = "Error: " . $conn->error;
        }
    } else if (isset($_POST['change_password'])) {
        $current_password = hash('sha256', $_POST['current_password']);
        $new_password = $_POST['new_password'];
        
        // Verify current password
        if ($current_password != $user['password']) {
            $message = isset($lang['current_password_incorrect']) ? $lang['current_password_incorrect'] : "Current password is incorrect";
        } else if (empty($new_password)) {
            $message = isset($lang['new_password_required']) ? $lang['new_password_required'] : "New password is required";
        } else {
            $hashed_new_password = hash('sha256', $new_password);
            
            if ($user_type == 'student') {
                $sql = "UPDATE students SET password = '$hashed_new_password' WHERE id = $user_id";
            } else if ($user_type == 'teacher') {
                $sql = "UPDATE teachers SET password = '$hashed_new_password' WHERE id = $user_id";
            }
            
            if ($conn->query($sql) === TRUE) {
                $message = isset($lang['profile_updated']) ? $lang['profile_updated'] : "Profile updated successfully";
            } else {
                $message = "Error: " . $conn->error;
            }
        }
    }
}

$page_title = isset($lang['profile']) ? $lang['profile'] : "My Profile";
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
            <div class="user-panel">
                <div class="user-panel-header">
                    <h2><i class="fas fa-user-circle"></i> <?php echo $page_title; ?></h2>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <!-- User Profile Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-id-card"></i> <?php echo isset($lang['personal_info']) ? $lang['personal_info'] : 'Personal Information'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <div class="profile-container">
                            <div class="profile-main">
                                <div class="profile-avatar-section">
                                    <div class="profile-avatar">
                                        <i class="fas <?php echo $user_type == 'student' ? 'fa-user-graduate' : 'fa-user-tie'; ?>"></i>
                                    </div>
                                    
                                    <div class="profile-badges">
                                        <span class="profile-badge primary-badge">
                                            <?php echo $user_type == 'student' ? $user['student_number'] : $user['teacher_number']; ?>
                                        </span>
                                        <span class="profile-badge department-badge">
                                            <?php echo $user['department']; ?>
                                        </span>
                                        <?php if ($user_type == 'teacher'): ?>
                                            <span class="profile-badge position-badge">
                                                <?php echo $user['position']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="profile-info-section">
                                    <div class="profile-name">
                                        <h2><?php echo $user['name']; ?></h2>
                                    </div>
                                    
                                    <div class="profile-data">
                                        <div class="profile-data-item">
                                            <div class="data-icon">
                                                <i class="fas fa-envelope"></i>
                                            </div>
                                            <div class="data-content">
                                                <span class="data-label"><?php echo isset($lang['email_label']) ? $lang['email_label'] : 'Email'; ?></span>
                                                <span class="data-value"><?php echo $user['email']; ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="profile-data-item">
                                            <div class="data-icon">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="data-content">
                                                <span class="data-label"><?php echo isset($lang['username_label']) ? $lang['username_label'] : 'Username'; ?></span>
                                                <span class="data-value"><?php echo $user['username']; ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($user_type == 'student'): ?>
                                            <div class="profile-data-item">
                                                <div class="data-icon">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </div>
                                                <div class="data-content">
                                                    <span class="data-label"><?php echo isset($lang['admission_date']) ? $lang['admission_date'] : 'Admission Date'; ?></span>
                                                    <span class="data-value"><?php echo date('d M Y', strtotime($user['admission_date'])); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="profile-data-item">
                                                <div class="data-icon">
                                                    <i class="fas fa-graduation-cap"></i>
                                                </div>
                                                <div class="data-content">
                                                    <span class="data-label"><?php echo isset($lang['class_year']) ? $lang['class_year'] : 'Class Year'; ?></span>
                                                    <span class="data-value"><?php echo $user['class_year']; ?></span>
                                                </div>
                                            </div>
                                        <?php elseif ($user_type == 'teacher'): ?>
                                            <div class="profile-data-item">
                                                <div class="data-icon">
                                                    <i class="fas fa-briefcase"></i>
                                                </div>
                                                <div class="data-content">
                                                    <span class="data-label"><?php echo isset($lang['hire_date']) ? $lang['hire_date'] : 'Hire Date'; ?></span>
                                                    <span class="data-value"><?php echo date('d M Y', strtotime($user['hire_date'])); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="profile-data-item">
                                            <div class="data-icon">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="data-content">
                                                <span class="data-label"><?php echo isset($lang['reg_date']) ? $lang['reg_date'] : 'Registration Date'; ?></span>
                                                <span class="data-value"><?php echo date('d M Y', strtotime($user['reg_date'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Update Profile Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-edit"></i> <?php echo isset($lang['update_profile']) ? $lang['update_profile'] : 'Update Profile'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <form method="post" class="profile-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email"><?php echo isset($lang['email_label']) ? $lang['email_label'] : 'Email'; ?></label>
                                    <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="department"><?php echo isset($lang['department']) ? $lang['department'] : 'Department'; ?></label>
                                    <input type="text" id="department" name="department" value="<?php echo $user['department']; ?>" required>
                                </div>
                                
                                <?php if ($user_type == 'teacher'): ?>
                                <div class="form-group">
                                    <label for="position"><?php echo isset($lang['position']) ? $lang['position'] : 'Position'; ?></label>
                                    <input type="text" id="position" name="position" value="<?php echo $user['position']; ?>" required>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn primary">
                                    <i class="fas fa-save"></i> <?php echo isset($lang['save_changes']) ? $lang['save_changes'] : 'Save Changes'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fas fa-key"></i> <?php echo isset($lang['change_password']) ? $lang['change_password'] : 'Change Password'; ?></h3>
                    </div>
                    <div class="panel-card-body">
                        <form method="post" class="password-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="current_password"><?php echo isset($lang['current_password']) ? $lang['current_password'] : 'Current Password'; ?></label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password"><?php echo isset($lang['new_password']) ? $lang['new_password'] : 'New Password'; ?></label>
                                    <input type="password" id="new_password" name="new_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password"><?php echo isset($lang['confirm_password_label']) ? $lang['confirm_password_label'] : 'Confirm Password'; ?></label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn primary">
                                    <i class="fas fa-key"></i> <?php echo isset($lang['change_password']) ? $lang['change_password'] : 'Change Password'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* User Panel - Matching Operations Panel Design */
.user-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.user-panel-header h2 {
    margin: 0 0 20px 0;
    font-size: 24px;
    font-weight: 500;
    color: var(--text-primary);
    display: flex;
    align-items: center;
}

.user-panel-header h2 i {
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
    font-size: 18px;
}

.panel-card-body {
    padding: 20px;
}

/* Improved Profile Styling */
.profile-container {
    padding: 20px;
}

.profile-main {
    display: flex;
    gap: 20px;
    background-color: #f8fafc;
    border-radius: 10px;
    padding: 20px;
}

.profile-avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background-color: white;
    color: var(--primary-color);
    border: 3px solid rgba(var(--primary-color-rgb), 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
}

.profile-badges {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: center;
}

.profile-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-align: center;
    white-space: nowrap;
}

.primary-badge {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
}

.department-badge {
    background-color: rgba(46, 125, 50, 0.1);
    color: #2e7d32;
}

.position-badge {
    background-color: rgba(245, 124, 0, 0.1);
    color: #f57c00;
}

.profile-info-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.profile-name h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: var(--text-primary);
    position: relative;
    padding-bottom: 10px;
}

.profile-name h2:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: linear-gradient(to right, var(--primary-color), #5c6bc0);
    border-radius: 3px;
}

.profile-data {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.profile-data-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px;
    border-radius: 8px;
    background-color: white;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.profile-data-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
}

.data-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.data-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.data-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 2px;
}

.data-value {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Form Styling */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
}

.form-group input {
    padding: 10px 12px;
    border: 1px solid #dde1e7;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.1);
    outline: none;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
}

.btn {
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    transition: all 0.2s;
}

.btn.primary {
    background-color: var(--primary-color);
    color: white;
}

.btn.primary:hover {
    background-color: var(--primary-dark);
    transform: translateY(-2px);
}

.btn.outline {
    background-color: transparent;
    border: 1px solid #dde1e7;
    color: var(--text-secondary);
}

.btn.outline:hover {
    background-color: #f1f5f9;
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .profile-details {
        grid-template-columns: 1fr;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
        align-items: center;
    }
    
    .profile-subtitle {
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordForm = document.querySelector('.password-form');
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                e.preventDefault();
                alert('<?php echo isset($lang['passwords_dont_match']) ? $lang['passwords_dont_match'] : 'Passwords do not match'; ?>');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
