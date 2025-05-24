<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

// Mock announcements (in a real system, these would come from the database)
$announcements = [
    [
        'id' => 1,
        'title' => 'System Maintenance',
        'content' => 'The system will be down for maintenance on Sunday from 2 AM to 5 AM.',
        'date' => date('Y-m-d', strtotime('+3 days'))
    ],
    [
        'id' => 2,
        'title' => 'Final Exam Schedule',
        'content' => 'Final exam schedules are now available. Please check your courses for details.',
        'date' => date('Y-m-d', strtotime('-1 days'))
    ],
    [
        'id' => 3,
        'title' => 'Course Registration',
        'content' => 'Course registration for the next semester starts on June 15.',
        'date' => date('Y-m-d', strtotime('-3 days'))
    ]
];

echo json_encode(['announcements' => $announcements]);
?>
