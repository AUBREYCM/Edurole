<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

// Get teacher's staff profile
$stmt = $pdo->prepare("SELECT * FROM staff_profiles WHERE user_id = ?");
$stmt->execute([$teacher_id]);
$profile = $stmt->fetch();

// Get classes taught by this teacher
$classes = $pdo->prepare("
    SELECT DISTINCT c.class_id, c.class_name, c.academic_year, p.programme_name
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.class_id
    JOIN programmes p ON c.programme_id = p.programme_id
    WHERE ta.teacher_id = ?
    ORDER BY c.class_name
");
$classes->execute([$teacher_id]);
$taughtClasses = $classes->fetchAll();

// Get subjects taught by this teacher
$subjects = $pdo->prepare("
    SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code, s.credits
    FROM teacher_assignments ta
    JOIN subjects s ON ta.subject_id = s.subject_id
    WHERE ta.teacher_id = ?
    ORDER BY s.subject_name
");
$subjects->execute([$teacher_id]);
$taughtSubjects = $subjects->fetchAll();

// Get today's timetable for this teacher
$today = date('l'); // Monday, Tuesday, etc.
$timetable = $pdo->prepare("
    SELECT t.*, c.class_name, s.subject_name
    FROM timetable t
    JOIN classes c ON t.class_id = c.class_id
    JOIN subjects s ON t.subject_id = s.subject_id
    WHERE t.teacher_id = ? AND t.day_of_week = ?
    ORDER BY t.start_time
");
$timetable->execute([$teacher_id, $today]);
$todaySchedule = $timetable->fetchAll();

// Get pending leave requests
$leaveRequests = $pdo->prepare("
    SELECT * FROM leave_requests 
    WHERE user_id = ? AND status = 'pending'
    ORDER BY created_at DESC
");
$leaveRequests->execute([$teacher_id]);
$pendingLeaves = $leaveRequests->fetchAll();

// Get recent attendance marked (last 5 entries)
$recentAttendance = $pdo->prepare("
    SELECT a.*, u.full_name as student_name, c.class_name, s.subject_name
    FROM attendance a
    JOIN users u ON a.student_id = u.user_id
    JOIN classes c ON a.class_id = c.class_id
    JOIN subjects s ON a.subject_id = s.subject_id
    WHERE a.marked_by = ?
    ORDER BY a.attendance_date DESC
    LIMIT 10
");
$recentAttendance->execute([$teacher_id]);
$recentMarks = $recentAttendance->fetchAll();

// Get notices for teachers
$notices = $pdo->query("
    SELECT * FROM notices 
    WHERE target_role IN ('all', 'teacher') 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard — EduRole</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }
        
        /* Header */
        .header {
            background: #1a73e8;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header .logo h1 { font-size: 24px; letter-spacing: 2px; }
        .header .logo p { font-size: 11px; opacity: 0.8; }
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        
        /* Navigation Tabs */
        .nav-tabs {
            background: white;
            padding: 0 30px;
            display: flex;
            gap: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .nav-tab {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            transition: all 0.2s;
        }
        .nav-tab.active {
            color: #1a73e8;
            border-bottom: 3px solid #1a73e8;
        }
        .nav-tab:hover { background: #f5f5f5; }
        
        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #34a853, #0d7a3e);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .welcome-banner h2 { font-size: 28px; margin-bottom: 10px; }
        .welcome-banner p { opacity: 0.9; font-size: 14px; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: 800;
            color: #1a73e8;
        }
        .stat-card .label {
            color: #666;
            font-size: 13px;
            margin-top: 8px;
        }
        
        /* Two Column Layout */
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1a73e8;
            display: inline-block;
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 10px 0;
            font-size: 13px;
            color: #666;
            border-bottom: 1px solid #eee;
        }
        td {
            padding: 10px 0;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #f5f5f5;
        }
        tr:last-child td { border-bottom: none; }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending { background: #fff3e0; color: #fa7b17; }
        .time-badge {
            background: #e8f0fe;
            color: #1a73e8;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .btn-small {
            background: #1a73e8;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            border: none;
            cursor: pointer;
        }
        .btn-small:hover { background: #1557b0; }
        
        .btn-outline {
            background: white;
            color: #1a73e8;
            border: 1px solid #1a73e8;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .two-column { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">
        <h1>EDUROLE</h1>
        <p>Teacher Portal</p>
    </div>
    <div class="user-info">
        <span>👋 Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="nav-tabs">
    <button class="nav-tab active" onclick="showTab('overview')">📊 Overview</button>
    <button class="nav-tab" onclick="showTab('attendance')">✅ Mark Attendance</button>
    <button class="nav-tab" onclick="showTab('grades')">📝 Enter Grades</button>
    <button class="nav-tab" onclick="showTab('leave')">📋 Leave Request</button>
</div>

<div class="container">
    <!-- OVERVIEW TAB -->
    <div id="overview" class="tab-content active">
        <div class="welcome-banner">
            <h2>Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! 👨‍🏫</h2>
            <p>Department: <?php echo htmlspecialchars($profile['department'] ?? 'Not assigned'); ?> | Position: <?php echo htmlspecialchars($profile['position'] ?? 'Teacher'); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo count($taughtClasses); ?></div>
                <div class="label">Classes Assigned</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo count($taughtSubjects); ?></div>
                <div class="label">Subjects</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo count($todaySchedule); ?></div>
                <div class="label">Today's Classes</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo count($pendingLeaves); ?></div>
                <div class="label">Pending Leaves</div>
            </div>
        </div>

        <div class="two-column">
            <!-- Today's Schedule -->
            <div class="card">
                <h3>📅 Today's Schedule (<?php echo $today; ?>)</h3>
                <?php if (empty($todaySchedule)): ?>
                    <div class="empty-state">No classes scheduled for today.</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Time</th><th>Class</th><th>Subject</th><th>Room</th></tr></thead>
                        <tbody>
                            <?php foreach ($todaySchedule as $schedule): ?>
                                <tr>
                                    <td><span class="time-badge"><?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('h:i A', strtotime($schedule['end_time'])); ?></span></td>
                                    <td><?php echo htmlspecialchars($schedule['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['room'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- My Subjects -->
            <div class="card">
                <h3>📚 My Subjects</h3>
                <?php if (empty($taughtSubjects)): ?>
                    <div class="empty-state">No subjects assigned yet.</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Subject</th><th>Code</th><th>Credits</th></tr></thead>
                        <tbody>
                            <?php foreach ($taughtSubjects as $subject): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                    <td><?php echo $subject['credits']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="two-column">
            <!-- Recent Attendance Marked -->
            <div class="card">
                <h3>✅ Recent Attendance Marked</h3>
                <?php if (empty($recentMarks)): ?>
                    <div class="empty-state">No attendance marked yet.</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentMarks as $mark): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($mark['attendance_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($mark['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($mark['class_name']); ?></td>
                                    <td><span class="badge badge-<?php echo $mark['status']; ?>"><?php echo ucfirst($mark['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Notices -->
            <div class="card">
                <h3>📢 Announcements</h3>
                <?php if (empty($notices)): ?>
                    <div class="empty-state">No announcements yet.</div>
                <?php else: ?>
                    <?php foreach ($notices as $notice): ?>
                        <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                            <div style="font-weight: 700; color: #1a73e8;">📌 <?php echo htmlspecialchars($notice['title']); ?></div>
                            <div style="font-size: 12px; color: #888;"><?php echo date('d M Y', strtotime($notice['created_at'])); ?></div>
                            <div style="font-size: 13px; margin-top: 5px;"><?php echo htmlspecialchars(substr($notice['content'], 0, 100)) . '...'; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ATTENDANCE TAB -->
    <div id="attendance" class="tab-content">
        <div class="card">
            <h3>✅ Mark Student Attendance</h3>
            <form method="GET" action="mark_attendance.php">
                <div class="form-group">
                    <label>Select Class</label>
                    <select name="class_id" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($taughtClasses as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Subject</label>
                    <select name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($taughtSubjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-small">Go to Mark Attendance</button>
            </form>
        </div>
    </div>

    <!-- GRADES TAB -->
    <div id="grades" class="tab-content">
        <div class="card">
            <h3>📝 Enter Student Grades</h3>
            <form method="GET" action="enter_grades.php">
                <div class="form-group">
                    <label>Select Class</label>
                    <select name="class_id" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($taughtClasses as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Subject</label>
                    <select name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($taughtSubjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-small">Go to Enter Grades</button>
            </form>
        </div>
    </div>

    <!-- LEAVE REQUEST TAB -->
    <div id="leave" class="tab-content">
        <div class="two-column">
            <div class="card">
                <h3>📋 Submit Leave Request</h3>
                <form method="POST" action="submit_leave.php">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select name="leave_type" required>
                            <option value="annual">Annual Leave</option>
                            <option value="sick">Sick Leave</option>
                            <option value="emergency">Emergency Leave</option>
                            <option value="maternity">Maternity Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea name="reason" rows="4" required placeholder="Please provide reason for leave..."></textarea>
                    </div>
                    <button type="submit" class="btn-small">Submit Request</button>
                </form>
            </div>

            <div class="card">
                <h3>📋 My Leave Requests</h3>
                <?php
                $allLeaves = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC");
                $allLeaves->execute([$teacher_id]);
                $myLeaves = $allLeaves->fetchAll();
                ?>
                <?php if (empty($myLeaves)): ?>
                    <div class="empty-state">No leave requests submitted yet.</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Type</th><th>Dates</th><th>Status</th><th>Submitted</th></tr></thead>
                        <tbody>
                            <?php foreach ($myLeaves as $leave): ?>
                                <tr>
                                    <td><?php echo ucfirst($leave['leave_type']); ?></td>
                                    <td><?php echo date('d M', strtotime($leave['start_date'])); ?> - <?php echo date('d M', strtotime($leave['end_date'])); ?></td>
                                    <td><span class="badge badge-<?php echo $leave['status']; ?>"><?php echo ucfirst($leave['status']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($leave['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    // Remove active class from all nav buttons
    document.querySelectorAll('.nav-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    // Add active class to clicked button
    event.target.classList.add('active');
}
</script>

</body>
</html>