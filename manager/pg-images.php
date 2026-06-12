<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, name FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();

if(!$pg) {
    echo "<div class='container'><h1>No PG found</h1></div>";
    exit;
}

$pg_id = $pg['id'];
$message = '';

// Handle multiple image upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pg_images'])) {
    $upload_dir = '../assets/images/uploads/';
    
    // Create directory if not exists
    if(!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $uploaded_count = 0;
    $failed_count = 0;
    
    // Loop through all uploaded files
    foreach($_FILES['pg_images']['tmp_name'] as $key => $tmp_name) {
        if($_FILES['pg_images']['error'][$key] == 0) {
            $file_type = $_FILES['pg_images']['type'][$key];
            $file_name = $_FILES['pg_images']['name'][$key];
            
            if(in_array($file_type, $allowed_types)) {
                $filename = time() . '_' . rand(1000, 9999) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
                $filepath = $upload_dir . $filename;
                
                if(move_uploaded_file($tmp_name, $filepath)) {
                    $stmt = $pdo->prepare("INSERT INTO pg_images (pg_id, image_path) VALUES (?, ?)");
                    $stmt->execute([$pg_id, 'assets/images/uploads/' . $filename]);
                    $uploaded_count++;
                } else {
                    $failed_count++;
                }
            } else {
                $failed_count++;
            }
        }
    }
    
    if($uploaded_count > 0) {
        $message = '<div class="success-message">Successfully uploaded ' . $uploaded_count . ' images!';
        if($failed_count > 0) {
            $message .= ' (' . $failed_count . ' failed)';
        }
        $message .= '</div>';
    } elseif($failed_count > 0) {
        $message = '<div class="error-message">Failed to upload ' . $failed_count . ' images. Please check file types (JPG, PNG, GIF only).</div>';
    } else {
        $message = '<div class="error-message">No images selected or invalid file types.</div>';
    }
}

// Handle single image delete
if(isset($_GET['delete_image'])) {
    $image_id = $_GET['delete_image'];
    $stmt = $pdo->prepare("SELECT image_path FROM pg_images WHERE id = ? AND pg_id = ?");
    $stmt->execute([$image_id, $pg_id]);
    $image = $stmt->fetch();
    
    if($image) {
        // Delete file
        $file_to_delete = '../' . $image['image_path'];
        if(file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM pg_images WHERE id = ?");
        $stmt->execute([$image_id]);
        $message = '<div class="success-message">Image deleted successfully!</div>';
    }
}

// Handle multiple image delete
if(isset($_POST['delete_multiple'])) {
    $selected_images = isset($_POST['selected_images']) ? $_POST['selected_images'] : [];
    
    if(count($selected_images) > 0) {
        $deleted_count = 0;
        foreach($selected_images as $image_id) {
            $stmt = $pdo->prepare("SELECT image_path FROM pg_images WHERE id = ? AND pg_id = ?");
            $stmt->execute([$image_id, $pg_id]);
            $image = $stmt->fetch();
            
            if($image) {
                $file_to_delete = '../' . $image['image_path'];
                if(file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
                
                $stmt = $pdo->prepare("DELETE FROM pg_images WHERE id = ?");
                $stmt->execute([$image_id]);
                $deleted_count++;
            }
        }
        $message = '<div class="success-message">Successfully deleted ' . $deleted_count . ' images!</div>';
    } else {
        $message = '<div class="error-message">No images selected for deletion.</div>';
    }
}

// Get all images
$stmt = $pdo->prepare("SELECT * FROM pg_images WHERE pg_id = ? ORDER BY id DESC");
$stmt->execute([$pg_id]);
$images = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage PG Images - <?php echo htmlspecialchars($pg['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .image-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .upload-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .upload-area {
            background: #f8f9fa;
            border: 2px dashed #185FA5;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background: #e8f4fd;
            border-color: #0d3b66;
        }
        .upload-area input {
            display: none;
        }
        .upload-area label {
            cursor: pointer;
            display: block;
        }
        .upload-icon {
            font-size: 48px;
            color: #185FA5;
            margin-bottom: 10px;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .image-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            transition: transform 0.3s;
        }
        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .image-card.selected {
            border: 2px solid #28a745;
            box-shadow: 0 0 0 2px #28a745;
        }
        .image-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .image-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            z-index: 10;
        }
        .image-info {
            padding: 10px;
            text-align: center;
            background: #f8f9fa;
        }
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-top: 5px;
        }
        .delete-btn:hover {
            background: #c82333;
        }
        .bulk-actions {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: #185FA5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .image-count {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        .select-all {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="tenants.php">Tenants</a></li>
                <li><a href="rooms.php">Rooms & Beds</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="pg-images.php" class="active">PG Images</a></li>
                <li><a href="pg-settings.php">PG Settings</a></li>
<li><a href="profile.php">My Profile</a></li>


            </ul>
        </div>
        
        <div class="main-content">
            <div class="image-container">
                <h1>Manage PG Images - <?php echo htmlspecialchars($pg['name']); ?></h1>
                
                <?php echo $message; ?>
                
                <!-- Upload Section -->
                <div class="upload-section">
                    <h2>Upload Multiple Images</h2>
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                            <label>
                                <div class="upload-icon">📸</div>
                                <div>Click or drag to upload multiple images</div>
                                <small>Supported formats: JPG, PNG, GIF (Max 5MB each)</small>
                                <input type="file" name="pg_images[]" id="fileInput" accept="image/jpeg,image/png,image/jpg,image/gif" multiple style="display:none;" onchange="document.getElementById('uploadForm').submit()">
                            </label>
                        </div>
                    </form>
                    <div class="image-count">
                        Total Images: <?php echo count($images); ?>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <?php if(count($images) > 0): ?>
                <form method="POST" id="bulkDeleteForm">
                    <div class="bulk-actions">
                        <button type="button" onclick="selectAll()" class="btn-warning">Select All</button>
                        <button type="button" onclick="deselectAll()" class="btn-warning">Deselect All</button>
                        <button type="submit" name="delete_multiple" class="btn-danger" onclick="return confirmDeleteMultiple()">Delete Selected</button>
                        <div class="select-all">
                            <span id="selectedCount">0</span> images selected
                        </div>
                    </div>
                    
                    <!-- Image Gallery -->
                    <div class="image-grid">
                        <?php foreach($images as $image): ?>
                            <div class="image-card" data-id="<?php echo $image['id']; ?>">
                                <input type="checkbox" name="selected_images[]" value="<?php echo $image['id']; ?>" class="image-checkbox" onchange="updateSelectedCount()">
                                <img src="../<?php echo $image['image_path']; ?>" alt="PG Image">
                                <div class="image-info">
                                    <small>ID: <?php echo $image['id']; ?></small>
                                    <a href="?delete_image=<?php echo $image['id']; ?>" class="delete-btn" onclick="return confirm('Delete this image?')">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
                <?php else: ?>
                    <div class="upload-section" style="text-align: center;">
                        <p>No images uploaded yet. Click the upload area above to add images.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        let selectedCount = 0;
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.image-checkbox:checked');
            selectedCount = checkboxes.length;
            document.getElementById('selectedCount').innerText = selectedCount;
            
            // Highlight selected cards
            document.querySelectorAll('.image-card').forEach(card => {
                const checkbox = card.querySelector('.image-checkbox');
                if(checkbox && checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.image-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        }
        
        function deselectAll() {
            const checkboxes = document.querySelectorAll('.image-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }
        
        function confirmDeleteMultiple() {
            if(selectedCount === 0) {
                alert('Please select images to delete');
                return false;
            }
            return confirm('Delete ' + selectedCount + ' selected images? This action cannot be undone.');
        }
        
        // Preview selected files
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const files = e.target.files;
            if(files.length > 0) {
                let message = 'Selected ' + files.length + ' file(s) to upload:\n';
                for(let i = 0; i < files.length; i++) {
                    message += '- ' + files[i].name + ' (' + (files[i].size / 1024).toFixed(2) + ' KB)\n';
                }
                if(confirm(message + '\n\nUpload these images?')) {
                    document.getElementById('uploadForm').submit();
                }
            }
        });
    </script>
</body>
</html>