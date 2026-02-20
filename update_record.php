<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

check_authentication('doctor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Invalid request method', null, 405);

try {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $visit_date     = $_POST['visit_date'] ?? '';
    $diagnosis      = sanitize_input($_POST['diagnosis'] ?? '');
    $treatment      = sanitize_input($_POST['treatment'] ?? '');
    $prescription   = sanitize_input($_POST['prescription'] ?? '');
    $notes          = sanitize_input($_POST['notes'] ?? '');

    $errors = [];
    if ($appointment_id <= 0) $errors[] = 'Valid appointment ID is required';
    if (empty($visit_date))   $errors[] = 'Visit date is required';
    if (empty($diagnosis))    $errors[] = 'Diagnosis is required';
    if (empty($treatment))    $errors[] = 'Treatment is required';

    if (!empty($visit_date)) {
        try {
            if ((new DateTime($visit_date)) > new DateTime()) $errors[] = 'Visit date cannot be in the future';
        } catch (Exception) { $errors[] = 'Invalid visit date format'; }
    }

    if (!empty($errors)) json_out(false, implode(' | ', $errors));

    $stmt = $conn->prepare("
        SELECT a.*, p.name as patient_name, d.name as doctor_name
        FROM Appointments a
        JOIN Patients p ON a.patient_id = p.patient_id
        JOIN Doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    if (!$appointment) json_out(false, 'Appointment not found');

    $existing = $conn->prepare("SELECT record_id FROM MedicalRecords WHERE appointment_id = ?");
    $existing->execute([$appointment_id]);
    $rec = $existing->fetch();

    if ($rec) {
        $conn->prepare("UPDATE MedicalRecords SET visit_date=?,diagnosis=?,treatment=?,prescription=?,notes=? WHERE appointment_id=?")
             ->execute([$visit_date, $diagnosis, $treatment, $prescription, $notes, $appointment_id]);
        $record_id = $rec['record_id']; $action = 'updated';
    } else {
        $conn->prepare("INSERT INTO MedicalRecords (appointment_id,patient_id,doctor_id,visit_date,diagnosis,treatment,prescription,notes) VALUES (?,?,?,?,?,?,?,?)")
             ->execute([$appointment_id, $appointment['patient_id'], $appointment['doctor_id'], $visit_date, $diagnosis, $treatment, $prescription, $notes]);
        $record_id = (int)$conn->lastInsertId(); $action = 'created';
    }

    $conn->prepare("UPDATE Appointments SET status='Completed' WHERE appointment_id=?")->execute([$appointment_id]);

    $detail = $conn->prepare("SELECT mr.*, p.name as patient_name, d.name as doctor_name FROM MedicalRecords mr JOIN Patients p ON mr.patient_id=p.patient_id JOIN Doctors d ON mr.doctor_id=d.doctor_id WHERE mr.record_id=?");
    $detail->execute([$record_id]);

    json_out(true, "Medical record {$action} successfully!", $detail->fetch());

} catch (Exception $e) {
    error_log("Medical record error: " . $e->getMessage());
    json_out(false, 'An unexpected error occurred.', null, 500);
}
