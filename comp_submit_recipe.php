<?php
session_start();
require 'database.php';
$requiresLogin = true;
require 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $category_arr = $_POST['category'];
    $cleaned_categories = [];
    foreach ($category_arr as $cat) {
        $cleaned_categories[] = mysqli_real_escape_string($conn, $cat);
    }
    $category = implode(", ", $cleaned_categories);

    // check is user already submitted recipe
    $checkQuery = "SELECT COUNT(*) AS total FROM competition_recipe WHERE user_id = '$user_id'";
    $result = mysqli_query($conn, $checkQuery);
    $row = mysqli_fetch_assoc($result);

    if ($row['total'] > 0) {
        echo "<script>alert('You have already submitted a recipe. Only one submission is allowed.'); window.location.href='comp_submit_recipe.php';</script>";
        exit;
    }

    // image upload
    if (!is_dir('competition_uploads/images')) {
        mkdir('competition_uploads/images', 0777, true);
    }
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $image_name = time() . "_" . basename($_FILES['image']['name']);
        $image_path = "competition_uploads/images/" . $image_name;
        move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
    } else {
        $image_path = "";
    }


    // pdf upload
    if (!is_dir('competition_uploads/pdfs')) {
        mkdir('competition_uploads/pdfs', 0777, true);
    }
    if (isset($_FILES['recipe']) && $_FILES['recipe']['error'] === 0) {
        $recipe_name = time() . "_" . basename($_FILES['recipe']['name']);
        $recipe_path = "competition_uploads/pdfs/" . $recipe_name;
        move_uploaded_file($_FILES['recipe']['tmp_name'], $recipe_path);
    } else {
        $pdf_name = "";
    }

    $query = "INSERT INTO competition_recipe (user_id, comp_title, comp_desc, compRecipe_cat, compRecipe_image, compRecipe_file)
            VALUES ('$user_id', '$title', '$description', '$category', '$image_path', '$recipe_path')";

    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Recipe submitted successfully!'); window.location.href='comp_submit_recipe.php';</script>";
    } else {
        echo "<script>alert('Error submitting recipe.'); window.history.back();</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Your Malaysian Main Course Recipe</title>
    <style>
        body {
            background-color: #eefbfe;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 2em;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        h1 {
            color: #006981;
            text-align: center;
            margin-bottom: 0.5em;
        }

        .subtext {
            text-align: center;
            color: #444;
            margin-bottom: 1.5em;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #006981;
        }

        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            border: 2px solid #006981;
            border-radius: 6px;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            height: 100px;
        }

        input[type="file"] {
            display: block;
            margin-top: 10px;
        }

        button {
            margin-top: 20px;
            background-color: #006981;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 30px;
            cursor: pointer;
            display: block;
            width: 100%;
        }

        button:hover {
            background-color: #006981;
        }

        .footer-note {
            margin-top: 20px;
            font-size: 0.9em;
            text-align: center;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Submit Your Malaysian Main Course Recipe</h1>
        <p class="subtext">Think your dish has what it takes to impress? Share your signature Malaysian main course recipe with us and join the competition!</p>

        <form method="post" enctype="multipart/form-data">
            <label for="title">Recipe Title</label>
            <input type="text" id="title" name="title" placeholder="Give your recipe a catchy name!" required>

            <label for="description">Short Description</label>
            <textarea id="description" name="description" placeholder="Describe your dish in a few sentences — what’s special about it?" required></textarea>

            <label for="category">Select Recipe Categories</label>
            <select name="category[]" id="category" multiple required>
                <option value="Rice">Rice</option>
                <option value="Noodles">Noodles</option>
                <option value="Soup">Soup</option>
                <option value="Malay">Malay</option>
                <option value="Chinese">Chinese</option>
                <option value="Indian">Indian</option>
                <option value="Other">Other</option>
            </select>
            <small>Hold CTRL (Windows) or CMD (Mac) to select multiple</small>


            <label for="image">Upload Image</label>
            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png" required>

            <label for="recipe">Upload Recipe (PDF)</label>
            <input type="file" id="recipe" name="recipe" accept=".pdf" required>

            <button type="submit">Submit Recipe</button>
        </form>

        <p class="footer-note">By submitting, you agree that your recipe may be displayed publicly as part of the competition. Let the cooking battle begin!</p>
    </div>
</body>

</html>
<?php require 'footer.php'; ?>