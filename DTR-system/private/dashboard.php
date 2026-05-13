<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../public/login.php");
    exit;
}

include '../include/dbcon.php';

// Handle AJAX request for day records
if (isset($_GET['ajax_date'])) {
    $date = $_GET['ajax_date'];
    
    $admin_q = $conn->prepare("SELECT role, college_id FROM admin WHERE name = ? LIMIT 1");
    $admin_q->bind_param("s", $_SESSION['admin_name']);
    $admin_q->execute();
    $adm_d = $admin_q->get_result()->fetch_assoc();
    $a_role = $adm_d['role'] ?? 'superadmin';
    $a_cid = $adm_d['college_id'] ?? null;
    
    if ($a_role === 'college_admin' && $a_cid) {
        $stmt = $conn->prepare("SELECT r.id, r.idnumber, u.name, r.record_type, r.timestamp, u.position, r.photo_path FROM records r JOIN user u ON r.idnumber = u.idnumber WHERE DATE(r.timestamp) = ? AND u.college_id = ? ORDER BY r.timestamp ASC");
        $stmt->bind_param("si", $date, $a_cid);
    } else {
        $stmt = $conn->prepare("SELECT r.id, r.idnumber, u.name, r.record_type, r.timestamp, u.position, r.photo_path FROM records r LEFT JOIN user u ON r.idnumber = u.idnumber WHERE DATE(r.timestamp) = ? ORDER BY r.timestamp ASC");
        $stmt->bind_param("s", $date);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($records);
    exit;
}

// Cleanup selfies older than 30 days (1 month)
$thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
$cleanup_stmt = $conn->prepare("SELECT id, photo_path FROM records WHERE timestamp < ? AND photo_path IS NOT NULL AND photo_path != ''");
$cleanup_stmt->bind_param("s", $thirty_days_ago);
$cleanup_stmt->execute();
$cleanup_result = $cleanup_stmt->get_result();

while ($row = $cleanup_result->fetch_assoc()) {
    $path1 = dirname(__DIR__) . '/' . $row['photo_path'];
    $path2 = dirname(__DIR__) . '/public/' . $row['photo_path'];
    
    if (file_exists($path1)) { unlink($path1); }
    if (file_exists($path2)) { unlink($path2); }
    
    $update_stmt = $conn->prepare("UPDATE records SET photo_path = NULL WHERE id = ?");
    $update_stmt->bind_param("i", $row['id']);
    $update_stmt->execute();
}

// Auto-migration for Superadmin/College feature
$conn->query("CREATE TABLE IF NOT EXISTS colleges (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL UNIQUE)");
$check_admin_role = $conn->query("SHOW COLUMNS FROM admin LIKE 'role'");
if ($check_admin_role->num_rows == 0) {
    $conn->query("ALTER TABLE admin ADD COLUMN role ENUM('superadmin', 'college_admin') DEFAULT 'superadmin', ADD COLUMN college_id INT NULL, ADD FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE SET NULL");
    $conn->query("UPDATE admin SET role = 'superadmin'");
}
$check_user_college = $conn->query("SHOW COLUMNS FROM user LIKE 'college_id'");
if ($check_user_college->num_rows == 0) {
    $conn->query("ALTER TABLE user ADD COLUMN college_id INT NULL, ADD FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE SET NULL");
}

// Fetch current admin role
$admin_query = $conn->prepare("SELECT role, college_id FROM admin WHERE name = ? LIMIT 1");
$admin_query->bind_param("s", $_SESSION['admin_name']);
$admin_query->execute();
$admin_data = $admin_query->get_result()->fetch_assoc();
$admin_role = $admin_data['role'] ?? 'superadmin';
$admin_college_id = $admin_data['college_id'] ?? null;

// Fetch all colleges for dropdowns
$colleges_result = $conn->query("SELECT id, name FROM colleges ORDER BY name ASC");
$all_colleges = [];
if ($colleges_result) {
    while($row = $colleges_result->fetch_assoc()) {
        $all_colleges[] = $row;
    }
}

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

$calendar_query = "SELECT DATE(r.timestamp) as date, SUM(CASE WHEN r.record_type = 'timein' THEN 1 ELSE 0 END) as timein_count, SUM(CASE WHEN r.record_type = 'timeout' THEN 1 ELSE 0 END) as timeout_count FROM records r ";
if ($admin_role === 'college_admin' && $admin_college_id) {
    $calendar_query .= " JOIN user u ON r.idnumber = u.idnumber WHERE u.college_id = '$admin_college_id' AND MONTH(r.timestamp) = '$month' AND YEAR(r.timestamp) = '$year' GROUP BY DATE(r.timestamp)";
} else {
    $calendar_query .= " WHERE MONTH(r.timestamp) = '$month' AND YEAR(r.timestamp) = '$year' GROUP BY DATE(r.timestamp)";
}
$calendar_result = $conn->query($calendar_query);

$calendar_data = [];
while ($row = $calendar_result->fetch_assoc()) {
    $calendar_data[$row['date']] = [
        'timein' => $row['timein_count'],
        'timeout' => $row['timeout_count']
    ];
}

// Fetch all users for the dropdown
$users_sql = "SELECT u.idnumber, u.name, u.position, u.email, u.college_id, c.name as college_name FROM user u LEFT JOIN colleges c ON u.college_id = c.id";
if ($admin_role === 'college_admin' && $admin_college_id) {
    $users_sql .= " WHERE u.college_id = '$admin_college_id'";
}
$users_sql .= " ORDER BY u.name ASC";
$users_result = $conn->query($users_sql);

$all_users = [];
while($row = $users_result->fetch_assoc()) {
    $all_users[] = $row;
}

$college_admins = [];
if ($admin_role === 'superadmin') {
    $admins_sql = "SELECT a.idnumber, a.name, a.college_id, c.name as college_name FROM admin a LEFT JOIN colleges c ON a.college_id = c.id WHERE a.role = 'college_admin' ORDER BY a.name ASC";
    $admins_result = $conn->query($admins_sql);
    if ($admins_result) {
        while($row = $admins_result->fetch_assoc()) {
            $college_admins[] = $row;
        }
    }
}

// Fetch records for a specific user if selected
$selected_user_records = [];
$selected_user_name = '';
$selected_user_position = '';
$emp_stats = ['weekly_timeins' => 0, 'monthly_timeins' => 0];

if (isset($_GET['idnumber']) && !empty($_GET['idnumber'])) {
    $emp_id = $_GET['idnumber'];
    
    // Get user name separately so the card works even if they have zero records yet
    $name_stmt = $conn->prepare("SELECT name, position, email, college_id FROM user WHERE idnumber = ?");
    $name_stmt->bind_param("s", $emp_id);
    $name_stmt->execute();
    $name_res = $name_stmt->get_result();
    if ($row = $name_res->fetch_assoc()) {
        $selected_user_name = $row['name'];
        $selected_user_position = $row['position'] ?? 'Employee';
        $selected_user_email = $row['email'] ?? '';
        $selected_user_college_id = $row['college_id'] ?? null;
    }

    // Get activity history records
    $stmt = $conn->prepare("SELECT r.id, u.name, r.record_type, r.timestamp, r.photo_path FROM records r JOIN user u ON r.idnumber = u.idnumber WHERE r.idnumber = ? ORDER BY r.timestamp DESC");
    $stmt->bind_param("s", $emp_id);
    $stmt->execute();
    $user_records_result = $stmt->get_result();
    while($row = $user_records_result->fetch_assoc()) {
        $selected_user_records[] = $row;
    }
    
    // Get activity statistics for the specific card
    $stats_stmt = $conn->prepare("SELECT SUM(CASE WHEN record_type = 'timein' AND YEARWEEK(timestamp, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as weekly_timeins, SUM(CASE WHEN record_type = 'timein' AND MONTH(timestamp) = MONTH(CURDATE()) AND YEAR(timestamp) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as monthly_timeins FROM records WHERE idnumber = ?");
    $stats_stmt->bind_param("s", $emp_id);
    $stats_stmt->execute();
    if ($stats_row = $stats_stmt->get_result()->fetch_assoc()) {
        $emp_stats = $stats_row;
    }
}

// Fetch preview data for Download Reports
$dl_user = $_GET['dl_user'] ?? 'all';
$dl_time = $_GET['dl_time'] ?? 'all';
$dl_search = $_GET['dl_search'] ?? '';
$dl_date = $_GET['dl_date'] ?? '';
$dl_college = $_GET['dl_college'] ?? '';

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

if ($admin_role === 'college_admin' && $admin_college_id) {
    $where_clauses_dl[] = "u.college_id = ?";
    $params_dl[] = $admin_college_id;
    $types_dl .= 'i';
} elseif ($admin_role === 'superadmin' && !empty($dl_college)) {
    $where_clauses_dl[] = "u.college_id = ?";
    $params_dl[] = $dl_college;
    $types_dl .= 'i';
}

if (!empty($dl_date)) {
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

$recent_photos_query = "SELECT r.id, r.idnumber, u.name, r.photo_path, r.timestamp FROM records r LEFT JOIN user u ON r.idnumber = u.idnumber WHERE r.photo_path IS NOT NULL AND r.photo_path != ''";
if ($admin_role === 'college_admin' && $admin_college_id) {
    $recent_photos_query .= " AND u.college_id = '$admin_college_id'";
}
$recent_photos_query .= " ORDER BY r.timestamp DESC LIMIT 20";
$recent_photos_result = $conn->query($recent_photos_query);
$recent_photos = $recent_photos_result ? $recent_photos_result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch Quick Stats for Dashboard Overview
$college_filter_user = ($admin_role === 'college_admin' && $admin_college_id) ? " WHERE college_id = '$admin_college_id'" : "";
$stat_emp_query = $conn->query("SELECT COUNT(*) as count FROM user" . $college_filter_user);
$total_emp = $stat_emp_query ? $stat_emp_query->fetch_assoc()['count'] : 0;

$join_u = ($admin_role === 'college_admin' && $admin_college_id) ? " JOIN user u ON r.idnumber = u.idnumber " : "";
$where_u = ($admin_role === 'college_admin' && $admin_college_id) ? " u.college_id = '$admin_college_id' AND " : "";

$stat_today_query = $conn->query("SELECT COUNT(*) as count FROM records r $join_u WHERE $where_u DATE(r.timestamp) = CURDATE() AND r.record_type = 'timein'");
$total_today_in = $stat_today_query ? $stat_today_query->fetch_assoc()['count'] : 0;

$stat_month_query = $conn->query("SELECT COUNT(*) as count FROM records r $join_u WHERE $where_u MONTH(r.timestamp) = MONTH(CURDATE()) AND YEAR(r.timestamp) = YEAR(CURDATE())");
$total_month_records = $stat_month_query ? $stat_month_query->fetch_assoc()['count'] : 0;

// Fetch current cutoff settings
$morning_res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'morning_cutoff'");
$morning_cutoff = ($morning_res && $morning_res->num_rows > 0) ? $morning_res->fetch_assoc()['setting_value'] : '12:00:00';

$afternoon_res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'afternoon_cutoff'");
$afternoon_cutoff = ($afternoon_res && $afternoon_res->num_rows > 0) ? $afternoon_res->fetch_assoc()['setting_value'] : '13:00:00';

$morning_formatted = date('H:i', strtotime($morning_cutoff));
$afternoon_formatted = date('H:i', strtotime($afternoon_cutoff));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header class="admin-header">
        <div class="header-brand">
            <img src="../assets/img/logo.jpg" alt="WMSU Logo" class="header-logo">
            <div class="header-title">
                <h1>Western Mindanao State University</h1>
                <p>Daily Time Record • Admin Dashboard</p>
            </div>
        </div>
        <button class="mobile-menu-btn" onclick="toggleMenu()" title="Toggle Menu">☰</button>
        <div class="header-actions" id="headerActions">
            <div class="admin-profile">
                <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'], 0, 1)) ?></div>
                <span class="admin-greeting" style="line-height: 1.2;">Hi, <?= htmlspecialchars($_SESSION['admin_name']) ?> <br><small style="font-size: 0.7rem; color: #ffd700; font-weight: 700;"><?= $admin_role === 'superadmin' ? '⭐ Superadmin' : '🏢 College Admin' ?></small></span>
            </div>
            <a href="../public/index.php" class="header-btn btn-portal">🌐 Public Portal</a>
            <a href="logout.php" class="header-btn btn-logout">🚪 Logout</a>
        </div>
    </header>

    <div class="container dashboard-container">
        <?php
        if (isset($_GET['status'])) {
            echo "<div class='alert-success'>&#10004; " . htmlspecialchars($_GET['status']) . "</div>";
        }
        if (isset($_GET['error'])) {
            echo "<div class='alert-error'>&#9888; " . htmlspecialchars($_GET['error']) . "</div>";
        }
        ?>

        <!-- Quick Stats Overview -->
        <div class="quick-stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h4>Total Employees</h4>
                    <p><?= number_format($total_emp) ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <h4>Today's Time-Ins</h4>
                    <p><?= number_format($total_today_in) ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <h4>Records This Month</h4>
                    <p><?= number_format($total_month_records) ?></p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div id="system-settings" class="form-section">
                <h3 style="display: flex; align-items: center; gap: 10px;">⚙️ System Settings</h3>
                <form action="update_settings.php" method="POST" class="filter-container" style="margin-bottom: 0;">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="morning_cutoff">Morning Cutoff Time:</label>
                            <input type="time" name="morning_cutoff" id="morning_cutoff" value="<?= htmlspecialchars($morning_formatted) ?>" required>
                        </div>
                        <div class="filter-group">
                            <label for="afternoon_cutoff">Afternoon Cutoff Time:</label>
                            <input type="time" name="afternoon_cutoff" id="afternoon_cutoff" value="<?= htmlspecialchars($afternoon_formatted) ?>" required>
                        </div>
                        <div class="filter-actions" style="min-width: auto; flex: none;">
                            <button type="submit">Update Settings</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if ($admin_role === 'superadmin'): ?>
            <div id="manage-colleges" class="form-section">
                <h3 style="display: flex; align-items: center; gap: 10px;">🏢 Add New College</h3>
                <form action="add_college.php" method="POST" class="filter-container" style="margin-bottom: 0;">
                    <div class="filter-row">
                        <div class="filter-group large">
                            <label for="college_name">College Name:</label>
                            <input type="text" name="college_name" id="college_name" placeholder="e.g. College of Engineering" required>
                        </div>
                        <div class="filter-actions" style="min-width: auto; flex: none;">
                            <button type="submit">Add College</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div id="manage-admins" class="form-section">
                <h3 style="display: flex; align-items: center; gap: 10px;">🛡️ Add College Admin</h3>
                <form action="add_admin.php" method="POST" class="report-form">
                    <label for="admin_idnumber">Admin Username / ID:</label>
                    <input type="text" id="admin_idnumber" name="idnumber" required>
                    <label for="admin_name">Admin Full Name:</label>
                    <input type="text" id="admin_name" name="name" required>
                    <label for="admin_password">Password:</label>
                    <input type="password" id="admin_password" name="password" required>
                    <label for="admin_college">Assign to College:</label>
                    <select id="admin_college" name="college_id" required>
                        <option value="">-- Select a College --</option>
                        <?php foreach($all_colleges as $college): ?>
                            <option value="<?= $college['id'] ?>"><?= htmlspecialchars($college['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Create Admin</button>
                </form>
            </div>

            <?php endif; ?>

            <div id="user-management" class="form-section">
                <h3 style="display: flex; align-items: center; gap: 10px;">👤 Add New User</h3>
                <form action="add_user.php" method="POST" class="report-form" onsubmit="return verifyWmsuEmail()">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" placeholder="e.g. Juan Dela Cruz" required>
                    
                    <label for="idnumber">ID Number:</label>
                    <input type="text" id="idnumber" name="idnumber" required placeholder="XXXX-XXXXX" pattern="[0-9]{4}-[0-9]{4,5}" title="Please use the format XXXX-XXXX or XXXX-XXXXX" oninput="formatIdNumber(this)">
                    
                    <label for="email">WMSU Email Address:</label>
                    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <input type="email" id="email" name="email" required placeholder="e.g. employee@wmsu.edu.ph" pattern="^[a-zA-Z0-9._%+\-]+@wmsu\.edu\.ph$" title="Must be a valid @wmsu.edu.ph email address" style="margin-bottom: 0; flex: 1;">
                        <button type="button" id="sendCodeBtn" onclick="sendVerificationCode()" style="flex: 0 0 auto; width: auto; margin: 0;">Send Code</button>
                    </div>
                    
                    <div id="verificationSection" style="display: none;">
                        <label for="verification_code">Verification Code:</label>
                        <input type="text" id="verification_code" name="verification_code" placeholder="Enter the 6-digit code" maxlength="6" style="letter-spacing: 4px; font-weight: bold; text-align: center; font-size: 1.2rem;">
                    </div>

                    <label for="position">Position / Role:</label>
                    <select id="position" name="position">
                        <option value="Employee">Employee</option>
                        <option value="Intern">Intern</option>
                        <option value="Manager">Manager</option>
                        <option value="Staff">Staff</option>
                        <option value="Faculty">Faculty</option>
                    </select>
                    
                    <label for="college_id">College Assignment:</label>
                    <?php if ($admin_role === 'superadmin'): ?>
                        <select id="college_id" name="college_id" required>
                            <option value="">-- Select a College --</option>
                            <?php foreach($all_colleges as $college): ?>
                                <option value="<?= $college['id'] ?>"><?= htmlspecialchars($college['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="hidden" name="college_id" value="<?= $admin_college_id ?>">
                        <?php $college_name = ''; foreach($all_colleges as $c) { if($c['id'] == $admin_college_id) $college_name = $c['name']; } ?>
                        <input type="text" value="<?= htmlspecialchars($college_name) ?>" disabled style="background: #e2e8f0; cursor: not-allowed; margin-bottom: 20px;">
                    <?php endif; ?>
                    
                    <button type="submit">Add User</button>
                </form>
            </div>

            <div id="employee-directory" class="form-section full-width">
                <h3 style="display: flex; align-items: center; gap: 10px;">📋 Registered Employees</h3>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="user-records-table">
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr><th>ID Number</th><th>Name</th><th>Position</th><th>Email</th><th>College</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_users)): ?>
                                <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">No registered employees found.</td></tr>
                            <?php else: foreach($all_users as $emp): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($emp['idnumber']) ?></td>
                                    <td><?= htmlspecialchars($emp['name']) ?></td>
                                    <td><span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;"><?= htmlspecialchars($emp['position'] ?? 'Employee') ?></span></td>
                                    <td style="font-size: 0.9rem; color: var(--text-muted);"><?= htmlspecialchars($emp['email'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge badge-timein" style="background: #e2e8f0 !important; color: #475569 !important; border-color: #cbd5e1 !important;"><?= htmlspecialchars($emp['college_name'] ?? 'Unassigned') ?></span>
                                    </td>
                                    <td>
                                        <a href="dashboard.php?idnumber=<?= urlencode($emp['idnumber']) ?>#user-management" class="btn-secondary" style="padding: 4px 12px; font-size: 0.75rem; border-radius: 50px; text-decoration: none; border: 1px solid #cbd5e1; display: inline-flex; align-items: center; box-shadow: none;">🔍 View Activity</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($admin_role === 'superadmin'): ?>
            <div id="college-admins-list" class="form-section full-width">
                <h3 style="display: flex; align-items: center; gap: 10px;">👨‍💼 College Admins</h3>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="user-records-table">
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr><th>ID Number</th><th>Name</th><th>Assigned College</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($college_admins)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No college admins found.</td></tr>
                            <?php else: foreach($college_admins as $admin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($admin['idnumber']) ?></td>
                                    <td><?= htmlspecialchars($admin['name']) ?></td>
                                    <td><span class="badge badge-timein" style="background: #e2e8f0 !important; color: #475569 !important; border-color: #cbd5e1 !important;"><?= htmlspecialchars($admin['college_name'] ?? 'Unassigned') ?></span></td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="btn-secondary" onclick="openEditAdminModal('<?= htmlspecialchars($admin['idnumber']) ?>', '<?= htmlspecialchars(addslashes($admin['name'])) ?>', '<?= $admin['college_id'] ?? '' ?>')" style="padding: 4px 12px; font-size: 0.75rem; border-radius: 50px; box-shadow: none; margin: 0; border: 1px solid #cbd5e1;">✏️ Edit</button>
                                            <form action="delete_admin.php" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this college admin?');">
                                                <input type="hidden" name="idnumber" value="<?= htmlspecialchars($admin['idnumber']) ?>">
                                                <button type="submit" style="padding: 4px 12px; font-size: 0.75rem; border-radius: 50px; box-shadow: none; margin: 0; border: 1px solid #fecaca; background: #fff5f5; color: #c53030; transition: all 0.2s;">🗑️ Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-section full-width">
                <h3 style="display: flex; align-items: center; gap: 10px;">📅 View Employee Activity</h3>
                <form action="dashboard.php" method="GET" class="filter-container" style="margin-bottom: 0;">
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
                    <?php if (isset($_GET['dl_college'])): ?>
                        <input type="hidden" name="dl_college" value="<?= htmlspecialchars($_GET['dl_college']) ?>">
                    <?php endif; ?>
                    <div class="filter-row">
                        <?php if ($admin_role === 'superadmin'): ?>
                        <div class="filter-group">
                            <label for="filter_college">Filter by College:</label>
                            <select name="filter_college" id="filter_college" onchange="this.form.submit()">
                                <option value="">All Colleges</option>
                                <?php foreach($all_colleges as $college): ?>
                                    <option value="<?= $college['id'] ?>" <?= (isset($_GET['filter_college']) && $_GET['filter_college'] == $college['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($college['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="filter-group">
                            <label for="user_select">Select Employee:</label>
                            <select name="idnumber" id="user_select" onchange="this.form.submit()">
                                <option value="">-- Select an Employee --</option>
                                <?php foreach($all_users as $user): ?>
                                    <?php if (isset($_GET['filter_college']) && $_GET['filter_college'] !== '' && $user['college_id'] != $_GET['filter_college']) continue; ?>
                                    <option value="<?= htmlspecialchars($user['idnumber']) ?>" <?= (isset($_GET['idnumber']) && $_GET['idnumber'] == $user['idnumber']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['idnumber']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <?php if (isset($_GET['idnumber']) && !empty($_GET['idnumber']) && !empty($selected_user_name)): ?>
                    <div class="employee-card" style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center; background: linear-gradient(to right, rgba(153,0,0,0.03), transparent); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-top: 20px;">
                        <div style="width: 60px; height: 60px; background: var(--primary); color: var(--secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 700; box-shadow: 0 4px 10px rgba(153,0,0,0.2); border: 2px solid var(--secondary);">
                            <?= strtoupper(substr($selected_user_name, 0, 1)) ?>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <h4 style="margin: 0 0 6px 0; font-size: 1.2rem; color: var(--text-main);"><?= htmlspecialchars($selected_user_name) ?></h4>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                <span style="background: #fff; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; color: var(--text-muted); font-weight: 600; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">ID: <?= htmlspecialchars($_GET['idnumber']) ?></span>
                                <span style="background: #fff5f5; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; color: #990000; font-weight: 600; border: 1px solid #fecaca; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Role: <?= htmlspecialchars($selected_user_position) ?></span>
                                <button onclick="openEmailModal()" class="btn-secondary" style="padding: 4px 12px; font-size: 0.75rem; border-radius: 50px; box-shadow: none; margin: 0; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #cbd5e1; background: #ebf8ff; color: #2b6cb0;">📧 Email</button>
                                <button onclick="openEditModal()" class="btn-secondary" style="padding: 4px 12px; font-size: 0.75rem; border-radius: 50px; box-shadow: none; margin: 0; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #cbd5e1;">✏️ Edit Profile</button>
                                <form action="delete_user.php" method="POST" style="display: inline; width: auto;" onsubmit="return confirm('Are you sure you want to delete this employee and all their records? This action cannot be undone.');">
                                    <input type="hidden" name="idnumber" value="<?= htmlspecialchars($_GET['idnumber']) ?>">
                                    <button type="submit" style="padding: 4px 12px; font-size: 0.75rem; border-radius: 50px; box-shadow: none; margin: 0; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #fecaca; background: #fff5f5; color: #c53030; transition: all 0.2s;">🗑️ Delete</button>
                                </form>
                            </div>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <div style="background: #fff; padding: 12px 18px; border-radius: 10px; text-align: center; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.5px;">This Week</div>
                                <div style="font-size: 1.1rem; font-weight: 700; color: #990000; display: flex; align-items: baseline; justify-content: center; gap: 4px;"><?= $emp_stats['weekly_timeins'] ?: 0 ?> <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">Time Ins</span></div>
                            </div>
                            <div style="background: #fff; padding: 12px 18px; border-radius: 10px; text-align: center; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.5px;">This Month</div>
                                <div style="font-size: 1.1rem; font-weight: 700; color: #990000; display: flex; align-items: baseline; justify-content: center; gap: 4px;"><?= $emp_stats['monthly_timeins'] ?: 0 ?> <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">Time Ins</span></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($selected_user_records)): ?>
                    <h4 style="margin-top: 24px; margin-bottom: 12px; color: var(--text-main); font-size: 1rem;">Activity History</h4>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="user-records-table">
                            <thead>
                                <tr>
                                    <th>Record Type</th>
                                    <th>Timestamp</th>
                                    <th>Photo</th>
                                    <th>Action</th>
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
                                                <a href="../<?= htmlspecialchars($record['photo_path']) ?>" target="_blank" style="color: var(--primary); text-decoration: underline; font-size: 0.85rem;">View</a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.85rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form action="delete_record.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this specific record?');" style="margin:0;">
                                                <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                                                <button type="submit" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 4px; background: #fff5f5; color: #c53030; border: 1px solid #fecaca; box-shadow: none; width: auto;">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 20px;">No historical records found for this employee.</p>
                    <?php endif; ?>
                <?php elseif(isset($_GET['idnumber']) && !empty($_GET['idnumber'])): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 20px;">No records found for the selected employee.</p>
                <?php endif; ?>
            </div>

            <div class="form-section full-width">
                <h3 style="display: flex; align-items: center; gap: 10px;">📥 Download Reports & Preview</h3>
                <form action="dashboard.php" method="GET" class="filter-container">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                    <?php if (isset($_GET['idnumber'])): ?>
                        <input type="hidden" name="idnumber" value="<?= htmlspecialchars($_GET['idnumber']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['filter_college'])): ?>
                        <input type="hidden" name="filter_college" value="<?= htmlspecialchars($_GET['filter_college']) ?>">
                    <?php endif; ?>

                    <div class="filter-row">
                        <div class="filter-group large">
                            <label for="dl_search">Search by Name/ID:</label>
                            <input type="search" name="dl_search" id="dl_search" placeholder="Enter name or ID..." value="<?= htmlspecialchars($dl_search) ?>" onchange="this.form.submit()">
                        </div>

                        <?php if ($admin_role === 'superadmin'): ?>
                        <div class="filter-group">
                            <label for="dl_college">College:</label>
                            <select name="dl_college" id="dl_college" onchange="this.form.submit()">
                                <option value="">All Colleges</option>
                                <?php foreach($all_colleges as $college): ?>
                                    <option value="<?= $college['id'] ?>" <?= ($dl_college == $college['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($college['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="filter-group">
                            <label for="dl_user">Employee:</label>
                            <select name="dl_user" id="dl_user" onchange="this.form.submit()">
                                <option value="all">All Employees</option>
                                <?php foreach($all_users as $user): ?>
                                    <?php if ($admin_role === 'superadmin' && $dl_college !== '' && $user['college_id'] != $dl_college) continue; ?>
                                    <option value="<?= htmlspecialchars($user['idnumber']) ?>" <?= ($dl_user == $user['idnumber']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row" style="margin-top: 15px;">
                        <div class="filter-group">
                            <label for="dl_date">Specific Date:</label>
                            <input type="date" name="dl_date" id="dl_date" value="<?= htmlspecialchars($dl_date) ?>" onchange="this.form.submit()">
                        </div>

                        <div class="filter-group">
                            <label for="dl_time">Or Timeframe:</label>
                            <select name="dl_time" id="dl_time" onchange="this.form.submit()" <?= !empty($dl_date) ? 'disabled title="Clear Specific Date to use timeframe"' : '' ?>>
                                <option value="all" <?= ($dl_time == 'all') ? 'selected' : '' ?>>All Time</option>
                                <option value="weekly" <?= ($dl_time == 'weekly') ? 'selected' : '' ?>>This Week</option>
                                <option value="monthly" <?= ($dl_time == 'monthly') ? 'selected' : '' ?>>This Month</option>
                                <option value="yearly" <?= ($dl_time == 'yearly') ? 'selected' : '' ?>>This Year</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <a href="download.php?dl_user=<?= urlencode($dl_user) ?>&dl_time=<?= urlencode($dl_time) ?>&dl_search=<?= urlencode($dl_search) ?>&dl_date=<?= urlencode($dl_date) ?>&dl_college=<?= urlencode($dl_college) ?>" class="btn">📥 Download CSV</a>
                            <a href="dashboard.php" class="btn btn-secondary" title="Clear all filters">❌ Clear</a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive" style="max-height: 250px; overflow-y: auto; margin-top: 20px;">
                    <table class="user-records-table">
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr><th>ID Number</th><th>Name</th><th>Record Type</th><th>Timestamp</th><th>Photo</th><th>Action</th></tr>
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
                                            <a href="../<?= htmlspecialchars($rec['photo_path']) ?>" target="_blank" style="color: var(--primary); text-decoration: underline; font-size: 0.85rem;">View</a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="delete_record.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this specific record?');" style="margin:0;">
                                            <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
                                            <button type="submit" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 4px; background: #fff5f5; color: #c53030; border: 1px solid #fecaca; box-shadow: none; width: auto;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-section full-width">
                <h3 style="display: flex; align-items: center; gap: 10px;">📸 Recent Employee Photos</h3>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto; margin-top: 10px;">
                    <table class="user-records-table">
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th style="text-align: center; width: 80px;">Photo</th>
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
                                    <td style="text-align: center;">
                                        <a href="../<?= htmlspecialchars($photo['photo_path']) ?>" target="_blank" title="View Full Image">
                                            <img src="../<?= htmlspecialchars($photo['photo_path']) ?>" alt="Selfie" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.15); display: inline-block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
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
            <?php $dlParam = (isset($_GET['dl_user']) ? '&dl_user=' . urlencode($_GET['dl_user']) : '') . (isset($_GET['dl_time']) ? '&dl_time=' . urlencode($_GET['dl_time']) : '') . (isset($_GET['dl_search']) && !empty($_GET['dl_search']) ? '&dl_search=' . urlencode($_GET['dl_search']) : '') . (isset($_GET['dl_date']) && !empty($_GET['dl_date']) ? '&dl_date=' . urlencode($_GET['dl_date']) : '') . (isset($_GET['dl_college']) && !empty($_GET['dl_college']) ? '&dl_college=' . urlencode($_GET['dl_college']) : '') . (isset($_GET['filter_college']) && !empty($_GET['filter_college']) ? '&filter_college=' . urlencode($_GET['filter_college']) : ''); ?>
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
                    <?php if (isset($_GET['dl_college'])): ?>
                        <input type="hidden" name="dl_college" value="<?= htmlspecialchars($_GET['dl_college']) ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['filter_college'])): ?>
                        <input type="hidden" name="filter_college" value="<?= htmlspecialchars($_GET['filter_college']) ?>">
                    <?php endif; ?>
                    <h2 style="margin: 0; display: flex; align-items: center; gap: 8px;">📈 System Activity</h2>
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
                    $onclick = ($timein > 0 || $timeout > 0) ? "onclick='openDayModal(\"$date_str\")' style='cursor: pointer;' title='Click to view records'" : "";
                    
                    echo "<div class='calendar-day $isToday' $onclick>";
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

    <!-- Edit User Modal -->
    <?php if (isset($_GET['idnumber']) && !empty($_GET['idnumber']) && !empty($selected_user_name)): ?>
    <div id="editUserModal" class="modal">
        <div class="modal-content" style="text-align: left;">
            <h3 style="margin-top: 0; margin-bottom: 20px; border-bottom: none; padding-bottom: 0; color: var(--text-main);">✏️ Edit Employee Profile</h3>
            <form action="edit_user.php" method="POST" class="report-form">
                <input type="hidden" name="old_idnumber" value="<?= htmlspecialchars($_GET['idnumber']) ?>">
                
                <label for="edit_idnumber">ID Number:</label>
                <input type="text" id="edit_idnumber" name="idnumber" value="<?= htmlspecialchars($_GET['idnumber']) ?>" required pattern="[0-9]{4}-[0-9]{4,5}" title="Please use the format XXXX-XXXX or XXXX-XXXXX" oninput="formatIdNumber(this)">
                
                <label for="edit_name">Full Name:</label>
                <input type="text" id="edit_name" name="name" value="<?= htmlspecialchars($selected_user_name) ?>" required>
                
                <label for="edit_email">WMSU Email Address:</label>
                <input type="email" id="edit_email" name="email" value="<?= htmlspecialchars($selected_user_email ?? '') ?>" required placeholder="e.g. employee@wmsu.edu.ph" pattern="^[a-zA-Z0-9._%+\-]+@wmsu\.edu\.ph$" title="Must be a valid @wmsu.edu.ph email address">
                
                <label for="edit_position">Position / Role:</label>
                <select id="edit_position" name="position" style="margin-bottom: 24px;">
                    <option value="Employee" <?= $selected_user_position == 'Employee' ? 'selected' : '' ?>>Employee</option>
                    <option value="Intern" <?= $selected_user_position == 'Intern' ? 'selected' : '' ?>>Intern</option>
                    <option value="Manager" <?= $selected_user_position == 'Manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="Staff" <?= $selected_user_position == 'Staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="Faculty" <?= $selected_user_position == 'Faculty' ? 'selected' : '' ?>>Faculty</option>
                </select>
                
                <label for="edit_college_id">College Assignment:</label>
                <?php if ($admin_role === 'superadmin'): ?>
                    <select id="edit_college_id" name="college_id" required style="margin-bottom: 24px;">
                        <option value="">-- Select a College --</option>
                        <?php foreach($all_colleges as $college): ?>
                            <option value="<?= $college['id'] ?>" <?= $selected_user_college_id == $college['id'] ? 'selected' : '' ?>><?= htmlspecialchars($college['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="college_id" value="<?= $admin_college_id ?>">
                    <?php $college_name = ''; foreach($all_colleges as $c) { if($c['id'] == $admin_college_id) $college_name = $c['name']; } ?>
                    <input type="text" value="<?= htmlspecialchars($college_name) ?>" disabled style="background: #e2e8f0; cursor: not-allowed; margin-bottom: 24px;">
                <?php endif; ?>

                <div class="button-group">
                    <button type="submit">Save Changes</button>
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit College Admin Modal (Superadmin Only) -->
    <?php if ($admin_role === 'superadmin'): ?>
    <div id="editAdminModal" class="modal">
        <div class="modal-content" style="text-align: left;">
            <h3 style="margin-top: 0; margin-bottom: 20px; border-bottom: none; padding-bottom: 0; color: var(--text-main);">✏️ Edit College Admin</h3>
            <form action="edit_admin.php" method="POST" class="report-form">
                <input type="hidden" name="old_idnumber" id="edit_admin_old_idnumber">
                
                <label for="edit_admin_idnumber">Admin Username / ID:</label>
                <input type="text" id="edit_admin_idnumber" name="idnumber" required>
                
                <label for="edit_admin_name">Full Name:</label>
                <input type="text" id="edit_admin_name" name="name" required>
                
                <label for="edit_admin_password">New Password (leave blank to keep current):</label>
                <input type="password" id="edit_admin_password" name="password">
                
                <label for="edit_admin_college">Assign to College:</label>
                <select id="edit_admin_college" name="college_id" required style="margin-bottom: 24px;">
                    <option value="">-- Select a College --</option>
                    <?php foreach($all_colleges as $college): ?>
                        <option value="<?= $college['id'] ?>"><?= htmlspecialchars($college['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="button-group">
                    <button type="submit">Save Changes</button>
                    <button type="button" class="btn-secondary" onclick="closeEditAdminModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Email User Modal -->
    <?php if (isset($_GET['idnumber']) && !empty($_GET['idnumber']) && !empty($selected_user_name)): ?>
    <div id="emailUserModal" class="modal">
        <div class="modal-content" style="text-align: left;">
            <h3 style="margin-top: 0; margin-bottom: 20px; border-bottom: none; padding-bottom: 0; color: var(--text-main);">📧 Email <?= htmlspecialchars($selected_user_name) ?></h3>
            <form action="send_user_email.php" method="POST" class="report-form">
                <input type="hidden" name="idnumber" value="<?= htmlspecialchars($_GET['idnumber']) ?>">
                
                <label for="email_subject">Subject:</label>
                <input type="text" id="email_subject" name="subject" placeholder="Enter email subject" required>
                
                <label for="email_message">Message:</label>
                <textarea id="email_message" name="message" rows="5" placeholder="Type your message here..." required style="resize: vertical;"></textarea>
                
                <div class="button-group">
                    <button type="submit">Send Email</button>
                    <button type="button" class="btn-secondary" onclick="closeEmailModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Day Records Modal -->
    <div id="dayRecordsModal" class="modal">
        <div class="modal-content" style="text-align: left; max-width: 600px; width: 95%;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px;">
                <h3 style="margin: 0; border: none; padding: 0; display: flex; align-items: center; gap: 10px;" id="dayModalTitle">📅 Records for Date</h3>
                <button type="button" class="btn-secondary" onclick="closeDayModal()" style="padding: 6px 12px; flex: none; font-size: 0.8rem; margin: 0;">Close</button>
            </div>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="user-records-table">
                    <thead style="position: sticky; top: 0; z-index: 1;">
                        <tr><th style="text-align: center; width: 60px;">Photo</th><th>Name</th><th>ID</th><th>Type</th><th>Time</th><th>Action</th></tr>
                    </thead>
                    <tbody id="dayRecordsBody">
                        <tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleMenu() {
            const menu = document.getElementById('headerActions');
            menu.classList.toggle('show');
        }

        function openDayModal(dateStr) {
            document.getElementById('dayRecordsModal').style.display = 'flex';
            
            const dateObj = new Date(dateStr + 'T00:00:00');
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('dayModalTitle').innerHTML = '📅 Records for ' + dateObj.toLocaleDateString('en-US', options);
            
            const tbody = document.getElementById('dayRecordsBody');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">Loading records...</td></tr>';
            
            fetch('dashboard.php?ajax_date=' + encodeURIComponent(dateStr))
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">No records found for this day.</td></tr>';
                        return;
                    }
                    let html = '';
                    data.forEach(rec => {
                        const typeClass = rec.record_type === 'timein' ? 'badge-timein' : 'badge-timeout';
                        const typeText = rec.record_type.charAt(0).toUpperCase() + rec.record_type.slice(1);
                        const timeObj = new Date(rec.timestamp.replace(' ', 'T'));
                        const timeStr = timeObj.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
                        const photoHtml = rec.photo_path 
                            ? `<a href="../${rec.photo_path}" target="_blank"><img src="../${rec.photo_path}" alt="Selfie" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.15);"></a>`
                            : `<span style="color: var(--text-muted); font-size: 0.85rem;">-</span>`;
                        html += `<tr>
                            <td style="text-align: center;">${photoHtml}</td>
                            <td>
                                <div style="font-weight: 600;">${rec.name || 'Unknown'}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">${rec.position || 'Employee'}</div>
                            </td>
                            <td style="vertical-align: middle;">${rec.idnumber}</td>
                            <td style="vertical-align: middle;"><span class="badge ${typeClass}">${typeText}</span></td>
                            <td style="font-size: 0.9rem; color: var(--text-muted); vertical-align: middle;">${timeStr}</td>
                            <td style="vertical-align: middle;">
                                <form action="delete_record.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this specific record?');" style="margin:0;">
                                    <input type="hidden" name="record_id" value="${rec.id}">
                                    <button type="submit" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 4px; background: #fff5f5; color: #c53030; border: 1px solid #fecaca; box-shadow: none; width: auto;">Delete</button>
                                </form>
                            </td>
                        </tr>`;
                    });
                    tbody.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #c53030;">Error loading records. Please try again.</td></tr>';
                });
        }

        function closeDayModal() {
            document.getElementById('dayRecordsModal').style.display = 'none';
        }

        function openEditModal() {
            document.getElementById('editUserModal').style.display = 'flex';
        }
        function closeEditModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        function openEditAdminModal(idnumber, name, college_id) {
            document.getElementById('edit_admin_old_idnumber').value = idnumber;
            document.getElementById('edit_admin_idnumber').value = idnumber;
            document.getElementById('edit_admin_name').value = name;
            document.getElementById('edit_admin_password').value = '';
            document.getElementById('edit_admin_college').value = college_id;
            document.getElementById('editAdminModal').style.display = 'flex';
        }
        function closeEditAdminModal() {
            document.getElementById('editAdminModal').style.display = 'none';
        }

        function openEmailModal() {
            document.getElementById('emailUserModal').style.display = 'flex';
        }
        function closeEmailModal() {
            document.getElementById('emailUserModal').style.display = 'none';
        }

        function verifyWmsuEmail() {
            const emailInput = document.getElementById('email').value;
            if (!emailInput.endsWith('@wmsu.edu.ph')) {
                alert('Verification Failed: Only official @wmsu.edu.ph email addresses are allowed.');
                return false;
            }
            return true;
        }

        function sendVerificationCode() {
            const emailInput = document.getElementById('email');
            const email = emailInput.value;
            if (!email.endsWith('@wmsu.edu.ph')) {
                alert('Please enter a valid @wmsu.edu.ph email address first.');
                return;
            }

            const btn = document.getElementById('sendCodeBtn');
            btn.innerText = 'Sending...';
            btn.disabled = true;

            fetch('send_code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Verification code sent to ' + email);
                    document.getElementById('verificationSection').style.display = 'block';
                    document.getElementById('verification_code').required = true;
                    btn.innerText = 'Code Sent';
                } else {
                    alert('Error: ' + data.message);
                    btn.innerText = 'Send Code';
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send verification code. Please check your network connection.');
                btn.innerText = 'Send Code';
                btn.disabled = false;
            });
        }

        function formatIdNumber(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 4) {
                value = value.substring(0, 4) + '-' + value.substring(4, 9);
            }
            input.value = value;
        }

        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success, .alert-error');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 500);
            });
        }, 5000);
    </script>
</body>
</html>