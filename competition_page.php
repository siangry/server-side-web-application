<?php
session_start();
require 'database.php';
$requiresLogin = false;
require 'header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Malaysian Main Course Challenge</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #333;
        }

        .competition-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .competition-header {
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

        .competition-header h1 {
            color: #006981;
            font-size: 2em;
            margin-bottom: 20px;
        }

        .competition-header .text {
            flex: 0 1 65%;
        }

        .competition-header .image {
            flex: 0 1 35%;
            text-align: right;
        }

        .competition-header img {
            max-width: 70%;
            height: auto;
            display: inline-block;
        }

        .competition-details {
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

        .competition-details .details {
            flex: 1 1 60%;
        }

        .competition-details h2 {
            color: #006981;
            margin-bottom: 10px;
        }

        .competition-details ul {
            list-style: disc;
            padding-left: 20px;
        }

        .competition-details .prize-info {
            text-align: center;
            flex: 1 1 35%;
        }

        .competition-details .prize-info img {
            max-width: 30%;
        }

        .actions {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .action-box {
            background-color: #fff;
            border-radius: 12px;
            padding: 20px;
            flex: 1 1 30%;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .action-box img {
            max-width: 50px;
            margin-bottom: 15px;
        }

        .action-box p {
            font-size: 0.95em;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            background-color: #006981;
            color: #fff;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #006981;
        }
    </style>
</head>

<body>
    <div class="competition-container">
        <section class="competition-header">
            <div class="text">
                <h1>Ultimate Malaysian Main Course Challenge</h1>
                <p>Show off your culinary creativity and stand a chance to be crowned the Top Chef of the Month! Whether
                    you're cooking up Nasi Lemak, Rendang, or a twist on Mee Goreng, we want to see your best.</p>
            </div>
            <div class="image">
                <img src="assets/comp_pan.png" alt="Pan Illustration">
            </div>
        </section>

        <section class="competition-details">
            <div class="details">
                <h2>Competition Details</h2>
                <ul>
                    <li><strong>Theme:</strong> Malaysian Main Course</li>
                    <li><strong>Submission Deadline:</strong> 25/5/2025</li>
                    <li><strong>Voting Period:</strong> 1/4/2025 - 25/6/2025</li>
                    <li><strong>Winner Announcement:</strong> 25/6/2025</li>
                </ul>
            </div>
            <div class="prize-info">
                <img src="assets/comp_medal.png" alt="Medal Icon">
                <p>Prizes await the top 3 dishes with the most votes!</p>
            </div>
        </section>

        <section class="actions">
            <div class="action-box">
                <img src="assets/comp_menu_icon.png" alt="Menu Icon">
                <p>Got a mouth-watering main course recipe? Share it with the world!</p>
                <a href="comp_submit_recipe.php" class="btn">Submit Your Recipe</a>
            </div>

            <div class="action-box">
                <img src="assets/comp_vote_icon.png" alt="Vote Icon">
                <p>Support your favorite recipe!<br>You can vote once per day, so keep coming back and help crown the
                    winner.</p>
                <a href="comp_vote.php" class="btn">Vote for Your Favourite Dish</a>
            </div>

            <div class="action-box">
                <img src="assets/comp_ranking.png" alt="Results Icon">
                <p>Check out the leading dishes and see who's cooking up a storm in the competition!</p>
                <a href="comp_results.php" class="btn">Current Results</a>
            </div>
        </section>
    </div>
</body>

</html>
<?php require 'footer.php'; ?>