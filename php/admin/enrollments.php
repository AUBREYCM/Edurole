<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: enrollments.php?success=deleted");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    $student_id    = $_POST['student_id'];
    $class_id      = $_POST['class_id'];
    $programme_id  = $_POST['programme_id'];
    $enrollment_date = date('Y-m-d');

    try {
        // Check if already enrolled
        $check = $pdo->prepare("SELECT * FROM enrollments WHERE student_id=? AND class_id=?");
        $check->execute([$student_id, $class_id]);
        if ($check->fetch()) {
            $error = "This student is already enrolled in that class.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, class_id, programme_id, enrollment_date, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$student_id, $class_id, $programme_id, $enrollment_date]);
            header("Location: enrollments.php?success=enrolled");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch enrollments
$enrollments = $pdo->query("
    SELECT e.*, u.full_name as student_name, u.email,
           c.class_name, p.programme_name
    FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    JOIN classes c ON e.class_id = c.class_id
    JOIN programmes p ON e.programme_id = p.programme_id
    ORDER BY e.enrollment_date DESC
")->fetchAll();

$students   = $pdo->query("SELECT user_id, full_name, email FROM users WHERE role='student' AND status='active' ORDER BY full_name")->fetchAll();
$classes    = $pdo->query("SELECT c.*, p.programme_name, p.programme_id FROM classes c JOIN programmes p ON c.programme_id = p.programme_id ORDER BY c.class_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enrollments — EduRole</title>
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
        .modal { background: white; padding: 30px; border-radius: 10px; width: 500px; max-width: 95%; }
        .modal h3 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: #555; }
        .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group select:focus { border-color: #1a73e8; }
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
        .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-active { background: #e6f4ea; color: #34a853; }
        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #e6f4ea; color: #34a853; }
        .alert-error { background: #fce8e6; color: #ea4335; }
        .empty-state { text-align: center; padding: 50px; color: #999; }
        .empty-state .icon { font-size: 48px; margin-bottom: 10px; }
        .search-bar { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; width: 250px; outline: none; }
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
        <a href="enrollments.php" class="active"><span>📋</span> Enrollments</a>
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

<div class="main">
    <div class="topbar">
        <h2>📋 Student Enrollments</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
            + Enroll Student
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'enrolled') echo "✅ Student enrolled successfully!";
            if ($_GET['success'] == 'deleted') echo "🗑️ Enrollment removed successfully!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>All Enrollments (<?php echo count($enrollments); ?>)</h3>
            <input type="text" class="search-bar" id="searchInput" placeholder="🔍 Search..." onkeyup="searchTable()">
        </div>
        <table id="enrollTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Class</th>
                    <th>Programme</th>
                    <th>Date Enrolled</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($enrollments)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="icon">📋</div>
                                <p>No enrollments yet. Click "Enroll Student" to get started.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enrollments as $i => $enroll): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($enroll['student_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($enroll['email']); ?></td>
                            <td><?php echo htmlspecialchars($enroll['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($enroll['programme_name']); ?></td>
                            <td><?php echo date('d M Y', strtotime($enroll['enrollment_date'])); ?></td>
                            <td><span class="badge badge-active"><?php echo ucfirst($enroll['status']); ?></span></td>
                            <td>
                                <a href="?delete=<?php echo $enroll['enrollment_id']; ?>" class="btn btn-danger" onclick="return confirm('Remove this enrollment?')">Remove</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Enroll Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3>📋 Enroll Student</h3>
        <form method="POST">
            <div class="form-group">
                <label>Student *</label>
                <select name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['user_id']; ?>">
                            <?php echo htmlspecialchars($student['full_name']); ?> — <?php echo $student['email']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Class *</label>
                <select name="class_id" required onchange="setProgramme(this)">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>" data-programme="<?php echo $class['programme_id']; ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?> — <?php echo $class['programme_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="programme_id" id="programme_id" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="enroll" class="btn btn-primary">Enroll Student</button>
            </div>
        </form>
    </div>
</div>

<script>
function setProgramme(select) {
    const selected = select.options[select.selectedIndex];
    document.getElementById('programme_id').value = selected.dataset.programme;
}
function searchTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#enrollTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>