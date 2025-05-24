<?php
// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "obs_system";

// Create connection with error reporting
try {
    $conn = new mysqli($servername, $username, $password);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if (!$conn->query($sql)) {
        throw new Exception("Error creating database: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db($dbname);
    
    // Test connection to the selected database
    if (!$conn->ping()) {
        throw new Exception("Connection to database lost");
    }
    
    echo "<!-- Database connection established -->";
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Create tables if they don't exist
function createTables($conn) {
    // First disable foreign key checks to avoid constraint errors
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Create students table
    $sql = "CREATE TABLE IF NOT EXISTS students (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_number VARCHAR(20) UNIQUE NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        class_year INT(1) NOT NULL,
        department VARCHAR(100) DEFAULT 'General',
        admission_date DATE,
        status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
        gpa FLOAT DEFAULT 0.0,
        reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        error_log("Error creating students table: " . $conn->error);
    }
    
    // Create teachers table
    $sql = "CREATE TABLE IF NOT EXISTS teachers (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_number VARCHAR(20) UNIQUE NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        department VARCHAR(100) DEFAULT 'General',
        position VARCHAR(50) DEFAULT 'Instructor',
        expertise VARCHAR(255),
        hire_date DATE,
        status ENUM('active', 'inactive', 'retired') DEFAULT 'active',
        reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        error_log("Error creating teachers table: " . $conn->error);
    }
    
    // Create admins table
    $sql = "CREATE TABLE IF NOT EXISTS admins (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        error_log("Error creating admins table: " . $conn->error);
    }
    
    // Create courses table
    $sql = "CREATE TABLE IF NOT EXISTS courses (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(20) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        teacher_id INT(6) UNSIGNED,
        credits INT(1) NOT NULL,
        description TEXT,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        error_log("Error creating courses table: " . $conn->error);
    }
    
    // Modify the courses table to use a single class year instead of multiple
    // Remove suitable_years and add class_year column
    $conn->query("ALTER TABLE courses DROP COLUMN IF EXISTS suitable_years");
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS class_year INT(1) NOT NULL DEFAULT 1");
    
    // Create course enrollment requests table
    $sql = "CREATE TABLE IF NOT EXISTS enrollment_requests (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT(6) UNSIGNED NOT NULL,
        course_id INT(6) UNSIGNED NOT NULL,
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        reviewed_by INT(6) UNSIGNED NULL,
        review_date TIMESTAMP NULL,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewed_by) REFERENCES teachers(id) ON DELETE SET NULL,
        UNIQUE (student_id, course_id, status)
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        error_log("Error creating enrollment_requests table: " . $conn->error);
    }
    
    // Create enrollments table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS enrollments (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT(6) UNSIGNED NOT NULL,
        course_id INT(6) UNSIGNED NOT NULL,
        enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE (student_id, course_id)
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        error_log("Error creating enrollments table: " . $conn->error);
    }
    
    // Create grades table without comments field
    $sql = "CREATE TABLE IF NOT EXISTS grades (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        enrollment_id INT(6) UNSIGNED NOT NULL,
        midterm FLOAT DEFAULT NULL,
        final FLOAT DEFAULT NULL,
        assignment FLOAT DEFAULT NULL,
        total_grade FLOAT DEFAULT NULL,
        letter_grade VARCHAR(2) DEFAULT NULL,
        update_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        error_log("Error creating grades table: " . $conn->error);
    }
    
    // Create announcements table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS announcements (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        author_id INT(6) UNSIGNED NOT NULL,
        author_type ENUM('admin', 'teacher') NOT NULL,
        publish_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        error_log("Error creating announcements table: " . $conn->error);
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
}

// Create tables
createTables($conn);

// Insert demo data
function insertDemoData($conn) {
    // Insert default admin if not exists
    $admin_check = $conn->query("SELECT id FROM admins WHERE username = 'admin'");
    if ($admin_check && $admin_check->num_rows == 0) {
        $admin_password = hash('sha256', 'admin123');
        $sql = "INSERT INTO admins (username, password, email, name) VALUES 
                ('admin', '$admin_password', 'admin@example.com', 'Administrator')";
        
        if (!$conn->query($sql)) {
            error_log("Error inserting admin: " . $conn->error);
        }
    }
    
    // Insert demo teacher if not exists
    $teacher_check = $conn->query("SELECT id FROM teachers WHERE username = 'instructor'");
    if ($teacher_check && $teacher_check->num_rows == 0) {
        $teacher_password = hash('sha256', 'instructor123');
        $current_date = date('Y-m-d');
        $current_year = date('Y');
        $sql = "INSERT INTO teachers (teacher_number, username, password, email, name, department, position, expertise, hire_date) VALUES 
                ('{$current_year}T0001', 'instructor', '$teacher_password', 'instructor@example.com', 'Demo Instructor', 'Computer Science', 'Assistant Professor', 'Web Development', '$current_date')";
        
        if (!$conn->query($sql)) {
            error_log("Error inserting teacher: " . $conn->error);
        }
    }
    
    // Insert demo student if not exists
    $student_check = $conn->query("SELECT id FROM students WHERE username = 'student'");
    if ($student_check && $student_check->num_rows == 0) {
        $student_password = hash('sha256', 'student123');
        $current_date = date('Y-m-d');
        $current_year = date('Y');
        $sql = "INSERT INTO students (student_number, username, password, email, name, class_year, department, admission_date) VALUES 
                ('{$current_year}S0001', 'student', '$student_password', 'student@example.com', 'Demo Student', 2, 'Computer Science', '$current_date')";
        
        if (!$conn->query($sql)) {
            error_log("Error inserting student: " . $conn->error);
        }
    }
    
    // Insert demo courses if none exist
    $course_check = $conn->query("SELECT id FROM courses");
    if ($course_check && $course_check->num_rows == 0) {
        $result = $conn->query("SELECT id FROM teachers LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $teacher_id = $row['id'];
            
            $sql = "INSERT INTO courses (course_code, title, teacher_id, credits, description) VALUES 
                    ('CS101', 'Introduction to Programming', $teacher_id, 3, 'Basic programming concepts'),
                    ('CS102', 'Data Structures', $teacher_id, 4, 'Fundamental data structures'),
                    ('MATH101', 'Calculus I', $teacher_id, 4, 'Limits, derivatives and integrals')";
            
            if (!$conn->query($sql)) {
                error_log("Error inserting courses: " . $conn->error);
            }
        }
    }
}

// Insert demo data
insertDemoData($conn);
?>
