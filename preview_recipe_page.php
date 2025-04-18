<?php
session_start();
require 'database.php';
require 'header.php';

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$recipe_id = isset($_GET['recipe_id']) ? intval($_GET['recipe_id']) : 0;
if (!$recipe_id) {
    echo "<h3>❌ No recipe selected.</h3>";
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;

$query = "SELECT r.*, c.category_name, u.username FROM recipe r JOIN recipe_category c ON r.category_id = c.category_id JOIN user u ON r.user_id = u.user_id WHERE r.recipe_id = $recipe_id";
$result = mysqli_query($conn, $query);
if (!$result) {
    die("SQL Error: " . mysqli_error($conn));
}
$recipe = mysqli_fetch_assoc($result);

if (!$recipe) {
    echo "<h3>❌ Recipe not found.</h3>";
    exit();
}

if (!$is_admin && $user_id != $recipe['user_id']) {
    echo "<h3>⛔ You do not have permission to edit this recipe.</h3>";
    exit();
}

$cat_result = mysqli_query($conn, "SELECT * FROM recipe_category");
$success = '';
$error = '';
$editing = isset($_GET['edit']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_admin && isset($_POST['approve'])) {
        //approval status update
        $status_update = "UPDATE recipe SET status='approved', reject_reason=NULL WHERE recipe_id=$recipe_id";
        if (mysqli_query($conn, $status_update)) {
            echo "<script>window.location.href = 'my_recipes.php?reviewed=1';</script>"; //prompt back to my_recipe page to continue review
            exit();
        } else {
            $error = "❌ Failed to approve: " . mysqli_error($conn);
        }

    } elseif ($is_admin && isset($_POST['reject']) && !empty($_POST['reject_reason'])) {
        $reason = mysqli_real_escape_string($conn, $_POST['reject_reason']);
        $status_update = "UPDATE recipe SET status='rejected', reject_reason='$reason' WHERE recipe_id=$recipe_id";
        if (mysqli_query($conn, $status_update)) {
            echo "<script>window.location.href = 'my_recipes.php?reviewed=1';</script>";
            exit();
        } else {
            $error = "❌ Failed to reject: " . mysqli_error($conn);
        }
    } else {
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $cuisine = mysqli_real_escape_string($conn, $_POST['cuisine_type']);
        $category_id = intval($_POST['category_id']);
        $difficulty = mysqli_real_escape_string($conn, $_POST['difficulty']);
        $ingredient = mysqli_real_escape_string($conn, $_POST['ingredient']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $step = mysqli_real_escape_string($conn, $_POST['step']);

        $image = $recipe['recipe_image'];

        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed)) {
                $new_name = uniqid('recipe_', true) . "." . $file_ext;
                $upload_path = 'uploads/' . $new_name;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image = $new_name;
                } else {
                    $error = "❌ Failed to upload image.";
                }
            } else {
                $error = "❌ Invalid image format.";
            }
        }

        if (!$error) {
            $update = "UPDATE recipe SET 
                recipe_title='$title', cuisine_type='$cuisine', category_id=$category_id,
                difficulty='$difficulty', ingredient='$ingredient', recipe_desc='$description',
                step='$step', recipe_image='$image'";

            if (!$is_admin) {
                $update .= ", status='pending', reject_reason=NULL";
            }

            $update .= " WHERE recipe_id=$recipe_id";

            if (mysqli_query($conn, $update)) {
                $refresh = mysqli_query($conn, $query);
                $recipe = mysqli_fetch_assoc($refresh);
                $editing = false;
                $success = "✅ Recipe updated successfully!";
            } else {
                $error = "❌ Update failed: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $editing ? 'Edit Recipe' : 'Preview Recipe' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f8f8; padding: 30px; }
        .container { max-width: 900px; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin: auto; }
        .img-preview { width: 100%; max-height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; }
        .meta strong { width: 100px; display: inline-block; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= $editing ? 'Edit Recipe' : 'Preview Recipe' ?></h2>
        <div>
            <?php if ($editing): ?>
                <a href="?recipe_id=<?= $recipe_id ?>" class="btn btn-secondary">Cancel</a>
            <?php elseif (!$is_admin): ?>
                <a href="?recipe_id=<?= $recipe_id ?>&edit=1" class="btn btn-success">Edit</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <?php if ($editing): ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="title" value="<?= htmlspecialchars($recipe['recipe_title']) ?>" class="form-control mb-3" required>
            <select name="cuisine_type" class="form-control mb-3" required>
                <option value="">-- Select Cuisine --</option>
                <?php foreach (["Malay", "Chinese", "Indian", "Western", "Thai", "Other"] as $type): ?>
                    <option value="<?= $type ?>" <?= $recipe['cuisine_type'] == $type ? 'selected' : '' ?>><?= $type ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category_id" class="form-control mb-3" required>
                <option value="">-- Select Category --</option>
                <?php while ($cat = mysqli_fetch_assoc($cat_result)): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= $recipe['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="difficulty" class="form-control mb-3" required>
                <option value="">-- Select Difficulty --</option>
                <?php foreach (["Easy", "Medium", "Hard"] as $diff): ?>
                    <option value="<?= $diff ?>" <?= $recipe['difficulty'] == $diff ? 'selected' : '' ?>><?= $diff ?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="ingredient" class="form-control mb-3" rows="3" required><?= htmlspecialchars($recipe['ingredient']) ?></textarea>
            <textarea name="description" class="form-control mb-3" rows="2" required><?= htmlspecialchars($recipe['recipe_desc']) ?></textarea>
            <textarea name="step" class="form-control mb-3" rows="4" required><?= htmlspecialchars($recipe['step']) ?></textarea>
            <div class="mb-3">
                <label>Replace Image (optional):</label>
                <input type="file" name="image" class="form-control" accept=".jpeg,.jpg,.png">
            </div>
            <button type="submit" class="btn btn-primary">Update Recipe</button>
        </form>

    <?php else: ?>
        <img src="uploads/<?= htmlspecialchars($recipe['recipe_image']) ?>" alt="Recipe Image" class="img-preview">
        <h3><?= htmlspecialchars($recipe['recipe_title']) ?></h3>
        <hr style="border: none; border-top: 1px solid #ccc;margin: 20px 0;">
        <div class="meta mb-4">
            <div><strong>Cuisine:</strong> <?= htmlspecialchars($recipe['cuisine_type']) ?></div>
            <div><strong>Category:</strong> <?= htmlspecialchars($recipe['category_name']) ?></div>
            <div><strong>Difficulty:</strong> <?= htmlspecialchars($recipe['difficulty']) ?></div>
            <div><strong>Status:</strong> <?= ucfirst($recipe['status']) ?></div>
            <?php if ($recipe['status'] === 'rejected' && !empty($recipe['reject_reason'])): ?>
                <div class="text-danger fst-italic mt-2">❌ Reason: <?= htmlspecialchars($recipe['reject_reason']) ?></div>
            <?php endif; ?>
        </div>
        <h4>Description</h4>
        <p><?= nl2br(htmlspecialchars($recipe['recipe_desc'])) ?></p>
        <h4>Ingredients</h4>
        <ul>
            <?php foreach (preg_split("/(\r\n|\r|\n)/", $recipe['ingredient']) as $ing): ?>
                <li><?= htmlspecialchars(trim($ing)) ?></li>
            <?php endforeach; ?>
        </ul>
        <h4>Preparation Steps</h4>
        <ol>
            <?php foreach (explode("\n", $recipe['step']) as $step): ?>
                <?php $step = preg_replace('/^\d+[\.\)\-]\s*/', '', trim($step)); ?>
                <li><?= htmlspecialchars($step) ?></li>
            <?php endforeach; ?>
        </ol>

        <?php if ($is_admin && $recipe['status'] === 'pending'): ?>
            <form method="POST" class="mt-4">
                <button type="submit" name="approve" class="btn btn-success me-2">Approve</button>
                <button type="button" class="btn btn-danger" onclick="showRejectBox()">Reject</button>

                <div id="reject-box" class="mt-3" style="display:none;">
                    <textarea name="reject_reason" class="form-control mb-2" placeholder="Reject reason..."></textarea>
                    <button type="submit" name="reject" class="btn btn-danger">Confirm Reject</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    //reject box only shown when admin click reject button
    function showRejectBox() {
        document.getElementById("reject-box").style.display = "block";
    }
</script>

</body>
</html>

<?php require 'footer.php'; ?>