<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle approval actions
if (isset($_POST['action']) && isset($_POST['recipe_id'])) {
    $recipe_id = $_POST['recipe_id'];
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    $admin_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'approve':
            $query = "UPDATE recipes SET 
                        approval_status = 'approved', 
                        admin_notes = :notes, 
                        reviewed_at = NOW(), 
                        reviewed_by = :admin_id 
                      WHERE id = :id";
            $message = "Recipe approved successfully!";
            break;
            
        case 'reject':
            $query = "UPDATE recipes SET 
                        approval_status = 'rejected', 
                        admin_notes = :notes, 
                        reviewed_at = NOW(), 
                        reviewed_by = :admin_id 
                      WHERE id = :id";
            $message = "Recipe rejected!";
            break;
            
        case 'feature':
            $query = "UPDATE recipes SET is_featured = 1 WHERE id = :id";
            $message = "Recipe featured!";
            break;
            
        case 'unfeature':
            $query = "UPDATE recipes SET is_featured = 0 WHERE id = :id";
            $message = "Recipe unfeatured!";
            break;
    }
    
    $stmt = $db->prepare($query);
    if (isset($admin_notes)) {
        $stmt->bindParam(':notes', $admin_notes);
    }
    if (isset($admin_id)) {
        $stmt->bindParam(':admin_id', $admin_id);
    }
    $stmt->bindParam(':id', $recipe_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = "Error updating recipe.";
    }
    
    header("Location: approve_recipes.php");
    exit();
}

// Get pending recipes for approval
$pending_query = "SELECT r.*, u.name as author_name, c.name as category_name 
                  FROM recipes r 
                  LEFT JOIN users u ON r.user_id = u.id 
                  LEFT JOIN categories c ON r.category_id = c.id 
                  WHERE r.approval_status = 'pending' 
                  ORDER BY r.created_at DESC";
$pending_stmt = $db->prepare($pending_query);
$pending_stmt->execute();
// Fetch all pending recipes into an array so we can render both table and mobile card views
$pending_recipes = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all recipes for management
$all_recipes_query = "SELECT r.*, u.name as author_name, c.name as category_name 
                      FROM recipes r 
                      LEFT JOIN users u ON r.user_id = u.id 
                      LEFT JOIN categories c ON r.category_id = c.id 
                      ORDER BY r.created_at DESC 
                      LIMIT 50";
$all_recipes_stmt = $db->prepare($all_recipes_query);
$all_recipes_stmt->execute();
$all_recipes = $all_recipes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Recipes - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .approval-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-feature { background: #ffc107; color: #212529; }
        .btn-unfeature { background: #6c757d; color: white; }
        .btn-view { background: #17a2b8; color: white; }
        
        .btn-approve:hover { background: #218838; }
        .btn-reject:hover { background: #c82333; }
        .btn-feature:hover { background: #e0a800; }
        .btn-unfeature:hover { background: #5a6268; }
        .btn-view:hover { background: #138496; }
        
        .admin-notes-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        .recipe-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .approval-section {
            margin-bottom: 40px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-featured { background: #d1ecf1; color: #0c5460; }
        
        .recipe-meta {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
        
        /* Mobile Card Header */
        .mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            gap: 10px;
        }
        
        .mobile-card-header h4 {
            margin: 0;
            flex: 1;
            color: var(--secondary-color);
            font-size: 1.1rem;
            line-height: 1.3;
        }
        
        /* Mobile Meta Row Improvements */
        .mobile-meta-row {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin: 10px 0;
            font-size: 0.85rem;
            color: #666;
        }
        
        .meta-item {
            display: flex;
            gap: 5px;
        }
        
        .meta-item strong {
            min-width: 70px;
            color: var(--secondary-color);
        }
        
        /* Action Forms Mobile */
        .action-form {
            width: 100%;
        }
        
        .notes-container {
            margin-bottom: 8px;
        }
        
        /* All Recipes Mobile Cards */
        .mobile-all-recipes-list {
            display: none;
            flex-direction: column;
            gap: 15px;
        }
        
        .mobile-all-recipe-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 15px;
        }
        
        .all-recipe-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            gap: 10px;
        }
        
        .all-recipe-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            flex: 1;
        }
        
        .all-recipe-status {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .all-recipe-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .all-recipe-detail {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .all-recipe-detail-label {
            font-weight: 600;
            color: #555;
            min-width: 80px;
        }
        
        .all-recipe-detail-value {
            color: #333;
            text-align: right;
            flex: 1;
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .admin-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .admin-header h1 {
                font-size: 1.5rem;
                margin-bottom: 5px;
            }
            
            .approval-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .approval-actions form {
                width: 100%;
            }
            
            .approval-actions .btn {
                width: 100%;
                margin: 0;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .admin-notes-input {
                font-size: 16px;
                min-height: 44px;
            }
            
            .btn-sm {
                padding: 12px 16px;
                font-size: 0.9rem;
            }
            
            .card-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .card-header h3 {
                font-size: 1.2rem;
            }
            
            .mobile-pending-card {
                padding: 15px 12px;
                margin-bottom: 15px;
            }
            
            .mobile-pending-card h4 {
                font-size: 1.1rem;
                margin-bottom: 8px;
            }
            
            .recipe-preview {
                padding: 12px;
                font-size: 0.9rem;
            }
            
            .recipe-meta {
                font-size: 0.75rem;
            }
            
            /* Hide tables on mobile, show cards */
            .admin-table-container {
                display: none;
            }
            
            .mobile-pending-list {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .mobile-all-recipes-list {
                display: flex;
            }
            
            .mobile-all-recipe-card {
                padding: 15px;
            }
            
            .all-recipe-title {
                font-size: 1rem;
            }
            
            .all-recipe-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .all-recipe-status {
                width: 100%;
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 10px 8px;
            }
            
            .admin-header h1 {
                font-size: 1.3rem;
            }
            
            .card-content {
                padding: 15px;
            }
        }
        
        /* Desktop - hide mobile cards */
        @media (min-width: 769px) {
            .mobile-pending-list {
                display: none !important;
            }
            
            .mobile-all-recipes-list {
                display: none !important;
            }
            
            .admin-table-container {
                display: block;
            }
        }
        
        /* Touch-friendly improvements */
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
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        /* Prevent zoom on iOS */
        @media (max-width: 768px) {
            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="search"],
            textarea,
            select {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button (visible only on small screens) -->
    <button class="mobile-toggle-btn" id="mobileToggleBtn" aria-label="Toggle Navigation">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../assets/img/webpage logo2.1.png" alt="Grandma's Kitchen Logo" class="logo-img">
            <h1>Grandma's<span>Kitchen</span></h1>
            <p>Recipe Approval</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="approve_recipes.php" class="active"><i class="fas fa-check-circle"></i> Approve Recipes</a></li>
            <li><a href="manage_recipes.php"><i class="fas fa-utensils"></i> Manage Recipes</a></li>
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
            <h1>Recipe Approval System</h1>
            <p>Review and approve user-submitted recipes</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Pending Approval Section -->
        <div class="approval-section">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Pending Approval</h3>
                    <span class="badge"><?php echo count($pending_recipes); ?> recipes waiting</span>
                </div>
                <div class="card-content">
                    <?php if (count($pending_recipes) > 0): ?>
                        <div class="admin-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Recipe Details</th>
                                        <th>Author</th>
                                        <th>Category</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_recipes as $recipe): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($recipe['title']); ?></strong>
                                            <div class="recipe-meta">
                                                <i class="fas fa-clock"></i> <?php echo $recipe['prep_time'] + $recipe['cook_time']; ?> mins • 
                                                <i class="fas fa-utensils"></i> Serves <?php echo $recipe['servings']; ?> • 
                                                <i class="fas fa-fire"></i> <?php echo $recipe['difficulty']; ?>
                                            </div>
                                            <div class="recipe-preview">
                                                <small><strong>Description:</strong> <?php echo htmlspecialchars(substr($recipe['description'], 0, 150)); ?>...</small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($recipe['author_name']); ?></td>
                                        <td><?php echo htmlspecialchars($recipe['category_name']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($recipe['created_at'])); ?></td>
                                        <td>
                                            <div class="approval-actions">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-approve btn-sm">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="text" name="admin_notes" class="admin-notes-input" placeholder="Rejection reason (optional)">
                                                    <button type="submit" class="btn btn-reject btn-sm" style="margin-top: 5px;">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                                
                                                <a href="../view_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-view btn-sm" target="_blank">
                                                    <i class="fas fa-eye"></i> Preview
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile card view (hidden on desktop, visible on small screens) -->
                        <div class="mobile-pending-list">
                            <?php foreach ($pending_recipes as $recipe): ?>
                                <div class="mobile-pending-card">
                                    <div class="mobile-card-header">
                                        <h4><?php echo htmlspecialchars($recipe['title']); ?></h4>
                                        <span class="status-badge status-pending">Pending</span>
                                    </div>
                                    
                                    <div class="recipe-meta">
                                        <i class="fas fa-clock"></i> <?php echo $recipe['prep_time'] + $recipe['cook_time']; ?> mins • 
                                        <i class="fas fa-utensils"></i> Serves <?php echo $recipe['servings']; ?> • 
                                        <i class="fas fa-fire"></i> <?php echo $recipe['difficulty']; ?>
                                    </div>
                                    
                                    <div class="recipe-preview">
                                        <small><strong>Description:</strong> <?php echo htmlspecialchars(substr($recipe['description'], 0, 150)); ?>...</small>
                                    </div>
                                    
                                    <div class="mobile-meta-row">
                                        <div class="meta-item">
                                            <strong>Author:</strong> <?php echo htmlspecialchars($recipe['author_name']); ?>
                                        </div>
                                        <div class="meta-item">
                                            <strong>Category:</strong> <?php echo htmlspecialchars($recipe['category_name']); ?>
                                        </div>
                                        <div class="meta-item">
                                            <strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($recipe['created_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="approval-actions mobile-actions">
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-approve btn-sm">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>

                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <div class="notes-container">
                                                <input type="text" name="admin_notes" class="admin-notes-input" placeholder="Rejection reason (optional)">
                                            </div>
                                            <button type="submit" class="btn btn-reject btn-sm">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>

                                        <a href="../view_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-view btn-sm" target="_blank">
                                            <i class="fas fa-eye"></i> Preview
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-check-circle"></i>
                            <p>No recipes pending approval. Great job!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- All Recipes Management Section -->
        <div class="approval-section">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Recipes</h3>
                    <span class="badge"><?php echo count($all_recipes); ?> total recipes</span>
                </div>
                <div class="card-content">
                    <?php if (count($all_recipes) > 0): ?>
                        <div class="admin-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Recipe</th>
                                        <th>Author</th>
                                        <th>Status</th>
                                        <th>Featured</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_recipes as $recipe): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($recipe['title']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($recipe['category_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($recipe['author_name']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $recipe['approval_status']; ?>">
                                                <?php echo ucfirst($recipe['approval_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($recipe['is_featured']): ?>
                                                <span class="status-badge status-featured">Featured</span>
                                            <?php else: ?>
                                                <span class="badge">Normal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="approval-actions">
                                                <?php if ($recipe['approval_status'] == 'approved'): ?>
                                                    <?php if ($recipe['is_featured']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                                            <input type="hidden" name="action" value="unfeature">
                                                            <button type="submit" class="btn btn-unfeature btn-sm">
                                                                <i class="fas fa-star"></i> Unfeature
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                                            <input type="hidden" name="action" value="feature">
                                                            <button type="submit" class="btn btn-feature btn-sm">
                                                                <i class="fas fa-star"></i> Feature
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <a href="../view_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-view btn-sm" target="_blank">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                
                                                <?php if ($recipe['approval_status'] == 'pending'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-approve btn-sm">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile card view for All Recipes -->
                        <div class="mobile-all-recipes-list">
                            <?php foreach ($all_recipes as $recipe): ?>
                                <div class="mobile-all-recipe-card">
                                    <div class="all-recipe-card-header">
                                        <h3 class="all-recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                        <div class="all-recipe-status">
                                            <span class="status-badge status-<?php echo $recipe['approval_status']; ?>">
                                                <?php echo ucfirst($recipe['approval_status']); ?>
                                            </span>
                                            <?php if ($recipe['is_featured']): ?>
                                                <span class="status-badge status-featured">Featured</span>
                                            <?php else: ?>
                                                <span class="badge">Normal</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="all-recipe-details">
                                        <div class="all-recipe-detail">
                                            <span class="all-recipe-detail-label">Category:</span>
                                            <span class="all-recipe-detail-value"><?php echo htmlspecialchars($recipe['category_name']); ?></span>
                                        </div>
                                        <div class="all-recipe-detail">
                                            <span class="all-recipe-detail-label">Author:</span>
                                            <span class="all-recipe-detail-value"><?php echo htmlspecialchars($recipe['author_name']); ?></span>
                                        </div>
                                        <div class="all-recipe-detail">
                                            <span class="all-recipe-detail-label">Created:</span>
                                            <span class="all-recipe-detail-value"><?php echo date('M j, Y', strtotime($recipe['created_at'])); ?></span>
                                        </div>
                                        <div class="all-recipe-detail">
                                            <span class="all-recipe-detail-label">Time:</span>
                                            <span class="all-recipe-detail-value"><?php echo $recipe['prep_time'] + $recipe['cook_time']; ?> mins</span>
                                        </div>
                                        <div class="all-recipe-detail">
                                            <span class="all-recipe-detail-label">Servings:</span>
                                            <span class="all-recipe-detail-value"><?php echo $recipe['servings']; ?> people</span>
                                        </div>
                                    </div>
                                    
                                    <div class="approval-actions mobile-actions">
                                        <?php if ($recipe['approval_status'] == 'approved'): ?>
                                            <?php if ($recipe['is_featured']): ?>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                                    <input type="hidden" name="action" value="unfeature">
                                                    <button type="submit" class="btn btn-unfeature btn-sm">
                                                        <i class="fas fa-star"></i> Remove Feature
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                                    <input type="hidden" name="action" value="feature">
                                                    <button type="submit" class="btn btn-feature btn-sm">
                                                        <i class="fas fa-star"></i> Feature Recipe
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <a href="../view_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-view btn-sm" target="_blank">
                                            <i class="fas fa-eye"></i> View Recipe
                                        </a>
                                        
                                        <?php if ($recipe['approval_status'] == 'pending'): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-approve btn-sm">
                                                    <i class="fas fa-check"></i> Approve Recipe
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-utensils"></i>
                            <p>No recipes found in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/main.js"></script>
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