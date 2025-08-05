<?php
// auth_check.php - Session Authentication Check
require_once 'config.php';
session_start();

function check_authentication() {
    global $conn;
    
    if (!isset($_COOKIE['session_id']) && !isset($_SESSION['user_id'])) {
        generate_response(false, 'Authentication required');
    }

    if (isset($_COOKIE['session_id'])) {
        $stmt = $conn->prepare("
            SELECT user_id, user_type, expires_at 
            FROM Sessions 
            WHERE session_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$_COOKIE['session_id']]);
        $session = $stmt->fetch();

        if (!$session) {
            generate_response(false, 'Session expired. Please login again.');
        }

        $_SESSION['user_id'] = $session['user_id'];
        $_SESSION['user_type'] = $session['user_type'];
    }
}

// Check authentication for protected routes
check_authentication();
?>