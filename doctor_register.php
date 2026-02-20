<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

check_authentication('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Invalid request method', null, 405);

try {
    $name           = sanitize_input($_POST['name'] ?? '');
    $specialization = sanitize_input($_POST['specialization'] ?? '');
    $contact        = sanitize_input($_POST['contact'] ?? '');
    $email          = sanitize_input($_POST['email'] ?? '');
    $license_number = sanitize_input($_POST['license_number'] ?? '');
    $username       = sanitize_input($_POST['username'] ?? '');
    $password       = $_POST['password'] ?? '';

    $errors = [];
    if (empty($name))           $errors[] = 'Name is required';
    if (empty($specialization)) $errors[] = 'Specialization is required';
    if (empty($contact))        $errors[] = 'Contact is required';
    if (empty($email))          $errors[] = 'Email is required';
    if (empty($license_number)) $errors[] = 'License number is required';
    if (empty($username))       $errors[] = 'Username is required';
    if (empty($password))       $errors[] = 'Password is required';
    if (!validate_email($email))  $errors[] = 'Invalid email format';
    if (!validate_phone($contact)) $errors[] = 'Invalid contact format';
    if (strlen($password) < 6)   $errors[] = 'Password must be at least 6 characters';

    if (!empty($errors)) json_out(false, implode(' | ', $errors));

    $check = $conn->prepare("SELECT doctor_id FROM Doctors WHERE username = ? OR email = ? OR license_number = ?");
    $check->execute([$username, $email, $license_number]);
    if ($check->rowCount() > 0) json_out(false, 'Username, email, or license number already exists.');

    $stmt = $conn->prepare("
        INSERT INTO Doctors (name, specialization, contact, email, license_number, username, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
    ");
    $result = $stmt->execute([
        $name, $specialization, $contact, $email,
        $license_number, $username, password_hash($password, PASSWORD_BCRYPT)
    ]);

    if ($result) {
        json_out(true, 'Doctor registered successfully!', ['doctor_id' => (int)$conn->lastInsertId()]);
    } else {
        json_out(false, 'Registration failed.');
    }
} catch (Exception $e) {
    error_log("Doctor registration error: " . $e->getMessage());
    json_out(false, 'Registration failed.', null, 500);
}
