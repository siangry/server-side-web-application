<?php
require 'database.php';
session_start();

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get selected user_id for admin view
$selected_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

// Get the date from the query parameter
$date = $_GET['date'] ?? date('Y-m-d');

// Function to render meal card
function renderMealCard($meal, $date) {
  global $conn;
  global $selected_user_id;
  global $is_admin;
  $cardHtml = "<div class='meal-card'>";
  
  // Convert meal type to match database format
  $meal_category = strtolower($meal); 
  
  // First check if there's a meal plan for this date
  $mealPlanQuery = "SELECT mp.*, me.mealEntry_id, me.meal_category, me.meal_date, cr.cusRecipe_title, cr.image 
                    FROM meal_plan mp 
                    LEFT JOIN meal_entry me ON mp.mealPlan_id = me.mealPlan_id 
                    LEFT JOIN custom_recipe cr ON me.cusRecipe_id = cr.cusRecipe_id 
                    WHERE ? BETWEEN mp.start_date AND mp.end_date
                    AND mp.user_id = ?";
  $stmt = $conn->prepare($mealPlanQuery);
  $stmt->bind_param("si", $date, $selected_user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  // Organize meals by category
  $meals = array();
  $plan_name = '';
  
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      if (empty($plan_name)) {
        $plan_name = $row['plan_name'];
      }
      if ($row['meal_category'] === $meal_category && $row['cusRecipe_title']) {
        $meals[] = array(
          'title' => $row['cusRecipe_title'],
          'image' => $row['image'],
          'date' => $row['meal_date']
        );
      }
    }
    
    // Display the meal category title with plan name
    $cardHtml .= "<h3 style='color: #007bff;'>{$meal}</h3>";
    
    if (!empty($meals)) {
      // Display each meal for this category
      foreach ($meals as $meal_item) {
        $formattedDate = date('n/j/Y', strtotime($meal_item['date']));
        $encodedTitle = urlencode($meal_item['title']);
        $cardHtml .= "
          <div class='meal-item' style='margin-bottom: 15px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);' 
               onmouseover='this.style.transform=\"translateY(-5px)\"; this.style.boxShadow=\"0 5px 15px rgba(0,0,0,0.2)\";'
               onmouseout='this.style.transform=\"translateY(0)\"; this.style.boxShadow=\"0 2px 4px rgba(0,0,0,0.1)\";'
               onclick='window.location.href=\"recipe_planner.php?filter_title={$encodedTitle}" . ($is_admin ? "&user_id=" . $selected_user_id : "") . "\"'>
            <img src='uploads/{$meal_item['image']}' alt='{$meal_item['title']}' style='width: 100px; height: 100px; object-fit: cover; border-radius: 8px;'>
            <div class='meal-details' style='margin-left: 10px;'>
              <div style='font-weight: 500;'>{$meal_item['title']}</div>
              <div style='color: #666; font-size: 0.9em;'>{$formattedDate}</div>
            </div>
          </div>
        ";
      }
    } else {
      // No meals for this category
      $encodedPlanName = urlencode($plan_name);
      $redirectUrl = !empty($plan_name) ? "recipe_planner.php?filter_plan_name={$encodedPlanName}" . ($is_admin ? "&user_id=" . $selected_user_id : "") . "#meal-plans" : "recipe_planner.php" . ($is_admin ? "?user_id=" . $selected_user_id : "") . "#meal-plans";
      $cardHtml .= "
        <button class='btn btn-primary' onclick='window.location.href=\"{$redirectUrl}\"' style='width: 100%;'>
          Add a meal
        </button>
      ";
    }
  } else {
    // No meal plan exists for this date
    $cardHtml .= "
      <h3 style='color: #007bff;'>{$meal}</h3>
      <button class='btn btn-primary' onclick='window.location.href=\"recipe_planner.php" . ($is_admin ? "?user_id=" . $selected_user_id : "") . "#meal-plans\"' style='width: 100%;'>
        Add a meal
      </button>
    ";
  }
  
  $cardHtml .= "</div>";
  return $cardHtml;
}

// Render meal cards for all meal types
$meals = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
foreach ($meals as $meal) {
  echo renderMealCard($meal, $date);
}
?> 