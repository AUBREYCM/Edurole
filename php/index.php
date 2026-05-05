<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

switch ($role) {
    case 'admin':
        header("Location: admin/dashboard.php");
        break;
    case 'teacher':
        header("Location: teacher/dashboard.php");
        break;
    case 'student':
        header("Location: students/dashboard.php");
        break;
    case 'parent':
        header("Location: parent/dashboard.php");
        break;
    default:
        header("Location: login.php");
        break;
}
exit();
?>