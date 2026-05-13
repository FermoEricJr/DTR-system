<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $morning_cutoff = $_POST['morning_cutoff'] ?? '12:00:00';
    $afternoon_cutoff = $_POST['afternoon_cutoff'] ?? '13:00:00';
    
    // Basic validation to ensure it's a valid time format (HH:MM / HH:MM:SS)
    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $morning_cutoff) && 
        preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $afternoon_cutoff)) {
        
        // Update or Insert morning_cutoff
        $stmt1 = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('morning_cutoff', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt1->bind_param("ss", $morning_cutoff, $morning_cutoff);
        $stmt1->execute();

        // Update or Insert afternoon_cutoff
        $stmt2 = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('afternoon_cutoff', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt2->bind_param("ss", $afternoon_cutoff, $afternoon_cutoff);
        $stmt2->execute();

        header("Location: dashboard.php?status=" . urlencode("Cutoff times updated successfully."));
        exit;
    }
    header("Location: dashboard.php?error=" . urlencode("Failed to update cutoff times. Invalid format."));
    exit;
}
?>