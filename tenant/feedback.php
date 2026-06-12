<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';

$tenant_id = $_SESSION['user_id'];

// Get confirmed PG
$stmt = $pdo->prepare("
    SELECT pg_id, pg.name as pg_name 
    FROM bookings b
    JOIN pgs pg ON b.pg_id = pg.id
    WHERE b.tenant_id = ? AND b.status = 'confirmed'
    ORDER BY b.requested_at DESC LIMIT 1
");
$stmt->execute([$tenant_id]);
$booking = $stmt->fetch();

// Check if already reviewed
$stmt = $pdo->prepare("SELECT id FROM feedback WHERE tenant_id = ? AND pg_id = ?");
$stmt->execute([$tenant_id, $booking['pg_id'] ?? 0]);
$existing = $stmt->fetch();

$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $rating = $_POST['rating'];
    $review = $_POST['review'];
    
    if($existing) {
        $stmt = $pdo->prepare("UPDATE feedback SET rating = ?, review = ? WHERE tenant_id = ? AND pg_id = ?");
        $stmt->execute([$rating, $review, $tenant_id, $booking['pg_id']]);
        $message = '<div class="success-message">Feedback updated successfully!</div>';
    } else {
        $stmt = $pdo->prepare("INSERT INTO feedback (tenant_id, pg_id, rating, review) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $booking['pg_id'], $rating, $review]);
        $message = '<div class="success-message">Thank you for your feedback!</div>';
    }
}

// Get existing feedback
if($booking) {
    $stmt = $pdo->prepare("SELECT * FROM feedback WHERE tenant_id = ? AND pg_id = ?");
    $stmt->execute([$tenant_id, $booking['pg_id']]);
    $feedback = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #f5a623;
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
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="feedback.php" class="active">Feedback</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Share Your Feedback</h1>
            
            <?php echo $message; ?>
            
            <?php if($booking): ?>
                <div class="feedback-form">
                    <h2>Rate your stay at <?php echo htmlspecialchars($booking['pg_name']); ?></h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Your Rating</label>
                            <div class="star-rating">
                                <input type="radio" name="rating" value="5" id="star5" <?php echo ($feedback && $feedback['rating'] == 5) ? 'checked' : ''; ?>>
                                <label for="star5">★</label>
                                <input type="radio" name="rating" value="4" id="star4" <?php echo ($feedback && $feedback['rating'] == 4) ? 'checked' : ''; ?>>
                                <label for="star4">★</label>
                                <input type="radio" name="rating" value="3" id="star3" <?php echo ($feedback && $feedback['rating'] == 3) ? 'checked' : ''; ?>>
                                <label for="star3">★</label>
                                <input type="radio" name="rating" value="2" id="star2" <?php echo ($feedback && $feedback['rating'] == 2) ? 'checked' : ''; ?>>
                                <label for="star2">★</label>
                                <input type="radio" name="rating" value="1" id="star1" <?php echo ($feedback && $feedback['rating'] == 1) ? 'checked' : ''; ?>>
                                <label for="star1">★</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Your Review</label>
                            <textarea name="review" rows="5" placeholder="Share your experience with us..."><?php echo htmlspecialchars($feedback['review'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="submit_feedback" class="btn-primary">Submit Feedback</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">You need an active booking to submit feedback.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>