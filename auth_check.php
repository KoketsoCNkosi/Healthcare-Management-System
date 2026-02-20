

    // Try cookie-based session first
    if (isset($_COOKIE['session_id'])) {
        $stmt = $conn->prepare("
            SELECT user_id, user_type, expires_at
            FROM Sessions
            WHERE session_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$_COOKIE['session_id']]);
        $session = $stmt->fetch();

        if ($session) {
            $_SESSION['user_id']   = $session['user_id'];
            $_SESSION['user_type'] = $session['user_type'];
            $user = $session;
        } else {
            // Expired — clear cookie
            setcookie('session_id', '', time() - 3600, '/');
        }
    }

    // Fallback to PHP session
    if (!$user && isset($_SESSION['user_id'])) {
        $user = [
            'user_id'   => $_SESSION['user_id'],
            'user_type' => $_SESSION['user_type'],
        ];
    }

    if (!$user) {
        json_out(false, 'Authentication required. Please log in.', null, 401);
    }

    if ($required_role && $user['user_type'] !== $required_role && $user['user_type'] !== 'admin') {
        json_out(false, 'Access denied. Insufficient permissions.', null, 403);
    }

    return $user;
}
