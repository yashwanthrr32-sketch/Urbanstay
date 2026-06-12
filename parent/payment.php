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

if(count($children) == 0) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>No Children - Urban Stay</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <div class="dashboard-container">
            <div class="sidebar">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="children.php">My Children</a></li>
                    <li><a href="attendance.php">Attendance</a></li>
                    <li><a href="payment.php" class="active">Rent & Payment</a></li>
                    <li><a href="profile.php">Profile</a></li>
                </ul>
            </div>
            <div class="main-content">
                <div style="text-align: center; padding: 50px;">
                    <h2>No Children Linked</h2>
                    <p>Please contact the PG Manager to link your child.</p>
                    <a href="dashboard.php" class="btn-primary">Go to Dashboard</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : $children[0]['id'];

// Verify child is linked
$stmt = $pdo->prepare("SELECT tenant_id FROM parent_tenant WHERE parent_id = ? AND tenant_id = ?");
$stmt->execute([$parent_id, $child_id]);
if(!$stmt->fetch()) {
    $child_id = $children[0]['id'];
}

// Get child and booking details
$stmt = $pdo->prepare("
    SELECT u.name as child_name, b.id as booking_id, b.pg_id, pg.name as pg_name, 
           pg.upi_id, pg.qr_code_image, td.rent_amount, td.due_date
    FROM users u
    JOIN bookings b ON u.id = b.tenant_id AND b.status = 'confirmed'
    JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE u.id = ?
");
$stmt->execute([$child_id]);
$booking = $stmt->fetch();

if(!$booking) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>No Active Booking</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <div class="dashboard-container">
            <div class="sidebar">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="children.php">My Children</a></li>
                    <li><a href="attendance.php">Attendance</a></li>
                    <li><a href="payment.php" class="active">Rent & Payment</a></li>
                    <li><a href="profile.php">Profile</a></li>
                </ul>
            </div>
            <div class="main-content">
                <div style="text-align: center; padding: 50px;">
                    <h2>No Active Booking</h2>
                    <p><?php echo htmlspecialchars($booking['child_name']); ?> doesn't have an active booking.</p>
                    <a href="children.php" class="btn-primary">Back to Children</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_utr'])) {
    $utr = $_POST['utr_number'];
    $amount = $_POST['amount'];
    
    $stmt = $pdo->prepare("
        INSERT INTO payments (tenant_id, pg_id, amount, payment_type, utr_number, payment_date, status)
        VALUES (?, ?, ?, 'upi', ?, CURDATE(), 'pending')
    ");
    $stmt->execute([$child_id, $booking['pg_id'], $amount, $utr]);
    $message = '<div class="success-message">UTR submitted successfully! Manager will verify it.</div>';
}

// Get payment history
$stmt = $pdo->prepare("SELECT * FROM payments WHERE tenant_id = ? ORDER BY payment_date DESC");
$stmt->execute([$child_id]);
$payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Rent - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-container { max-width: 800px; margin: 0 auto; }
        .child-selector {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .payment-info {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .payment-info .amount { font-size: 42px; font-weight: bold; color: #185FA5; margin: 10px 0; }
        .payment-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn {
            flex: 1;
            padding: 12px;
            background: #f0f0f0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .tab-btn.active { background: #185FA5; color: white; }
        .payment-tab {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .qr-code { text-align: center; margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 10px; }
        .qr-code img {
            max-width: 220px;
            border: 3px solid #185FA5;
            border-radius: 12px;
            padding: 12px;
            background: white;
            margin: 15px auto;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .qr-code img:hover { transform: scale(1.05); }
        .qr-actions { display: flex; gap: 12px; justify-content: center; margin-top: 15px; flex-wrap: wrap; }
        .btn-download { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .btn-copy { background: #185FA5; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .btn-save { background: #ffc107; color: #333; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .upi-id-box { background: #e8f4fd; padding: 18px; border-radius: 10px; margin-bottom: 25px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; }
        .btn-primary { background: #185FA5; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; width: 100%; font-size: 16px; }
        .btn-primary:hover { background: #0d3b66; }
        .payment-history { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #185FA5; }
        .success-message { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .toast-message { position: fixed; bottom: 20px; right: 20px; background: #28a745; color: white; padding: 12px 20px; border-radius: 8px; z-index: 9999; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="children.php">My Children</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="payment.php" class="active">Rent & Payment</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="payment-container">
                <h1>💰 Pay Rent</h1>
                
                <div class="child-selector">
                    <div><strong>Select Child:</strong>
                        <select onchange="window.location.href='?child_id='+this.value">
                            <?php foreach($children as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $child_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><strong>Paying for:</strong> <?php echo htmlspecialchars($booking['child_name']); ?></div>
                </div>
                
                <?php echo $message; ?>
                
                <div class="payment-info">
                    <h3><?php echo htmlspecialchars($booking['pg_name']); ?></h3>
                    <div class="amount">₹<?php echo number_format($booking['rent_amount'] ?? 0); ?></div>
                    <p>Due Date: <strong><?php echo $booking['due_date'] ?? 'End of month'; ?></strong></p>
                </div>
                
                <div class="payment-tabs">
                    <button class="tab-btn active" onclick="showTab('upi')">📱 UPI Payment</button>
                    <button class="tab-btn" onclick="showTab('cash')">💵 Cash Payment</button>
                </div>
                
                <div id="upiTab" class="payment-tab">
                    <h3>Pay via UPI</h3>
                    
                    <?php if(!empty($booking['qr_code_image']) && file_exists('../' . $booking['qr_code_image'])): ?>
                        <div class="qr-code">
                            <img src="../<?php echo $booking['qr_code_image']; ?>" alt="UPI QR Code" id="qrCodeImage">
                            <div class="qr-actions">
                                <button onclick="downloadQRCode()" class="btn-download">📥 Download QR</button>
                                <button onclick="saveQRCodeImage()" class="btn-save">💾 Save Image</button>
                                <button onclick="copyUPIID()" class="btn-copy">📋 Copy UPI ID</button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($booking['upi_id'])): ?>
                        <div class="upi-id-box">
                            <p><strong>UPI ID:</strong> <span id="upiId"><?php echo htmlspecialchars($booking['upi_id']); ?></span></p>
                            <button onclick="copyUPIID()" class="btn-copy">📋 Copy UPI ID</button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="amount" value="<?php echo $booking['rent_amount']; ?>">
                        <div class="form-group">
                            <label>UTR Number</label>
                            <input type="text" name="utr_number" required placeholder="Enter UTR number">
                        </div>
                        <button type="submit" name="submit_utr" class="btn-primary">Submit UTR</button>
                    </form>
                </div>
                
                <div id="cashTab" class="payment-tab" style="display:none;">
                    <h3>Pay via Cash</h3>
                    <p>Please pay the rent amount in cash directly to the PG Manager.</p>
                </div>
                
                <?php if(count($payments) > 0): ?>
                <div class="payment-history">
                    <h3>Payment History</h3>
                    <table><thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody><?php foreach($payments as $p): ?>
                        <tr><td><?php echo $p['payment_date']; ?></td><td>₹<?php echo number_format($p['amount']); ?></td>
                        <td><?php echo ucfirst($p['status']); ?></td></tr>
                    <?php endforeach; ?></tbody></table>
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
        
        function downloadQRCode() {
            const img = document.getElementById('qrCodeImage');
            if(img) { const link = document.createElement('a'); link.download = 'qrcode.png'; link.href = img.src; link.click(); showToast('QR Code downloaded!', 'success'); }
            else showToast('QR Code not found', 'error');
        }
        
        function saveQRCodeImage() {
            const img = document.getElementById('qrCodeImage');
            if(img) { window.open(img.src, '_blank'); showToast('Image opened. Right-click to save.', 'info'); }
            else showToast('QR Code not found', 'error');
        }
        
        function copyUPIID() {
            const upiId = document.getElementById('upiId')?.innerText;
            if(upiId) { navigator.clipboard.writeText(upiId); showToast('UPI ID copied!', 'success'); }
            else showToast('UPI ID not found', 'error');
        }
        
        function showToast(msg, type) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.style.backgroundColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#185FA5');
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 3000);
        }
    </script>
</body>
</html>