<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Initialize search variables
$search_term = '';
$where_conditions = [];
$query_params = [];

// Handle search
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $where_conditions[] = "(u.name LIKE :search OR u.email LIKE :search)";
    $query_params[':search'] = '%' . $search_term . '%';
}

// Handle role filter
if (isset($_GET['role']) && !empty($_GET['role']) && in_array($_GET['role'], ['admin', 'user'])) {
    $where_conditions[] = "u.role = :role";
    $query_params[':role'] = $_GET['role'];
}

// Build the WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get all users with search/filter
$users_query = "SELECT u.id, u.name, u.email, u.role, u.created_at 
                FROM users u 
                $where_clause
                ORDER BY u.created_at DESC";
$users_stmt = $db->prepare($users_query);

// Bind parameters if they exist
foreach ($query_params as $param => $value) {
    $users_stmt->bindValue($param, $value);
}

$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user counts for filters
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$admin_count = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$user_count = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = $_GET['id'];
    
    // Prevent admin from modifying their own account
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot modify your own account.";
        header("Location: manage_users.php" . (!empty($search_term) ? "?search=" . urlencode($search_term) : ""));
        exit();
    }
    
    switch ($action) {
        case 'delete':
            $delete_query = "DELETE FROM users WHERE id = :id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':id', $user_id);
            if ($delete_stmt->execute()) {
                $_SESSION['success'] = "User deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting user.";
            }
            header("Location: manage_users.php" . (!empty($search_term) ? "?search=" . urlencode($search_term) : ""));
            exit();
            break;
            
        case 'make_admin':
            $admin_query = "UPDATE users SET role = 'admin' WHERE id = :id";
            $admin_stmt = $db->prepare($admin_query);
            $admin_stmt->bindParam(':id', $user_id);
            if ($admin_stmt->execute()) {
                $_SESSION['success'] = "User promoted to admin!";
            } else {
                $_SESSION['error'] = "Error promoting user.";
            }
            header("Location: manage_users.php" . (!empty($search_term) ? "?search=" . urlencode($search_term) : ""));
            exit();
            break;
            
        case 'make_user':
            $user_query = "UPDATE users SET role = 'user' WHERE id = :id";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':id', $user_id);
            if ($user_stmt->execute()) {
                $_SESSION['success'] = "User demoted to regular user!";
            } else {
                $_SESSION['error'] = "Error demoting user.";
            }
            header("Location: manage_users.php" . (!empty($search_term) ? "?search=" . urlencode($search_term) : ""));
            exit();
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-view { background: #17a2b8; color: white; }
        .btn-edit { background: #007bff; color: white; }
        .btn-info { background: #6c757d; color: white; }
        
        .btn-success:hover { background: #218838; }
        .btn-warning:hover { background: #e0a800; }
        .btn-delete:hover { background: #c82333; }
        .btn-view:hover { background: #138496; }
        .btn-edit:hover { background: #0056b3; }
        .btn-info:hover { background: #5a6268; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-badge.admin { background: #d4edda; color: #155724; }
        .status-badge.user { background: #fff3cd; color: #856404; }
        
        .badge.current-user {
            background: #6c757d;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        .text-muted {
            color: #6c757d !important;
            font-style: italic;
        }
        
        /* Search and Filter Styles - FIXED TEXT VISIBILITY */
        .search-filter-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #333333 !important; /* Force dark text color */
            font-weight: 500;
        }
        
        .search-input::placeholder {
            color: #6c757d;
            font-weight: normal;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            background: #ffffff;
            color: #333333 !important; /* Ensure text stays dark on focus */
        }
        
        /* Ensure text is visible in all states */
        .search-input:-webkit-autofill,
        .search-input:-webkit-autofill:hover, 
        .search-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #333333 !important;
            -webkit-box-shadow: 0 0 0px 1000px white inset !important;
            background-color: white !important;
            color: #333333 !important;
        }
        
        .search-btn {
            padding: 12px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-weight: 600;
        }
        
        .search-btn:hover {
            background: #0056b3;
        }
        
        .filter-options {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-label {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            color: #333333;
        }
        
        .reset-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }
        
        .reset-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        .search-results-info {
            background: #e7f3ff;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        
        .search-results-info p {
            margin: 0;
            color: #0056b3;
            font-weight: 500;
        }
        
        /* Mobile Card Layout */
        .mobile-users-list {
            display: none;
            flex-direction: column;
            gap: 15px;
        }
        
        .mobile-user-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        .user-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .user-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .user-detail {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .user-detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .user-detail-value {
            color: #333;
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .admin-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .admin-header h1 {
                font-size: 1.5rem;
                margin-bottom: 5px;
            }
            
            /* Search mobile optimizations */
            .search-box {
                flex-direction: column;
            }
            
            .search-input {
                font-size: 16px; /* Prevent zoom on iOS */
                min-height: 50px; /* Better touch target */
            }
            
            .search-btn {
                min-height: 50px; /* Better touch target */
                font-size: 1rem;
            }
            
            .filter-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .filter-group {
                width: 100%;
                justify-content: space-between;
            }
            
            .filter-select {
                flex: 1;
                max-width: 200px;
                min-height: 40px;
            }
            
            /* Hide table on mobile, show cards */
            .admin-table-container {
                display: none;
            }
            
            .mobile-users-list {
                display: flex;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .action-buttons .btn {
                width: 100%;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
            }
            
            .btn-sm {
                padding: 12px 16px;
                font-size: 0.9rem;
            }
            
            .status-badge {
                font-size: 0.7rem;
                padding: 3px 6px;
            }
            
            .badge.current-user {
                font-size: 0.65rem;
                padding: 2px 6px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 10px 8px;
            }
            
            .admin-header h1 {
                font-size: 1.3rem;
            }
            
            .mobile-user-card {
                padding: 15px;
            }
            
            .user-name {
                font-size: 1rem;
            }
            
            .search-filter-container {
                padding: 15px;
            }
        }
        
        /* Desktop - hide mobile cards */
        @media (min-width: 769px) {
            .mobile-users-list {
                display: none !important;
            }
            
            .admin-table-container {
                display: block;
            }
        }
        
        /* Touch-friendly improvements */
        .btn {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
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
            <p>Manage Users</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="approve_recipes.php"><i class="fas fa-check-circle"></i> Approve Recipes</a></li>
            <li><a href="manage_recipes.php"><i class="fas fa-utensils"></i> Manage Recipes</a></li>
            <li><a href="manage_users.php" class="active"><i class="fas fa-users"></i> Manage Users</a></li>
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
            <h1>Manage Users</h1>
            <p>Manage user accounts and permissions</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="search-filter-container">
            <form method="GET" action="" class="search-box">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search users by name or email..." 
                       value="<?php echo htmlspecialchars($search_term); ?>"
                       aria-label="Search users"
                       style="color: #333333 !important; background: #ffffff !important;">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
            
            <div class="filter-options">
                <div class="filter-group">
                    <span class="filter-label">Filter by Role:</span>
                    <select name="role" class="filter-select" onchange="this.form.submit()" form="filterForm">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo (isset($_GET['role']) && $_GET['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?php echo (isset($_GET['role']) && $_GET['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <span class="filter-label">Quick Stats:</span>
                    <span class="badge" style="background: #17a2b8;">Total: <?php echo $total_users; ?></span>
                    <span class="badge" style="background: #28a745;">Admins: <?php echo $admin_count; ?></span>
                    <span class="badge" style="background: #ffc107;">Users: <?php echo $user_count; ?></span>
                </div>
                
                <?php if (!empty($search_term) || isset($_GET['role'])): ?>
                    <a href="manage_users.php" class="reset-btn">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
            <form id="filterForm" method="GET" style="display: none;">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
            </form>
        </div>

        <?php if (!empty($search_term) || isset($_GET['role'])): ?>
            <div class="search-results-info">
                <p>
                    <i class="fas fa-info-circle"></i>
                    Found <?php echo count($users); ?> user(s)
                    <?php if (!empty($search_term)): ?>
                        matching "<?php echo htmlspecialchars($search_term); ?>"
                    <?php endif; ?>
                    <?php if (isset($_GET['role']) && !empty($_GET['role'])): ?>
                        with role "<?php echo htmlspecialchars($_GET['role']); ?>"
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Desktop Table -->
        <div class="admin-table-container">
            <?php if (count($users) > 0): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($user['name']); ?>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge current-user">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($user['role'] == 'user' && $user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?action=make_admin&id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : ''; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-user-shield"></i> Make Admin
                                        </a>
                                    <?php elseif ($user['role'] == 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?action=make_user&id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : ''; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-user"></i> Make User
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : ''; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Are you sure? This cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Current user</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-users fa-3x"></i>
                    <h3>No Users Found</h3>
                    <p>
                        <?php if (!empty($search_term) || isset($_GET['role'])): ?>
                            No users match your search criteria. Try adjusting your search terms or <a href="manage_users.php">clear filters</a>.
                        <?php else: ?>
                            There are no users in the system yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mobile Cards -->
        <div class="mobile-users-list">
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                    <div class="mobile-user-card">
                        <div class="user-card-header">
                            <h3 class="user-name">
                                <?php echo htmlspecialchars($user['name']); ?>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge current-user">You</span>
                                <?php endif; ?>
                            </h3>
                            <span class="status-badge <?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        
                        <div class="user-details">
                            <div class="user-detail">
                                <span class="user-detail-label">Email:</span>
                                <span class="user-detail-value"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div class="user-detail">
                                <span class="user-detail-label">Joined:</span>
                                <span class="user-detail-value"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <?php if ($user['role'] == 'user' && $user['id'] != $_SESSION['user_id']): ?>
                                <a href="?action=make_admin&id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : ''; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-user-shield"></i> Make Admin
                                </a>
                            <?php elseif ($user['role'] == 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                                <a href="?action=make_user&id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : ''; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-user"></i> Make User
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="?action=delete&id=<?php echo $user['id']; ?>&search=<?php echo urlencode($search_term); ?><?php echo isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : ''; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Are you sure? This cannot be undone.')">
                                    <i class="fas fa-trash"></i> Delete User
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="text-align: center; display: block; padding: 10px;">Current user account</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-users fa-3x"></i>
                    <h3>No Users Found</h3>
                    <p>
                        <?php if (!empty($search_term) || isset($_GET['role'])): ?>
                            No users match your search criteria. Try adjusting your search terms or <a href="manage_users.php">clear filters</a>.
                        <?php else: ?>
                            There are no users in the system yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/main.js"></script>
    <script>
        // Auto-submit role filter when changed
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.querySelector('select[name="role"]');
            if (roleSelect) {
                roleSelect.addEventListener('change', function() {
                    // Update the hidden form and submit it
                    const searchInput = document.querySelector('input[name="search"]');
                    const filterForm = document.getElementById('filterForm');
                    if (searchInput && filterForm) {
                        filterForm.submit();
                    }
                });
            }
            
            // Focus search input on page load if there's a search term
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }
            
            // Force text color for search input (additional safety)
            const forceTextColor = function() {
                const inputs = document.querySelectorAll('.search-input');
                inputs.forEach(input => {
                    input.style.color = '#333333';
                    input.style.backgroundColor = '#ffffff';
                });
            };
            
            forceTextColor();
            setInterval(forceTextColor, 100);
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