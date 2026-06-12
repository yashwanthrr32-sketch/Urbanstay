<?php
require_once '../config/db.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $security_question = $_POST['security_question'];
    $security_answer = $_POST['security_answer'];
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if($stmt->fetch()) {
        $error = 'Email already registered';
    } else {
        // Create upload directory if not exists
        $upload_dir = '../assets/images/uploads/documents/';
        if(!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if($role == 'manager') {
            $pg_name = $_POST['pg_name'];
            $pg_address = $_POST['pg_address'];
            $status = 'pending';
            
            // Check if files are uploaded for manager
            if(!isset($_FILES['manager_id_proof']) || $_FILES['manager_id_proof']['error'] != 0) {
                $error = 'Please upload Government ID Proof';
            } elseif(!isset($_FILES['manager_address_proof']) || $_FILES['manager_address_proof']['error'] != 0) {
                $error = 'Please upload Address Proof';
            } elseif(!isset($_FILES['manager_profile_photo']) || $_FILES['manager_profile_photo']['error'] != 0) {
                $error = 'Please upload Profile Photo';
            } else {
                $pdo->beginTransaction();
                try {
                    // Insert user
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, security_question, security_answer, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $phone, $password, $role, $security_question, $security_answer, $status]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Insert PG
                    $stmt = $pdo->prepare("INSERT INTO pgs (manager_id, name, address, status) VALUES (?, ?, ?, 'pending')");
                    $stmt->execute([$user_id, $pg_name, $pg_address]);
                    
                    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                    $photo_allowed = ['image/jpeg', 'image/png', 'image/jpg'];
                    
                    // Upload Manager ID Proof
                    if(in_array($_FILES['manager_id_proof']['type'], $allowed)) {
                        $filename = 'manager_' . $user_id . '_idproof_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['manager_id_proof']['name']);
                        $filepath = $upload_dir . $filename;
                        if(move_uploaded_file($_FILES['manager_id_proof']['tmp_name'], $filepath)) {
                            $stmt = $pdo->prepare("INSERT INTO user_documents (user_id, user_role, document_type, document_path, status) VALUES (?, 'manager', 'ID Proof', ?, 'pending')");
                            $stmt->execute([$user_id, 'assets/images/uploads/documents/' . $filename]);
                        }
                    }
                    
                    // Upload Manager Address Proof
                    if(in_array($_FILES['manager_address_proof']['type'], $allowed)) {
                        $filename = 'manager_' . $user_id . '_addressproof_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['manager_address_proof']['name']);
                        $filepath = $upload_dir . $filename;
                        if(move_uploaded_file($_FILES['manager_address_proof']['tmp_name'], $filepath)) {
                            $stmt = $pdo->prepare("INSERT INTO user_documents (user_id, user_role, document_type, document_path, status) VALUES (?, 'manager', 'Address Proof', ?, 'pending')");
                            $stmt->execute([$user_id, 'assets/images/uploads/documents/' . $filename]);
                        }
                    }
                    
                    // Upload Manager Profile Photo
                    if(in_array($_FILES['manager_profile_photo']['type'], $photo_allowed)) {
                        $filename = 'manager_' . $user_id . '_profile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['manager_profile_photo']['name']);
                        $filepath = $upload_dir . $filename;
                        if(move_uploaded_file($_FILES['manager_profile_photo']['tmp_name'], $filepath)) {
                            $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                            $stmt->execute(['assets/images/uploads/documents/' . $filename, $user_id]);
                        }
                    }
                    
                    $pdo->commit();
                    $success = 'Registration successful! Please wait for admin approval.';
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        } 
        elseif($role == 'parent') {
            // Check if files are uploaded for parent
            if(!isset($_FILES['parent_id_proof']) || $_FILES['parent_id_proof']['error'] != 0) {
                $error = 'Please upload Government ID Proof';
            } elseif(!isset($_FILES['parent_address_proof']) || $_FILES['parent_address_proof']['error'] != 0) {
                $error = 'Please upload Address Proof';
            } elseif(!isset($_FILES['parent_profile_photo']) || $_FILES['parent_profile_photo']['error'] != 0) {
                $error = 'Please upload Profile Photo';
            } else {
                $pdo->beginTransaction();
                try {
                    // Insert user
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, security_question, security_answer, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->execute([$name, $email, $phone, $password, $role, $security_question, $security_answer]);
                    $user_id = $pdo->lastInsertId();
                    
                    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                    $photo_allowed = ['image/jpeg', 'image/png', 'image/jpg'];
                    
                    // Upload Parent ID Proof
                    if(in_array($_FILES['parent_id_proof']['type'], $allowed)) {
                        $filename = 'parent_' . $user_id . '_idproof_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['parent_id_proof']['name']);
                        $filepath = $upload_dir . $filename;
                        if(move_uploaded_file($_FILES['parent_id_proof']['tmp_name'], $filepath)) {
                            $stmt = $pdo->prepare("INSERT INTO user_documents (user_id, user_role, document_type, document_path, status) VALUES (?, 'parent', 'ID Proof', ?, 'pending')");
                            $stmt->execute([$user_id, 'assets/images/uploads/documents/' . $filename]);
                        } else {
                            throw new Exception('Failed to upload ID Proof');
                        }
                    }
                    
                    // Upload Parent Address Proof
                    if(in_array($_FILES['parent_address_proof']['type'], $allowed)) {
                        $filename = 'parent_' . $user_id . '_addressproof_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['parent_address_proof']['name']);
                        $filepath = $upload_dir . $filename;
                        if(move_uploaded_file($_FILES['parent_address_proof']['tmp_name'], $filepath)) {
                            $stmt = $pdo->prepare("INSERT INTO user_documents (user_id, user_role, document_type, document_path, status) VALUES (?, 'parent', 'Address Proof', ?, 'pending')");
                            $stmt->execute([$user_id, 'assets/images/uploads/documents/' . $filename]);
                        } else {
                            throw new Exception('Failed to upload Address Proof');
                        }
                    }
                    
                    // Upload Parent Profile Photo
                    if(in_array($_FILES['parent_profile_photo']['type'], $photo_allowed)) {
                        $filename = 'parent_' . $user_id . '_profile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['parent_profile_photo']['name']);
                        $filepath = $upload_dir . $filename;
                        if(move_uploaded_file($_FILES['parent_profile_photo']['tmp_name'], $filepath)) {
                            $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                            $stmt->execute(['assets/images/uploads/documents/' . $filename, $user_id]);
                        } else {
                            throw new Exception('Failed to upload Profile Photo');
                        }
                    }
                    
                    $pdo->commit();
                    $success = 'Registration successful! Please login.';
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
        else {
            // Tenant registration (no documents required)
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, security_question, security_answer, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $email, $phone, $password, $role, $security_question, $security_answer]);
            $success = 'Registration successful! Please login.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .auth-container {
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #f5f7fa;
        }
        .auth-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .role-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .tab-btn {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #185FA5;
            background: white;
            color: #185FA5;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .tab-btn.active {
            background: #185FA5;
            color: white;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn-primary {
            background: #185FA5;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            margin-top: 1rem;
        }
        .btn-primary:hover {
            background: #0d3b66;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #dc3545;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }
        .auth-link {
            text-align: center;
            margin-top: 1rem;
        }
        .auth-link a {
            color: #185FA5;
            text-decoration: none;
        }
        .document-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px solid #e0e0e0;
        }
        .document-section h4 {
            margin-bottom: 1rem;
            color: #185FA5;
            font-size: 16px;
        }
        .document-section small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 11px;
        }
        .photo-preview {
            margin-top: 10px;
            text-align: center;
        }
        .photo-preview img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 50%;
            border: 2px solid #185FA5;
            padding: 3px;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="auth-container">
        <div class="auth-card">
            <h2 style="text-align: center; color: #185FA5;">Create an Account</h2>
            <p style="text-align: center; color: #666; margin-bottom: 1.5rem;">Join Urban Stay community</p>
            
            <?php if($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="role-tabs">
                <button class="tab-btn active" data-role="tenant">Tenant</button>
                <button class="tab-btn" data-role="manager">PG Manager</button>
                <button class="tab-btn" data-role="parent">Parent</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="registerForm">
                <input type="hidden" name="role" id="role" value="tenant">
                
                <div class="form-group">
                    <label class="required-field">Full Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label class="required-field">Email Address</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label class="required-field">Phone Number</label>
                    <input type="tel" name="phone" required>
                </div>
                <div class="form-group">
                    <label class="required-field">Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label class="required-field">Security Question</label>
                    <select name="security_question" required>
                        <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                        <option value="What was your first pet's name?">What was your first pet's name?</option>
                        <option value="What is your favorite book?">What is your favorite book?</option>
                        <option value="What is your birth city?">What is your birth city?</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required-field">Security Answer</label>
                    <input type="text" name="security_answer" required>
                </div>
                
                <!-- Manager Specific Fields -->
                <div id="managerFields" style="display:none;">
                    <div class="form-group">
                        <label class="required-field">PG Name</label>
                        <input type="text" name="pg_name">
                    </div>
                    <div class="form-group">
                        <label class="required-field">PG Address</label>
                        <textarea name="pg_address" rows="2"></textarea>
                    </div>
                    <div class="document-section">
                        <h4>📄 Required Documents for Manager Verification</h4>
                        <div class="form-group">
                            <label class="required-field">Government ID Proof (Aadhar/PAN/Passport)</label>
                            <input type="file" name="manager_id_proof" accept="image/jpeg,image/png,image/jpg,application/pdf" onchange="previewDocument(this, 'managerIdPreview')">
                            <div id="managerIdPreview" class="photo-preview"></div>
                            <small>Upload clear image of your ID proof (JPG, PNG, PDF) - Max 5MB</small>
                        </div>
                        <div class="form-group">
                            <label class="required-field">Address Proof (Electricity Bill/Rent Agreement)</label>
                            <input type="file" name="manager_address_proof" accept="image/jpeg,image/png,image/jpg,application/pdf" onchange="previewDocument(this, 'managerAddressPreview')">
                            <div id="managerAddressPreview" class="photo-preview"></div>
                            <small>Upload address proof document (JPG, PNG, PDF) - Max 5MB</small>
                        </div>
                        <div class="form-group">
                            <label class="required-field">Profile Photo</label>
                            <input type="file" name="manager_profile_photo" accept="image/jpeg,image/png,image/jpg" onchange="previewPhoto(this, 'managerProfilePreview')">
                            <div id="managerProfilePreview" class="photo-preview"></div>
                            <small>Upload a recent passport size photo (JPG, PNG) - Max 2MB</small>
                        </div>
                    </div>
                </div>
                
                <!-- Parent Specific Fields -->
                <div id="parentFields" style="display:none;">
                    <div class="document-section">
                        <h4>📄 Required Documents for Parent Verification</h4>
                        <div class="form-group">
                            <label class="required-field">Government ID Proof (Aadhar/PAN/Passport)</label>
                            <input type="file" name="parent_id_proof" accept="image/jpeg,image/png,image/jpg,application/pdf" onchange="previewDocument(this, 'parentIdPreview')">
                            <div id="parentIdPreview" class="photo-preview"></div>
                            <small>Upload clear image of your ID proof (JPG, PNG, PDF) - Max 5MB</small>
                        </div>
                        <div class="form-group">
                            <label class="required-field">Address Proof</label>
                            <input type="file" name="parent_address_proof" accept="image/jpeg,image/png,image/jpg,application/pdf" onchange="previewDocument(this, 'parentAddressPreview')">
                            <div id="parentAddressPreview" class="photo-preview"></div>
                            <small>Upload address proof document (JPG, PNG, PDF) - Max 5MB</small>
                        </div>
                        <div class="form-group">
                            <label class="required-field">Profile Photo</label>
                            <input type="file" name="parent_profile_photo" accept="image/jpeg,image/png,image/jpg" onchange="previewPhoto(this, 'parentProfilePreview')">
                            <div id="parentProfilePreview" class="photo-preview"></div>
                            <small>Upload a recent passport size photo (JPG, PNG) - Max 2MB</small>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Register</button>
                <p class="auth-link">Already have an account? <a href="login.php">Login here</a></p>
            </form>
        </div>
    </div>
    
    <script>
        // Role tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const role = btn.dataset.role;
                document.getElementById('role').value = role;
                
                // Show/hide role-specific fields
                document.getElementById('managerFields').style.display = role === 'manager' ? 'block' : 'none';
                document.getElementById('parentFields').style.display = role === 'parent' ? 'block' : 'none';
            });
        });
        
        // Preview document (for PDF and images)
        function previewDocument(input, previewId) {
            const preview = document.getElementById(previewId);
            if(input.files && input.files[0]) {
                const file = input.files[0];
                const fileType = file.type;
                
                if(fileType === 'application/pdf') {
                    preview.innerHTML = `<div style="background: #f0f0f0; padding: 10px; border-radius: 5px;">
                                            📄 PDF Document: ${file.name}<br>
                                            <small>${(file.size / 1024).toFixed(2)} KB</small>
                                          </div>`;
                } else if(fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `<img src="${e.target.result}" style="max-width: 100px; max-height: 100px; border-radius: 5px; border: 2px solid #185FA5; padding: 3px;">`;
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = `<span style="color: red;">Unsupported file type</span>`;
                }
            }
        }
        
        // Preview profile photo
        function previewPhoto(input, previewId) {
            const preview = document.getElementById(previewId);
            if(input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; border-radius: 50%; border: 2px solid #185FA5; padding: 3px; object-fit: cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const role = document.getElementById('role').value;
            
            if(role === 'manager') {
                const pgName = document.querySelector('input[name="pg_name"]').value;
                const pgAddress = document.querySelector('textarea[name="pg_address"]').value;
                const idProof = document.querySelector('input[name="manager_id_proof"]').files.length;
                const addressProof = document.querySelector('input[name="manager_address_proof"]').files.length;
                const profilePhoto = document.querySelector('input[name="manager_profile_photo"]').files.length;
                
                if(!pgName || !pgAddress) {
                    alert('Please fill PG Name and Address');
                    e.preventDefault();
                    return false;
                }
                if(idProof === 0) {
                    alert('Please upload Government ID Proof');
                    e.preventDefault();
                    return false;
                }
                if(addressProof === 0) {
                    alert('Please upload Address Proof');
                    e.preventDefault();
                    return false;
                }
                if(profilePhoto === 0) {
                    alert('Please upload Profile Photo');
                    e.preventDefault();
                    return false;
                }
            }
            
            if(role === 'parent') {
                const idProof = document.querySelector('input[name="parent_id_proof"]').files.length;
                const addressProof = document.querySelector('input[name="parent_address_proof"]').files.length;
                const profilePhoto = document.querySelector('input[name="parent_profile_photo"]').files.length;
                
                if(idProof === 0) {
                    alert('Please upload Government ID Proof');
                    e.preventDefault();
                    return false;
                }
                if(addressProof === 0) {
                    alert('Please upload Address Proof');
                    e.preventDefault();
                    return false;
                }
                if(profilePhoto === 0) {
                    alert('Please upload Profile Photo');
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>