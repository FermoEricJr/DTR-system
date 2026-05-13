<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idnumber = $_POST['idnumber'] ?? '';
    $name = $_POST['name'] ?? '';
    $position = $_POST['position'] ?? 'Employee';
    $college_id = $_POST['college_id'] ?? null;
    $status = '';
    $error = '';

    // Check if name is empty
    if (empty(trim($name))) {
        $error = "Name cannot be empty.";
    } else {
        // Update the user details
        $stmt = $conn->prepare("UPDATE user SET name = ?, position = ?, college_id = ? WHERE idnumber = ?");
        $stmt->bind_param("ssis", $name, $position, $college_id, $idnumber);
        
        if ($stmt->execute()) {
            $status = "User " . htmlspecialchars($name) . "'s profile updated successfully.";
        } else {
            $error = "Failed to update user profile in the database.";
        }
    }

    // Redirect back to the dashboard targeting the specific user
    $redirect_url = "dashboard.php?idnumber=" . urlencode($idnumber);
    if (!empty($error)) {
        $redirect_url .= "&error=" . urlencode($error);
    } else {
        $redirect_url .= "&status=" . urlencode($status);
    }
    header("Location: " . $redirect_url);
    exit;
}
?>