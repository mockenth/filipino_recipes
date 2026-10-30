<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get recipe data
$recipe_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Check if recipe belongs to user
$recipe_query = "SELECT * FROM recipes WHERE id = :id AND user_id = :user_id";
$recipe_stmt = $db->prepare($recipe_query);
$recipe_stmt->bindParam(':id', $recipe_id);
$recipe_stmt->bindParam(':user_id', $user_id);
$recipe_stmt->execute();

if ($recipe_stmt->rowCount() == 0) {
    header("Location: my_recipes.php");
    exit();
}

$recipe = $recipe_stmt->fetch(PDO::FETCH_ASSOC);

// Get categories
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
    $status = $_POST['status'];
    
    // Handle image upload
    $image = $recipe['image'];
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
                // Delete old image if exists
                if ($image && file_exists('../' . $image)) {
                    unlink('../' . $image);
                }
                $image = 'uploads/' . $filename;
            }
        }
    }
    
    // NEW: When user edits a recipe, reset approval_status to 'pending' for admin review
    $query = "UPDATE recipes SET 
                title = :title, 
                description = :description, 
                ingredients = :ingredients, 
                instructions = :instructions, 
                prep_time = :prep_time, 
                cook_time = :cook_time, 
                servings = :servings, 
                difficulty = :difficulty, 
                image = :image, 
                category_id = :category_id, 
                status = :status, 
                approval_status = 'pending',  -- Reset to pending for re-approval
                admin_notes = NULL,           -- Clear previous admin notes
                reviewed_at = NULL,           -- Clear previous review timestamp
                reviewed_by = NULL,           -- Clear previous reviewer
                updated_at = NOW() 
              WHERE id = :id AND user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':ingredients', $ingredients);
    $stmt->bindParam(':instructions', $instructions);
    $stmt->bindParam(':prep_time', $prep_time);
    $stmt->bindParam(':cook_time', $cook_time);
    $stmt->bindParam(':servings', $servings);
    $stmt->bindParam(':difficulty', $difficulty);
    $stmt->bindParam(':image', $image);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $recipe_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        $success = "Recipe updated successfully! It has been sent for admin approval again.";
        // Refresh recipe data
        $recipe_stmt->execute();
        $recipe = $recipe_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Error updating recipe. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Recipe - Grandma's Kitchen</title>
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

        .approval-status {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #6c757d;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }

        .admin-notes {
            margin-top: 10px;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #e9ecef;
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
            <p>Edit Recipe</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="add_recipe.php"><i class="fas fa-plus-circle"></i> Add Recipe</a></li>
            <li><a href="my_recipes.php"><i class="fas fa-utensils"></i> My Recipes</a></li>
            <li class="logout-item">
                <a href="../auth/logout.php" class="logout-btn-sidebar">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="add-recipe-form">
            <h2 class="form-title">Edit Recipe</h2>
            
            <!-- Current Approval Status -->
            <div class="approval-status">
                <strong>Current Approval Status:</strong>
                <span class="status-badge <?php echo $recipe['approval_status']; ?>">
                    <?php echo ucfirst($recipe['approval_status']); ?>
                </span>
                
                <?php if ($recipe['approval_status'] == 'rejected' && !empty($recipe['admin_notes'])): ?>
                    <div class="admin-notes">
                        <strong><i class="fas fa-exclamation-circle"></i> Admin Notes:</strong><br>
                        <?php echo htmlspecialchars($recipe['admin_notes']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Approval Notice -->
            <div class="approval-notice">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> After editing, this recipe will require admin approval again before being published.
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
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($recipe['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php 
                            $categories_stmt->execute();
                            while ($category = $categories_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $recipe['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($recipe['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="prep_time">Prep Time (minutes) *</label>
                        <input type="number" id="prep_time" name="prep_time" class="form-control" value="<?php echo $recipe['prep_time']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cook_time">Cook Time (minutes) *</label>
                        <input type="number" id="cook_time" name="cook_time" class="form-control" value="<?php echo $recipe['cook_time']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="servings">Servings *</label>
                        <input type="number" id="servings" name="servings" class="form-control" value="<?php echo $recipe['servings']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="difficulty">Difficulty *</label>
                        <select id="difficulty" name="difficulty" class="form-control" required>
                            <option value="Easy" <?php echo $recipe['difficulty'] == 'Easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="Medium" <?php echo $recipe['difficulty'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Hard" <?php echo $recipe['difficulty'] == 'Hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ingredients">Ingredients * (one per line)</label>
                    <textarea id="ingredients" name="ingredients" class="form-control" rows="5" required><?php echo htmlspecialchars($recipe['ingredients']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="instructions">Instructions * (step by step)</label>
                    <textarea id="instructions" name="instructions" class="form-control" rows="8" required><?php echo htmlspecialchars($recipe['instructions']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Publication Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="published" <?php echo $recipe['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $recipe['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="image">Recipe Image</label>
                    <?php if ($recipe['image']): ?>
                        <div class="current-image">
                            <img src="../<?php echo $recipe['image']; ?>" alt="Current recipe image" style="max-width: 200px; margin-bottom: 10px;">
                            <br>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" class="form-control" accept="image/*">
                    <small>Leave empty to keep current image. Supported formats: JPG, PNG, GIF</small>
                </div>
                
                <!-- Status Information -->
                <div class="status-info">
                    <i class="fas fa-sync-alt"></i>
                    <strong>Status after update:</strong> <span style="color: #856404;">Pending Approval</span>
                    <br>
                    <small>Your recipe will be sent for admin review again after saving changes.</small>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sync-alt"></i> Update & Resubmit for Approval
                </button>
                <a href="my_recipes.php" class="btn btn-outline">Cancel</a>
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