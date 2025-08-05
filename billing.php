<?php
// billing.php - Billing Handler
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generate_response(false, 'Invalid request method');
}

try {
    // Get and sanitize input data
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = sanitize_input($_POST['payment_method'] ?? 'Cash');
    $status = sanitize_input($_POST['status'] ?? 'Pending');

    // Validation
    $errors = [];
    
    if ($appointment_id <= 0) $errors[] = 'Valid appointment ID is required';
    if ($amount <= 0) $errors[] = 'Amount must be greater than 0';
    if ($amount > 99999.99) $errors[] = 'Amount cannot exceed $99,999.99';
    
    $valid_payment_methods = ['Cash', 'Credit Card', 'Debit Card', 'Insurance', 'Bank Transfer'];
    if (!in_array($payment_method, $valid_payment_methods)) {
        $errors[] = 'Invalid payment method';
    }
    
    $valid_statuses = ['Pending', 'Paid', 'Partially Paid', 'Cancelled'];
    if (!in_array($status, $valid_statuses)) {
        $errors[] = 'Invalid bill status';
    }

    if (!empty($errors)) {
        generate_response(false, implode(', ', $errors));
    }

    // Get appointment details and verify it exists
    $stmt = $conn->prepare("
        SELECT a.*, p.name as patient_name 
        FROM Appointments a
        JOIN Patients p ON a.patient_id = p.patient_id
        WHERE a.appointment_id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        generate_response(false, 'Appointment not found');
    }

    // Check if bill already exists for this appointment
    $stmt = $conn->prepare("SELECT bill_id FROM Bills WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);
    $existing_bill = $stmt->fetch();

    if ($existing_bill) {
        // Update existing bill
        $stmt = $conn->prepare("
            UPDATE Bills 
            SET amount = ?, payment_method = ?, status = ?
            WHERE appointment_id = ?
        ");
        $result = $stmt->execute([$amount, $payment_method, $status, $appointment_id]);
        $bill_id = $existing_bill['bill_id'];
        $action = 'updated';
    } else {
        // Insert new bill
        $stmt = $conn->prepare("
            INSERT INTO Bills (appointment_id, patient_id, amount, status, date_issued, payment_method) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $appointment_id, $appointment['patient_id'], $amount, $status, 
            date('Y-m-d'), $payment_method
        ]);
        $bill_id = $conn->lastInsertId();
        $action = 'generated';
    }

    if ($result) {
        // Get complete bill details for response
        $stmt = $conn->prepare("
            SELECT b.*, p.name as patient_name 
            FROM Bills b
            JOIN Patients p ON b.patient_id = p.patient_id
            WHERE b.bill_id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill_details = $stmt->fetch();
        
        generate_response(true, "Bill {$action} successfully!", $bill_details);
    } else {
        generate_response(false, 'Failed to process bill. Please try again.');
    }

} catch (PDOException $e) {
    error_log("Billing error: " . $e->getMessage());
    generate_response(false, 'Database error occurred. Please contact support.');
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());

}