<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generate_response(false, 'Invalid request method');
}

try {
    $name = sanitize_input($_POST['name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $gender = sanitize_input($_POST['gender'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $contact = sanitize_input($_POST['contact'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $emergency_contact = sanitize_input($_POST['emergency_contact'] ?? '');
    $insurance_info = sanitize_input($_POST['insurance_info'] ?? '');
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($dob)) $errors[] = 'Date of birth is required';
    if (empty($gender)) $errors[] = 'Gender is required';
    if (empty($address)) $errors[] = 'Address is required';
    if (empty($contact)) $errors[] = 'Contact number is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (!validate_phone($contact)) $errors[] = 'Invalid contact number format';
    if (!empty($email) && !validate_email($email)) $errors[] = 'Invalid email format';
    if (!empty($emergency_contact) && !validate_phone($emergency_contact)) $errors[] = 'Invalid emergency contact format';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters long';
    if (strlen($username) < 4) $errors[] = 'Username must be at least 4 characters long';

    $birth_date = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    if ($age > 150 || $age < 0) $errors[] = 'Invalid date of birth';

    if (!empty($errors)) {
        generate_response(false, implode(', ', $errors));
    }

    $stmt = $conn->prepare("SELECT patient_id FROM Patients WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        generate_response(false, 'Username already exists. Please choose another.');
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("
        INSERT INTO Patients (name, dob, gender, address, contact, email, emergency_contact, insurance_info, username, password_hash) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $result = $stmt->execute([
        $name, $dob, $gender, $address, $contact, 
        $email ?: null, $emergency_contact ?: null, $insurance_info ?: null, 
        $username, $password_hash
    ]);

    if ($result) {
        $patient_id = $conn->lastInsertId();
        generate_response(true, 'Patient registered successfully!', ['patient_id' => $patient_id]);
    } else {
        generate_response(false, 'Registration failed. Please try again.');
    }
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    generate_response(false, 'Database error occurred. Please contact support.');
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    generate_response(false, 'An unexpected error occurred. Please try again.');
}
?>