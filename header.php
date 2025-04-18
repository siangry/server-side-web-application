<?php
$requiresLogin = isset($requiresLogin) ? $requiresLogin : false;

// Check if user is logged in
if ($requiresLogin && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        .header {
            background-color: #2c3e50;
            color: white;
            padding: 1.2rem 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            gap: 2.0rem;
            align-items: center;
            font-size: 1.2rem;
        }

        .nav-item {
            position: relative;
            white-space: nowrap;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            transition: background-color 0.3s;
            font-size: 1.2rem;
        }

        .nav-link:hover {
            background-color: #34495e;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            display: none;
            z-index: 1000;
        }

        .nav-item:hover .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 0.75rem 1rem;
            color: #2c3e50;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
            font-size: 1.14rem;
        }

        .profile-link:hover {
            background-color: #34495e;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-menu {
                flex-direction: column;
                width: 100%;
                text-align: center;
            }

            .dropdown-menu {
                position: static;
                width: 100%;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-container">
            <a href="view_recipes.php" class="logo">YummiFi</a>
            <nav class="nav-menu">
                <div class="nav-item">
                    <a href="view_recipes.php" class="nav-link">Recipe Management</a>
                    <div class="dropdown-menu">
                        <a href="view_recipes.php" class="dropdown-item">View Recipes</a>
                        <a href="add_new_recipe.php" class="dropdown-item">Add New Recipe</a>
                        <a href="my_recipes.php" class="dropdown-item">My Recipes</a>

                    </div>
                </div>
                <div class="nav-item">
                    <a href="meal_planner_view.php" class="nav-link">Meal Planner</a>
                    <div class="dropdown-menu">
                        <a href="meal_planner_view.php" class="dropdown-item">Meal Planner View</a>
                        <a href="recipe_planner.php" class="dropdown-item">Saved Recipes & Planner</a>
                    </div>
                </div>
                <div class="nav-item">
                    <a href="community.php" class="nav-link">Community</a>
                </div>
                <div class="nav-item">
                    <a href="competition_page.php" class="nav-link">Cooking Competition</a>
                    <div class="dropdown-menu">
                        <a href="competition_page.php" class="dropdown-item">Competition Page</a>
                        <a href="comp_submit_recipe.php" class="dropdown-item">Join Competitions</a>
                        <a href="comp_vote.php" class="dropdown-item">Vote For Favourite</a>
                        <a href="comp_results.php" class="dropdown-item">View Results</a>
                    </div>
                </div>
            </nav>
            <div class="user-profile">
                <a href="profile.php" class="profile-link"><?= htmlspecialchars($_SESSION['username']) ?></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="profile-link">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
</body>

</html>