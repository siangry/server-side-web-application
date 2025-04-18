<?php
session_start();
require 'database.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = md5(trim($_POST['password'] ?? '')); // Convert password to MD5

    $stmt = $conn->prepare("SELECT * FROM USER WHERE username = ? AND password = ?"); // Check both username and password
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        header("Location: view_recipes.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to right, #667eea, #764ba2);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card {
      width: 100%;
      max-width: 500px;
      min-height: 350px;
      border-radius: 1rem;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
      padding: 2rem;
      font-size: 1.2rem;
    }
    .form-control {
      padding: 0.5rem 0.75rem;
    }
    .btn {
      padding: 0.5rem 1rem;
      font-size: 1.3rem;
    }
    .alert {
      padding: 0.5rem 1rem;
      margin-bottom: 1rem;
    }
    .mb-3 {
      margin-bottom: 1rem !important;
    }
    .mb-4 {
      margin-bottom: 1.5rem !important;
    }
  </style>
</head>
<body>
<div class="card p-4">
  <h4 class="text-center mb-4">Login</h4>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Login</button>
  </form>

  <div class="mt-3 text-center">
    <a href="forgot_password.php">Forgot Password?</a>  | 
    <a href="register.php">Register Account</a>
  </div>
</div>
</body>
</html>
