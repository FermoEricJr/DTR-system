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
    $email = $_POST['email'] ?? '';
    $verification_code = $_POST['verification_code'] ?? '';
    $status = '';
    $error = '';

    // 1. Check if email or verification code is empty
    if (empty(trim($email)) || empty(trim($verification_code))) {
        $error = "Please input code or email.";
    }
    // 2. Check verification code validity
    elseif ($verification_code !== ($_SESSION['verification_code'] ?? '') || 
        $email !== ($_SESSION['verification_email'] ?? '')) {
        $error = "Invalid or expired verification code.";
    }
    // 3. Validate format on server-side (allows 4 digits, hyphen, then 4 or 5 digits)
    elseif (!preg_match('/^[0-9]{4}-[0-9]{4,5}$/', $idnumber)) {
        $error = "Invalid ID Number format. Must be XXXX-XXXX or XXXX-XXXXX.";
    } 
    // 4. Check if name is empty
    elseif (empty(trim($name))) {
        $error = "Name cannot be empty.";
    }
    else {
        // 5. Check if ID number already exists
        $stmt = $conn->prepare("SELECT id FROM user WHERE idnumber = ?");
        $stmt->bind_param("s", $idnumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "User with this ID Number already exists.";
        } else {
            // Ensure email column exists in user table
            $check_col = $conn->query("SHOW COLUMNS FROM user LIKE 'email'");
            if ($check_col->num_rows == 0) {
                $conn->query("ALTER TABLE user ADD COLUMN email VARCHAR(255) NULL");
            }

            // 5. Insert new user with the verified email
            $insert_stmt = $conn->prepare("INSERT INTO user (idnumber, name, position, email) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssss", $idnumber, $name, $position, $email);
            if ($insert_stmt->execute()) {
                $status = "User " . htmlspecialchars($name) . " added successfully.";
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_email']);
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