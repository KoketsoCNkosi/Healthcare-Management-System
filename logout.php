<?php
// logout.php - Logout Handler
require_once 'config.php';
session_start();

try {
    if (isset($_COOKIE['session_id'])) {
        // Remove session from database
        $stmt = $conn->prepare("DELETE FROM Sessions WHERE session_id = ?");
        $stmt->execute([$_COOKIE['session_id']]);
        
        // Clear cookie
        setcookie('session_id', '', time() - 3600, '/');
    }

    // Destroy PHP session
    session_destroy();
    
    generate_response(true, 'Logged out successfully');

} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    generate_response(false, 'Logout failed');
}
?>