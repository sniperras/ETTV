<?php
// admin/forgot.php - Password reset request page
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ET TV</title>
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

        .forgot-container {
            background: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 420px;
            max-width: 90%;
            text-align: center;
        }

        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 24px;
        }

        .message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 15px;
        }

        .email-link {
            display: inline-block;
            background: #f0f0f0;
            padding: 12px 20px;
            border-radius: 10px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin: 15px 0;
            transition: all 0.3s ease;
        }

        .email-link:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #999;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: #667eea;
            text-decoration: underline;
        }

        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }

        .footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
        }

        @media (max-width: 480px) {
            .forgot-container {
                padding: 30px 25px;
            }

            .icon {
                font-size: 48px;
            }

            h2 {
                font-size: 20px;
            }

            .message {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
    <div class="forgot-container">
        <div class="icon">🔐</div>
        <h2>Forgot Password?</h2>
        <div class="message">
            Please contact the system administrator to reset your password.
        </div>

        <a href="mailto:Natnaelbizuneh@ethiopianairlines.com" class="email-link">
            📧 Natnaelbizuneh@ethiopianairlines.com
        </a>

        <div class="message" style="font-size: 13px; margin-top: 10px;">
            Or contact via Microsoft Teams
        </div>

        <hr>

        <a href="login.php" class="back-link">← Back to Login</a>
    </div>

    <div class="footer">
        © 2026 Ethiopian Airlines. All rights reserved.
    </div>
</body>

</html>