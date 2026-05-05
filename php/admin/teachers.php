<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['deactivate'])) {
    $stmt = $pdo->prepare("UPDATE users SET status='inactive' WHERE user_id=? AND role='teacher'");
    $stmt->execute([$_GET['deactivate']]);
    header("Location: teachers.php?success=deactivated");
    exit();
}

if (isset($_GET['activate'])) {
    $stmt = $pdo->prepare("UPDATE users SET status='active' WHERE user_id=? AND role='teacher'");
    $stmt->execute([$_GET['activate']]);
    header("Location: teachers.php?success=activated");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name   = trim($_POST['full_name']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);
    $address     = trim($_POST['address']);
    $password    = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
    $department  = trim($_POST['department']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, phone, address, status) VALUES (?, ?, ?, 'teacher', ?, ?, 'active')");
        $stmt->execute([$full_name, $email, $password, $phone, $address]);
        $teacher_id = $pdo->lastInsertId();

        $stmt2 = $pdo->prepare("INSERT INTO staff_profiles (user_id, department, position, employment_type, hire_date) VALUES (?, ?, 'teacher', 'full_time', CURDATE())");
        $stmt2->execute([$teacher_id, $department]);

        $pdo->commit();
        header("Location: teachers.php?success=added");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

$teachers = $pdo->query("
    SELECT u.*, s.department 
    FROM users u 
    LEFT JOIN staff_profiles s ON u.user_id = s.user_id 
    WHERE u.role='teacher' 
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers — EduRole</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }
        .sidebar {
            width: 250px; background: #1a73e8;
            min-height: 100vh; position: fixed;
            top: 0; left: 0; padding: 20px 0;
        }
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
        .btn-success { background: #34a853; color: white; font-size: 12px; padding: 6px 12px; }
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
        .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-active { background: #e6f4ea; color: #34a853; }
        .badge-inactive { background: #fce8e6; color: #ea4335; }
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
        <a href="teachers.php" class="active"><span>👨‍🏫</span> Teachers</a>
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

<div class="main">
    <div class="topbar">
        <h2>👨‍🏫 Teacher Management</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
            + Add New Teacher
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'added') echo "✅ Teacher added successfully!";
            if ($_GET['success'] == 'deactivated') echo "⛔ Teacher deactivated successfully!";
            if ($_GET['success'] == 'activated') echo "✅ Teacher activated successfully!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>All Teachers (<?php echo count($teachers); ?>)</h3>
            <input type="text" class="search-bar" id="searchInput" placeholder="🔍 Search teachers..." onkeyup="searchTable()">
        </div>
        <table id="teachersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teachers)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="icon">👨‍🏫</div>
                                <p>No teachers registered yet. Click "Add New Teacher" to get started.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $i => $teacher): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['phone'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($teacher['department'] ?? '—'); ?></td>
                            <td>
                                <span class="badge <?php echo $teacher['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo ucfirst($teacher['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($teacher['created_at'])); ?></td>
                            <td>
                                <?php if ($teacher['status'] === 'active'): ?>
                                    <a href="?deactivate=<?php echo $teacher['user_id']; ?>" class="btn btn-danger" onclick="return confirm('Deactivate this teacher?')">Deactivate</a>
                                <?php else: ?>
                                    <a href="?activate=<?php echo $teacher['user_id']; ?>" class="btn btn-success">Activate</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3>➕ Add New Teacher</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required placeholder="Tom Jerry">
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required placeholder="tom.jerry@edurole.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" placeholder="+260 97 000 0000">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required placeholder="Set a password">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="e.g. Science, Mathematics">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" placeholder="Teacher's address">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="add_teacher" class="btn btn-primary">Add Teacher</button>
            </div>
        </form>
    </div>
</div>

<script>
function searchTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#teachersTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>