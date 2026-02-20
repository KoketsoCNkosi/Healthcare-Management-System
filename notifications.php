
try {
    $notifications = [];
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    if ($user['user_type'] === 'admin' || $user['user_type'] === 'doctor') {
        $did = $user['user_type'] === 'doctor' ? $user['user_id'] : null;

        // Overdue bills
        $sql = "SELECT b.bill_id, b.amount, p.name as patient_name FROM Bills b JOIN Patients p ON b.patient_id=p.patient_id WHERE b.status='Pending' AND b.date_issued < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        if ($did) $sql .= " AND b.appointment_id IN (SELECT appointment_id FROM Appointments WHERE doctor_id=$did)";
        $sql .= " LIMIT 5";
        $overdue = $conn->query($sql)->fetchAll();
        foreach ($overdue as $b) {
            $notifications[] = ['type'=>'warning','icon'=>'💰','title'=>'Overdue Bill','message'=>"Bill #".$b['bill_id']." for ".$b['patient_name']." (R".number_format($b['amount'],2).") is overdue",'time'=>'30+ days ago'];
        }

        // Tomorrow's appointments
        $sql = "SELECT a.appointment_id, a.time, p.name as patient_name FROM Appointments a JOIN Patients p ON a.patient_id=p.patient_id WHERE a.date=? AND a.status='Scheduled'";
        $params = [$tomorrow];
        if ($did) { $sql .= " AND a.doctor_id=?"; $params[] = $did; }
        $stmt = $conn->prepare($sql); $stmt->execute($params);
        $tmrw = $stmt->fetchAll();
        foreach ($tmrw as $a) {
            $notifications[] = ['type'=>'info','icon'=>'📅','title'=>"Tomorrow's Appointment",'message'=>$a['patient_name']." at ".$a['time'],'time'=>'Tomorrow'];
        }
    }

    if ($user['user_type'] === 'patient') {
        $pid = $user['user_id'];
        // Upcoming appointments
        $stmt = $conn->prepare("SELECT a.date, a.time, d.name as doctor_name FROM Appointments a JOIN Doctors d ON a.doctor_id=d.doctor_id WHERE a.patient_id=? AND a.date BETWEEN ? AND DATE_ADD(?,INTERVAL 7 DAY) AND a.status='Scheduled' ORDER BY a.date ASC LIMIT 3");
        $stmt->execute([$pid, $today, $today]);
        foreach ($stmt->fetchAll() as $a) {
            $notifications[] = ['type'=>'info','icon'=>'📅','title'=>'Upcoming Appointment','message'=>"With ".$a['doctor_name']." on ".$a['date']." at ".$a['time'],'time'=>$a['date']];
        }
        // Pending bills
        $stmt = $conn->prepare("SELECT b.amount, b.date_issued FROM Bills b WHERE b.patient_id=? AND b.status='Pending' ORDER BY b.date_issued DESC LIMIT 3");
        $stmt->execute([$pid]);
        foreach ($stmt->fetchAll() as $b) {
            $notifications[] = ['type'=>'warning','icon'=>'💳','title'=>'Pending Bill','message'=>"R".number_format($b['amount'],2)." due since ".$b['date_issued'],'time'=>$b['date_issued']];
        }
    }

    json_out(true, 'Notifications retrieved', $notifications);
} catch (Exception $e) {
    error_log("Notifications error: " . $e->getMessage());
    json_out(false, 'Failed to load notifications', null, 500);
}
