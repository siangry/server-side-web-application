<?php
session_start();
require 'database.php';
require 'header.php';

if (!isset($_GET['recipe_id'])) {
    echo "<h3>❌ No recipe selected.</h3>";
    exit();
}

$recipe_id = intval($_GET['recipe_id']);
$user_id = $_SESSION['user_id'] ?? null;

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

//fetch the recipe and its category
$query = "SELECT r.*, c.category_name 
          FROM recipe r
          JOIN recipe_category c ON r.category_id = c.category_id
          WHERE r.recipe_id = $recipe_id";

$result = mysqli_query($conn, $query);
$recipe = mysqli_fetch_assoc($result);

if (!$recipe) {
    echo "<div class='alert alert-danger'>❌ Recipe not found.</div>";
    exit();
}

// Calculate average rating and count from recipe_rating table
$ratingQuery = "SELECT AVG(rating) AS avg_rating, COUNT(*) AS num_ratings 
                FROM recipe_rating 
                WHERE recipe_id = $recipe_id";
$result_rating = mysqli_query($conn, $ratingQuery);
if ($row_rating = mysqli_fetch_assoc($result_rating)) {
    $avg_rating = round($row_rating['avg_rating'], 1);
    $num_ratings = $row_rating['num_ratings'];
} else {
    $avg_rating = 0;
    $num_ratings = 0;
}

// Save recipe
if (isset($_POST['save_recipe'])) {
    $recipe_id = (int)$_POST['save_recipe_id'];

    if ($user_id) {
        $stmt = $conn->prepare("SELECT r.*, c.category_name 
          FROM recipe r
          JOIN recipe_category c ON r.category_id = c.category_id
          WHERE r.recipe_id = $recipe_id");
        $stmt->execute();
        $result = $stmt->get_result();
        $recipe = $result->fetch_assoc();

        if ($recipe) {
            // Check for duplicate in custom_recipe 
            $check = $conn->prepare("
                SELECT cusRecipe_id FROM custom_recipe 
                WHERE cusRecipe_title = ? AND step = ? AND description = ? AND user_id = ?
            ");
            $check->bind_param("sssi", 
                $recipe['recipe_title'], 
                $recipe['step'], 
                $recipe['recipe_desc'], 
                $user_id
            );
            $check->execute();
            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0) {
                // Already exist
                $duplicate_message = "You have already saved this recipe to your custom recipes.";
            } else {
                // Not yet exist — copy image first
                $originalImage = 'uploads/' . $recipe['recipe_image'];
                $copiedImageName = $recipe['recipe_image']; // fallback to original

                if (file_exists($originalImage)) {
                    $ext = pathinfo($recipe['recipe_image'], PATHINFO_EXTENSION);
                    $base = pathinfo($recipe['recipe_image'], PATHINFO_FILENAME);
                    $copiedImageName = $base . '_copy_' . time() . '.' . $ext;
                    $destinationPath = 'uploads/' . $copiedImageName;

                    copy($originalImage, $destinationPath);
                }

                // Insert with copied image filename
                $insert = $conn->prepare("
                    INSERT INTO custom_recipe (cusRecipe_title, image, cuisine_type, ingredient, description, step, save_record, user_id)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $insert->bind_param(
                    "ssssssi",
                    $recipe['recipe_title'],
                    $copiedImageName,
                    $recipe['cuisine_type'],
                    $recipe['ingredient'],
                    $recipe['recipe_desc'],
                    $recipe['step'],
                    $user_id
                );
                $insert->execute();

                $message = "Recipe successfully saved to your custom recipes!";
            }
        } else {
            $message = "Recipe not found.";
        }
    }
}

?>


?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($recipe['recipe_title']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f8f8;
            padding: 0;
            margin: 0;
            color: #333;
        }
        .container {
            max-width: 900px;
            background: #fff;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        img {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .meta {
            font-size: 15px;
            color: #444;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .meta div {
            margin-bottom: 4px;
        }
        .meta strong {
            min-width: 80px;
            display: inline-block;
            color: #222;
        }
        .recipe_info {
            display: flex;
            gap: 60px; 
            margin-bottom: 20px;
            font-size: 19px;
            color: #333;
            flex-wrap: wrap; 
        }
        .recipe_info span {
            white-space: nowrap; 
        }
        .colon {
            width: 10px;
            display: inline-block;
            text-align: right;
            margin-right: 6px;
        }
        h3 {
            margin-top: 30px;
            color: #222;
        }
        ul, ol {
            padding-left: 20px;
            margin-top: 10px;
        }
        li {
            line-height: 1.6;
        }
        p {
            line-height: 1.7;
        }
        a.back {
            display: inline-block;
            margin-bottom: 30px;
            text-decoration: none;
            color: #007BFF;
            font-weight: bold;
            transition: color 0.2s ease;
        }
        a.back:hover {
            color: #0056b3;
        }
        .breakline{
            border: none; 
            border-top: 1px solid #ccc;
            margin: 20px 0;
        }

        .save-button-section {
            margin-top: 10px;
            padding-top: 10px;
            padding-bottom: 10px;
            text-align: left; /* or center/right based on layout */
        }

        .save-button-section button {
            padding: 10px 20px;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .save-button-section button:hover {
            background-color: #2980b9;
        }

        .alert {
            padding: 12px 20px;
            margin-top: 10px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            display: inline-block;
            max-width: 100%;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease-in-out;
        }
        .alert-success {
            background-color: #eafaf1;
            color: #2d7a46;
            border: 1px solid #bde5d1;
        }

        .alert-warning {
            background-color: #fff8e6;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        /* Styles for Average Rating and Comments Sections */
        .section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .section button {
            padding: 10px 20px;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        .section button:hover {
            background-color: #2980b9;
        }
        .comment {
            background-color: #f9f9f9;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .comment strong {
            font-size: 1.05rem;
            color: #333;
        }
        .comment em {
            font-size: 0.85rem;
            color: #777;
        }
    </style>
</head>
<body>
<div class="container">
    <a class="back" href="view_recipes.php">← Back to Browse Recipes</a>
    <img src="uploads/<?= htmlspecialchars($recipe['recipe_image']) ?>" alt="Recipe Image">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
    <h1 style="margin: 0; font-size: 26px; font-weight: bold;">
        <?= htmlspecialchars($recipe['recipe_title']) ?>
    </h1>
    </div>

    <div class = "save-button-section">
        <form method="post">
        <input type="hidden" name="save_recipe_id" value="<?= $recipe_id ?>">
        <button type="submit" name="save_recipe">
            Save the Recipe
        </button>
        </form>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($duplicate_message)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($duplicate_message) ?></div>
    <?php endif; ?>

    <hr class="breakline">

    <div class="recipe_info">
        <span><strong>Cuisine:</strong> <?= htmlspecialchars($recipe['cuisine_type']) ?></span>
        <span><strong>Category:</strong> <?= htmlspecialchars($recipe['category_name']) ?></span>
        <span><strong>Difficulty:</strong> <?= htmlspecialchars($recipe['difficulty']) ?></span>
    </div>

    <h3>Description</h3>
    <p><?= nl2br(htmlspecialchars($recipe['recipe_desc'])) ?></p>

    <h3>Ingredients</h3>
    <ul>
    <?php
    $ingredients_raw = $recipe['ingredient'];
    $ingredients = strpos($ingredients_raw, "\n") !== false //split using \n or , to looks clean 
        ? explode("\n", $ingredients_raw) 
        : explode(",", $ingredients_raw);

    foreach ($ingredients as $ingredient):
        $trimmed = trim($ingredient); //trim blank spaces
        if ($trimmed): 
    ?>
        <li><?= htmlspecialchars($trimmed) ?></li>
    <?php endif;
    endforeach;
    ?>
    </ul>

    <h3>Preparation Steps</h3>
    <ol>
    <?php
    $steps = explode("\n", $recipe['step']);
    foreach ($steps as $step):
        $step = trim($step);
        $step = preg_replace('/^\d+[\.\)\-]\s*/', '', $step);  //removes digits, any dot, closed parenthesis, or dash infront
        echo "<li>" . htmlspecialchars($step) . "</li>"; //consistent numbering
    endforeach;
    ?>
    </ol>

    <!-- Average Rating Section -->
    <div class="section">
        <h3>Ratings</h3>
        <p style="font-size: 1.5rem;">
            <?php if ($num_ratings > 0): ?>
                <?= $avg_rating ?> / 5.0 (<?= $num_ratings ?> reviews)
            <?php else: ?>
                No ratings yet.
            <?php endif; ?>
        </p>
        <button onclick="window.location.href='rating.php?recipe_id=<?= $recipe_id ?>'">
            Rate This Recipe
        </button>
    </div>

    <!-- Comments Section -->
    <div class="section">
        <h3>Comments</h3>

        <?php
        // Pagination setup
        $commentsPerPage = 5;
        $currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $offset = ($currentPage - 1) * $commentsPerPage;

        // Count total comments for pagination
        $totalQuery = "SELECT COUNT(*) AS total FROM recipe_comment WHERE recipe_id = $recipe_id";
        $totalResult = mysqli_query($conn, $totalQuery);
        $totalRow = mysqli_fetch_assoc($totalResult);
        $totalComments = $totalRow['total'];
        $totalPages = ceil($totalComments / $commentsPerPage);

        // Fetch paginated comments
        $commentQuery = "
            SELECT c.comment, c.created_at, u.username, u.user_id 
            FROM recipe_comment c 
            JOIN user u ON c.user_id = u.user_id 
            WHERE c.recipe_id = $recipe_id 
            ORDER BY c.created_at DESC 
            LIMIT $commentsPerPage OFFSET $offset
        ";

        $result_comment = mysqli_query($conn, $commentQuery);

        if (mysqli_num_rows($result_comment) > 0) {
            while($comment = mysqli_fetch_assoc($result_comment)) {
                $comment_user_id = $comment['user_id'];

                // Get rating for this comment's user
                $rating_result = mysqli_query($conn, "
                    SELECT rating FROM recipe_rating 
                    WHERE recipe_id = $recipe_id AND user_id = $comment_user_id
                    LIMIT 1
                ");
                $rating_row = mysqli_fetch_assoc($rating_result);
                $user_rating = $rating_row ? $rating_row['rating'] : null;

                echo '<div class="comment">';
                echo '<div style="display: flex; justify-content: space-between; font-weight: bold;">';
                echo '<span>' . htmlspecialchars($comment['username']) . '</span>';
                echo '<em style="font-weight: normal; color: #777;">' . $comment['created_at'] . '</em>';
                echo '</div>';
                if ($user_rating !== null) {
                    echo '<div style="font-size: 0.95rem; color: #555; margin-bottom: 5px;">Rating: ' . number_format($user_rating, 1) . '</div>';
                }
                echo '<p style="margin: 0;">' . nl2br(htmlspecialchars($comment['comment'])) . '</p>';
                echo '</div>';
            }
        } else {
            echo '<p>No comments yet.</p>';
        }
        ?>

        <!-- Pagination Links -->
        <div style="margin-top: 15px; text-align: center;">
            <?php if ($totalPages > 1): ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="recipe_page.php?recipe_id=<?= $recipe_id ?>&page=<?= $i ?>" 
                    style="margin: 0 5px; text-decoration: none; <?= ($i == $currentPage ? 'font-weight: bold; color: #007BFF;' : '') ?>">
                    <?= $i ?>
                    </a>
                <?php endfor; ?>
            <?php endif; ?>
        </div>

        <!-- Button to add new comment -->
        <button onclick="window.location.href='comment.php?recipe_id=<?= $recipe_id ?>'">
            Comment on This Recipe
        </button>
    </div>
</div>
</body>
</html>

<?php require 'footer.php'; ?>
s