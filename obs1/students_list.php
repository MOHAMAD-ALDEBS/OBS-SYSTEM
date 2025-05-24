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
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$message = '';

// Get teacher information
$teacher_sql = "SELECT id, name, department FROM teachers WHERE id = $teacher_id";
$teacher_result = $conn->query($teacher_sql);
$teacher = $teacher_result->fetch_assoc();

// Set default filters
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$dept_filter = isset($_GET['department']) ? $conn->real_escape_string($_GET['department']) : '';
$year_filter = isset($_GET['year']) ? $conn->real_escape_string($_GET['year']) : '';
$search_by = isset($_GET['search_by']) ? $conn->real_escape_string($_GET['search_by']) : '';

// Build the SQL query with filters
$sql_parts = ["SELECT s.id, s.student_number, s.name, s.class_year, s.department, s.email
              FROM students s"];
$where_clauses = [];

if (!empty($search_term)) {
    if ($search_by == 'student_number') {
        $where_clauses[] = "s.student_number LIKE '%$search_term%'";
    } elseif ($search_by == 'name') {
        $where_clauses[] = "s.name LIKE '%$search_term%'";
    } else {
        $where_clauses[] = "(s.student_number LIKE '%$search_term%' OR s.name LIKE '%$search_term%')";
    }
}

if (!empty($dept_filter)) {
    $where_clauses[] = "s.department = '$dept_filter'";
}

if (!empty($year_filter)) {
    $where_clauses[] = "s.class_year = '$year_filter'";
}

if (!empty($where_clauses)) {
    $sql_parts[] = "WHERE " . implode(' AND ', $where_clauses);
}

// Changed the ORDER BY clause to sort by student_number
$sql_parts[] = "ORDER BY s.student_number";
$sql = implode(' ', $sql_parts);

// Execute query
$students_result = $conn->query($sql);

// Get available departments for filter dropdown
$dept_sql = "SELECT DISTINCT department FROM students ORDER BY department";
$dept_result = $conn->query($dept_sql);

// Get available class years for filter dropdown
$year_sql = "SELECT DISTINCT class_year FROM students ORDER BY class_year";
$year_result = $conn->query($year_sql);

// Get courses taught by this teacher
$courses_sql = "SELECT id, course_code, title FROM courses WHERE teacher_id = $teacher_id ORDER BY course_code";
$courses_result = $conn->query($courses_sql);

$page_title = isset($lang['students_list']) ? $lang['students_list'] : 'Students List';
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
            <div class="teacher-panel">
                <div class="teacher-panel-header">
                    <h2><i class="fas fa-user-graduate"></i> <?php echo $page_title; ?></h2>
                </div>
                
                <!-- Search and Filter Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><?php echo isset($lang['search_filter_students']) ? $lang['search_filter_students'] : 'Search & Filter Students'; ?></h3>
                    </div>
                    <div class="panel-card-body filter-body">
                        <form class="filter-form" id="searchForm">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="search"><?php echo isset($lang['search_term']) ? $lang['search_term'] : 'Search Term'; ?></label>
                                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" 
                                           placeholder="<?php echo isset($lang['search_placeholder']) ? $lang['search_placeholder'] : 'Enter student number'; ?>" 
                                           class="filter-input" autocomplete="off">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="department"><?php echo isset($lang['department']) ? $lang['department'] : 'Department'; ?></label>
                                    <select id="department" name="department" class="filter-select">
                                        <option value=""><?php echo isset($lang['all_departments']) ? $lang['all_departments'] : 'All Departments'; ?></option>
                                        <?php 
                                        // Reset pointer to reuse result set
                                        $dept_result->data_seek(0);
                                        while ($dept = $dept_result->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $dept['department']; ?>" <?php echo $dept_filter == $dept['department'] ? 'selected' : ''; ?>><?php echo $dept['department']; ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="year"><?php echo isset($lang['class_year']) ? $lang['class_year'] : 'Class Year'; ?></label>
                                    <select id="year" name="year" class="filter-select">
                                        <option value=""><?php echo isset($lang['all_years']) ? $lang['all_years'] : 'All Years'; ?></option>
                                        <?php 
                                        // Reset pointer to reuse result set
                                        $year_result->data_seek(0);
                                        while ($year = $year_result->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $year['class_year']; ?>" <?php echo $year_filter == $year['class_year'] ? 'selected' : ''; ?>><?php echo $year['class_year']; ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Students List Card -->
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h3><?php echo isset($lang['students_list']) ? $lang['students_list'] : 'Students List'; ?></h3>
                        <?php if ($students_result && $students_result->num_rows > 0): ?>
                            <span class="result-count">
                                <?php echo sprintf(isset($lang['showing_students']) ? $lang['showing_students'] : 'Showing %d students', $students_result->num_rows); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="panel-card-body">
                        <div id="students-container">
                            <?php if ($students_result && $students_result->num_rows > 0): ?>
                                <?php while($student = $students_result->fetch_assoc()): ?>
                                    <a href="student_details.php?id=<?php echo $student['id']; ?>" class="item-card student-item">
                                        <div class="item-icon student-icon">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                        <div class="item-content">
                                            <div class="item-header">
                                                <div class="item-title"><?php echo $student['name']; ?></div>
                                                <div class="student-number"><?php echo $student['student_number']; ?></div>
                                            </div>
                                            <div class="item-metadata">
                                                <span class="item-meta"><i class="fas fa-envelope"></i> <?php echo $student['email']; ?></span>
                                                <span class="item-meta"><i class="fas fa-graduation-cap"></i> <?php echo isset($lang['year']) ? $lang['year'] : 'Year'; ?> <?php echo $student['class_year']; ?></span>
                                                <span class="item-meta"><i class="fas fa-building"></i> <?php echo $student['department']; ?></span>
                                                
                                                <!-- Check if student is enrolled in any of this teacher's courses -->
                                                <?php
                                                $enrolled_sql = "SELECT c.course_code FROM enrollments e 
                                                               JOIN courses c ON e.course_id = c.id 
                                                               WHERE e.student_id = {$student['id']} AND c.teacher_id = $teacher_id 
                                                               LIMIT 3";
                                                $enrolled_result = $conn->query($enrolled_sql);
                                                
                                                if ($enrolled_result && $enrolled_result->num_rows > 0):
                                                    $courses = [];
                                                    while ($course = $enrolled_result->fetch_assoc()) {
                                                        $courses[] = $course['course_code'];
                                                    }
                                                    $more_courses = '';
                                                    if ($enrolled_result->num_rows > 3) {
                                                        $more_courses = '...';
                                                    }
                                                ?>
                                                    <span class="item-meta enrolled"><i class="fas fa-check-circle"></i> <?php echo implode(', ', $courses) . $more_courses; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="item-action">
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-search"></i></div>
                                    <p><?php echo isset($lang['no_students_found']) ? $lang['no_students_found'] : 'No students found matching your criteria.'; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Teacher Panel - Matching Operations Panel Design */
.teacher-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.teacher-panel-header h2 {
    margin: 0 0 20px 0;
    font-size: 24px;
    font-weight: 500;
    color: var(--text-primary);
    display: flex;
    align-items: center;
}

.teacher-panel-header h2 i {
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
}

.result-count {
    background-color: rgba(255, 255, 255, 0.2);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

/* Filter Form Styling */
.filter-body {
    padding: 20px;
}

.filter-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
}

.filter-input,
.filter-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.filter-input:focus,
.filter-select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.1);
}

/* Loading indicator for search */
.loading-indicator {
    text-align: center;
    padding: 20px;
    display: none;
}

.loading-indicator i {
    font-size: 30px;
    color: var(--primary-color);
    animation: spin 1s infinite linear;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.filter-buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.btn {
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s;
    text-decoration: none;
}

.btn i {
    margin-right: 8px;
}

.btn.primary {
    background-color: var(--primary-color);
    color: white;
}

.btn.primary:hover {
    background-color: var(--primary-dark);
}

.btn.outline {
    background-color: transparent;
    border: 1px solid #cbd5e0;
    color: var(--text-secondary);
}

.btn.outline:hover {
    background-color: #f1f5f9;
    border-color: #a0aec0;
}

/* Category Headers */
.category-header {
    padding: 12px 20px;
    background-color: #f1f3f5;
    font-weight: 600;
    color: var(--primary-color);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    font-size: 14px;
}

/* Student Item Cards */
.item-card {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
    position: relative;
}

.item-card:last-child {
    border-bottom: none;
}

.item-card:hover {
    background-color: rgba(0, 0, 0, 0.02);
    padding-left: 25px;
}

.item-icon {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background-color: rgba(77, 171, 247, 0.1);
    color: #4dabf7;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
    transition: transform 0.2s;
}

.student-icon {
    background-color: rgba(94, 53, 177, 0.1);
    color: #5e35b1;
}

.item-card:hover .item-icon {
    transform: scale(1.1) rotate(5deg);
}

.item-content {
    flex: 1;
    min-width: 0; /* Prevent flexbox overflow */
}

.item-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 8px;
}

.item-title {
    font-weight: 600;
    font-size: 16px;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.student-number {
    font-size: 14px;
    color: var(--primary-color);
    font-weight: 500;
    white-space: nowrap;
}

.item-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.item-meta {
    font-size: 13px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
}

.item-meta i {
    margin-right: 5px;
    font-size: 12px;
}

.item-meta.enrolled {
    color: #38a169;
    font-weight: 500;
}

.item-action {
    color: #c5cae9;
    transition: transform 0.2s, color 0.2s;
    margin-left: 10px;
}

.item-card:hover .item-action {
    color: var(--primary-color);
    transform: translateX(3px);
}

/* Empty state */
.empty-state {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 48px;
    color: #e0e0e0;
    margin-bottom: 15px;
}

.empty-state p {
    font-size: 16px;
    margin: 0;
}

/* Animation for items */
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

.item-card {
    animation: fadeInUp 0.3s ease forwards;
    opacity: 0;
}

.item-card:nth-child(2) { animation-delay: 0.05s; }
.item-card:nth-child(3) { animation-delay: 0.1s; }
.item-card:nth-child(4) { animation-delay: 0.15s; }
.item-card:nth-child(5) { animation-delay: 0.2s; }
.item-card:nth-child(6) { animation-delay: 0.25s; }
.item-card:nth-child(7) { animation-delay: 0.3s; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-buttons {
        flex-direction: column;
    }
    
    .item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .student-number {
        font-size: 13px;
    }
    
    .item-metadata {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<script>
// Live search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const departmentSelect = document.getElementById('department');
    const yearSelect = document.getElementById('year');
    const studentsContainer = document.getElementById('students-container');
    
    let searchTimeout = null;
    
    // Function to perform search
    function performSearch() {
        const searchTerm = searchInput.value;
        const department = departmentSelect.value;
        const year = yearSelect.value;
        
        // Create loading indicator
        studentsContainer.innerHTML = '<div class="loading-indicator"><i class="fas fa-spinner"></i></div>';
        document.querySelector('.loading-indicator').style.display = 'block';
        
        // Create AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `search_students.php?search=${encodeURIComponent(searchTerm)}&department=${encodeURIComponent(department)}&year=${encodeURIComponent(year)}`, true);
        
        xhr.onload = function() {
            if (this.status === 200) {
                studentsContainer.innerHTML = this.responseText;
                
                // Update result count
                const studentItems = document.querySelectorAll('.student-item');
                const resultCount = document.querySelector('.result-count');
                if (resultCount) {
                    resultCount.textContent = `Showing ${studentItems.length} students`;
                }
            }
        };
        
        xhr.send();
    }
    
    // Add event listeners for search input and filters
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 500);
    });
    
    departmentSelect.addEventListener('change', performSearch);
    yearSelect.addEventListener('change', performSearch);
});
</script>

<?php include 'includes/footer.php'; ?>
