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
    $status = '';
    $error = '';

    // 1. Validate format on server-side (allows 4 digits, hyphen, then 4 or 5 digits)
    if (!preg_match('/^[0-9]{4}-[0-9]{4,5}$/', $idnumber)) {
        $error = "Invalid ID Number format. Must be XXXX-XXXX or XXXX-XXXXX.";
    } 
    // 2. Check if name is empty
    elseif (empty(trim($name))) {
        $error = "Name cannot be empty.";
    }
    else {
        // 3. Check if ID number already exists
        $stmt = $conn->prepare("SELECT id FROM user WHERE idnumber = ?");
        $stmt->bind_param("s", $idnumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "User with this ID Number already exists.";
        } else {
            // 4. Insert new user
            $insert_stmt = $conn->prepare("INSERT INTO user (idnumber, name) VALUES (?, ?)");
            $insert_stmt->bind_param("ss", $idnumber, $name);
            if ($insert_stmt->execute()) {
                $status = "User " . htmlspecialchars($name) . " added successfully.";
            } else {
                $error = "Failed to add user to the database.";
            }
        }
    }

    // Redirect back with message and scroll to the user management section
    $redirect_url = !empty($error) ? "dashboard.php?error=" . urlencode($error) : "dashboard.php?status=" . urlencode($status);
    header("Location: " . $redirect_url . "#user-management");
    exit;
}
?>