<?php
    // Set default timezone for the Philippines (WMSU)
    date_default_timezone_set('Asia/Manila');

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "dtrsystem";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Synchronize MySQL timezone with PHP to ensure accurate CURRENT_TIMESTAMP
    $conn->query("SET time_zone = '+08:00'");
?>