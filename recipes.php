<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category_slug = $_GET['category'] ?? '';
$difficulty = $_GET['difficulty'] ?? '';

// Build query
$query = "SELECT r.*, c.name as category_name, u.name as author_name 
          FROM recipes r 
          LEFT JOIN categories c ON r.category_id = c.id 
          LEFT JOIN users u ON r.user_id = u.id 
          WHERE r.status = 'published'
          AND r.approval_status = 'approved'";

$params = [];

if (!empty($search)) {
    $query .= " AND (r.title LIKE :search OR r.description LIKE :search OR r.ingredients LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_slug)) {
    $query .= " AND c.slug = :category_slug";
    $params[':category_slug'] = $category_slug;
}

if (!empty($difficulty)) {
    $query .= " AND r.difficulty = :difficulty";
    $params[':difficulty'] = $difficulty;
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get categories for filter
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();

// Get current category name if filtered
$current_category = '';
if (!empty($category_slug)) {
    $cat_query = "SELECT name FROM categories WHERE slug = :slug";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->bindParam(':slug', $category_slug);
    $cat_stmt->execute();
    $current_category = $cat_stmt->fetch(PDO::FETCH_ASSOC)['name'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipes - TastyKitchen</title>
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
        <div class="logo">
            <img src="../assets/img/webpage logo2.1.png" alt="Grandma's Kitchen Logo" class="logo-img">
            <h1>Grandma's<span>Kitchen</span></h1>
            <p>Browse Recipes</p>
        </div>
        
        <!-- Search Bar -->
        <div class="search-container">
            <form action="recipes.php" method="GET">
                <div class="search-box">
                    <input type="text" name="search" class="search-input" placeholder="Search recipes..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <ul class="nav-links">
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="recipes.php" class="active"><i class="fas fa-utensils"></i> All Recipes</a></li>
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

    <div class="main-content">
        <div class="page-header">
            <h1>
                <?php if (!empty($current_category)): ?>
                    <?php echo htmlspecialchars($current_category); ?> Recipes
                <?php elseif (!empty($search)): ?>
                    Search Results for "<?php echo htmlspecialchars($search); ?>"
                <?php else: ?>
                    All Recipes
                <?php endif; ?>
            </h1>
            <p>Discover delicious Filipino recipes</p>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters">
                <div class="filter-group">
                    <strong>Categories:</strong>
                    <a href="recipes.php" class="filter-btn <?php echo empty($category_slug) ? 'active' : ''; ?>">All</a>
                    <?php 
                    $categories_stmt->execute(); // Re-execute for filters
                    while ($category = $categories_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <a href="recipes.php?category=<?php echo $category['slug']; ?>" class="filter-btn <?php echo $category_slug == $category['slug'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    <?php endwhile; ?>
                </div>
                
                <div class="filter-group">
                    <strong>Difficulty:</strong>
                    <a href="recipes.php<?php echo !empty($category_slug) ? '?category=' . $category_slug : ''; ?>" class="filter-btn <?php echo empty($difficulty) ? 'active' : ''; ?>">All</a>
                    <a href="recipes.php?<?php echo !empty($category_slug) ? 'category=' . $category_slug . '&' : ''; ?>difficulty=Easy" class="filter-btn <?php echo $difficulty == 'Easy' ? 'active' : ''; ?>">Easy</a>
                    <a href="recipes.php?<?php echo !empty($category_slug) ? 'category=' . $category_slug . '&' : ''; ?>difficulty=Medium" class="filter-btn <?php echo $difficulty == 'Medium' ? 'active' : ''; ?>">Medium</a>
                    <a href="recipes.php?<?php echo !empty($category_slug) ? 'category=' . $category_slug . '&' : ''; ?>difficulty=Hard" class="filter-btn <?php echo $difficulty == 'Hard' ? 'active' : ''; ?>">Hard</a>
                </div>
            </div>
        </div>

        <!-- Recipes Grid -->
        <div class="recipes-grid">
            <?php while ($recipe = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
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
                        <div class="recipe-difficulty">
                            <span class="difficulty-badge <?php echo strtolower($recipe['difficulty']); ?>">
                                <?php echo $recipe['difficulty']; ?>
                            </span>
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
            
            <?php if ($stmt->rowCount() == 0): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No Recipes Found</h3>
                    <p>Try adjusting your search or filters to find more recipes.</p>
                    <a href="recipes.php" class="btn">View All Recipes</a>
                </div>
            <?php endif; ?>
        </div>
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