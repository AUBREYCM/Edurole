<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Call Java API for authentication
    $ch = curl_init('http://localhost:8081/api/auth/login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'password' => $password]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($result['success'] == true) {
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['full_name'] = $result['full_name'];
        $_SESSION['email'] = $result['email'];
        $_SESSION['role'] = $result['role'];
        
        // Redirect based on role
        switch ($result['role']) {
            case 'admin':
                header("Location: admin/dashboard.php");
                break;
            case 'teacher':
                header("Location: teacher/dashboard.php");
                break;
            case 'student':
                header("Location: student/dashboard.php");
                break;
            default:
                header("Location: login.php");
        }
        exit();
    } else {
        $error = $result['error'] ?? "Invalid credentials";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>EduRole - Java Auth Login</title>
    <style>
        body { font-family: Arial; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { background: white; padding: 40px; border-radius: 10px; width: 350px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        button { width: 100%; padding: 10px; background: #1a73e8; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .error { color: red; margin-bottom: 10px; }
        h2 { color: #1a73e8; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>EDUROLE</h2>
        <p>Java-Powered Login</p>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login (via Java)</button>
        </form>
        <p style="font-size: 12px; margin-top: 15px;">Powered by Java Auth Server on port 8081</p>
    </div>
</body>
</html>