<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generate_response(false, 'Invalid request method');
}

try {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $remarks = sanitize_input($_POST['remarks'] ?? '');

    $errors = [];
    if ($patient_id <= 0) $errors[] = 'Valid patient ID is required';
    if ($doctor_id <= 0) $errors[] = 'Doctor selection is required';
    if (empty($date)) $errors[] = 'Appointment date is required';
    if (empty($time)) $errors[] = 'Appointment time is required';

    try {
        $appointment_date = new DateTime($date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        if ($appointment_date < $today) $errors[] = 'Appointment date cannot be in the past';
        $six_months = new DateTime();
        $six_months->add(new DateInterval('P6M'));
        if ($appointment_date > $six_months) $errors[] = 'Appointments cannot be booked more than 6 months in advance';
    } catch (Exception $e) {
        $errors[] = 'Invalid appointment date format';
    }

    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        $errors[] = 'Invalid time format';
    }

    if (!empty($errors)) {
        generate_response(false, implode(', ', $errors));
    }

    $stmt = $conn->prepare("SELECT patient_id FROM Patients WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    if ($stmt->rowCount() === 0) {
        generate_response(false, 'Patient not found');
    }

    $stmt = $conn->prepare("SELECT doctor_id FROM Doctors WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    if ($stmt->rowCount() === 0) {
        generate_response(false, 'Doctor not found');
    }

    $stmt = $conn->prepare("
        SELECT appointment_id FROM Appointments 
        WHERE doctor_id = ? AND date = ? AND time = ? AND status NOT IN ('Cancelled', 'Completed')
    ");
    $stmt->execute([$doctor_id, $date, $time]);
    if ($stmt->rowCount() > 0) {
        generate_response(false, 'This time slot is already booked for the selected doctor');
    }

    $stmt = $conn->prepare("
        SELECT appointment_id FROM Appointments 
        WHERE patient_id = ? AND date = ? AND time = ? AND status NOT IN ('Cancelled', 'Completed')
    ");
    $stmt->execute([$patient_id, $date, $time]);
    if ($stmt->rowCount() > 0) {
        generate_response(false, 'You already have an appointment at this time');
    }

    $stmt = $conn->prepare("
        INSERT INTO Appointments (patient_id, doctor_id, date, time, status, remarks) 
        VALUES (?, ?, ?, ?, 'Scheduled', ?)
    ");
    $result = $stmt->execute([$patient_id, $doctor_id, $date, $time, $remarks]);

    if ($result) {
        $appointment_id = $conn->lastInsertId();
        generate_response(true, 'Appointment booked successfully!', ['appointment_id' => $appointment_id]);
    } else {
        generate_response(false, 'Failed to book appointment.');
    }
} catch (PDOException $e) {
    error_log("Appointment booking error: " . $e->getMessage());
    generate_response(false, 'Database error occurred. Please contact support.');
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    generate_response(false, 'An unexpected error occurred. Please try again.');
}
?>