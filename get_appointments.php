<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

$user = check_authentication();

try {
    $search = sanitize_input($_GET['search'] ?? '');
    $status_filter = sanitize_input($_GET['status'] ?? '');
    $date_from = $_GET['date_from'] ?? '';
    $date_to   = $_GET['date_to'] ?? '';

    $params = [];

    if ($user['user_type'] === 'patient') {
        $sql = "SELECT a.*, d.name as doctor_name, d.specialization FROM Appointments a JOIN Doctors d ON a.doctor_id=d.doctor_id WHERE a.patient_id=?";
        $params[] = $user['user_id'];
    } elseif ($user['user_type'] === 'doctor') {
        $sql = "SELECT a.*, p.name as patient_name, p.contact as patient_contact FROM Appointments a JOIN Patients p ON a.patient_id=p.patient_id WHERE a.doctor_id=?";
        $params[] = $user['user_id'];
    } else {
        $sql = "SELECT a.*, p.name as patient_name, d.name as doctor_name, d.specialization FROM Appointments a JOIN Patients p ON a.patient_id=p.patient_id JOIN Doctors d ON a.doctor_id=d.doctor_id WHERE 1=1";
    }

    if (!empty($status_filter)) { $sql .= " AND a.status = ?"; $params[] = $status_filter; }
    if (!empty($date_from))     { $sql .= " AND a.date >= ?"; $params[] = $date_from; }
    if (!empty($date_to))       { $sql .= " AND a.date <= ?"; $params[] = $date_to; }
    if (!empty($search) && $user['user_type'] === 'admin') {
        $sql .= " AND (p.name LIKE ? OR d.name LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%";
    }

    $sql .= " ORDER BY a.date DESC, a.time DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    json_out(true, 'Appointments retrieved', $stmt->fetchAll());
} catch (Exception $e) {
    error_log("Get appointments error: " . $e->getMessage());
    json_out(false, 'Failed to retrieve appointments', null, 500);
}
