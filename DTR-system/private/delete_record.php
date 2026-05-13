<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id = $_POST['record_id'] ?? '';
    
    // Clean up the referer URL so we don't stack success/error messages
    $referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    $referer = preg_replace('/[&?]status=[^&]*/', '', $referer);
    $referer = preg_replace('/[&?]error=[^&]*/', '', $referer);
    $separator = (parse_url($referer, PHP_URL_QUERY) == NULL) ? '?' : '&';

    if (empty($record_id)) {
        header("Location: " . $referer . $separator . "error=" . urlencode("Invalid record ID."));
        exit;
    }
    
    $stmt = $conn->prepare("SELECT photo_path FROM records WHERE id = ?");
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['photo_path'])) {
            $path1 = dirname(__DIR__) . '/' . $row['photo_path'];
            $path2 = dirname(__DIR__) . '/public/' . $row['photo_path'];
            if (file_exists($path1)) { unlink($path1); }
            if (file_exists($path2)) { unlink($path2); }
        }
        
        $del_stmt = $conn->prepare("DELETE FROM records WHERE id = ?");
        $del_stmt->bind_param("i", $record_id);
        $del_stmt->execute();
        
        header("Location: " . $referer . $separator . "status=" . urlencode("Record deleted successfully."));
    } else {
        header("Location: " . $referer . $separator . "error=" . urlencode("Record not found."));
    }
}
?>