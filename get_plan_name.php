<?php
session_start();
require 'database.php';

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get selected user_id for admin view
$selected_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

// Get the date from the query parameter
$date = $_GET['date'] ?? date('Y-m-d');

// Query to get plan name for the date
$query = "SELECT plan_name FROM meal_plan WHERE ? BETWEEN start_date AND end_date AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("si", $date, $selected_user_id);
$stmt->execute();
$result = $stmt->get_result();

$response = ['plan_name' => ''];

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['plan_name'] = $row['plan_name'];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 