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
$success = $error = "";

// Get user data
$user_query = "SELECT name, email FROM users WHERE id = :id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ($_POST) {
    $name = trim($_POST['name']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!empty($new_password)) {
        // Verify current password
        $verify_query = "SELECT password FROM users WHERE id = :id";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->bindParam(':id', $user_id);
        $verify_stmt->execute();
        $user_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user_data['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long.";
        } else {
            // Update with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET name = :name, password = :password WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':id', $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $name;
                $success = "Profile updated successfully!";
                $user['name'] = $name;
            } else {
                $error = "Error updating profile.";
            }
        }
    } else {
        // Update without password
        $query = "UPDATE users SET name = :name WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            $success = "Profile updated successfully!";
            $user['name'] = $name;
        } else {
            $error = "Error updating profile.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TastyKitchen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="../assets/img/webpage logo2.1.png" alt="Grandma's Kitchen Logo" class="logo-img">
            <h1>Grandma's<span>Kitchen</span></h1>
            <p>My Profile</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="add_recipe.php"><i class="fas fa-plus-circle"></i> Add Recipe</a></li>
            <li><a href="my_recipes.php"><i class="fas fa-utensils"></i> My Recipes</a></li>
            <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
            <li class="logout-item">
                <a href="../auth/logout.php" class="logout-btn-sidebar">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="add-recipe-form">
            <h2 class="form-title">My Profile</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <small>Email cannot be changed</small>
                </div>
                
                <h3>Change Password</h3>
                <p class="text-muted">Leave password fields empty if you don't want to change your password.</p>
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                </div>
                
                <button type="submit" class="btn">Update Profile</button>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>