<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';

$tenant_id = $_SESSION['user_id'];
$currentMonth = date('Y-m');
$month = $_GET['month'] ?? $currentMonth;

// Get attendance for the month
$stmt = $pdo->prepare("
    SELECT date, status 
    FROM attendance 
    WHERE tenant_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
    ORDER BY date
");
$stmt->execute([$tenant_id, $month]);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .calendar {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #185FA5;
            color: white;
            text-align: center;
            padding: 0.75rem;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        .calendar-day {
            padding: 1rem;
            text-align: center;
            border: 1px solid #eee;
            min-height: 80px;
        }
        .calendar-day.present {
            background: #d4edda;
        }
        .calendar-day.absent {
            background: #f8d7da;
        }
        .calendar-day.empty {
            background: #f5f5f5;
        }
        .day-number {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="booking.php">My Booking</a></li>
                <li><a href="payment.php">Rent & Payment</a></li>
                <li><a href="attendance.php" class="active">Attendance</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Attendance Record</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Present Days</h3>
                    <div class="stat-number"><?php echo $present; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Absent Days</h3>
                    <div class="stat-number"><?php echo $absent; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Attendance %</h3>
                    <div class="stat-number"><?php echo $percentage; ?>%</div>
                </div>
            </div>
            
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
            
            <div class="data-table">
                <h3>Detailed Record</h3>
                <table>
                    <thead>
                        <tr><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($attendances as $att): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($att['date'])); ?></td>
                                <td><?php echo ucfirst($att['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(count($attendances) == 0): ?>
                            <tr><td colspan="2">No attendance records yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>