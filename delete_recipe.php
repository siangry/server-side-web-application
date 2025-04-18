<?php
session_start();
require 'database.php';

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get selected user_id for admin view
$selected_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

$recipe_id = intval($_GET['recipe_id']);
$user_id = $_SESSION['user_id'] ?? null;

//unauthorized delete is not allowed
$check = mysqli_query($conn, "SELECT * FROM recipe WHERE recipe_id = $recipe_id AND user_id = $user_id");
if (mysqli_num_rows($check) === 0) {
    header("Location: my_recipes.php?error=unauthorized");
    exit();
}

$delete = mysqli_query($conn, "DELETE FROM recipe WHERE recipe_id = $recipe_id");

if ($delete) {
    header("Location: my_recipes.php?deleted=1");
} else {
    header("Location: my_recipes.php?error=delete_failed");
}
exit();
?>
