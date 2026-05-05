<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM notices WHERE notice_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: notices.php?success=deleted");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notice'])) {
    $title       = trim($_POST['title']);
    $content     = trim($_POST['content']);
    $target_role = trim($_POST['target_role']);
    $posted_by   = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO notices (title, content, posted_by, target_role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $posted_by, $target_role]);
        header("Location: notices.php?success=added");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$notices = $pdo->query("
    SELECT n.*, u.full_name as posted_by_name 
    FROM notices n 
    LEFT JOIN users u ON n.posted_by = u.user_id 
    ORDER BY n.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notices — EduRole</title>
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
        .modal { background: white; padding: 30px; border-radius: 10px; width: 550px; max-width: 95%; }
        .modal h3 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; font-family: Arial, sans-serif; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #1a73e8; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { background: #f0f2f5; color: #333; }
        .notices-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .notice-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); padding: 20px; border-left: 4px solid #1a73e8; }
        .notice-card.all { border-left-color: #1a73e8; }
        .notice-card.student { border-left-color: #34a853; }
        .notice-card.teacher { border-left-color: #fa7b17; }
        .notice-card.parent { border-left-color: #9c27b0; }
        .notice-card .notice-title { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .notice-card .notice-content { font-size: 14px; color: #555; line-height: 1.6; margin-bottom: 15px; }
        .notice-card .notice-meta { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #999; }
        .badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-all { background: #e8f0fe; color: #1a73e8; }
        .badge-student { background: #e6f4ea; color: #34a853; }
        .badge-teacher { background: #fff3e0; color: #fa7b17; }
        .badge-parent { background: #f3e5f5; color: #9c27b0; }
        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #e6f4ea; color: #34a853; }
        .alert-error { background: #fce8e6; color: #ea4335; }
        .empty-state { text-align: center; padding: 50px; color: #999; background: white; border-radius: 10px; }
        .empty-state .icon { font-size: 48px; margin-bottom: 10px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h3 { color: #333; font-size: 16px; }
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
        <a href="attendance.php"><span>✅</span> Attendance</a>
        <a href="grades.php"><span>📊</span> Grades</a>
        <a href="fees.php"><span>💰</span> Fee Management</a>
        <a href="staff.php"><span>👥</span> HR / Staff</a>
        <a href="notices.php" class="active"><span>📢</span> Notices</a>
        <a href="reports.php"><span>📈</span> Reports</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
</div>

<div class="main">
    <div class="topbar">
        <h2>📢 Notice Board</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
            + Post New Notice
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'added') echo "✅ Notice posted successfully!";
            if ($_GET['success'] == 'deleted') echo "🗑️ Notice deleted successfully!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (empty($notices)): ?>
        <div class="empty-state">
            <div class="icon">📢</div>
            <p>No notices posted yet. Click "Post New Notice" to get started.</p>
        </div>
    <?php else: ?>
        <div class="section-header">
            <h3>All Notices (<?php echo count($notices); ?>)</h3>
        </div>
        <div class="notices-grid">
            <?php foreach ($notices as $notice): ?>
                <div class="notice-card <?php echo $notice['target_role']; ?>">
                    <div class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></div>
                    <div class="notice-content"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></div>
                    <div class="notice-meta">
                        <div>
                            <span class="badge badge-<?php echo $notice['target_role']; ?>">
                                <?php echo ucfirst($notice['target_role']); ?>
                            </span>
                            &nbsp; By <?php echo htmlspecialchars($notice['posted_by_name']); ?>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php echo date('d M Y', strtotime($notice['created_at'])); ?>
                            <a href="?delete=<?php echo $notice['notice_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this notice?')">Delete</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Notice Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3>📢 Post New Notice</h3>
        <form method="POST">
            <div class="form-group">
                <label>Notice Title *</label>
                <input type="text" name="title" required placeholder="e.g. Exam Timetable Released">
            </div>
            <div class="form-group">
                <label>Target Audience *</label>
                <select name="target_role" required>
                    <option value="all">Everyone</option>
                    <option value="student">Students Only</option>
                    <option value="teacher">Teachers Only</option>
                    <option value="parent">Parents Only</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notice Content *</label>
                <textarea name="content" required rows="5" placeholder="Write your notice here..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="add_notice" class="btn btn-primary">Post Notice</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>