<aside class="sidebar">
    <div class="user-info">
        <div class="avatar">
            <span class="user-initial"><?php echo substr($_SESSION['name'], 0, 1); ?></span>
        </div>
        <div class="user-details">
            <p class="user-name"><?php echo $_SESSION['name']; ?></p>
            <p class="user-role"><?php echo ucfirst($_SESSION['user_type']); ?></p>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <?php if ($_SESSION['user_type'] == 'student'): ?>
                <h4 class="sidebar-heading">Academic</h4>
                <li>
                    <a href="courses.php" class="<?php echo $current_page == 'courses.php' ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i> My Courses
                    </a>
                </li>
                <li>
                    <a href="grades.php" class="<?php echo $current_page == 'grades.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> Grades
                    </a>
                </li>
                <li>
                    <a href="schedule.php" class="<?php echo $current_page == 'schedule.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Schedule
                    </a>
                </li>
                
                <h4 class="sidebar-heading">Account</h4>
                <li>
                    <a href="profile.php" class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
            
            <?php elseif ($_SESSION['user_type'] == 'teacher'): ?>
                <h4 class="sidebar-heading">Teaching</h4>
                <li>
                    <a href="teaching.php" class="<?php echo $current_page == 'teaching.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i> My Courses
                    </a>
                </li>
                <li>
                    <a href="students.php" class="<?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                </li>
                <li>
                    <a href="grading.php" class="<?php echo $current_page == 'grading.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i> Grading
                    </a>
                </li>
                
                <h4 class="sidebar-heading">Account</h4>
                <li>
                    <a href="profile.php" class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
            
            <?php elseif ($_SESSION['user_type'] == 'admin'): ?>
                <h4 class="sidebar-heading">Administration</h4>
                <li>
                    <a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Users
                    </a>
                </li>
                <li>
                    <a href="courses_admin.php" class="<?php echo $current_page == 'courses_admin.php' ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i> Courses
                    </a>
                </li>
                <li>
                    <a href="departments.php" class="<?php echo $current_page == 'departments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i> Departments
                    </a>
                </li>
                
                <h4 class="sidebar-heading">System</h4>
                <li>
                    <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li>
                    <a href="logs.php" class="<?php echo $current_page == 'logs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Logs
                    </a>
                </li>
            <?php endif; ?>
            
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
</aside>
