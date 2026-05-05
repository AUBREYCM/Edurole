<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../login.php");
    exit();
}

$parent_id = $_SESSION['user_id'];

// Get parent info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$parent_id]);
$parent = $stmt->fetch();

// Get linked students
$students = $pdo->prepare("
    SELECT ps.relationship, u.user_id, u.full_name, u.email, u.phone
    FROM parent_student ps
    JOIN users u ON ps.student_id = u.user_id
    WHERE ps.parent_id = ?
");
$students->execute([$parent_id]);
$linkedStudents = $students->fetchAll();

// Get selected student (default to first one)
$selected_student_id = $_GET['student_id'] ?? ($linkedStudents[0]['user_id'] ?? 0);
$selected_student = null;

// Get student details if selected
if ($selected_student_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$selected_student_id]);
    $selected_student = $stmt->fetch();
    
    // Get student's classes
    $classes = $pdo->prepare("
        SELECT c.class_id, c.class_name, c.academic_year, p.programme_name
        FROM enrollments e
        JOIN classes c ON e.class_id = c.class_id
        JOIN programmes p ON c.programme_id = p.programme_id
        WHERE e.student_id = ? AND e.status = 'active'
    ");
    $classes->execute([$selected_student_id]);
    $studentClasses = $classes->fetchAll();
    
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
    $grades->execute([$selected_student_id]);
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
    $attendance->execute([$selected_student_id]);
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
    $payments->execute([$selected_student_id]);
    $recentPayments = $payments->fetchAll();
    
    // Get unpaid fees
    $unpaidFees = $pdo->prepare("
        SELECT fs.*, p.programme_name,
               COALESCE((SELECT SUM(amount_paid) FROM fee_payments WHERE student_id = ? AND fee_id = fs.fee_id), 0) as paid_amount
        FROM fee_structure fs
        JOIN programmes p ON fs.programme_id = p.programme_id
        WHERE fs.programme_id IN (
            SELECT programme_id FROM enrollments WHERE student_id = ?
        )
    ");
    $unpaidFees->execute([$selected_student_id, $selected_student_id]);
    $outstandingFees = $unpaidFees->fetchAll();
    
    // Calculate attendance percentage
    $attendancePercent = 0;
    if ($attendanceStats && $attendanceStats['total_days'] > 0) {
        $attendancePercent = round(($attendanceStats['present_days'] / $attendanceStats['total_days']) * 100, 1);
    }
}

// Get notices for parents
$notices = $pdo->query("
    SELECT * FROM notices 
    WHERE target_role IN ('all', 'parent') 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parent Dashboard — EduRole</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }
        
        .header {
            background: #9c27b0;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .logo h1 { font-size: 24px; }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 30px; }
        
        .welcome-banner {
            background: linear-gradient(135deg, #9c27b0, #6a1b9a);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .welcome-banner h2 { font-size: 28px; margin-bottom: 10px; }
        
        .student-selector {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .student-selector select {
            padding: 10px 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            min-width: 200px;
        }
        .student-selector .btn {
            background: #9c27b0;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        
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
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: 800;
            color: #9c27b0;
        }
        .stat-card .label { color: #666; font-size: 13px; margin-top: 8px; }
        
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
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
            border-bottom: 2px solid #9c27b0;
            display: inline-block;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 0; font-size: 13px; color: #666; border-bottom: 1px solid #eee; }
        td { padding: 10px 0; font-size: 14px; color: #333; border-bottom: 1px solid #f5f5f5; }
        
        .badge-present { background: #e6f4ea; color: #34a853; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        .badge-absent { background: #fce8e6; color: #ea4335; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        .badge-late { background: #fff3e0; color: #fa7b17; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        
        .grade-A { background: #e6f4ea; color: #34a853; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 12px; }
        .grade-B { background: #e8f0fe; color: #1a73e8; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 12px; }
        .grade-C { background: #fff3e0; color: #fa7b17; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 12px; }
        .grade-D, .grade-F { background: #fce8e6; color: #ea4335; padding: 4px 10px; border-radius: 20px; display: inline-block; font-size: 12px; }
        
        .fee-amount { font-weight: 700; color: #ea4335; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        
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
        <p>Parent Portal</p>
    </div>
    <div class="user-info">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">
    <div class="welcome-banner">
        <h2>Welcome, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! 👨‍👩‍👧</h2>
        <p>Monitor your child's academic progress, attendance, and fee status.</p>
    </div>

    <?php if (count($linkedStudents) > 1): ?>
    <div class="student-selector">
        <label>Select Child:</label>
        <form method="GET" action="">
            <select name="student_id" onchange="this.form.submit()">
                <?php foreach ($linkedStudents as $student): ?>
                    <option value="<?php echo $student['user_id']; ?>" <?php echo $selected_student_id == $student['user_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['relationship']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($selected_student && $selected_student_id): ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo count($studentClasses); ?></div>
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

    <div class="two-column">
        <div class="card">
            <h3>📚 <?php echo htmlspecialchars($selected_student['full_name']); ?>'s Classes</h3>
            <?php if (empty($studentClasses)): ?>
                <div class="empty-state">No classes enrolled yet.</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Class Name</th><th>Programme</th><th>Academic Year</th></tr></thead>
                    <tbody>
                        <?php foreach ($studentClasses as $class): ?>
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

        <div class="card">
            <h3>📊 Recent Grades</h3>
            <?php if (empty($recentGrades)): ?>
                <div class="empty-state">No grades available yet.</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Subject</th><th>Assessment</th><th>Score</th><th>Grade</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentGrades as $grade):
                            $pct = $grade['percentage'];
                            $letter = $pct >= 80 ? 'A' : ($pct >= 70 ? 'B' : ($pct >= 60 ? 'C' : ($pct >= 50 ? 'D' : 'F')));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td><small><?php echo htmlspecialchars($grade['assessment_name']); ?></small></td>
                                <td><?php echo $grade['marks_obtained']; ?>/<?php echo $grade['total_marks']; ?></td>
                                <td><span class="grade-<?php echo $letter; ?>"><?php echo $letter; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="two-column">
        <div class="card">
            <h3>✅ Attendance Overview</h3>
            <?php if (!$attendanceStats || $attendanceStats['total_days'] == 0): ?>
                <div class="empty-state">No attendance records yet.</div>
            <?php else: ?>
                <table>
                    <tr><td style="width: 50%;">Total Days:</td><td><strong><?php echo $attendanceStats['total_days']; ?></strong></td></tr>
                    <tr><td>Present:</td><td style="color:#34a853;"><?php echo $attendanceStats['present_days']; ?></td></tr>
                    <tr><td>Absent:</td><td style="color:#ea4335;"><?php echo $attendanceStats['absent_days']; ?></td></tr>
                    <tr><td>Late:</td><td style="color:#fa7b17;"><?php echo $attendanceStats['late_days']; ?></td></tr>
                    <tr><td><strong>Attendance Rate:</strong></td><td><strong><?php echo $attendancePercent; ?>%</strong></td></tr>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>💰 Fee Status</h3>
            <?php if (empty($outstandingFees)): ?>
                <div class="empty-state">✅ All fees paid! Good standing.</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Fee Type</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead>
                    <tbody>
                        <?php 
                        $totalOutstanding = 0;
                        foreach ($outstandingFees as $fee):
                            $balance = $fee['amount'] - $fee['paid_amount'];
                            $totalOutstanding += $balance;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                <td>ZMW <?php echo number_format($fee['amount'], 2); ?></td>
                                <td>ZMW <?php echo number_format($fee['paid_amount'], 2); ?></td>
                                <td class="fee-amount">ZMW <?php echo number_format($balance, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="border-top: 2px solid #eee;">
                            <td><strong>Total Outstanding</strong></td>
                            <td colspan="3" class="fee-amount"><strong>ZMW <?php echo number_format($totalOutstanding, 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3>💳 Recent Payments</h3>
        <?php if (empty($recentPayments)): ?>
            <div class="empty-state">No payment history yet.</div>
        <?php else: ?>
            <table>
                <thead><tr><th>Fee Type</th><th>Amount</th><th>Date</th><th>Receipt No.</th></tr></thead>
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

    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <p>No students linked to your account yet. Please contact the school administrator.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>📢 School Announcements</h3>
        <?php if (empty($notices)): ?>
            <div class="empty-state">No announcements yet.</div>
        <?php else: ?>
            <?php foreach ($notices as $notice): ?>
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <div style="font-weight: 700; color: #9c27b0;">📌 <?php echo htmlspecialchars($notice['title']); ?></div>
                    <div style="font-size: 12px; color: #888;"><?php echo date('d M Y', strtotime($notice['created_at'])); ?></div>
                    <div style="font-size: 14px; margin-top: 8px;"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>