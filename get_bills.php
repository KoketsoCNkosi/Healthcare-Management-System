<?php
// get_bills.php - Retrieve Bills
require_once 'config.php';
require_once 'auth_check.php';

try {
    $patient_id = $_GET['patient_id'] ?? $_SESSION['user_id'];
    
    // Authorization check
    if ($_SESSION['user_type'] === 'patient' && $patient_id != $_SESSION['user_id']) {
        generate_response(false, 'Access denied');
    }

    $stmt = $conn->prepare("
        SELECT b.*, a.date as appointment_date, a.time as appointment_time,
               d.name as doctor_name, d.specialization
        FROM Bills b
        JOIN Appointments a ON b.appointment_id = a.appointment_id
        JOIN Doctors d ON a.doctor_id = d.doctor_id
        WHERE b.patient_id = ?
        ORDER BY b.date_issued DESC
    ");
    $stmt->execute([$patient_id]);

    $bills = $stmt->fetchAll();
    generate_response(true, 'Bills retrieved successfully', $bills);

} catch (Exception $e) {
    error_log("Get bills error: " . $e->getMessage());
    generate_response(false, 'Failed to retrieve bills');
}
?>