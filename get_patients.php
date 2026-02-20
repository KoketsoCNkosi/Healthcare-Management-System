

check_authentication('admin');

try {
    $search = sanitize_input($_GET['search'] ?? '');
    $sql    = "SELECT patient_id, name, dob, gender, address, contact, email, emergency_contact, insurance_info, created_at FROM Patients WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR contact LIKE ? OR email LIKE ?)";
        $params = ["%$search%","%$search%","%$search%"];
    }
    $sql .= " ORDER BY name ASC LIMIT 200";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    json_out(true, 'Patients retrieved', $stmt->fetchAll());
} catch (Exception $e) {
    error_log("Get patients error: " . $e->getMessage());
    json_out(false, 'Failed to retrieve patients', null, 500);
}
