<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get featured recipes
$featured_query = "SELECT r.*, c.name as category_name, u.name as author_name 
                   FROM recipes r 
                   LEFT JOIN categories c ON r.category_id = c.id 
                   LEFT JOIN users u ON r.user_id = u.id 
                   WHERE r.status = 'published' AND r.is_featured = 1 
                   AND r.approval_status = 'approved' 
                   AND r.is_featured = 1 
                   ORDER BY r.created_at DESC LIMIT 6";
$featured_stmt = $db->prepare($featured_query);
$featured_stmt->execute();

// Get categories
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TastyKitchen - Filipino Recipes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Mobile Toggle Button (visible only on small screens) -->
    <button class="mobile-toggle-btn" id="mobileToggleBtn" aria-label="Toggle Navigation">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <div class="logo"> <!-- logo here -->
            <img src="../assets/img/webpage logo2.1.png" alt="Grandma's Kitchen Logo" class="logo-img"> 
            <h1>Grandma's<span>Kitchen</span></h1>
            <p>Authentic Filipino Recipes</p>
        </div>
        
        <!-- Search Bar -->
        <div class="search-container">
            <form action="recipes.php" method="GET">
                <div class="search-box">
                    <input type="text" name="search" class="search-input" placeholder="Search recipes...">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <ul class="nav-links">
            <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="recipes.php"><i class="fas fa-utensils"></i> All Recipes</a></li>
            <li><a href="recipes.php?category=main-course"><i class="fas fa-list"></i> Categories</a></li>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="user/dashboard.php"><i class="fas fa-user"></i> My Dashboard</a></li>
                <li><a href="user/add_recipe.php"><i class="fas fa-plus-circle"></i> Add Recipe</a></li>
                <?php if ($_SESSION['user_role'] == 'admin'): ?>
                    <li><a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                <?php endif; ?>
                <li class="logout-item">
                    <a href="auth/logout.php" class="logout-btn-sidebar">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            <?php else: ?>
                <li><a href="auth/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a href="auth/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <h2>Discover Authentic Filipino Recipes</h2>
                <p>Join our community and explore traditional Filipino dishes passed down through generations</p>
                <a href="recipes.php" class="btn">Explore Recipes</a>
            </div>
        </section>

        <!-- Categories -->
        <section class="categories-section">
            <h2 class="section-title">Recipe Categories</h2>
            <div class="categories-grid">
                <?php while ($category = $categories_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <a href="recipes.php?category=<?php echo $category['slug']; ?>" class="category-card">
                        <div class="category-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                        <p><?php echo htmlspecialchars($category['description']); ?></p>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- Featured Recipes -->
        <section id="recipes">
            <h2 class="section-title">Featured Recipes</h2>
            <div class="recipes-grid">
                <?php while ($recipe = $featured_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="recipe-card">
                        <div class="recipe-badge"><?php echo htmlspecialchars($recipe['category_name']); ?></div>
                        <div class="recipe-img" style="background-image: url('<?php echo $recipe['image'] ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1480&q=80'; ?>')">
                        </div>
                        <div class="recipe-content">
                            <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                            <div class="recipe-meta">
                                <span>By: <?php echo htmlspecialchars($recipe['author_name']); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo $recipe['prep_time'] + $recipe['cook_time']; ?> mins</span>
                            </div>
                            <p class="recipe-description"><?php echo htmlspecialchars(substr($recipe['description'], 0, 100)); ?>...</p>
                            <div class="recipe-stats">
                                <span><i class="fas fa-eye"></i> <?php echo $recipe['views']; ?> views</span>
                                <span><i class="fas fa-utensils"></i> Serves <?php echo $recipe['servings']; ?></span>
                            </div>
                            <a href="view_recipe.php?id=<?php echo $recipe['id']; ?>" class="view-recipe">View Recipe</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <?php if ($featured_stmt->rowCount() == 0): ?>
                <div class="no-results">
                    <i class="fas fa-utensils"></i>
                    <p>No featured recipes yet. Check back soon!</p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <script src="js/main.js"></script>
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