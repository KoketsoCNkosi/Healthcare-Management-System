<?php
// get_medical_records.php - Retrieve Medical Records
require_once 'config.php';
require_once 'auth_check.php';

try {
    $patient_id = $_GET['patient_id'] ?? $_SESSION['user_id'];
    
    // Authorization check
    if ($_SESSION['user_type'] === 'patient' && $patient_id != $_SESSION['user_id']) {
        generate_response(false, 'Access denied');
    }

    $stmt = $conn->prepare("
        SELECT mr.*, d.name as doctor_name, d.specialization, a.date as appointment_date
        FROM MedicalRecords mr
        JOIN Doctors d ON mr.doctor_id = d.doctor_id
        JOIN Appointments a ON mr.appointment_id = a.appointment_id
        WHERE mr.patient_id = ?
        ORDER BY mr.visit_date DESC
    ");
    $stmt->execute([$patient_id]);

    $records = $stmt->fetchAll();
    generate_response(true, 'Medical records retrieved successfully', $records);

} catch (Exception $e) {
    error_log("Get medical records error: " . $e->getMessage());
    generate_response(false, 'Failed to retrieve medical records');
}
?>