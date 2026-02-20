<?php
require_once __DIR__ . '/config.php';

try {
    $search = sanitize_input($_GET['search'] ?? '');
    $spec   = sanitize_input($_GET['specialization'] ?? '');

    $sql    = "SELECT doctor_id, name, specialization, contact, email FROM Doctors WHERE status = 'Active'";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR specialization LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%";
    }
    if (!empty($spec)) {
        $sql .= " AND specialization = ?";
        $params[] = $spec;
    }
    $sql .= " ORDER BY name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    json_out(true, 'Doctors retrieved successfully', $stmt->fetchAll())
