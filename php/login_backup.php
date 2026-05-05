<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRole — Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #827b9e98;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(113, 97, 97, 0.85);
            width: 100%;
            max-width: 400px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #5b7291;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 2px;
        }
        .logo p {
            color: #666;
            font-size: 13px;
            margin-top: 4px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }
        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbaaaab1;
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.3s;
            outline: none;
        }
        input:focus { border-color: #2d1727d0; }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #58a39e;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover { background: #1557b0; }
        .error {
            background: #fce8e6;
            color: #c5221f;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <h1>EDUROLE</h1>
        <p>Education Management Information System</p>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <?php
            $error = $_GET['error'];
            if ($error == 'invalid') echo "Invalid email or password. Please try again.";
            if ($error == 'inactive') echo "Your account is inactive. Contact administrator.";
            ?>
        </div>
    <?php endif; ?>

    <form action="auth.php" method="POST">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn-login">Login</button>
    </form>

    <div class="footer-text">
        &copy; 2026 EduRole — Powered by Corelink
    </div>
</div>
</body>
</html>