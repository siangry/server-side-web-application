<?php
session_start();
$requiresLogin = true;
require 'database.php';

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

// Get plans for the selected user
$plans_dropdown = $conn->prepare("SELECT mealPlan_id, plan_name FROM meal_plan WHERE user_id = ? ORDER BY plan_name");
$plans_dropdown->bind_param("i", $selected_user_id);
$plans_dropdown->execute();
$available_plans = $plans_dropdown->get_result();
$plans = array();
while ($plan = $available_plans->fetch_assoc()) {
    $plans[] = $plan;
}

// Handle fetching updated plan data
if (isset($_POST['fetch_plan_data'])) {
    $plan_id = $_POST['plan_id'];
    
    // Verify the plan exists
    $verify_query = "SELECT user_id FROM meal_plan WHERE mealPlan_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $plan_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    
    if (!$verify_data) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'not_found']);
        exit();
    }
    
    // Check if user is admin or owns the plan
    if (!$is_admin && $verify_data['user_id'] != $_SESSION['user_id']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'unauthorized']);
        exit();
    }
    
    // Fetch updated plan data
    $plans_query = "SELECT mp.*, me.mealEntry_id, me.meal_category, me.meal_date, cr.cusRecipe_title, cr.image 
                    FROM meal_plan mp 
                    LEFT JOIN meal_entry me ON mp.mealPlan_id = me.mealPlan_id 
                    LEFT JOIN custom_recipe cr ON me.cusRecipe_id = cr.cusRecipe_id
                    WHERE mp.mealPlan_id = ?";
    
    $plans_stmt = $conn->prepare($plans_query);
    $plans_stmt->bind_param("i", $plan_id);
    $plans_stmt->execute();
    $result = $plans_stmt->get_result();
    
    // Organize the results
    $plan_data = array(
        'plan_name' => '',
        'start_date' => '',
        'end_date' => '',
        'meals' => array(
            'breakfast' => array(),
            'lunch' => array(),
            'dinner' => array(),
            'snack' => array()
        )
    );
    
    while ($row = $result->fetch_assoc()) {
        if (empty($plan_data['plan_name'])) {
            $plan_data['plan_name'] = $row['plan_name'];
            $plan_data['start_date'] = $row['start_date'];
            $plan_data['end_date'] = $row['end_date'];
        }
        if ($row['cusRecipe_title']) {
            $category = strtolower($row['meal_category']);
            $plan_data['meals'][$category][] = array(
                'mealEntry_id' => $row['mealEntry_id'],
                'title' => $row['cusRecipe_title'],
                'image' => $row['image'],
                'date' => $row['meal_date']
            );
        }
    }
    
    // Generate HTML for the plan card
    $html = '
    <div class="card-content">
        <h3 class="plan-title">' . htmlspecialchars($plan_data['plan_name']) . '</h3>
        <p class="plan-date">
            ' . date('M d, Y', strtotime($plan_data['start_date'])) . ' 
            - ' . date('M d, Y', strtotime($plan_data['end_date'])) . '
        </p>';
    
    foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $meal_type) {
        $html .= '
        <div class="meal-type">
            <h4>' . ucfirst($meal_type) . '</h4>';
        
        if (empty($plan_data['meals'][$meal_type])) {
            $html .= '<p class="no-meal">No ' . $meal_type . ' planned</p>';
        } else {
            foreach ($plan_data['meals'][$meal_type] as $meal) {
                $html .= '
                <div class="meal-item">
                    <img src="uploads/' . htmlspecialchars($meal['image']) . '" 
                        alt="' . htmlspecialchars($meal['title']) . '" 
                        class="meal-thumbnail">
                    <div class="meal-info">
                        <p class="meal-title">' . htmlspecialchars($meal['title']) . '</p>
                        <p class="meal-date">' . date('M d, Y', strtotime($meal['date'])) . '</p>
                    </div>
                </div>';
            }
        }
        $html .= '</div>';
    }
    
    $html .= '
        </div>
        <div class="plan-actions">
            <button class="btn-edit-plan" onclick="handleEditPlan(event, \'' . $plan_id . '\')">Edit Plan</button>
            <button class="btn-delete-plan" onclick="handleDeletePlan(event, \'' . $plan_id . '\')">Delete Plan</button>
        </div>';
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'html' => $html]);
    exit();
}

// Handle meal entry deletion
if (isset($_POST['delete_meal_entry'])) {
    $mealEntry_id = $_POST['mealEntry_id'];
    $plan_id = $_POST['plan_id'];
    
    // Verify the meal entry exists
    $verify_query = "SELECT mp.user_id 
                    FROM meal_entry me 
                    JOIN meal_plan mp ON me.mealPlan_id = mp.mealPlan_id 
                    WHERE me.mealEntry_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $mealEntry_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    
    if (!$verify_data) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'not_found']);
        exit();
    }
    
    // Check if user is admin or owns the plan
    if (!$is_admin && $verify_data['user_id'] != $_SESSION['user_id']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'unauthorized']);
        exit();
    }
    
    // Delete the meal entry
    $delete_stmt = $conn->prepare("DELETE FROM meal_entry WHERE mealEntry_id = ?");
    $delete_stmt->bind_param("i", $mealEntry_id);
    
    if ($delete_stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'delete_failed']);
        exit();
    }
}

// Handle delete action
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    
    // First, verify the recipe exists
    $verify_query = "SELECT image, user_id FROM custom_recipe WHERE cusRecipe_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $delete_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    
    if (!$verify_data) {
        header("Location: recipe_planner.php?error=not_found" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    // Check if user is admin or owns the recipe
    if (!$is_admin && $verify_data['user_id'] != $_SESSION['user_id']) {
        header("Location: recipe_planner.php?error=unauthorized" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    // Delete from meal_entry first (foreign key constraint)
    $delete_meals = $conn->prepare("DELETE FROM meal_entry WHERE cusRecipe_id = ?");
    $delete_meals->bind_param("i", $delete_id);
    $delete_meals->execute();

    // Then delete the recipe
    $delete_stmt = $conn->prepare("DELETE FROM custom_recipe WHERE cusRecipe_id = ?");
    $delete_stmt->bind_param("i", $delete_id);
    
    if ($delete_stmt->execute()) {
        // Delete the image file if it exists
        if ($verify_data && $verify_data['image']) {
            $image_path = 'uploads/' . $verify_data['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        header("Location: recipe_planner.php" . ($is_admin && isset($_GET['user_id']) ? "?user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    exit();
}

// Handle plan deletion
if (isset($_POST['delete_plan'])) {
    $plan_id = $_POST['plan_id'];
    
    // Verify the plan exists
    $verify_query = "SELECT user_id FROM meal_plan WHERE mealPlan_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $plan_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    
    if (!$verify_data) {
        header("Location: recipe_planner.php?error=not_found" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    // Check if user is admin or owns the plan
    if (!$is_admin && $verify_data['user_id'] != $_SESSION['user_id']) {
        header("Location: recipe_planner.php?error=unauthorized" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    // First delete all meal entries for this plan
    $delete_meals_stmt = $conn->prepare("DELETE FROM meal_entry WHERE mealPlan_id = ?");
    $delete_meals_stmt->bind_param("i", $plan_id);
    $delete_meals_stmt->execute();
    
    // Then delete the plan itself
    $delete_plan_stmt = $conn->prepare("DELETE FROM meal_plan WHERE mealPlan_id = ?");
    $delete_plan_stmt->bind_param("i", $plan_id);
    if ($delete_plan_stmt->execute()) {
        header("Location: recipe_planner.php?success=delete" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    } else {
        header("Location: recipe_planner.php?error=delete_failed" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
}

// Handle edit action
if (isset($_POST['edit_id'])) {
    $edit_id = $_POST['edit_id'];
    
    // Verify the recipe exists
    $verify_query = "SELECT user_id FROM custom_recipe WHERE cusRecipe_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $edit_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    
    if (!$verify_data) {
        header("Location: recipe_planner.php?error=not_found" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    // Check if user is admin or owns the recipe
    if (!$is_admin && $verify_data['user_id'] != $_SESSION['user_id']) {
        header("Location: recipe_planner.php?error=unauthorized" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    $title = $_POST['title'];
    $cuisine_type = $_POST['cuisine_type'];
    $ingredient = $_POST['ingredient'];
    $description = $_POST['description'];
    $step = $_POST['step'];
    $current_time = date('Y-m-d H:i:s');

    // Handle image upload if a new image is provided
    $image_update = "";
    $image_params = array();
    if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] === 0) {
        $allowed = array('jpg', 'jpeg', 'png');
        $filename = $_FILES['new_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/' . $new_filename;
            
            if (move_uploaded_file($_FILES['new_image']['tmp_name'], $upload_path)) {
                $image_update = ", image = ?";
                $image_params[] = $new_filename;
                
                // Delete old image if it exists
                $old_image_query = "SELECT image FROM custom_recipe WHERE cusRecipe_id = ?";
                $old_image_stmt = $conn->prepare($old_image_query);
                $old_image_stmt->bind_param("i", $edit_id);
                $old_image_stmt->execute();
                $old_image_result = $old_image_stmt->get_result();
                if ($old_image = $old_image_result->fetch_assoc()) {
                    $old_image_path = 'uploads/' . $old_image['image'];
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
            }
        }
    }

    $update_query = "UPDATE custom_recipe SET cusRecipe_title = ?, cuisine_type = ?, ingredient = ?, description = ?, step = ?, modify_record = ?" . $image_update . " WHERE cusRecipe_id = ?";
    $params = array($title, $cuisine_type, $ingredient, $description, $step, $current_time);
    $params = array_merge($params, $image_params, array($edit_id));
    
    $types = str_repeat("s", 6) . (empty($image_update) ? "" : "s") . "i";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param($types, ...$params);
    
    if ($update_stmt->execute()) {
        header("Location: recipe_planner.php" . ($is_admin && isset($_GET['user_id']) ? "?user_id=" . $_GET['user_id'] : ""));
        exit();
    } else {
        header("Location: recipe_planner.php?error=update_failed" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
}

// Handle adding recipe to plan
if (isset($_POST['add_to_plan'])) {
    $plan_id = $_POST['plan_id'];
    $recipe_id = $_POST['recipe_id'];
    $meal_type = $_POST['meal_type'];
    
    // Verify the recipe exists
    $verify_recipe = $conn->prepare("SELECT user_id FROM custom_recipe WHERE cusRecipe_id = ?");
    $verify_recipe->bind_param("i", $recipe_id);
    $verify_recipe->execute();
    $recipe_result = $verify_recipe->get_result();
    $recipe_data = $recipe_result->fetch_assoc();
    
    if (!$recipe_data) {
        header("Location: recipe_planner.php?error=not_found" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    // Check if user is admin or owns the recipe
    if (!$is_admin && $recipe_data['user_id'] != $_SESSION['user_id']) {
        header("Location: recipe_planner.php?error=unauthorized" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    if ($plan_id === 'new') {
        // Check for duplicate plan name
        $plan_name = trim($_POST['new_plan_name']);
        $check_duplicate = $conn->prepare("SELECT COUNT(*) as count FROM meal_plan WHERE plan_name = ? AND user_id = ?");
        $check_duplicate->bind_param("si", $plan_name, $selected_user_id);
        $check_duplicate->execute();
        $result = $check_duplicate->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            header("Location: recipe_planner.php?error=duplicate_name" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
            exit();
        }
        
        // Check for date overlap
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        $check_overlap = $conn->prepare("SELECT COUNT(*) as count FROM meal_plan 
            WHERE user_id = ? AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ? 
            OR ? BETWEEN start_date AND end_date OR ? BETWEEN start_date AND end_date)");
        $check_overlap->bind_param("issssss", $selected_user_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        $check_overlap->execute();
        $overlap_result = $check_overlap->get_result();
        $overlap_count = $overlap_result->fetch_assoc()['count'];
        
        if ($overlap_count > 0) {
            header("Location: recipe_planner.php?error=date_overlap" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
            exit();
        }
        
        // Create new plan
        $create_plan_stmt = $conn->prepare("INSERT INTO meal_plan (plan_name, start_date, end_date, user_id) VALUES (?, ?, ?, ?)");
        $create_plan_stmt->bind_param("sssi", $plan_name, $start_date, $end_date, $selected_user_id);
        if ($create_plan_stmt->execute()) {
            $plan_id = $conn->insert_id;
        } else {
            header("Location: recipe_planner.php?error=create_failed" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
            exit();
        }
    } else {
        // Verify the plan belongs to the selected user
        $verify_plan = $conn->prepare("SELECT user_id FROM meal_plan WHERE mealPlan_id = ?");
        $verify_plan->bind_param("i", $plan_id);
        $verify_plan->execute();
        $plan_result = $verify_plan->get_result();
        $plan_data = $plan_result->fetch_assoc();
        
        if (!$plan_data || $plan_data['user_id'] != $selected_user_id) {
            header("Location: recipe_planner.php?error=unauthorized" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
            exit();
        }
    }

    // Add recipe to plan
    $current_time = date('Y-m-d H:i:s');
    $add_recipe_stmt = $conn->prepare("INSERT INTO meal_entry (meal_category, meal_date, mealPlan_id, cusRecipe_id) VALUES (?, ?, ?, ?)");
    $add_recipe_stmt->bind_param("ssii", $meal_type, $current_time, $plan_id, $recipe_id);
    
    if ($add_recipe_stmt->execute()) {
        header("Location: recipe_planner.php?success=1" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    } else {
        header("Location: recipe_planner.php?error=add_failed" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
}

// Handle update plan dates
if (isset($_POST['update_plan_dates'])) {
    $plan_id = $_POST['plan_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $plan_name = $_POST['plan_name'];
    
    // Verify the plan belongs to the user or user is admin
    $verify_query = "SELECT user_id FROM meal_plan WHERE mealPlan_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $plan_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    
    if (!$verify_data || (!$is_admin && $verify_data['user_id'] != $_SESSION['user_id'])) {
        header("Location: recipe_planner.php?error=unauthorized" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    // Check for duplicate plan name if it's being changed
    if (isset($_POST['plan_name'])) {
        $check_duplicate = $conn->prepare("SELECT COUNT(*) as count FROM meal_plan WHERE plan_name = ? AND mealPlan_id != ? AND user_id = ?");
        $check_duplicate->bind_param("sii", $plan_name, $plan_id, $verify_data['user_id']);
        $check_duplicate->execute();
        $result = $check_duplicate->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            header("Location: recipe_planner.php?error=duplicate_name" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
            exit();
        }
    }
    
    // Check for date overlap with other plans
    $check_overlap = $conn->prepare("SELECT COUNT(*) as count FROM meal_plan 
        WHERE user_id = ? AND mealPlan_id != ? AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ? 
        OR ? BETWEEN start_date AND end_date OR ? BETWEEN start_date AND end_date)");
    $check_overlap->bind_param("iissssss", $verify_data['user_id'], $plan_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
    $check_overlap->execute();
    $overlap_result = $check_overlap->get_result();
    $overlap_count = $overlap_result->fetch_assoc()['count'];
    
    if ($overlap_count > 0) {
        header("Location: recipe_planner.php?error=date_overlap" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    $update_query = "UPDATE meal_plan SET plan_name = ?, start_date = ?, end_date = ? WHERE mealPlan_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssi", $plan_name, $start_date, $end_date, $plan_id);
    
    if ($update_stmt->execute()) {
        header("Location: recipe_planner.php#meal-plans" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    } else {
        header("Location: recipe_planner.php?error=update_failed" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
}



// Handle filter and sort
$filter_title = isset($_GET['filter_title']) ? $_GET['filter_title'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'save_record';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
$selected_meal = isset($_GET['meal_title']) ? $_GET['meal_title'] : '';

// Add PHP variables for plan filtering and sorting at the top with other filter variables
$filter_plan_name = isset($_GET['filter_plan_name']) ? $_GET['filter_plan_name'] : '';
$plan_sort_by = isset($_GET['plan_sort_by']) ? $_GET['plan_sort_by'] : 'start_date';
$plan_sort_order = isset($_GET['plan_sort_order']) ? $_GET['plan_sort_order'] : 'asc';

// Get the most recent end date from existing plans
$latest_end_date_query = "SELECT MAX(end_date) as latest_end_date FROM meal_plan WHERE user_id = ?";
$latest_end_date_stmt = $conn->prepare($latest_end_date_query);
$latest_end_date_stmt->bind_param("i", $selected_user_id);
$latest_end_date_stmt->execute();
$latest_end_date_result = $latest_end_date_stmt->get_result();
$latest_end_date = $latest_end_date_result->fetch_assoc()['latest_end_date'];

// If there are no existing plans, use today's date
$default_start_date = $latest_end_date ? date('Y-m-d', strtotime($latest_end_date . ' +1 day')) : date('Y-m-d');
$default_end_date = date('Y-m-d', strtotime($default_start_date . ' +7 days'));

// Build the query
$query = "SELECT * FROM custom_recipe WHERE user_id = ?";
$params = array($selected_user_id);
$types = "i";

if (!empty($filter_title) || !empty($selected_meal)) {
    $query .= " AND (";
    if (!empty($filter_title)) {
        $query .= "cusRecipe_title LIKE ?";
        $params[] = "%$filter_title%";
        $types .= "s";
    }
    if (!empty($selected_meal)) {
        if (!empty($filter_title)) {
            $query .= " OR ";
        }
        $query .= "cusRecipe_title = ?";
        $params[] = $selected_meal;
        $types .= "s";
    }
    $query .= ")";
}

$query .= " ORDER BY $sort_by $sort_order";

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch existing plans with their recipes
$plans_query = "SELECT mp.*, me.mealEntry_id, me.meal_category, me.meal_date, cr.cusRecipe_title, cr.image 
                FROM meal_plan mp 
                LEFT JOIN meal_entry me ON mp.mealPlan_id = me.mealPlan_id 
                LEFT JOIN custom_recipe cr ON me.cusRecipe_id = cr.cusRecipe_id
                WHERE mp.user_id = ?";

if (!empty($filter_plan_name)) {
    $plans_query .= " AND mp.plan_name LIKE ?";
}

$plans_query .= " ORDER BY mp." . $plan_sort_by . " " . $plan_sort_order;

$plans_stmt = $conn->prepare($plans_query);
if (!empty($filter_plan_name)) {
    $search_term = "%$filter_plan_name%";
    $plans_stmt->bind_param("is", $selected_user_id, $search_term);
} else {
    $plans_stmt->bind_param("i", $selected_user_id);
}
$plans_stmt->execute();
$result = $plans_stmt->get_result();

// Organize the results by plan
$organized_plans = array();
while ($row = $result->fetch_assoc()) {
    $plan_id = $row['mealPlan_id'];
    if (!isset($organized_plans[$plan_id])) {
        $organized_plans[$plan_id] = array(
            'plan_name' => $row['plan_name'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'meals' => array(
                'breakfast' => array(),
                'lunch' => array(),
                'dinner' => array(),
                'snack' => array()
            )
        );
    }
    if ($row['cusRecipe_title']) {
        $category = strtolower($row['meal_category']);
        $organized_plans[$plan_id]['meals'][$category][] = array(
            'mealEntry_id' => $row['mealEntry_id'],
            'title' => $row['cusRecipe_title'],
            'image' => $row['image'],
            'date' => $row['meal_date']
        );
    }
}

// Add at the beginning of PHP section, before any HTML output
if (isset($_POST['update_plan_dates'])) {
    $plan_id = $_POST['plan_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $plan_name = $_POST['plan_name'];
    
    // Verify the plan belongs to the user or user is admin
    $verify_query = "SELECT user_id FROM meal_plan WHERE mealPlan_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $plan_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    
    if (!$verify_data || (!$is_admin && $verify_data['user_id'] != $_SESSION['user_id'])) {
        header("Location: recipe_planner.php?error=unauthorized" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    // Check for duplicate plan name if it's being changed
    if (isset($_POST['plan_name'])) {
        $check_duplicate = $conn->prepare("SELECT COUNT(*) as count FROM meal_plan WHERE plan_name = ? AND mealPlan_id != ? AND user_id = ?");
        $check_duplicate->bind_param("sii", $plan_name, $plan_id, $verify_data['user_id']);
        $check_duplicate->execute();
        $result = $check_duplicate->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            header("Location: recipe_planner.php?error=duplicate_name" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
            exit();
        }
    }
    
    // Check for date overlap with other plans
    $check_overlap = $conn->prepare("SELECT COUNT(*) as count FROM meal_plan 
        WHERE user_id = ? AND mealPlan_id != ? AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ? 
        OR ? BETWEEN start_date AND end_date OR ? BETWEEN start_date AND end_date)");
    $check_overlap->bind_param("iissssss", $verify_data['user_id'], $plan_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
    $check_overlap->execute();
    $overlap_result = $check_overlap->get_result();
    $overlap_count = $overlap_result->fetch_assoc()['count'];
    
    if ($overlap_count > 0) {
        header("Location: recipe_planner.php?error=date_overlap" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
    
    $update_query = "UPDATE meal_plan SET plan_name = ?, start_date = ?, end_date = ? WHERE mealPlan_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssi", $plan_name, $start_date, $end_date, $plan_id);
    
    if ($update_stmt->execute()) {
        header("Location: recipe_planner.php#meal-plans" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    } else {
        header("Location: recipe_planner.php?error=update_failed" . ($is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""));
        exit();
    }
}

// Add error message display at the top of the page
if (isset($_GET['error'])) {
    echo '<div class="error-message" style="background-color: #ffebee; color: #c62828; padding: 10px; margin: 10px 0; border-radius: 4px; text-align: center;">';
    switch($_GET['error']) {
        case 'duplicate_name':
            echo "A meal plan with this name already exists. Please choose a different name.";
            break;
        case 'date_overlap':
            echo "The selected date range overlaps with an existing meal plan. Please choose different dates.";
            break;
        case 'unauthorized':
            echo "You do not have permission to perform this action. Please make sure you are logged in and own the recipe or plan you are trying to modify.";
            break;
        default:
            echo "An error occurred. Please try again.";
    }
    echo '</div>';
}

if (isset($_GET['success'])) {
    echo '<div class="success-message" style="background-color: #e8f5e9; color: #2e7d32; padding: 10px; margin: 10px 0; border-radius: 4px; text-align: center;">';
    echo "Meal plan created successfully!";
    echo '</div>';
}

require 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Recipe & Planner</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        .nav-menu {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            font-size: 1.2rem;
        }

        .header {
            background-color: #2c3e50;
            color: white;
            padding: 1.4rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .body-container {
            padding: 20px;
            background-color: #f5f5f5;
        }

        .body-container footer {
            margin: -20px;
            padding: 0;
        }

        .section {
            margin-bottom: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: margin 0.5s ease-in-out;
        }

        .section:not(.expanded) {
            margin-bottom: 10px;
        }

        .section-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: border-radius 0.5s ease-in-out;
        }

        .section.expanded .section-header {
            border-radius: 10px 10px 0 0;
        }

        .section-header h2 {
            font-size: 1.5rem;
            color: #333;
        }

        .section-content {
            padding: 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: all 0.5s ease-in-out;
        }

        .section-content.expanded {
            padding: 20px;
            max-height: 5000px; /* Large enough to fit content */
            opacity: 1;
        }

        .card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            margin: 10px;
            position: relative;
            display: flex;
            flex-direction: column;
            height: 600px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .card-image-container {
            width: 100%;
            height: 180px;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            transition: transform 0.3s ease;
        }

        .card:hover .card-image {
            transform: scale(1.05);
        }

        .card-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .card-scroll-content {
            overflow-y: auto;
            padding-right: 10px;
            flex-grow: 1;
            scrollbar-width: thin;
            scrollbar-color: #888 #f1f1f1;
        }

        .card-scroll-content::-webkit-scrollbar {
            width: 6px;
        }

        .card-scroll-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .card-scroll-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .card-scroll-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .card-title {
            font-size: 26px;
            color: #333;
            margin-bottom: 8px;
            font-weight: bold;
            border-bottom: 5px solid rgb(80,80,80);
            padding-bottom: 8px;
        }

        .cuisine-type {
            color: #666;
            margin-bottom: 12px;
            display: block;
            font-size: 20px;
        }

        .recipe-info {
            margin-bottom: 12px;
        }

        .recipe-info-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 4px;
            display: block;
            font-size: 20px;
        }

        .recipe-info-content {
            color: #666;
            line-height: 1.4;
            margin-left: 10px;
            font-size: 18px;
        }

        .card-actions {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .btn {
            padding: 8px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background-color: #4CAF50;
            color: white;
        }

        .btn-delete {
            background-color: #f44336;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .toggle-icon {
            width: 24px;
            height: 24px;
            position: relative;
            transition: transform 0.5s ease-in-out;
        }

        .toggle-icon::before,
        .toggle-icon::after {
            content: '';
            position: absolute;
            background-color: #333;
            transition: transform 0.5s ease-in-out;
        }

        .toggle-icon::before {
            width: 2px;
            height: 16px;
            left: 11px;
            top: 4px;
        }

        .toggle-icon::after {
            width: 16px;
            height: 2px;
            left: 4px;
            top: 11px;
        }

        .section.expanded .toggle-icon::before {
            transform: rotate(90deg);
        }

        @media (max-width: 1200px) {
            .section-content {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .section-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .section-content {
                grid-template-columns: 1fr;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            width: 80%;
            max-width: 800px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .modal-close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .modal-close:hover {
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .btn-edit {
            background-color: #4CAF50;
            color: white;
        }

        .btn-delete {
            background-color: #f44336;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* Updated Edit Window Styles */
        .edit-window {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
            z-index: 1001;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
        }

        .edit-window-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .edit-window-title {
            font-size: 24px;
            color: #333;
            margin: 0;
            font-weight: 600;
        }

        .edit-window-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .edit-window-close:hover {
            background-color: #eee;
            color: #333;
        }

        .edit-form-content {
            padding: 25px;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background-color: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }

        .form-actions {
            padding: 20px 25px;
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-save {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel {
            background-color: #e0e0e0;
            color: #333;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            background-color: #43a047;
            transform: translateY(-1px);
        }

        .btn-cancel:hover {
            background-color: #d5d5d5;
            transform: translateY(-1px);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        /* Plan Window Styles */
        .plan-window {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
            z-index: 1001;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
        }

        .plan-window-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .plan-window-title {
            font-size: 24px;
            color: #333;
            margin: 0;
            font-weight: 600;
        }

        .plan-window-content {
            padding: 25px;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .plan-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .meal-type-select {
            margin-top: 20px;
        }

        .btn-add-plan {
            background-color: #2196F3;
            color: white;
        }

        .plan-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }

        .plan-date {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .meal-type {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .meal-type h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 5px;
        }

        .meal-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            animation: slideIn 0.5s ease-out backwards;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .meal-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }

        .meal-info {
            flex-grow: 1;
        }

        .meal-title {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .meal-date {
            font-size: 12px;
            color: #666;
        }

        .no-meal {
            color: #999;
            font-style: italic;
            padding: 10px;
        }

        .no-plans {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        /* Detailed Plan Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content.plan-detail-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 80%;
            max-width: 1000px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-close {
            position: absolute;
            right: 25px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            z-index: 1;
        }

        .modal-close:hover {
            color: #333;
        }

        .plan-detail-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .plan-detail-title {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }

        .plan-detail-dates {
            color: #666;
            font-size: 16px;
        }

        .plan-detail-meals {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .plan-detail-meal-type {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
        }

        .plan-detail-meal-type h3 {
            color: #333;
            font-size: 24px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .plan-detail-meal-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .plan-detail-meal-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background-color: #f8f9fa;
        }

        .plan-detail-meal-item:hover .plan-detail-meal-title {
            color: #4CAF50;
        }

        .plan-detail-meal-item:hover .plan-detail-meal-image {
            transform: scale(1.05);
        }

        .plan-detail-meal-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .plan-detail-meal-info {
            flex-grow: 1;
        }

        .plan-detail-meal-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .plan-detail-meal-date {
            color: #666;
            font-size: 14px;
        }

        .card {
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .filter-sort-form {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-inputs {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 18px;
        }

        .form-control option {
            font-size: 16px;
            padding: 8px;
        }

        .filter-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .btn-filter, .btn-reset {
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
            min-width: 120px;
        }

        .btn-filter {
            background-color: #4CAF50;
            color: white;
        }

        .btn-reset {
            background-color: #f44336;
            color: white;
        }

        .btn-filter:hover, .btn-reset:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .filter-inputs {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }

            .filter-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-filter, .btn-reset {
                width: 100%;
            }
        }

        .autocomplete-items {
            position: absolute;
            border: 1px solid #ddd;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            max-height: 200px;
            overflow-y: auto;
        }

        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .autocomplete-items div:hover {
            background-color: #f8f9fa;
        }

        .autocomplete-active {
            background-color: #4CAF50 !important;
            color: white;
        }

        #meal-plans .section-content {
            grid-template-columns: repeat(3, 1fr);
        }

        #meal-plans .card {
            height: 650px;
            display: flex;
            flex-direction: column;
        }

        #meal-plans .card-content {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #888 #f1f1f1;
            padding-bottom: 10px;
        }

        .plan-actions {
            padding: 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-edit-plan, .btn-delete-plan {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-edit-plan {
            background-color: #2196F3;
        }

        .btn-delete-plan {
            background-color: #f44336;
        }

        .btn-edit-plan:hover {
            background-color: #1976D2;
            transform: translateY(-2px);
        }

        .btn-delete-plan:hover {
            background-color: #d32f2f;
            transform: translateY(-2px);
        }

        @media (max-width: 1200px) {
            #meal-plans .section-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            #meal-plans .section-content {
                grid-template-columns: 1fr;
            }
        }

        .image-upload-container {
            margin-bottom: 20px;
            text-align: center;
        }

        .current-image {
            max-width: 200px;
            max-height: 200px;
            margin-bottom: 10px;
            border-radius: 8px;
            object-fit: cover;
        }

        .image-upload-label {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .image-upload-label:hover {
            background-color: #45a049;
        }

        .image-upload-input {
            display: none;
        }

        .image-preview {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }

        .meal-categories {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .meal-category {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }

        .meal-category h4 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
        }

        .meal-item {
            display: flex;
            align-items: center;
            background: white;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .meal-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }

        .meal-item-info {
            flex-grow: 1;
        }

        .meal-item-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .meal-item-date {
            font-size: 14px;
            color: #666;
        }

        .meal-item-actions {
            margin-left: 10px;
        }

        .btn-remove-meal {
            background: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-remove-meal:hover {
            background: #d32f2f;
        }

        .plan-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-edit-plan, .btn-delete-plan {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            color: white;
        }

        .btn-edit-plan {
            background-color: #2196F3;
        }

        .btn-delete-plan {
            background-color: #f44336;
        }

        .btn-edit-plan:hover {
            background-color: #1976D2;
        }

        .btn-delete-plan:hover {
            background-color: #d32f2f;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-create-recipe {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-create-recipe:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }

        /* Update section header styles to accommodate the new button */
        .section-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-create-recipe {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .btn-create-recipe:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }

        .section-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #meal-plans .filter-sort-form {
            border-bottom: 1px solid #eee;
        }

        /* Ensure filter inputs are properly spaced in both sections */
        .filter-inputs {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .filter-inputs .form-group {
            flex: 1;
            min-width: 200px;
        }

        /* Ensure consistent button styling */
        .filter-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .btn-filter, .btn-reset {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-filter {
            background-color: #4CAF50;
            color: white;
        }

        .btn-reset {
            background-color: #f44336;
            color: white;
        }

        .btn-filter:hover, .btn-reset:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .filter-inputs {
                flex-direction: column;
            }
            
            .filter-inputs .form-group {
                width: 100%;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .btn-filter, .btn-reset {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            border: 1px solid #4a90e2;
            background: white;
            color: #4a90e2;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .view-toggle-btn.active {
            background: #4a90e2;
            color: white;
        }

        .button-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
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
    <div class="body-container">
        <div class="button-container">
            <?php if ($is_admin): ?>
            <div class="admin-controls">
                <span class="admin-label">View User:</span>
                <select class="user-select" onchange="window.location.href='recipe_planner.php?user_id=' + this.value">
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
                <button type="button" class="view-toggle-btn" onclick="switchView('planner')" style="width: 300px">Meal Planner View</button>
                <button type="button" class="view-toggle-btn active" onclick="switchView('recipes')" style="width: 300px">Saved Recipes & Planner</button>
            </div>
        </div>

        <div class="section expanded" id="saved-recipes">
            <div class="section-header" style="cursor: pointer;">
                <div class="header-left">
                    <h2>Saved Recipes</h2>
                </div>
                <div class="header-actions">
                    <a href="insert_custom_recipe.php<?php echo $is_admin ? '?user_id=' . $selected_user_id : ''; ?>" class="btn-create-recipe" onclick="event.stopPropagation()">
                        <span style="font-size: 20px;">+</span> Create New Recipe
                    </a>
                    <div class="toggle-icon"></div>
                </div>
            </div>
            <div class="filter-sort-form">
                <form method="GET" class="filter-form" onsubmit="window.location.href='recipe_planner.php#saved-recipes'; return true;">
                    <div class="filter-inputs">
                        <div class="form-group">
                            <input type="text" name="filter_title" placeholder="Filter by recipe title..." 
                                value="<?php echo htmlspecialchars($filter_title); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="sort_by" class="form-control">
                                <option value="cusRecipe_title" <?php echo $sort_by === 'cusRecipe_title' ? 'selected' : ''; ?>>Title</option>
                                <option value="save_record" <?php echo $sort_by === 'save_record' ? 'selected' : ''; ?>>Date Saved</option>
                                <option value="modify_record" <?php echo $sort_by === 'modify_record' ? 'selected' : ''; ?>>Date Modified</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="sort_order" class="form-control">
                                <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-filter">Apply</button>
                        <button type="button" class="btn btn-reset" onclick="window.location.href=window.location.pathname">Reset</button>
                    </div>
                </form>
            </div>
            <div class="section-content expanded">
                <?php foreach ($recipes as $recipe): ?>
                <div class="card" data-id="<?php echo $recipe['cusRecipe_id']; ?>">
                    <div class="card-image-container">
                        <img src="uploads/<?php echo htmlspecialchars($recipe['image']); ?>" alt="<?php echo htmlspecialchars($recipe['cusRecipe_title']); ?>" class="card-image">
                    </div>
                    <div class="card-content">
                        <div class="card-scroll-content">
                            <h3 class="card-title"><?php echo htmlspecialchars($recipe['cusRecipe_title']); ?></h3>

                            <div class="recipe-info">
                                <span class="recipe-info-label">Cuisine Type:</span>
                                <div class="recipe-info-content"><?php echo nl2br(htmlspecialchars($recipe['cuisine_type'])); ?></div>
                            </div>
                            
                            <div class="recipe-info">
                                <span class="recipe-info-label">Ingredients:</span>
                                <div class="recipe-info-content"><?php echo nl2br(htmlspecialchars($recipe['ingredient'])); ?></div>
                            </div>

                            <div class="recipe-info">
                                <span class="recipe-info-label">Description:</span>
                                <div class="recipe-info-content"><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></div>
                            </div>

                            <div class="recipe-info">
                                <span class="recipe-info-label">Steps:</span>
                                <div class="recipe-info-content"><?php echo nl2br(htmlspecialchars($recipe['step'])); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-edit" onclick="openEditWindow(event, <?php echo $recipe['cusRecipe_id']; ?>)">Edit</button>
                        <button class="btn btn-delete" onclick="confirmDelete(event, <?php echo $recipe['cusRecipe_id']; ?>)">Delete</button>
                        <button class="btn btn-add-plan" onclick="openPlanWindow(event, <?php echo $recipe['cusRecipe_id']; ?>)">Add to Plan</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Updated Edit Window -->
        <div class="edit-window-overlay" id="editOverlay"></div>
        <div class="edit-window" id="editWindow">
            <div class="edit-window-header">
                <h2 class="edit-window-title">Edit Recipe</h2>
                <button class="edit-window-close" onclick="closeEditWindow()">&times;</button>
            </div>
            <form method="POST" id="editRecipeForm" enctype="multipart/form-data">
                <div class="edit-form-content">
                    <input type="hidden" name="edit_id" id="editId">
                    
                    <div class="image-upload-container">
                        <img id="currentImage" class="current-image" src="" alt="Current recipe image">
                        <br>
                        <label class="image-upload-label">
                            Change Image
                            <input type="file" name="new_image" class="image-upload-input" accept="image/*" onchange="previewImage(this)">
                        </label>
                        <div class="image-preview" id="imagePreview"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" class="form-control" name="title" id="editTitle" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cuisine_type">Cuisine Type:</label>
                            <input type="text" class="form-control" name="cuisine_type" id="editCuisine" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ingredient">Ingredients (one per line):</label>
                        <textarea class="form-control" name="ingredient" id="editIngredients" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea class="form-control" name="description" id="editDescription" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="step">Steps (one per line):</label>
                        <textarea class="form-control" name="step" id="editSteps" required></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditWindow()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>

        <div class="section expanded" id="meal-plans">
            <div class="section-header">
                <h2>Plans</h2>
                <div class="toggle-icon"></div>
            </div>
            <div class="filter-sort-form">
                <form method="GET" class="filter-form" onsubmit="window.location.hash='meal-plans'; return true;">
                    <div class="filter-inputs">
                        <div class="form-group">
                            <input type="text" name="filter_plan_name" 
                                placeholder="Filter by plan name..." 
                                value="<?php echo htmlspecialchars($filter_plan_name); ?>" 
                                class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="plan_sort_by" class="form-control">
                                <option value="plan_name" <?php echo $plan_sort_by === 'plan_name' ? 'selected' : ''; ?>>Plan Name</option>
                                <option value="start_date" <?php echo $plan_sort_by === 'start_date' ? 'selected' : ''; ?>>Start Date</option>
                                <option value="end_date" <?php echo $plan_sort_by === 'end_date' ? 'selected' : ''; ?>>End Date</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="plan_sort_order" class="form-control">
                                <option value="asc" <?php echo $plan_sort_order === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="desc" <?php echo $plan_sort_order === 'desc' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-filter">Apply</button>
                        <button type="button" class="btn btn-reset" onclick="window.location.href='recipe_planner.php#meal-plans'">Reset</button>
                    </div>
                </form>
            </div>
            <div class="section-content expanded">
                <?php if (empty($organized_plans)): ?>
                    <div class="no-plans">
                        <p>No meal plans created yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($organized_plans as $plan_id => $plan): ?>
                    <div class="card" data-plan-id="<?php echo $plan_id; ?>">
                        <div class="card-content">
                            <h3 class="plan-title"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                            <p class="plan-date">
                                <?php echo date('M d, Y', strtotime($plan['start_date'])); ?> 
                                - <?php echo date('M d, Y', strtotime($plan['end_date'])); ?>
                            </p>
                            
                            <?php foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $meal_type): ?>
                            <div class="meal-type">
                                <h4><?php echo ucfirst($meal_type); ?></h4>
                                <?php if (empty($plan['meals'][$meal_type])): ?>
                                    <p class="no-meal">No <?php echo $meal_type; ?> planned</p>
                                <?php else: ?>
                                    <?php foreach ($plan['meals'][$meal_type] as $meal): ?>
                                    <div class="meal-item">
                                        <img src="uploads/<?php echo htmlspecialchars($meal['image']); ?>" 
                                            alt="<?php echo htmlspecialchars($meal['title']); ?>" 
                                            class="meal-thumbnail">
                                        <div class="meal-info">
                                            <p class="meal-title"><?php echo htmlspecialchars($meal['title']); ?></p>
                                            <p class="meal-date"><?php echo date('M d, Y', strtotime($meal['date'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="plan-actions">
                            <button class="btn-edit-plan" onclick="handleEditPlan(event, '<?php echo $plan_id; ?>')">Edit Plan</button>
                            <button class="btn-delete-plan" onclick="handleDeletePlan(event, '<?php echo $plan_id; ?>')">Delete Plan</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add to Plan Window -->
        <div class="edit-window-overlay" id="planOverlay"></div>
        <div class="plan-window" id="planWindow">
            <div class="plan-window-header">
                <h2 class="plan-window-title">Add to Meal Plan</h2>
                <button class="edit-window-close" onclick="closePlanWindow()">&times;</button>
            </div>
            <div class="plan-window-content">
                <form method="POST" id="addToPlanForm">
                    <input type="hidden" name="add_to_plan" value="1">
                    <input type="hidden" name="recipe_id" id="planRecipeId">
                    
                    <div class="form-group">
                        <label for="plan_id">Select Plan:</label>
                        <select class="form-control" name="plan_id" id="planSelect" onchange="toggleNewPlanName()" required>
                            <option value="">Choose a plan...</option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo htmlspecialchars($plan['mealPlan_id']); ?>">
                                    <?php echo htmlspecialchars($plan['plan_name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new">Create New Plan</option>
                        </select>
                    </div>

                    <div class="form-group" id="newPlanGroup" style="display: none;">
                        <label for="new_plan_name">New Plan Name:</label>
                        <input type="text" class="form-control" name="new_plan_name" id="newPlanName">
                        
                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group">
                                <label for="start_date">Start Date:</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date:</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group meal-type-select">
                        <label for="meal_type">Meal Type:</label>
                        <select class="form-control" name="meal_type" required>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="snack">Snack</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="closePlanWindow()">Cancel</button>
                        <button type="submit" class="btn-save">Add to Plan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Detailed Plan Modal -->
        <div id="planDetailModal" class="modal">
            <div class="modal-content plan-detail-content">
                <span class="modal-close" onclick="closePlanDetailModal()">&times;</span>
                <div id="planDetailContent"></div>
            </div>
        </div>

        <!-- Edit Plan Window -->
        <div class="edit-window-overlay" id="editPlanOverlay"></div>
        <div class="edit-window" id="editPlanWindow" style="width: 80%; max-width: 1000px;">
            <div class="edit-window-header">
                <h2 class="edit-window-title">Edit Meal Plan</h2>
                <button class="edit-window-close" onclick="closeEditPlanWindow()">&times;</button>
            </div>
            <div class="edit-form-content">
                <form method="POST" class="date-edit-section" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <input type="hidden" name="plan_id" id="edit_plan_id">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="edit_plan_name" style="display: block; margin-bottom: 5px;">Plan Name:</label>
                        <input type="text" name="plan_name" id="edit_plan_name" class="form-control" required>
                    </div>
                    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="edit_start_date" style="display: block; margin-bottom: 5px;">Start Date:</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="edit_end_date" style="display: block; margin-bottom: 5px;">End Date:</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" name="update_plan_dates" class="btn btn-save" style="background-color: #4CAF50;">Update Plan</button>
                </form>
                <div class="meal-categories">
                    <div class="meal-category" id="breakfast-items">
                        <h4>Breakfast</h4>
                        <div class="meal-items"></div>
                    </div>
                    <div class="meal-category" id="lunch-items">
                        <h4>Lunch</h4>
                        <div class="meal-items"></div>
                    </div>
                    <div class="meal-category" id="dinner-items">
                        <h4>Dinner</h4>
                        <div class="meal-items"></div>
                    </div>
                    <div class="meal-category" id="snack-items">
                        <h4>Snack</h4>
                        <div class="meal-items"></div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeEditPlanWindow()">Close</button>
            </div>
        </div>        

    <script>
        // Function to handle view switching
        function switchView(view) {
            if (view === 'planner') {
                window.location.href = 'meal_planner_view.php';
            }
            else{
                window.location.href = 'recipe_planner.php';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.section-header').forEach(header => {
                header.addEventListener('click', function(event) {
                    // Don't toggle if clicking the Create Recipe button
                    if (event.target.closest('.btn-create-recipe')) {
                        return;
                    }
                    
                    const section = header.closest('.section');
                    const content = section.querySelector('.section-content');
                    
                    section.classList.toggle('expanded');
                    content.classList.toggle('expanded');
                });
            });
        });

        // Modal functionality
        const modal = document.getElementById('recipeModal');
        const modalContent = document.getElementById('modalContent');
        const editForm = document.getElementById('editForm');
        const editRecipeForm = document.getElementById('editRecipeForm');
        let currentRecipeId = null;

        // Open modal when clicking a card
        document.querySelectorAll('#saved-recipes .card').forEach(card => {
            card.addEventListener('click', function(event) {
                // Don't trigger if clicking a button in card-actions
                if (!event.target.closest('.card-actions')) {
                    const id = this.dataset.id;
                    openEditWindow(event, id);
                }
            });
        });

        function openEditWindow(event, id) {
            event.stopPropagation();
            const card = document.querySelector(`.card[data-id="${id}"]`);
            const title = card.querySelector('.card-title').textContent;
            const cuisine = card.querySelector('.recipe-info:nth-of-type(1) .recipe-info-content').textContent;
            const ingredients = card.querySelector('.recipe-info:nth-of-type(2) .recipe-info-content').textContent;
            const description = card.querySelector('.recipe-info:nth-of-type(3) .recipe-info-content').textContent;
            const steps = card.querySelector('.recipe-info:nth-of-type(4) .recipe-info-content').textContent;
            const image = card.querySelector('.card-image').src;

            document.getElementById('editId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editCuisine').value = cuisine;
            document.getElementById('editIngredients').value = ingredients;
            document.getElementById('editDescription').value = description;
            document.getElementById('editSteps').value = steps;
            document.getElementById('currentImage').src = image;
            document.getElementById('imagePreview').textContent = '';

            document.getElementById('editOverlay').style.display = 'block';
            document.getElementById('editWindow').style.display = 'block';
        }

        // Close modal
        document.querySelector('.modal-close').addEventListener('click', () => {
            modal.style.display = 'none';
            hideEditForm();
        });

        // Close modal when clicking outside
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
                hideEditForm();
            }
        });

        function showEditForm() {
            modalContent.style.display = 'none';
            editForm.style.display = 'block';
        }

        function hideEditForm() {
            modalContent.style.display = 'block';
            editForm.style.display = 'none';
        }

        function confirmDelete(event, recipeId) {
            event.preventDefault();
            event.stopPropagation();
            
            if (confirm('Are you sure you want to delete this recipe? This will also remove it from any meal plans.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'recipe_planner.php<?php echo $is_admin && isset($_GET['user_id']) ? "?user_id=" . $_GET['user_id'] : ""; ?>';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_id';
                input.value = recipeId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeEditWindow() {
            document.getElementById('editOverlay').style.display = 'none';
            document.getElementById('editWindow').style.display = 'none';
        }

        // Close edit window when clicking overlay
        document.getElementById('editOverlay').addEventListener('click', closeEditWindow);

        function openPlanWindow(event, recipeId) {
            event.stopPropagation();
            document.getElementById('planRecipeId').value = recipeId;
            document.getElementById('planOverlay').style.display = 'block';
            document.getElementById('planWindow').style.display = 'block';
        }

        function closePlanWindow() {
            document.getElementById('planOverlay').style.display = 'none';
            document.getElementById('planWindow').style.display = 'none';
            document.getElementById('newPlanGroup').style.display = 'none';
            document.getElementById('planSelect').value = '';
            document.getElementById('newPlanName').value = '';
        }

        function toggleNewPlanName() {
            const planSelect = document.getElementById('planSelect');
            const newPlanGroup = document.getElementById('newPlanGroup');
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            if (planSelect.value === 'new') {
                newPlanGroup.style.display = 'block';
                startDate.required = true;
                endDate.required = true;
                
                // Set default dates from PHP variables
                startDate.value = '<?php echo $default_start_date; ?>';
                endDate.value = '<?php echo $default_end_date; ?>';
            } else {
                newPlanGroup.style.display = 'none';
                startDate.required = false;
                endDate.required = false;
            }
        }

        // Add date validation
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = new Date(this.value);
            const endDate = document.getElementById('end_date');
            endDate.min = this.value;
            
            if (new Date(endDate.value) < startDate) {
                endDate.value = this.value;
            }
        });

        document.getElementById('end_date').addEventListener('change', function() {
            const endDate = new Date(this.value);
            const startDate = document.getElementById('start_date');
            startDate.max = this.value;
            
            if (new Date(startDate.value) > endDate) {
                startDate.value = this.value;
            }
        });

        function showPlanDetail(planId) {
            const plan = <?php echo json_encode($organized_plans); ?>[planId];
            if (!plan) return;

            let content = `
                <div class="plan-detail-header">
                    <h2 class="plan-detail-title">${plan.plan_name}</h2>
                    <p class="plan-detail-dates">
                        ${new Date(plan.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })} 
                        - ${new Date(plan.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
                    </p>
                </div>
                <div class="plan-detail-meals">`;

            ['breakfast', 'lunch', 'dinner', 'snack'].forEach(mealType => {
                content += `
                    <div class="plan-detail-meal-type">
                        <h3>${mealType.charAt(0).toUpperCase() + mealType.slice(1)}</h3>`;

                if (plan.meals[mealType].length === 0) {
                    content += `<p class="no-meal">No ${mealType} planned</p>`;
                } else {
                    plan.meals[mealType].forEach(meal => {
                        content += `
                            <div class="plan-detail-meal-item" onclick="window.location.href='recipe_planner.php?filter_title=${encodeURIComponent(meal.title)}<?php echo $is_admin && isset($_GET['user_id']) ? "&user_id=" . $_GET['user_id'] : ""; ?>'">
                                <img src="uploads/${meal.image}" alt="${meal.title}" class="plan-detail-meal-image">
                                <div class="plan-detail-meal-info">
                                    <h4 class="plan-detail-meal-title">${meal.title}</h4>
                                    <p class="plan-detail-meal-date">${new Date(meal.date).toLocaleDateString()}</p>
                                </div>
                            </div>`;
                    });
                }
                content += `</div>`;
            });

            content += `</div>`;

            document.getElementById('planDetailContent').innerHTML = content;
            document.getElementById('planDetailModal').style.display = 'block';
        }

        function closePlanDetailModal() {
            document.getElementById('planDetailModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('planDetailModal');
            if (event.target == modal) {
                closePlanDetailModal();
            }
        }

        // Update the card click handler in the plans section
        document.querySelectorAll('#meal-plans .card').forEach(card => {
            card.addEventListener('click', function(event) {
                // Don't show plan detail if clicking on action buttons or their container
                if (!event.target.closest('.plan-actions')) {
                    const planId = this.getAttribute('data-plan-id');
                    if (planId) {
                        showPlanDetail(planId);
                    }
                }
            });
        });

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                preview.textContent = `Selected: ${fileName}`;
            } else {
                preview.textContent = '';
            }
        }

        function openEditPlanWindow(planId) {
            const plans = <?php echo json_encode($organized_plans); ?>;
            const plan = plans[planId];
            
            if (!plan) {
                alert('Error: Plan not found');
                return;
            }

            document.getElementById('edit_plan_id').value = planId;
            document.getElementById('edit_plan_name').value = plan.plan_name;
            document.getElementById('edit_start_date').value = plan.start_date;
            document.getElementById('edit_end_date').value = plan.end_date;

            // Clear existing items
            document.querySelectorAll('.meal-items').forEach(container => {
                container.innerHTML = '';
            });
            
            // Populate meal items
            ['breakfast', 'lunch', 'dinner', 'snack'].forEach(category => {
                const container = document.querySelector(`#${category}-items .meal-items`);
                if (!container) return;

                if (!plan.meals[category] || plan.meals[category].length === 0) {
                    container.innerHTML = `<p class="no-meal">No ${category} planned</p>`;
                } else {
                    plan.meals[category].forEach(meal => {
                        container.innerHTML += `
                            <div class="meal-item">
                                <img src="uploads/${meal.image}" alt="${meal.title}">
                                <div class="meal-item-info">
                                    <div class="meal-item-title">${meal.title}</div>
                                    <div class="meal-item-date">${new Date(meal.date).toLocaleDateString()}</div>
                                </div>
                                <div class="meal-item-actions">
                                    <button class="btn-remove-meal" onclick="removeMealItem(${meal.mealEntry_id}, this, ${planId})">Remove</button>
                                </div>
                            </div>
                        `;
                    });
                }
            });

            document.getElementById('editPlanOverlay').style.display = 'block';
            document.getElementById('editPlanWindow').style.display = 'block';
        }

        function closeEditPlanWindow() {
            document.getElementById('editPlanOverlay').style.display = 'none';
            document.getElementById('editPlanWindow').style.display = 'none';
            // Refresh the page and add a hash to scroll to plans section
            window.location.href = 'recipe_planner.php#meal-plans';
            location.reload();
        }

        function removeMealItem(entryId, button, planId) {
            if (confirm('Are you sure you want to remove this meal?')) {
                const formData = new FormData();
                formData.append('delete_meal_entry', '1');
                formData.append('mealEntry_id', entryId);
                formData.append('plan_id', planId);

                fetch('recipe_planner.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the meal item from the edit window
                        const mealItem = button.closest('.meal-item');
                        const category = mealItem.closest('.meal-category');
                        mealItem.remove();

                        // Check if category is empty in edit window
                        const remainingMeals = category.querySelectorAll('.meal-item');
                        if (remainingMeals.length === 0) {
                            const categoryName = category.querySelector('h4').textContent.toLowerCase();
                            const noMealMsg = document.createElement('p');
                            noMealMsg.className = 'no-meal';
                            noMealMsg.textContent = `No ${categoryName} planned`;
                            category.querySelector('.meal-items').appendChild(noMealMsg);
                        }

                        // Update the main plan view
                        updatePlanView(planId);
                    }
                });
            }
        }

        function updatePlanView(planId) {
            const formData = new FormData();
            formData.append('fetch_plan_data', '1');
            formData.append('plan_id', planId);

            fetch('recipe_planner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Find the plan card and update its content
                    const planCard = document.querySelector(`#meal-plans .card[data-plan-id="${planId}"]`);
                    if (planCard) {
                        planCard.innerHTML = data.html;
                    }
                }
            })
            .catch(error => {
                console.error('Error updating plan view:', error);
            });
        }

        function deletePlan(planId) {
            if (confirm('Are you sure you want to delete this meal plan? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'recipe_planner.php<?php echo $is_admin && isset($_GET['user_id']) ? "?user_id=" . $_GET['user_id'] : ""; ?>';
                form.innerHTML = `<input type="hidden" name="delete_plan" value="1"><input type="hidden" name="plan_id" value="${planId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add these new handler functions
        function handleEditPlan(event, planId) {
            event.preventDefault();
            event.stopPropagation();
            openEditPlanWindow(planId);
        }

        function handleDeletePlan(event, planId) {
            event.preventDefault();
            event.stopPropagation();
            deletePlan(planId);
        }

        // Add date validation
        document.getElementById('edit_start_date').addEventListener('change', function() {
            const endDate = document.getElementById('edit_end_date');
            endDate.min = this.value;
        });

        document.getElementById('edit_end_date').addEventListener('change', function() {
            const startDate = document.getElementById('edit_start_date');
            startDate.max = this.value;
        });

        // Add date validation
        document.getElementById('edit_start_date').addEventListener('change', function() {
            const endDate = document.getElementById('edit_end_date');
            endDate.min = this.value;
        });

        document.getElementById('edit_end_date').addEventListener('change', function() {
            const startDate = document.getElementById('edit_start_date');
            startDate.max = this.value;
        });

        // Add this helper function for the :contains selector
        jQuery.expr[':'].contains = function(a, i, m) {
            return jQuery(a).text().toUpperCase()
                .indexOf(m[3].toUpperCase()) >= 0;
        };

        function validatePlanName(planName) {
            // Remove leading/trailing spaces
            planName = planName.trim();
            
            if (planName.length === 0) {
                alert("Plan name cannot be empty");
                return false;
            }
            
            return true;
        }

        // Update the form submission handler
        document.getElementById('addToPlanForm').addEventListener('submit', function(e) {
            const planSelect = document.getElementById('planSelect');
            if (planSelect.value === 'new') {
                const planName = document.getElementById('newPlanName').value;
                if (!validatePlanName(planName)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html> 

<?php
    require 'footer.php';
?>