<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

try {
    if (isset($_COOKIE['session_id'])) {
        $conn->prepare("DELETE FROM Sessions WHERE session_id = ?")->execute([$_COOKIE['session_id']]);
        setcookie('session_id', '', time() - 3600, '/');
    }
    session_destroy();
    json_out(true, 'Logged out successfully');
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    json_out(false, 'Logout failed', null, 500);
}
