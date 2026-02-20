<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

$user = check_authentication();

try {
    $patient_id = (int)($_GET['patient_id'] ?? $user['user_id']);

    if ($user['user_type'] === 'patient' && $patient_id !== (int)$user['user_id']) {
        json_out(false, 'Access denied', null, 403);
    }

    $params = [];
    if ($user['user_type'] === 'admin' && empty($_GET['patient_id'])) {
        $sql = "SELECT b.*, a.date as appointment_date, a.time as appointment_time, p.name as patient_name, d.name as doctor_name, d.specialization FROM Bills b JOIN Appointments a ON b.appointment_id=a.appointment_id JOIN Doctors d ON a.doctor_id=d.doctor_id JOIN Patients p ON b.patient_id=p.patient_id ORDER BY b.date_issued DESC";
    } else {
        $sql = "SELECT b.*, a.date as appointment_date, a.time as appointment_time, d.name as doctor_name, d.specialization FROM Bills b JOIN Appointments a ON b.appointment_id=a.appointment_id JOIN Doctors d ON a.doctor_id=d.doctor_id WHERE b.patient_id=? ORDER BY b.date_issued DESC";
        $params[] = $patient_id;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    json_out(true, 'Bills retrieved', $stmt->fetchAll());
} catch (Exception $e) {
    error_log("Get bills error: " . $e->getMessage());
    json_out(false, 'Failed to retrieve bills', null, 500);
}
