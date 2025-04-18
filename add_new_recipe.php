<?php
session_start();
require 'database.php';
require 'header.php';

$success = '';
$error = '';
$form_data = $_POST;

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get selected user_id for admin view
$selected_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

$status =  $is_admin ? 'approved' : 'pending';

$user_id = $_SESSION['user_id'];
$cat_result = mysqli_query($conn, "SELECT * FROM recipe_category");

// Handle reset
if (isset($_POST['reset'])) {
    $form_data = [];
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    $title = trim($_POST['title']);
    $cuisine = $_POST['cuisine_type'];
    if ($cuisine === 'Other' && !empty($_POST['other_cuisine'])) {
        $cuisine = mysqli_real_escape_string($conn, $_POST['other_cuisine']);
    }

    $ingredients = mysqli_real_escape_string($conn, $_POST['ingredient']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $steps = mysqli_real_escape_string($conn, $_POST['step']);
    $difficulty = $_POST['difficulty'];
    $category_id = intval($_POST['category_id']);

    // Validate title
    if (!preg_match("/^[a-zA-Z0-9\s,.\'!\-]{3,100}$/", $title)) {
        $error = "❌ Invalid characters in title.";
    }

    // Image handling
    $image_folder = "uploads/";
    if (!file_exists($image_folder)) mkdir($image_folder, 0777, true);

        $image_name = $_FILES['image']['name'];
        $image_tmp = $_FILES['image']['tmp_name'];
        $image_size = $_FILES['image']['size'];
        $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        // Generate image name based on recipe title
        $clean_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($title)); // clean up
        $timestamp = time(); // to avoid duplicates
        $new_image_name = $clean_title . '_' . $timestamp . '.' . $image_ext;
        $image_path = $image_folder . $new_image_name;

        $allowed_ext = ['jpg', 'jpeg', 'png'];
        $max_size = 2 * 1024 * 1024;

        if (!in_array($image_ext, $allowed_ext)) {
            $error = "❌ Only JPG, JPEG, and PNG files are allowed.";
        } elseif ($image_size > $max_size) {
            $error = "❌ File too large. Max size is 2MB.";
        }
        
        if (!$error && move_uploaded_file($image_tmp, $image_path)) {
            // Insert into DB
            $query = "INSERT INTO recipe (
                user_id, category_id, recipe_title, recipe_desc, cuisine_type,
                recipe_image, ingredient, step, difficulty, status, created_at
            ) VALUES (
                '$user_id', '$category_id', '$title', '$description', '$cuisine',
                '$new_image_name', '$ingredients', '$steps', '$difficulty', '$status', NOW()
            )"; 
            
            if (mysqli_query($conn, $query)) {
                $success = "✅ Recipe submitted successfully!";
                $form_data = [];
            } else {
                $error = "❌ Database Error: " . mysqli_error($conn);
            }
        } elseif (!$error) {
            $error = "❌ Failed to upload image.";
        }
    }
    ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Add New Recipe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width: 700px;">
<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h3 class="mb-0">Add a New Recipe</h3>
        <form method="post">
            <button type="submit" name="reset" value="1" class="btn btn-secondary">Reset All</button>
        </form>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <div style="margin-top: 10px;">
        <a href="my_recipes.php" class="btn btn-outline-primary">View My Recipes</a>
        <a href="add_new_recipe.php" class="btn btn-outline-primary">Add Another Recipe</a>
    </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if(!$success):?>
    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="title" class="form-control mb-3" placeholder="Recipe Title" value="<?= htmlspecialchars($form_data['title'] ?? '') ?>" required>

        <select name="cuisine_type" id="cuisine_select" onchange="toggleOtherInput()" class="form-control mb-3" required>
            <option value="">-- Select Cuisine Type --</option>
            <?php foreach (["Malay", "Chinese", "Indian", "Western", "Thai", "Other"] as $type): ?>
                <option value="<?= $type ?>" <?= ($form_data['cuisine_type'] ?? '') == $type ? 'selected' : '' ?>><?= $type ?></option>
            <?php endforeach; ?>
        </select>

        <div id="other_cuisine_input" style="display:none;" class="mb-3">
            <input type="text" name="other_cuisine" id="other_cuisine" class="form-control" placeholder="Specify cuisine" value="<?= htmlspecialchars($form_data['other_cuisine'] ?? '') ?>">
        </div>

        <select name="category_id" class="form-control mb-3" required>
            <option value="">-- Select Category --</option>
            <?php while ($cat = mysqli_fetch_assoc($cat_result)): ?>
                <option value="<?= $cat['category_id']; ?>" <?= (isset($form_data['category_id']) && $form_data['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <select name="difficulty" class="form-control mb-3" required>
            <option value="">-- Select Difficulty --</option>
            <?php foreach (["Easy", "Medium", "Hard"] as $diff): ?>
                <option value="<?= $diff ?>" <?= ($form_data['difficulty'] ?? '') == $diff ? 'selected' : '' ?>><?= $diff ?></option>
            <?php endforeach; ?>
        </select>

        <textarea name="ingredient" class="form-control mb-3" rows="3" placeholder="Ingredients" required><?= htmlspecialchars($form_data['ingredient'] ?? '') ?></textarea>
        <textarea name="description" class="form-control mb-3" rows="2" placeholder="Short Description" required><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
        <textarea name="step" class="form-control mb-3" rows="4" placeholder="Steps to Cook" required><?= htmlspecialchars($form_data['step'] ?? '') ?></textarea>

        <div class="mb-3">
            <label>Upload Image (JPG/JPEG/PNG only):</label>
            <input type="file" name="image" class="form-control" accept=".jpeg,.jpg,.png" required>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" name="submit" class="btn btn-primary">Submit Recipe</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function toggleOtherInput() {
    const select = document.getElementById("cuisine_select");
    const otherInput = document.getElementById("other_cuisine_input");
    const otherField = document.getElementById("other_cuisine");

    if (select.value === "Other") {
        otherInput.style.display = "block";
        otherField.required = true;
    } else {
        otherInput.style.display = "none";
        otherField.required = false;
    }
}
// call it on page load if needed
toggleOtherInput();
</script>
</body>
</html>
<?php require 'footer.php'; ?>
