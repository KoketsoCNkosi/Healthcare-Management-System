<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

$user = check_authentication();

$type   = sanitize_input($_GET['type'] ?? '');
$format = sanitize_input($_GET['format'] ?? 'print');
$id     = (int)($_GET['id'] ?? 0);

try {
    if ($type === 'bill' && $id > 0) {
        $stmt = $conn->prepare("SELECT b.*, p.name as patient_name, p.address, p.contact, p.email, a.date as appt_date, a.time as appt_time, d.name as doctor_name, d.specialization FROM Bills b JOIN Patients p ON b.patient_id=p.patient_id JOIN Appointments a ON b.appointment_id=a.appointment_id JOIN Doctors d ON a.doctor_id=d.doctor_id WHERE b.bill_id=?");
        $stmt->execute([$id]);
        $bill = $stmt->fetch();
        if (!$bill) json_out(false, 'Bill not found');
        // Auth check
        if ($user['user_type'] === 'patient' && $bill['patient_id'] != $user['user_id']) json_out(false,'Access denied',null,403);
        json_out(true, 'Bill data', $bill);
    }

    if ($type === 'record' && $id > 0) {
        $stmt = $conn->prepare("SELECT mr.*, p.name as patient_name, p.dob, p.gender, p.contact, d.name as doctor_name, d.specialization FROM MedicalRecords mr JOIN Patients p ON mr.patient_id=p.patient_id JOIN Doctors d ON mr.doctor_id=d.doctor_id WHERE mr.record_id=?");
        $stmt->execute([$id]);
        $rec = $stmt->fetch();
        if (!$rec) json_out(false,'Record not found');
        if ($user['user_type'] === 'patient' && $rec['patient_id'] != $user['user_id']) json_out(false,'Access denied',null,403);
        json_out(true, 'Record data', $rec);
    }

    json_out(false, 'Invalid export request');
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    json_out(false, 'Export failed', null, 500);
}
