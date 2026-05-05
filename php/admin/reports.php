<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$students = $pdo->query("SELECT user_id, full_name FROM users WHERE role='student' AND status='active' ORDER BY full_name")->fetchAll();
$classes = $pdo->query("SELECT class_id, class_name FROM classes ORDER BY class_name")->fetchAll();

$reportGenerated = false;
$reportFile = '';
$reportError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_student_report'])) {
        $student_id = $_POST['student_id'];
        
        // Call Python script
        $command = "python ../python/generate_report.py student $student_id 2>&1";
        $output = shell_exec($command);
        
        if ($output && trim($output)) {
            $reportFile = trim($output);
            $reportGenerated = true;
        } else {
            $reportError = "Failed to generate report. Make sure Python is installed.";
        }
    }
    
    if (isset($_POST['generate_attendance_report'])) {
        $class_id = $_POST['class_id'];
        
        $command = "python ../python/generate_report.py attendance $class_id 2>&1";
        $output = shell_exec($command);
        
        if ($output && trim($output)) {
            $reportFile = trim($output);
            $reportGenerated = true;
        } else {
            $reportError = "Failed to generate report. Make sure Python is installed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports — EduRole</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }
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
        .sidebar .brand h1 { color: white; font-size: 24px; letter-spacing: 2px; }
        .sidebar .brand p { color: rgba(255,255,255,0.7); font-size: 11px; margin-top: 4px; }
        .sidebar nav { margin-top: 20px; }
        .sidebar nav a {
            display: block;
            padding: 12px 25px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        .sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.15); color: white; }
        .sidebar nav a span { margin-right: 10px; }
        .main {
            margin-left: 250px;
            padding: 30px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .topbar h2 { color: #333; font-size: 22px; }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            padding: 25px;
            margin-bottom: 25px;
        }
        .card h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1a73e8;
            display: inline-block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #555;
        }
        .form-group select {
            width: 100%;
            max-width: 400px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-primary:hover { background: #1557b0; }
        .btn-success { background: #34a853; color: white; }
        .alert {
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #e6f4ea; color: #34a853; }
        .alert-error { background: #fce8e6; color: #ea4335; }
        .report-link {
            margin-top: 15px;
            padding: 15px;
            background: #e8f0fe;
            border-radius: 6px;
        }
        .report-link a {
            color: #1a73e8;
            text-decoration: none;
            font-weight: 600;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand">
        <h1>EDUROLE</h1>
        <p>Admin Panel</p>
    </div>
    <nav>
        <a href="dashboard.php"><span>🏠</span> Dashboard</a>
        <a href="students.php"><span>🎓</span> Students</a>
        <a href="teachers.php"><span>👨‍🏫</span> Teachers</a>
        <a href="classes.php"><span>🏫</span> Classes</a>
        <a href="programmes.php"><span>🎯</span> Programmes</a>
        <a href="subjects.php"><span>📚</span> Subjects</a>
        <a href="enrollments.php"><span>📋</span> Enrollments</a>
        <a href="timetable.php"><span>🗓️</span> Timetable</a>
        <a href="attendance.php"><span>✅</span> Attendance</a>
        <a href="grades.php"><span>📊</span> Grades</a>
        <a href="fees.php"><span>💰</span> Fee Management</a>
        <a href="staff.php"><span>👥</span> HR / Staff</a>
        <a href="notices.php"><span>📢</span> Notices</a>
        <a href="reports.php" class="active"><span>📈</span> Reports</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
</div>

<div class="main">
    <div class="topbar">
        <h2>📈 Reports & Analytics</h2>
    </div>

    <?php if ($reportError): ?>
        <div class="alert alert-error">❌ <?php echo $reportError; ?></div>
    <?php endif; ?>

    <?php if ($reportGenerated && $reportFile): ?>
        <div class="alert alert-success">
            ✅ Report generated successfully!
        </div>
        <div class="report-link">
            📄 <a href="../reports/<?php echo basename($reportFile); ?>" target="_blank">Click here to download your report</a>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Student Transcript Report -->
        <div class="card">
            <h3>📊 Student Transcript</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Student</label>
                    <select name="student_id" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['user_id']; ?>"><?php echo htmlspecialchars($student['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="generate_student_report" class="btn btn-primary">Generate Transcript (PDF)</button>
            </form>
            <p style="margin-top: 15px; font-size: 12px; color: #888;">
                <small>📝 Generates a complete academic transcript with all grades, subject averages, and final grade.</small>
            </p>
        </div>

        <!-- Attendance Summary Report -->
        <div class="card">
            <h3>✅ Attendance Summary</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Class</label>
                    <select name="class_id" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="generate_attendance_report" class="btn btn-primary">Generate Attendance Report (PDF)</button>
            </form>
            <p style="margin-top: 15px; font-size: 12px; color: #888;">
                <small>📝 Shows attendance percentage for all students in the selected class.</small>
            </p>
        </div>
    </div>

    <!-- Dashboard Stats Card -->
    <div class="card">
        <h3>📈 Quick Stats</h3>
        <?php
        $totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
        $totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
        $totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
        $totalGrades = $pdo->query("SELECT COUNT(*) FROM grades")->fetchColumn();
        ?>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; text-align: center;">
            <div><strong style="font-size: 28px; color: #1a73e8;"><?php echo $totalStudents; ?></strong><br>Students</div>
            <div><strong style="font-size: 28px; color: #34a853;"><?php echo $totalTeachers; ?></strong><br>Teachers</div>
            <div><strong style="font-size: 28px; color: #fa7b17;"><?php echo $totalClasses; ?></strong><br>Classes</div>
            <div><strong style="font-size: 28px; color: #9c27b0;"><?php echo $totalGrades; ?></strong><br>Grades Entered</div>
        </div>
    </div>
</div>

</body>
</html>