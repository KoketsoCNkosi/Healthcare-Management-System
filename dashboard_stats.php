<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';

$user = check_authentication();

try {
    $stats = [];
    $today = date('Y-m-d');

    if ($user['user_type'] === 'admin') {
        // Total patients
        $stats['total_patients'] = (int)$conn->query("SELECT COUNT(*) FROM Patients")->fetchColumn();
        // Total doctors
        $stats['total_doctors'] = (int)$conn->query("SELECT COUNT(*) FROM Doctors WHERE status='Active'")->fetchColumn();
        // Appointments today
        $stats['appointments_today'] = (int)$conn->prepare("SELECT COUNT(*) FROM Appointments WHERE date=?")->execute([$today]) ? (int)$conn->query("SELECT COUNT(*) FROM Appointments WHERE date='$today'")->fetchColumn() : 0;
        // Revenue this month
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM Bills WHERE status='Paid' AND MONTH(date_issued)=MONTH(CURDATE()) AND YEAR(date_issued)=YEAR(CURDATE())");
        $stmt->execute(); $stats['revenue_month'] = (float)$stmt->fetchColumn();
        // Pending bills
        $stats['pending_bills'] = (int)$conn->query("SELECT COUNT(*) FROM Bills WHERE status='Pending'")->fetchColumn();
        // Scheduled appointments
        $stats['scheduled'] = (int)$conn->query("SELECT COUNT(*) FROM Appointments WHERE status='Scheduled'")->fetchColumn();
        // Recent appointments
        $stmt = $conn->prepare("SELECT a.appointment_id, a.date, a.time, a.status, p.name as patient_name, d.name as doctor_name, d.specialization FROM Appointments a JOIN Patients p ON a.patient_id=p.patient_id JOIN Doctors d ON a.doctor_id=d.doctor_id ORDER BY a.date DESC, a.time DESC LIMIT 8");
        $stmt->execute(); $stats['recent_appointments'] = $stmt->fetchAll();
        // Revenue by month (last 6)
        $stmt = $conn->query("SELECT DATE_FORMAT(date_issued,'%b') as month, COALESCE(SUM(amount),0) as total FROM Bills WHERE status='Paid' AND date_issued >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY MONTH(date_issued), DATE_FORMAT(date_issued,'%b') ORDER BY MIN(date_issued)");
        $stats['revenue_chart'] = $stmt->fetchAll();

    } elseif ($user['user_type'] === 'doctor') {
        $did = $user['user_id'];
        $stats['my_today']     = (int)$conn->prepare("SELECT COUNT(*) FROM Appointments WHERE doctor_id=? AND date=?")->execute([$did,$today]) ? (int)$conn->query("SELECT COUNT(*) FROM Appointments WHERE doctor_id=$did AND date='$today'")->fetchColumn() : 0;
        $stats['my_total']     = (int)$conn->query("SELECT COUNT(*) FROM Appointments WHERE doctor_id=$did")->fetchColumn();
        $stats['my_completed'] = (int)$conn->query("SELECT COUNT(*) FROM Appointments WHERE doctor_id=$did AND status='Completed'")->fetchColumn();
        $stats['my_pending']   = (int)$conn->query("SELECT COUNT(*) FROM Appointments WHERE doctor_id=$did AND status='Scheduled'")->fetchColumn();
        // Upcoming
        $stmt = $conn->prepare("SELECT a.appointment_id,a.date,a.time,a.status,a.remarks,p.name as patient_name,p.contact FROM Appointments a JOIN Patients p ON a.patient_id=p.patient_id WHERE a.doctor_id=? AND a.date>=? AND a.status='Scheduled' ORDER BY a.date ASC,a.time ASC LIMIT 10");
        $stmt->execute([$did,$today]); $stats['upcoming'] = $stmt->fetchAll();
        // Calendar data for this month
        $stmt = $conn->prepare("SELECT date, COUNT(*) as count FROM Appointments WHERE doctor_id=? AND MONTH(date)=MONTH(CURDATE()) GROUP BY date");
        $stmt->execute([$did]); $stats['calendar'] = $stmt->fetchAll();

    } elseif ($user['user_type'] === 'patient') {
        $pid = $user['user_id'];
        $stats['my_appointments'] = (int)$conn->query("SELECT COUNT(*) FROM Appointments WHERE patient_id=$pid")->fetchColumn();
        $stats['my_records']      = (int)$conn->query("SELECT COUNT(*) FROM MedicalRecords WHERE patient_id=$pid")->fetchColumn();
        $stats['total_billed']    = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM Bills WHERE patient_id=$pid")->fetchColumn();
        $stats['unpaid']          = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM Bills WHERE patient_id=$pid AND status='Pending'")->fetchColumn();
        $stmt = $conn->prepare("SELECT a.appointment_id,a.date,a.time,a.status,a.remarks,d.name as doctor_name,d.specialization FROM Appointments a JOIN Doctors d ON a.doctor_id=d.doctor_id WHERE a.patient_id=? ORDER BY a.date DESC LIMIT 5");
        $stmt->execute([$pid]); $stats['recent_appointments'] = $stmt->fetchAll();
    }

    json_out(true, 'Stats retrieved', $stats);
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    json_out(false, 'Failed to load stats', null, 500);
}
