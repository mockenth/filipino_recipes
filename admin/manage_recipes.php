<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Initialize search variables
$search_term = '';
$where_conditions = [];
$query_params = [];

// Handle search
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $where_conditions[] = "(r.title LIKE :search OR r.description LIKE :search OR u.name LIKE :search OR c.name LIKE :search)";
    $query_params[':search'] = '%' . $search_term . '%';
}

// Handle status filter
if (isset($_GET['status']) && !empty($_GET['status']) && in_array($_GET['status'], ['published', 'draft'])) {
    $where_conditions[] = "r.status = :status";
    $query_params[':status'] = $_GET['status'];
}

// Handle featured filter
if (isset($_GET['featured']) && !empty($_GET['featured']) && in_array($_GET['featured'], ['featured', 'normal'])) {
    if ($_GET['featured'] == 'featured') {
        $where_conditions[] = "r.is_featured = 1";
    } else {
        $where_conditions[] = "r.is_featured = 0";
    }
}

// Build the WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get all recipes with search/filter
$recipes_query = "SELECT r.*, c.name as category_name, u.name as author_name 
                  FROM recipes r 
                  LEFT JOIN categories c ON r.category_id = c.id 
                  LEFT JOIN users u ON r.user_id = u.id 
                  $where_clause
                  ORDER BY r.created_at DESC";
$recipes_stmt = $db->prepare($recipes_query);

// Bind parameters if they exist
foreach ($query_params as $param => $value) {
    $recipes_stmt->bindValue($param, $value); // FIXED: Changed from $users_stmt to $recipes_stmt
}

$recipes_stmt->execute();
$recipes = $recipes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recipe counts for filters
$total_recipes = $db->query("SELECT COUNT(*) FROM recipes")->fetchColumn();
$published_count = $db->query("SELECT COUNT(*) FROM recipes WHERE status = 'published'")->fetchColumn();
$draft_count = $db->query("SELECT COUNT(*) FROM recipes WHERE status = 'draft'")->fetchColumn();
$featured_count = $db->query("SELECT COUNT(*) FROM recipes WHERE is_featured = 1")->fetchColumn();

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $recipe_id = $_GET['id'];
    
    // Build redirect URL to preserve filters
    $redirect_params = [];
    if (!empty($search_term)) $redirect_params[] = 'search=' . urlencode($search_term);
    if (isset($_GET['status']) && !empty($_GET['status'])) $redirect_params[] = 'status=' . urlencode($_GET['status']);
    if (isset($_GET['featured']) && !empty($_GET['featured'])) $redirect_params[] = 'featured=' . urlencode($_GET['featured']);
    
    $redirect_url = "manage_recipes.php";
    if (!empty($redirect_params)) {
        $redirect_url .= "?" . implode("&", $redirect_params);
    }

    switch ($action) {
        case 'delete':
            $delete_query = "DELETE FROM recipes WHERE id = :id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':id', $recipe_id);
            if ($delete_stmt->execute()) {
                $_SESSION['success'] = "Recipe deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting recipe.";
            }
            header("Location: " . $redirect_url);
            exit();
            break;
            
        case 'feature':
            $feature_query = "UPDATE recipes SET is_featured = 1 WHERE id = :id";
            $feature_stmt = $db->prepare($feature_query);
            $feature_stmt->bindParam(':id', $recipe_id);
            if ($feature_stmt->execute()) {
                $_SESSION['success'] = "Recipe featured successfully!";
            } else {
                $_SESSION['error'] = "Error featuring recipe.";
            }
            header("Location: " . $redirect_url);
            exit();
            break;
            
        case 'unfeature':
            $unfeature_query = "UPDATE recipes SET is_featured = 0 WHERE id = :id";
            $unfeature_stmt = $db->prepare($unfeature_query);
            $unfeature_stmt->bindParam(':id', $recipe_id);
            if ($unfeature_stmt->execute()) {
                $_SESSION['success'] = "Recipe unfeatured successfully!";
            } else {
                $_SESSION['error'] = "Error unfeaturing recipe.";
            }
            header("Location: " . $redirect_url);
            exit();
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Recipes - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-view { background: #17a2b8; color: white; }
        .btn-edit { background: #007bff; color: white; }
        .btn-info { background: #6c757d; color: white; }
        
        .btn-success:hover { background: #218838; }
        .btn-warning:hover { background: #e0a800; }
        .btn-delete:hover { background: #c82333; }
        .btn-view:hover { background: #138496; }
        .btn-edit:hover { background: #0056b3; }
        .btn-info:hover { background: #5a6268; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-badge.published { background: #d4edda; color: #155724; }
        .status-badge.draft { background: #fff3cd; color: #856404; }
        .status-badge.featured { background: #d1ecf1; color: #0c5460; }
        
        .badge {
            background: #6c757d;
            color: white;
            padding: 3px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
        }
        
        /* Search and Filter Styles */
        .search-filter-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #333333 !important;
            font-weight: 500;
        }
        
        .search-input::placeholder {
            color: #6c757d;
            font-weight: normal;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            background: #ffffff;
            color: #333333 !important;
        }
        
        .search-input:-webkit-autofill,
        .search-input:-webkit-autofill:hover, 
        .search-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #333333 !important;
            -webkit-box-shadow: 0 0 0px 1000px white inset !important;
            background-color: white !important;
            color: #333333 !important;
        }
        
        .search-btn {
            padding: 12px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-weight: 600;
        }
        
        .search-btn:hover {
            background: #0056b3;
        }
        
        .filter-options {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-label {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            color: #333333;
        }
        
        .reset-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }
        
        .reset-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        .search-results-info {
            background: #e7f3ff;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        
        .search-results-info p {
            margin: 0;
            color: #0056b3;
            font-weight: 500;
        }
        
        /* Mobile Card Layout */
        .mobile-recipes-list {
            display: none;
            flex-direction: column;
            gap: 15px;
        }
        
        .mobile-recipe-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        .recipe-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            gap: 10px;
        }
        
        .recipe-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            flex: 1;
        }
        
        .recipe-status {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .recipe-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .recipe-detail {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .recipe-detail-label {
            font-weight: 600;
            color: #555;
            min-width: 80px;
        }
        
        .recipe-detail-value {
            color: #333;
            text-align: right;
            flex: 1;
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .main-content { padding: 15px 10px; }
            .admin-header { flex-direction: column; gap: 15px; text-align: center; }
            .admin-header h1 { font-size: 1.5rem; margin-bottom: 5px; }
            .search-box { flex-direction: column; }
            .search-input { font-size: 16px; min-height: 50px; }
            .search-btn { min-height: 50px; font-size: 1rem; }
            .filter-options { flex-direction: column; align-items: flex-start; gap: 10px; }
            .filter-group { width: 100%; justify-content: space-between; }
            .filter-select { flex: 1; max-width: 200px; min-height: 40px; }
            .admin-table-container { display: none; }
            .mobile-recipes-list { display: flex; }
            .action-buttons { flex-direction: column; gap: 8px; }
            .action-buttons .btn {
                width: 100%; min-height: 44px;
                display: flex; align-items: center; justify-content: center; margin: 0;
            }
            .btn-sm { padding: 12px 16px; font-size: 0.9rem; }
            .status-badge { font-size: 0.7rem; padding: 3px 6px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px 8px; }
            .admin-header h1 { font-size: 1.3rem; }
            .mobile-recipe-card { padding: 15px; }
            .recipe-title { font-size: 1rem; }
            .recipe-card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .recipe-status { width: 100%; justify-content: flex-start; }
            .search-filter-container { padding: 15px; }
        }
        
        @media (min-width: 769px) {
            .mobile-recipes-list { display: none !important; }
            .admin-table-container { display: block; }
        }
        
        .btn {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-sm { padding: 8px 12px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <button class="mobile-toggle-btn" id="mobileToggleBtn" aria-label="Toggle Navigation">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../assets/img/webpage logo2.1.png" alt="Grandma's Kitchen Logo" class="logo-img">
            <h1>Grandma's<span>Kitchen</span></h1>
            <p>Manage Recipes</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="approve_recipes.php"><i class="fas fa-check-circle"></i> Approve Recipes</a></li>
            <li><a href="manage_recipes.php" class="active"><i class="fas fa-utensils"></i> Manage Recipes</a></li>
            <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li class="logout-item">
                <a href="../auth/logout.php" class="logout-btn-sidebar">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="admin-header">
            <h1>Manage Recipes</h1>
            <p>Manage all recipes in the system</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Search and Filter Section (Now properly wrapped in one form) -->
        <div class="search-filter-container">
            <form method="GET" action="">
                <div class="search-box">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search recipes by title, description, author, or category..." 
                           value="<?php echo htmlspecialchars($search_term); ?>"
                           aria-label="Search recipes">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                
                <div class="filter-options">
                    <div class="filter-group">
                        <span class="filter-label">Status:</span>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="published" <?php echo (isset($_GET['status']) && $_GET['status'] == 'published') ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo (isset($_GET['status']) && $_GET['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <span class="filter-label">Featured:</span>
                        <select name="featured" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Recipes</option>
                            <option value="featured" <?php echo (isset($_GET['featured']) && $_GET['featured'] == 'featured') ? 'selected' : ''; ?>>Featured Only</option>
                            <option value="normal" <?php echo (isset($_GET['featured']) && $_GET['featured'] == 'normal') ? 'selected' : ''; ?>>Normal Only</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <span class="filter-label">Quick Stats:</span>
                        <span class="badge" style="background: #17a2b8;">Total: <?php echo $total_recipes; ?></span>
                        <span class="badge" style="background: #28a745;">Published: <?php echo $published_count; ?></span>
                        <span class="badge" style="background: #ffc107;">Drafts: <?php echo $draft_count; ?></span>
                        <span class="badge" style="background: #e83e8c;">Featured: <?php echo $featured_count; ?></span>
                    </div>
                    
                    <?php if (!empty($search_term) || isset($_GET['status']) || isset($_GET['featured'])): ?>
                        <a href="manage_recipes.php" class="reset-btn">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!empty($search_term) || isset($_GET['status']) || isset($_GET['featured'])): ?>
            <div class="search-results-info">
                <p>
                    <i class="fas fa-info-circle"></i>
                    Found <?php echo count($recipes); ?> recipe(s)
                    <?php if (!empty($search_term)): ?>
                        matching "<?php echo htmlspecialchars($search_term); ?>"
                    <?php endif; ?>
                    <?php if (isset($_GET['status']) && !empty($_GET['status'])): ?>
                        with status "<?php echo htmlspecialchars($_GET['status']); ?>"
                    <?php endif; ?>
                    <?php if (isset($_GET['featured']) && !empty($_GET['featured'])): ?>
                        that are "<?php echo htmlspecialchars($_GET['featured']); ?>"
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Desktop Table -->
        <div class="admin-table-container">
            <?php if (count($recipes) > 0): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipes as $recipe): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($recipe['title']); ?></td>
                            <td><?php echo htmlspecialchars($recipe['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($recipe['author_name']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $recipe['status']; ?>">
                                    <?php echo ucfirst($recipe['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($recipe['is_featured']): ?>
                                    <span class="status-badge featured">Featured</span>
                                <?php else: ?>
                                    <span class="badge">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($recipe['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="../view_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-view btn-sm" target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../user/edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-edit btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($recipe['is_featured']): ?>
                                        <a href="?action=unfeature&id=<?php echo $recipe['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['featured']) ? '&featured=' . urlencode($_GET['featured']) : ''; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-star"></i> Unfeature
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=feature&id=<?php echo $recipe['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['featured']) ? '&featured=' . urlencode($_GET['featured']) : ''; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-star"></i> Feature
                                        </a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?php echo $recipe['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['featured']) ? '&featured=' . urlencode($_GET['featured']) : ''; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Are you sure you want to delete this recipe?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-utensils fa-3x"></i>
                    <h3>No Recipes Found</h3>
                    <p>
                        <?php if (!empty($search_term) || isset($_GET['status']) || isset($_GET['featured'])): ?>
                            No recipes match your search criteria. Try adjusting your search terms or <a href="manage_recipes.php">clear filters</a>.
                        <?php else: ?>
                            There are no recipes in the system yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mobile Cards -->
        <div class="mobile-recipes-list">
            <?php if (count($recipes) > 0): ?>
                <?php foreach ($recipes as $recipe): ?>
                    <div class="mobile-recipe-card">
                        <div class="recipe-card-header">
                            <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                            <div class="recipe-status">
                                <span class="status-badge <?php echo $recipe['status']; ?>">
                                    <?php echo ucfirst($recipe['status']); ?>
                                </span>
                                <?php if ($recipe['is_featured']): ?>
                                    <span class="status-badge featured">Featured</span>
                                <?php else: ?>
                                    <span class="badge">Normal</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="recipe-details">
                            <div class="recipe-detail">
                                <span class="recipe-detail-label">Category:</span>
                                <span class="recipe-detail-value"><?php echo htmlspecialchars($recipe['category_name']); ?></span>
                            </div>
                            <div class="recipe-detail">
                                <span class="recipe-detail-label">Author:</span>
                                <span class="recipe-detail-value"><?php echo htmlspecialchars($recipe['author_name']); ?></span>
                            </div>
                            <div class="recipe-detail">
                                <span class="recipe-detail-label">Created:</span>
                                <span class="recipe-detail-value"><?php echo date('M j, Y', strtotime($recipe['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="../view_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-view btn-sm" target="_blank">
                                <i class="fas fa-eye"></i> View Recipe
                            </a>
                            <a href="../user/edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-edit btn-sm">
                                <i class="fas fa-edit"></i> Edit Recipe
                            </a>
                            <?php if ($recipe['is_featured']): ?>
                                <a href="?action=unfeature&id=<?php echo $recipe['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['featured']) ? '&featured=' . urlencode($_GET['featured']) : ''; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-star"></i> Remove Feature
                                </a>
                            <?php else: ?>
                                <a href="?action=feature&id=<?php echo $recipe['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['featured']) ? '&featured=' . urlencode($_GET['featured']) : ''; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-star"></i> Feature Recipe
                                </a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?php echo $recipe['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['featured']) ? '&featured=' . urlencode($_GET['featured']) : ''; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Are you sure you want to delete this recipe?')">
                                <i class="fas fa-trash"></i> Delete Recipe
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-utensils fa-3x"></i>
                    <h3>No Recipes Found</h3>
                    <p>
                        <?php if (!empty($search_term) || isset($_GET['status']) || isset($_GET['featured'])): ?>
                            No recipes match your search criteria. Try adjusting your search terms or <a href="manage_recipes.php">clear filters</a>.
                        <?php else: ?>
                            There are no recipes in the system yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/main.js"></script>
    <script>
        // Focus search input on page load if there's a search term
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.focus();
                // Move cursor to the end of the text
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }
        });
    </script>
    <script>
        // Mobile sidebar toggle functionality
        const mobileToggleBtn = document.getElementById('mobileToggleBtn');
        const sidebar = document.getElementById('sidebar');

        if (mobileToggleBtn && sidebar) {
            mobileToggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-open');
                mobileToggleBtn.classList.toggle('active');
            });

            // Close sidebar when a link is clicked
            const navLinks = sidebar.querySelectorAll('a');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    mobileToggleBtn.classList.remove('active');
                });
            });
        }
    </script>
</body>
</html>