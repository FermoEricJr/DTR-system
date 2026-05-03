<?php
session_start();
include '../include/dbcon.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idnumber = $_POST['idnumber'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admin WHERE idnumber = ?");
    $stmt->bind_param("s", $idnumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password']) || $password === $row['password']) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_name'] = $row['name'];
            header("Location: ../private/dashboard.php");
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Admin ID not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - WMSU DTR</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <div class="login-overlay"></div>
    <div class="login-card">
        <h2>Admin Login</h2>
        <p class="subtitle">WMSU Daily Time Record System</p>
        
        <?php if ($error): ?><div class="alert-error">&#9888; <?= $error ?></div><?php endif; ?>
        
        <form method="POST">
            <label>Admin ID:</label>
            <input type="text" name="idnumber" required>
            <label>Password:</label>
            <input type="password" name="password" required>
            <button type="submit" style="width: 100%;">Login</button>
        </form>
        
        <div style="margin-top: 25px; font-size: 14px;">
            <a href="index.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">&larr; Back to Public Portal</a>
        </div>
    </div>
</body>
</html>