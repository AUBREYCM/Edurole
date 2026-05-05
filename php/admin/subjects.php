<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: subjects.php?success=deleted");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);
    $programme_id = trim($_POST['programme_id']);
    $credits      = trim($_POST['credits']);

    try {
        $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, subject_code, programme_id, credits) VALUES (?, ?, ?, ?)");
        $stmt->execute([$subject_name, $subject_code, $programme_id, $credits]);
        header("Location: subjects.php?success=added");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$subjects = $pdo->query("
    SELECT s.*, p.programme_name 
    FROM subjects s 
    LEFT JOIN programmes p ON s.programme_id = p.programme_id 
    ORDER BY s.subject_id DESC
")->fetchAll();

$programmes = $pdo->query("SELECT * FROM programmes WHERE status='active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subjects — EduRole</title>
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
        .btn-danger { background: #ea4335; color: white; font-size: 12px; padding: 6px 12px; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; padding: 30px; border-radius: 10px; width: 500px; max-width: 95%; }
        .modal h3 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; }
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
        <a href="subjects.php" class="active"><span>📚</span> Subjects</a>
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
        <h2>📚 Subject Management</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
            + Add New Subject
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'added') echo "✅ Subject added successfully!";
            if ($_GET['success'] == 'deleted') echo "🗑️ Subject deleted successfully!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>All Subjects (<?php echo count($subjects); ?>)</h3>
            <input type="text" class="search-bar" id="searchInput" placeholder="🔍 Search subjects..." onkeyup="searchTable()">
        </div>
        <table id="subjectsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject Name</th>
                    <th>Subject Code</th>
                    <th>Programme</th>
                    <th>Credits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="icon">📚</div>
                                <p>No subjects added yet. Click "Add New Subject" to get started.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $i => $subject): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                            <td><span class="badge"><?php echo htmlspecialchars($subject['subject_code'] ?? '—'); ?></span></td>
                            <td><?php echo htmlspecialchars($subject['programme_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($subject['credits']); ?> Credit(s)</td>
                            <td>
                                <a href="?delete=<?php echo $subject['subject_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this subject?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3>➕ Add New Subject</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Subject Name *</label>
                    <input type="text" name="subject_name" required placeholder="e.g. Mathematics">
                </div>
                <div class="form-group">
                    <label>Subject Code *</label>
                    <input type="text" name="subject_code" required placeholder="e.g. MATH101">
                </div>
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
                    <label>Credits *</label>
                    <select name="credits" required>
                        <option value="1">1 Credit</option>
                        <option value="2">2 Credits</option>
                        <option value="3" selected>3 Credits</option>
                        <option value="4">4 Credits</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="add_subject" class="btn btn-primary">Add Subject</button>
            </div>
        </form>
    </div>
</div>

<script>
function searchTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#subjectsTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>