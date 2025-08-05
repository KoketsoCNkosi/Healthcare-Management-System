<?php
// doctor_register.php - Doctor Registration (Admin only)
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generate_response(false, 'Invalid request method');
}

try {
    $name = sanitize_input($_POST['name'] ?? '');
    $specialization = sanitize_input($_POST['specialization'] ?? '');
    $contact = sanitize_input($_POST['contact'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $license_number = sanitize_input($_POST['license_number'] ?? '');
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($specialization)) $errors[] = 'Specialization is required';  
    if (empty($contact)) $errors[] = 'Contact is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($license_number)) $errors[] = 'License number is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($password)) $errors[] = 'Password is required';

    if (!validate_email($email)) $errors[] = 'Invalid email format';
    if (!validate_phone($contact)) $errors[] = 'Invalid contact format';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';

    if (!empty($errors)) {
        generate_response(false, implode(', ', $errors));
    }

    // Check for duplicates
    $stmt = $conn->prepare("SELECT doctor_id FROM Doctors WHERE username = ? OR email = ? OR license_number = ?");
    $stmt->execute([$username, $email, $license_number]);
    if ($stmt->rowCount() > 0) {
        generate_response(false, 'Username, email, or license number already exists');
    }

    // Hash password and insert
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("
        INSERT INTO Doctors (name, specialization, contact, email, license_number, username, password_hash) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([$name, $specialization, $contact, $email, $license_number, $username, $password_hash]);

    if ($result) {
        generate_response(true, 'Doctor registered successfully!', ['doctor_id' => $conn->lastInsertId()]);
    } else {
        generate_response(false, 'Registration failed');
    }

} catch (Exception $e) {
    error_log("Doctor registration error: " . $e->getMessage());
    generate_response(false, 'Registration failed. Please try again.');
}
?>