<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get stats with approval status
$users_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$recipes_count = $db->query("SELECT COUNT(*) FROM recipes")->fetchColumn();
$categories_count = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$published_recipes = $db->query("SELECT COUNT(*) FROM recipes WHERE status = 'published'")->fetchColumn();
$pending_approvals = $db->query("SELECT COUNT(*) FROM recipes WHERE approval_status = 'pending'")->fetchColumn();
$approved_recipes = $db->query("SELECT COUNT(*) FROM recipes WHERE approval_status = 'approved'")->fetchColumn();
$featured_recipes = $db->query("SELECT COUNT(*) FROM recipes WHERE is_featured = 1 AND approval_status = 'approved'")->fetchColumn();

// Get recent users
$recent_users_query = "SELECT name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users_stmt = $db->prepare($recent_users_query);
$recent_users_stmt->execute();

// Get recent recipes with approval status
$recent_recipes_query = "SELECT r.title, r.created_at, r.approval_status, u.name as author 
                         FROM recipes r 
                         LEFT JOIN users u ON r.user_id = u.id 
                         ORDER BY r.created_at DESC LIMIT 5";
$recent_recipes_stmt = $db->prepare($recent_recipes_query);
$recent_recipes_stmt->execute();

// Get pending recipes for quick access
$pending_recipes_query = "SELECT r.title, r.created_at, u.name as author 
                          FROM recipes r 
                          LEFT JOIN users u ON r.user_id = u.id 
                          WHERE r.approval_status = 'pending' 
                          ORDER BY r.created_at DESC LIMIT 5";
$pending_recipes_stmt = $db->prepare($pending_recipes_query);
$pending_recipes_stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Grandma's Kitchen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-action-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-color);
        }
        
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .quick-action-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .action-pending { color: #ffc107; }
        .action-approve { color: #28a745; }
        .action-manage { color: #007bff; }
        .action-users { color: #6f42c1; }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        .stat-icon.pending { background: #ffc107; }
        .stat-icon.approved { background: #28a745; }
        .stat-icon.featured { background: #e74c3c; }
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
            <p>Admin Dashboard</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="approve_recipes.php"><i class="fas fa-check-circle"></i> Approve Recipes</a></li>
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
            <h1>Admin Dashboard</h1>
            <div class="admin-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                <div class="admin-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="approve_recipes.php" class="quick-action-card">
                <i class="fas fa-clock action-pending"></i>
                <h3>Pending Approval</h3>
                <p><?php echo $pending_approvals; ?> recipes waiting</p>
            </a>
            
            <a href="manage_recipes.php" class="quick-action-card">
                <i class="fas fa-utensils action-manage"></i>
                <h3>Manage Recipes</h3>
                <p><?php echo $recipes_count; ?> total recipes</p>
            </a>
            
            <a href="manage_users.php" class="quick-action-card">
                <i class="fas fa-users action-users"></i>
                <h3>Manage Users</h3>
                <p><?php echo $users_count; ?> registered users</p>
            </a>
            
            <a href="manage_categories.php" class="quick-action-card">
                <i class="fas fa-tags action-approve"></i>
                <h3>Categories</h3>
                <p><?php echo $categories_count; ?> categories</p>
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $users_count; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon recipes">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $recipes_count; ?></h3>
                    <p>Total Recipes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $pending_approvals; ?></h3>
                    <p>Pending Approval</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $approved_recipes; ?></h3>
                    <p>Approved Recipes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon featured">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $featured_recipes; ?></h3>
                    <p>Featured Recipes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon categories">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $categories_count; ?></h3>
                    <p>Categories</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="dashboard-grid">
            <!-- Pending Recipes -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Pending Approval</h3>
                    <a href="approve_recipes.php" class="view-all">Review All</a>
                </div>
                <div class="card-content">
                    <div class="recent-list">
                        <?php if ($pending_recipes_stmt->rowCount() > 0): ?>
                            <?php while ($recipe = $pending_recipes_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <div class="recent-item">
                                    <div class="recent-avatar" style="background: #ffc107;">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="recent-info">
                                        <h4><?php echo htmlspecialchars($recipe['title']); ?></h4>
                                        <p>By <?php echo htmlspecialchars($recipe['author']); ?></p>
                                    </div>
                                    <div class="recent-time">
                                        <span class="status-badge status-pending">Pending</span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-results">
                                <i class="fas fa-check-circle"></i>
                                <p>No recipes pending approval</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent Users</h3>
                    <a href="manage_users.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <div class="recent-list">
                        <?php while ($user = $recent_users_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="recent-item">
                                <div class="recent-avatar">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <div class="recent-info">
                                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                                <div class="recent-time">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Recipes -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent Recipes</h3>
                    <a href="manage_recipes.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <div class="recent-list">
                        <?php while ($recipe = $recent_recipes_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="recent-item">
                                <div class="recent-avatar" style="background: #e74c3c;">
                                    <i class="fas fa-utensils"></i>
                                </div>
                                <div class="recent-info">
                                    <h4><?php echo htmlspecialchars($recipe['title']); ?></h4>
                                    <p>By <?php echo htmlspecialchars($recipe['author']); ?></p>
                                </div>
                                <div class="recent-time">
                                    <span class="status-badge status-<?php echo $recipe['approval_status']; ?>">
                                        <?php echo ucfirst($recipe['approval_status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
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
    <script src="../js/main.js"></script>
</body>
</html>