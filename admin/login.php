<?php
// admin/login.php - Improved version with logo
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'lmtadmin') {
        header('Location: ../lmt/lmtadmin.php');
    } elseif ($_SESSION['role'] === 'bmtadmin') {
        header('Location: ../bmt/bmtadmin.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                if ($user['role'] == 'lmtadmin') {
                    header('Location: ../lmt/lmtadmin.php');
                } else {
                    header('Location: ../bmt/bmtadmin.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password';
                error_log("Failed login attempt for username: $username from " . $_SERVER['REMOTE_ADDR']);
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
            error_log("Login database error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ET TV</title>
    <link rel="icon" type="image/png" href="../img/ethiopian_logo.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .login-container {
            background: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 420px;
            max-width: 90%;
            text-align: center;
        }

        .logo-wrapper {
            margin-bottom: 25px;
            display: flex;
            justify-content: center;
        }

        .logo {
            max-width: 320px;
            height: auto;
            display: block;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
            font-size: 24px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid #c62828;
        }

        .info {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }

        .test-link {
            text-align: center;
            margin-top: 12px;
        }

        .test-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 12px;
        }

        .test-link a:hover {
            text-decoration: underline;
        }

        .login-footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 25px;
                width: 95%;
            }

            .logo {
                max-width: 90px;
            }

            h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            input,
            button {
                padding: 10px 12px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Logo at the top of login container -->
        <div class="logo-wrapper">
            <img src="../img/logo.png" alt="Logo" class="logo" onerror="this.style.display='none'">
        </div>

        <h2>ET TV Admin Login</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="test-link">
            <a href="/admin/forgot.php">Forgot password?</a>
        </div>
        <div class="info">
            Secure Admin Access Only
        </div>
    </div>

    <div class="login-footer">
        © 2026 Ethiopian Airlines. All rights reserved.
    </div>
</body>

</html>