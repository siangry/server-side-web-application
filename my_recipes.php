<?php
session_start();
require 'database.php';
require 'header.php';

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Determine user ID to fetch recipes
$user_id = $_SESSION['user_id'];

if ($is_admin) {
    //admin can see all recipes with usernames
    $recipes = mysqli_query($conn, "
        SELECT r.*, c.category_name, u.username
        FROM recipe r
        JOIN recipe_category c ON r.category_id = c.category_id
        JOIN user u ON r.user_id = u.user_id
        ORDER BY r.created_at DESC
    ");
}
else {
    // user only see their recipes
    $uid = $_SESSION['user_id'];
    $recipes = mysqli_query($conn, "
        SELECT r.*, c.category_name, u.username
        FROM recipe r
        JOIN recipe_category c ON r.category_id = c.category_id
        JOIN user u ON r.user_id = u.user_id
        WHERE r.user_id = $user_id
        ORDER BY r.created_at DESC
    ");
}

$pending = $approved = $rejected = [];

while ($row = mysqli_fetch_assoc($recipes)) {
    switch ($row['status']) {
        case 'approved':
            $approved[] = $row;
            break;
        case 'rejected':
            $rejected[] = $row;
            break;
        default:
            $pending[] = $row;
    }
}

function renderSection($title, $recipes, $is_admin, $showReason = false) {
    echo "<h3 style='margin-top: 40px;'>$title (" . count($recipes) . ")</h3>";
    if (empty($recipes)) {
        echo "<p style='color:gray;'>No recipes in this section.</p>";
        return;
    }

    foreach ($recipes as $row): ?>
        <div class="recipe-box">
            <div class="recipe-info">
                <h4><?= htmlspecialchars($row['recipe_title']) ?></h4>
                <div>Category: <?= htmlspecialchars($row['category_name']) ?> | Cuisine: <?= htmlspecialchars($row['cuisine_type']) ?></div>
                <div>By: <?= htmlspecialchars($row['username']) ?></div>
                <div class="status <?= strtolower($row['status']) ?>">
                Status: <?= ucfirst($row['status']) ?>
                </div>
                <?php if ($showReason && !empty($row['reject_reason'])): ?>
                    <div class="reason">❌ Reason: <?= htmlspecialchars($row['reject_reason']) ?></div> 
                <?php endif; ?>
            </div>
            <div class="actions">
                <a href="preview_recipe_page.php?recipe_id=<?= $row['recipe_id'] ?>" class="btn-view"><?= $is_admin ? 'Review' : 'View' ?></a>
                <a href="delete_recipe.php?recipe_id=<?= $row['recipe_id'] ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this recipe?')">Delete</a>
            </div>
        </div>
    <?php endforeach;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Recipes</title>
    <style>
        body {
            font-family: Arial;
            background: #f8f8f8;
            padding: 20px 10px 50px 10px;
            margin: 0;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            padding: 20px;
        }

        .recipe-box {
            background: #ffffff;
            padding: 20px 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            transition: all 0.2s ease-in-out;
        }

        .recipe-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .recipe-info {
            max-width: 70%;
            line-height: 1.6;
        }

        .status {
            margin-top: 5px;
        }

        .reason {
            margin-top: 8px;
        }

        .status.approved { color: green; font-weight: bold; }
        .status.pending { color: orange; font-weight: bold; }
        .status.rejected { color: red; font-weight: bold; }

        .actions {
            margin-left: auto;
        }

        .actions a {
            margin-right: 10px;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-view, .btn-delete {
            display: inline-block;
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 5px;
        }

        .btn-view {
            background-color: #007BFF;
            color: white;
        }

        .btn-delete {
            background-color: crimson;
            color: white;
        }

        .alert {
            max-width: 1000px;
            margin: 20px auto;
        }
    </style>
</head>

<body>
<div class="container">
    <h2><?= $is_admin ? 'All Submitted Recipes' : 'My Recipes' ?></h2> 

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            ✅ Recipe has been deleted!
        </div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>
        <div class="alert alert-danger">
            ❌ Failed to delete the recipe.
        </div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
        <div class="alert alert-warning">
            ⚠️ You are not authorized to delete this recipe.
        </div>
    <?php endif; ?>

    <?php
    renderSection("Pending Approval", $pending, $is_admin);
    renderSection("Approved Recipes", $approved, $is_admin);
    renderSection("Rejected Recipes", $rejected, $is_admin, true);
    ?>
</div>

<script>
    //temporary message that last for 3 seconds 
    setTimeout(() => {
        const alert = document.querySelector(".alert");
        if (alert) alert.style.display = "none";
    }, 3000);
</script>

</body>
</html>

<?php require 'footer.php'; ?>
