<?php
    include '../include/dbcon.php';

    // Fetch recent activity
    $recent_query = "SELECT u.name, r.record_type, r.timestamp FROM records r JOIN user u ON r.idnumber = u.idnumber ORDER BY r.timestamp DESC LIMIT 5";
    $recent_result = $conn->query($recent_query);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/index.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    
</head>

<body>
    <header>
        <div class="branding">
            <h1>Western Mindanao State University</h1>
            <p>Daily Time Record System</p>
        </div>
    </header>

    <div class="main-wrapper">
        <div class="hero-section">
            <h2 style="color: white; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.67);">Attendance Logs</h2>
            <p>Welcome to the WMSU Daily Time Record System. Log your attendance quickly and securely.</p>
        </div>
        
        <div class="landing-container" style="flex-direction: column; gap: 20px; justify-content: center;">
            <div class="form-card">
                <h2>Log Your Time</h2>
                
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

                <form action="process_time.php" method="POST" id="timeForm" onsubmit="event.preventDefault(); submitAction('timein');">
                    <label class="form-label" for="idnumber">Employee ID Number:</label>
                    <input type="text" id="idnumber" name="idnumber" required placeholder="e.g. 1234-5678" pattern="[0-9]{4}-[0-9]{4,5}" title="Please use the format XXXX-XXXX or XXXX-XXXXX" oninput="formatIdNumber(this)">
                    
                    <input type="hidden" name="photo" id="photo">
                    <input type="hidden" name="action" id="action_type">
                    
                    <div class="action-button-group">
                        <button type="button" class="btn-timein" onclick="submitAction('timein')">Time In</button>
                        <button type="button" class="btn-timeout" onclick="submitAction('timeout')">Time Out</button>
                    </div>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px;">
                        <button type="button" onclick="openRecentActivityModal()" style="flex: 1; font-size: 0.95rem; padding: 12px; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; box-shadow: none; border-radius: 8px; margin: 0;">📋 View Recent Activity</button>
                        <a href="login.php" class="admin-cog-inline" title="Admin Login" style="font-size: 20px; text-decoration: none; color: #718096; transition: transform 0.3s ease, color 0.3s ease; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            &#9881;
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Activity Modal -->
<div id="recentActivityModal" class="modal">
    <div class="modal-content" style="text-align: left; max-width: 600px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; margin-bottom: 15px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #333; border: none; padding: 0;">📋 Recent Activity</h3>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0;">Name</th>
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: center;">Morning</th>
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: center;">Afternoon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_result && $recent_result->num_rows > 0): ?>
                        <?php while($row = $recent_result->fetch_assoc()): 
                            $time = strtotime($row['timestamp']);
                            $hour = (int)date('H', $time);
                            $formatted_time = date('h:i A', $time);
                            $is_am = ($hour < 12);
                        ?>
                            <tr style="border-bottom: 1px solid #f0f2f5;">
                                <td style="padding: 12px 10px;">
                                    <div style="font-weight: 600; color: #334155;"><?= htmlspecialchars($row['name']) ?></div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;"><?= date('M d, Y', $time) ?></div>
                                </td>
                                
                                <!-- Morning Column -->
                                <td style="padding: 10px; text-align: center;">
                                    <?php if ($is_am): ?>
                                        <span style="font-size: 0.7rem; padding: 4px 8px; border-radius: 4px; font-weight: 600; background: <?= $row['record_type'] === 'timein' ? '#ebf8ff' : '#fff5f5' ?>; color: <?= $row['record_type'] === 'timein' ? '#2b6cb0' : '#c53030' ?>; border: 1px solid <?= $row['record_type'] === 'timein' ? '#bee3f8' : '#fed7d7' ?>;">
                                            <?= htmlspecialchars(ucfirst($row['record_type'])) ?>: <?= $formatted_time ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;">--</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Afternoon Column -->
                                <td style="padding: 10px; text-align: center;">
                                    <?php if (!$is_am): ?>
                                        <span style="font-size: 0.7rem; padding: 4px 8px; border-radius: 4px; font-weight: 600; background: <?= $row['record_type'] === 'timein' ? '#ebf8ff' : '#fff5f5' ?>; color: <?= $row['record_type'] === 'timein' ? '#2b6cb0' : '#c53030' ?>; border: 1px solid <?= $row['record_type'] === 'timein' ? '#bee3f8' : '#fed7d7' ?>;">
                                            <?= htmlspecialchars(ucfirst($row['record_type'])) ?>: <?= $formatted_time ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;">--</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: #94a3b8; padding: 30px 0;">No recent activity yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            <button type="button" class="btn-secondary" onclick="closeRecentActivityModal()" style="width: 100%;">Close</button>
        </div>
    </div>
</div>

    <!-- Camera Modal -->
    <div id="cameraModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Take a Selfie</h3>
            <video id="video" width="100%" autoplay playsinline></video>
            <canvas id="canvas" style="display: none;"></canvas>
            <div class="button-group">
                <button type="button" onclick="capturePhoto()">Capture & Submit</button>
                <button type="button" class="btn-secondary" onclick="closeCamera()">Cancel</button>
            </div>
        </div>
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

        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const photoInput = document.getElementById('photo');
        const modal = document.getElementById('cameraModal');
        let stream = null;

        function submitAction(action) {
            const idInput = document.getElementById('idnumber');
            if (!idInput.checkValidity()) {
                idInput.reportValidity();
                return;
            }
            
            document.getElementById('action_type').value = action;
            document.getElementById('modalTitle').innerText = action === 'timein' ? 'Take a Selfie for Time In' : 'Take a Selfie for Time Out';
            
            modal.style.display = 'flex';
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(s => {
                    stream = s;
                    video.srcObject = stream;
                })
                .catch(err => {
                    console.error("Error accessing the camera: ", err);
                    alert("Could not access camera. Please ensure permissions are granted.");
                    closeCamera();
                });
        }

        function closeCamera() {
            modal.style.display = 'none';
            if (stream) stream.getTracks().forEach(track => track.stop());
        }

        function capturePhoto() {
            if (!stream) return;
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            photoInput.value = canvas.toDataURL('image/jpeg');
            closeCamera();
            document.getElementById('timeForm').submit();
        }

        function openRecentActivityModal() {
            document.getElementById('recentActivityModal').style.display = 'flex';
        }

        function closeRecentActivityModal() {
            document.getElementById('recentActivityModal').style.display = 'none';
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