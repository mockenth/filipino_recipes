<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user's recipes with approval status
$user_id = $_SESSION['user_id'];
$recipes_query = "SELECT r.*, c.name as category_name 
                  FROM recipes r 
                  LEFT JOIN categories c ON r.category_id = c.id 
                  WHERE r.user_id = :user_id 
                  ORDER BY r.created_at DESC LIMIT 6";
$recipes_stmt = $db->prepare($recipes_query);
$recipes_stmt->bindParam(':user_id', $user_id);
$recipes_stmt->execute();

// Count user's recipes by status
$count_query = "SELECT 
    COUNT(*) as total_recipes,
    SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_recipes,
    SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_recipes,
    SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_recipes,
    SUM(views) as total_views,
    SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_recipes
    FROM recipes 
    WHERE user_id = :user_id";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':user_id', $user_id);
$count_stmt->execute();
$stats = $count_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Grandma's Kitchen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .approval-status-badge {
            position: absolute;
            top: 190px;
            right: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 2;
        }

        .approval-status-badge.pending { background: #fff3cd; color: #856404; }
        .approval-status-badge.approved { background: #d4edda; color: #155724; }
        .approval-status-badge.rejected { background: #f8d7da; color: #721c24; }
        
        .stat-icon.pending { background: #ffc107; }
        .stat-icon.approved { background: #28a745; }
        .stat-icon.featured { background: #e74c3c; }
        .stat-icon.views { background: #17a2b8; }
        
        .recipe-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        
        .btn-edit { background: #3498db; color: white; }
        .btn-delete { background: #e74c3c; color: white; }
        .btn-view { background: #2ecc71; color: white; }
        
        .btn-edit:hover { background: #2980b9; }
        .btn-delete:hover { background: #c0392b; }
        .btn-view:hover { background: #27ae60; }
        
        .admin-notes {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #666;
            border-left: 3px solid #dc3545;
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
            <p>User Dashboard</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="add_recipe.php"><i class="fas fa-plus-circle"></i> Add Recipe</a></li>
            <li><a href="my_recipes.php"><i class="fas fa-utensils"></i> My Recipes</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li class="logout-item">
                <a href="../auth/logout.php" class="logout-btn-sidebar">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            <p>Manage your recipes and track their approval status</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon recipes">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_recipes'] ?? 0; ?></h3>
                    <p>Total Recipes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved_recipes'] ?? 0; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending_recipes'] ?? 0; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon views">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_views'] ?? 0; ?></h3>
                    <p>Total Views</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon featured">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['featured_recipes'] ?? 0; ?></h3>
                    <p>Featured</p>
                </div>
            </div>
        </div>

        <!-- Approval Status Legend -->
        <div class="form-section" style="margin-bottom: 30px;">
            <h3><i class="fas fa-info-circle"></i> Recipe Status</h3>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="approval-status-badge pending" style="position: static;">Pending</span>
                    <span>Waiting for admin approval</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="approval-status-badge approved" style="position: static;">Approved</span>
                    <span>Published on website</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="approval-status-badge rejected" style="position: static;">Rejected</span>
                    <span>Needs changes</span>
                </div>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>My Recent Recipes</h2>
                <a href="add_recipe.php" class="btn">Add New Recipe</a>
            </div>
            
            <div class="recipes-grid">
                <?php while ($recipe = $recipes_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="recipe-card">
                        <div class="recipe-badge"><?php echo htmlspecialchars($recipe['category_name']); ?></div>
                        <div class="approval-status-badge <?php echo $recipe['approval_status']; ?>">
                            <?php echo ucfirst($recipe['approval_status']); ?>
                        </div>
                        <div class="recipe-img" style="background-image: url('<?php echo $recipe['image'] ? '../' . $recipe['image'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1480&q=80'; ?>')">
                        </div>
                        <div class="recipe-content">
                            <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                            <div class="recipe-meta">
                                <span>Status: <?php echo ucfirst($recipe['status']); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo $recipe['prep_time'] + $recipe['cook_time']; ?> mins</span>
                            </div>
                            <p class="recipe-description"><?php echo htmlspecialchars(substr($recipe['description'], 0, 100)); ?>...</p>
                            
                            <?php if ($recipe['approval_status'] == 'rejected' && !empty($recipe['admin_notes'])): ?>
                                <div class="admin-notes">
                                    <strong><i class="fas fa-exclamation-circle"></i> Admin Notes:</strong><br>
                                    <?php echo htmlspecialchars($recipe['admin_notes']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="recipe-actions">
                                <a href="../view_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-sm btn-view" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-sm btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="delete_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Are you sure?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                
                <?php if ($recipes_stmt->rowCount() == 0): ?>
                    <div class="no-recipes">
                        <i class="fas fa-utensils"></i>
                        <h3>No Recipes Yet</h3>
                        <p>Start sharing your favorite Filipino recipes with the community!</p>
                        <a href="add_recipe.php" class="btn">Add Your First Recipe</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($recipes_stmt->rowCount() > 0): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="my_recipes.php" class="btn btn-outline">View All My Recipes</a>
                </div>
            <?php endif; ?>
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