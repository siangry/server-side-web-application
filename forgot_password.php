<?php
require 'database.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists in users table - modified query to select email instead of id
        $check_stmt = mysqli_prepare($conn, "SELECT email FROM user WHERE email = ?");
        if (!$check_stmt) {
            $error = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($check_stmt, "s", $email);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($result) > 0) {
                // Generate token
                $token = bin2hex(random_bytes(32));
                
                // Delete any existing reset tokens for this email
                $delete_stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE email = ?");
                if (!$delete_stmt) {
                    $error = "Delete error: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($delete_stmt, "s", $email);
                    if (!mysqli_stmt_execute($delete_stmt)) {
                        $error = "Delete execute error: " . mysqli_stmt_error($delete_stmt);
                    }
                    mysqli_stmt_close($delete_stmt);
                }

                if (!$error) {
                    // Set timezone and calculate expiry time
                    date_default_timezone_set('Asia/Kuala_Lumpur');
                    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $insert_stmt = mysqli_prepare($conn, "INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
                    if (!$insert_stmt) {
                        $error = "Insert error: " . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($insert_stmt, "sss", $email, $token, $expires);
                        
                        if (mysqli_stmt_execute($insert_stmt)) {
                            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                            $success = "Password reset link has been generated. <br><a href='$resetLink' class='alert-link'>Click here to reset your password</a>";
                        } else {
                            $error = "Insert execute error: " . mysqli_stmt_error($insert_stmt);
                        }
                        mysqli_stmt_close($insert_stmt);
                    }
                }
            } else {
                $error = "No account found with this email address.";
            }
            mysqli_stmt_close($check_stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
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
            min-height: 300px;
            border-radius: 1rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        .alert-link {
            text-decoration: none;
            color: #0d6efd;
            font-weight: 500;
            font-size: 1.4rem;
        }
        .alert-link:hover {
            text-decoration: underline;
            color: #0a58ca;
        }
        .back-to-login {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.2s;
            font-size: 1.3rem;
            margin-top: 1.5rem;
            display: inline-block;
        }
        .back-to-login:hover {
            color: #0d6efd;
        }
        /* Add new styles for larger text */
        h4 {
            font-size: 2.5rem;
            margin-bottom: 2rem !important;
        }
        .form-label {
            font-size: 1.5rem;
        }
        .form-control {
            font-size: 1.4rem;
            padding: 0.75rem 1rem;
        }
        .btn {
            font-size: 1.5rem;
            padding: 0.75rem 1.5rem;
            margin-top: 1.5rem;
        }
        .alert {
            font-size: 1.4rem;
            padding: 1rem 1.5rem;
        }
    </style>
</head>
<body>
    <div class="card p-4">
        <h4 class="text-center mb-4">Forgot Password</h4>
        
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

        <?php if (!$success): ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="Enter your email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">Request Reset Link</button>
            </form>
        <?php endif; ?>
        
        <div class="text-center">
            <a href="login.php" class="back-to-login">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
