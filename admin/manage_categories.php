<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all categories
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();

// Handle form submission
$success = $error = "";
if ($_POST) {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $error = "Category name is required.";
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            
            // Check if slug exists
            $check_query = "SELECT id FROM categories WHERE slug = :slug";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':slug', $slug);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = "Category already exists.";
            } else {
                $insert_query = "INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':name', $name);
                $insert_stmt->bindParam(':slug', $slug);
                $insert_stmt->bindParam(':description', $description);
                
                if ($insert_stmt->execute()) {
                    $success = "Category added successfully!";
                    // Refresh categories
                    $categories_stmt->execute();
                } else {
                    $error = "Error adding category.";
                }
            }
        }
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Check if category has recipes
    $check_recipes = "SELECT COUNT(*) FROM recipes WHERE category_id = :id";
    $check_stmt = $db->prepare($check_recipes);
    $check_stmt->bindParam(':id', $delete_id);
    $check_stmt->execute();
    $recipe_count = $check_stmt->fetchColumn();
    
    if ($recipe_count > 0) {
        $error = "Cannot delete category that has recipes. Please reassign recipes first.";
    } else {
        $delete_query = "DELETE FROM categories WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $delete_id);
        
        if ($delete_stmt->execute()) {
            $success = "Category deleted successfully!";
            // Refresh categories
            $categories_stmt->execute();
        } else {
            $error = "Error deleting category.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <!-- Mobile Toggle Button (visible only on small screens) -->
    <button class="mobile-toggle-btn" id="mobileToggleBtn" aria-label="Toggle Navigation">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../assets/img/webpage logo2.1.png" alt="Grandma's Kitchen Logo" class="logo-img">
            <h1>Grand's<span>Kitchen</span></h1>
            <p>Manage Categories</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_recipes.php"><i class="fas fa-utensils"></i> Manage Recipes</a></li>
            <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="manage_categories.php" class="active"><i class="fas fa-tags"></i> Categories</a></li>
            <li class="logout-item">
                <a href="../auth/logout.php" class="logout-btn-sidebar">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="admin-header">
            <h1>Manage Categories</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Add Category Form -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Add New Category</h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Category Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" name="add_category" class="btn">Add Category</button>
                    </form>
                </div>
            </div>

            <!-- Categories List -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Existing Categories</h3>
                </div>
                <div class="card-content">
                    <div class="categories-list">
                        <?php while ($category = $categories_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="category-item">
                                <div class="category-info">
                                    <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($category['description']); ?></p>
                                    <small>Slug: <?php echo $category['slug']; ?></small>
                                </div>
                                <div class="category-actions">
                                    <a href="?delete_id=<?php echo $category['id']; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Are you sure? This will permanently delete the category.')">Delete</a>
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