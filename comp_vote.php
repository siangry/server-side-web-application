<?php
session_start();
require 'database.php';
$requiresLogin = true;
require 'header.php';

$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (isset($_POST['delete_recipe_id']) && $is_admin) {
    $delete_id = mysqli_real_escape_string($conn, $_POST['delete_recipe_id']);

    $delete_votes_query = "DELETE FROM competition_votes WHERE compRecipe_id = '$delete_id'";
    if (!mysqli_query($conn, $delete_votes_query)) {
        echo "Error deleting votes: " . mysqli_error($conn);
    }

    $delete_recipe_query = "DELETE FROM competition_recipe WHERE compRecipe_id = '$delete_id'";
    if (mysqli_query($conn, $delete_recipe_query)) {
        echo "<script>alert('Recipe deleted successfully.');</script>";
    } else {
        echo "Error deleting recipe: " . mysqli_error($conn);
    }
}

if (isset($_POST['vote_recipe_id'])) {
    $recipe_id = $_POST['vote_recipe_id'];
    $today = date('Y-m-d');

    $checkQuery = "SELECT * FROM competition_votes 
                   WHERE user_id = '$user_id' 
                   AND compRecipe_id = '$recipe_id' 
                   AND vote_date = '$today'";
    $checkResult = mysqli_query($conn, $checkQuery);

    if (mysqli_num_rows($checkResult) == 0) {
        $voteQuery = "INSERT INTO competition_votes (user_id, compRecipe_id, vote_date) 
                      VALUES ('$user_id', '$recipe_id', '$today')";
        mysqli_query($conn, $voteQuery);
        echo "<script>alert('Thank you for your vote!');</script>";
    } else {
        echo "<script>alert('You already voted for this recipe today!');</script>";
    }
}

$recipes = mysqli_query($conn, "SELECT * FROM competition_recipe");

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_categories = isset($_GET['category']) && !empty($_GET['category'])
    ? explode(',', $_GET['category'])
    : [];

$conditions = [];

if (!empty($search_term)) {
    $search_term_safe = mysqli_real_escape_string($conn, $search_term);
    $conditions[] = "(comp_title LIKE '%$search_term_safe%' OR comp_desc LIKE '%$search_term_safe%')";
}

if (!empty($selected_categories)) {
    $category_clauses = [];
    foreach ($selected_categories as $cat) {
        $cat_safe = mysqli_real_escape_string($conn, trim($cat));
        $category_clauses[] = "compRecipe_cat LIKE '%$cat_safe%'";
    }
    $conditions[] = '(' . implode(' OR ', $category_clauses) . ')';
}

$recipes_query = "SELECT * FROM competition_recipe";
if (!empty($conditions)) {
    $recipes_query .= " WHERE " . implode(" AND ", $conditions);
}

$recipes = mysqli_query($conn, $recipes_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote For Favourite</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #333;
        }

        .vote-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .vote-header {
            background-color: #eefbfe;
            padding: 30px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
            flex-wrap: nowrap;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .vote-header h1 {
            color: #006981;
            font-size: 2em;
            padding-bottom: 20px;
        }

        .vote-header .text {
            flex: 0 1 65%;
        }

        .vote-header .image {
            flex: 0 1 35%;
            text-align: right;
        }

        .vote-header img {
            max-width: 70%;
            height: auto;
            display: inline-block;
        }

        .vote-rules {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
        }

        .vote-rules .rules {
            flex: 1 1 60%;
        }

        .vote-rules h2 {
            color: #006981;
            margin-bottom: 10px;
        }

        .vote-rules ul {
            list-style: disc;
            padding-left: 20px;
        }

        .vote-content {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .search-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            width: 280px;
            border: 2px solid #006981;
            border-radius: 50px;
            padding: 2px 20px;
        }

        .search-bar input {
            border: none;
            outline: none;
            width: 90%;
            font-size: 16px;
        }

        .search-btn {
            width: 40px;
            height: 40px;
            background-color: transparent;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .search-btn img {
            width: 50px;
            height: 50px;
        }

        .category-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-btn {
            background-color: transparent;
            border: 2px solid #006981;
            border-radius: 50px;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }

        .filter-btn.active {
            background-color: #006981;
            color: #fff;
        }

        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .recipe-card {
            background-color: #eefbfe;
            border-radius: 20px;
            overflow: hidden;
            padding: 20px;
            position: relative;
            transition: all 0.3s;
        }

        .recipe-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #006981;
            overflow: hidden;
            margin: 0 auto;
        }

        .recipe-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .author {
            color: #999;
            font-size: 14px;
            margin-top: 10px;
        }

        .recipe-title {
            font-size: 20px;
            font-weight: bold;
            margin: 5px 0;
        }

        .recipe-description {
            font-size: 14px;
            color: #333;
            margin-bottom: 15px;
        }

        .recipe-tag {
            display: inline-block;
            background-color: transparent;
            border: 2px solid #006981;
            border-radius: 50px;
            padding: 5px 12px;
            font-size: 12px;
            color: #006981;
        }

        .recipe-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            gap: 15px;
        }

        .view-btn,
        .vote-btn,
        .delete-btn {
            background-color: #006981;
            border: none;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            color: white;
            padding: 10px 20px;
            transition: background-color 0.3s ease;
            text-decoration: none;
            font-size: 14px;
        }

        .vote-btn:disabled {
            background-color: #bfbfbf;
            cursor: not-allowed;
        }

        .vote-display {
            padding-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .vote-icon {
            width: 20px;
            height: 20px;
        }
    </style>
</head>

<body>
    <div class="vote-container">
        <section class="vote-header">
            <div class="text">
                <h1>Vote for Your Favourite Malaysian Main Course</h1>
                <p>Help us decide who will be crowned the next Top Chef! Browse through the delicious entries submitted by fellow foodies and cast your vote for the most mouth-watering dish.</p>
            </div>
            <div class="image">
                <img src="assets/comp_vote.png" alt="Voting Illustration">
            </div>
        </section>
        <section class="vote-rules">
            <div class="rules">
                <h2>Voting Rules</h2>
                <ul>
                    <li>You can vote once per day</li>
                    <li>Voting is open until: 25/6/2025</li>
                    <li>Only registered users can vote</li>
                </ul>
                <br
                    <p>Your vote helps celebrate Malaysian culinary creativity — thank you for supporting our homegrown chefs!</p>
            </div>
        </section>
    </div>
    <div class="vote-content">
        <form method="GET" action="">
            <div class="search-container">
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Search" value="<?php echo $search_term; ?>">
                    <button type="submit" class="search-btn">
                        <img src="assets/comp_search_icon.png" alt="Search Icon">
                    </button>
                </div>
            </div>
        </form>
        <div class="category-filters">
            <?php
            $manual_categories = ['Rice', 'Noodles', 'Soup', 'Malay', 'Chinese', 'Indian', 'Other'];
            $selected_categories = isset($_GET['category']) ? explode(',', $_GET['category']) : [];
            ?>
            <?php foreach ($manual_categories as $cat): ?>
                <?php
                $is_selected = in_array($cat, $selected_categories);
                $new_selection = $selected_categories;

                if ($is_selected) {
                    $new_selection = array_diff($selected_categories, [$cat]);
                } else {
                    $new_selection[] = $cat;
                }

                $filter_url = '?category=' . urlencode(implode(',', $new_selection));
                if (!empty($_GET['search'])) {
                    $filter_url .= '&search=' . urlencode($_GET['search']);
                }
                ?>
                <a href="<?php echo $filter_url; ?>" class="filter-btn <?php echo $is_selected ? 'active' : ''; ?>">
                    <?php echo $cat; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="recipe-grid">
            <?php if (mysqli_num_rows($recipes) > 0): ?>
                <?php while ($recipe = mysqli_fetch_assoc($recipes)):
                    // Get user info
                    $author_id = $recipe['user_id'];
                    $author_query = "SELECT username FROM user WHERE user_id = '$author_id'";
                    $author_result = mysqli_query($conn, $author_query);
                    $author = mysqli_fetch_assoc($author_result);

                    // Get vote count
                    $vote_query = "SELECT COUNT(*) as vote_count FROM competition_votes WHERE compRecipe_id = '" . $recipe['compRecipe_id'] . "'";
                    $vote_result = mysqli_query($conn, $vote_query);
                    $vote_data = mysqli_fetch_assoc($vote_result);
                    $vote_count = $vote_data['vote_count'];

                    $user_voted = false;

                    $today = date('Y-m-d');
                    $user_vote_query = "SELECT * FROM competition_votes 
                    WHERE user_id = '$user_id' 
                    AND vote_date = '$today'";
                    $user_vote_result = mysqli_query($conn, $user_vote_query);
                    $user_voted = mysqli_num_rows($user_vote_result) > 0;
                ?>
                    <div class="recipe-card">
                        <div class="recipe-image">
                            <img src="<?php echo htmlspecialchars($recipe['compRecipe_image']); ?>" alt="<?php echo htmlspecialchars($recipe['comp_title']); ?>" class="recipe-img">
                        </div>
                        <p class="author"><?php echo $author['username']; ?></p>
                        <h3 class="recipe-title"><?php echo $recipe['comp_title']; ?></h3>
                        <p class="recipe-description"><?php echo $recipe['comp_desc']; ?></p>
                        <span class="recipe-tag"><?php echo $recipe['compRecipe_cat']; ?></span>
                        <div class="vote-display">
                            <img src="assets/comp_heart_red.png" alt="Vote icon" class="vote-icon">
                            <span class="vote-count"><?php echo $vote_count; ?></span>
                        </div>
                        <div class="recipe-footer">
                            <?php if ($is_admin): ?>
                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this recipe?');" style="margin-top:10px;">
                                    <input type="hidden" name="delete_recipe_id" value="<?php echo $recipe['compRecipe_id']; ?>">
                                    <button type="submit" class="delete-btn" style="background-color: #e55e5b;">Delete</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <input type="hidden" name="vote_recipe_id" value="<?php echo $recipe['compRecipe_id']; ?>">
                                <button type="submit" class="vote-btn" <?php echo $user_voted ? 'disabled' : ''; ?>>
                                    Vote
                                </button>
                            </form>
                            <a href="competition_uploads/pdfs/<?php echo htmlspecialchars(basename($recipe['compRecipe_file'])); ?>" class="view-btn" target="_blank">
                                View Recipe
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No recipes found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
<?php require 'footer.php'; ?>
