<?php
// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: index.php");
    exit;
}

// Language selection
if (isset($_GET['lang']) && ($_GET['lang'] == 'en' || $_GET['lang'] == 'tr')) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    // Default language
    $_SESSION['lang'] = 'en';
}

// Include language file
include_once 'lang/' . $_SESSION['lang'] . '.php';

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get the URL for switching language
$current_page_url = $_SERVER['PHP_SELF'];
$current_query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
$current_query = preg_replace('/(?:^|&)lang=[^&]*/', '', $current_query);
$current_query = trim($current_query, '&');

if ($_SESSION['lang'] == 'en') {
    $switch_lang_url = $current_page_url . ($current_query ? "?{$current_query}&lang=tr" : "?lang=tr");
} else {
    $switch_lang_url = $current_page_url . ($current_query ? "?{$current_query}&lang=en" : "?lang=en");
}

// If language_label key is missing, provide a default value
$language_label = isset($lang['language_label']) ? $lang['language_label'] : 'Language:';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBS - <?php echo isset($lang['app_name']) ? $lang['app_name'] : "Student Information System"; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="images/favicon.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-container">
            <div class="header-left">
                <div class="logo">
                    <a href="dashboard.php">
                        <i class="fas fa-graduation-cap"></i>
                        <span>OBS</span>
                    </a>
                </div>
                <h1 class="site-title"><?php echo isset($lang['app_name']) ? $lang['app_name'] : "Student Information System"; ?></h1>
            </div>
            
            <div class="header-right">
                <div class="language-selector">
                    <span class="language-label"><?php echo $language_label; ?></span>
                    <a href="<?php echo $switch_lang_url; ?>" class="language-btn">
                        <?php echo isset($lang['switch_language']) ? $lang['switch_language'] : ($_SESSION['lang'] == 'en' ? 'TR' : 'EN'); ?>
                    </a>
                </div>
                
                <div class="user-menu">
                    <div class="user-info">
                        <span class="user-name"><?php echo $_SESSION['name']; ?></span>
                        <span class="user-type"><?php echo ucfirst($_SESSION['user_type']); ?></span>
                    </div>
                    <div class="user-avatar">
                        <i class="fas <?php echo $_SESSION['user_type'] == 'student' ? 'fa-user-graduate' : 'fa-user-tie'; ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <style>
/* Header with matching operations panel design */
.main-header {
    background: linear-gradient(to right, #3949ab, #5c6bc0);
    color: white;
    padding: 0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
    position: relative;
    z-index: 10;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.header-left {
    display: flex;
    align-items: center;
}

.logo {
    margin-right: 15px;
}

.logo a {
    display: flex;
    align-items: center;
    color: white;
    text-decoration: none;
    font-weight: 700;
    font-size: 24px;
}

.logo i {
    font-size: 28px;
    margin-right: 8px;
}

.site-title {
    font-size: 18px;
    font-weight: 500;
    margin: 0;
    letter-spacing: 0.3px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.language-selector {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.language-label {
    display: none;
}

.language-btn {
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background-color 0.2s;
}

.language-btn:hover {
    background-color: rgba(255, 255, 255, 0.3);
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 10px;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 25px;
    transition: background-color 0.2s;
    cursor: pointer;
}

.user-menu:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.user-info {
    display: flex;
    flex-direction: column;
    text-align: right;
}

.user-name {
    font-weight: 500;
    font-size: 14px;
    line-height: 1.2;
}

.user-type {
    font-size: 12px;
    opacity: 0.8;
}

.user-avatar {
    width: 36px;
    height: 36px;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

/* Dashboard layout - operations on the left side (original layout) */
.dashboard-container {
    display: flex;
    min-height: calc(100vh - 60px);
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.main-content {
    display: flex;
    flex: 1;
    width: 100%;
}

.operations-column {
    width: 280px;
    padding-right: 20px;
    flex-shrink: 0;
}

.content-column {
    flex: 1;
    padding-left: 20px;
    overflow-x: hidden;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .site-title {
        display: none;
    }
    
    .user-info {
        display: none;
    }
    
    .header-container {
        padding: 10px 15px;
    }
    
    .dashboard-container {
        flex-direction: column;
        padding: 10px;
    }
    
    .main-content {
        flex-direction: column;
    }
    
    .operations-column {
        width: 100%;
        padding-right: 0;
        margin-bottom: 20px;
    }
    
    .content-column {
        padding-left: 0;
    }
}

@media (max-width: 480px) {
    .logo span {
        display: none;
    }
}
</style>

    <div class="main-content-wrapper">
        <!-- Main content container -->
