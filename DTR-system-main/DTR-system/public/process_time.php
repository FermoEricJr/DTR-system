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
        
        // Get the user's last recorded action
        $check_stmt = $conn->prepare("SELECT record_type FROM records WHERE idnumber = ? ORDER BY timestamp DESC LIMIT 1");
        $check_stmt->bind_param("s", $idnumber);
        $check_stmt->execute();
        $last_record_result = $check_stmt->get_result();
        
        $last_action = null;
        if ($last_record_result->num_rows > 0) {
            $last_record = $last_record_result->fetch_assoc();
            $last_action = $last_record['record_type'];
        }
        
        // Enforce alternating Time In / Time Out
        if ($action === 'timeout' && $last_action !== 'timein') {
            header("Location: index.php?error=You must Time In before you can Time Out.");
            exit;
        }
        if ($action === 'timein' && $last_action === 'timein') {
            header("Location: index.php?error=You are already Timed In. Please Time Out first.");
            exit;
        }

        // Ensure photo_path column exists
        $check_col = $conn->query("SHOW COLUMNS FROM records LIKE 'photo_path'");
        if ($check_col->num_rows == 0) {
            $conn->query("ALTER TABLE records ADD COLUMN photo_path VARCHAR(255) NULL");
        }

        // Process photo if provided
        $photo_path = null;
        if (!empty($_POST['photo'])) {
            $base64_string = $_POST['photo'];
            $image_parts = explode(";base64,", $base64_string);
            if (count($image_parts) == 2) {
                $image_base64 = base64_decode($image_parts[1]);
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $filename = $idnumber . '_' . time() . '.jpg';
                $filepath = $upload_dir . $filename;
                if (file_put_contents($filepath, $image_base64)) {
                    // Save relative path for web access
                    $photo_path = 'uploads/' . $filename;
                }
            }
        }

        // User exists, record their time
        $insert_stmt = $conn->prepare("INSERT INTO records (idnumber, record_type, photo_path) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("sss", $idnumber, $action, $photo_path);
        
        if ($insert_stmt->execute()) {
            header("Location: index.php?status=Successfully recorded $action for " . urlencode($user['name']));
        } else {
            header("Location: index.php?error=Failed to save record to the database");
        }
    } else {
        header("Location: index.php?error=ID Number not found in the system");
    }
    exit;
}
?>