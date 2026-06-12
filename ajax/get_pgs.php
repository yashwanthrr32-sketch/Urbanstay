<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$price = isset($_GET['price']) ? trim($_GET['price']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

try {
    $sql = "SELECT p.*, 
            (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) as image,
            COALESCE(AVG(f.rating), 0) as rating
            FROM pgs p 
            LEFT JOIN feedback f ON p.id = f.pg_id 
            WHERE p.status = 'approved'";

    $params = [];

    if(!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.address LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if(!empty($type)) {
        $sql .= " AND p.type = ?";
        $params[] = $type;
    }

    if(!empty($price)) {
        if($price == '15000+') {
            $sql .= " AND p.price_per_month >= 15000";
        } else {
            $range = explode('-', $price);
            if(count($range) == 2) {
                $sql .= " AND p.price_per_month BETWEEN ? AND ?";
                $params[] = (int)$range[0];
                $params[] = (int)$range[1];
            }
        }
    }

    $sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data and fix image paths
    foreach($pgs as &$pg) {
        $pg['price_per_month'] = (int)$pg['price_per_month'];
        $pg['rating'] = round($pg['rating'], 1);
        
        // Fix image path - remove any double slashes or wrong paths
        if(!empty($pg['image']) && file_exists('../' . $pg['image'])) {
            $pg['image'] = $pg['image'];
        } else {
            $pg['image'] = null;
        }
    }
    
    echo json_encode($pgs);
    
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>