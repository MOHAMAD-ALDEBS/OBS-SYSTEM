<?php
// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Get current file name to determine active menu item
$current_file = basename($_SERVER['PHP_SELF']);
$user_type = $_SESSION['user_type'];

// Define operations based on user type
if ($user_type == 'student') {
    // Operations for students
    $operations = [
        [
            'title' => isset($lang['my_courses']) ? $lang['my_courses'] : 'My Courses',
            'icon' => 'fas fa-book',
            'url' => 'courses.php',
            'class' => $current_file == 'courses.php' ? 'active academic' : 'academic',
        ],
        [
            'title' => isset($lang['request_course']) ? $lang['request_course'] : 'Request Courses',
            'icon' => 'fas fa-plus-circle',
            'url' => 'request_course.php',
            'class' => $current_file == 'request_course.php' ? 'active academic' : 'academic',
        ],
        [
            'title' => isset($lang['grades']) ? $lang['grades'] : 'Grades',
            'icon' => 'fas fa-chart-bar',
            'url' => 'grades.php',
            'class' => $current_file == 'grades.php' ? 'active academic' : 'academic',
        ],
        [
            'title' => isset($lang['announcements']) ? $lang['announcements'] : 'Announcements',
            'icon' => 'fas fa-bullhorn',
            'url' => 'announcements.php',
            'class' => $current_file == 'announcements.php' ? 'active academic' : 'academic',
        ],
        [
            'title' => isset($lang['profile']) ? $lang['profile'] : 'My Profile',
            'icon' => 'fas fa-user',
            'url' => 'profile.php',
            'class' => $current_file == 'profile.php' ? 'active account' : 'account',
        ],
        [
            'title' => isset($lang['logout']) ? $lang['logout'] : 'Logout',
            'icon' => 'fas fa-sign-out-alt',
            'url' => 'logout.php',
            'class' => 'account',
        ]
    ];
} elseif ($user_type == 'teacher') {
    // Operations for teachers
    $operations = [
        [
            'title' => isset($lang['my_courses']) ? $lang['my_courses'] : 'My Courses',
            'icon' => 'fas fa-chalkboard-teacher',
            'url' => 'teacher_courses.php',
            'class' => $current_file == 'teacher_courses.php' ? 'active teaching' : 'teaching',
        ],
        [
            'title' => isset($lang['students']) ? $lang['students'] : 'Students',
            'icon' => 'fas fa-user-graduate',
            'url' => 'students_list.php',
            'class' => $current_file == 'students_list.php' ? 'active teaching' : 'teaching',
        ],
        [
            'title' => isset($lang['grades']) ? $lang['grades'] : 'Grades',
            'icon' => 'fas fa-chart-line',
            'url' => 'teacher_grades.php',
            'class' => $current_file == 'teacher_grades.php' ? 'active teaching' : 'teaching',
        ],
        [
            'title' => isset($lang['announcements']) ? $lang['announcements'] : 'Announcements',
            'icon' => 'fas fa-bullhorn',
            'url' => 'announcements.php',
            'class' => $current_file == 'announcements.php' ? 'active teaching' : 'teaching',
        ],
        [
            'title' => isset($lang['manage_requests']) ? $lang['manage_requests'] : 'Course Requests',
            'icon' => 'fas fa-clipboard-list',
            'url' => 'manage_course_requests.php',
            'class' => $current_file == 'manage_course_requests.php' ? 'active teaching' : 'teaching',
        ],
        [
            'title' => isset($lang['profile']) ? $lang['profile'] : 'My Profile',
            'icon' => 'fas fa-user',
            'url' => 'profile.php',
            'class' => $current_file == 'profile.php' ? 'active account' : 'account',
        ],
        [
            'title' => isset($lang['logout']) ? $lang['logout'] : 'Logout',
            'icon' => 'fas fa-sign-out-alt',
            'url' => 'logout.php',
            'class' => 'account',
        ]
    ];
} elseif ($user_type == 'admin') {
    // Operations for admins
    $operations = [
        [
            'title' => 'Manage Users',
            'icon' => 'fas fa-users-cog',
            'url' => 'admin_users.php',
            'class' => $current_file == 'admin_users.php' ? 'active admin' : 'admin',
        ],
        [
            'title' => 'Manage Courses',
            'icon' => 'fas fa-book',
            'url' => 'admin_courses.php',
            'class' => $current_file == 'admin_courses.php' ? 'active admin' : 'admin',
        ],
        [
            'title' => 'System Settings',
            'icon' => 'fas fa-cogs',
            'url' => 'admin_settings.php',
            'class' => $current_file == 'admin_settings.php' ? 'active admin' : 'admin',
        ],
        [
            'title' => isset($lang['logout']) ? $lang['logout'] : 'Logout',
            'icon' => 'fas fa-sign-out-alt',
            'url' => 'logout.php',
            'class' => 'account',
        ]
    ];
}

// Display operations with enhanced styling
echo '<div class="operations">';

// Add a header for the operations menu
echo '<div class="operations-header">';
echo '<h4>' . (isset($lang['quick_actions']) ? $lang['quick_actions'] : 'Quick Actions') . '</h4>';
echo '</div>';

if (!empty($operations)) {
    foreach ($operations as $op) {
        echo '<a href="' . $op['url'] . '" class="operation-card ' . $op['class'] . '">';
        echo '<div class="operation-icon"><i class="' . $op['icon'] . '"></i></div>';
        echo '<div class="operation-info">';
        echo '<div class="operation-title">' . $op['title'] . '</div>';
        
        // Add description for primary operations based on user type
        if ($user_type == 'student' && $op['url'] == 'courses.php') {
            echo '<div class="operation-desc">' . (isset($lang['view_enrolled_courses']) ? $lang['view_enrolled_courses'] : 'View your enrolled courses') . '</div>';
        } elseif ($user_type == 'teacher' && $op['url'] == 'teacher_courses.php') {
            echo '<div class="operation-desc">' . (isset($lang['manage_your_courses']) ? $lang['manage_your_courses'] : 'Manage your teaching courses') . '</div>';
        } elseif ($op['url'] == 'logout.php') {
            echo '<div class="operation-desc">' . (isset($lang['sign_out_system']) ? $lang['sign_out_system'] : 'Sign out of the system') . '</div>';
        }
        
        echo '</div>';
        echo '</a>';
    }
}
echo '</div>';
?>

<style>
/* Enhanced Operations Menu Styling */
.operations {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    padding-bottom: 10px;
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.3s ease;
    margin-bottom: 20px;
}

.operations:hover {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
}

.operations-header {
    padding: 15px 20px;
    background: linear-gradient(to right, #3949ab, #5c6bc0);
    color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.operations-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    letter-spacing: 0.3px;
}

.operation-card {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    margin: 5px 8px;
    border-radius: 6px;
}

.operation-card:hover {
    background-color: rgba(0, 0, 0, 0.03);
    transform: translateX(3px);
}

.operation-card.active {
    border-left-color: var(--primary-color);
    background-color: rgba(var(--primary-color-rgb), 0.04);
}

.operation-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.operation-card:hover .operation-icon {
    transform: scale(1.1) rotate(5deg);
}

.operation-info {
    flex-grow: 1;
}

.operation-title {
    font-weight: 500;
    font-size: 14px;
    margin-bottom: 2px;
}

.operation-desc {
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.3;
}

/* User type specific styles */
.operation-card.academic .operation-icon {
    background-color: rgba(77, 171, 247, 0.1);
    color: #4dabf7;
}

.operation-card.academic.active .operation-icon {
    background-color: rgba(77, 171, 247, 0.2);
}

.operation-card.teaching .operation-icon {
    background-color: rgba(94, 53, 177, 0.1);
    color: #5e35b1;
}

.operation-card.teaching.active .operation-icon {
    background-color: rgba(94, 53, 177, 0.2);
}

.operation-card.admin .operation-icon {
    background-color: rgba(230, 81, 0, 0.1);
    color: #e65100;
}

.operation-card.admin.active .operation-icon {
    background-color: rgba(230, 81, 0, 0.2);
}

.operation-card.account .operation-icon {
    background-color: rgba(121, 134, 203, 0.1);
    color: #7986cb;
}

.operation-card.account.active .operation-icon {
    background-color: rgba(121, 134, 203, 0.2);
}

/* Responsive behavior */
@media (max-width: 768px) {
    .operations {
        margin-bottom: 15px;
    }
    
    .operation-card {
        padding: 10px 12px;
        margin: 3px 5px;
    }
    
    .operation-icon {
        width: 32px;
        height: 32px;
    }
}

/* Fade in animation for operations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.operation-card {
    animation: fadeInUp 0.3s ease forwards;
    opacity: 0;
}

.operation-card:nth-child(2) { animation-delay: 0.05s; }
.operation-card:nth-child(3) { animation-delay: 0.1s; }
.operation-card:nth-child(4) { animation-delay: 0.15s; }
.operation-card:nth-child(5) { animation-delay: 0.2s; }
.operation-card:nth-child(6) { animation-delay: 0.25s; }
.operation-card:nth-child(7) { animation-delay: 0.3s; }
</style>
