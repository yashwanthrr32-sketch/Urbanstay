<?php
// Helper function to get correct path for links
function url($path = '') {
    static $base_url = null;
    
    if ($base_url === null) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $script_name = $_SERVER['SCRIPT_NAME'];
        
        // Get the base directory (urbanstay folder)
        $base_dir = dirname($script_name);
        if ($base_dir == '/' || $base_dir == '\\') {
            $base_dir = '';
        }
        
        $base_url = $protocol . $host . $base_dir;
    }
    
    return $base_url . '/' . ltrim($path, '/');
}

// Alternative: Get relative path from current location
function getRelativePath($target) {
    $current_path = dirname($_SERVER['PHP_SELF']);
    $current_depth = substr_count($current_path, '/');
    $target_depth = substr_count($target, '/');
    
    $back = str_repeat('../', $current_depth);
    
    return $back . $target;
}
?>