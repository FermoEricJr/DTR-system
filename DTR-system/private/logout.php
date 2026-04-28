<?php
session_start();
// Destroy all session data to securely log out the admin
session_destroy();
header("Location: ../public/login.php");
exit;
?>