<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

$user = check_authentication();

try {
    $patient_id = (int)($_GET['patient_id'] ?? $user['user_id']);

    if ($user['user_type'] === 'patient' && $patient_id !== (int)$user['user_id']) {
        json_out(false, 'Access denied', null, 403);
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
    json_out(true, 'Medical records retrieved', $stmt->fetchAll());
} catch (Exception $e) {
    error_log("Get records error: " . $e->getMessage());
    json_out(false, 'Failed to retrieve records', null, 500);
}
