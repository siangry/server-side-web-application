<?php
session_start();
require 'database.php';

if (!isset($_GET['recipe_id'])) {
    die("No recipe specified.");
}
$recipe_id = (int) $_GET['recipe_id'];

// Fetch recipe information from recipe table.
$sql = "SELECT * FROM recipe WHERE recipe_id = $recipe_id";
$result = $conn->query($sql);
if ($result->num_rows != 1) {
    die("Recipe not found.");
}
$recipe = $result->fetch_assoc();

// For testing, use an existing user id; replace with session user data later.
$user_id = $_SESSION['user_id'];

// Check if the user has already rated this recipe.
$current_rating = 0;
$stmt_check = $conn->prepare("SELECT rating_id, rating FROM recipe_rating WHERE recipe_id = ? AND user_id = ?");
$stmt_check->bind_param("ii", $recipe_id, $user_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    $stmt_check->bind_result($rating_id, $current_rating);
    $stmt_check->fetch();
}
$stmt_check->close();

// Process the form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (float) $_POST['rating'];
    
    // If the user already rated, update the rating.
    if ($current_rating > 0) {
        $stmt_update = $conn->prepare("UPDATE recipe_rating SET rating = ?, created_at = CURRENT_TIMESTAMP WHERE recipe_id = ? AND user_id = ?");
        $stmt_update->bind_param("dii", $rating, $recipe_id, $user_id);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // If no rating exists, insert a new record.
        $stmt_insert = $conn->prepare("INSERT INTO recipe_rating (recipe_id, user_id, rating) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iid", $recipe_id, $user_id, $rating);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    
    header("Location: recipe_page.php?recipe_id=" . $recipe_id);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rate Recipe: <?php echo htmlspecialchars($recipe['recipe_title']); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
        }

        .container-wrapper {
            min-height: calc(100vh - 80px); /* subtract header height */
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }

        .recipe-image {
            width: 100%;
            max-width: 300px;
            height: auto;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .star-rating {
            direction: rtl;
            font-size: 2em;
            unicode-bidi: bidi-override;
            display: inline-block;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            display: inline-block;
            position: relative;
            width: 1em;
            cursor: pointer;
            color: #ccc;
        }

        .star-rating label::before {
            content: "\2605"; /* Unicode star */
            position: absolute;
            left: 0;
        }

        .star-rating input:checked ~ label::before,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: gold;
        }

        textarea {
            width: 100%;
            height: 100px;
            font-size: 1em;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
            resize: vertical;
        }

        .button-container input[type="submit"],
        .button-container input[type="button"] {
            padding: 12px 24px;
            font-size: 1rem;
            margin: 10px 10px 0 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .button-container input[type="submit"] {
            background-color: #28a745;
            color: white;
        }

        .button-container input[type="submit"]:hover {
            background-color: #218838;
            transform: scale(1.05);
        }

        .button-container input[type="button"] {
            background-color: #dc3545;
            color: white;
        }

        .button-container input[type="button"]:hover {
            background-color: #c82333;
            transform: scale(1.05);
        }

        h2 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<?php require 'header.php'; ?>
<div class="container-wrapper">
    <div class="container">
        <!-- Caption on top -->
        <h2><?php echo htmlspecialchars($recipe['recipe_title']); ?></h2>
        <!-- Display the recipe image in the middle -->
        <img src="uploads/<?php echo htmlspecialchars($recipe['recipe_image']); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_title']); ?>" class="recipe-image">
        
        <form method="post" action="">
            <div style="margin-bottom: 30px;">
                <input type="range" id="rating" name="rating" min="0.5" max="5" step="0.5"
                    value="<?= htmlspecialchars($current_rating ?: 2.5) ?>"
                    style="width: 100%; cursor: pointer;">
            </div>

            <div id="rating-result" style="font-size: 1.2rem; font-weight: bold; margin-bottom: 20px;"></div>

            <div class="button-container">
                <input type="submit" value="Submit">
                <input type="button" value="Cancel" onclick="history.back();">
            </div>
        </form>
    </div>
</div>
<script>
    const slider = document.getElementById('rating');
    const result = document.getElementById('rating-result');

    const messages = {
        1: "Very Bad 😖",
        2: "Poor 😕",
        3: "Average 🙂",
        4: "Good 😃",
        5: "Excellent 🤩"
    };

    function updateSliderMessage(value) {
        const rounded = Math.round(value); // Round 3.5 → 4
        result.innerHTML = `${value} / 5 - ${messages[rounded] || ""}`;
    }

    // Initial load
    updateSliderMessage(slider.value);

    // Update as user moves the slider
    slider.addEventListener('input', function () {
        updateSliderMessage(this.value);
    });
</script>
</body>
</html>
