<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';

$tenant_id = $_SESSION['user_id'];

// Get active booking and PG details
$stmt = $pdo->prepare("
    SELECT b.*, pg.name as pg_name, pg.upi_id, pg.qr_code_image, pg.price_per_month,
           td.rent_amount, td.due_date
    FROM bookings b
    JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE b.tenant_id = ? AND b.status = 'confirmed'
    ORDER BY b.requested_at DESC LIMIT 1
");
$stmt->execute([$tenant_id]);
$booking = $stmt->fetch();

if(!$booking) {
    header('Location: dashboard.php');
    exit;
}

// Get payment history
$stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE tenant_id = ? 
    ORDER BY payment_date DESC
");
$stmt->execute([$tenant_id]);
$payments = $stmt->fetchAll();

$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_utr'])) {
    $utr = $_POST['utr_number'];
    $amount = $_POST['amount'];
    
    $stmt = $pdo->prepare("
        INSERT INTO payments (tenant_id, pg_id, amount, payment_type, utr_number, payment_date, status)
        VALUES (?, ?, ?, 'upi', ?, CURDATE(), 'pending')
    ");
    $stmt->execute([$tenant_id, $booking['pg_id'], $amount, $utr]);
    $message = '<div class="success-message">UTR submitted successfully! Manager will verify it.</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent & Payment - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .payment-info {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .payment-info .amount {
            font-size: 42px;
            font-weight: bold;
            color: #185FA5;
            margin: 10px 0;
        }
        .payment-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            flex: 1;
            padding: 12px;
            background: #f0f0f0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .tab-btn.active {
            background: #185FA5;
            color: white;
        }
        .payment-tab {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .qr-code {
            text-align: center;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .qr-code p {
            margin-bottom: 10px;
            color: #333;
        }
        .qr-code img {
            max-width: 220px;
            border: 3px solid #185FA5;
            border-radius: 12px;
            padding: 12px;
            background: white;
            margin: 15px auto;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .qr-code img:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        .qr-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .btn-download {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-download:hover {
            background: #218838;
        }
        .btn-copy {
            background: #185FA5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-copy:hover {
            background: #0d3b66;
        }
        .btn-save {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-save:hover {
            background: #e0a800;
        }
        .upi-id-box {
            background: #e8f4fd;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }
        .upi-id-box p {
            margin-bottom: 12px;
            font-size: 16px;
        }
        .upi-id-box strong {
            color: #185FA5;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #185FA5;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        .btn-primary {
            background: #185FA5;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: #0d3b66;
        }
        .payment-history {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .payment-history h3 {
            color: #185FA5;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #185FA5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #185FA5;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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
                <li><a href="payment.php" class="active">Rent & Payment</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="payment-container">
                <h1>💰 Rent & Payment</h1>
                
                <?php echo $message; ?>
                
                <!-- Payment Info -->
                <div class="payment-info">
                    <h3><?php echo htmlspecialchars($booking['pg_name']); ?></h3>
                    <div class="amount">₹<?php echo number_format($booking['rent_amount'] ?? $booking['price_per_month']); ?></div>
                    <p>Due Date: <strong><?php echo $booking['due_date'] ?? 'End of month'; ?></strong></p>
                </div>
                
                <!-- Payment Tabs -->
                <div class="payment-tabs">
                    <button class="tab-btn active" onclick="showTab('upi')">📱 UPI Payment</button>
                    <button class="tab-btn" onclick="showTab('cash')">💵 Cash Payment</button>
                </div>
                
                <!-- UPI Tab -->
                <div id="upiTab" class="payment-tab">
                    <h3 style="margin-top: 0;">Pay via UPI</h3>
                    
                    <?php if(!empty($booking['qr_code_image']) && file_exists('../' . $booking['qr_code_image'])): ?>
                        <div class="qr-code">
                            <p><strong>Scan QR Code to Pay</strong></p>
                            <img src="../<?php echo $booking['qr_code_image']; ?>" alt="UPI QR Code" id="qrCodeImage">
                            <div class="qr-actions">
                                <button onclick="downloadQRCode()" class="btn-download">📥 Download QR Code</button>
                                <button onclick="saveQRCodeImage()" class="btn-save">💾 Save Image</button>
                                <button onclick="copyUPIID()" class="btn-copy">📋 Copy UPI ID</button>
                            </div>
                            <p style="margin-top: 15px; font-size: 12px; color: #666;">
                                💡 Tip: Right-click on QR code to save image directly
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="qr-code">
                            <p>⚠️ No QR code uploaded by manager yet.</p>
                            <p>You can still pay using UPI ID below:</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($booking['upi_id'])): ?>
                        <div class="upi-id-box">
                            <p><strong>UPI ID:</strong> <span id="upiId"><?php echo htmlspecialchars($booking['upi_id']); ?></span></p>
                            <button onclick="copyUPIID()" class="btn-copy">📋 Copy UPI ID</button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="amount" value="<?php echo $booking['rent_amount'] ?? $booking['price_per_month']; ?>">
                        <div class="form-group">
                            <label>UTR Number (Transaction Reference Number)</label>
                            <input type="text" name="utr_number" required placeholder="Enter UTR number from your payment app">
                            <small>After successful payment, enter the UTR/Reference number shown in your UPI app (e.g., 123456789012)</small>
                        </div>
                        <button type="submit" name="submit_utr" class="btn-primary">Submit UTR for Verification</button>
                    </form>
                </div>
                
                <!-- Cash Tab -->
                <div id="cashTab" class="payment-tab" style="display:none;">
                    <h3 style="margin-top: 0;">Pay via Cash</h3>
                    <div style="text-align: center; padding: 20px;">
                        <p style="font-size: 48px; margin-bottom: 10px;">💵</p>
                        <p>Please pay the rent amount in cash directly to the PG Manager.</p>
                        <div style="background: #e8f4fd; padding: 15px; border-radius: 8px; margin-top: 20px;">
                            <p><strong>📍 Instructions:</strong></p>
                            <p>1. Visit the PG and pay the rent amount to the manager</p>
                            <p>2. The manager will record your payment in the system</p>
                            <p>3. Once recorded, the payment will appear as verified</p>
                        </div>
                    </div>
                </div>
                
                <!-- Payment History -->
                <?php if(count($payments) > 0): ?>
                <div class="payment-history">
                    <h3>📜 Payment History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                    <td>₹<?php echo number_format($payment['amount']); ?></td>
                                    <td><?php echo ucfirst($payment['payment_type']); ?></td>
                                    <td>
                                        <?php if($payment['status'] == 'verified'): ?>
                                            <span style="color: green;">✓ Verified</span>
                                        <?php elseif($payment['status'] == 'pending'): ?>
                                            <span style="color: orange;">⏳ Pending</span>
                                        <?php else: ?>
                                            <span style="color: red;">✗ Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="toast" class="toast-message" style="display: none;"></div>
    
    <script>
        function showTab(tab) {
            document.getElementById('upiTab').style.display = tab === 'upi' ? 'block' : 'none';
            document.getElementById('cashTab').style.display = tab === 'cash' ? 'block' : 'none';
            
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
        
        // Download QR Code
        function downloadQRCode() {
            const qrImage = document.getElementById('qrCodeImage');
            if(qrImage) {
                const link = document.createElement('a');
                link.download = 'urbanstay_qrcode.png';
                link.href = qrImage.src;
                link.click();
                showToast('QR Code downloaded successfully!', 'success');
            } else {
                showToast('QR Code not found', 'error');
            }
        }
        
        // Save QR Code Image (opens in new tab)
        function saveQRCodeImage() {
            const qrImage = document.getElementById('qrCodeImage');
            if(qrImage) {
                window.open(qrImage.src, '_blank');
                showToast('Image opened in new tab. Right-click to save.', 'info');
            } else {
                showToast('QR Code not found', 'error');
            }
        }
        
        // Copy UPI ID to clipboard
        function copyUPIID() {
            const upiIdElement = document.getElementById('upiId');
            if(upiIdElement) {
                const upiId = upiIdElement.innerText;
                navigator.clipboard.writeText(upiId).then(() => {
                    showToast('UPI ID copied to clipboard: ' + upiId, 'success');
                }).catch(() => {
                    showToast('Failed to copy UPI ID', 'error');
                });
            } else {
                showToast('UPI ID not found', 'error');
            }
        }
        
        // Show toast notification
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.backgroundColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#185FA5');
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
        
        // Right-click save functionality
        document.addEventListener('DOMContentLoaded', function() {
            const qrImage = document.getElementById('qrCodeImage');
            if(qrImage) {
                qrImage.addEventListener('contextmenu', function(e) {
                    showToast('Right-click to save image', 'info');
                });
            }
        });
    </script>
</body>
</html>