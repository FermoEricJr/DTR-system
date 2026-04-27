<?php
include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idnumber = $_POST['idnumber'] ?? '';
    $action = $_POST['action'] ?? ''; // 'timein' or 'timeout'

    // Check if the user exists in the database
    $stmt = $conn->prepare("SELECT id, name FROM user WHERE idnumber = ?");
    $stmt->bind_param("s", $idnumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if user is trying to timeout without timing in first
        if ($action === 'timeout') {
            $check_stmt = $conn->prepare("SELECT record_type FROM records WHERE idnumber = ? ORDER BY timestamp DESC LIMIT 1");
            $check_stmt->bind_param("s", $idnumber);
            $check_stmt->execute();
            $last_record_result = $check_stmt->get_result();
            
            $can_timeout = false;
            if ($last_record_result->num_rows > 0) {
                $last_record = $last_record_result->fetch_assoc();
                if ($last_record['record_type'] === 'timein') {
                    $can_timeout = true;
                }
            }
            
            if (!$can_timeout) {
                header("Location: ../public/index.php?error=You must Time In before you can Time Out.");
                exit;
            }
        }

        // User exists, record their time
        $insert_stmt = $conn->prepare("INSERT INTO records (idnumber, record_type) VALUES (?, ?)");
        $insert_stmt->bind_param("ss", $idnumber, $action);
        
        if ($insert_stmt->execute()) {
            header("Location: ../public/index.php?status=Successfully recorded $action for " . urlencode($user['name']));
        } else {
            header("Location: ../public/index.php?error=Failed to save record to the database");
        }
    } else {
        header("Location: ../public/index.php?error=ID Number not found in the system");
    }
    exit;
}
?>