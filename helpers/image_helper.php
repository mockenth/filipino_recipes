<?php
// helpers/image_helper.php

function getRecipeImage($imagePath, $defaultImage = null) {
    // Set default image if not provided
    if ($defaultImage === null) {
        $defaultImage = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1480&q=80';
    }
    
    // If image path is empty, return default
    if (empty($imagePath) || $imagePath === 'null') {
        return $defaultImage;
    }
    
    // If it's already a full URL, return as is
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }
    
    // If it starts with http but not validated, still use it
    if (strpos($imagePath, 'http') === 0) {
        return $imagePath;
    }
    
    // Handle different path formats
    $cleanPath = ltrim($imagePath, '/');
    
    // Check if it's already a web-accessible path
    if (strpos($cleanPath, 'assets/') === 0 || strpos($cleanPath, 'uploads/') === 0) {
        return '/' . $cleanPath;
    }
    
    // Try to find the image in different locations
    $possiblePaths = [
        '../uploads/' . $cleanPath,
        '../assets/uploads/' . $cleanPath,
        'uploads/' . $cleanPath,
        'assets/uploads/' . $cleanPath,
        '../' . $cleanPath,
        './' . $cleanPath,
        $cleanPath
    ];
    
    foreach ($possiblePaths as $testPath) {
        if (file_exists($testPath)) {
            // Convert to web-accessible path
            if (strpos($testPath, 'uploads/') !== false && strpos($testPath, 'assets/') === false) {
                return '/uploads/' . basename($cleanPath);
            }
            if (strpos($testPath, 'assets/uploads/') !== false) {
                return '/assets/uploads/' . basename($cleanPath);
            }
            return '/' . $cleanPath;
        }
    }
    
    // If file doesn't exist but we have a path, try to construct web path
    if (!empty($imagePath)) {
        // If it's stored as just a filename in uploads
        if (strpos($imagePath, '/') === false) {
            // Check both possible locations
            if (file_exists('../uploads/' . $imagePath)) {
                return '/uploads/' . $imagePath;
            }
            if (file_exists('../assets/uploads/' . $imagePath)) {
                return '/assets/uploads/' . $imagePath;
            }
            return '/uploads/' . $imagePath; // Assume it's in uploads
        }
        
        return $imagePath;
    }
    
    // Fallback to default image
    return $defaultImage;
}

// Function to handle image upload
function uploadRecipeImage($fileInput, $targetDir = '../uploads/') {
    if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileInput['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return '';
    }
    
    // Validate file size
    if ($fileInput['size'] > $maxSize) {
        return '';
    }
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Generate unique filename
    $fileExtension = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
    $filename = 'recipe_' . uniqid() . '_' . time() . '.' . $fileExtension;
    $uploadPath = $targetDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($fileInput['tmp_name'], $uploadPath)) {
        // Return relative path for database storage
        if ($targetDir === '../assets/uploads/') {
            return 'assets/uploads/' . $filename;
        }
        return 'uploads/' . $filename;
    }
    
    return '';
}
?>