<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle leave request approval/rejection
if (isset($_GET['approve_leave'])) {
    $stmt = $pdo->prepare("UPDATE leave_requests SET status='approved', reviewed_by=? WHERE leave_id=?");
    $stmt->execute([$_SESSION['user_id'], $_GET['approve_leave']]);
    header("Location: staff.php?tab=leaves&success=approved");
    exit();
}

if (isset($_GET['reject_leave'])) {
    $stmt = $pdo->prepare("UPDATE leave_requests SET status='rejected', reviewed_by=? WHERE leave_id=?");
    $stmt->execute([$_SESSION['user_id'], $_GET['reject_leave']]);
    header("Location: staff.php?tab=leaves&success=rejected");
    exit();
}

// Handle delete staff
if (isset($_GET['delete_staff'])) {
    $stmt = $pdo->prepare("DELETE FROM staff_profiles WHERE user_id=?");
    $stmt->execute([$_GET['delete_staff']]);
    $stmt2 = $pdo->prepare("DELETE FROM users WHERE user_id=? AND role='teacher'");
    $stmt2->execute([$_GET['delete_staff']]);
    header("Location: staff.php?tab=staff&success=deleted");
    exit();
}

// Handle delete leave request
if (isset($_GET['delete_leave'])) {
    $stmt = $pdo->prepare("DELETE FROM leave_requests WHERE leave_id=?");
    $stmt->execute([$_GET['delete_leave']]);
    header("Location: staff.php?tab=leaves&success=deleted");
    exit();
}

// Fetch staff profiles (teachers with staff details)
$staff = $pdo->query("
    SELECT u.*, s.department, s.position, s.employment_type, s.hire_date, s.salary
    FROM users u
    JOIN staff_profiles s ON u.user_id = s.user_id
    WHERE u.role = 'teacher'
    ORDER BY u.full_name
")->fetchAll();

// Fetch leave requests
$leaveRequests = $pdo->query("
    SELECT l.*, u.full_name as staff_name, u.email, 
           r.full_name as reviewed_by_name
    FROM leave_requests l
    JOIN users u ON l.user_id = u.user_id
    LEFT JOIN users r ON l.reviewed_by = r.user_id
    ORDER BY l.created_at DESC
")->fetchAll();

$tab = $_GET['tab'] ?? 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR / Staff Management — EduRole</title>
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
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #ddd;
        }
        .tab {
            padding: 10px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #666;
            transition: all 0.2s;
        }
        .tab.active {
            color: #1a73e8;
            border-bottom: 2px solid #1a73e8;
            margin-bottom: -2px;
        }
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
        .btn-success { background: #34a853; color: white; }
        .btn-warning { background: #fa7b17; color: white; }
        .btn-danger { background: #ea4335; color: white; font-size: 12px; padding: 6px 12px; }
        .btn-sm { font-size: 12px; padding: 5px 12px; }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            overflow: hidden;
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
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending { background: #fff3e0; color: #fa7b17; }
        .badge-approved { background: #e6f4ea; color: #34a853; }
        .badge-rejected { background: #fce8e6; color: #ea4335; }
        .badge-full_time { background: #e8f0fe; color: #1a73e8; }
        .badge-part_time { background: #f3e5f5; color: #9c27b0; }
        .badge-contract { background: #fff3e0; color: #fa7b17; }
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
        .search-bar {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            width: 220px;
            outline: none;
        }
        .amount { font-weight: 700; color: #1a73e8; }
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
        <a href="grades.php"><span>📊</span> Grades</a>
        <a href="fees.php"><span>💰</span> Fee Management</a>
        <a href="staff.php" class="active"><span>👥</span> HR / Staff</a>
        <a href="notices.php"><span>📢</span> Notices</a>
        <a href="reports.php"><span>📈</span> Reports</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
</div>

<div class="main">
    <div class="topbar">
        <h2>👥 HR & Staff Management</h2>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab <?php echo $tab === 'staff' ? 'active' : ''; ?>" onclick="window.location.href='?tab=staff'">Staff Directory</button>
        <button class="tab <?php echo $tab === 'leaves' ? 'active' : ''; ?>" onclick="window.location.href='?tab=leaves'">Leave Requests</button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'deleted') echo "✅ Staff member removed successfully!";
            if ($_GET['success'] == 'approved') echo "✅ Leave request approved!";
            if ($_GET['success'] == 'rejected') echo "❌ Leave request rejected!";
            ?>
        </div>
    <?php endif; ?>

    <!-- STAFF DIRECTORY TAB -->
    <?php if ($tab === 'staff'): ?>
    <div class="card">
        <div class="card-header">
            <h3>👨‍🏫 Staff Directory (<?php echo count($staff); ?> teachers)</h3>
            <input type="text" class="search-bar" id="searchInput" placeholder="🔍 Search staff..." onkeyup="searchTable()">
        </div>
        <table id="staffTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Staff Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Employment Type</th>
                    <th>Salary (ZMW)</th>
                    <th>Hire Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staff)): ?>
                    <tr><td colspan="9"><div class="empty-state"><div class="icon">👥</div><p>No staff records found. Teachers added via "Teachers" module appear here.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($staff as $i => $employee): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($employee['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($employee['email']); ?></td>
                            <td><?php echo htmlspecialchars($employee['department'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($employee['position'] ?? 'Teacher'); ?></td>
                            <td><span class="badge badge-<?php echo str_replace('_', '-', $employee['employment_type'] ?? 'full_time'); ?>"><?php echo str_replace('_', ' ', ucfirst($employee['employment_type'] ?? 'Full Time')); ?></span></td>
                            <td class="amount">ZMW <?php echo number_format($employee['salary'] ?? 0, 2); ?></td>
                            <td><?php echo $employee['hire_date'] ? date('d M Y', strtotime($employee['hire_date'])) : '—'; ?></td>
                            <td>
                                <a href="?delete_staff=<?php echo $employee['user_id']; ?>&tab=staff" class="btn btn-danger btn-sm" onclick="return confirm('Remove this staff member? This will delete their account.')">Remove</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- LEAVE REQUESTS TAB -->
    <?php if ($tab === 'leaves'): ?>
    <div class="card">
        <div class="card-header">
            <h3>📋 Leave Requests (<?php echo count($leaveRequests); ?>)</h3>
        </div>
        <table id="leavesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Staff Member</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Duration</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaveRequests)): ?>
                    <tr><td colspan="9"><div class="empty-state"><div class="icon">📋</div><p>No leave requests submitted yet.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($leaveRequests as $i => $leave): ?>
                        <?php
                        $start = new DateTime($leave['start_date']);
                        $end = new DateTime($leave['end_date']);
                        $duration = $start->diff($end)->days + 1;
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($leave['staff_name']); ?></strong><br><small><?php echo htmlspecialchars($leave['email']); ?></small></td>
                            <td><?php echo ucfirst($leave['leave_type']); ?></td>
                            <td><?php echo date('d M Y', strtotime($leave['start_date'])); ?></td>
                            <td><?php echo date('d M Y', strtotime($leave['end_date'])); ?></td>
                            <td><?php echo $duration; ?> day(s)</td>
                            <td><small><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></small></td>
                            <td>
                                <span class="badge badge-<?php echo $leave['status']; ?>">
                                    <?php echo ucfirst($leave['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($leave['status'] === 'pending'): ?>
                                    <a href="?approve_leave=<?php echo $leave['leave_id']; ?>&tab=leaves" class="btn btn-success btn-sm" onclick="return confirm('Approve this leave request?')">Approve</a>
                                    <a href="?reject_leave=<?php echo $leave['leave_id']; ?>&tab=leaves" class="btn btn-warning btn-sm" onclick="return confirm('Reject this leave request?')">Reject</a>
                                <?php endif; ?>
                                <a href="?delete_leave=<?php echo $leave['leave_id']; ?>&tab=leaves" class="btn btn-danger btn-sm" onclick="return confirm('Delete this leave request?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function searchTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#staffTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>