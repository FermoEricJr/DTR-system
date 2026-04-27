<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

// Set headers to trigger file download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="DTR_Sheet_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
// Write the column headers
fputcsv($output, ['Record ID', 'ID Number', 'Name', 'Record Type', 'Timestamp']);

$dl_user = $_GET['dl_user'] ?? 'all';
$dl_time = $_GET['dl_time'] ?? 'all';

$where_clauses = [];
$params = [];
$types = '';

if ($dl_user !== 'all') {
    $where_clauses[] = "r.idnumber = ?";
    $params[] = $dl_user;
    $types .= 's';
}

if ($dl_time === 'weekly') {
    $where_clauses[] = "YEARWEEK(r.timestamp, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($dl_time === 'monthly') {
    $where_clauses[] = "MONTH(r.timestamp) = MONTH(CURDATE()) AND YEAR(r.timestamp) = YEAR(CURDATE())";
} elseif ($dl_time === 'yearly') {
    $where_clauses[] = "YEAR(r.timestamp) = YEAR(CURDATE())";
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

$query = "SELECT r.id, r.idnumber, u.name, r.record_type, r.timestamp FROM records r LEFT JOIN user u ON r.idnumber = u.idnumber $where_sql ORDER BY r.timestamp DESC";

if ($types) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}
fclose($output);
?>