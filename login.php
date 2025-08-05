<?php
// login.php - Authentication Handler
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generate_response(false, 'Invalid request method');
}

try {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = sanitize_input($_POST['user_type'] ?? 'patient'); // patient, doctor, admin

    if (empty($username) || empty($password)) {
        generate_response(false, 'Username and password are required');
    }

    // Determine which table to check based on user type
    $table = ($user_type === 'doctor') ? 'Doctors' : 'Patients';
    $id_field = ($user_type === 'doctor') ? 'doctor_id' : 'patient_id';

    $stmt = $conn->prepare("SELECT {$id_field}, username, password_hash, name FROM {$table} WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        generate_response(false, 'Invalid username or password');
    }

    // Create session
    $session_id = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $conn->prepare("INSERT INTO Sessions (session_id, user_id, user_type, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$session_id, $user[$id_field], $user_type, $expires_at]);

    // Set session cookie
    setcookie('session_id', $session_id, time() + (24 * 60 * 60), '/');
    $_SESSION['user_id'] = $user[$id_field];
    $_SESSION['user_type'] = $user_type;
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];

    generate_response(true, 'Login successful', [
        'user_id' => $user[$id_field],
        'user_type' => $user_type,
        'name' => $user['name']
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    generate_response(false, 'Login failed. Please try again.');
}
?>