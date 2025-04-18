<?php
session_start();
require 'database.php';
$requiresLogin = false;
require 'header.php';

$user_id = $_SESSION['user_id'];

// get top 3 recipe based on vote count
$top_3_ids_query = "SELECT competition_recipe.compRecipe_id FROM competition_recipe 
                    LEFT JOIN competition_votes ON competition_recipe.compRecipe_id = competition_votes.compRecipe_id 
                    GROUP BY competition_recipe.compRecipe_id 
                    ORDER BY COUNT(vote_id) DESC 
                    LIMIT 3";
$top_3_result = mysqli_query($conn, $top_3_ids_query);

$top_3_ids = [];
while ($row = mysqli_fetch_assoc($top_3_result)) {
    $top_3_ids[] = $row['compRecipe_id'];
}

$top_3_ids_str = implode(',', $top_3_ids);
$top_3_condition = !empty($top_3_ids_str) ? "WHERE compRecipe_id NOT IN ($top_3_ids_str)" : "";

// get remaining recipes 
$recipes_query = "SELECT * FROM competition_recipe $top_3_condition";
$recipes = mysqli_query($conn, $recipes_query);

// get top 3 recipe details
$top_query = "SELECT cr.*, u.username, COUNT(cv.vote_id) as vote_count 
             FROM competition_recipe cr
             JOIN user u ON cr.user_id = u.user_id
             LEFT JOIN competition_votes cv ON cr.compRecipe_id = cv.compRecipe_id
             GROUP BY cr.compRecipe_id
             ORDER BY vote_count DESC
             LIMIT 3";

$top_result = mysqli_query($conn, $top_query);

// get remaining recipe details
$recipes_query = "SELECT cr.*, u.username, COUNT(cv.vote_id) as vote_count 
                 FROM competition_recipe cr
                 JOIN user u ON cr.user_id = u.user_id
                 LEFT JOIN competition_votes cv ON cr.compRecipe_id = cv.compRecipe_id
                 " . ($top_3_condition ? "WHERE cr.compRecipe_id NOT IN ($top_3_ids_str)" : "") . "
                 GROUP BY cr.compRecipe_id
                 ORDER BY vote_count DESC";

$recipes_result = mysqli_query($conn, $recipes_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competition Results</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #333;
        }

        .result-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .result-header {
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

        .result-header h1 {
            color: #006981;
            font-size: 2em;
            margin-bottom: 20px;
        }

        .result-header .text {
            flex: 0 1 65%
        }

        .result-header .image {
            flex: 0 1 35%;
            text-align: right;
        }

        .result-header img {
            max-width: 70%;
            height: auto;
            display: inline-block;
        }

        .result-details {
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

        .result-details .details {
            flex: 1 1 60%;
        }

        .result-details h2 {
            color: #006981;
            margin-bottom: 10px;
        }

        .result-details ul {
            list-style: disc;
            padding-left: 20px;
        }

        .result-content {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .top-entries {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 15px;
        }

        .entry-card {
            background-color: #eefbfe;
            border-radius: 20px;
            padding: 20px;
            width: 32%;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .entry-card.winner {
            position: relative;
        }

        .crown {
            width: 50px;
            height: 30px;
        }

        .rank-badge {
            position: absolute;
            width: 30px;
            height: 30px;
            background-color: #006981;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            right: 0;
            bottom: 0;
            z-index: 10;
        }

        .recipe-img-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
            position: relative;
        }

        .recipe-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #006981;
        }

        .recipe-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .author {
            color: #444;
            margin-bottom: 10px;
        }

        .category {
            display: inline-block;
            background-color: #eefbfe;
            color: #888;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            border: 1px solid #006981;
            margin-bottom: 10px;
        }

        .likes {
            color: #006981;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-weight: bold;
        }

        .likes i {
            color: #006981;
        }

        .like-icon {
            width: 20px;
            height: 20px;
        }

        .list-entries {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }

        .list-card {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 15px;
            padding: 15px;
            border: 2px solid #006981;
        }

        .list-img-container {
            width: 80px;
            height: 80px;
            margin-right: 20px;
            flex-shrink: 0;
        }

        .list-content {
            flex-grow: 1;
        }

        .list-likes {
            color: #006981;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: bold;
            margin-left: 20px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 5px 10px;
            color: #333;
            text-decoration: none;
        }

        .pagination .active {
            font-weight: bold;
        }

        .pagination .prev,
        .pagination .next {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="result-container">
        <section class="result-header">
            <div class="text">
                <h1>Competition Results</h1>
                <p>Check out the latest standings in the Malaysian Main Course Cooking Competition! See who's leading the race to be crowned our Top Malaysian Home Chef. Results are updated in real-time based on public votes.</p>
            </div>
            <div class="image">
                <img src="assets/comp_medal.png" alt="Medal Illustration">
            </div>
        </section>
        <section class="result-details">
            <div class="details">
                <h2>Voting Still Open!</h2>
                <ul>
                    <li>Voting closes on: 25/6/2025</li>
                    <li>Don't forget to <a href="comp_vote.php"><strong>Vote Now</strong></a> and support your favorite dish!</li>
                </ul>
                <br
                    <p>Your vote helps celebrate Malaysian culinary creativity — thank you for supporting our homegrown chefs!</p>
            </div>
            <div class="details">
                <h2>Winners Announcement</h2>
                <ul>
                    <li>Final winners will be announced on: 25/6/2025</li>
                    <li>Prizes await the top 3 voted dishes!</li>
                </ul>

            </div>
            <br
                <p>Want to Climb the Ranks? There's still time! Join the competition if you haven't submitted your recipe yet!</p>
        </section>
    </div>
    <div class="result-content">
        <div class="top-entries">
            <?php
            $rank = 1;
            while ($row = mysqli_fetch_assoc($top_result)) {
                $isWinner = ($rank == 1);
            ?>
                <div class="entry-card <?php echo $isWinner ? 'winner' : ''; ?>">
                    <?php if ($isWinner) { ?>
                        <img src="assets/comp_crown.png" alt="Winner" class="crown">
                    <?php } ?>
                    <div class="recipe-img-container">
                        <img src="<?php echo htmlspecialchars($row['compRecipe_image']); ?>" alt="<?php echo htmlspecialchars($row['comp_title']); ?>" class="recipe-img">
                        <div class="rank-badge"><?php echo $rank; ?></div>
                    </div>
                    <h2 class="recipe-title"><?php echo htmlspecialchars($row['comp_title']); ?></h2>
                    <p class="author"><?php echo htmlspecialchars($row['username']); ?></p>
                    <div class="category"><?php echo htmlspecialchars($row['compRecipe_cat']); ?></div>
                    <div class="likes">
                        <img src="assets/comp_heart_red.png" alt="Votes" class="like-icon">
                        <span><?php echo number_format($row['vote_count']); ?></span>
                    </div>
                </div>
            <?php
                $rank++;
            }
            ?>
        </div>

        <div class="list-entries">
            <?php
            if (mysqli_num_rows($recipes_result) > 0) {
                while ($row = mysqli_fetch_assoc($recipes_result)) {
            ?>
                    <div class="list-card">
                        <div class="list-img-container">
                            <img src="<?php echo htmlspecialchars($row['compRecipe_image']); ?>" alt="<?php echo htmlspecialchars($row['comp_title']); ?>" class="recipe-img">
                        </div>
                        <div class="list-content">
                            <h2 class="recipe-title"><?php echo htmlspecialchars($row['comp_title']); ?></h2>
                            <p class="author"><?php echo htmlspecialchars($row['username']); ?></p>
                            <div class="category"><?php echo htmlspecialchars($row['compRecipe_cat']); ?></div>
                        </div>
                        <div class="list-likes">
                            <img src="assets/comp_heart_red.png" alt="Votes" class="like-icon">
                            <span><?php echo number_format($row['vote_count']); ?></span>
                        </div>
                    </div>
            <?php
                }
            }
            ?>
        </div>
    </div>
</body>
<?php require 'footer.php'; ?>