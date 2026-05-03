<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idnumber = $_POST['idnumber'] ?? '';
    $status = '';
    $error = '';

    if (empty($idnumber)) {
        $error = "Invalid ID Number provided.";
    } else {
        // The 'records' table has ON DELETE CASCADE, 
        // so deleting the user will automatically remove their historical logs.
        $stmt = $conn->prepare("DELETE FROM user WHERE idnumber = ?");
        $stmt->bind_param("s", $idnumber);
        
        if ($stmt->execute()) {
            $status = "Employee and their records have been successfully deleted.";
        } else {
            $error = "Failed to delete the employee from the database.";
        }
    }

    // Redirect back to the dashboard, clearing the selected user context
    $redirect_url = "dashboard.php";
    if (!empty($error)) {
        $redirect_url .= "?error=" . urlencode($error);
    } else {
        $redirect_url .= "?status=" . urlencode($status);
    }
    header("Location: " . $redirect_url);
    exit;
}
?>