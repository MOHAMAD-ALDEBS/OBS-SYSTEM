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

require_once 'db_config.php';

$error = '';
$success = '';
$registration_success = ''; // Initialize the variable for success message
$registered_id = ''; // Variable to store the registered ID

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Debug output
    error_log("POST data received: " . print_r($_POST, true));
    
    if (empty($_POST['name']) || empty($_POST['username']) || empty($_POST['email']) || 
        empty($_POST['password']) || empty($_POST['confirm_password']) || empty($_POST['user_type'])) {
        $error = $lang['all_fields_required'];
        error_log("Form validation failed: missing fields");
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error = $lang['passwords_dont_match'];
        error_log("Form validation failed: passwords don't match");
    } else {
        $name = $conn->real_escape_string($_POST['name']);
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = hash('sha256', $_POST['password']);
        $user_type = $_POST['user_type'];
        $current_date = date('Y-m-d');
        $current_year = date('Y');
        
        // Check username in all tables
        $username_exists = false;
        
        // Check in students
        $check_sql = "SELECT id FROM students WHERE username = '$username'";
        $check_result = $conn->query($check_sql);
        if ($check_result && $check_result->num_rows > 0) {
            $username_exists = true;
        }
        
        // Check in teachers
        if (!$username_exists) {
            $check_sql = "SELECT id FROM teachers WHERE username = '$username'";
            $check_result = $conn->query($check_sql);
            if ($check_result && $check_result->num_rows > 0) {
                $username_exists = true;
            }
        }
        
        // Check in admins
        if (!$username_exists) {
            $check_sql = "SELECT id FROM admins WHERE username = '$username'";
            $check_result = $conn->query($check_sql);
            if ($check_result && $check_result->num_rows > 0) {
                $username_exists = true;
            }
        }
        
        if ($username_exists) {
            $error = $lang['username_exists'];
            error_log("Registration failed: username exists");
        } else {
            // Check email in all tables
            $email_exists = false;
            
            // Check in students
            $check_sql = "SELECT id FROM students WHERE email = '$email'";
            $check_result = $conn->query($check_sql);
            if ($check_result && $check_result->num_rows > 0) {
                $email_exists = true;
            }
            
            // Check in teachers
            if (!$email_exists) {
                $check_sql = "SELECT id FROM teachers WHERE email = '$email'";
                $check_result = $conn->query($check_sql);
                if ($check_result && $check_result->num_rows > 0) {
                    $email_exists = true;
                }
            }
            
            // Check in admins
            if (!$email_exists) {
                $check_sql = "SELECT id FROM admins WHERE email = '$email'";
                $check_result = $conn->query($check_sql);
                if ($check_result && $check_result->num_rows > 0) {
                    $email_exists = true;
                }
            }
            
            if ($email_exists) {
                $error = $lang['email_exists'];
                error_log("Registration failed: email exists");
            } else {
                if ($user_type === 'student') {
                    // Generate student number
                    $student_number = $current_year . 'S' . sprintf('%04d', 1);
                    
                    // Check if number exists and get the next one
                    $check_sql = "SELECT student_number FROM students WHERE student_number LIKE '$current_year" . "S%' ORDER BY student_number DESC LIMIT 1";
                    $check_result = $conn->query($check_sql);
                    
                    if ($check_result && $check_result->num_rows > 0) {
                        $row = $check_result->fetch_assoc();
                        $last_number = intval(substr($row['student_number'], 5));
                        $student_number = $current_year . 'S' . sprintf('%04d', $last_number + 1);
                    }
                    
                    // Assign random class year (1-4)
                    $class_year = rand(1, 4);
                    
                    // Insert new student
                    $sql = "INSERT INTO students (student_number, username, password, email, name, class_year, admission_date) 
                            VALUES ('$student_number', '$username', '$password', '$email', '$name', $class_year, '$current_date')";
                    
                    error_log("Executing SQL: " . $sql);
                    
                    if ($conn->query($sql)) {
                        $registered_id = $student_number; // Store the ID
                        $registration_success = sprintf($lang['student_registration_success'], $student_number);
                        error_log("Student registration successful: $student_number");
                    } else {
                        $error = "Database Error: " . $conn->error;
                        error_log("Student registration failed SQL error: " . $conn->error);
                    }
                } else {
                    // Generate teacher number
                    $teacher_number = $current_year . 'T' . sprintf('%04d', 1);
                    
                    // Check if number exists and get the next one
                    $check_sql = "SELECT teacher_number FROM teachers WHERE teacher_number LIKE '$current_year" . "T%' ORDER BY teacher_number DESC LIMIT 1";
                    $check_result = $conn->query($check_sql);
                    
                    if ($check_result && $check_result->num_rows > 0) {
                        $row = $check_result->fetch_assoc();
                        $last_number = intval(substr($row['teacher_number'], 5));
                        $teacher_number = $current_year . 'T' . sprintf('%04d', $last_number + 1);
                    }
                    
                    // Insert new teacher
                    $sql = "INSERT INTO teachers (teacher_number, username, password, email, name, hire_date) 
                            VALUES ('$teacher_number', '$username', '$password', '$email', '$name', '$current_date')";
                    
                    error_log("Executing SQL: " . $sql);
                    
                    if ($conn->query($sql)) {
                        $registered_id = $teacher_number; // Store the ID
                        $registration_success = sprintf($lang['teacher_registration_success'], $teacher_number);
                        error_log("Teacher registration successful: $teacher_number");
                    } else {
                        $error = "Database Error: " . $conn->error;
                        error_log("Teacher registration failed SQL error: " . $conn->error);
                    }
                }
            }
        }
    }
}

// Function to generate student number
function generateStudentNumber($conn, $year) {
    // Try to find the maximum student number for the current year
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(student_number, 6) AS UNSIGNED)) as max_num FROM students WHERE student_number LIKE '$year"."S%'");
    $row = $result->fetch_assoc();
    
    $next_num = 1;  // Default to 1 if no existing numbers
    if ($row && isset($row['max_num']) && $row['max_num'] !== null) {
        $next_num = intval($row['max_num']) + 1;
    }
    
    return $year . "S" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

// Function to generate teacher number
function generateTeacherNumber($conn, $year) {
    // Try to find the maximum teacher number for the current year
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(teacher_number, 6) AS UNSIGNED)) as max_num FROM teachers WHERE teacher_number LIKE '$year"."T%'");
    $row = $result->fetch_assoc();
    
    $next_num = 1;  // Default to 1 if no existing numbers
    if ($row && isset($row['max_num']) && $row['max_num'] !== null) {
        $next_num = intval($row['max_num']) + 1;
    }
    
    return $year . "T" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

// Get the URL for switching language
$current_page = $_SERVER['PHP_SELF'];
$current_query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
$current_query = preg_replace('/(?:^|&)lang=[^&]*/', '', $current_query);
$current_query = trim($current_query, '&');

if ($_SESSION['lang'] == 'en') {
    $switch_lang_url = $current_page . ($current_query ? "?{$current_query}&lang=tr" : "?lang=tr");
} else {
    $switch_lang_url = $current_page . ($current_query ? "?{$current_query}&lang=en" : "?lang=en");
}

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
        <div class="auth-card register-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1><?php echo $lang['app_name']; ?></h1>
                <p class="auth-subtitle"><?php echo $lang['register_title']; ?></p>
            </div>
            
            <?php if (!empty($registration_success)): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $registration_success; ?>
                </div>
                
                <div class="id-info-box">
                    <div class="id-display">
                        <h3><?php echo isset($lang['your_id_number']) ? $lang['your_id_number'] : 'Your ID Number'; ?>:</h3>
                        <div class="id-number"><?php echo htmlspecialchars($registered_id); ?></div>
                        <p class="id-note"><?php echo isset($lang['save_id_note']) ? $lang['save_id_note'] : 'Please save this number. You will need it to log in.'; ?></p>
                    </div>
                </div>
                
                <div class="auth-links">
                    <a href="index.php" class="btn btn-primary btn-block btn-success-login">
                        <i class="fas fa-sign-in-alt"></i> <?php echo isset($lang['go_to_login']) ? $lang['go_to_login'] : 'Go to Login'; ?>
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form id="registerForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="auth-form">
                    <h4 class="form-section-title"><?php echo isset($lang['account_details']) ? $lang['account_details'] : 'Account Details'; ?></h4>
                    
                    <div class="form-group">
                        <label for="name"><?php echo $lang['name_label']; ?></label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" placeholder="<?php echo $lang['name_placeholder']; ?>">
                        </div>
                        <small class="error-text" id="name-error"></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="username"><?php echo $lang['username_label']; ?></label>
                        <div class="input-with-icon">
                            <i class="fas fa-at"></i>
                            <input type="text" id="username" name="username" value="" placeholder="<?php echo $lang['username_placeholder']; ?>">
                        </div>
                        <small class="error-text" id="username-error"></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><?php echo $lang['email_label']; ?></label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="<?php echo $lang['email_placeholder']; ?>">
                        </div>
                        <small class="error-text" id="email-error"></small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password"><?php echo $lang['password_label']; ?></label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" placeholder="<?php echo $lang['password_placeholder']; ?>">
                            </div>
                            <small class="error-text" id="password-error"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><?php echo $lang['confirm_password_label']; ?></label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="<?php echo $lang['confirm_password_placeholder']; ?>">
                            </div>
                            <small class="error-text" id="confirm-password-error"></small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_type"><?php echo $lang['user_type_label']; ?></label>
                        <div class="input-with-icon">
                            <i class="fas fa-user-circle"></i>
                            <select id="user_type" name="user_type">
                                <option value="student" <?php echo isset($user_type) && $user_type === 'student' ? 'selected' : ''; ?>><?php echo $lang['student_option']; ?></option>
                                <option value="teacher" <?php echo isset($user_type) && $user_type === 'teacher' ? 'selected' : ''; ?>><?php echo $lang['teacher_option']; ?></option>
                            </select>
                        </div>
                        <small class="error-text" id="user-type-error"></small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> 
                        <?php echo $lang['register_button']; ?>
                    </button>
                </form>
                
                <div class="auth-links">
                    <p><?php echo $lang['have_account_text']; ?> <a href="index.php" class="text-primary signin-link"><?php echo $lang['signin_link']; ?></a></p>
                </div>
            <?php endif; ?>
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
        padding: 30px 20px;
    }

    .auth-card {
        width: 100%;
        max-width: 600px;
        background: transparent;
        position: relative;
    }

    .register-card {
        max-width: 600px;
    }

    .auth-header {
        text-align: center;
        padding: 0 0 25px;
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
        padding: 30px;
        margin-bottom: 20px;
    }

    .form-section-title {
        font-size: 17px;
        font-weight: 600;
        color: #2d3748;
        margin: 0 0 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #edf2f7;
        position: relative;
    }

    .form-section-title:after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -1px;
        width: 50px;
        height: 3px;
        background: linear-gradient(to right, #4569d4, #6b8cff);
        border-radius: 3px;
    }

    .form-row {
        display: flex;
        gap: 15px;
        margin-bottom: 5px;
    }

    .form-row .form-group {
        flex: 1;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 8px;
        color: #4a5568;
    }

    .input-with-icon {
        position: relative;
    }

    .input-with-icon i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #a0aec0;
        font-size: 16px;
        transition: all 0.3s ease;
    }

    .input-with-icon input,
    .input-with-icon select {
        width: 100%;
        padding: 12px 12px 12px 40px;
        font-size: 14px;
        line-height: 1.4;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background-color: #f8fafc;
        color: #4a5568;
        transition: all 0.3s ease;
        font-family: inherit;
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

    .user-type-info {
        margin-bottom: 20px;
    }

    .info-box {
        display: none;
        padding: 12px 15px;
        background-color: #f8fafc;
        border-radius: 10px;
        margin-bottom: 15px;
        border-left: 3px solid #4569d4;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        align-items: center;
        opacity: 0;
        transform: translateY(10px);
    }

    .info-box.active {
        display: flex;
        opacity: 1;
        transform: translateY(0);
        animation: fadeIn 0.4s ease;
    }

    .info-box i {
        font-size: 20px;
        color: #4569d4;
        margin-right: 12px;
        flex-shrink: 0;
    }

    .info-box p {
        font-size: 13px;
        color: #64748b;
        margin: 0;
        line-height: 1.5;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 48px;
        padding: 0 25px;
        font-size: 15px;
        font-weight: 600;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        font-family: inherit;
    }

    .btn-primary {
        background: linear-gradient(to right, #4569d4, #6b8cff);
        color: white;
        box-shadow: 0 8px 15px rgba(69, 105, 212, 0.25);
    }

    .btn-primary:hover {
        box-shadow: 0 12px 22px rgba(69, 105, 212, 0.35);
        transform: translateY(-2px);
    }

    .btn-block {
        width: 100%;
        margin-top: 15px;
    }

    .btn i {
        margin-right: 8px;
        font-size: 16px;
    }

    .auth-links {
        text-align: center;
        font-size: 14px;
        color: #4a5568;
        background-color: white;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    }

    .auth-links a {
        color: #ffffff; /* Changed from #3f5495 to white */
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }
    
    /* Specific styling for Sign In link */
    .signin-link {
        color: #4569d4 !important; /* Blue color for Sign In link */
    }

    .auth-links a:hover {
        color: #2d46a8;
        text-decoration: underline;
    }

    .auth-footer {
        text-align: center;
        padding: 20px;
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

    /* Message styling */
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
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }

    /* Animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .auth-header, .auth-form, .auth-links {
        animation: fadeInUp 0.5s ease-out forwards;
    }

    .auth-form { animation-delay: 0.1s; }
    .auth-links { animation-delay: 0.2s; }

    /* Responsive adjustments */
    @media (max-width: 600px) {
        .form-row {
            flex-direction: column;
            gap: 0;
        }
        
        .auth-form {
            padding: 20px;
        }
        
        .language-switch-container {
            top: 15px;
            right: 15px;
        }
        
        .language-switch-btn {
            padding: 6px 12px;
            font-size: 13px;
        }
    }
    
    /* ID display styling */
    .id-info-box {
        background-color: #fff;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 20px;
        text-align: center;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        border-top: 4px solid #4569d4;
        animation: fadeInUp 0.5s ease-out forwards;
        animation-delay: 0.15s;
    }
    
    .id-display {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .id-display h3 {
        color: #2d3748;
        margin: 0 0 15px;
        font-size: 18px;
        font-weight: 600;
    }
    
    .id-number {
        font-size: 28px;
        font-weight: 700;
        color: #4569d4;
        padding: 15px 30px;
        background-color: #f0f7ff;
        border: 2px dashed #a3bffa;
        border-radius: 8px;
        letter-spacing: 1px;
        margin-bottom: 15px;
    }
    
    .id-note {
        font-size: 14px;
        color: #64748b;
        margin: 0;
        font-style: italic;
    }
    
    /* Success page login button with lighter text */
    .btn-success-login {
        color: #ffffff; /* Changed from rgba(255, 255, 255, 0.9) to pure white */
        font-weight: 500;
        letter-spacing: 0.5px;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    
    .btn-success-login:hover {
        color: #ffffff;
        font-weight: 600;
        background: linear-gradient(to right, #4976e6, #7898ff); /* Slightly lighter gradient on hover */
    }
    </style>
    
    <script src="js/validation.js" defer></script>
    <script>
        // Pass language strings to JS for validation messages
        const langStrings = {
            username_required: '<?php echo addslashes($lang["username_required"]); ?>',
            username_min_length: '<?php echo addslashes($lang["username_min_length"]); ?>',
            email_required: '<?php echo addslashes($lang["email_required"]); ?>',
            email_invalid: '<?php echo addslashes($lang["email_invalid"]); ?>',
            name_required: '<?php echo addslashes($lang["name_required"]); ?>',
            password_required: '<?php echo addslashes($lang["password_required"]); ?>',
            password_min_length: '<?php echo addslashes($lang["password_min_length"]); ?>',
            passwords_dont_match: '<?php echo addslashes($lang["passwords_dont_match"]); ?>',
            user_type_required: '<?php echo addslashes($lang["user_type_required"]); ?>'
        };
        
        // Toggle user type info boxes
        document.addEventListener('DOMContentLoaded', function() {
            const userTypeSelect = document.getElementById('user_type');
            const studentInfo = document.querySelector('.student-info');
            const teacherInfo = document.querySelector('.teacher-info');
            
            if (userTypeSelect && studentInfo && teacherInfo) {
                userTypeSelect.addEventListener('change', function() {
                    if (this.value === 'student') {
                        studentInfo.classList.add('active');
                        teacherInfo.classList.remove('active');
                    } else {
                        teacherInfo.classList.add('active');
                        studentInfo.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>
</html>
