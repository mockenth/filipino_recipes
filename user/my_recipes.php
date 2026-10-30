<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$recipes_query = "SELECT r.*, c.name as category_name 
                  FROM recipes r 
                  LEFT JOIN categories c ON r.category_id = c.id 
                  WHERE r.user_id = :user_id 
                  ORDER BY r.created_at DESC";
$recipes_stmt = $db->prepare($recipes_query);
$recipes_stmt->bindParam(':user_id', $user_id);
$recipes_stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Recipes - TastyKitchen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
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
            <p>My Recipes</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="add_recipe.php"><i class="fas fa-plus-circle"></i> Add Recipe</a></li>
            <li><a href="my_recipes.php" class="active"><i class="fas fa-utensils"></i> My Recipes</a></li>
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
            <h1>My Recipes</h1>
            <p>Manage all your submitted recipes</p>
        </div>

        <div class="recipes-grid">
            <?php while ($recipe = $recipes_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                <div class="recipe-card">
                    <div class="recipe-badge"><?php echo htmlspecialchars($recipe['category_name']); ?></div>
                     <div class="recipe-img" style="background-image: url('<?php echo $recipe['image'] ? '../' . $recipe['image'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1480&q=80'; ?>')">
                    </div>
                    <div class="recipe-content">
                        <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                        <div class="recipe-meta">
                            <span>Status: <?php echo ucfirst($recipe['status']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo $recipe['prep_time'] + $recipe['cook_time']; ?> mins</span>
                        </div>
                        <p class="recipe-description"><?php echo htmlspecialchars(substr($recipe['description'], 0, 100)); ?>...</p>
                        <div class="recipe-stats">
                            <span><i class="fas fa-eye"></i> <?php echo $recipe['views']; ?> views</span>
                            <span><i class="fas fa-utensils"></i> Serves <?php echo $recipe['servings']; ?></span>
                        </div>
                        <div class="recipe-actions">
                            <a href="../view_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-view">View</a>
                            <a href="edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-edit">Edit</a>
                            <a href="delete_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
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