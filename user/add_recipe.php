<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get categories for dropdown
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();

$success = $error = "";

if ($_POST) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $ingredients = trim($_POST['ingredients']);
    $instructions = trim($_POST['instructions']);
    $prep_time = intval($_POST['prep_time']);
    $cook_time = intval($_POST['cook_time']);
    $servings = intval($_POST['servings']);
    $difficulty = $_POST['difficulty'];
    $category_id = intval($_POST['category_id']);
    $user_id = $_SESSION['user_id'];
    
    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'recipe_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image = 'uploads/' . $filename;
            }
        }
    }
    
    // Generate slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    
    // NEW: Automatically set approval_status to 'pending' for new recipes
    $query = "INSERT INTO recipes (title, slug, description, ingredients, instructions, prep_time, cook_time, servings, difficulty, image, category_id, user_id, approval_status) 
              VALUES (:title, :slug, :description, :ingredients, :instructions, :prep_time, :cook_time, :servings, :difficulty, :image, :category_id, :user_id, 'pending')";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':slug', $slug);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':ingredients', $ingredients);
    $stmt->bindParam(':instructions', $instructions);
    $stmt->bindParam(':prep_time', $prep_time);
    $stmt->bindParam(':cook_time', $cook_time);
    $stmt->bindParam(':servings', $servings);
    $stmt->bindParam(':difficulty', $difficulty);
    $stmt->bindParam(':image', $image);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        $success = "Recipe submitted successfully! It will be reviewed by an admin before being published.";
        // Clear form
        $_POST = array();
    } else {
        $error = "Error adding recipe. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Recipe - Grandma's Kitchen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .approval-notice {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: #1565c0;
        }

        .approval-notice i {
            color: #2196f3;
            margin-right: 10px;
        }

        .status-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            font-size: 0.9rem;
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
            <p>Add New Recipe</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="add_recipe.php" class="active"><i class="fas fa-plus-circle"></i> Add Recipe</a></li>
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
        <div class="add-recipe-form">
            <h2 class="form-title">Add a New Recipe</h2>
            
            <!-- Approval Notice -->
            <div class="approval-notice">
                <i class="fas fa-info-circle"></i>
                <strong>Important:</strong> All recipes require admin approval before being published on the website.
                Your recipe will be automatically sent for review after submission.
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Recipe Title *</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo $_POST['title'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php 
                            $categories_stmt->execute();
                            while ($category = $categories_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" class="form-control" rows="3" required><?php echo $_POST['description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="prep_time">Prep Time (minutes) *</label>
                        <input type="number" id="prep_time" name="prep_time" class="form-control" value="<?php echo $_POST['prep_time'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cook_time">Cook Time (minutes) *</label>
                        <input type="number" id="cook_time" name="cook_time" class="form-control" value="<?php echo $_POST['cook_time'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="servings">Servings *</label>
                        <input type="number" id="servings" name="servings" class="form-control" value="<?php echo $_POST['servings'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="difficulty">Difficulty *</label>
                        <select id="difficulty" name="difficulty" class="form-control" required>
                            <option value="Easy" <?php echo ($_POST['difficulty'] ?? '') == 'Easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="Medium" <?php echo ($_POST['difficulty'] ?? '') == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Hard" <?php echo ($_POST['difficulty'] ?? '') == 'Hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ingredients">Ingredients * (one per line)</label>
                    <textarea id="ingredients" name="ingredients" class="form-control" rows="5" required><?php echo $_POST['ingredients'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="instructions">Instructions * (step by step)</label>
                    <textarea id="instructions" name="instructions" class="form-control" rows="8" required><?php echo $_POST['instructions'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="image">Recipe Image</label>
                    <input type="file" id="image" name="image" class="form-control" accept="image/*">
                    <small>Supported formats: JPG, PNG, GIF</small>
                </div>
                
                <!-- Status Information -->
                <div class="status-info">
                    <i class="fas fa-clock"></i>
                    <strong>Status after submission:</strong> <span style="color: #856404;">Pending Approval</span>
                    <br>
                    <small>Your recipe will be visible to admins for review immediately after submission.</small>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Submit for Approval
                </button>
            </form>
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