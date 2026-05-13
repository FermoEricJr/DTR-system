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
    $name = trim($_POST['name'] ?? '');
    
    if (!empty($id) && !empty($name)) {
        $stmt = $conn->prepare("UPDATE colleges SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        if ($stmt->execute()) {
            header("Location: dashboard.php?status=" . urlencode("College updated successfully."));
        } else {
            header("Location: dashboard.php?error=" . urlencode("Failed to update college. The name might already exist."));
        }
    } else {
        header("Location: dashboard.php?error=" . urlencode("College name cannot be empty."));
    }
    exit;
}
?>