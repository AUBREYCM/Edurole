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
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $assessment_type = $_POST['assessment_type'];
    $assessment_name = trim($_POST['assessment_name']);
    $total_marks = $_POST['total_marks'];
    $grade_date = $_POST['grade_date'];
    $grades = $_POST['grades'] ?? [];
    
    foreach ($grades as $student_id => $marks_obtained) {
        if ($marks_obtained !== '') {
            $insert = $pdo->prepare("
                INSERT INTO grades (student_id, subject_id, class_id, assessment_type, assessment_name, marks_obtained, total_marks, grade_date, entered_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([$student_id, $subject_id, $class_id, $assessment_type, $assessment_name, $marks_obtained, $total_marks, $grade_date, $teacher_id]);
        }
    }
    
    header("Location: enter_grades.php?class_id=$class_id&subject_id=$subject_id&success=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Grades — EduRole</title>
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
            grid-template-columns: 1fr 1fr;
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
        input[type="number"] {
            width: 100px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .alert-success {
            background: #e6f4ea;
            color: #34a853;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .text-center { text-align: center; }
        .mt-3 { margin-top: 15px; }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>📝 Enter Grades</h1>
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
                <div>
                    <button type="submit" class="btn btn-primary">Load Students</button>
                </div>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">✅ Grades saved successfully!</div>
    <?php endif; ?>

    <?php if ($class_id && $subject_id && !empty($students)): ?>
        <div class="card">
            <h3>Enter Grades</h3>
            <form method="POST" action="">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                
                <div class="row">
                    <div class="form-group">
                        <label>Assessment Type</label>
                        <select name="assessment_type" required>
                            <option value="assignment">Assignment</option>
                            <option value="test">Test</option>
                            <option value="exam">Exam</option>
                            <option value="activity">Activity</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Assessment Name</label>
                        <input type="text" name="assessment_name" required placeholder="e.g. Midterm Exam">
                    </div>
                    <div class="form-group">
                        <label>Total Marks</label>
                        <input type="number" name="total_marks" required placeholder="e.g. 100" step="0.5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Grade Date</label>
                    <input type="date" name="grade_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <h3 style="margin: 20px 0 15px 0;">Student Marks</h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Marks Obtained</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td>
                                    <input type="number" name="grades[<?php echo $student['user_id']; ?>]" step="0.5" placeholder="0 - <?php echo $_POST['total_marks'] ?? 100; ?>" style="width: 120px;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="mt-3">
                    <button type="submit" name="save_grades" class="btn btn-success">💾 Save Grades</button>
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

</body>
</html>