<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get stats
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$totalClasses  = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$totalNotices  = $pdo->query("SELECT COUNT(*) FROM notices")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — EduRole</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #1a73e8;
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0;
            padding: 20px 0;
        }
        .sidebar .brand {
            text-align: center;
            padding: 10px 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .sidebar .brand h1 {
            color: white;
            font-size: 24px;
            letter-spacing: 2px;
        }
        .sidebar .brand p {
            color: rgba(255,255,255,0.7);
            font-size: 11px;
            margin-top: 4px;
        }
        .sidebar nav { margin-top: 20px; }
        .sidebar nav a {
            display: block;
            padding: 12px 25px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        .sidebar nav a span { margin-right: 10px; }

        /* Main content */
        .main {
            margin-left: 250px;
            padding: 30px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .topbar h2 { color: #333; font-size: 22px; }
        .topbar .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            font-size: 14px;
        }
        .topbar .logout {
            background: #ff4444;
            color: white;
            padding: 7px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
        }

        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            text-align: center;
        }
        .stat-card .number {
            font-size: 42px;
            font-weight: 800;
            color: #1a73e8;
        }
        .stat-card .label {
            color: #888;
            font-size: 13px;
            margin-top: 5px;
        }
        .stat-card.green .number { color: #34a853; }
        .stat-card.orange .number { color: #fa7b17; }
        .stat-card.red .number { color: #ea4335; }

        /* Quick actions */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            transition: transform 0.2s;
        }
        .action-card:hover { transform: translateY(-3px); }
        .action-card .icon { font-size: 28px; margin-bottom: 8px; }
        .action-card .text { font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <h1>EDUROLE</h1>
        <p>Admin Panel</p>
    </div>
    <nav>
        <a href="dashboard.php" class="active"><span>🏠</span> Dashboard</a>
        <a href="students.php"><span>🎓</span> Students</a>
        <a href="teachers.php"><span>👨‍🏫</span> Teachers</a>
        <a href="classes.php"><span>🏫</span> Classes</a>
        <a href="subjects.php"><span>📚</span> Subjects</a>
        <a href="timetable.php"><span>🗓️</span> Timetable</a>
        <a href="attendance.php"><span>✅</span> Attendance</a>
        <a href="grades.php"><span>📊</span> Grades</a>
        <a href="fees.php"><span>💰</span> Fee Management</a>
        <a href="staff.php"><span>👥</span> HR / Staff</a>
        <a href="notices.php"><span>📢</span> Notices</a>
        <a href="reports.php"><span>📈</span> Reports</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
</div>

<!-- Main Content -->
<div class="main">
    <div class="topbar">
        <h2>Dashboard Overview</h2>
        <div class="user-info">
            Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $totalStudents; ?></div>
            <div class="label">Total Students</div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo $totalTeachers; ?></div>
            <div class="label">Total Teachers</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo $totalClasses; ?></div>
            <div class="label">Total Classes</div>
        </div>
        <div class="stat-card red">
            <div class="number"><?php echo $totalNotices; ?></div>
            <div class="label">Notices Posted</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="actions-grid">
        <a href="students.php" class="action-card">
            <div class="icon">🎓</div>
            <div class="text">Manage Students</div>
        </a>
        <a href="teachers.php" class="action-card">
            <div class="icon">👨‍🏫</div>
            <div class="text">Manage Teachers</div>
        </a>
        <a href="classes.php" class="action-card">
            <div class="icon">🏫</div>
            <div class="text">Manage Classes</div>
        </a>
        <a href="fees.php" class="action-card">
            <div class="icon">💰</div>
            <div class="text">Fee Management</div>
        </a>
        <a href="timetable.php" class="action-card">
            <div class="icon">🗓️</div>
            <div class="text">Timetable</div>
        </a>
        <a href="grades.php" class="action-card">
            <div class="icon">📊</div>
            <div class="text">Grades</div>
        </a>
        <a href="notices.php" class="action-card">
            <div class="icon">📢</div>
            <div class="text">Post Notice</div>
        </a>
        <a href="reports.php" class="action-card">
            <div class="icon">📈</div>
            <div class="text">View Reports</div>
        </a>
    </div>
</div>

</body>
</html>