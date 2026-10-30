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

$success = $error = '';

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile Update
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        
        if (empty($name)) {
            $error = "Name is required.";
        } else {
            $query = "UPDATE users SET name = :name, updated_at = NOW() WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $name;
                $success = "Profile updated successfully!";
            } else {
                $error = "Error updating profile.";
            }
        }
    }
    
    // Password Update
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $error = "Current password is required.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters long.";
            } else {
                // Verify current password
                $verify_query = "SELECT password FROM users WHERE id = :id";
                $verify_stmt = $db->prepare($verify_query);
                $verify_stmt->bindParam(':id', $user_id);
                $verify_stmt->execute();
                $user_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($current_password, $user_data['password'])) {
                    $error = "Current password is incorrect.";
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        $success = "Password updated successfully!";
                    } else {
                        $error = "Error updating password.";
                    }
                }
            }
        }
    }
}

// Handle avatar upload
if ($_FILES && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = $_FILES['avatar']['type'];
    $file_size = $_FILES['avatar']['size'];
    
    if (in_array($file_type, $allowed_types)) {
        if ($file_size <= 5 * 1024 * 1024) {
            $upload_dir = '../uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Get current avatar to delete later
            $current_avatar_query = "SELECT avatar FROM users WHERE id = :id";
            $current_avatar_stmt = $db->prepare($current_avatar_query);
            $current_avatar_stmt->bindParam(':id', $user_id);
            $current_avatar_stmt->execute();
            $current_avatar = $current_avatar_stmt->fetch(PDO::FETCH_ASSOC)['avatar'];
            
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $file_path)) {
                // Delete old avatar if exists
                if ($current_avatar && file_exists('../' . $current_avatar)) {
                    unlink('../' . $current_avatar);
                }
                
                $avatar_path = 'uploads/avatars/' . $filename;
                $avatar_query = "UPDATE users SET avatar = :avatar WHERE id = :id";
                $avatar_stmt = $db->prepare($avatar_query);
                $avatar_stmt->bindParam(':avatar', $avatar_path);
                $avatar_stmt->bindParam(':id', $user_id);
                
                if ($avatar_stmt->execute()) {
                    $success = $success ? $success . " Avatar updated successfully!" : "Avatar updated successfully!";
                } else {
                    $error = $error ? $error . " Error updating avatar." : "Error updating avatar.";
                }
            } else {
                $error = $error ? $error . " Error uploading avatar." : "Error uploading avatar.";
            }
        } else {
            $error = $error ? $error . " Avatar file too large (max 5MB)." : "Avatar file too large (max 5MB).";
        }
    } else {
        $error = $error ? $error . " Invalid file type. Only JPG, PNG, GIF, and WebP allowed." : "Invalid file type. Only JPG, PNG, GIF, and WebP allowed.";
    }
}

// Get user data for display
$user_query = "SELECT name, email, avatar, created_at FROM users WHERE id = :id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics
$stats_query = "SELECT 
    COUNT(*) as total_recipes,
    SUM(views) as total_views,
    SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_recipes
    FROM recipes 
    WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TastyKitchen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }

        .avatar-container {
            position: relative;
            text-align: center;
        }

        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
        }

        .avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            border: 4px solid var(--primary-color);
        }

        .avatar-upload {
            margin-top: 15px;
        }

        .avatar-upload-label {
            display: inline-block;
            padding: 8px 15px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .avatar-upload-label:hover {
            background: #d84315;
        }

        .avatar-upload input {
            display: none;
        }

        .profile-info h1 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .profile-stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        .profile-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 1rem;
        }

        .profile-form-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .form-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }

        .form-section h3 {
            color: var(--secondary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .member-since {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
        }

        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-form-container {
                grid-template-columns: 1fr;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }
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
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="avatar-container">
                <?php if ($user['avatar'] && file_exists('../' . $user['avatar'])): ?>
                    <img src="../<?php echo $user['avatar']; ?>" alt="Profile Avatar" class="avatar">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                
                <div class="avatar-upload">
                    <form method="POST" action="" enctype="multipart/form-data" id="avatar-form">
                        <label for="avatar-upload" class="avatar-upload-label">
                            <i class="fas fa-camera"></i> Change Avatar
                        </label>
                        <input type="file" id="avatar-upload" name="avatar" accept="image/*">
                    </form>
                </div>
            </div>
            
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['name']); ?></h1>
                <p class="member-since">
                    <i class="fas fa-calendar-alt"></i> 
                    Joined since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                </p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>

        <!-- Profile Stats -->
        <div class="profile-stats">
            <div class="profile-stat-card">
                <div class="stat-number"><?php echo $stats['total_recipes'] ?? 0; ?></div>
                <div class="stat-label">Recipes Published</div>
            </div>
            
            <div class="profile-stat-card">
                <div class="stat-number"><?php echo $stats['total_views'] ?? 0; ?></div>
                <div class="stat-label">Total Views</div>
            </div>
            
            <div class="profile-stat-card">
                <div class="stat-number"><?php echo $stats['featured_recipes'] ?? 0; ?></div>
                <div class="stat-label">Featured Recipes</div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Profile Forms -->
        <div class="profile-form-container">
            <!-- Profile Information Form -->
            <div class="form-section">
                <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small class="text-muted">Email cannot be changed</small>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="form-section">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <form method="POST" action="" id="password-form">
                    <input type="hidden" name="update_password" value="1">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control">
                        <div id="password-strength" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                        <div id="password-match" class="password-strength"></div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                    
                    <p class="text-muted" style="margin-top: 15px; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> Leave password fields empty if you don't want to change your password.
                    </p>
                </form>
            </div>
        </div>

        <!-- Account Actions -->
        <div class="form-section" style="margin-top: 30px;">
            <h3><i class="fas fa-cog"></i> Account Actions</h3>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="my_recipes.php" class="btn btn-outline">
                    <i class="fas fa-utensils"></i> View My Recipes
                </a>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
                <a href="../auth/logout.php" class="btn" style="background: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <script>
        // Avatar upload preview and auto-submit
        document.getElementById('avatar-upload').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarContainer = document.querySelector('.avatar-container');
                    const existingAvatar = avatarContainer.querySelector('.avatar');
                    const existingPlaceholder = avatarContainer.querySelector('.avatar-placeholder');
                    
                    if (existingAvatar) {
                        existingAvatar.src = e.target.result;
                    } else if (existingPlaceholder) {
                        existingPlaceholder.innerHTML = '<img src="' + e.target.result + '" class="avatar" alt="Profile Avatar">';
                    }
                };
                reader.readAsDataURL(this.files[0]);
                
                // Auto-submit form
                document.getElementById('avatar-form').submit();
            }
        });

        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthElement = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthElement.innerHTML = '';
                return;
            }
            
            const strength = checkPasswordStrength(password);
            let message = '';
            let className = '';
            
            switch(strength) {
                case 'weak':
                    message = 'Weak password';
                    className = 'strength-weak';
                    break;
                case 'medium':
                    message = 'Medium strength';
                    className = 'strength-medium';
                    break;
                case 'strong':
                    message = 'Strong password';
                    className = 'strength-strong';
                    break;
            }
            
            strengthElement.innerHTML = `<span class="${className}">${message}</span>`;
        });

        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const matchElement = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchElement.innerHTML = '';
                return;
            }
            
            if (newPassword === confirmPassword) {
                matchElement.innerHTML = '<span class="strength-strong">Passwords match</span>';
            } else {
                matchElement.innerHTML = '<span class="strength-weak">Passwords do not match</span>';
            }
        });

        function checkPasswordStrength(password) {
            const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})/;
            const mediumRegex = /^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z])(?=.*[0-9]))|((?=.*[A-Z])(?=.*[0-9])))(?=.{6,})/;

            if (strongRegex.test(password)) {
                return 'strong';
            } else if (mediumRegex.test(password)) {
                return 'medium';
            } else {
                return 'weak';
            }
        }

        // Form validation
        document.getElementById('password-form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            // If any password field is filled, all must be filled
            if (newPassword || confirmPassword || currentPassword) {
                if (!currentPassword) {
                    alert('Please enter your current password to change your password.');
                    e.preventDefault();
                    return;
                }
                
                if (!newPassword) {
                    alert('Please enter a new password.');
                    e.preventDefault();
                    return;
                }
                
                if (!confirmPassword) {
                    alert('Please confirm your new password.');
                    e.preventDefault();
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    alert('New passwords do not match.');
                    e.preventDefault();
                    return;
                }
                
                if (newPassword.length < 6) {
                    alert('New password must be at least 6 characters long.');
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>
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