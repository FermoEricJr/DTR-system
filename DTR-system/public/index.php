<?php
    include '../include/dbcon.php';

    // Fetch recent activity
    $recent_query = "SELECT u.name, r.record_type, r.timestamp FROM records r JOIN user u ON r.idnumber = u.idnumber ORDER BY r.timestamp DESC LIMIT 5";
    $recent_result = $conn->query($recent_query);

    // Fetch college cutoff times
    $colleges_cutoff_query = "SELECT name, morning_cutoff, afternoon_cutoff FROM colleges ORDER BY name ASC";
    $colleges_cutoff_result = $conn->query($colleges_cutoff_query);
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
        <img src="../assets/img/logo.png" class="header-logo">
        <h1>WESTERN MINDANAO STATE UNIVERSITY</h1>
        <p>DAILY TIME RECORD SYSTEM</p>
    </div>
</header>

<div class="main-wrapper">

    <div class="hero-section">
        <h2 style="color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.6);">Attendance Logs</h2>
        <p>Welcome to the WMSU Daily Time Record System. Log your attendance quickly and securely.</p>
    </div>

    <!-- NEW GRID LAYOUT -->
    <div class="dashboard-layout">

        <!-- LEFT: FORM -->
        <div class="form-card">
            <h2>Log Your Time</h2>

            <div id="real-time-clock" class="clock"></div>

            <?php
            if (isset($_GET['status'])) {
                echo "<div class='alert-success'>" . htmlspecialchars($_GET['status']) . "</div>";
            }
            if (isset($_GET['error'])) {
                echo "<div class='alert-error'>" . htmlspecialchars($_GET['error']) . "</div>";
            }
            ?>

            <form action="process_time.php" method="POST" id="timeForm" onsubmit="event.preventDefault(); submitAction('timein');">

                <label class="form-label">Employee ID Number:</label>
                <input type="text" id="idnumber" name="idnumber" required
                    placeholder="e.g. 1234-5678"
                    pattern="[0-9]{4}-[0-9]{4,5}"
                    oninput="formatIdNumber(this)">

                <input type="hidden" name="photo" id="photo">
                <input type="hidden" name="action" id="action_type">

                <div class="action-button-group">
                    <button type="button" class="btn-timein" onclick="submitAction('timein')">Time In</button>
                    <button type="button" class="btn-timeout" onclick="submitAction('timeout')">Time Out</button>
                </div>

                <div class="bottom-actions">
                    <button type="button" onclick="openRecentActivityModal()" class="btn-secondary">
                        View Recent Activity
                    </button>

                    <a href="login.php" class="admin-cog-inline" title="Admin Login">
                        &#9881;
                    </a>
                </div>

            </form>
        </div>

        <!-- RIGHT: INFO PANEL (NEW) -->
        <div class="info-panel">

            <div class="info-card">
                <h3>College Cutoff Times</h3>
                <div class="cutoff-list-container">
                    <?php if ($colleges_cutoff_result && $colleges_cutoff_result->num_rows > 0): ?>
                        <?php while($col = $colleges_cutoff_result->fetch_assoc()): 
                            $m_cutoff = date('h:i A', strtotime($col['morning_cutoff']));
                            $a_cutoff = date('h:i A', strtotime($col['afternoon_cutoff']));
                        ?>
                        <div style="margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                            <div style="font-weight: 600; font-size: 13px; color: #1e293b; margin-bottom: 4px;"><?= htmlspecialchars($col['name']) ?></div>
                            <div style="font-size: 12px; color: #64748b; display: flex; justify-content: space-between; gap: 10px;">
                                <span>AM Cutoff: <strong style="color: #990000;"><?= $m_cutoff ?></strong></span>
                                <span>PM Cutoff: <strong style="color: #990000;"><?= $a_cutoff ?></strong></span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="font-size: 13px; color: #64748b; margin: 0;">No colleges registered yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card">
                <h3>Guidelines</h3>
                <ul>
                    <li>Enter your correct ID number</li>
                    <li>Ensure camera access is allowed</li>
                    <li>Time-in before starting work</li>
                    <li>Time-out after completing work</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>Quick Status</h3>
                <p>System is operational.</p>
                <p>Camera verification enabled.</p>
            </div>

        </div>

    </div>
</div>

<!-- MODALS (UNCHANGED) -->

<div id="recentActivityModal" class="modal">
    <div class="modal-content" style="max-width: 600px; width: 90%;">
        <h3>Recent Activity</h3>

        <table style="width:100%;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Morning</th>
                    <th>Afternoon</th>
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
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>

                        <td>
                            <?= $is_am ? ucfirst($row['record_type']) . ": " . $formatted_time : "--" ?>
                        </td>

                        <td>
                            <?= !$is_am ? ucfirst($row['record_type']) . ": " . $formatted_time : "--" ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3">No recent activity</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <button onclick="closeRecentActivityModal()" class="btn-secondary">Close</button>
    </div>
</div>

<!-- CAMERA MODAL -->
<div id="cameraModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Take a Selfie</h3>
        <video id="video" autoplay></video>
        <canvas id="canvas" style="display:none;"></canvas>

        <div class="button-group">
            <button onclick="capturePhoto()">Capture & Submit</button>
            <button class="btn-secondary" onclick="closeCamera()">Cancel</button>
        </div>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();

    const options = {
        weekday: 'short',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    };

    document.getElementById('real-time-clock')
        .textContent = now.toLocaleString('en-US', options);
}

setInterval(updateClock, 1000);
updateClock();

function formatIdNumber(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 4) {
        value = value.substring(0, 4) + '-' + value.substring(4, 9);
    }
    input.value = value;
}

const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const photoInput = document.getElementById('photo');
let stream;

function submitAction(action) {
    document.getElementById('action_type').value = action;
    document.getElementById('cameraModal').style.display = 'flex';

    navigator.mediaDevices.getUserMedia({ video: true })
        .then(s => {
            stream = s;
            video.srcObject = stream;
        });
}

function closeCamera() {
    document.getElementById('cameraModal').style.display = 'none';
    if (stream) stream.getTracks().forEach(track => track.stop());
}

function capturePhoto() {
    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    photoInput.value = canvas.toDataURL();
    closeCamera();
    document.getElementById('timeForm').submit();
}

function openRecentActivityModal() {
    document.getElementById('recentActivityModal').style.display = 'flex';
}
function closeRecentActivityModal() {
    document.getElementById('recentActivityModal').style.display = 'none';
}
</script>

</body>
</html>
