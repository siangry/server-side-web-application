<?php
session_start();
require 'database.php';
require 'header.php';

//get search and filter parameters
$search = $_GET['search'] ?? '';
$filter_category = $_GET['category_id'] ?? '';

//fetch all categories
$category_result = mysqli_query($conn, "SELECT * FROM recipe_category ORDER BY category_name ASC");

//pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

//count no. of recipes and total pages
$countQuery = "SELECT COUNT(*) as total FROM recipe r WHERE r.status = 'approved'";
$countResult = mysqli_query($conn, $countQuery);
$total = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($total / $limit);

//fetch recipes
$query = "SELECT r.*, c.category_name 
          FROM recipe r
          JOIN recipe_category c ON r.category_id = c.category_id
          WHERE r.status = 'approved'
          ORDER BY r.created_at DESC
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query) or die("SQL Error: " . mysqli_error($conn));
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Recipes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .title-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px;
        }
        .title-bar h1 {
            margin: 0;
        }
        .title-bar span {
            color: rgb(81, 77, 77);
        }
        .search-container {
            display: flex;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 999px;
            padding: 5px 10px;
            background: white;
            width: fit-content;
        }
        .search-container input[type="text"] {
            border: none;
            outline: none;
            padding: 8px;
            font-size: 16px;
            border-radius: 999px;
        }
        .search-container:focus-within {
            border-color: #007BFF;
        }
        .search-container button {
            border: none;
            background: none;
            cursor: pointer;
            font-size: 18px;
            color: tomato;
            padding: 0 8px;
        }
        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .recipe-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 12px;
            text-align: center;
        }
        .recipe-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }
        .recipe-card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 6px;
        }
        .recipe-card h3 {
            margin: 10px 0 5px;
            font-size: 18px;
        }
        .recipe-card .meta {
            font-size: 13px;
            color: #666;
        }
        .recipe-card a {
            text-decoration: none;
            color: #000;
        }
        .pagination {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
            gap: 8px;
        }
        .pagination a {
            text-decoration: none;
        }
        .pagination button {
            min-width: 40px;
            padding: 6px 12px;
            font-size: 14px;
            border: none;
            border-radius: 4px;
            background-color: #eee;
            cursor: pointer;
        }
        .pagination button:hover {
            background-color: #ccc;
        }
        .pagination button[style*="font-weight:bold;"] {
            background-color: #007BFF;
            color: white;
        }
    </style>
</head>
<body>

<div class="title-bar">
    <h1>Recipes <span>(<?= $total ?>)</span></h1>
    <form id="searchForm" method="get" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
    <select name="category_id" id="categorySelect" style= "padding: 6px 12px; border-radius: 6px; max-width: 160px; border: 1px solid rgba(194, 195, 196, 0.79); cursor: pointer;">
            <option value="" >All Categories</option>
            <?php while ($cat = mysqli_fetch_assoc($category_result)): ?>
                <option value="<?= $cat['category_id'] ?>" <?= $filter_category == $cat['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <div class="search-container">
            <input type="text" id="searchInput" name="search" placeholder="Search Recipes..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">🔍</button>
        </div>
    </form>
</div>

<div id="initialResults" class="recipe-grid">
    <?php if (mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <div class="recipe-card">
                <a href="recipe_page.php?recipe_id=<?= $row['recipe_id']; ?>">
                    <img src="uploads/<?= htmlspecialchars($row['recipe_image']); ?>" alt="recipe image">
                    <h3><?= htmlspecialchars($row['recipe_title']); ?></h3>
                </a>
                <p class="meta">
                    <?= htmlspecialchars($row['cuisine_type']); ?> | <?= htmlspecialchars($row['difficulty']); ?>
                </p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No matching recipes found.</p>
    <?php endif; ?>
</div>

<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?search=<?= urlencode($search) ?>&category_id=<?= $filter_category ?>&page=<?= $page - 1 ?>">
            <button><< Prev</button>
        </a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?search=<?= urlencode($search) ?>&category_id=<?= $filter_category ?>&page=<?= $i ?>">
            <button <?= $i == $page ? 'style="font-weight:bold;"' : '' ?>><?= $i ?></button>
        </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="?search=<?= urlencode($search) ?>&category_id=<?= $filter_category ?>&page=<?= $page + 1 ?>">
            <button>Next >></button>
        </a>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const categorySelect = document.getElementById('categorySelect');
    const searchForm = document.getElementById('searchForm');

    function fetchFilteredRecipes() {
        const search = searchInput.value;
        const category = categorySelect.value;

        //AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `ajax_search.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}`);
        xhr.onload = function () {
            const resultDiv = document.getElementById('initialResults');
            resultDiv.innerHTML = xhr.status === 200 ? xhr.responseText : '<p>Error loading recipes.</p>';
        };
        xhr.send();
    }

    searchInput.addEventListener('keyup', fetchFilteredRecipes);
    categorySelect.addEventListener('change', fetchFilteredRecipes);
    searchForm.addEventListener('submit', function (e) {
        e.preventDefault();
        fetchFilteredRecipes();
    });
});
</script>

</body>
</html>

<?php require 'footer.php'; ?>
