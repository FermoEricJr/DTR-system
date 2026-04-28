<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

// Fetch data for the calendar (current month)
if (isset($_GET['month_year']) && !empty($_GET['month_year'])) {
    list($year, $month) = explode('-', $_GET['month_year']);
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
} else {
    $month = isset($_GET['month']) ? str_pad($_GET['month'], 2, '0', STR_PAD_LEFT) : date('m');
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
}

// Calculate previous and next months for navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth == 0) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth == 13) { $nextMonth = 1; $nextYear++; }

$calendar_query = "SELECT 
                    DATE(timestamp) as date, 
                    SUM(CASE WHEN record_type = 'timein' THEN 1 ELSE 0 END) as timein_count,
                    SUM(CASE WHEN record_type = 'timeout' THEN 1 ELSE 0 END) as timeout_count
                FROM records 
                WHERE MONTH(timestamp) = '$month' AND YEAR(timestamp) = '$year'
                GROUP BY DATE(timestamp)";
$calendar_result = $conn->query($calendar_query);

$calendar_data = [];
while ($row = $calendar_result->fetch_assoc()) {
    $calendar_data[$row['date']] = [
        'timein' => $row['timein_count'],
        'timeout' => $row['timeout_count']
    ];
}

// Fetch all users for the dropdown
$users_result = $conn->query("SELECT idnumber, name FROM user ORDER BY name ASC");

$all_users = [];
while($row = $users_result->fetch_assoc()) {
    $all_users[] = $row;
}

// Fetch records for a specific user if selected
$selected_user_records = [];
$selected_user_name = '';
if (isset($_GET['idnumber']) && !empty($_GET['idnumber'])) {
    $stmt = $conn->prepare("SELECT u.name, r.record_type, r.timestamp, r.photo_path FROM records r JOIN user u ON r.idnumber = u.idnumber WHERE r.idnumber = ? ORDER BY r.timestamp DESC");
    $stmt->bind_param("s", $_GET['idnumber']);
    $stmt->execute();
    $user_records_result = $stmt->get_result();
    while($row = $user_records_result->fetch_assoc()) {
        $selected_user_records[] = $row;
    }
    if (!empty($selected_user_records)) {
        $selected_user_name = $selected_user_records[0]['name'];
    }
}

// Fetch preview data for Download Reports
$dl_user = $_GET['dl_user'] ?? 'all';
$dl_time = $_GET['dl_time'] ?? 'all';
$dl_search = $_GET['dl_search'] ?? '';
$dl_date = $_GET['dl_date'] ?? '';

$where_clauses_dl = [];
$params_dl = [];
$types_dl = '';

if ($dl_user !== 'all') {
    $where_clauses_dl[] = "r.idnumber = ?";
    $params_dl[] = $dl_user;
    $types_dl .= 's';
}

if (!empty($dl_search)) {
    $where_clauses_dl[] = "(u.name LIKE ? OR r.idnumber LIKE ?)";
    $search_term = "%" . $dl_search . "%";
    $params_dl[] = $search_term;
    $params_dl[] = $search_term;
    $types_dl .= 'ss';
}

if (!empty($dl_date)) {
    // If a specific date is chosen, it overrides the timeframe dropdown
    $where_clauses_dl[] = "DATE(r.timestamp) = ?";
    $params_dl[] = $dl_date;
    $types_dl .= 's';
} elseif ($dl_time === 'weekly') {
    $where_clauses_dl[] = "YEARWEEK(r.timestamp, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($dl_time === 'monthly') {
    $where_clauses_dl[] = "MONTH(r.timestamp) = MONTH(CURDATE()) AND YEAR(r.timestamp) = YEAR(CURDATE())";
} elseif ($dl_time === 'yearly') {
    $where_clauses_dl[] = "YEAR(r.timestamp) = YEAR(CURDATE())";
}

$where_sql_dl = '';
if (count($where_clauses_dl) > 0) {
    $where_sql_dl = "WHERE " . implode(" AND ", $where_clauses_dl);
}

$preview_query = "SELECT r.id, r.idnumber, u.name, r.record_type, r.timestamp, r.photo_path FROM records r LEFT JOIN user u ON r.idnumber = u.idnumber $where_sql_dl ORDER BY r.timestamp DESC LIMIT 100";

if ($types_dl) {
    $stmt_dl = $conn->prepare($preview_query);
    $stmt_dl->bind_param($types_dl, ...$params_dl);
    $stmt_dl->execute();
    $preview_result = $stmt_dl->get_result();
} else {
    $preview_result = $conn->query($preview_query);
}

$preview_records = $preview_result ? $preview_result->fetch_all(MYSQLI_ASSOC) : [];

$recent_photos_query = "SELECT r.idnumber, u.name, r.photo_path, r.timestamp FROM records r LEFT JOIN user u ON r.idnumber = u.idnumber WHERE r.photo_path IS NOT NULL AND r.photo_path != '' ORDER BY r.timestamp DESC LIMIT 20";
$recent_photos_result = $conn->query($recent_photos_query);
$recent_photos = $recent_photos_result ? $recent_photos_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; margin-top: 15px; }
        .calendar-day-header { text-align: center; font-weight: bold; background: #f0f0f0; padding: 5px; border-radius: 4px; color: #333; }
        .calendar-day { min-height: 80px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #fafafa; display: flex; flex-direction: column; }
        .calendar-day.empty { background: transparent; border: none; }
        .calendar-day.today { border: 2px solid #007bff; background: #e9f5ff; }
        .day-number { font-weight: bold; margin-bottom: 5px; color: #555; }
        .day-stats { font-size: 0.8em; display: flex; flex-direction: column; gap: 2px; }
        .stat-in { color: #28a745; background: #e6f4ea; padding: 2px 4px; border-radius: 3px; display: inline-block; }
        .stat-out { color: #dc3545; background: #fce8e8; padding: 2px 4px; border-radius: 3px; display: inline-block; }
        
        .report-form { display: flex; flex-direction: column; gap: 10px; }
        .report-form select, .report-form button { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-family: inherit;}
        .report-form button { background: #007bff; color: white; border: none; cursor: pointer; }
        .report-form button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <header>
        <h1>Western Mindanao State University</h1>
        <p>Admin Dashboard</p>
    </header>

    <div class="container dashboard-container">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?> (Admin)</h2>

        <div class="action-links">
            <a href="../public/index.php" class="btn btn-secondary">Back to Public Portal</a>
            <a href="logout.php" class="btn" style="background-color: #e53e3e;">Logout Admin</a>
        </div>

        <?php
        // Display success or error messages from redirects
        if (isset($_GET['status'])) {
            echo "<div class='alert-success'>&#10004; " . htmlspecialchars($_GET['status']) . "</div>";
        }
        if (isset($_GET['error'])) {
            echo "<div class='alert-error'>&#9888; " . htmlspecialchars($_GET['error']) . "</div>";
        }
        ?>

        <div class="dashboard-grid">
            <!-- User Management Section -->
            <div id="user-management" class="form-section">
                <h3>Add New User</h3>
                <form action="add_user.php" method="POST">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" placeholder="e.g. Juan Dela Cruz" required>
                    
                    <label for="idnumber">ID Number:</label>
                    <input type="text" id="idnumber" name="idnumber" required placeholder="XXXX-XXXXX" pattern="[0-9]{4}-[0-9]{4,5}" title="Please use the format XXXX-XXXX or XXXX-XXXXX" oninput="formatIdNumber(this)">
                    
                    <button type="submit">Add User</button>
                </form>
            </div>

            <!-- View Employee Records Section -->
            <div class="form-section">
                <h3>View Employee Activity</h3>
                <form action="dashboard.php" method="GET">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                    <?php if (isset($_GET['dl_user'])): ?>
                        <input type="hidden" name="dl_user" value="<?= htmlspecialchars($_GET['dl_user']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['dl_time'])): ?>
                        <input type="hidden" name="dl_time" value="<?= htmlspecialchars($_GET['dl_time']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['dl_search'])): ?>
                        <input type="hidden" name="dl_search" value="<?= htmlspecialchars($_GET['dl_search']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['dl_date'])): ?>
                        <input type="hidden" name="dl_date" value="<?= htmlspecialchars($_GET['dl_date']) ?>">
                    <?php endif; ?>
                    <label for="user_select">Select Employee:</label>
                    <select name="idnumber" id="user_select" onchange="this.form.submit()">
                        <option value="">-- Select an Employee --</option>
                        <?php foreach($all_users as $user): ?>
                            <option value="<?= htmlspecialchars($user['idnumber']) ?>" <?= (isset($_GET['idnumber']) && $_GET['idnumber'] == $user['idnumber']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['idnumber']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if (!empty($selected_user_records)): ?>
                    <h4 style="margin-top: 20px; margin-bottom: 10px; color: var(--text-main);">Records for <?= htmlspecialchars($selected_user_name) ?></h4>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="user-records-table">
                            <thead>
                                <tr>
                                    <th>Record Type</th>
                                    <th>Timestamp</th>
                                    <th>Photo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($selected_user_records as $record): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?= $record['record_type'] === 'timein' ? 'badge-timein' : 'badge-timeout' ?>">
                                                <?= htmlspecialchars(ucfirst($record['record_type'])) ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.9rem; color: var(--text-muted);"><?= date('M d, Y h:i A', strtotime($record['timestamp'])) ?></td>
                                        <td>
                                            <?php if (!empty($record['photo_path'])): ?>
                                                <a href="../public/<?= htmlspecialchars($record['photo_path']) ?>" target="_blank" style="color: var(--primary); text-decoration: underline; font-size: 0.85rem;">View</a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.85rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif(isset($_GET['idnumber']) && !empty($_GET['idnumber'])): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 20px;">No records found for the selected employee.</p>
                <?php endif; ?>
            </div>

            <!-- Export Employee Records Section -->
            <div class="form-section full-width">
                <h3>Download Reports & Preview</h3>
                <form action="dashboard.php" method="GET" class="report-form" style="display: flex; flex-direction: row; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                    <!-- Hidden inputs to preserve state -->
                    <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                    <?php if (isset($_GET['idnumber'])): ?>
                        <input type="hidden" name="idnumber" value="<?= htmlspecialchars($_GET['idnumber']) ?>">
                    <?php endif; ?>

                    <!-- Search Bar -->
                    <div style="flex: 2; min-width: 250px;">
                        <label for="dl_search" style="display:block; margin-bottom: 5px;">Search by Name/ID:</label>
                        <div style="display:flex; gap: 5px;">
                            <input type="search" name="dl_search" id="dl_search" placeholder="Enter name or ID..." value="<?= htmlspecialchars($dl_search) ?>" style="margin-bottom: 0; width: 100%;" onchange="this.form.submit()">
                        </div>
                    </div>

                    <!-- Employee Dropdown -->
                    <div style="flex: 1; min-width: 180px;">
                        <label for="dl_user" style="display:block; margin-bottom: 5px;">Employee:</label>
                        <select name="dl_user" id="dl_user" onchange="this.form.submit()" style="margin-bottom: 0;">
                            <option value="all">All Employees</option>
                            <?php foreach($all_users as $user): ?>
                                <option value="<?= htmlspecialchars($user['idnumber']) ?>" <?= ($dl_user == $user['idnumber']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Date Filter -->
                    <div style="flex: 1; min-width: 150px;">
                        <label for="dl_date" style="display:block; margin-bottom: 5px;">Specific Date:</label>
                        <input type="date" name="dl_date" id="dl_date" value="<?= htmlspecialchars($dl_date) ?>" onchange="this.form.submit()" style="margin-bottom: 0; padding: 6px 10px;">
                    </div>

                    <!-- Timeframe Dropdown -->
                    <div style="flex: 1; min-width: 150px;">
                        <label for="dl_time" style="display:block; margin-bottom: 5px;">Or Timeframe:</label>
                        <select name="dl_time" id="dl_time" onchange="this.form.submit()" style="margin-bottom: 0;" <?= !empty($dl_date) ? 'disabled title="Clear Specific Date to use timeframe"' : '' ?>>
                            <option value="all" <?= ($dl_time == 'all') ? 'selected' : '' ?>>All Time</option>
                            <option value="weekly" <?= ($dl_time == 'weekly') ? 'selected' : '' ?>>This Week</option>
                            <option value="monthly" <?= ($dl_time == 'monthly') ? 'selected' : '' ?>>This Month</option>
                            <option value="yearly" <?= ($dl_time == 'yearly') ? 'selected' : '' ?>>This Year</option>
                        </select>
                    </div>
                    
                    <!-- Actions -->
                    <div style="flex: 1; min-width: 250px; display: flex; gap: 10px;">
                        <a href="download.php?dl_user=<?= urlencode($dl_user) ?>&dl_time=<?= urlencode($dl_time) ?>&dl_search=<?= urlencode($dl_search) ?>&dl_date=<?= urlencode($dl_date) ?>" class="btn" style="flex: 1; text-align: center; margin-bottom: 0; display: flex; align-items: center; justify-content: center;">Download CSV</a>
                        <a href="dashboard.php" class="btn btn-secondary" style="flex: 1; text-align: center; margin-bottom: 0; display: flex; align-items: center; justify-content: center;" title="Clear all filters">Clear</a>
                    </div>
                </form>

                <div class="table-responsive" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius); margin-top: 20px;">
                    <table class="user-records-table" style="margin-top: 0; border: none;">
                        <thead style="position: sticky; top: 0; z-index: 1; background: #f8fafc; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            <tr><th>ID Number</th><th>Name</th><th>Record Type</th><th>Timestamp</th><th>Photo</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($preview_records)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No records found for the selected filters.</td></tr>
                            <?php else: foreach($preview_records as $rec): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rec['idnumber']) ?></td>
                                    <td><?= htmlspecialchars($rec['name'] ?? 'Unknown') ?></td>
                                    <td><span class="badge <?= $rec['record_type'] === 'timein' ? 'badge-timein' : 'badge-timeout' ?>"><?= htmlspecialchars(ucfirst($rec['record_type'])) ?></span></td>
                                    <td style="font-size: 0.9rem; color: var(--text-muted);"><?= date('M d, Y h:i A', strtotime($rec['timestamp'])) ?></td>
                                    <td>
                                        <?php if (!empty($rec['photo_path'])): ?>
                                            <a href="../public/<?= htmlspecialchars($rec['photo_path']) ?>" target="_blank" style="color: var(--primary); text-decoration: underline; font-size: 0.85rem;">View</a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Photos Section -->
            <div class="form-section full-width">
                <h3>Recent Employee Photos</h3>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius); margin-top: 10px;">
                    <table class="user-records-table" style="margin-top: 0; border: none;">
                        <thead style="position: sticky; top: 0; z-index: 1; background: #f8fafc; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            <tr>
                                <th>Photo</th>
                                <th>ID Number</th>
                                <th>Name</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_photos)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No recent photos available.</td></tr>
                            <?php else: foreach($recent_photos as $photo): ?>
                                <tr>
                                    <td>
                                        <a href="../public/<?= htmlspecialchars($photo['photo_path']) ?>" target="_blank" title="View Full Image">
                                            <img src="../public/<?= htmlspecialchars($photo['photo_path']) ?>" alt="Selfie" style="width: 45px; height: 45px; object-fit: cover; border-radius: 50%; border: 2px solid var(--border-color); display: block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                        </a>
                                    </td>
                                    <td style="vertical-align: middle; font-weight: 500;"><?= htmlspecialchars($photo['idnumber']) ?></td>
                                    <td style="vertical-align: middle;"><?= htmlspecialchars($photo['name'] ?? 'Unknown') ?></td>
                                    <td style="font-size: 0.9rem; color: var(--text-muted); vertical-align: middle;"><?= date('M d, Y h:i A', strtotime($photo['timestamp'])) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <?php $idParam = isset($_GET['idnumber']) && !empty($_GET['idnumber']) ? '&idnumber=' . urlencode($_GET['idnumber']) : ''; ?>
            <?php $dlParam = (isset($_GET['dl_user']) ? '&dl_user=' . urlencode($_GET['dl_user']) : '') . (isset($_GET['dl_time']) ? '&dl_time=' . urlencode($_GET['dl_time']) : '') . (isset($_GET['dl_search']) && !empty($_GET['dl_search']) ? '&dl_search=' . urlencode($_GET['dl_search']) : '') . (isset($_GET['dl_date']) && !empty($_GET['dl_date']) ? '&dl_date=' . urlencode($_GET['dl_date']) : ''); ?>
            <div class="calendar-header-nav">
                <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?><?= $idParam ?><?= $dlParam ?>">&laquo; Previous</a>
                <form action="dashboard.php" method="GET" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                    <?php if (isset($_GET['idnumber'])): ?>
                        <input type="hidden" name="idnumber" value="<?= htmlspecialchars($_GET['idnumber']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['dl_user'])): ?>
                        <input type="hidden" name="dl_user" value="<?= htmlspecialchars($_GET['dl_user']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['dl_time'])): ?>
                        <input type="hidden" name="dl_time" value="<?= htmlspecialchars($_GET['dl_time']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['dl_search'])): ?>
                        <input type="hidden" name="dl_search" value="<?= htmlspecialchars($_GET['dl_search']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['dl_date'])): ?>
                        <input type="hidden" name="dl_date" value="<?= htmlspecialchars($_GET['dl_date']) ?>">
                    <?php endif; ?>
                    <h2 style="margin: 0;">System Activity</h2>
                    <input type="month" name="month_year" value="<?= sprintf('%04d-%02d', $year, $month) ?>" onchange="this.form.submit()" style="margin: 0; padding: 6px 10px; border: 1px solid var(--border-color); border-radius: var(--radius); font-family: inherit; font-size: 1rem; color: var(--text-main); background: #f8fafc; cursor: pointer;">
                </form>
                <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?><?= $idParam ?><?= $dlParam ?>">Next &raquo;</a>
            </div>
            <div class="calendar-grid">
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
                
                <?php
                $firstDayOfMonth = date("w", strtotime("$year-$month-01"));
                $daysInMonth = date("t", strtotime("$year-$month-01"));
                
                for ($i = 0; $i < $firstDayOfMonth; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date_str = sprintf("%04d-%02d-%02d", $year, $month, $day);
                    $timein = $calendar_data[$date_str]['timein'] ?? 0;
                    $timeout = $calendar_data[$date_str]['timeout'] ?? 0;
                    
                    $isToday = ($date_str == date('Y-m-d')) ? 'today' : '';
                    
                    echo "<div class='calendar-day $isToday'>";
                    echo "<div class='day-number'>$day</div>";
                    if ($timein > 0 || $timeout > 0) {
                        echo "<div class='day-stats'>";
                        if ($timein > 0) echo "<span class='stat-in' title='Time In'>In: $timein</span>";
                        if ($timeout > 0) echo "<span class='stat-out' title='Time Out'>Out: $timeout</span>";
                        echo "</div>";
                    }
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>

    <script>
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