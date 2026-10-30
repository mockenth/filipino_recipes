<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

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

// Delete recipe
$delete_query = "DELETE FROM recipes WHERE id = :id AND user_id = :user_id";
$delete_stmt = $db->prepare($delete_query);
$delete_stmt->bindParam(':id', $recipe_id);
$delete_stmt->bindParam(':user_id', $user_id);

if ($delete_stmt->execute()) {
    // Delete associated image
    if ($recipe['image'] && file_exists('../' . $recipe['image'])) {
        unlink('../' . $recipe['image']);
    }
    $_SESSION['success'] = "Recipe deleted successfully!";
} else {
    $_SESSION['error'] = "Error deleting recipe.";
}

header("Location: my_recipes.php");
exit();
?>