<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

// Enforce Superadmin access only
$admin_query = $conn->prepare("SELECT role FROM admin WHERE name = ? LIMIT 1");
$admin_query->bind_param("s", $_SESSION['admin_name']);
$admin_query->execute();
$admin_role = $admin_query->get_result()->fetch_assoc()['role'] ?? 'superadmin';

if ($admin_role !== 'superadmin') {
    header("Location: dashboard.php?error=" . urlencode("Unauthorized action."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    
    if (!empty($id)) {
        $stmt = $conn->prepare("DELETE FROM colleges WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: dashboard.php?status=" . urlencode("College successfully deleted. Associated employees have been unassigned."));
        } else {
            header("Location: dashboard.php?error=" . urlencode("Failed to delete the college."));
        }
    } else {
        header("Location: dashboard.php?error=" . urlencode("Invalid College ID."));
    }
    exit;
}
?>