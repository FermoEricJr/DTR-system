<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9._%+\-]+@wmsu\.edu\.ph$/', $email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email domain. Must be @wmsu.edu.ph']);
        exit;
    }

    // Generate 6 digit code
    $code = sprintf("%06d", mt_rand(1, 999999));
    $_SESSION['verification_code'] = $code;
    $_SESSION['verification_email'] = $email;

    $subject = "DTR System - Employee Registration Verification";
    $message = "Your verification code to add a new employee is: $code\n\nIf you did not request this, please ignore this email.";
    $headers = "From: no-reply@wmsu.edu.ph\r\n";

    // Attempt to send the email
    $mail_sent = @mail($email, $subject, $message, $headers);

    if ($mail_sent) {
        echo json_encode(['success' => true]);
    } else {
        // FALLBACK FOR LOCAL XAMPP TESTING
        // Because XAMPP cannot send emails out-of-the-box without config, we return the code directly for testing purposes.
        echo json_encode([
            'success' => true, 
            'message' => "LOCAL TESTING MODE (mail failed):\nYour verification code is: $code\n\n(Note: Setup SMTP to send actual emails)"
        ]);
    }
}
?>