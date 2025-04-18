<?php
session_start();
$requiresLogin = true;
require 'database.php';
require 'header.php';

$success = '';
$error = '';

// Get the target user_id (either from admin selection or current user)
$target_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

// Verify admin permissions if trying to insert for another user
if ($target_user_id != $_SESSION['user_id'] && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    header("Location: recipe_planner.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cusRecipe_title = $_POST['title'];
    $cuisine_type = $_POST['cuisine_type'];
    $ingredient = $_POST['ingredient'];
    $description = $_POST['description'];
    $step = $_POST['step'];

    $imageName = null;

    // === Image Upload Check ===
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        $fileType = $_FILES['image']['type'];
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        // Check both MIME type and file extension
        if (in_array($fileType, $allowedTypes) && in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            $imageName = uniqid('img_') . '.' . $fileExtension;
            $targetPath = 'uploads/' . $imageName;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $error = "Failed to save image file.";
            }
        } else {
            $error = "Only JPEG, JPG, or PNG files are allowed. Detected type: " . $fileType;
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("INSERT INTO custom_recipe 
            (cusRecipe_title, cuisine_type, ingredient, description, step, save_record, user_id, image) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");

        $stmt->bind_param("sssssis", $cusRecipe_title, $cuisine_type, $ingredient, $description, $step, $target_user_id, $imageName);

        if ($stmt->execute()) {
            $success = "✅ Recipe inserted successfully!";
        } else {
            $error = "❌ Failed to insert: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Insert Custom Recipe</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container" style="max-width: 700px;">
    <div class="mb-4" style="margin-top: 50px;">
      <a href="recipe_planner.php<?php echo isset($_GET['user_id']) ? '?user_id=' . $_GET['user_id'] : ''; ?>" class="btn btn-outline-primary">← Back to Recipe Planner</a>
    </div>
    <h3 class="mb-4">Add Custom Recipe</h3>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="mb-3"><input type="text" name="title" class="form-control" placeholder="Recipe Title" required></div>
      <div class="mb-3"><input type="text" name="cuisine_type" class="form-control" placeholder="Cuisine Type" required></div>
      <div class="mb-3"><textarea name="ingredient" class="form-control" rows="3" placeholder="Ingredients" required></textarea></div>
      <div class="mb-3"><textarea name="description" class="form-control" rows="2" placeholder="Short Description" required></textarea></div>
      <div class="mb-3"><textarea name="step" class="form-control" rows="4" placeholder="Steps to Cook" required></textarea></div>
      <div class="mb-3">
        <label>Upload Image (JPG/JPEG/PNG only):</label>
        <input type="file" name="image" class="form-control" accept=".jpeg,.jpg,.png">
      </div>
      <button class="btn btn-primary" style="margin-bottom: 50px; margin-top: 10px;">Insert Recipe</button>
    </form>
  </div>
</body>
</html>

<?php
require'footer.php';
?>
