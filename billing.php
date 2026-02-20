<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

check_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Invalid request method', null, 405);

try {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $amount         = (float)($_POST['amount'] ?? 0);
    $payment_method = sanitize_input($_POST['payment_method'] ?? 'Cash');
    $status         = sanitize_input($_POST['status'] ?? 'Pending');

    $errors = [];
    if ($appointment_id <= 0)  $errors[] = 'Valid appointment ID is required';
    if ($amount <= 0)          $errors[] = 'Amount must be greater than 0';
    if ($amount > 99999.99)    $errors[] = 'Amount cannot exceed R99,999.99';

    $valid_methods = ['Cash', 'Credit Card', 'Debit Card', 'Insurance', 'Bank Transfer', 'EFT'];
    if (!in_array($payment_method, $valid_methods)) $errors[] = 'Invalid payment method';

    $valid_statuses = ['Pending', 'Paid', 'Partially Paid', 'Cancelled'];
    if (!in_array($status, $valid_statuses)) $errors[] = 'Invalid bill status';

    if (!empty($errors)) json_out(false, implode(' | ', $errors));

    // Get appointment
    $stmt = $conn->prepare("
        SELECT a.*, p.name as patient_name
        FROM Appointments a
        JOIN Patients p ON a.patient_id = p.patient_id
        WHERE a.appointment_id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    if (!$appointment) json_out(false, 'Appointment not found');

    // Upsert bill
    $existing = $conn->prepare("SELECT bill_id FROM Bills WHERE appointment_id = ?");
    $existing->execute([$appointment_id]);
    $bill = $existing->fetch();

    if ($bill) {
        $conn->prepare("UPDATE Bills SET amount=?, payment_method=?, status=? WHERE appointment_id=?")
             ->execute([$amount, $payment_method, $status, $appointment_id]);
        $bill_id = $bill['bill_id'];
        $action  = 'updated';
    } else {
        $conn->prepare("INSERT INTO Bills (appointment_id, patient_id, amount, status, date_issued, payment_method) VALUES (?,?,?,?,?,?)")
             ->execute([$appointment_id, $appointment['patient_id'], $amount, $status, date('Y-m-d'), $payment_method]);
        $bill_id = (int)$conn->lastInsertId();
        $action  = 'generated';
    }

    $detail = $conn->prepare("
        SELECT b.*, p.name as patient_name
        FROM Bills b
        JOIN Patients p ON b.patient_id = p.patient_id
        WHERE b.bill_id = ?
    ");
    $detail->execute([$bill_id]);

    json_out(true, "Bill {$action} successfully!", $detail->fetch());

} catch (PDOException $e) {
    error_log("Billing error: " . $e->getMessage());
    json_out(false, 'Database error occurred.', null, 500);
} catch (Exception $e) {
    error_log("Billing general error: " . $e->getMessage());
    json_out(false, 'An unexpected error occurred.', null, 500);
}
