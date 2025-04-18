<?php
require 'database.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $name = $_POST['name'];
    $age = $_POST['age'];
    $phone = $_POST['phone_num'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Validate required fields
    if (empty($username) || empty($name) || empty($email) || empty($phone)) {
        $error = "All fields are required.";
    } 
    // Validate age
    elseif ($age < 1 || $age > 120) {
        $error = "Please enter a valid age between 1 and 120.";
    }
    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    // Validate phone number format (basic validation)
    elseif (!preg_match("/^[0-9]{10,}$/", $phone)) {
        $error = "Please enter a valid phone number (at least 10 digits).";
    }
    // Check for duplicate username
    else {
        $check_username = $conn->prepare("SELECT user_id FROM USER WHERE username = ?");
        $check_username->bind_param("s", $username);
        $check_username->execute();
        if ($check_username->get_result()->num_rows > 0) {
            $error = "Username already exists. Please choose another.";
        }
        // Check for duplicate email
        elseif ($check_email = $conn->prepare("SELECT user_id FROM USER WHERE email = ?")) {
            $check_email->bind_param("s", $email);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $error = "Email already exists. Please choose another.";
            }
            // Check for duplicate phone number
            elseif ($check_phone = $conn->prepare("SELECT user_id FROM USER WHERE phone_num = ?")) {
                $check_phone->bind_param("s", $phone);
                $check_phone->execute();
                if ($check_phone->get_result()->num_rows > 0) {
                    $error = "Phone number already exists. Please choose another.";
                }
                // Validate password requirements
                else {
                    $password_errors = array();
                    
                    if (strlen($password) < 8) {
                        $password_errors[] = "at least 8 characters long";
                    }
                    if (!preg_match('/[A-Z]/', $password)) {
                        $password_errors[] = "one uppercase letter";
                    }
                    if (!preg_match('/[0-9]/', $password)) {
                        $password_errors[] = "one number";
                    }
                    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                        $password_errors[] = "one special character";
                    }
                    
                    if (!empty($password_errors)) {
                        $error = "Password must contain: " . implode(", ", $password_errors) . ".";
                    }
                    else {
                        // Hash the password
                        $hashed_password = md5($password);
                        
                        // Insert the new user
                        $stmt = $conn->prepare("INSERT INTO USER (username, name, age, phone_num, email, password) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssisss", $username, $name, $age, $phone, $email, $hashed_password);
                        
                        if ($stmt->execute()) {
                            $success = "Registration successful! You can now login.";
                        } else {
                            $error = "Registration failed: " . $stmt->error;
                        }
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body {
      font-size: 1.1rem;
    }
    .card {
      font-size: 1.1rem;
    }
    .form-control {
      font-size: 1.1rem;
      padding: 0.75rem;
    }
    .mb-2 {
      margin-bottom: 1rem !important;
    }
    .mb-3 {
      margin-bottom: 1.5rem !important;
    }
    .mt-3 {
      margin-top: 1.5rem !important;
    }
    .btn {
      font-size: 1.1rem;
      padding: 0.75rem;
    }
    small {
      font-size: 0.95rem;
    }
  </style>
</head>
<body style="background: linear-gradient(to right, #43cea2, #185a9d); height: 100vh; display: flex; align-items: center; justify-content: center;">
  <div class="card p-4" style="max-width: 500px; width: 100%; padding: 2rem;">
    <h4 class="text-center mb-3">Register Account</h4>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-2"><input name="username" class="form-control" placeholder="Username" required></div>
      <div class="mb-2"><input name="name" class="form-control" placeholder="Full Name" required></div>
      <div class="mb-2"><input type="number" name="age" class="form-control" placeholder="Age" required></div>
      <div class="mb-2"><input name="phone_num" class="form-control" placeholder="Phone Number" required></div>
      <div class="mb-2"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
      <div class="mb-2">
        <input type="password" name="password" class="form-control" placeholder="Password" required>
        <small class="text-muted">Password requirements: at least 8 characters, one uppercase letter, one number, and one special character.</small>
      </div>
      <button type="submit" class="btn btn-success w-100">Register</button>
    </form>

    <div class="mt-3 text-center">
      Already have an account? <a href="login.php">Login here</a>
    </div>
  </div>
</body>
</html>
