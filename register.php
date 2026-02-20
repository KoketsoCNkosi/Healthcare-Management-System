<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Invalid request method', null, 405);

try {
    $name              = sanitize_input($_POST['name'] ?? '');
    $dob               = $_POST['dob'] ?? '';
    $gender            = sanitize_input($_POST['gender'] ?? '');
    $address           = sanitize_input($_POST['address'] ?? '');
    $contact           = sanitize_input($_POST['contact'] ?? '');
    $email             = sanitize_input($_POST['email'] ?? '');
    $emergency_contact = sanitize_input($_POST['emergency_contact'] ?? '');
    $insurance_info    = sanitize_input($_POST['insurance_info'] ?? '');
    $username          = sanitize_input($_POST['username'] ?? '');
    $password          = $_POST['password'] ?? '';

    $errors = [];
    if (empty($name))     $errors[] = 'Name is required';
    if (empty($dob))      $errors[] = 'Date of birth is required';
    if (empty($gender))   $errors[] = 'Gender is required';
    if (empty($address))  $errors[] = 'Address is required';
    if (empty($contact))  $errors[] = 'Contact number is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($password)) $errors[] = 'Password is required';

    if (!validate_phone($contact))                                           $errors[] = 'Invalid contact number (10-15 digits)';
    if (!empty($email) && !validate_email($email))                           $errors[] = 'Invalid email format';
    if (!empty($emergency_contact) && !validate_phone($emergency_contact))   $errors[] = 'Invalid emergency contact (10-15 digits)';
    if (strlen($password) < 6)                                               $errors[] = 'Password must be at least 6 characters';
    if (strlen($username) < 4)                                               $errors[] = 'Username must be at least 4 characters';

    if (!empty($dob)) {
        try {
            $age = (new DateTime())->diff(new DateTime($dob))->y;
            if ($age > 150 || $age < 0) $errors[] = 'Invalid date of birth';
        } catch (Exception) {
            $errors[] = 'Invalid date of birth format';
        }
    }

    if (!empty($errors)) json_out(false, implode(' | ', $errors));

    $check = $conn->prepare("SELECT patient_id FROM Patients WHERE username = ?");
    $check->execute([$username]);
    if ($check->rowCount() > 0) json_out(false, 'Username already taken. Please choose another.');

    $stmt = $conn->prepare("
        INSERT INTO Patients (name, dob, gender, address, contact, email, emergency_contact, insurance_info, username, password_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $result = $stmt->execute([
        $name, $dob, $gender, $address, $contact,
        $email ?: null, $emergency_contact ?: null, $insurance_info ?: null,
        $username, password_hash($password, PASSWORD_BCRYPT)
    ]);

    if ($result) {
        json_out(true, 'Patient registered successfully!', ['patient_id' => (int)$conn->lastInsertId()]);
    } else {
        json_out(false, 'Registration failed. Please try again.');
    }
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    json_out(false, 'Database error occurred.', null, 500);
}
