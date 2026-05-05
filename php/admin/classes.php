<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: classes.php?success=deleted");
    exit();
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $class_name    = trim($_POST['class_name']);
    $programme_id  = trim($_POST['programme_id']);
    $year_level    = trim($_POST['year_level']);
    $academic_year = trim($_POST['academic_year']);

    try {
        $stmt = $pdo->prepare("INSERT INTO classes (class_name, programme_id, year_level, academic_year) VALUES (?, ?, ?, ?)");
        $stmt->execute([$class_name, $programme_id, $year_level, $academic_year]);
        header("Location: classes.php?success=added");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch classes with programme name
$classes = $pdo->query("
    SELECT c.*, p.programme_name 
    FROM classes c 
    LEFT JOIN programmes p ON c.programme_id = p.programme_id 
    ORDER BY c.academic_year DESC, c.class_name ASC
")->fetchAll();

// Fetch programmes for dropdown
$programmes = $pdo->query("SELECT * FROM programmes WHERE status='active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes — EduRole</title>
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
        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #e6f4ea; color: #34a853; }
        .alert-error { background: #fce8e6; color: #ea4335; }
        .empty-state { text-align: center; padding: 50px; color: #999; }
        .empty-state .icon { font-size: 48px; margin-bottom: 10px; }
        .notice { background: #fff8e1; border-left: 4px solid #fa7b17; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; color: #555; }
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
        <a href="classes.php" class="active"><span>🏫</span> Classes</a>
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

<div class="main">
    <div class="topbar">
        <h2>🏫 Class Management</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
            + Add New Class
        </button>
    </div>

    <?php if (empty($programmes)): ?>
        <div class="notice">
            ⚠️ No programmes found. Please <a href="programmes.php">add a programme</a> first before creating classes.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'added') echo "✅ Class added successfully!";
            if ($_GET['success'] == 'deleted') echo "🗑️ Class deleted successfully!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>All Classes (<?php echo count($classes); ?>)</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Class Name</th>
                    <th>Programme</th>
                    <th>Year Level</th>
                    <th>Academic Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classes)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="icon">🏫</div>
                                <p>No classes created yet. Click "Add New Class" to get started.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classes as $i => $class): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($class['programme_name'] ?? '—'); ?></td>
                            <td>Year <?php echo htmlspecialchars($class['year_level']); ?></td>
                            <td><?php echo htmlspecialchars($class['academic_year']); ?></td>
                            <td>
                                <a href="?delete=<?php echo $class['class_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this class? This cannot be undone.')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3>➕ Add New Class</h3>
        <form method="POST">
            <div class="form-group">
                <label>Class Name *</label>
                <input type="text" name="class_name" required placeholder="e.g. Grade 10A, Year 2 Science">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Programme *</label>
                    <select name="programme_id" required>
                        <option value="">-- Select Programme --</option>
                        <?php foreach ($programmes as $prog): ?>
                            <option value="<?php echo $prog['programme_id']; ?>">
                                <?php echo htmlspecialchars($prog['programme_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year Level *</label>
                    <select name="year_level" required>
                        <option value="">-- Select Year --</option>
                        <option value="1">Year 1</option>
                        <option value="2">Year 2</option>
                        <option value="3">Year 3</option>
                        <option value="4">Year 4</option>
                        <option value="5">Year 5</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Academic Year *</label>
                <input type="text" name="academic_year" required placeholder="e.g. 2025/2026" value="2025/2026">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="add_class" class="btn btn-primary">Add Class</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>