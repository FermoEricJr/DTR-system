<?php
    include '../include/dbcon.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <header>
        <h1>Western Mindanao State University</h1>
        <p>Daily Time Record System</p>
        
        <!-- Admin Cogwheel Icon -->
        <a href="login.php" class="admin-cog" title="Admin Login">
            &#9881;
        </a>
    </header>

    <div class="container">
        <h2>Welcome!</h2>
        
        <div id="real-time-clock" class="clock"></div>

        <?php
        // Display success or error messages from the redirect
        if (isset($_GET['status'])) {
            echo "<div class='alert-success'>&#10004; " . htmlspecialchars($_GET['status']) . "</div>";
        }
        if (isset($_GET['error'])) {
            echo "<div class='alert-error'>&#9888; " . htmlspecialchars($_GET['error']) . "</div>";
        }
        ?>

        <form action="process_time.php" method="POST">
            <label for="idnumber">Enter ID Number:</label>
            <input type="text" id="idnumber" name="idnumber" required placeholder="e.g. 1234-5678" pattern="[0-9]{4}-[0-9]{4,5}" title="Please use the format XXXX-XXXX or XXXX-XXXXX" oninput="formatIdNumber(this)">
            <div class="button-group">
                <button type="submit" name="action" value="timein">Time In</button>
                <button type="submit" name="action" value="timeout">Time Out</button>
            </div>
        </form>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            document.getElementById('real-time-clock').textContent = now.toLocaleDateString('en-US', options);
        }
        setInterval(updateClock, 1000);
        updateClock();

        function formatIdNumber(input) {
            // Remove all non-digit characters
            let value = input.value.replace(/\D/g, '');
            if (value.length > 4) {
                value = value.substring(0, 4) + '-' + value.substring(4, 9);
            }
            input.value = value;
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success, .alert-error');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 500); // Hide completely after fade
            });
        }, 5000);
    </script>
</body>
</html>