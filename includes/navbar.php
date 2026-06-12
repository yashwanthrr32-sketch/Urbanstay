<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection for profile photo
require_once __DIR__ . '/../config/db.php';

// Detect if we're in a subdirectory
$is_in_subdir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
                 strpos($_SERVER['PHP_SELF'], '/manager/') !== false ||
                 strpos($_SERVER['PHP_SELF'], '/tenant/') !== false ||
                 strpos($_SERVER['PHP_SELF'], '/parent/') !== false ||
                 strpos($_SERVER['PHP_SELF'], '/auth/') !== false);

$home_path = $is_in_subdir ? '../index.php' : 'index.php';
$login_path = $is_in_subdir ? '../auth/login.php' : 'auth/login.php';
$register_path = $is_in_subdir ? '../auth/register.php' : 'auth/register.php';
$logout_path = $is_in_subdir ? '../auth/logout.php' : 'auth/logout.php';

$dashboard_path = '';
$user_name = '';
$user_role = '';
$role_text = '';
$user_profile_photo = '';
$user_initial = '';

if(isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_role = $role;
    
    // Get profile photo from database
    try {
        $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_photo_data = $stmt->fetch();
        if($user_photo_data && !empty($user_photo_data['profile_photo'])) {
            $user_profile_photo = '../' . $user_photo_data['profile_photo'];
            // Check if file exists
            if(!file_exists($user_profile_photo)) {
                $user_profile_photo = '';
            }
        }
    } catch(Exception $e) {
        $user_profile_photo = '';
    }
    
    // Get user initial for fallback
    $user_initial = strtoupper(substr($user_name, 0, 1));
    
    // Set role text and dashboard path
    switch($role) {
        case 'admin':
            $role_text = 'Administrator';
            $dashboard_path = $is_in_subdir ? '../admin/dashboard.php' : 'admin/dashboard.php';
            break;
        case 'manager':
            $role_text = 'PG Manager';
            $dashboard_path = $is_in_subdir ? '../manager/dashboard.php' : 'manager/dashboard.php';
            break;
        case 'tenant':
            $role_text = 'Tenant';
            $dashboard_path = $is_in_subdir ? '../tenant/dashboard.php' : 'tenant/dashboard.php';
            break;
        case 'parent':
            $role_text = 'Parent';
            $dashboard_path = $is_in_subdir ? '../parent/dashboard.php' : 'parent/dashboard.php';
            break;
        default:
            $role_text = 'User';
    }
}
?>

<nav class="navbar">
    <div class="logo">
        <a href="<?php echo $home_path; ?>">Urban Stay</a>
    </div>
    
    <div class="nav-links">
        <!-- Home link for all users -->
        <a href="<?php echo $home_path; ?>">Home</a>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="<?php echo $dashboard_path; ?>">Dashboard</a>
            
            <!-- User Profile Dropdown -->
            <div class="user-dropdown">
                <button class="user-btn">
                    <?php if(!empty($user_profile_photo) && file_exists($user_profile_photo)): ?>
                        <img src="<?php echo $user_profile_photo; ?>" class="user-avatar-img" alt="Profile">
                    <?php else: ?>
                        <span class="user-avatar"><?php echo $user_initial; ?></span>
                    <?php endif; ?>
                    <span class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="user-role"><?php echo $role_text; ?></span>
                    </span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="dropdown-content">
                    <a href="<?php echo $dashboard_path; ?>">Dashboard</a>
                    <a href="<?php echo $home_path; ?>">Home</a>
                    <hr>
                    <a href="<?php echo $logout_path; ?>" class="logout-link">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="<?php echo $login_path; ?>" class="btn-login">Login</a>
            <a href="<?php echo $register_path; ?>">Register</a>
        <?php endif; ?>
    </div>
</nav>

<style>
.navbar {
    background-color: #185FA5;
    padding: 0.8rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.logo a {
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
    text-decoration: none;
}

.nav-links {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.nav-links a {
    color: white;
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    transition: background 0.3s;
}

.nav-links a:hover {
    background-color: rgba(255,255,255,0.2);
}

.btn-login {
    background-color: white;
    color: #185FA5 !important;
}

.btn-login:hover {
    background-color: #e0e0e0 !important;
}

/* User Dropdown Styles */
.user-dropdown {
    position: relative;
    display: inline-block;
}

.user-btn {
    background: rgba(255,255,255,0.15);
    color: white;
    border: none;
    padding: 0.4rem 0.8rem;
    border-radius: 40px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.3s;
    font-size: 14px;
}

.user-btn:hover {
    background: rgba(255,255,255,0.25);
}

.user-avatar-img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid white;
}

.user-avatar {
    font-size: 14px;
    font-weight: bold;
    background: rgba(255,255,255,0.2);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    text-transform: uppercase;
}

.user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.3;
}

.user-name {
    font-weight: bold;
    font-size: 14px;
}

.user-role {
    font-size: 11px;
    opacity: 0.8;
}

.dropdown-arrow {
    font-size: 10px;
    margin-left: 5px;
}

.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background-color: white;
    min-width: 180px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    border-radius: 8px;
    z-index: 1;
    margin-top: 5px;
    overflow: hidden;
}

.dropdown-content a {
    color: #333;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    transition: background 0.2s;
}

.dropdown-content a:hover {
    background-color: #f5f5f5;
}

.dropdown-content hr {
    margin: 0;
    border: none;
    border-top: 1px solid #eee;
}

.logout-link {
    color: #dc3545 !important;
}

.logout-link:hover {
    background-color: #fee !important;
}

.user-dropdown:hover .dropdown-content {
    display: block;
}

/* Responsive */
@media (max-width: 768px) {
    .navbar {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }
    
    .nav-links {
        justify-content: center;
    }
    
    .user-info {
        display: none;
    }
    
    .user-btn {
        padding: 0.4rem;
    }
    
    .user-avatar-img, .user-avatar {
        margin: 0;
    }
    
    .dropdown-arrow {
        display: none;
    }
}
</style>