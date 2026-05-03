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
        
        // 2. Alternating Time In / Time Out logic
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

        // 3. IMAGE HANDLING (uploads/)
        $photo_path = null;
        if (!empty($photo_data)) {
            
            // Navigate to uploads/
            $base_dir = dirname(__DIR__); 
            $upload_dir = $base_dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $image_parts = explode(";base64,", $photo_data);
            if (count($image_parts) == 2) {
                $image_base64 = base64_decode($image_parts[1]);
                
                // REMOVED "selfie_" prefix - just using ID and timestamp string
                $filename = $idnumber . '_' . time() . '.jpg';
                $filepath = $upload_dir . $filename;

                if (file_put_contents($filepath, $image_base64)) {
                    // Path to save in database for retrieval
                    $photo_path = 'uploads/' . $filename; 
                } else {
                    header("Location: index.php?error=Failed to write image to uploads folder.");
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