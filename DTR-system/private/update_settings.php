<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $college_id = $_POST['college_id'] ?? '';
    $morning_cutoff = $_POST['morning_cutoff'] ?? '12:00:00';
    $afternoon_cutoff = $_POST['afternoon_cutoff'] ?? '13:00:00';
    
    if (empty($college_id)) {
        header("Location: dashboard.php?error=" . urlencode("Please select a college."));
        exit;
    }

    // Verify admin permissions
    $admin_query = $conn->prepare("SELECT role, college_id FROM admin WHERE name = ? LIMIT 1");
    $admin_query->bind_param("s", $_SESSION['admin_name']);
    $admin_query->execute();
    $admin_data = $admin_query->get_result()->fetch_assoc();
    
    if (($admin_data['role'] ?? 'superadmin') === 'college_admin' && $admin_data['college_id'] != $college_id) {
        header("Location: dashboard.php?error=" . urlencode("Unauthorized action for this college."));
        exit;
    }

    // Basic validation to ensure it's a valid time format (HH:MM / HH:MM:SS)
    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $morning_cutoff) && 
        preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $afternoon_cutoff)) {
        
        $stmt = $conn->prepare("UPDATE colleges SET morning_cutoff = ?, afternoon_cutoff = ? WHERE id = ?");
        $stmt->bind_param("ssi", $morning_cutoff, $afternoon_cutoff, $college_id);
        $stmt->execute();

        header("Location: dashboard.php?status=" . urlencode("Cutoff times updated successfully."));
        exit;
    }
    header("Location: dashboard.php?error=" . urlencode("Failed to update cutoff times. Invalid format."));
    exit;
}
?>