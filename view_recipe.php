<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$recipe_id = $_GET['id'] ?? 0;

// Get recipe details
$recipe_query = "SELECT r.*, c.name as category_name, u.name as author_name 
                 FROM recipes r 
                 LEFT JOIN categories c ON r.category_id = c.id 
                 LEFT JOIN users u ON r.user_id = u.id 
                 WHERE r.id = :id AND r.status = 'published'";
$recipe_stmt = $db->prepare($recipe_query);
$recipe_stmt->bindParam(':id', $recipe_id);
$recipe_stmt->execute();

if ($recipe_stmt->rowCount() == 0) {
    header("Location: recipes.php");
    exit();
}

$recipe = $recipe_stmt->fetch(PDO::FETCH_ASSOC);

// Increment view count
$views_query = "UPDATE recipes SET views = views + 1 WHERE id = :id";
$views_stmt = $db->prepare($views_query);
$views_stmt->bindParam(':id', $recipe_id);
$views_stmt->execute();

// --- FIX: Safely check permissions without undefined array key warnings ---
$is_author = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $recipe['user_id'];
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
$can_edit = $is_author || $is_admin;

// Convert ingredients and instructions to arrays
$ingredients = explode("\n", $recipe['ingredients']);
$instructions = explode("\n", $recipe['instructions']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe['title']); ?> - TastyKitchen</title>
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
            <img src="assets/img/webpage logo2.1.png" alt="Grandma's Kitchen Logo" class="logo-img">
            <h1>Grandma's<span>Kitchen</span></h1>
            <p>Recipe Details</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="recipes.php"><i class="fas fa-utensils"></i> All Recipes</a></li>
            <li><a href="recipes.php?category=<?php echo urlencode($recipe['category_name']); ?>"><i class="fas fa-list"></i> <?php echo htmlspecialchars($recipe['category_name']); ?></a></li>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="user/dashboard.php"><i class="fas fa-user"></i> My Dashboard</a></li>
                <li><a href="user/add_recipe.php"><i class="fas fa-plus-circle"></i> Add Recipe</a></li>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
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
        <div class="recipe-detail">
            <!-- Recipe Header -->
            <div class="recipe-header">
                <div class="recipe-image-large">
                    <img src="<?php echo $recipe['image'] ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1480&q=80'; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                </div>
                <div class="recipe-info">
                    <div class="recipe-badge-large"><?php echo htmlspecialchars($recipe['category_name']); ?></div>
                    <h1><?php echo htmlspecialchars($recipe['title']); ?></h1>
                    <p class="recipe-description-large"><?php echo htmlspecialchars($recipe['description']); ?></p>
                    
                    <div class="recipe-meta-large">
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span>By <?php echo htmlspecialchars($recipe['author_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $recipe['prep_time'] + $recipe['cook_time']; ?> mins total</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-utensils"></i>
                            <span>Serves <?php echo $recipe['servings']; ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-fire"></i>
                            <span class="difficulty-badge <?php echo strtolower($recipe['difficulty']); ?>">
                                <?php echo $recipe['difficulty']; ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-eye"></i>
                            <span><?php echo $recipe['views'] + 1; ?> views</span>
                        </div>
                    </div>
                    
                    <div class="time-breakdown">
                        <div class="time-item">
                            <strong>Prep Time</strong>
                            <span><?php echo $recipe['prep_time']; ?> mins</span>
                        </div>
                        <div class="time-item">
                            <strong>Cook Time</strong>
                            <span><?php echo $recipe['cook_time']; ?> mins</span>
                        </div>
                        <div class="time-item">
                            <strong>Total Time</strong>
                            <span><?php echo $recipe['prep_time'] + $recipe['cook_time']; ?> mins</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recipe Content -->
            <div class="recipe-content-detail">
                <div class="ingredients-section">
                    <h2><i class="fas fa-list"></i> Ingredients</h2>
                    <ul class="ingredients-list">
                        <?php foreach ($ingredients as $ingredient): ?>
                            <?php if (trim($ingredient)): ?>
                                <li><?php echo htmlspecialchars(trim($ingredient)); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="instructions-section">
                    <h2><i class="fas fa-mortar-pestle"></i> Instructions</h2>
                    <ol class="instructions-list">
                        <?php foreach ($instructions as $index => $instruction): ?>
                            <?php if (trim($instruction)): ?>
                                <li>
                                    <div class="instruction-step">
                                        <span class="step-number"><?php echo $index + 1; ?></span>
                                        <div class="step-content"><?php echo nl2br(htmlspecialchars(trim($instruction))); ?></div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="recipe-actions-large">
                <a href="recipes.php?category=<?php echo urlencode($recipe['category_name']); ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> More <?php echo htmlspecialchars($recipe['category_name']); ?> Recipes
                </a>
                <?php if ($can_edit): ?>
                    <a href="user/edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-edit">
                        <i class="fas fa-edit"></i> Edit Recipe
                    </a>
                <?php endif; ?>
            </div>
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