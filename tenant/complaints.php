
<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';

$tenant_id = $_SESSION['user_id'];

// Get tenant's PG
$stmt = $pdo->prepare("
    SELECT pg_id FROM bookings 
    WHERE tenant_id = ? AND status = 'confirmed' 
    ORDER BY requested_at DESC LIMIT 1
");
$stmt->execute([$tenant_id]);
$booking = $stmt->fetch();
$pg_id = $booking['pg_id'] ?? 0;

// Handle new complaint
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complaint'])) {
    $category = $_POST['category'];
    $description = $_POST['description'];
    
    $stmt = $pdo->prepare("INSERT INTO complaints (tenant_id, pg_id, category, description, status) VALUES (?, ?, ?, ?, 'open')");
    $stmt->execute([$tenant_id, $pg_id, $category, $description]);
    $success = "Complaint submitted successfully";
}

// Get complaints
$stmt = $pdo->prepare("SELECT * FROM complaints WHERE tenant_id = ? ORDER BY created_at DESC");
$stmt->execute([$tenant_id]);
$complaints = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="booking.php">My Booking</a></li>
                <li><a href="payment.php">Rent & Payment</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="complaints.php" class="active">Complaints</a></li>
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Complaints</h1>
            
            <?php if(isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="complaint-form">
                <h2>Raise a Complaint</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" required>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Food Quality">Food Quality</option>
                            <option value="Cleanliness">Cleanliness</option>
                            <option value="Staff Behavior">Staff Behavior</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4" required placeholder="Please describe your issue in detail..."></textarea>
                    </div>
                    <button type="submit" name="submit_complaint" class="btn-primary">Submit Complaint</button>
                </form>
            </div>
            
            <div class="complaints-list">
                <h2>My Complaints</h2>
                <?php if(count($complaints) > 0): ?>
                    <?php foreach($complaints as $complaint): ?>
                        <div class="complaint-card">
                            <div class="complaint-header">
                                <strong><?php echo htmlspecialchars($complaint['category']); ?></strong>
                                <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                    <?php echo ucfirst($complaint['status']); ?>
                                </span>
                            </div>
                            <p><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                            <small>Submitted: <?php echo date('d M Y', strtotime($complaint['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No complaints filed yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>