<?php
session_start();
$requiresLogin = true;
require 'database.php';

$error = '';
$success = '';
$user_data = array();

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM USER WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $age = intval($_POST['age'] ?? 0);
        $phone = trim($_POST['phone_num'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        // Validate required fields
        if (empty($username) || empty($name) || empty($email) || empty($phone)) {
            $error = "All fields are required except password fields.";
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
        elseif ($username !== $user_data['username']) {
            $check_username = $conn->prepare("SELECT user_id FROM USER WHERE username = ?");
            $check_username->bind_param("s", $username);
            $check_username->execute();
            if ($check_username->get_result()->num_rows > 0) {
                $error = "Username already exists. Please choose another.";
            }
        }
        // Check for duplicate email
        elseif ($email !== $user_data['email']) {
            $check_email = $conn->prepare("SELECT user_id FROM USER WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $error = "Email already exists. Please choose another.";
            }
        }
        // Check for duplicate phone number
        elseif ($phone !== $user_data['phone_num']) {
            $check_phone = $conn->prepare("SELECT user_id FROM USER WHERE phone_num = ?");
            $check_phone->bind_param("s", $phone);
            $check_phone->execute();
            if ($check_phone->get_result()->num_rows > 0) {
                $error = "Phone number already exists. Please choose another.";
            }
        }
        // Check if password fields are filled
        elseif (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
            // If any password field is filled, all must be filled
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = "To change password, please fill all password fields.";
            }
            // Verify current password
            elseif (md5($current_password) !== $user_data['password']) {
                $error = "Current password is incorrect.";
            }
            // Check if new passwords match
            elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            }
            // Check password requirements
            else {
                $password_errors = array();
                
                if (strlen($new_password) < 8) {
                    $password_errors[] = "at least 8 characters long";
                }
                if (!preg_match('/[A-Z]/', $new_password)) {
                    $password_errors[] = "one uppercase letter";
                }
                if (!preg_match('/[0-9]/', $new_password)) {
                    $password_errors[] = "one number";
                }
                if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
                    $password_errors[] = "one special character";
                }
                
                if (!empty($password_errors)) {
                    $error = "Password must contain: " . implode(", ", $password_errors) . ".";
                }
            }
        }

        if (empty($error)) {
            // Prepare the update query
            $update_fields = array();
            $params = array();
            $types = "";

            // Add non-password fields
            $update_fields[] = "username = ?";
            $update_fields[] = "name = ?";
            $update_fields[] = "age = ?";
            $update_fields[] = "phone_num = ?";
            $update_fields[] = "email = ?";
            
            $params[] = $username;
            $params[] = $name;
            $params[] = $age;
            $params[] = $phone;
            $params[] = $email;
            $types = "ssiss";

            // Add password if being changed
            if (!empty($new_password)) {
                $update_fields[] = "password = ?";
                $params[] = md5($new_password);
                $types .= "s";
            }

            // Add user_id to params
            $params[] = $_SESSION['user_id'];
            $types .= "i";

            // Build and execute the update query
            $query = "UPDATE USER SET " . implode(", ", $update_fields) . " WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
                // Update session username if changed
                $_SESSION['username'] = $username;
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM USER WHERE user_id = ?");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
            } else {
                $error = "Failed to update profile: " . $stmt->error;
            }
        }
    }
    // Handle account deletion
    elseif (isset($_POST['delete_account'])) {
        // Verify password before deletion
        $password = trim($_POST['delete_password'] ?? '');
        
        if (empty($password)) {
            $error = "Please enter your password to confirm account deletion.";
        } elseif (md5($password) !== $user_data['password']) {
            $error = "Incorrect password. Please try again.";
        } else {
            // Delete user's account
            $delete_stmt = $conn->prepare("DELETE FROM USER WHERE user_id = ?");
            $delete_stmt->bind_param("i", $_SESSION['user_id']);
            
            if ($delete_stmt->execute()) {
                // Clear session and redirect to login page
                session_destroy();
                header("Location: login.php");
                exit();
            } else {
                $error = "Failed to delete account. Please try again.";
            }
        }
    }
    // Handle admin account creation
    elseif (isset($_POST['create_admin']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $new_admin_username = trim($_POST['new_admin_username']);
        $new_admin_name = trim($_POST['new_admin_name']);
        $new_admin_age = intval($_POST['new_admin_age']);
        $new_admin_phone = trim($_POST['new_admin_phone']);
        $new_admin_email = trim($_POST['new_admin_email']);
        $new_admin_password = trim($_POST['new_admin_password']);

        // Validate new admin fields
        if (empty($new_admin_username) || empty($new_admin_name) || empty($new_admin_email) || empty($new_admin_phone)) {
            $error = "All fields are required for new admin account.";
        } 
        elseif ($new_admin_age < 1 || $new_admin_age > 120) {
            $error = "Please enter a valid age between 1 and 120 for new admin.";
        }
        elseif (!filter_var($new_admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address for new admin.";
        }
        elseif (!preg_match("/^[0-9]{10,}$/", $new_admin_phone)) {
            $error = "Please enter a valid phone number (at least 10 digits) for new admin.";
        }
        else {
            // Check for duplicate username for new admin
            $check_new_username = $conn->prepare("SELECT user_id FROM USER WHERE username = ?");
            $check_new_username->bind_param("s", $new_admin_username);
            $check_new_username->execute();
            if ($check_new_username->get_result()->num_rows > 0) {
                $error = "Username already exists for new admin. Please choose another.";
            }
            // Check for duplicate email for new admin
            elseif ($check_new_email = $conn->prepare("SELECT user_id FROM USER WHERE email = ?")) {
                $check_new_email->bind_param("s", $new_admin_email);
                $check_new_email->execute();
                if ($check_new_email->get_result()->num_rows > 0) {
                    $error = "Email already exists for new admin. Please choose another.";
                }
                // Check for duplicate phone for new admin
                elseif ($check_new_phone = $conn->prepare("SELECT user_id FROM USER WHERE phone_num = ?")) {
                    $check_new_phone->bind_param("s", $new_admin_phone);
                    $check_new_phone->execute();
                    if ($check_new_phone->get_result()->num_rows > 0) {
                        $error = "Phone number already exists for new admin. Please choose another.";
                    }
                    else {
                        // Validate password requirements
                        $password_errors = array();
                        
                        if (strlen($new_admin_password) < 8) {
                            $password_errors[] = "at least 8 characters long";
                        }
                        if (!preg_match('/[A-Z]/', $new_admin_password)) {
                            $password_errors[] = "one uppercase letter";
                        }
                        if (!preg_match('/[0-9]/', $new_admin_password)) {
                            $password_errors[] = "one number";
                        }
                        if (!preg_match('/[^A-Za-z0-9]/', $new_admin_password)) {
                            $password_errors[] = "one special character";
                        }
                        
                        if (!empty($password_errors)) {
                            $error = "Password must contain: " . implode(", ", $password_errors) . ".";
                        }
                        else {
                            // Create new admin account
                            $new_admin_password = md5($new_admin_password);
                            $create_admin = $conn->prepare("INSERT INTO USER (username, name, age, phone_num, email, password, role) VALUES (?, ?, ?, ?, ?, ?, 'admin')");
                            $create_admin->bind_param("ssisss", $new_admin_username, $new_admin_name, $new_admin_age, $new_admin_phone, $new_admin_email, $new_admin_password);
                            
                            if ($create_admin->execute()) {
                                $success = "Admin account created successfully!";
                            } else {
                                $error = "Failed to create admin account: " . $create_admin->error;
                            }
                        }
                    }
                }
            }
        }
    }
}
require 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Recipe Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>

        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-label {
            font-weight: 500;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body style="background: #f8f9fa;">
    <div class="profile-container">
        <h2 class="mb-4">Profile Settings</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="username" class="form-label required-field">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?= htmlspecialchars($user_data['username'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="name" class="form-label required-field">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="age" class="form-label required-field">Age</label>
                    <input type="number" class="form-control" id="age" name="age" 
                           value="<?= htmlspecialchars($user_data['age'] ?? '') ?>" required min="1" max="120">
                </div>
                <div class="col-md-6">
                    <label for="phone_num" class="form-label required-field">Phone Number</label>
                    <input type="tel" class="form-control" id="phone_num" name="phone_num" 
                           value="<?= htmlspecialchars($user_data['phone_num'] ?? '') ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label required-field">Email</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
            </div>

            <hr class="my-4">
            <h5 class="mb-3">Change Password (Optional)</h5>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password">
                </div>
                <div class="col-md-4">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password">
                    <small class="text-muted">Password requirements: at least 8 characters, one uppercase letter, one number, and one special character.</small>
                </div>
                <div class="col-md-4">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                </div>
            </div>

            <div class="d-grid gap-2" style="margin-top: 50px;">
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </div>
        </form>

        <hr class="my-4">
        <h5 class="mb-3 text-danger">Delete Account</h5>
        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
            <div class="mb-3">
                <label for="delete_password" class="form-label">Enter your password to confirm account deletion</label>
                <input type="password" class="form-control" id="delete_password" name="delete_password" required>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" name="delete_account" class="btn btn-danger">Delete Account</button>
            </div>
        </form>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <hr class="my-4">
        <h5 class="mb-3">Create Admin Account</h5>
        
        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="new_admin_username" class="form-label required-field">New Admin Username</label>
                    <input type="text" class="form-control" id="new_admin_username" name="new_admin_username" required>
                </div>
                <div class="col-md-6">
                    <label for="new_admin_name" class="form-label required-field">New Admin Full Name</label>
                    <input type="text" class="form-control" id="new_admin_name" name="new_admin_name" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="new_admin_age" class="form-label required-field">Age</label>
                    <input type="number" class="form-control" id="new_admin_age" name="new_admin_age" min="1" max="120" required>
                </div>
                <div class="col-md-6">
                    <label for="new_admin_phone" class="form-label required-field">Phone Number</label>
                    <input type="tel" class="form-control" id="new_admin_phone" name="new_admin_phone" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="new_admin_email" class="form-label required-field">Email</label>
                <input type="email" class="form-control" id="new_admin_email" name="new_admin_email" required>
            </div>

            <div class="mb-3">
                <label for="new_admin_password" class="form-label required-field">Password</label>
                <input type="password" class="form-control" id="new_admin_password" name="new_admin_password" required>
                <small class="text-muted">Password requirements: at least 8 characters, one uppercase letter, one number, and one special character.</small>
            </div>

            <div class="d-grid gap-2" style="margin-top: 30px;">
                <button type="submit" name="create_admin" class="btn btn-success">Create Admin Account</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <?php require 'footer.php'; ?>
</body>
</html> 