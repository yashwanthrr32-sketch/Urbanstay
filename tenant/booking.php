<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';

$tenant_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT b.*, pg.name as pg_name, pg.address, r.room_number, bed.bed_label,
           td.rent_amount, td.due_date, td.move_in_date
    FROM bookings b
    JOIN pgs pg ON b.pg_id = pg.id
    JOIN beds bed ON b.bed_id = bed.id
    JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE b.tenant_id = ? 
    ORDER BY b.requested_at DESC
");
$stmt->execute([$tenant_id]);
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Booking - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="booking.php" class="active">My Booking</a></li>
                <li><a href="payment.php">Rent & Payment</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>My Bookings</h1>
            
            <?php if(count($bookings) > 0): ?>
                <div class="data-table">
                    <table>
                        <thead>
                            <tr><th>PG Name</th><th>Room/Bed</th><th>Status</th><th>Move-in Date</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['pg_name']); ?></td>
                                    <td>Room <?php echo $booking['room_number']; ?>, Bed <?php echo $booking['bed_label']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $booking['move_in_date'] ?? 'Pending'; ?></td>
                                    <td>
                                        <?php if($booking['status'] == 'confirmed'): ?>
                                            <button onclick="requestVacate(<?php echo $booking['id']; ?>)" class="btn-danger">Request Vacate</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">You have no bookings yet. <a href="../index.php">Browse PGs</a> to make a booking.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function requestVacate(bookingId) {
            if(confirm('Are you sure you want to request to vacate?')) {
                fetch('../ajax/request_vacate.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({booking_id: bookingId})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('Vacate request submitted successfully');
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>