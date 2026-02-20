<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Invalid request method', null, 405);

try {
    $username  = sanitize_input($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $user_type = sanitize_input($_POST['user_type'] ?? 'patient');

    if (empty($username) || empty($password)) json_out(false, 'Username and password are required');

    $valid_types = ['patient', 'doctor', 'admin'];
    if (!in_array($user_type, $valid_types)) json_out(false, 'Invalid user type');

    $table    = ($user_type === 'doctor') ? 'Doctors'  : 'Patients';
    $id_field = ($user_type === 'doctor') ? 'doctor_id' : 'patient_id';

    $stmt = $conn->prepare("SELECT {$id_field}, username, password_hash, name FROM {$table} WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_out(false, 'Invalid username or password');
    }

    // Delete old sessions for this user
    $conn->prepare("DELETE FROM Sessions WHERE user_id = ? AND user_type = ?")->execute([$user[$id_field], $user_type]);

    $session_token = bin2hex(random_bytes(32));
    $expires_at    = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $conn->prepare("INSERT INTO Sessions (session_id, user_id, user_type, expires_at) VALUES (?, ?, ?, ?)")
         ->execute([$session_token, $user[$id_field], $user_type, $expires_at]);

    setcookie('session_id', $session_token, [
        'expires'  => time() + 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    $_SESSION['user_id']   = $user[$id_field];
    $_SESSION['user_type'] = $user_type;
    $_SESSION['username']  = $user['username'];
    $_SESSION['name']      = $user['name'];

    json_out(true, 'Login successful', [
        'user_id'   => $user[$id_field],
        'user_type' => $user_type,
        'name'      => $user['name'],
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    json_out(false, 'Login failed. Please try again.', null, 500);
}
