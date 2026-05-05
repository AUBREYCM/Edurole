<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $class_id      = $_POST['class_id'];
    $subject_id    = $_POST['subject_id'];
    $date          = $_POST['date'];
    $marked_by     = $_SESSION['user_id'];
    $attendance    = $_POST['attendance'];

    try {
        // Delete existing attendance for same class/subject/date
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE class_id=? AND subject_id=? AND attendance_date=?");
        $stmt->execute([$class_id, $subject_id, $date]);

        // Insert new records
        foreach ($attendance as $student_id => $status) {
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, class_id, subject_id, attendance_date, status, marked_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $class_id, $subject_id, $date, $status, $marked_by]);
        }
        header("Location: attendance.php?success=saved");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch classes and subjects for filters
$classes  = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();

// Fetch students for selected class
$students = [];
$selected_class   = $_GET['class_id'] ?? '';
$selected_subject = $_GET['subject_id'] ?? '';
$selected_date    = $_GET['date'] ?? date('Y-m-d');

if ($selected_class) {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.email 
        FROM users u 
        JOIN enrollments e ON u.user_id = e.student_id 
        WHERE e.class_id = ? AND u.status = 'active'
        ORDER BY u.full_name
    ");
    $stmt->execute([$selected_class]);
    $students = $stmt->fetchAll();
}

// Fetch existing attendance for selected class/subject/date
$existing = [];
if ($selected_class && $selected_subject && $selected_date) {
    $stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE class_id=? AND subject_id=? AND attendance_date=?");
    $stmt->execute([$selected_class, $selected_subject, $selected_date]);
    foreach ($stmt->fetchAll() as $row) {
        $existing[$row['student_id']] = $row['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance — EduRole</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }
        .sidebar { width: 250px; background: #1a73e8; min-height: 100vh; position: fixed; top: 0; left: 0; padding: 20px 0; }
        .sidebar .brand { text-align: center; padding: 10px 20px 30px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .brand h1 { color: white; font-size: 24px; letter-spacing: 2px; }
        .sidebar .brand p { color: rgba(255,255,255,0.7); font-size: 11px; margin-top: 4px; }
        .sidebar nav { margin-top: 20px; }
        .sidebar nav a { display: block; padding: 12px 25px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: background 0.2s; }
        .sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.15); color: white; }
        .sidebar nav a span { margin-right: 10px; }
        .main { margin-left: 250px; padding: 30px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .topbar h2 { color: #333; font-size: 22px; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-primary:hover { background: #1557b0; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); padding: 25px; margin-bottom: 25px; }
        .card h3 { font-size: 16px; color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; align-items: end; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #1a73e8; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 13px; color: #666; font-weight: 600; border-bottom: 1px solid #eee; }
        td { padding: 12px 15px; font-size: 14px; color: #333; border-bottom: 1px solid #f5f5f5; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9f9f9; }
        .status-btns { display: flex; gap: 8px; }
        .status-btn { padding: 6px 14px; border: 2px solid #ddd; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: 600; background: white; transition: all 0.2s; }
        .status-btn.present { border-color: #34a853; color: #34a853; }
        .status-btn.present.selected { background: #34a853; color: white; }
        .status-btn.absent { border-color: #ea4335; color: #ea4335; }
        .status-btn.absent.selected { background: #ea4335; color: white; }
        .status-btn.late { border-color: #fa7b17; color: #fa7b17; }
        .status-btn.late.selected { background: #fa7b17; color: white; }
        .status-btn.excused { border-color: #9c27b0; color: #9c27b0; }
        .status-btn.excused.selected { background: #9c27b0; color: white; }
        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #e6f4ea; color: #34a853; }
        .alert-error { background: #fce8e6; color: #ea4335; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state .icon { font-size: 48px; margin-bottom: 10px; }
        .summary { display: flex; gap: 15px; margin-bottom: 20px; }
        .summary-item { padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .summary-item.present { background: #e6f4ea; color: #34a853; }
        .summary-item.absent { background: #fce8e6; color: #ea4335; }
        .summary-item.late { background: #fff3e0; color: #fa7b17; }
        input[type="hidden"] { display: none; }
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
        <a href="timetable.php"><span>🗓️</span> Timetable</a>
        <a href="attendance.php" class="active"><span>✅</span> Attendance</a>
        <a href="grades.php"><span>📊</span> Grades</a>
        <a href="fees.php"><span>💰</span> Fee Management</a>
        <a href="staff.php"><span>👥</span> HR / Staff</a>
        <a href="notices.php"><span>📢</span> Notices</a>
        <a href="reports.php"><span>📈</span> Reports</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
</div>

<div class="main">
    <div class="topbar">
        <h2>✅ Attendance Management</h2>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ Attendance saved successfully!</div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card">
        <h3>🔍 Select Class, Subject & Date</h3>
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Class *</label>
                    <select name="class_id" required onchange="this.form.submit()">
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>" <?php echo $selected_class == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>" <?php echo $selected_subject == $subject['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" value="<?php echo $selected_date; ?>" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width:100%">Load Students</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Attendance Table -->
    <?php if ($selected_class && $selected_subject): ?>
    <div class="card">
        <h3>📋 Mark Attendance — <?php echo $selected_date; ?></h3>

        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="icon">🎓</div>
                <p>No students enrolled in this class yet. Go to <a href="enrollments.php">Enrollments</a> to add students.</p>
            </div>
        <?php else: ?>
            <div class="summary" id="summary">
                <span class="summary-item present" id="count-present">Present: 0</span>
                <span class="summary-item absent" id="count-absent">Absent: 0</span>
                <span class="summary-item late" id="count-late">Late: 0</span>
            </div>
            <form method="POST">
                <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $student): ?>
                            <?php $currentStatus = $existing[$student['user_id']] ?? 'present'; ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <input type="hidden" name="attendance[<?php echo $student['user_id']; ?>]" id="status_<?php echo $student['user_id']; ?>" value="<?php echo $currentStatus; ?>">
                                    <div class="status-btns">
                                        <button type="button" class="status-btn present <?php echo $currentStatus === 'present' ? 'selected' : ''; ?>" onclick="setStatus(<?php echo $student['user_id']; ?>, 'present', this)">Present</button>
                                        <button type="button" class="status-btn absent <?php echo $currentStatus === 'absent' ? 'selected' : ''; ?>" onclick="setStatus(<?php echo $student['user_id']; ?>, 'absent', this)">Absent</button>
                                        <button type="button" class="status-btn late <?php echo $currentStatus === 'late' ? 'selected' : ''; ?>" onclick="setStatus(<?php echo $student['user_id']; ?>, 'late', this)">Late</button>
                                        <button type="button" class="status-btn excused <?php echo $currentStatus === 'excused' ? 'selected' : ''; ?>" onclick="setStatus(<?php echo $student['user_id']; ?>, 'excused', this)">Excused</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <br>
                <button type="submit" name="save_attendance" class="btn btn-primary">💾 Save Attendance</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function setStatus(studentId, status, btn) {
    document.getElementById('status_' + studentId).value = status;
    const btns = btn.parentElement.querySelectorAll('.status-btn');
    btns.forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    updateSummary();
}

function updateSummary() {
    let present = 0, absent = 0, late = 0;
    document.querySelectorAll('input[name^="attendance"]').forEach(input => {
        if (input.value === 'present') present++;
        else if (input.value === 'absent') absent++;
        else if (input.value === 'late') late++;
    });
    document.getElementById('count-present').textContent = 'Present: ' + present;
    document.getElementById('count-absent').textContent = 'Absent: ' + absent;
    document.getElementById('count-late').textContent = 'Late: ' + late;
}
updateSummary();
</script>
</body>
</html>