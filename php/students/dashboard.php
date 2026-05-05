<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get enrolled classes
$classes = $pdo->prepare("
    SELECT c.class_id, c.class_name, c.academic_year, p.programme_name
    FROM enrollments e
    JOIN classes c ON e.class_id = c.class_id
    JOIN programmes p ON c.programme_id = p.programme_id
    WHERE e.student_id = ? AND e.status = 'active'
");
$classes->execute([$student_id]);
$enrolledClasses = $classes->fetchAll();

// Get recent grades
$grades = $pdo->prepare("
    SELECT g.*, s.subject_name, 
           ROUND((g.marks_obtained / g.total_marks) * 100, 1) as percentage
    FROM grades g
    JOIN subjects s ON g.subject_id = s.subject_id
    WHERE g.student_id = ?
    ORDER BY g.grade_date DESC
    LIMIT 10
");
$grades->execute([$student_id]);
$recentGrades = $grades->fetchAll();

// Get attendance summary
$attendance = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
    FROM attendance
    WHERE student_id = ?
");
$attendance->execute([$student_id]);
$attendanceStats = $attendance->fetch();

// Get fee payments
$payments = $pdo->prepare("
    SELECT fp.*, fs.fee_name, fs.amount as fee_amount
    FROM fee_payments fp
    JOIN fee_structure fs ON fp.fee_id = fs.fee_id
    WHERE fp.student_id = ?
    ORDER BY fp.payment_date DESC
    LIMIT 5
");
$payments->execute([$student_id]);
$recentPayments = $payments->fetchAll();

// Get unpaid fees
$unpaidFees = $pdo->prepare("
    SELECT fs.*, p.programme_name
    FROM fee_structure fs
    JOIN programmes p ON fs.programme_id = p.programme_id
    WHERE fs.programme_id IN (
        SELECT programme_id FROM enrollments WHERE student_id = ?
    )
    AND fs.amount > COALESCE(
        (SELECT SUM(amount_paid) FROM fee_payments WHERE student_id = ? AND fee_id = fs.fee_id), 0
    )
");
$unpaidFees->execute([$student_id, $student_id]);
$outstandingFees = $unpaidFees->fetchAll();

// Get notices for students
$notices = $pdo->query("
    SELECT * FROM notices 
    WHERE target_role IN ('all', 'student') 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Calculate attendance percentage
$attendancePercent = 0;
if ($attendanceStats && $attendanceStats['total_days'] > 0) {
    $attendancePercent = round(($attendanceStats['present_days'] / $attendanceStats['total_days']) * 100, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard — EduRole</title>
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
        
        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
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
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
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
        .badge-present { background: #e6f4ea; color: #34a853; }
        .badge-absent { background: #fce8e6; color: #ea4335; }
        .badge-late { background: #fff3e0; color: #fa7b17; }
        .badge-A { background: #e6f4ea; color: #34a853; }
        .badge-B { background: #e8f0fe; color: #1a73e8; }
        .badge-C { background: #fff3e0; color: #fa7b17; }
        .badge-D, .badge-F { background: #fce8e6; color: #ea4335; }
        
        .grade-letter {
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
        }
        
        .fee-amount {
            font-weight: 700;
            color: #ea4335;
        }
        
        .btn-small {
            background: #1a73e8;
            color: white;
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
        <p>Student Portal</p>
    </div>
    <div class="user-info">
        <span>👋 Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <h2>Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! 🎓</h2>
        <p>Here's your academic overview and recent activities.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo count($enrolledClasses); ?></div>
            <div class="label">Enrolled Classes</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo count($recentGrades); ?></div>
            <div class="label">Grades Recorded</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $attendancePercent; ?>%</div>
            <div class="label">Attendance Rate</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo count($recentPayments); ?></div>
            <div class="label">Payments Made</div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="two-column">
        <!-- Enrolled Classes -->
        <div class="card">
            <h3>📚 My Classes</h3>
            <?php if (empty($enrolledClasses)): ?>
                <div class="empty-state">No classes enrolled yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Class Name</th><th>Programme</th><th>Academic Year</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrolledClasses as $class): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($class['programme_name']); ?></td>
                                <td><?php echo htmlspecialchars($class['academic_year']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Recent Grades -->
        <div class="card">
            <h3>📊 Recent Grades</h3>
            <?php if (empty($recentGrades)): ?>
                <div class="empty-state">No grades available yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Subject</th><th>Assessment</th><th>Score</th><th>Grade</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGrades as $grade):
                            $percentage = $grade['percentage'];
                            if ($percentage >= 80) $letter = 'A';
                            elseif ($percentage >= 70) $letter = 'B';
                            elseif ($percentage >= 60) $letter = 'C';
                            elseif ($percentage >= 50) $letter = 'D';
                            else $letter = 'F';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td><small><?php echo htmlspecialchars($grade['assessment_name']); ?></small></td>
                                <td><?php echo $grade['marks_obtained']; ?>/<?php echo $grade['total_marks']; ?></td>
                                <td><span class="grade-letter badge-<?php echo $letter; ?>"><?php echo $letter; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="two-column">
        <!-- Attendance Summary -->
        <div class="card">
            <h3>✅ Attendance Overview</h3>
            <?php if (!$attendanceStats || $attendanceStats['total_days'] == 0): ?>
                <div class="empty-state">No attendance records yet.</div>
            <?php else: ?>
                <table>
                    <tr><td style="width: 50%;">Total Days:</td><td><strong><?php echo $attendanceStats['total_days']; ?></strong></td></tr>
                    <tr><td>Present:</td><td><span style="color:#34a853;"><?php echo $attendanceStats['present_days']; ?></span></td></tr>
                    <tr><td>Absent:</td><td><span style="color:#ea4335;"><?php echo $attendanceStats['absent_days']; ?></span></td></tr>
                    <tr><td>Late:</td><td><span style="color:#fa7b17;"><?php echo $attendanceStats['late_days']; ?></span></td></tr>
                    <tr><td><strong>Attendance Rate:</strong></td><td><strong><?php echo $attendancePercent; ?>%</strong></td></tr>
                </table>
                <div style="margin-top: 15px; background: #e8f0fe; padding: 10px; border-radius: 8px; font-size: 13px;">
                    📌 Keep your attendance above 80% to maintain good academic standing.
                </div>
            <?php endif; ?>
        </div>

        <!-- Fee Status -->
        <div class="card">
            <h3>💰 Fee Status</h3>
            <?php if (empty($outstandingFees)): ?>
                <div class="empty-state">✅ All fees paid! Good standing.</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Fee Type</th><th>Amount</th><th>Due Date</th></tr></thead>
                    <tbody>
                        <?php 
                        $totalOutstanding = 0;
                        foreach ($outstandingFees as $fee):
                            $paid = $pdo->prepare("SELECT SUM(amount_paid) as paid FROM fee_payments WHERE student_id=? AND fee_id=?");
                            $paid->execute([$student_id, $fee['fee_id']]);
                            $paidAmount = $paid->fetch()['paid'] ?? 0;
                            $balance = $fee['amount'] - $paidAmount;
                            $totalOutstanding += $balance;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                <td class="fee-amount">ZMW <?php echo number_format($balance, 2); ?></td>
                                <td><?php echo date('d M Y', strtotime($fee['due_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="border-top: 2px solid #eee;">
                            <td><strong>Total Outstanding</strong></td>
                            <td class="fee-amount"><strong>ZMW <?php echo number_format($totalOutstanding, 2); ?></strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Payments & Notices -->
    <div class="two-column">
        <!-- Recent Payments -->
        <div class="card">
            <h3>💳 Recent Payments</h3>
            <?php if (empty($recentPayments)): ?>
                <div class="empty-state">No payment history yet.</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Fee Type</th><th>Amount</th><th>Date</th><th>Receipt</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['fee_name']); ?></td>
                                <td>ZMW <?php echo number_format($payment['amount_paid'], 2); ?></td>
                                <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                <td><code><?php echo $payment['receipt_number']; ?></code></td>
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
                    <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                        <div style="font-weight: 700; color: #1a73e8; margin-bottom: 5px;">📌 <?php echo htmlspecialchars($notice['title']); ?></div>
                        <div style="font-size: 13px; color: #666; margin-bottom: 8px;"><?php echo date('d M Y', strtotime($notice['created_at'])); ?></div>
                        <div style="font-size: 14px; color: #333;"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>