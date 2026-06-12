<?php
require_once 'config/db.php';

$pg_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch PG details
$stmt = $pdo->prepare("SELECT * FROM pgs WHERE id = ? AND status = 'approved'");
$stmt->execute([$pg_id]);
$pg = $stmt->fetch();

if (!$pg) {
    header('Location: index.php');
    exit;
}

// Fetch ALL PG images
$stmt = $pdo->prepare("SELECT image_path FROM pg_images WHERE pg_id = ?");
$stmt->execute([$pg_id]);
$images = $stmt->fetchAll();

// Fetch rooms and beds
$stmt = $pdo->prepare("
    SELECT r.*, b.id as bed_id, b.bed_label, b.status as bed_status 
    FROM rooms r 
    JOIN beds b ON r.id = b.room_id 
    WHERE r.pg_id = ? 
    ORDER BY r.room_number, b.bed_label
");
$stmt->execute([$pg_id]);
$beds = $stmt->fetchAll();

// Group beds by room
$rooms = [];
foreach ($beds as $bed) {
    if (!isset($rooms[$bed['room_number']])) {
        $rooms[$bed['room_number']] = [];
    }
    $rooms[$bed['room_number']][] = $bed;
}

// Fetch reviews
$stmt = $pdo->prepare("
    SELECT f.*, u.name 
    FROM feedback f 
    JOIN users u ON f.tenant_id = u.id 
    WHERE f.pg_id = ? 
    ORDER BY f.created_at DESC
");
$stmt->execute([$pg_id]);
$reviews = $stmt->fetchAll();

$amenities = explode(',', $pg['amenities']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pg['name']); ?> - Urban Stay</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .gallery-container {
            position: relative;
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .gallery-main {
            position: relative;
            height: 400px;
            background: #333;
        }
        .gallery-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 12px 18px;
            cursor: pointer;
            font-size: 20px;
            border-radius: 50%;
            transition: background 0.3s;
            z-index: 10;
        }
        .gallery-nav:hover {
            background: rgba(0,0,0,0.8);
        }
        .gallery-prev { left: 15px; }
        .gallery-next { right: 15px; }
        .gallery-thumbs {
            display: flex;
            gap: 10px;
            padding: 15px;
            overflow-x: auto;
            background: white;
        }
        .gallery-thumbs img {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .gallery-thumbs img.active {
            border-color: #185FA5;
            transform: scale(1.05);
        }
        .image-counter {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 10;
        }
        .pg-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .pg-info h1 { color: #185FA5; margin-bottom: 1rem; }
        .address { color: #666; margin-bottom: 0.5rem; }
        .contact-info {
            background: #e8f4fd;
            padding: 12px 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .contact-info p { margin: 5px 0; }
        .contact-info a { color: #185FA5; text-decoration: none; }
        .contact-info a:hover { text-decoration: underline; }
        .price { font-size: 1.5rem; font-weight: bold; color: #185FA5; margin: 10px 0; }
        .type {
            display: inline-block;
            background: #e0e7ff;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .amenities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .amenity-tag {
            background: #e0e7ff;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        .bed {
            cursor: pointer;
            padding: 10px;
            margin: 5px;
            border-radius: 5px;
            text-align: center;
            display: inline-block;
            min-width: 60px;
        }
        .bed.available { background-color: #28a745; color: white; }
        .bed.occupied { background-color: #dc3545; color: white; cursor: not-allowed; }
        .bed.selected { background-color: #007bff; box-shadow: 0 0 0 2px white, 0 0 0 4px #007bff; }
        .beds-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .room-card {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background-color: #185FA5;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn-primary:hover { background-color: #0d3b66; }
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
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close {
            position: absolute;
            right: 1rem;
            top: 0.5rem;
            font-size: 2rem;
            cursor: pointer;
            color: #999;
        }
        .close:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .review-card {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .login-to-book {
            text-align: center;
            padding: 1rem;
            background: #f0f0f0;
            border-radius: 10px;
            margin-top: 1rem;
        }
        .warning-note {
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #856404;
        }
        @media (max-width: 768px) {
            .pg-detail { grid-template-columns: 1fr; }
            .gallery-main { height: 250px; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container">
        <div class="pg-detail">
            <div class="gallery-container">
                <?php if(count($images) > 0): ?>
                    <div class="gallery-main">
                        <img src="<?php echo $images[0]['image_path']; ?>" alt="PG Image" id="mainImage">
                        <button class="gallery-nav gallery-prev" onclick="changeImage(-1)">❮</button>
                        <button class="gallery-nav gallery-next" onclick="changeImage(1)">❯</button>
                        <div class="image-counter">
                            <span id="currentImageIndex">1</span> / <span id="totalImages"><?php echo count($images); ?></span>
                        </div>
                    </div>
                    <div class="gallery-thumbs" id="thumbnails">
                        <?php foreach($images as $index => $image): ?>
                            <img src="<?php echo $image['image_path']; ?>" 
                                 onclick="setCurrentImage(<?php echo $index; ?>)"
                                 class="<?php echo $index == 0 ? 'active' : ''; ?>">
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="gallery-main">
                        <img src="assets/images/default-pg.jpg" alt="No Image Available" id="mainImage">
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="pg-info">
                <h1><?php echo htmlspecialchars($pg['name']); ?></h1>
                <p class="address">📍 <?php echo nl2br(htmlspecialchars($pg['address'])); ?></p>
                
                <div class="contact-info">
                    <?php if(!empty($pg['contact_phone'])): ?>
                        <p>📞 <strong>Contact:</strong> <a href="tel:<?php echo htmlspecialchars($pg['contact_phone']); ?>"><?php echo htmlspecialchars($pg['contact_phone']); ?></a></p>
                    <?php endif; ?>
                    <?php if(!empty($pg['contact_email'])): ?>
                        <p>✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($pg['contact_email']); ?>"><?php echo htmlspecialchars($pg['contact_email']); ?></a></p>
                    <?php endif; ?>
                </div>
                
                <p class="price">💰 ₹<?php echo number_format($pg['price_per_month']); ?> <span style="font-size: 14px;">/ month</span></p>
                <p class="type">👥 <?php echo $pg['type']; ?></p>
                
                <div class="amenities">
                    <h3>Amenities</h3>
                    <div class="amenities-list">
                        <?php foreach($amenities as $amenity): ?>
                            <?php if(trim($amenity)): ?>
                                <span class="amenity-tag">✓ <?php echo htmlspecialchars(trim($amenity)); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="room-layout-section">
            <h2>Room Layout & Availability</h2>
            <div class="rooms-container">
                <?php foreach($rooms as $roomNum => $roomBeds): ?>
                    <div class="room-card">
                        <h3>Room <?php echo htmlspecialchars($roomNum); ?></h3>
                        <div class="beds-grid">
                            <?php foreach($roomBeds as $bed): ?>
                                <div class="bed <?php echo $bed['bed_status']; ?>" 
                                     data-bed-id="<?php echo $bed['bed_id']; ?>"
                                     data-bed-label="<?php echo $bed['bed_label']; ?>"
                                     onclick="selectBed(<?php echo $bed['bed_id']; ?>, '<?php echo $bed['bed_status']; ?>', '<?php echo $bed['bed_label']; ?>')">
                                    Bed <?php echo htmlspecialchars($bed['bed_label']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div id="bookingControls" style="text-align: center; margin-top: 20px;">
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <div class="login-to-book">
                        <p>Please <a href="auth/login.php">login</a> to book a bed</p>
                    </div>
                <?php elseif($_SESSION['role'] == 'tenant'): ?>
                    <button id="bookBtn" class="btn-primary" style="display:none;" onclick="showBookingForm()">Book Selected Bed</button>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Only tenants can book PGs. Please register as a tenant.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="reviews-section">
            <h2>Reviews & Ratings</h2>
            <?php if(count($reviews) > 0): ?>
                <?php foreach($reviews as $review): ?>
                    <div class="review-card">
                        <strong><?php echo htmlspecialchars($review['name']); ?></strong>
                        <div>⭐ <?php echo $review['rating']; ?>/5</div>
                        <p><?php echo htmlspecialchars($review['review']); ?></p>
                        <small><?php echo date('d M Y', strtotime($review['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No reviews yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Booking Form Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Complete Your Booking</h2>
            <form id="bookingForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="bed_id" id="selectedBedId">
                <input type="hidden" name="pg_id" value="<?php echo $pg_id; ?>">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" required>
                </div>
                <div class="form-group">
                    <label>Current Address *</label>
                    <textarea name="address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Profile Photo *</label>
                    <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/jpg" required>
                    <small>Upload a recent passport size photo (JPG, PNG) - Max 2MB</small>
                </div>
                <div class="form-group">
                    <label>Government ID Proof (Aadhar/PAN/Passport) *</label>
                    <input type="file" name="id_proof_document" accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                    <small>Upload clear image/scan of your government ID proof (JPG, PNG, PDF) - Max 5MB</small>
                </div>
                <div class="form-group">
                    <label>Emergency Contact Name *</label>
                    <input type="text" name="emergency_name" required>
                </div>
                <div class="form-group">
                    <label>Emergency Contact Phone *</label>
                    <input type="tel" name="emergency_phone" required>
                </div>
                
                <div class="warning-note">
                    ⚠️ Note: Your ID proof will be verified by the PG manager before your booking is confirmed.
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%;">Submit Booking Request</button>
            </form>
        </div>
    </div>
    
    <script>
        // Gallery Slider Variables
        let currentIndex = 0;
        let totalImages = <?php echo count($images); ?>;
        let images = <?php echo json_encode(array_column($images, 'image_path')); ?>;
        
        function changeImage(direction) {
            if(totalImages === 0) return;
            currentIndex += direction;
            if(currentIndex < 0) currentIndex = totalImages - 1;
            if(currentIndex >= totalImages) currentIndex = 0;
            setCurrentImage(currentIndex);
        }
        
        function setCurrentImage(index) {
            if(totalImages === 0) return;
            currentIndex = index;
            document.getElementById('mainImage').src = images[currentIndex];
            document.getElementById('currentImageIndex').innerText = currentIndex + 1;
            document.querySelectorAll('.gallery-thumbs img').forEach((thumb, i) => {
                if(i == currentIndex) {
                    thumb.classList.add('active');
                } else {
                    thumb.classList.remove('active');
                }
            });
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if(e.key === 'ArrowLeft') {
                changeImage(-1);
            } else if(e.key === 'ArrowRight') {
                changeImage(1);
            }
        });
        
        let selectedBed = null;
        let selectedBedLabel = null;
        
        function selectBed(bedId, status, bedLabel) {
            if(status !== 'available') {
                alert('This bed is not available for booking.');
                return;
            }
            
            document.querySelectorAll('.bed').forEach(bed => {
                bed.classList.remove('selected');
            });
            
            const bedElement = document.querySelector(`.bed[data-bed-id="${bedId}"]`);
            if(bedElement) {
                bedElement.classList.add('selected');
            }
            selectedBed = bedId;
            selectedBedLabel = bedLabel;
            
            <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'tenant'): ?>
                const bookBtn = document.getElementById('bookBtn');
                if(bookBtn) {
                    bookBtn.style.display = 'block';
                    bookBtn.innerHTML = 'Book Bed ' + bedLabel;
                }
            <?php endif; ?>
        }
        
        function showBookingForm() {
            if(selectedBed) {
                document.getElementById('selectedBedId').value = selectedBed;
                document.getElementById('bookingModal').style.display = 'block';
            } else {
                alert('Please select a bed first.');
            }
        }
        
        function closeModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if(event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Handle form submission with AJAX - NO REDIRECT
        const bookingForm = document.getElementById('bookingForm');
        if(bookingForm) {
            bookingForm.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Validate file inputs
                const fileInput = document.querySelector('input[name="profile_photo"]');
                if(fileInput && fileInput.files.length === 0) {
                    alert('Please upload a profile photo');
                    return false;
                }
                
                const idProofInput = document.querySelector('input[name="id_proof_document"]');
                if(idProofInput && idProofInput.files.length === 0) {
                    alert('Please upload your Government ID Proof');
                    return false;
                }
                
                // Validate all required fields
                const requiredFields = bookingForm.querySelectorAll('[required]');
                for(let field of requiredFields) {
                    if(!field.value.trim()) {
                        alert('Please fill in all required fields');
                        field.focus();
                        return false;
                    }
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.textContent : 'Submit';
                if(submitBtn) {
                    submitBtn.textContent = '⏳ Submitting...';
                    submitBtn.disabled = true;
                }
                
                // Create FormData object
                const formData = new FormData(this);
                
                // Send AJAX request
                fetch('ajax/book_bed.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert(data.message);
                        // Close the modal
                        closeModal();
                        // Reset the form
                        bookingForm.reset();
                        // Change the selected bed to occupied (red)
                        if(selectedBed) {
                            const bedElement = document.querySelector(`.bed[data-bed-id="${selectedBed}"]`);
                            if(bedElement) {
                                bedElement.classList.remove('available', 'selected');
                                bedElement.classList.add('occupied');
                                bedElement.setAttribute('onclick', '');
                                bedElement.style.cursor = 'not-allowed';
                            }
                        }
                        // Hide the book button
                        const bookBtn = document.getElementById('bookBtn');
                        if(bookBtn) {
                            bookBtn.style.display = 'none';
                        }
                        // Reset selected bed variable
                        selectedBed = null;
                        selectedBedLabel = null;
                    } else {
                        alert('Error: ' + data.message);
                    }
                    // Reset submit button
                    if(submitBtn) {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred: ' + error.message);
                    if(submitBtn) {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                });
                
                return false;
            });
        }
    </script>
</body>
</html>