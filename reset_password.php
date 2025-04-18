<?php
require 'database.php';

$token = $_GET['token'] ?? '';
$valid = false;
$error = '';
$success = '';

// Validate token first
if ($token) {
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires > CURRENT_TIMESTAMP");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid = $result->num_rows > 0;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate password
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match("/[a-z]/", $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $error = "Password must contain at least one number.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $newPassword = md5($password);

        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires > CURRENT_TIMESTAMP");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $email = $row['email'];

            // Update password
            $stmt = $conn->prepare("UPDATE user SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $newPassword, $email);
            $stmt->execute();

            // Clear reset token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $success = "Password reset successful. <a href='login.php'>Login now</a>";
        } else {
            $error = "Invalid or expired token.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #667eea, #764ba2);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .card {
            width: 100%;
            max-width: 550px;
            border-radius: 1rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        .card h4 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
        }
        .form-control {
            font-size: 1.2rem;
            padding: 0.8rem 1rem;
            height: auto;
        }
        .btn {
            font-size: 1.2rem;
            padding: 0.8rem 1rem;
        }
        .password-requirements {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .requirement {
            margin-bottom: 0.6rem;
            color: #6c757d;
            font-size: 1rem;
        }
        .requirement i {
            color: #198754;
            margin-right: 0.6rem;
            font-size: 1rem;
        }
        .back-to-login {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.2s;
            font-size: 1.2rem;
        }
        .back-to-login:hover {
            color: #0d6efd;
        }
        .alert {
            font-size: 1.2rem;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="card p-4">
        <h4 class="text-center mb-4">Reset Password</h4>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= $success ?>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (!$success && $valid): ?>
            <div class="password-requirements">
                <div class="requirement">
                    <i class="bi bi-check-circle-fill"></i>
                    Be at least 8 characters long
                </div>
                <div class="requirement">
                    <i class="bi bi-check-circle-fill"></i>
                    Contain at least one uppercase letter
                </div>
                <div class="requirement">
                    <i class="bi bi-check-circle-fill"></i>
                    Contain at least one lowercase letter
                </div>
                <div class="requirement">
                    <i class="bi bi-check-circle-fill"></i>
                    Contain at least one number
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter new password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="btn btn-success w-100 mb-3">
                    <i class="bi bi-key-fill me-2"></i>Reset Password
                </button>
            </form>
        <?php elseif (!$valid): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                Invalid or expired reset link. Please request a new password reset.
            </div>
            <a href="forgot_password.php" class="btn btn-primary w-100">
                <i class="bi bi-arrow-left me-2"></i>Request New Reset Link
            </a>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="login.php" class="back-to-login">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
