<?php
session_start();
require 'database.php'; // Database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch recipes available for tagging
$recipesResult = mysqli_query($conn, "SELECT recipe_id, recipe_title FROM recipe WHERE status = 'approved' ORDER BY recipe_title ASC");
$recipeOptions = [];
while ($row = mysqli_fetch_assoc($recipesResult)) {
    $recipeOptions[] = $row;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize inputs
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $user_id = $_SESSION['user_id'];
    
    // Process image upload if provided
    $image = '';
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $target_dir = "uploads/";
        // Generate a unique filename to avoid overwriting (optional)
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Validate file type (allow common image types)
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowed_types)) {
            // Optionally validate file size, etc.
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image = $filename; // Store only filename (or you could store full path)
            } else {
                $error = "Sorry, there was an error uploading your image.";
            }
        } else {
            $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    // Check if there were any errors during file upload
    if (isset($error)) {
        echo "<p style='color:red;'>$error</p>";
    } else {
        // Insert new post record (make sure your post table has an 'image' column)
        $sql = "INSERT INTO post (user_id, title, content, image, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $user_id, $title, $content, $image);
        $stmt->execute();
        $post_id = $stmt->insert_id;
        $stmt->close();
        
        // Process tagging of recipes (if any selected)
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            foreach ($_POST['tags'] as $tag_recipe_id) {
                $tag_recipe_id = intval($tag_recipe_id);
                $sqlTag = "INSERT INTO post_recipe (post_id, recipe_id) VALUES (?, ?)";
                $stmtTag = $conn->prepare($sqlTag);
                $stmtTag->bind_param("ii", $post_id, $tag_recipe_id);
                $stmtTag->execute();
                $stmtTag->close();
            }
        }
        
        // Redirect to the community page after creating the post
        header("Location: community.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Create New Post</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            background: #fff;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        form > div {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        textarea {
            resize: vertical;
            height: 150px;
        }
        input[type="file"] {
            padding: 5px 0;
        }
        .button-container {
            text-align: center;
        }
        .button-container input[type="submit"],
        .button-container input[type="button"] {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .button-container input[type="submit"] {
            background-color: #28a745;
            color: #fff;
        }
        .button-container input[type="submit"]:hover {
            background-color: #218838;
        }
        .button-container input[type="button"] {
            background-color: #dc3545;
            color: #fff;
        }
        .button-container input[type="button"]:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
<?php require 'header.php'; ?>
<div class="container">
    <h1>Create New Post</h1>
    <form action="create_post.php" method="post" enctype="multipart/form-data">
        <div>
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" required>
        </div>
        <div>
            <label for="content">Content:</label>
            <textarea name="content" id="content" required></textarea>
        </div>
        <div>
            <label for="image">Upload Image:</label>
            <input type="file" name="image" id="image" accept="image/*">
        </div>
        <div>
            <label for="tags">Tag Specific Recipes:</label>
            <select name="tags[]" id="tags" multiple size="5">
                <?php foreach ($recipeOptions as $option): ?>
                    <option value="<?= $option['recipe_id'] ?>"><?= htmlspecialchars($option['recipe_title']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Hold Ctrl (Windows) or Command (Mac) to select multiple recipes</small>
        </div>
        <div class="button-container">
            <input type="submit" value="Create Post">
            <input type="button" value="Cancel" onclick="history.back();">
        </div>
    </form>
</div>
<?php require 'footer.php'; ?>
</body>
</html>
