<?php
session_start();

// Language selection
if (isset($_GET['lang']) && ($_GET['lang'] == 'en' || $_GET['lang'] == 'tr')) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    // Default language
    $_SESSION['lang'] = 'en';
}

// Include language file
include_once 'lang/' . $_SESSION['lang'] . '.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once 'db_config.php';
$error = '';

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Server-side validation
    if (empty($_POST['login_id']) || empty($_POST['password']) || empty($_POST['user_type'])) {
        $error = empty($_POST['login_id']) ? $lang['login_id_required'] : 
                (empty($_POST['password']) ? $lang['password_required'] : $lang['user_type_required']);
    } else {
        $login_id = $conn->real_escape_string($_POST['login_id']);
        $password = hash('sha256', $_POST['password']); // SHA-256 for password hashing
        $user_type = $conn->real_escape_string($_POST['user_type']);
        
        // Check login based on ID number only
        if ($user_type === 'student') {
            // Student ID format: YYYYS0001
            $sql = "SELECT id, username, 'student' as user_type, name FROM students 
                    WHERE student_number = '$login_id' AND password = '$password'";
        } else if ($user_type === 'teacher') {
            // Teacher ID format: YYYYT0001
            $sql = "SELECT id, username, 'teacher' as user_type, name FROM teachers 
                    WHERE teacher_number = '$login_id' AND password = '$password'";
        } else {
            $error = $lang['invalid_user_type'];
            $sql = "";
        }
        
        if (!empty($sql)) {
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['name'] = $user['name'];
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error = $lang['invalid_credentials'];
            }
        }
    }
}

// Get the URL for switching language
$current_page = $_SERVER['PHP_SELF'];
$query_parts = [];

if (isset($_GET)) {
    foreach ($_GET as $key => $value) {
        if ($key !== 'lang') {
            $query_parts[] = "$key=" . urlencode($value);
        }
    }
}

$query_string = implode('&', $query_parts);
$query_prefix = !empty($query_string) ? "?$query_string&" : "?";
$switch_lang_url = $current_page . $query_prefix . "lang=" . ($_SESSION['lang'] == 'en' ? 'tr' : 'en');

// If language_label key is missing, provide a default value
$language_label = isset($lang['language_label']) ? $lang['language_label'] : 'Language:';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBS - <?php echo $lang['app_name']; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="images/favicon.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="auth-page">
    <div class="language-switch-container">
        <a href="<?php echo $switch_lang_url; ?>" class="language-switch-btn">
            <div class="language-icon"><i class="fas fa-globe"></i></div>
            <span><?php echo isset($lang['switch_language']) ? $lang['switch_language'] : ($_SESSION['lang'] == 'en' ? 'TR' : 'EN'); ?></span>
        </a>
    </div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1><?php echo $lang['app_name']; ?></h1>
                <p class="auth-subtitle"><?php echo $lang['login_title']; ?></p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="auth-form">
                <div class="form-group">
                    <label for="login_id"><?php echo isset($lang['id_number_label']) ? $lang['id_number_label'] : 'ID Number'; ?></label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" id="login_id" name="login_id" placeholder="<?php echo isset($lang['id_number_placeholder']) ? $lang['id_number_placeholder'] : 'Enter your ID number'; ?>">
                    </div>
                    <small class="error-text" id="login-id-error"></small>
                </div>
                
                <div class="form-group">
                    <label for="password"><?php echo $lang['password_label']; ?></label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="<?php echo $lang['password_placeholder']; ?>">
                    </div>
                    <small class="error-text" id="password-error"></small>
                </div>
                
                <div class="form-group">
                    <label for="user_type"><?php echo $lang['login_as_label']; ?></label>
                    <div class="input-with-icon">
                        <i class="fas fa-user-circle"></i>
                        <select id="user_type" name="user_type" style="appearance: menulist; -webkit-appearance: menulist; -moz-appearance: menulist; background-image: none; padding-right: 10px;">
                            <option value=""><?php echo isset($lang['select_user_type']) ? $lang['select_user_type'] : 'Select user type'; ?></option>
                            <option value="student"><?php echo $lang['student_option']; ?></option>
                            <option value="teacher"><?php echo $lang['teacher_option']; ?></option>
                        </select>
                    </div>
                    <small class="error-text" id="user-type-error"></small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> 
                    <?php echo $lang['login_button']; ?>
                </button>
            </form>
            
            <div class="auth-links">
                <p><?php echo $lang['no_account_text']; ?> <a href="register.php" class="text-primary"><?php echo $lang['register_link']; ?></a></p>
            </div>
        </div>
    </div>

    <footer class="auth-footer">
        <p>&copy; <?php echo date("Y"); ?> <?php echo $lang['footer_text']; ?></p>
    </footer>
    
    <style>
    /* Auth Page Styles - Clean Design Without Card */
    .auth-page {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background: linear-gradient(135deg, #f0f7ff, #e6effe);
        font-family: 'Roboto', sans-serif;
    }
    
    .auth-container {
        display: flex;
        justify-content: center;
        align-items: center;
        flex: 1;
        padding: 40px 20px;
    }
    
    .auth-card {
        width: 100%;
        max-width: 500px;
        background: transparent;
        position: relative;
    }
    
    .auth-header {
        text-align: center;
        padding: 0 0 30px;
        background: transparent;
        position: relative;
    }
    
    .auth-logo {
        width: 90px;
        height: 90px;
        background: white;
        color: #4569d4;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        margin: 0 auto 20px;
        box-shadow: 0 10px 25px rgba(69, 105, 212, 0.2);
    }
    
    .auth-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 10px;
        text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
    }
    
    .auth-subtitle {
        font-size: 16px;
        color: #4a5568;
        margin: 0;
        text-shadow: 0 1px 1px rgba(255, 255, 255, 0.6);
    }
    
    .auth-form {
        background-color: white;
        border-radius: 16px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        padding: 40px;
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-group label {
        display: block;
        font-size: 15px;
        font-weight: 500;
        margin-bottom: 10px;
        color: #4a5568;
    }
    
    .input-with-icon {
        position: relative;
    }
    
    .input-with-icon i {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #a0aec0;
        font-size: 18px;
        transition: all 0.3s ease;
    }
    
    .input-with-icon input {
        width: 100%;
        padding: 15px 15px 15px 50px;
        font-size: 15px;
        line-height: 1.4;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        background-color: #f8fafc;
        color: #4a5568;
        /* Remove custom appearance properties that might be causing issues */
        appearance: menulist;
        -webkit-appearance: menulist;
        -moz-appearance: menulist;
        font-family: inherit;
        cursor: pointer;
    }
    
    /* Custom select styling to ensure dropdown works correctly */
    .input-with-icon select {
        width: 100%;
        padding: 6px 28px 8px 50px;
        font-size: 15px;
        line-height: 1.4;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        background-color: #f8fafc;
        color: #4a5568;
        font-family: inherit;
        cursor: pointer;
        
        /* Remove any custom appearance properties */
        -webkit-appearance: menulist !important;
        -moz-appearance: menulist !important;
        appearance: menulist !important;
        background-image: none !important;
    }
    
    /* Adjust icon position for select element */
    .input-with-icon select + i {
        top: 43%;
    }
    
    .input-with-icon input:hover,
    .input-with-icon select:hover {
        border-color: #cbd5e0;
        background-color: #fff;
    }
    
    .input-with-icon input:focus,
    .input-with-icon select:focus {
        outline: none;
        border-color: #4569d4;
        background-color: #fff;
        box-shadow: 0 0 0 3px rgba(69, 105, 212, 0.15);
    }
    
    .input-with-icon input:focus + i,
    .input-with-icon select:focus + i {
        color: #4569d4;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 52px;
        padding: 0 30px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        font-family: inherit;
        letter-spacing: 0.5px;
    }
    
    .btn-primary {
        background: linear-gradient(to right, #4569d4, #6b8cff);
        color: white;
        box-shadow: 0 10px 20px rgba(69, 105, 212, 0.25);
    }
    
    .btn-primary:hover {
        box-shadow: 0 15px 25px rgba(69, 105, 212, 0.35);
        transform: translateY(-3px);
    }
    
    .btn-block {
        width: 100%;
        margin-top: 15px;
    }
    
    .btn i {
        margin-right: 10px;
        font-size: 18px;
    }
    
    .auth-links {
        text-align: center;
        font-size: 15px;
        color: #4a5568;
        background-color: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    }
    
    .auth-links a {
        color: #4569d4;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }
    
    .auth-links a:hover {
        color: #2d46a8;
        text-decoration: underline;
    }
    
    .auth-footer {
        text-align: center;
        padding: 25px;
        font-size: 14px;
        color: #4a5568;
        text-shadow: 0 1px 1px rgba(255, 255, 255, 0.6);
    }
    
    /* Language switch styling */
    .language-switch-container {
        position: absolute;
        top: 25px;
        right: 25px;
        z-index: 10;
    }
    
    .language-switch-btn {
        display: flex;
        align-items: center;
        padding: 8px 18px;
        background-color: white;
        border-radius: 30px;
        color: #4569d4;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    
    .language-switch-btn:hover {
        background-color: #4569d4;
        color: white;
        transform: translateY(-2px);
    }
    
    .language-switch-btn:hover .language-icon {
        background-color: rgba(255, 255, 255, 0.2);
    }
    
    .language-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 8px;
        width: 24px;
        height: 24px;
        background-color: rgba(69, 105, 212, 0.1);
        border-radius: 50%;
        font-size: 12px;
        transition: all 0.3s;
    }
    
    /* Error message styling */
    .message {
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        font-size: 15px;
        line-height: 1.5;
        background-color: #fff;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .message i {
        margin-right: 15px;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .message.error {
        border-left: 4px solid #e53e3e;
        color: #e53e3e;
    }
    
    .message.success {
        border-left: 4px solid #38a169;
        color: #38a169;
    }
    
    .error-text {
        color: #e53e3e;
        font-size: 13px;
        margin-top: 8px;
        display: block;
    }
    
    /* Animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .auth-header, .auth-form, .auth-links {
        animation: fadeInUp 0.6s ease-out forwards;
    }
    
    .auth-form { animation-delay: 0.1s; }
    .auth-links { animation-delay: 0.2s; }
    
    /* Responsive adjustments */
    @media (max-width: 650px) {
        .auth-container {
            padding: 30px 15px;
        }
        
        .auth-form {
            padding: 30px 25px;
        }
        
        .auth-logo {
            width: 80px;
            height: 80px;
            font-size: 32px;
        }
        
        .language-switch-container {
            top: 15px;
            right: 15px;
        }
        
        .language-switch-btn {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .language-icon {
            width: 20px;
            height: 20px;
            font-size: 10px;
        }
    }
    </style>
    
    <script src="js/validation.js" defer></script>
    <script>
        // Pass language strings to JS for validation messages
        const langStrings = {
            id_number_required: '<?php echo addslashes(isset($lang["id_number_required"]) ? $lang["id_number_required"] : "ID number is required"); ?>',
            password_required: '<?php echo addslashes($lang["password_required"]); ?>',
            user_type_required: '<?php echo addslashes($lang["user_type_required"]); ?>'
        };
        
        // Enhanced fix for select dropdown visibility issues
        document.addEventListener('DOMContentLoaded', function() {
            const userTypeSelect = document.getElementById('user_type');
            if (userTypeSelect) {
                // Force browser's default select styling
                userTypeSelect.style.appearance = 'menulist';
                userTypeSelect.style.webkitAppearance = 'menulist';
                userTypeSelect.style.mozAppearance = 'menulist';
                
                // Additional fixes for potential styling conflicts
                userTypeSelect.style.backgroundImage = 'none';
                userTypeSelect.style.paddingRight = '10px';
            }
        });
    </script>
</body>
</html>
