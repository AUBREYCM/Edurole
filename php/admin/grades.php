<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM grades WHERE grade_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: grades.php?success=deleted");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grade'])) {
    $student_id      = $_POST['student_id'];
    $subject_id      = $_POST['subject_id'];
    $class_id        = $_POST['class_id'];
    $assessment_type = $_POST['assessment_type'];
    $assessment_name = trim($_POST['assessment_name']);
    $marks_obtained  = $_POST['marks_obtained'];
    $total_marks     = $_POST['total_marks'];
    $weight          = $_POST['weight'];
    $grade_date      = $_POST['grade_date'];
    $entered_by      = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO grades (student_id, subject_id, class_id, assessment_type, assessment_name, marks_obtained, total_marks, weight, grade_date, entered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $subject_id, $class_id, $assessment_type, $assessment_name, $marks_obtained, $total_marks, $weight, $grade_date, $entered_by]);
        header("Location: grades.php?success=added");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch grades
$grades = $pdo->query("
    SELECT g.*, 
           u.full_name as student_name,
           s.subject_name,
           c.class_name,
           ROUND((g.marks_obtained / g.total_marks) * 100, 1) as percentage
    FROM grades g
    JOIN users u ON g.student_id = u.user_id
    JOIN subjects s ON g.subject_id = s.subject_id
    JOIN classes c ON g.class_id = c.class_id
    ORDER BY g.grade_date DESC
")->fetchAll();

$students = $pdo->query("SELECT user_id, full_name FROM users WHERE role='student' AND status='active' ORDER BY full_name")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
$classes  = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();

// Grade letter function
function getGradeLetter($percentage) {
    if ($percentage >= 90) return ['A+', '#34a853'];
    if ($percentage >= 80) return ['A',  '#34a853'];
    if ($percentage >= 70) return ['B',  '#1a73e8'];
    if ($percentage >= 60) return ['C',  '#fa7b17'];
    if ($percentage >= 50) return ['D',  '#ff9800'];
    return ['F', '#ea4335'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grades — EduRole</title>
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
        .btn-danger { background: #ea4335; color: white; font-size: 12px; padding: 6px 12px; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; padding: 30px; border-radius: 10px; width: 550px; max-width: 95%; }
        .modal h3 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #1a73e8; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { background: #f0f2f5; color: #333; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow: hidden; }
        .card-header { padding: 18px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 16px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 13px; color: #666; font-weight: 600; border-bottom: 1px solid #eee; }
        td { padding: 12px 15px; font-size: 14px; color: #333; border-bottom: 1px solid #f5f5f5; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9f9f9; }
        .grade-badge { padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; color: white; display: inline-block; }
        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #e6f4ea; color: #34a853; }
        .alert-error { background: #fce8e6; color: #ea4335; }
        .empty-state { text-align: center; padding: 50px; color: #999; }
        .empty-state .icon { font-size: 48px; margin-bottom: 10px; }
        .search-bar { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; width: 250px; outline: none; }
        .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e8f0fe; color: #1a73e8; }
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
        <a href="grades.php" class="active"><span>📊</span> Grades</a>
        <a href="fees.php"><span>💰</span> Fee Management</a>
        <a href="staff.php"><span>👥</span> HR / Staff</a>
        <a href="notices.php"><span>📢</span> Notices</a>
        <a href="reports.php"><span>📈</span> Reports</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
</div>

<div class="main">
    <div class="topbar">
        <h2>📊 Grade Management</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
            + Add Grade
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'added') echo "✅ Grade added successfully!";
            if ($_GET['success'] == 'deleted') echo "🗑️ Grade deleted successfully!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>All Grades (<?php echo count($grades); ?>)</h3>
            <input type="text" class="search-bar" id="searchInput" placeholder="🔍 Search grades..." onkeyup="searchTable()">
        </div>
        <table id="gradesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Assessment</th>
                    <th>Marks</th>
                    <th>Percentage</th>
                    <th>Grade</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grades)): ?>
                    <tr>
                        <td colspan="10">
                            <div class="empty-state">
                                <div class="icon">📊</div>
                                <p>No grades entered yet. Click "Add Grade" to get started.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($grades as $i => $grade): ?>
                        <?php [$letter, $color] = getGradeLetter($grade['percentage']); ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($grade['student_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($grade['class_name']); ?></td>
                            <td>
                                <span class="badge"><?php echo ucfirst($grade['assessment_type']); ?></span>
                                <br><small><?php echo htmlspecialchars($grade['assessment_name']); ?></small>
                            </td>
                            <td><?php echo $grade['marks_obtained']; ?> / <?php echo $grade['total_marks']; ?></td>
                            <td><?php echo $grade['percentage']; ?>%</td>
                            <td>
                                <span class="grade-badge" style="background:<?php echo $color; ?>">
                                    <?php echo $letter; ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($grade['grade_date'])); ?></td>
                            <td>
                                <a href="?delete=<?php echo $grade['grade_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this grade?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Grade Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3>➕ Add Grade</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Student *</label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['user_id']; ?>">
                                <?php echo htmlspecialchars($student['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Class *</label>
                    <select name="class_id" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assessment Type *</label>
                    <select name="assessment_type" required>
                        <option value="assignment">Assignment</option>
                        <option value="test">Test</option>
                        <option value="exam">Exam</option>
                        <option value="activity">Activity</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Assessment Name *</label>
                <input type="text" name="assessment_name" required placeholder="e.g. Midterm Exam, Assignment 1">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Marks Obtained *</label>
                    <input type="number" name="marks_obtained" required placeholder="e.g. 75" min="0" step="0.5">
                </div>
                <div class="form-group">
                    <label>Total Marks *</label>
                    <input type="number" name="total_marks" required placeholder="e.g. 100" min="1" step="0.5">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Weight (%)</label>
                    <input type="number" name="weight" value="100" min="1" max="100">
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="grade_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="add_grade" class="btn btn-primary">Save Grade</button>
            </div>
        </form>
    </div>
</div>

<script>
function searchTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#gradesTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>