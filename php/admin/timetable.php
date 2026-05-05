<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Delete timetable entry
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM timetable WHERE timetable_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: timetable.php?success=deleted");
    exit();
}

// Add timetable entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $teacher_id = $_POST['teacher_id'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $room = trim($_POST['room']);

    // Check for time conflict
    $check = $pdo->prepare("SELECT * FROM timetable WHERE class_id=? AND day_of_week=? AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))");
    $check->execute([$class_id, $day_of_week, $end_time, $start_time, $start_time, $end_time]);
    
    if ($check->fetch()) {
        $error = "Time conflict! This class already has a session at this time.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$class_id, $subject_id, $teacher_id, $day_of_week, $start_time, $end_time, $room]);
            header("Location: timetable.php?success=added");
            exit();
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch timetable entries with details
$timetable = $pdo->query("
    SELECT t.*, 
           c.class_name,
           s.subject_name,
           u.full_name as teacher_name
    FROM timetable t
    JOIN classes c ON t.class_id = c.class_id
    JOIN subjects s ON t.subject_id = s.subject_id
    JOIN users u ON t.teacher_id = u.user_id
    ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.start_time
")->fetchAll();

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
$teachers = $pdo->query("SELECT user_id, full_name FROM users WHERE role='teacher' AND status='active' ORDER BY full_name")->fetchAll();

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Timetable — EduRole</title>
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
        .btn {
            padding: 10px 20px;
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
        .btn-danger { background: #ea4335; color: white; font-size: 12px; padding: 6px 12px; }
        .btn-sm { font-size: 12px; padding: 5px 12px; }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .card-header {
            padding: 18px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h3 { font-size: 16px; color: #333; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            color: #666;
            font-weight: 600;
            border-bottom: 1px solid #eee;
        }
        td {
            padding: 12px 15px;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #f5f5f5;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9f9f9; }
        .alert {
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #e6f4ea; color: #34a853; }
        .alert-error { background: #fce8e6; color: #ea4335; }
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #999;
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 10px; }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 500px;
            max-width: 95%;
        }
        .modal h3 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
        }
        .form-group input:focus, .form-group select:focus { border-color: #1a73e8; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .btn-cancel { background: #f0f2f5; color: #333; }
        .time-badge {
            background: #e8f0fe;
            color: #1a73e8;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .filter-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .filter-bar select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
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
        <a href="timetable.php" class="active"><span>🗓️</span> Timetable</a>
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
        <h2>🗓️ Class Timetable</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
            + Add Schedule
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'added') echo "✅ Schedule added successfully!";
            if ($_GET['success'] == 'deleted') echo "🗑️ Schedule deleted successfully!";
            ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filter by Class -->
    <div class="filter-bar">
        <label>Filter by Class:</label>
        <select id="classFilter" onchange="filterTable()">
            <option value="all">All Classes</option>
            <?php foreach ($classes as $class): ?>
                <option value="<?php echo htmlspecialchars($class['class_name']); ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>📅 Weekly Schedule (<?php echo count($timetable); ?> sessions)</h3>
        </div>
        <table id="timetableTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($timetable)): ?>
                    <tr><td colspan="8"><div class="empty-state"><div class="icon">🗓️</div><p>No schedule entries yet. Click "Add Schedule" to create a timetable.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($timetable as $i => $entry): ?>
                        <tr class="schedule-row" data-class="<?php echo htmlspecialchars($entry['class_name']); ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($entry['class_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($entry['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['teacher_name']); ?></td>
                            <td><?php echo $entry['day_of_week']; ?></td>
                            <td><span class="time-badge"><?php echo date('h:i A', strtotime($entry['start_time'])); ?> - <?php echo date('h:i A', strtotime($entry['end_time'])); ?></span></td>
                            <td><?php echo htmlspecialchars($entry['room'] ?: '—'); ?></td>
                            <td>
                                <a href="?delete=<?php echo $entry['timetable_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this schedule entry?')">Delete</a>
                             </td>
                         </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Schedule Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3>➕ Add Timetable Entry</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Class *</label>
                    <select name="class_id" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Teacher *</label>
                <select name="teacher_id" required>
                    <option value="">-- Select Teacher --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['user_id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Day of Week *</label>
                <select name="day_of_week" required>
                    <option value="">-- Select Day --</option>
                    <?php foreach ($days as $day): ?>
                        <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Time *</label>
                    <input type="time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label>End Time *</label>
                    <input type="time" name="end_time" required>
                </div>
            </div>
            <div class="form-group">
                <label>Room Number</label>
                <input type="text" name="room" placeholder="e.g. Room 101, Lab A">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="add_schedule" class="btn btn-primary">Add Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterTable() {
    const filter = document.getElementById('classFilter').value.toLowerCase();
    const rows = document.querySelectorAll('.schedule-row');
    rows.forEach(row => {
        const className = row.getAttribute('data-class').toLowerCase();
        if (filter === 'all' || className === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
</body>
</html>