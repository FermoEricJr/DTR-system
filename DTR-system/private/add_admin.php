<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: ../public/login.php"); exit; }
include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idnumber = trim($_POST['idnumber'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $college_id = $_POST['college_id'] ?? null;
    
    if (!empty($idnumber) && !empty($name) && !empty($password) && !empty($college_id)) {
        $stmt = $conn->prepare("INSERT INTO admin (idnumber, name, password, role, college_id) VALUES (?, ?, ?, 'college_admin', ?)");
        $stmt->bind_param("sssi", $idnumber, $name, $password, $college_id);
        if ($stmt->execute()) {
            header("Location: dashboard.php?status=" . urlencode("College Admin added successfully."));
        } else {
            header("Location: dashboard.php?error=" . urlencode("Failed to add admin. Username/ID might exist."));
        }
    } else {
        header("Location: dashboard.php?error=" . urlencode("All fields are required."));
    }
    exit;
}
?>
