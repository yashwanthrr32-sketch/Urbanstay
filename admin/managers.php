<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

// Get all managers with their PG details
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.created_at as registered_date,
        u.status,
        u.security_question,
        u.security_answer,
        p.id as pg_id,
        p.name as pg_name,
        p.address as pg_address,
        p.type as pg_type,
        p.price_per_month,
        p.status as pg_status,
        p.created_at as pg_created_date,
        (SELECT COUNT(*) FROM bookings b JOIN pgs pg ON b.pg_id = pg.id WHERE pg.manager_id = u.id AND b.status = 'confirmed') as total_tenants,
        (SELECT COUNT(*) FROM rooms r JOIN pgs pg ON r.pg_id = pg.id WHERE pg.manager_id = u.id) as total_rooms,
        (SELECT COUNT(*) FROM complaints c JOIN pgs pg ON c.pg_id = pg.id WHERE pg.manager_id = u.id AND c.status = 'open') as open_complaints
    FROM users u
    LEFT JOIN pgs p ON u.id = p.manager_id
    WHERE u.role = 'manager'
    ORDER BY u.created_at DESC
");
$stmt->execute();
$managers = $stmt->fetchAll();

// Get statistics
$total_managers = count($managers);
$active_managers = count(array_filter($managers, function($m) { return $m['status'] == 'active'; }));
$pending_managers = count(array_filter($managers, function($m) { return $m['status'] == 'pending'; }));
$inactive_managers = count(array_filter($managers, function($m) { return $m['status'] == 'inactive'; }));

// Handle status update
if(isset($_POST['update_status'])) {
    $manager_id = $_POST['manager_id'];
    $new_status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'manager'");
    $stmt->execute([$new_status, $manager_id]);
    
    // If approving manager, also approve their PG
    if($new_status == 'active') {
        $stmt = $pdo->prepare("UPDATE pgs SET status = 'approved' WHERE manager_id = ?");
        $stmt->execute([$manager_id]);
    }
    
    header('Location: managers.php?updated=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PG Managers - Urban Stay Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #185FA5;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            width: 90%;
            max-width: 800px;
            border-radius: 10px;
            position: relative;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }
        .close:hover {
            color: #333;
        }
        .detail-row {
            margin-bottom: 12px;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 180px;
            color: #185FA5;
        }
        .detail-value {
            display: inline-block;
            color: #333;
        }
        .section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section h4 {
            color: #185FA5;
            margin-bottom: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .btn-view {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-view:hover {
            background-color: #138496;
        }
        .btn-primary {
            background-color: #185FA5;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
        }
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-section input, .filter-section select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
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
        tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="managers.php" class="active">PG Managers</a></li>
                <li><a href="tenants.php">All Tenants</a></li>
<li><a href="parents.php">Parents</a></li>
        
                <li><a href="pg-listings.php">PG Listings</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="payments.php">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>PG Managers</h1>
            
            <?php if(isset($_GET['updated'])): ?>
                <div class="success-message">Manager status updated successfully!</div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Managers</h3>
                    <div class="stat-number"><?php echo $total_managers; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Managers</h3>
                    <div class="stat-number"><?php echo $active_managers; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Approval</h3>
                    <div class="stat-number"><?php echo $pending_managers; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Inactive</h3>
                    <div class="stat-number"><?php echo $inactive_managers; ?></div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <input type="text" id="searchInput" placeholder="Search by name, email, PG name..." style="flex:1;">
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <!-- Managers Table -->
            <div class="table-container">
                <table id="managersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Manager Info</th>
                            <th>PG Details</th>
                            <th>Statistics</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($managers as $manager): ?>
                            <tr>
                                <td><?php echo $manager['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($manager['name']); ?></strong><br>
                                    <small>📧 <?php echo htmlspecialchars($manager['email']); ?></small><br>
                                    <small>📱 <?php echo htmlspecialchars($manager['phone']); ?></small><br>
                                    <small>📅 Joined: <?php echo date('d M Y', strtotime($manager['registered_date'])); ?></small>
                                </td>
                                <td>
                                    <?php if($manager['pg_name']): ?>
                                        <strong>🏠 <?php echo htmlspecialchars($manager['pg_name']); ?></strong><br>
                                        <small>📍 <?php echo htmlspecialchars(substr($manager['pg_address'], 0, 50)); ?>...</small><br>
                                        <small>👥 <?php echo $manager['pg_type']; ?></small><br>
                                        <small>💰 ₹<?php echo number_format($manager['price_per_month']); ?>/month</small>
                                    <?php else: ?>
                                        <span style="color:#999;">No PG assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>👨‍👩‍👧‍👦 Tenants: <?php echo $manager['total_tenants']; ?></small><br>
                                    <small>🚪 Rooms: <?php echo $manager['total_rooms']; ?></small><br>
                                    <small>📋 Complaints: <?php echo $manager['open_complaints']; ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $manager['status']; ?>">
                                        <?php echo ucfirst($manager['status']); ?>
                                    </span>
                                    <form method="POST" style="margin-top:5px;">
                                        <input type="hidden" name="manager_id" value="<?php echo $manager['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" style="padding:5px;">
                                            <option value="active" <?php echo $manager['status'] == 'active' ? 'selected' : ''; ?>>Set Active</option>
                                            <option value="pending" <?php echo $manager['status'] == 'pending' ? 'selected' : ''; ?>>Set Pending</option>
                                            <option value="inactive" <?php echo $manager['status'] == 'inactive' ? 'selected' : ''; ?>>Set Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td>
                                    <a href="view_manager.php?id=<?php echo $manager['id']; ?>" class="btn-view">View Details</a>                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Manager Details Modal -->
    <div id="managerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeManagerModal()">&times;</span>
            <h2>Manager Complete Details</h2>
            <div id="managerDetails"></div>
        </div>
    </div>
    
    <style>
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
    </style>
    
    <script>
        function viewManagerDetails(managerId) {
            console.log("Viewing manager ID:", managerId);
            
            const modal = document.getElementById('managerModal');
            const detailsDiv = document.getElementById('managerDetails');
            
            if(!modal || !detailsDiv) {
                alert("Error: Could not open details view");
                return;
            }
            
            detailsDiv.innerHTML = '<div style="text-align:center; padding:20px;">Loading manager details...</div>';
            modal.style.display = 'block';
            
            fetch(`../ajax/get_manager_details.php?manager_id=${managerId}`)
                .then(response => response.json())
                .then(data => {
                    console.log("Response data:", data);
                    
                    if(!data.success) {
                        detailsDiv.innerHTML = `<div style="color:red; padding:20px;">Error: ${data.error}</div>`;
                        return;
                    }
                    
                    const m = data.data;
                    
                    let html = `
                        <div class="section">
                            <h4>👤 Manager Information</h4>
                            <div class="detail-row">
                                <span class="detail-label">Full Name:</span>
                                <span class="detail-value">${escapeHtml(m.name)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value">${escapeHtml(m.email)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value">${escapeHtml(m.phone)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Security Question:</span>
                                <span class="detail-value">${escapeHtml(m.security_question)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Security Answer:</span>
                                <span class="detail-value">${escapeHtml(m.security_answer)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Registered On:</span>
                                <span class="detail-value">${m.registered_date}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Account Status:</span>
                                <span class="detail-value">
                                    <span class="status-badge status-${m.status}">${m.status.toUpperCase()}</span>
                                </span>
                            </div>
                        </div>
                        
                        <div class="section">
                            <h4>🏠 PG Information</h4>
                            <div class="detail-row">
                                <span class="detail-label">PG Name:</span>
                                <span class="detail-value">${escapeHtml(m.pg_name || 'Not assigned')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">PG Address:</span>
                                <span class="detail-value">${escapeHtml(m.pg_address || 'N/A')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">PG Type:</span>
                                <span class="detail-value">${escapeHtml(m.pg_type || 'N/A')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Price per Month:</span>
                                <span class="detail-value">₹${m.price_per_month ? Number(m.price_per_month).toLocaleString() : 'Not set'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">PG Status:</span>
                                <span class="detail-value">${escapeHtml(m.pg_status || 'N/A')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">PG Created:</span>
                                <span class="detail-value">${m.pg_created_date || 'N/A'}</span>
                            </div>
                        </div>
                        
                        <div class="section">
                            <h4>📊 Statistics</h4>
                            <div class="detail-row">
                                <span class="detail-label">Total Tenants:</span>
                                <span class="detail-value">${m.total_tenants}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Rooms:</span>
                                <span class="detail-value">${m.total_rooms}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Open Complaints:</span>
                                <span class="detail-value">${m.open_complaints}</span>
                            </div>
                        </div>
                    `;
                    
                    detailsDiv.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    detailsDiv.innerHTML = `<div style="color:red; padding:20px;">Error loading details: ${error.message}</div>`;
                });
        }
        
        function closeManagerModal() {
            document.getElementById('managerModal').style.display = 'none';
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('managerModal');
            if(event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Filter functionality
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            
            const rows = document.querySelectorAll('#managersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                let show = true;
                
                if(search && !text.includes(search)) show = false;
                
                if(status === 'active' && !text.includes('active')) show = false;
                if(status === 'pending' && !text.includes('pending')) show = false;
                if(status === 'inactive' && !text.includes('inactive')) show = false;
                
                row.style.display = show ? '' : 'none';
            });
        }
    </script>
</body>
</html>