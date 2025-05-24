<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = hash('sha256', $_POST['current_password']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Check if current password matches
    $check_sql = "SELECT id FROM users WHERE id = $user_id AND password = '$current_password'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows == 0) {
        $error = "Current password is incorrect";
    } else if ($new_password != $confirm_password) {
        $error = "New passwords do not match";
    } else if (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters";
    } else {
        // Hash and update the password
        $hashed_password = hash('sha256', $new_password);
        $update_sql = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
        
        if ($conn->query($update_sql) === TRUE) {
            $message = "Password changed successfully!";
        } else {
            $error = "Error changing password: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Student Information System</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/validation.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2>Change Password</h2>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="success-message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="profile-container">
                <div class="profile-card">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="password-form">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password">
                            <small class="error-text" id="current-password-error"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                            <small class="error-text" id="new-password-error"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                            <small class="error-text" id="confirm-password-error"></small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn primary">Change Password</button>
                            <a href="profile.php" class="btn secondary">Back to Profile</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.getElementById('password-form');
            
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    // Validate current password
                    const currentPassword = document.getElementById('current_password');
                    if (currentPassword.value === '') {
                        showError(currentPassword, 'Current password is required');
                        isValid = false;
                    } else {
                        clearError(currentPassword);
                    }
                    
                    // Validate new password
                    const newPassword = document.getElementById('new_password');
                    if (newPassword.value === '') {
                        showError(newPassword, 'New password is required');
                        isValid = false;
                    } else if (newPassword.value.length < 6) {
                        showError(newPassword, 'New password must be at least 6 characters');
                        isValid = false;
                    } else {
                        clearError(newPassword);
                    }
                    
                    // Validate confirm password
                    const confirmPassword = document.getElementById('confirm_password');
                    if (confirmPassword.value === '') {
                        showError(confirmPassword, 'Please confirm your new password');
                        isValid = false;
                    } else if (confirmPassword.value !== newPassword.value) {
                        showError(confirmPassword, 'Passwords do not match');
                        isValid = false;
                    } else {
                        clearError(confirmPassword);
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
                
                function showError(input, message) {
                    const formGroup = input.parentElement;
                    const errorElement = formGroup.querySelector('.error-text');
                    
                    input.classList.add('error');
                    errorElement.textContent = message;
                }
                
                function clearError(input) {
                    const formGroup = input.parentElement;
                    const errorElement = formGroup.querySelector('.error-text');
                    
                    input.classList.remove('error');
                    errorElement.textContent = '';
                }
            }
        });
    </script>
</body>
</html>
