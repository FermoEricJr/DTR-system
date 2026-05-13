<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: ../public/login.php"); exit; }
include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $college_name = trim($_POST['college_name'] ?? '');
    
    if (!empty($college_name)) {
        $stmt = $conn->prepare("INSERT INTO colleges (name) VALUES (?)");
        $stmt->bind_param("s", $college_name);
        if ($stmt->execute()) {
            header("Location: dashboard.php?status=" . urlencode("College added successfully."));
        } else {
            header("Location: dashboard.php?error=" . urlencode("Failed to add college. It might already exist."));
        }
    } else {
        header("Location: dashboard.php?error=" . urlencode("College name cannot be empty."));
    }
    exit;
}
?>
