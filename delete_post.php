<?php
session_start();
require 'database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

if (!isset($_GET['post_id'])) {
    die("No post ID provided.");
}

$post_id = intval($_GET['post_id']);

// 1. Get the image filename
$stmt = $conn->prepare("SELECT image FROM post WHERE post_id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$stmt->bind_result($image);
$stmt->fetch();
$stmt->close();

// 2. Delete the image file if it exists and not empty
if (!empty($image)) {
    $image_path = "uploads/" . $image;
    if (file_exists($image_path)) {
        unlink($image_path); // delete the image
    }
}

// 3. Delete the post (this will also delete post_comment, post_like, post_recipe due to FK constraints)
$stmt = $conn->prepare("DELETE FROM post WHERE post_id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$stmt->close();

// Redirect back to the same page (preserving scroll position if needed)
$redirect = "community.php";
if (isset($_GET['delete_mode'])) {
    $redirect .= "?delete_mode=1";
}
header("Location: $redirect");
exit();
?>
