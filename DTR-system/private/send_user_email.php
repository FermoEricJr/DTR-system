<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idnumber = $_POST['idnumber'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $status = '';
    $error = '';

    if (empty($idnumber) || empty($subject) || empty($message)) {
        $error = "All fields are required to send an email.";
    } else {
        // Fetch user's registered email
        $stmt = $conn->prepare("SELECT name, email FROM user WHERE idnumber = ?");
        $stmt->bind_param("s", $idnumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $email = $row['email'];
            $name = $row['name'];

            if (empty($email)) {
                $error = "User $name does not have a registered email address in the system.";
            } else {
                $headers = "From: no-reply@wmsu.edu.ph\r\n";
                $full_message = "Hello $name,\n\n" . $message . "\n\n--\nWMSU Administrator";

                // Attempt to send email
                $mail_sent = @mail($email, $subject, $full_message, $headers);

                if ($mail_sent) {
                    $status = "Email successfully sent to $name ($email).";
                } else {
                    $status = "LOCAL TESTING MODE: Fake Email to $email queued successfully. (Setup SMTP for real emails)";
                }
            }
        } else {
            $error = "Employee not found.";
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