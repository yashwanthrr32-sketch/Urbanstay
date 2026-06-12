<?php
require_once '../config/db.php';
$required_role = 'parent';
include '../includes/session-check.php';

$parent_id = $_SESSION['user_id'];

// Get all children for dropdown
$stmt = $pdo->prepare("
    SELECT u.id, u.name 
    FROM parent_tenant pt
    JOIN users u ON pt.tenant_id = u.id
    WHERE pt.parent_id = ?
");
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();

// If no children linked, show message and stop
if(count($children) == 0) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Attendance - Urban Stay</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            .no-children-container {
                max-width: 600px;
                margin: 50px auto;
                background: white;
                padding: 40px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .no-children-container h2 {
                color: #185FA5;
                margin-bottom: 15px;
            }
            .no-children-container p {
                color: #666;
                margin-bottom: 20px;
            }
            .btn-back {
                background: #185FA5;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                display: inline-block;
            }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        
        <div class="dashboard-container">
            <div class="sidebar">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="children.php">My Children</a></li>
                    <li><a href="attendance.php" class="active">Attendance</a></li>
                    <li><a href="payment.php">Rent & Payment</a></li>
                    <li><a href="profile.php">Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="no-children-container">
                    <h2>👶 No Children Linked Yet</h2>
                    <p>You don't have any children linked to your account.</p>
                    <p>Please contact the PG Manager to link your child to access attendance records.</p>
                    <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : $children[0]['id'];

// Verify child is linked to this parent
$stmt = $pdo->prepare("
    SELECT tenant_id FROM parent_tenant WHERE parent_id = ? AND tenant_id = ?
");
$stmt->execute([$parent_id, $child_id]);
if(!$stmt->fetch()) {
    $child_id = $children[0]['id'];
}

// Get child name
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$child_id]);
$child = $stmt->fetch();

$currentMonth = date('Y-m');
$month = isset($_GET['month']) ? $_GET['month'] : $currentMonth;

// Get attendance for the selected child
$stmt = $pdo->prepare("
    SELECT date, status 
    FROM attendance 
    WHERE tenant_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
    ORDER BY date
");
$stmt->execute([$child_id, $month]);
$attendances = $stmt->fetchAll();

// Calculate stats
$present = 0;
$absent = 0;
$attendanceByDate = [];
foreach($attendances as $att) {
    if($att['status'] == 'present') $present++;
    else $absent++;
    $attendanceByDate[$att['date']] = $att['status'];
}

// Get days in month
$daysInMonth = date('t', strtotime($month . '-01'));
$firstDay = date('w', strtotime($month . '-01'));

$total = $present + $absent;
$percentage = $total > 0 ? round(($present / $total) * 100) : 0;

// Get previous and next month
$prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
$currentMonthYear = date('F Y', strtotime($month . '-01'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child's Attendance - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .attendance-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .child-selector {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .child-selector select {
            padding: 8px 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #185FA5;
        }
        .stat-card .label {
            font-size: 12px;
            color: #666;
        }
        .calendar {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #185FA5;
            color: white;
            text-align: center;
            padding: 12px;
            font-weight: bold;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        .calendar-day {
            padding: 12px;
            text-align: center;
            border: 1px solid #eee;
            min-height: 80px;
        }
        .calendar-day.present { background: #d4edda; }
        .calendar-day.absent { background: #f8d7da; }
        .calendar-day.empty { background: #f5f5f5; }
        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .month-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 0 10px;
        }
        .month-nav a {
            background: #185FA5;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
        }
        .month-nav a:hover {
            background: #0d3b66;
        }
        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="children.php">My Children</a></li>
                <li><a href="attendance.php" class="active">Attendance</a></li>
                <li><a href="payment.php">Rent & Payment</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="attendance-container">
                <h1>📅 Attendance Record</h1>
                
                <!-- Child Selector -->
                <div class="child-selector">
                    <div>
                        <strong>Select Child:</strong>
                        <select id="childSelect" onchange="window.location.href='?child_id='+this.value">
                            <?php foreach($children as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $child_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <strong>Showing attendance for:</strong> <?php echo htmlspecialchars($child['name']); ?>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo $present; ?></div>
                        <div class="label">Present Days</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $absent; ?></div>
                        <div class="label">Absent Days</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $percentage; ?>%</div>
                        <div class="label">Attendance Percentage</div>
                    </div>
                </div>
                
                <!-- Month Navigation -->
                <div class="month-nav">
                    <a href="?child_id=<?php echo $child_id; ?>&month=<?php echo $prevMonth; ?>">← Previous Month</a>
                    <h3><?php echo $currentMonthYear; ?></h3>
                    <a href="?child_id=<?php echo $child_id; ?>&month=<?php echo $nextMonth; ?>">Next Month →</a>
                </div>
                
                <!-- Calendar View -->
                <div class="calendar">
                    <div class="calendar-header">
                        <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                    </div>
                    <div class="calendar-grid">
                        <?php
                        $day = 1;
                        for($i = 0; $i < $firstDay; $i++) {
                            echo '<div class="calendar-day empty"></div>';
                        }
                        for($d = 1; $d <= $daysInMonth; $d++) {
                            $date = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                            $status = $attendanceByDate[$date] ?? '';
                            $class = $status == 'present' ? 'present' : ($status == 'absent' ? 'absent' : '');
                            echo '<div class="calendar-day ' . $class . '">
                                    <div class="day-number">' . $d . '</div>
                                    <div class="status">' . ($status ? ucfirst($status) : '—') . '</div>
                                  </div>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Detailed Table -->
                <div class="data-table">
                    <h3 style="padding: 15px; margin: 0;">Detailed Attendance Record</h3>
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if(count($attendances) > 0): ?>
                                <?php foreach($attendances as $att): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($att['date'])); ?></td>
                                        <td><?php echo ucfirst($att['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" style="text-align: center;">No attendance records found for this month</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>