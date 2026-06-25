<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// admin/login.php - Improved version with logo and session handling
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'lmtadmin') {
        header('Location: ../lmt/lmtadmin.php');
    } elseif ($_SESSION['role'] === 'bmtadmin') {
        header('Location: ../bmt/bmtadmin.php');
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/admin.php');
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
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                if ($user['role'] === 'lmtadmin') {
                    header('Location: ../lmt/lmtadmin.php');
                } elseif ($user['role'] === 'bmtadmin') {
                    header('Location: ../bmt/bmtadmin.php');
                } elseif ($user['role'] === 'admin') {
                    header('Location: ../admin/admin.php');
                } else
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
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(118, 75, 162, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 40px 35px;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
            width: 420px;
            max-width: 92%;
            text-align: center;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }

        .logo-wrapper {
            margin-bottom: 25px;
            display: flex;
            justify-content: center;
        }

        .logo {
            max-width: 280px;
            height: auto;
            display: block;
        }

        h2 {
            text-align: center;
            margin-bottom: 8px;
            color: #1a1a2e;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .subtitle {
            text-align: center;
            color: #888;
            font-size: 13px;
            margin-bottom: 25px;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper .icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 16px;
            pointer-events: none;
        }

        input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            color: #333;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        input::placeholder {
            color: #bbb;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        button::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        button:hover::after {
            left: 100%;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid #fecaca;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .error::before {
            content: '⚠️';
            font-size: 16px;
        }

        .links {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            font-size: 13px;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .info {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #aaa;
            border-top: 1px solid #eee;
            padding-top: 18px;
        }

        .login-footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            z-index: 0;
        }

        .login-footer a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
        }

        .login-footer a:hover {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: underline;
        }

        /* Demo credentials hint */
        .demo-hint {
            font-size: 12px;
            color: #999;
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px dashed #ddd;
        }

        .demo-hint code {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #667eea;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                width: 95%;
            }

            .logo {
                max-width: 200px;
            }

            h2 {
                font-size: 22px;
            }

            input {
                padding: 10px 12px 10px 38px;
                font-size: 14px;
            }

            button {
                padding: 12px;
                font-size: 15px;
            }

            .links {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Logo at the top of login container -->
        <div class="logo-wrapper">
            <img src="../img/ethiopian_logo.ico" alt="Logo" class="logo"
                onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'font-size:60px;\'>✈️</div>';">
        </div>

        <h2>Welcome Back</h2>
        <p class="subtitle">Sign in to manage your TV display content</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>👤 Username</label>
                <div class="input-wrapper">
                    <span class="icon">👤</span>
                    <input type="text" name="username" placeholder="Enter your username" required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label>🔒 Password</label>
                <div class="input-wrapper">
                    <span class="icon">🔑</span>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            <button type="submit">🚀 Sign In</button>
        </form>

        <div class="links">
            <a href="/admin/forgot.php">Forgot password?</a>
            <span style="color:#ddd;">|</span>
            <a href="/">← Back to TV Display</a>
        </div>

        <div class="demo-hint">
            💡 Default credentials: <code>lmtadmin</code> / <code>admin123</code>
        </div>

        <div class="info">
            🔐 Secure Admin Access • All connections are encrypted
        </div>
    </div>

    <div class="login-footer">
        © <?php echo date('Y'); ?> Ethiopian Airlines TV Display System •
        <a href="/privacy.php">Privacy</a> •
        <a href="/terms.php">Terms</a>
    </div>
</body>

</html>