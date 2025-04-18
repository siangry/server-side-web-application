<?php
session_start();
require 'database.php';

if (!isset($_GET['recipe_id'])) {
    die("No recipe specified.");
}
$recipe_id = (int) $_GET['recipe_id'];
$sql = "SELECT * FROM recipe WHERE recipe_id = $recipe_id";
$result = $conn->query($sql);
if ($result->num_rows != 1) {
    die("Recipe not found.");
}
$recipe = $result->fetch_assoc();

$user_id = $_SESSION['user_id'];
// Process the comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment = trim($_POST['comment']);

    // Insert the comment into the recipe_comment table
    $stmt = $conn->prepare("INSERT INTO recipe_comment (recipe_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $recipe_id, $user_id, $comment);
    $stmt->execute();
    $stmt->close();

    // Redirect back to the recipes page after submission
    header("Location: recipe_page.php?recipe_id=" . $recipe_id);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Comment on Recipe: <?php echo htmlspecialchars($recipe['recipe_title']); ?></title>
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
            display: inline-block;
            font-size: 2em;
            unicode-bidi: bidi-override;
            margin-bottom: 30px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            color: grey;
            cursor: pointer;
            display: inline-block;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
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
        <!-- Recipe image centered -->
        <img src="uploads/<?php echo htmlspecialchars($recipe['recipe_image']); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_title']); ?>" class="recipe-image">
        
        <form method="post" action="">
            <div>Enter your comment below:</div>
            <textarea name="comment" placeholder="Write your comment here..." required></textarea>
            <div class="button-container">
                <input type="submit" value="Submit">
                <input type="button" value="Cancel" onclick="history.back();">
            </div>
        </form>
    </div>
</div>
</body>
</html>
