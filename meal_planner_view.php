<?php
session_start();
$requiresLogin = true;
require 'database.php';
require 'header.php';

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get selected user_id for admin view
$selected_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

// Get list of users for admin dropdown
$users = array();
if ($is_admin) {
    $users_query = "SELECT user_id, username FROM user ORDER BY username";
    $users_stmt = $conn->prepare($users_query);
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    while ($user = $users_result->fetch_assoc()) {
        $users[] = $user;
    }
}

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

function getMealDataForDate($date) {
  global $conn;
  global $selected_user_id;
  
  $mealData = [
    'plan_name' => '',
    'breakfast' => 'not set',
    'lunch' => 'not set',
    'dinner' => 'not set',
    'snack' => 'not set'
  ];
  
  $query = "SELECT mp.plan_name, me.meal_category, cr.cusRecipe_title 
            FROM meal_plan mp 
            LEFT JOIN meal_entry me ON mp.mealPlan_id = me.mealPlan_id 
            LEFT JOIN custom_recipe cr ON me.cusRecipe_id = cr.cusRecipe_id 
            WHERE ? BETWEEN mp.start_date AND mp.end_date
            AND mp.user_id = ?";
            
  $stmt = $conn->prepare($query);
  $stmt->bind_param("si", $date, $selected_user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      if (empty($mealData['plan_name'])) {
        $mealData['plan_name'] = $row['plan_name'];
      }
      if ($row['meal_category'] && $row['cusRecipe_title']) {
        $mealData[$row['meal_category']] = $row['cusRecipe_title'];
      }
    }
  }
  
  return $mealData;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meal Planner View</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <style>
    :root {
      --primary-color: #4a90e2;
      --secondary-color: #f5f5f5;
      --text-color: #333;
      --border-color: #e0e0e0;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--secondary-color);
      color: var(--text-color);
    }

    .view-toggle-container {
      background: white;
      padding: 15px;
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      margin-bottom: 20px;
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    .view-toggle-btn {
      padding: 10px 20px;
      border: 1px solid var(--primary-color);
      background: white;
      color: var(--primary-color);
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      transition: all 0.3s ease;
      min-width: 150px;
    }

    .view-toggle-btn.active {
      background: var(--primary-color);
      color: white;
    }

    .container {
      max-width: 1400px;
      margin: 20px auto;
      padding: 20px;
    }

    .controls {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      margin-bottom: 30px;
    }

    .calendar {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      padding: 20px;
    }

    .calendar-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .week-days {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 10px;
      margin-bottom: 10px;
    }

    .week-day {
      text-align: center;
      font-weight: bold;
      color: var(--primary-color);
      padding: 10px;
    }

    .days-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 10px;
    }

    .day-card {
      aspect-ratio: 1;
      overflow-x: auto;
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .day-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .day-number {
      font-weight: bold;
      margin-bottom: 5px;
    }

    .meal-preview {
      font-size: 0.8rem;
      color: #666;
    }

    .meal-preview div {
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .modal-content {
      border-radius: 15px;
    }

    .meal-detail {
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 8px;
      background: var(--secondary-color);
    }

    .meal-detail h4 {
      color: var(--primary-color);
      margin-bottom: 10px;
    }

    .ingredients-list, .steps-list {
      list-style-type: none;
      padding-left: 0;
    }

    .ingredients-list li, .steps-list li {
      margin-bottom: 5px;
      padding: 5px 10px;
      background: white;
      border-radius: 4px;
    }

    .btn-edit, .btn-delete {
      padding: 5px 10px;
      border-radius: 4px;
      border: none;
      cursor: pointer;
      margin-right: 5px;
    }

    .btn-edit {
      background-color: var(--primary-color);
      color: white;
    }

    .btn-delete {
      background-color: #dc3545;
      color: white;
    }

    .empty-day {
      background: var(--secondary-color);
      border: 1px dashed var(--border-color);
    }

    .current-day {
      border: 2px solid var(--primary-color);
    }

    .meal-cards-container {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-top: 20px;
    }

    .meal-card {
      background: white;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .meal-item {
      display: flex;
      align-items: center;
      padding: 10px;
      border-radius: 8px;
      background: #f8f9fa;
    }
    
    .meal-details {
      display: flex;
      flex-direction: column;
    }
    
    .btn-primary {
      background-color: #007bff;
      border-color: #007bff;
    }
    
    .btn-success {
      background-color: #28a745;
      border-color: #28a745;
    }
    
    .btn-danger {
      background-color: #dc3545;
      border-color: #dc3545;
    }

    .meal-cards-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .meal-card {
      background: white;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .meal-section {
      background: #f8f9fa;
      border-radius: 15px;
      padding: 30px;
      margin: 30px 0;
    }

    .meal-section h2 {
      color: #333;
      font-size: 24px;
      margin-bottom: 25px;
      font-weight: 500;
    }

    .meal-cards-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
    }

    .meal-category-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .meal-category-title {
      color: #007bff;
      font-size: 20px;
      margin-bottom: 20px;
      font-weight: 500;
    }

    .meal-item {
      background: white;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .meal-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .meal-item img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
      margin-right: 15px;
    }

    .meal-details {
      flex: 1;
    }

    .meal-title {
      font-weight: 500;
      color: #333;
      margin-bottom: 5px;
    }

    .meal-date {
      color: #666;
      font-size: 0.9em;
    }

    .add-meal-btn {
      width: 100%;
      padding: 12px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s ease;
    }

    .add-meal-btn:hover {
      background: #0056b3;
    }

    .calendar-header h3,
    .selected-day-details h3,
    #selectedDayTitle {
      color: #333;
      font-size: 24px;
      font-weight: 500;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }

    /* Add styles for admin controls */
    .admin-controls {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 20px;
      padding: 15px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .user-select {
      padding: 8px 15px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 16px;
      min-width: 200px;
    }

    .admin-label {
      font-weight: 500;
      color: #333;
    }
  </style>
</head>
<body>


  <div class="container">
    <?php if ($is_admin): ?>
    <div class="admin-controls">
      <span class="admin-label">View User:</span>
      <select class="user-select" onchange="window.location.href='meal_planner_view.php?user_id=' + this.value + '&style=<?= $_GET['style'] ?? 'daily' ?>'">
        <option value="">Select User</option>
        <?php foreach ($users as $user): ?>
          <option value="<?php echo $user['user_id']; ?>" <?php echo $selected_user_id == $user['user_id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($user['username']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="view-toggle-container">
    <button type="button" class="view-toggle-btn active" onclick="switchView('planner')" style="width: 300px">Meal Planner View</button>
    <button type="button" class="view-toggle-btn" onclick="switchView('recipes')" style="width: 300px">Saved Recipes & Planner</button>
    </div>

    <div class="controls">
      <form method="get" class="row g-3">
        <?php if ($is_admin): ?>
          <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
        <?php endif; ?>
        <div class="col-md-4">
          <label for="style" class="form-label">View Style</label>
          <select name="style" id="style" class="form-select" onchange="updateControls()">
            <option value="daily" <?= ($_GET['style'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
            <option value="weekly" <?= ($_GET['style'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
            <option value="monthly" <?= ($_GET['style'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
            <option value="custom" <?= ($_GET['style'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom Date</option>
          </select>
        </div>
        
        <div class="col-md-4" id="dateControl">
          <label for="date" class="form-label">Date</label>
          <input type="date" name="date" id="date" class="form-control" value="<?= $_GET['date'] ?? date('Y-m-d') ?>">
        </div>

        <div class="col-md-4" id="monthControl" style="display: none;">
          <label for="month" class="form-label">Month</label>
          <select name="month" id="month" class="form-select">
            <?php
            $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            $currentMonth = date('n');
            foreach ($months as $index => $month) {
              $selected = ($_GET['month'] ?? $currentMonth) == ($index + 1) ? 'selected' : '';
              echo "<option value='" . ($index + 1) . "' $selected>$month</option>";
            }
            ?>
          </select>
        </div>

        <div class="col-md-4" id="weekControl" style="display: none;">
          <label for="week" class="form-label">Week</label>
          <div class="week-selection">
            <select name="week" id="week" class="form-select">
              <?php
              $year = $_GET['year'] ?? date('Y');
              $weeks = getWeeksInYear($year);
              $currentWeek = date('W');
              foreach ($weeks as $week => $range) {
                $selected = ($_GET['week'] ?? $currentWeek) == $week ? 'selected' : '';
                echo "<option value='$week' $selected>Week $week ($range)</option>";
              }
              ?>
            </select>
          </div>
        </div>

        <div class="col-md-4" id="customControl" style="display: none;">
            <label class="form-label">Date Range</label>
            <div class="row g-2">
                <div class="col-6">
                <input type="date" name="startDate" id="startDate" class="form-control"
                        value="<?= $_GET['startDate'] ?? date('Y-m-d') ?>" onchange="validateDates()">
                </div>
                <div class="col-6">
                <input type="date" name="endDate" id="endDate" class="form-control"
                        value="<?= $_GET['endDate'] ?? date('Y-m-d') ?>" onchange="validateDates()">
                </div>
            </div>
            <div class="invalid-feedback" id="dateError"></div>
        </div>

        <div class="col-md-4">
          <label for="year" class="form-label">Year</label>
          <select name="year" id="year" class="form-select" onchange="updateYear()">
            <?php
            $currentYear = date('Y');
            for ($i = $currentYear - 1; $i <= $currentYear + 1; $i++) {
              $selected = ($_GET['year'] ?? $currentYear) == $i ? 'selected' : '';
              echo "<option value='$i' $selected>$i</option>";
            }
            ?>
          </select>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Apply</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='meal_planner_view.php<?php echo $is_admin ? '?user_id=' . $selected_user_id : ''; ?>'">
                Reset
            </button>
        </div>
      </form>
    </div>

    <div class="calendar">
      <?php
      $style = $_GET['style'] ?? 'daily';
      $year = $_GET['year'] ?? date('Y');
      $month = $_GET['month'] ?? date('n');
      $week = $_GET['week'] ?? date('W');
      $date = $_GET['date'] ?? date('Y-m-d');
      $startDate = $_GET['startDate'] ?? date('Y-m-d');
      $endDate = $_GET['endDate'] ?? date('Y-m-d');
      $currentDay = date('j');
      $currentMonth = date('n');
      $currentYear = date('Y');

      function formatDate($date) {
        return date('d M Y', strtotime($date));
      }

      function getWeeksInYear($year) {
        $weeks = [];
        
        // Calculate all weeks for the year
        $date = new DateTime();
        $date->setISODate($year, 1, 1); // First day of first week
        $endDate = new DateTime();
        $endDate->setISODate($year + 1, 1, 1); // First day of first week of next year
        $endDate->modify('-1 day'); // Last day of current year
        
        while ($date <= $endDate) {
            $weekNumber = str_pad($date->format('W'), 2, '0', STR_PAD_LEFT);
            $yearOfWeek = $date->format('o'); // ISO year
            
            // Only process if this week belongs to our target year
            if ($yearOfWeek == $year) {
                $startOfWeek = clone $date;
                // Make sure we're at the start of the week (Monday)
                if ($startOfWeek->format('N') != 1) {
                    $startOfWeek->modify('last monday');
                }
                $endOfWeek = clone $startOfWeek;
                $endOfWeek->modify('+6 days');
                
                $weeks[$weekNumber] = formatDate($startOfWeek->format('Y-m-d')) . ' - ' . formatDate($endOfWeek->format('Y-m-d'));
            }
            
            $date->modify('+1 week');
        }
        
        // Handle special case for week 01 of next year if it starts in current year
        $lastDay = new DateTime("$year-12-31");
        $lastWeekNumber = $lastDay->format('W');
        $lastWeekYear = $lastDay->format('o');
        
        if ($lastWeekYear > $year) {
            // Last week belongs to next year (week 01)
            $startOfLastWeek = clone $lastDay;
            $startOfLastWeek->modify('last monday');
            $endOfLastWeek = clone $startOfLastWeek;
            $endOfLastWeek->modify('+6 days');
            
            $weeks['53'] = formatDate($startOfLastWeek->format('Y-m-d')) . ' - ' . formatDate($endOfLastWeek->format('Y-m-d'));
        }
        
        // Handle special case for week 01 if it starts in previous year
        $firstDay = new DateTime("$year-01-01");
        $firstWeekNumber = $firstDay->format('W');
        $firstWeekYear = $firstDay->format('o');
        
        if ($firstWeekYear < $year) {
            // First week belongs to previous year
            $weekOneDate = new DateTime();
            $weekOneDate->setISODate($year, 1, 1);
            $startOfWeekOne = clone $weekOneDate;
            while ($startOfWeekOne->format('N') != 1) {
                $startOfWeekOne->modify('-1 day');
            }
            $endOfWeekOne = clone $startOfWeekOne;
            $endOfWeekOne->modify('+6 days');
            
            $weeks['01'] = formatDate($startOfWeekOne->format('Y-m-d')) . ' - ' . formatDate($endOfWeekOne->format('Y-m-d'));
        }
        
        ksort($weeks);
        return $weeks;
    }

      if ($style === 'daily') {
        $selectedDate = new DateTime($date);
        
        // Get plan name for this date
        $planNameQuery = "SELECT plan_name FROM meal_plan WHERE ? BETWEEN start_date AND end_date AND user_id = ?";
        $stmt = $conn->prepare($planNameQuery);
        $stmt->bind_param("si", $date, $selected_user_id);
        $stmt->execute();
        $planResult = $stmt->get_result();
        $planName = '';
        if ($planResult->num_rows > 0) {
            $planData = $planResult->fetch_assoc();
            $planName = ': ' . $planData['plan_name'];
        }
        
        echo "<div class='calendar-header'>";
        echo "<h3>" . $selectedDate->format('l, d M Y') . $planName . "</h3>";
        echo "</div>";
        
        echo "<div class='meal-cards-container'>";
        $meals = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
        foreach ($meals as $meal) {
          echo renderMealCard($meal, $selectedDate->format('Y-m-d'));
        }
        echo "</div>";

      } elseif ($style === 'weekly') {
        $weekStart = new DateTime();
        $weekStart->setISODate($year, $week);
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');
        
        echo "<div class='calendar-header'>";
        echo "<h3>Week $week: " . formatDate($weekStart->format('Y-m-d')) . " - " . formatDate($weekEnd->format('Y-m-d')) . "</h3>";
        echo "</div>";
        
        echo "<div class='week-days'>";
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        foreach ($dayNames as $dayName) {
          echo "<div class='week-day'>$dayName</div>";
        }
        echo "</div>";
        
        echo "<div class='days-grid'>";
        for ($i = 0; $i < 7; $i++) {
          $currentDate = clone $weekStart;
          $currentDate->modify("+$i days");
          $dayNumber = $currentDate->format('j');
          $isCurrentDay = ($dayNumber == $currentDay && $currentDate->format('n') == $currentMonth && $currentDate->format('Y') == $currentYear);
          $currentDayClass = $isCurrentDay ? 'current-day' : '';
          
          echo "<div class='day-card $currentDayClass' data-date='" . $currentDate->format('Y-m-d') . "' onclick='showDayDetails(this)'>";
          echo "<div class='day-number'>$dayNumber</div>";
          echo "<div class='meal-preview'>";
          $mealData = getMealDataForDate($currentDate->format('Y-m-d'));
          if (!empty($mealData['plan_name'])) {
            echo "<div style='font-weight: bold; color: #007bff; margin-bottom: 5px;'>" . htmlspecialchars($mealData['plan_name']) . "</div>";
          }
          echo "<div>Breakfast: " . htmlspecialchars($mealData['breakfast']) . "</div>";
          echo "<div>Lunch: " . htmlspecialchars($mealData['lunch']) . "</div>";
          echo "<div>Dinner: " . htmlspecialchars($mealData['dinner']) . "</div>";
          echo "<div>Snack: " . htmlspecialchars($mealData['snack']) . "</div>";
          echo "</div>";
          echo "</div>";
          $currentDate->modify('+1 day');
        }
        echo "</div>";

      } elseif ($style === 'monthly') {
        $firstDayOfMonth = date('N', mktime(0, 0, 0, $month, 1, $year));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
        
        echo "<div class='calendar-header'>";
        echo "<h3>$monthName $year</h3>";
        echo "</div>";
        
        echo "<div class='week-days'>";
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        foreach ($dayNames as $dayName) {
          echo "<div class='week-day'>$dayName</div>";
        }
        echo "</div>";
        
        echo "<div class='days-grid'>";
        // Add empty cells for days before the first day of month
        for ($i = 1; $i < $firstDayOfMonth; $i++) {
          echo "<div class='day-card empty-day'></div>";
        }
        
        for ($i = 1; $i <= $daysInMonth; $i++) {
          $isCurrentDay = ($i == $currentDay && $month == $currentMonth && $year == $currentYear);
          $currentDayClass = $isCurrentDay ? 'current-day' : '';
          $date = sprintf('%04d-%02d-%02d', $year, $month, $i);
          
          echo "<div class='day-card $currentDayClass' data-date='$date' onclick='showDayDetails(this)'>";
          echo "<div class='day-number'>$i</div>";
          echo "<div class='meal-preview'>";
          $mealData = getMealDataForDate($date);
          if (!empty($mealData['plan_name'])) {
            echo "<div style='font-weight: bold; color: #007bff; margin-bottom: 5px;'>" . htmlspecialchars($mealData['plan_name']) . "</div>";
          }
          echo "<div>Breakfast: " . htmlspecialchars($mealData['breakfast']) . "</div>";
          echo "<div>Lunch: " . htmlspecialchars($mealData['lunch']) . "</div>";
          echo "<div>Dinner: " . htmlspecialchars($mealData['dinner']) . "</div>";
          echo "<div>Snack: " . htmlspecialchars($mealData['snack']) . "</div>";
          echo "</div>";
          echo "</div>";
        }
        echo "</div>";

      } else {
        // Custom view
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $daysDiff = $start->diff($end)->days + 1;
        
        if ($daysDiff == 1) {
          // Show as daily view
          // Get plan name for this date
          $planNameQuery = "SELECT plan_name FROM meal_plan WHERE ? BETWEEN start_date AND end_date AND user_id = ?";
          $stmt = $conn->prepare($planNameQuery);
          $stmt->bind_param("si", $startDate, $selected_user_id);
          $stmt->execute();
          $planResult = $stmt->get_result();
          $planName = '';
          if ($planResult->num_rows > 0) {
              $planData = $planResult->fetch_assoc();
              $planName = ': ' . $planData['plan_name'];
          }
          
          echo "<div class='calendar-header'>";
          echo "<h3>" . $start->format('l, d M Y') . $planName . "</h3>";
          echo "</div>";
          
          echo "<div class='meal-cards-container'>";
          $meals = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
          foreach ($meals as $meal) {
            echo renderMealCard($meal, $start->format('Y-m-d'));
          }
          echo "</div>";
        } elseif ($daysDiff <= 7) {
          // Show as weekly view
          echo "<div class='calendar-header'>";
          echo "<h3>" . formatDate($startDate) . " - " . formatDate($endDate) . "</h3>";
          echo "</div>";
          
          echo "<div class='week-days'>";
          $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
          foreach ($dayNames as $dayName) {
            echo "<div class='week-day'>$dayName</div>";
          }
          echo "</div>";
          
          echo "<div class='days-grid'>";
          
          // Add empty cells for days before the start date
          $firstDayOfWeek = $start->format('N'); // 1 (Monday) through 7 (Sunday)
          for ($i = 1; $i < $firstDayOfWeek; $i++) {
            echo "<div class='day-card empty-day'></div>";
          }
          
          // Display the actual days
          $currentDate = clone $start;
          for ($i = 0; $i < $daysDiff; $i++) {
            $dayNumber = $currentDate->format('j');
            $isCurrentDay = ($dayNumber == $currentDay && $currentDate->format('n') == $currentMonth && $currentDate->format('Y') == $currentYear);
            $currentDayClass = $isCurrentDay ? 'current-day' : '';
            
            echo "<div class='day-card $currentDayClass' data-date='" . $currentDate->format('Y-m-d') . "' onclick='showDayDetails(this)'>";
            echo "<div class='day-number'>$dayNumber</div>";
            echo "<div class='meal-preview'>";
            $mealData = getMealDataForDate($currentDate->format('Y-m-d'));
            if (!empty($mealData['plan_name'])) {
              echo "<div style='font-weight: bold; color: #007bff; margin-bottom: 5px;'>" . htmlspecialchars($mealData['plan_name']) . "</div>";
            }
            echo "<div>Breakfast: " . htmlspecialchars($mealData['breakfast']) . "</div>";
            echo "<div>Lunch: " . htmlspecialchars($mealData['lunch']) . "</div>";
            echo "<div>Dinner: " . htmlspecialchars($mealData['dinner']) . "</div>";
            echo "<div>Snack: " . htmlspecialchars($mealData['snack']) . "</div>";
            echo "</div>";
            echo "</div>";
            $currentDate->modify('+1 day');
          }        
          
          echo "</div>";
        } else {
          // Show as monthly view
          $firstDayOfWeek = $start->format('N'); // 1 (Monday) through 7 (Sunday)
          
          echo "<div class='calendar-header'>";
          echo "<h3>" . formatDate($startDate) . " - " . formatDate($endDate) . "</h3>";
          echo "</div>";
          
          echo "<div class='week-days'>";
          $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
          foreach ($dayNames as $dayName) {
            echo "<div class='week-day'>$dayName</div>";
          }
          echo "</div>";
          
          echo "<div class='days-grid'>";
          
          // Add empty cells for days before the start date
          for ($i = 1; $i < $firstDayOfWeek; $i++) {
            echo "<div class='day-card empty-day'></div>";
          }
          
          // Display the actual days
          $currentDate = clone $start;
          for ($i = 0; $i < $daysDiff; $i++) {
            $dayNumber = $currentDate->format('j');
            $isCurrentDay = ($dayNumber == $currentDay && $currentDate->format('n') == $currentMonth && $currentDate->format('Y') == $currentYear);
            $currentDayClass = $isCurrentDay ? 'current-day' : '';
            
            echo "<div class='day-card $currentDayClass' data-date='" . $currentDate->format('Y-m-d') . "' onclick='showDayDetails(this)'>";
            echo "<div class='day-number'>$dayNumber</div>";
            echo "<div class='meal-preview'>";
            $mealData = getMealDataForDate($currentDate->format('Y-m-d'));
            if (!empty($mealData['plan_name'])) {
              echo "<div style='font-weight: bold; color: #007bff; margin-bottom: 5px;'>" . htmlspecialchars($mealData['plan_name']) . "</div>";
            }
            echo "<div>Breakfast: " . htmlspecialchars($mealData['breakfast']) . "</div>";
            echo "<div>Lunch: " . htmlspecialchars($mealData['lunch']) . "</div>";
            echo "<div>Dinner: " . htmlspecialchars($mealData['dinner']) . "</div>";
            echo "<div>Snack: " . htmlspecialchars($mealData['snack']) . "</div>";
            echo "</div>";
            echo "</div>";
            $currentDate->modify('+1 day');
          }
          
          echo "</div>";
        }
      }
      ?>
    </div>

    <div class="selected-day-details" id="selectedDayDetails" style="display: none;">
      <section class="meal-section">
        <h2 id="selectedDayTitle"></h2>
        <div class="meal-cards-grid" id="selectedDayMealCards">
          <!-- Meal cards will be loaded here dynamically -->
        </div>
      </section>
    </div>
  </div>

  <!-- Modal for meal details -->
  <div class="modal fade" id="mealModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Meal Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="meal-cards-container">
            <!-- Meal cards will be loaded here dynamically -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function updateControls() {
      const style = document.getElementById('style').value;
      const dateControl = document.getElementById('dateControl');
      const monthControl = document.getElementById('monthControl');
      const weekControl = document.getElementById('weekControl');
      const customControl = document.getElementById('customControl');
      
      dateControl.style.display = style === 'daily' ? 'block' : 'none';
      monthControl.style.display = style === 'monthly' ? 'block' : 'none';
      weekControl.style.display = style === 'weekly' ? 'block' : 'none';
      customControl.style.display = style === 'custom' ? 'block' : 'none';
    }

    function updateYear() {
      const year = document.getElementById('year').value;
      const style = document.getElementById('style').value;
      
      if (style === 'daily') {
        document.getElementById('date').value = year + '-01-01';
      } else if (style === 'weekly') {
        // Reload the page to update week options
        window.location.href = window.location.pathname + '?style=weekly&year=' + year;
      } else if (style === 'custom') {
        document.getElementById('startDate').value = year + '-01-01';
        document.getElementById('endDate').value = year + '-01-01';
      }
    }

    function validateDates() {
      const startDate = document.getElementById('startDate');
      const endDate = document.getElementById('endDate');
      const dateError = document.getElementById('dateError');
      
      if (startDate.value && endDate.value) {
        if (startDate.value > endDate.value) {
          dateError.textContent = 'Start date cannot be later than end date';
          endDate.value = startDate.value;
          dateError.style.display = 'block';
          return false;
        }
      }
      dateError.style.display = 'none';
      return true;
    }

    // Initialize controls on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateControls();
      
      // Add click handlers for day cards
      const dayCards = document.querySelectorAll('.day-card:not(.empty-day)');
      const selectedDayDetails = document.getElementById('selectedDayDetails');
      const selectedDayTitle = document.getElementById('selectedDayTitle');

      dayCards.forEach(card => {
        card.addEventListener('click', function() {
          const date = this.dataset.date;
          
          const formattedDate = selectedDate.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
          });
          
          selectedDayTitle.textContent = `Meals for Day ${formattedDate}`;
          selectedDayDetails.style.display = 'block';
          
          // Scroll to the details section
          selectedDayDetails.scrollIntoView({ behavior: 'smooth' });
        });
      });
    });

    function addMeal(button) {
      const mealType = button.dataset.mealType;
      const date = button.dataset.date;
      // Redirect to saved recipes view
      window.location.href = `saved_recipes.php?meal_type=${mealType}&date=${date}`;
    }

    function changeMeal(button) {
      const mealType = button.dataset.mealType;
      const date = button.dataset.date;
      // Redirect to saved recipes view
      window.location.href = `saved_recipes.php?meal_type=${mealType}&date=${date}`;
    }

    function editMeal(button) {
      const mealType = button.dataset.mealType;
      const date = button.dataset.date;
      // Redirect to recipe edit page
      window.location.href = `edit_recipes.php?meal_type=${mealType}&date=${date}`;
    }

    function deleteMeal(button) {
      const mealType = button.dataset.mealType;
      const date = button.dataset.date;
      if (confirm('Are you sure you want to delete this meal?')) {
        // Make AJAX call to delete meal
        fetch(`delete_meal.php?meal_type=${mealType}&date=${date}`, {
          method: 'POST'
        }).then(response => {
          if (response.ok) {
            // Refresh the page or update the UI
            location.reload();
          }
        });
      }
    }

    // Add this function to handle view switching
    function switchView(view) {
      // Get the user_id from the URL if it exists
      const urlParams = new URLSearchParams(window.location.search);
      const userId = urlParams.get('user_id');
      const userIdParam = userId ? `?user_id=${userId}` : '';
      
      if (view === 'planner') {
          window.location.href = 'meal_planner_view.php' + userIdParam;
      }
      else {
          window.location.href = 'recipe_planner.php' + userIdParam;
      }
    }

    function showDayDetails(dayCard) {
      const date = dayCard.dataset.date;
      const selectedDate = new Date(date);
      
      // Format the date as "DD MMM YYYY"
      const formattedDate = selectedDate.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      });

      const selectedDayDetails = document.getElementById('selectedDayDetails');
      const selectedDayTitle = document.getElementById('selectedDayTitle');
      const selectedDayMealCards = document.getElementById('selectedDayMealCards');
      
      // Get the user_id from the URL if it exists
      const urlParams = new URLSearchParams(window.location.search);
      const userId = urlParams.get('user_id');
      const userIdParam = userId ? `&user_id=${userId}` : '';
      
      // First fetch the plan name for this date
      fetch(`get_plan_name.php?date=${date}${userIdParam}`)
        .then(response => response.json())
        .then(data => {
          const planTitle = data.plan_name ? `: ${data.plan_name}` : '';
          selectedDayTitle.textContent = `Meals for ${formattedDate}${planTitle}`;
          
          // Then fetch the meal cards
          return fetch(`get_meal_cards.php?date=${date}${userIdParam}`);
        })
        .then(response => response.text())
        .then(html => {
          selectedDayMealCards.innerHTML = html;
          selectedDayDetails.style.display = 'block';
          selectedDayDetails.scrollIntoView({ behavior: 'smooth' });
        });
    }
  </script>
</body>
</html>

<?php
require 'footer.php';
?>