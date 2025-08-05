<?php
// get_doctors.php - Retrieve Available Doctors
require_once 'config.php';

try {
    $stmt = $conn->prepare("
        SELECT doctor_id, name, specialization, contact, email
        FROM Doctors 
        WHERE status = 'Active'
        ORDER BY name ASC
    ");
    $stmt->execute();

    $doctors = $stmt->fetchAll();
    generate_response(true, 'Doctors retrieved successfully', $doctors);

} catch (Exception $e) {
    error_log("Get doctors error: " . $e->getMessage());
    generate_response(false, 'Failed to retrieve doctors');
}
?>