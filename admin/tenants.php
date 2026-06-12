<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

// Get ALL tenants (registered, with or without booking)
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.created_at as registered_date,
        u.status as account_status,
        b.id as booking_id,
        b.status as booking_status,
        b.requested_at as booking_date,
        b.approved_at as vacated_date,
        b.personal_info_json,
        pg.id as pg_id,
        pg.name as pg_name,
        pg.address as pg_address,
        r.room_number,
        bed.bed_label,
        td.rent_amount,
        td.due_date,
        td.move_in_date,
        pt.parent_id,
        parent.name as parent_name,
        parent.email as parent_email,
        parent.phone as parent_phone
    FROM users u
    LEFT JOIN bookings b ON u.id = b.tenant_id
    LEFT JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN beds bed ON b.bed_id = bed.id
    LEFT JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    LEFT JOIN parent_tenant pt ON u.id = pt.tenant_id
    LEFT JOIN users parent ON pt.parent_id = parent.id
    WHERE u.role = 'tenant'
    ORDER BY 
        CASE 
            WHEN b.status IN ('processing', 'confirmed') THEN 1
            WHEN b.status = 'completed' THEN 2
            ELSE 3
        END,
        u.created_at DESC
");
$stmt->execute();
$all_tenants = $stmt->fetchAll();

// Separate tenants by booking status
$booked_tenants = [];
$vacated_tenants = [];
$registered_not_booked = [];

foreach($all_tenants as $tenant) {
    if($tenant['booking_status'] == 'processing' || $tenant['booking_status'] == 'confirmed') {
        $booked_tenants[] = $tenant;
    } elseif($tenant['booking_status'] == 'completed') {
        $vacated_tenants[] = $tenant;
    } else {
        $registered_not_booked[] = $tenant;
    }
}

// Get statistics
$total_tenants = count($all_tenants);
$active_bookings = count($booked_tenants);
$vacated_count = count($vacated_tenants);
$not_booked_count = count($registered_not_booked);

$stmt = $pdo->query("SELECT COUNT(*) as total FROM parent_tenant");
$linked_parents = $stmt->fetch()['total'];

// Handle tenant status update
if(isset($_POST['update_status'])) {
    $tenant_id = $_POST['tenant_id'];
    $new_status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'tenant'");
    $stmt->execute([$new_status, $tenant_id]);
    
    header('Location: tenants.php?updated=1');
    exit;
}

// Handle unlink parent
if(isset($_GET['unlink'])) {
    $tenant_id = (int)$_GET['unlink'];
    $stmt = $pdo->prepare("DELETE FROM parent_tenant WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    header('Location: tenants.php?unlinked=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Tenants - Urban Stay Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        .section-title {
            background: #185FA5;
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            margin: 25px 0 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-title h2 {
            margin: 0;
            font-size: 18px;
        }
        .section-title .count {
            background: white;
            color: #185FA5;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 14px;
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
            margin-bottom: 30px;
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
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-processing { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        .status-not-booked { background: #e2e3e5; color: #383d41; }
        .btn-view {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-view:hover {
            background-color: #138496;
        }
        .parent-linked {
            background: #e8f4fd;
            padding: 8px;
            border-radius: 5px;
        }
        .unlink-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 3px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
            margin-top: 5px;
        }
        select {
            padding: 5px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="managers.php">PG Managers</a></li>
                <li><a href="tenants.php" class="active">All Tenants</a></li>
<li><a href="parents.php">Parents</a></li>
        
                <li><a href="pg-listings.php">PG Listings</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="payments.php">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>All Tenants</h1>
            
            <?php if(isset($_GET['updated'])): ?>
                <div class="success-message">Tenant status updated successfully!</div>
            <?php endif; ?>
            <?php if(isset($_GET['unlinked'])): ?>
                <div class="success-message">Parent unlinked successfully!</div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Tenants</h3>
                    <div class="stat-number"><?php echo $total_tenants; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Bookings</h3>
                    <div class="stat-number"><?php echo $active_bookings; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Vacated</h3>
                    <div class="stat-number"><?php echo $vacated_count; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Registered (Not Booked)</h3>
                    <div class="stat-number"><?php echo $not_booked_count; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Linked Parents</h3>
                    <div class="stat-number"><?php echo $linked_parents; ?></div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <input type="text" id="searchInput" placeholder="Search by name, email, phone..." style="flex:1;">
                <select id="statusFilter">
                    <option value="">All Account Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select id="parentFilter">
                    <option value="">All Tenants</option>
                    <option value="linked">Has Parent Linked</option>
                    <option value="not_linked">No Parent Linked</option>
                </select>
            </div>
            
            <!-- Booked Tenants Section (Processing/Confirmed) -->
            <div class="section-title">
                <h2>🏠 Booked Tenants (Active)</h2>
                <span class="count"><?php echo count($booked_tenants); ?> tenants</span>
            </div>
            <div class="table-container">
                <table id="bookedTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>PG Details</th>
                            <th>Parent</th>
                            <th>Account Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($booked_tenants) > 0): ?>
                            <?php foreach($booked_tenants as $tenant): ?>
                                <tr>
                                    <td><?php echo $tenant['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($tenant['name']); ?></strong><br>
                                        <small>Joined: <?php echo date('d M Y', strtotime($tenant['registered_date'])); ?></small>
                                      </td>
                                    <td>
                                        📧 <?php echo htmlspecialchars($tenant['email']); ?><br>
                                        📱 <?php echo htmlspecialchars($tenant['phone']); ?>
                                      </td>
                                    <td>
                                        🏠 <?php echo htmlspecialchars($tenant['pg_name']); ?><br>
                                        🛏️ Room <?php echo $tenant['room_number']; ?>, Bed <?php echo $tenant['bed_label']; ?><br>
                                        <span class="status-badge status-<?php echo $tenant['booking_status']; ?>">
                                            <?php echo ucfirst($tenant['booking_status']); ?>
                                        </span>
                                      </td>
                                    <td>
                                        <?php if($tenant['parent_id']): ?>
                                            <div class="parent-linked">
                                                <strong><?php echo htmlspecialchars($tenant['parent_name']); ?></strong><br>
                                                <small><?php echo $tenant['parent_email']; ?></small><br>
                                                <a href="?unlink=<?php echo $tenant['id']; ?>" class="unlink-btn" onclick="return confirm('Remove parent link?')">Unlink Parent</a>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:#999;">Not linked</span>
                                        <?php endif; ?>
                                      </td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="active" <?php echo $tenant['account_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $tenant['account_status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                      </td>
                                    <td>
                                        <a href="view_tenant.php?id=<?php echo $tenant['id']; ?>" class="btn-view">View Details</a>
                                      </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="no-data">No active bookings found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Registered But Not Booked Tenants Section -->
            <div class="section-title">
                <h2>📝 Registered (Not Booked Yet)</h2>
                <span class="count"><?php echo count($registered_not_booked); ?> tenants</span>
            </div>
            <div class="table-container">
                <table id="notBookedTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Registration Date</th>
                            <th>Parent Linked</th>
                            <th>Account Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($registered_not_booked) > 0): ?>
                            <?php foreach($registered_not_booked as $tenant): ?>
                                <tr>
                                    <td><?php echo $tenant['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($tenant['name']); ?></strong>
                                    </td>
                                    <td>
                                        📧 <?php echo htmlspecialchars($tenant['email']); ?><br>
                                        📱 <?php echo htmlspecialchars($tenant['phone']); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($tenant['registered_date'])); ?>
                                    </td>
                                    <td>
                                        <?php if($tenant['parent_id']): ?>
                                            <div class="parent-linked">
                                                <strong><?php echo htmlspecialchars($tenant['parent_name']); ?></strong><br>
                                                <small><?php echo $tenant['parent_email']; ?></small><br>
                                                <a href="?unlink=<?php echo $tenant['id']; ?>" class="unlink-btn" onclick="return confirm('Remove parent link?')">Unlink Parent</a>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:#999;">Not linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="active" <?php echo $tenant['account_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $tenant['account_status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <a href="view_tenant.php?id=<?php echo $tenant['id']; ?>" class="btn-view">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="no-data">No registered tenants without booking</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Vacated Tenants Section -->
            <?php if(count($vacated_tenants) > 0): ?>
            <div class="section-title">
                <h2>📦 Vacated Tenants</h2>
                <span class="count"><?php echo count($vacated_tenants); ?> tenants</span>
            </div>
            <div class="table-container">
                <table id="vacatedTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Previous PG</th>
                            <th>Vacated Date</th>
                            <th>Account Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($vacated_tenants as $tenant): ?>
                            <tr>
                                <td><?php echo $tenant['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($tenant['name']); ?></strong><br>
                                    <small>Joined: <?php echo date('d M Y', strtotime($tenant['registered_date'])); ?></small>
                                 </td>
                                <td>
                                    📧 <?php echo htmlspecialchars($tenant['email']); ?><br>
                                    📱 <?php echo htmlspecialchars($tenant['phone']); ?>
                                 </td>
                                <td>
                                    🏠 <?php echo htmlspecialchars($tenant['pg_name']); ?><br>
                                    🛏️ Room <?php echo $tenant['room_number']; ?>, Bed <?php echo $tenant['bed_label']; ?>
                                 </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($tenant['vacated_date'])); ?>
                                 </td>
                                <td>
                                    <span class="status-badge status-completed">Vacated</span>
                                 </td>
                                <td>
                                    <a href="view_tenant.php?id=<?php echo $tenant['id']; ?>" class="btn-view">View Details</a>
                                 </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Filter functionality for all tables
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const parentFilter = document.getElementById('parentFilter');
        
        function filterTables() {
            const search = searchInput ? searchInput.value.toLowerCase() : '';
            const status = statusFilter ? statusFilter.value : '';
            const parent = parentFilter ? parentFilter.value : '';
            
            // Filter Booked Tenants Table
            const bookedRows = document.querySelectorAll('#bookedTable tbody tr');
            bookedRows.forEach(row => {
                if(row.querySelector('td')) {
                    const text = row.innerText.toLowerCase();
                    let show = true;
                    if(search && !text.includes(search)) show = false;
                    if(status === 'active' && !text.includes('active')) show = false;
                    if(status === 'inactive' && !text.includes('inactive')) show = false;
                    if(parent === 'linked' && !text.includes('linked')) show = false;
                    if(parent === 'not_linked' && text.includes('linked')) show = false;
                    row.style.display = show ? '' : 'none';
                }
            });
            
            // Filter Not Booked Table
            const notBookedRows = document.querySelectorAll('#notBookedTable tbody tr');
            notBookedRows.forEach(row => {
                if(row.querySelector('td')) {
                    const text = row.innerText.toLowerCase();
                    let show = true;
                    if(search && !text.includes(search)) show = false;
                    if(status === 'active' && !text.includes('active')) show = false;
                    if(status === 'inactive' && !text.includes('inactive')) show = false;
                    if(parent === 'linked' && !text.includes('linked')) show = false;
                    if(parent === 'not_linked' && text.includes('linked')) show = false;
                    row.style.display = show ? '' : 'none';
                }
            });
        }
        
        if(searchInput) searchInput.addEventListener('keyup', filterTables);
        if(statusFilter) statusFilter.addEventListener('change', filterTables);
        if(parentFilter) parentFilter.addEventListener('change', filterTables);
    </script>
</body>
</html>