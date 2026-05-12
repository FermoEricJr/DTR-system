<?php
include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idnumber = $_POST['idnumber'] ?? '';
    $action = $_POST['action'] ?? ''; 
    $photo_data = $_POST['photo'] ?? ''; 

    // 1. Check if the user exists
    $stmt = $conn->prepare("SELECT id, name FROM user WHERE idnumber = ?");
    $stmt->bind_param("s", $idnumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // --- NEW FEATURE: SESSION LIMIT LOGIC ---
        $current_hour = (int)date('H'); // 24-hour format
        $today = date('Y-m-d');
        
        // Define session boundaries (Adjust hours as needed)
        // AM: 00:00 - 11:59 | PM: 12:00 - 23:59
        $is_am = ($current_hour < 12);
        $start_time = $is_am ? "$today 00:00:00" : "$today 12:00:00";
        $end_time = $is_am ? "$today 11:59:59" : "$today 23:59:59";
        $session_name = $is_am ? "Morning" : "Afternoon";

        // Check if this action already exists for this user in this session
        $session_check = $conn->prepare("SELECT id FROM records WHERE idnumber = ? AND record_type = ? AND timestamp BETWEEN ? AND ?");
        $session_check->bind_param("ssss", $idnumber, $action, $start_time, $end_time);
        $session_check->execute();
        $session_result = $session_check->get_result();

        if ($session_result->num_rows > 0) {
            header("Location: index.php?error=You have already recorded a $action for the $session_name session.");
            exit;
        }
        // --- END OF SESSION LIMIT LOGIC ---

        // 2. Alternating Time In / Time Out logic (Keep this for sequential integrity)
        $check_stmt = $conn->prepare("SELECT record_type FROM records WHERE idnumber = ? ORDER BY timestamp DESC LIMIT 1");
        $check_stmt->bind_param("s", $idnumber);
        $check_stmt->execute();
        $last_record_result = $check_stmt->get_result();
        
        $last_action = null;
        if ($last_record_result->num_rows > 0) {
            $last_record = $last_record_result->fetch_assoc();
            $last_action = $last_record['record_type'];
        }
        
        if ($action === 'timeout' && $last_action !== 'timein') {
            header("Location: index.php?error=You must Time In before you can Time Out.");
            exit;
        }
        if ($action === 'timein' && $last_action === 'timein') {
            header("Location: index.php?error=You are already Timed In.");
            exit;
        }

        // 3. IMAGE HANDLING
        $photo_path = null;
        if (!empty($photo_data)) {
            $base_dir = dirname(__DIR__); 
            $upload_dir = $base_dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $image_parts = explode(";base64,", $photo_data);
            if (count($image_parts) == 2) {
                $image_base64 = base64_decode($image_parts[1]);
                $filename = $idnumber . '_' . time() . '.jpg';
                $filepath = $upload_dir . $filename;

                if (file_put_contents($filepath, $image_base64)) {
                    $photo_path = 'uploads/' . $filename; 
                } else {
                    header("Location: index.php?error=Failed to write image.");
                    exit;
                }
            }
        }

        // 4. Record to Database
        $insert_stmt = $conn->prepare("INSERT INTO records (idnumber, record_type, photo_path) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("sss", $idnumber, $action, $photo_path);
        
        if ($insert_stmt->execute()) {
            header("Location: index.php?status=Successfully recorded $action for " . urlencode($user['name']));
        } else {
            header("Location: index.php?error=Database error.");
        }
    } else {
        header("Location: index.php?error=ID Number not found.");
    }
    exit;
}
?>