<?php
// update_record.php - Medical Record Update Handler
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generate_response(false, 'Invalid request method');
}

try {
    // Get and sanitize input data
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $visit_date = $_POST['visit_date'] ?? '';
    $diagnosis = sanitize_input($_POST['diagnosis'] ?? '');
    $treatment = sanitize_input($_POST['treatment'] ?? '');
    $prescription = sanitize_input($_POST['prescription'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');

    // Validation
    $errors = [];
    
    if ($appointment_id <= 0) $errors[] = 'Valid appointment ID is required';
    if (empty($visit_date)) $errors[] = 'Visit date is required';
    if (empty($diagnosis)) $errors[] = 'Diagnosis is required';
    if (empty($treatment)) $errors[] = 'Treatment information is required';

    // Validate visit date
    try {
        $visit_datetime = new DateTime($visit_date);
        $today = new DateTime();
        
        if ($visit_datetime > $today) {
            $errors[] = 'Visit date cannot be in the future';
        }
    } catch (Exception $e) {
        $errors[] = 'Invalid visit date format';
    }

    if (!empty($errors)) {
        generate_response(false, implode(', ', $errors));
    }

    // Get appointment details and verify it exists
    $stmt = $conn->prepare("
        SELECT a.*, p.name as patient_name, d.name as doctor_name 
        FROM Appointments a
        JOIN Patients p ON a.patient_id = p.patient_id
        JOIN Doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        generate_response(false, 'Appointment not found');
    }

    // Check if medical record already exists for this appointment
    $stmt = $conn->prepare("SELECT record_id FROM MedicalRecords WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);
    $existing_record = $stmt->fetch();

    if ($existing_record) {
        // Update existing record
        $stmt = $conn->prepare("
            UPDATE MedicalRecords 
            SET visit_date = ?, diagnosis = ?, treatment = ?, prescription = ?, notes = ?
            WHERE appointment_id = ?
        ");
        $result = $stmt->execute([$visit_date, $diagnosis, $treatment, $prescription, $notes, $appointment_id]);
        $record_id = $existing_record['record_id'];
        $action = 'updated';
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO MedicalRecords (appointment_id, patient_id, doctor_id, visit_date, diagnosis, treatment, prescription, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $appointment_id, $appointment['patient_id'], $appointment['doctor_id'],
            $visit_date, $diagnosis, $treatment, $prescription, $notes
        ]);
        $record_id = $conn->lastInsertId();
        $action = 'created';
    }

    if ($result) {
        // Update appointment status to completed
        $stmt = $conn->prepare("UPDATE Appointments SET status = 'Completed' WHERE appointment_id = ?");
        $stmt->execute([$appointment_id]);
        
        // Get complete record details for response
        $stmt = $conn->prepare("
            SELECT mr.*, p.name as patient_name, d.name as doctor_name 
            FROM MedicalRecords mr
            JOIN Patients p ON mr.patient_id = p.patient_id
            JOIN Doctors d ON mr.doctor_id = d.doctor_id
            WHERE mr.record_id = ?
        ");
        $stmt->execute([$record_id]);
        $record_details = $stmt->fetch();
        
        generate_response(true, "Medical record {$action} successfully!", $record_details);
    } else {
        generate_response(false, 'Failed to update medical record. Please try again.');
    }

} catch (PDOException $e) {
    error_log("Medical record update error: " . $e->getMessage());
    generate_response(false, 'Database error occurred. Please contact support.');
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    generate_response(false, 'An unexpected error occurred. Please try again.');
}
?>