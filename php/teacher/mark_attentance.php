<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? $_POST['class_id'] ?? null;
$subject_id = $_GET['subject_id'] ?? $_POST['subject_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');
$message = '';
$students = [];

// Get teacher's classes and subjects
$classes = $pdo->prepare("
    SELECT DISTINCT c.class_id, c.class_name 
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.class_id
    WHERE ta.teacher_id = ?
");
$classes->execute([$teacher_id]);
$teacherClasses = $classes->fetchAll();

$subjects = $pdo->prepare("
    SELECT DISTINCT s.subject_id, s.subject_name 
    FROM teacher_assignments ta
    JOIN subjects s ON ta.subject_id = s.subject_id
    WHERE ta.teacher_id = ?
");
$subjects->execute([$teacher_id]);
$teacherSubjects = $subjects->fetchAll();

// If class and subject selected, get students
if ($class_id && $subject_id) {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name 
        FROM users u
        JOIN enrollments e ON u.user_id = e.student_id
        WHERE e.class_id = ? AND u.status = 'active'
        ORDER BY u.full_name
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
    
    // Get existing attendance for this date
    $existing = $pdo->prepare("
        SELECT student_id, status FROM attendance 
        WHERE class_id = ? AND subject_id = ? AND attendance_date = ?
    ");
    $existing->execute([$class_id, $subject_id, $date]);
    $existingAttendance = [];
    foreach ($existing->fetchAll() as $row) {
        $existingAttendance[$row['student_id']] = $row['status'];
    }
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $date = $_POST['date'];
    $attendance = $_POST['attendance'] ?? [];
    
    // Delete existing attendance for this date
    $delete = $pdo->prepare("DELETE FROM attendance WHERE class_id = ? AND subject_id = ? AND attendance_date = ?");
    $delete->execute([$class_id, $subject_id, $date]);
    
    // Insert new attendance
    foreach ($attendance as $student_id => $status) {
        $insert = $pdo->prepare("
            INSERT INTO attendance (student_id, class_id, subject_id, attendance_date, status, marked_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$student_id, $class_id, $subject_id, $date, $status, $teacher_id]);
    }
    
    $message = '<div class="alert-success">✅ Attendance saved successfully!</div>';
    
    // Refresh the page to show updated data
    header("Location: mark_attendance.php?class_id=$class_id&subject_id=$subject_id&date=$date&success=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance — EduRole</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }
        .header {
            background: #1a73e8;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 24px; }
        .back-btn {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h3 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-success { background: #34a853; color: white; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            border-bottom: 2px solid #ddd;
        }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .status-btns { display: flex; gap: 8px; flex-wrap: wrap; }
        .status-btn {
            padding: 6px 14px;
            border: 2px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            background: white;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .status-btn.present { border-color: #34a853; color: #34a853; }
        .status-btn.present.selected { background: #34a853; color: white; }
        .status-btn.absent { border-color: #ea4335; color: #ea4335; }
        .status-btn.absent.selected { background: #ea4335; color: white; }
        .status-btn.late { border-color: #fa7b17; color: #fa7b17; }
        .status-btn.late.selected { background: #fa7b17; color: white; }
        .status-btn.excused { border-color: #9c27b0; color: #9c27b0; }
        .status-btn.excused.selected { background: #9c27b0; color: white; }
        .alert-success {
            background: #e6f4ea;
            color: #34a853;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .summary {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .summary-item { font-size: 14px; font-weight: 600; }
        .mt-3 { margin-top: 15px; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <h1>📝 Mark Attendance</h1>
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
</div>

<div class="container">
    <div class="card">
        <h3>Select Class & Subject</h3>
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_id" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($teacherClasses as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>" <?php echo $class_id == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($teacherSubjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>" <?php echo $subject_id == $subject['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?php echo $date; ?>" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Load Students</button>
                </div>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">✅ Attendance saved successfully!</div>
    <?php endif; ?>

    <?php if ($class_id && $subject_id && !empty($students)): ?>
        <div class="card">
            <h3>Mark Attendance for <?php echo date('d F Y', strtotime($date)); ?></h3>
            <form method="POST" action="" id="attendanceForm">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <input type="hidden" name="date" value="<?php echo $date; ?>">
                
                <div class="summary" id="summary">
                    <span class="summary-item" id="presentCount">Present: 0</span>
                    <span class="summary-item" id="absentCount">Absent: 0</span>
                    <span class="summary-item" id="lateCount">Late: 0</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($students as $student): 
                            $currentStatus = $existingAttendance[$student['user_id']] ?? 'present';
                        ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
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
                
                <div class="mt-3">
                    <button type="submit" name="save_attendance" class="btn btn-success">💾 Save Attendance</button>
                </div>
            </form>
        </div>
    <?php elseif ($class_id && $subject_id && empty($students)): ?>
        <div class="card">
            <div class="text-center" style="padding: 40px;">
                <p>No students enrolled in this class yet.</p>
            </div>
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
    document.getElementById('presentCount').textContent = 'Present: ' + present;
    document.getElementById('absentCount').textContent = 'Absent: ' + absent;
    document.getElementById('lateCount').textContent = 'Late: ' + late;
}
updateSummary();
</script>

</body>
</html>