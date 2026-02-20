<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

$user = check_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Invalid request method', null, 405);

try {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $doctor_id  = (int)($_POST['doctor_id'] ?? 0);
    $date       = $_POST['date'] ?? '';
    $time       = $_POST['time'] ?? '';
    $remarks    = sanitize_input($_POST['remarks'] ?? '');

    $errors = [];
    if ($patient_id <= 0) $errors[] = 'Valid patient ID is required';
    if ($doctor_id  <= 0) $errors[] = 'Doctor selection is required';
    if (empty($date))     $errors[] = 'Appointment date is required';
    if (empty($time))     $errors[] = 'Appointment time is required';

    // Patients can only book for themselves
    if ($user['user_type'] === 'patient' && $patient_id !== (int)$user['user_id']) {
        json_out(false, 'You can only book appointments for yourself.', null, 403);
    }

    if (!empty($date)) {
        try {
            $appt_date = new DateTime($date);
            $today     = new DateTime(); $today->setTime(0,0,0);
            if ($appt_date < $today) $errors[] = 'Appointment date cannot be in the past';
            $max = (new DateTime())->add(new DateInterval('P6M'));
            if ($appt_date > $max)  $errors[] = 'Cannot book more than 6 months in advance';
        } catch (Exception) { $errors[] = 'Invalid appointment date'; }
    }

    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) $errors[] = 'Invalid time format';

    if (!empty($errors)) json_out(false, implode(' | ', $errors));

    // Verify patient & doctor exist
    $p = $conn->prepare("SELECT patient_id FROM Patients WHERE patient_id = ?"); $p->execute([$patient_id]);
    if ($p->rowCount() === 0) json_out(false, 'Patient not found');

    $d = $conn->prepare("SELECT doctor_id FROM Doctors WHERE doctor_id = ? AND status = 'Active'"); $d->execute([$doctor_id]);
    if ($d->rowCount() === 0) json_out(false, 'Doctor not found or inactive');

    // Slot availability
    $slot = $conn->prepare("SELECT appointment_id FROM Appointments WHERE doctor_id=? AND date=? AND time=? AND status NOT IN ('Cancelled','Completed')");
    $slot->execute([$doctor_id, $date, $time]);
    if ($slot->rowCount() > 0) json_out(false, 'This time slot is already booked for that doctor');

    $mine = $conn->prepare("SELECT appointment_id FROM Appointments WHERE patient_id=? AND date=? AND time=? AND status NOT IN ('Cancelled','Completed')");
    $mine->execute([$patient_id, $date, $time]);
    if ($mine->rowCount() > 0) json_out(false, 'You already have an appointment at this time');

    $stmt = $conn->prepare("INSERT INTO Appointments (patient_id, doctor_id, date, time, status, remarks) VALUES (?,?,?,?,'Scheduled',?)");
    $result = $stmt->execute([$patient_id, $doctor_id, $date, $time, $remarks]);

    if ($result) {
        json_out(true, 'Appointment booked successfully!', ['appointment_id' => (int)$conn->lastInsertId()]);
    } else {
        json_out(false, 'Failed to book appointment.');
    }
} catch (Exception $e) {
    error_log("Appointment error: " . $e->getMessage());
    json_out(false, 'An unexpected error occurred.', null, 500);
}
