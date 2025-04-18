<?php
require 'database.php';

$search = $_GET['search'] ?? '';
$category_id = $_GET['category'] ?? '';

// prevent sql injection
$search = mysqli_real_escape_string($conn, $search);

$category_id = intval($category_id);

$where = " r.status = 'approved'";

//find those match recipe title
if (!empty($search)) {
    $where .= " AND (r.recipe_title LIKE '%$search%' OR r.cuisine_type LIKE '%$search%')";
}

//show recipe at the filtered category
if (!empty($category_id)) {
    $where .= " AND r.category_id = $category_id";
}

$query = "SELECT r.*, c.category_name 
          FROM recipe r
          JOIN recipe_category c ON r.category_id = c.category_id
          WHERE $where
          ORDER BY r.created_at DESC
          LIMIT 12";

$result = mysqli_query($conn, $query);

// show recipes for search result
if (mysqli_num_rows($result) > 0):
    while ($row = mysqli_fetch_assoc($result)): ?>
        <div class="recipe-card">
            <a href="recipe_page.php?recipe_id=<?= $row['recipe_id']; ?>">
                <img src="uploads/<?= htmlspecialchars($row['recipe_image']); ?>" alt="recipe image">
                <h3><?= htmlspecialchars($row['recipe_title']); ?></h3>
            </a>
            <p class="meta">
                <?= htmlspecialchars($row['cuisine_type']); ?> | <?= htmlspecialchars($row['category_name']); ?>
            </p>
        </div>
    <?php endwhile;
else:
    echo '<p style="text-align: center,;">No matching recipes found.</p>';
endif;
?>
