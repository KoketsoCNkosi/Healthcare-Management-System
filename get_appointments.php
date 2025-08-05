<?php
// get_appointments.php - Retrieve Appointments
require_once 'config.php';
require_once 'auth_check.php';

try {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];

    // Build query based on user type
    if ($user_type === 'patient') {
        $stmt = $conn->prepare("
            SELECT a.*, d.name as doctor_name, d.specialization 
            FROM Appointments a
            JOIN Doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ?
            ORDER BY a.date DESC, a.time DESC
        ");
        $stmt->execute([$user_id]);
    } elseif ($user_type === 'doctor') {
        $stmt = $conn->prepare("
            SELECT a.*, p.name as patient_name, p.contact as patient_contact
            FROM Appointments a
            JOIN Patients p ON a.patient_id = p.patient_id
            WHERE a.doctor_id = ?
            ORDER BY a.date ASC, a.time ASC
        ");
        $stmt->execute([$user_id]);
    } else {
        // Admin can see all appointments
        $stmt = $conn->prepare("
            SELECT a.*, p.name as patient_name, d.name as doctor_name, d.specialization
            FROM Appointments a
            JOIN Patients p ON a.patient_id = p.patient_id
            JOIN Doctors d ON a.doctor_id = d.doctor_id
            ORDER BY a.date DESC, a.time DESC
        ");
        $stmt->execute();
    }

    $appointments = $stmt->fetchAll();
    generate_response(true, 'Appointments retrieved successfully', $appointments);

} catch (Exception $e) {
    error_log("Get appointments error: " . $e->getMessage());
    generate_response(false, 'Failed to retrieve appointments');
}
?>