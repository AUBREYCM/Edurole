<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Delete fee structure
if (isset($_GET['delete_fee'])) {
    $stmt = $pdo->prepare("DELETE FROM fee_structure WHERE fee_id=?");
    $stmt->execute([$_GET['delete_fee']]);
    header("Location: fees.php?tab=structure&success=deleted");
    exit();
}

// Delete payment
if (isset($_GET['delete_payment'])) {
    $stmt = $pdo->prepare("DELETE FROM fee_payments WHERE payment_id=?");
    $stmt->execute([$_GET['delete_payment']]);
    header("Location: fees.php?tab=payments&success=deleted");
    exit();
}

// Add fee structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee'])) {
    $programme_id = $_POST['programme_id'];
    $fee_name = trim($_POST['fee_name']);
    $amount = $_POST['amount'];
    $academic_year = trim($_POST['academic_year']);
    $due_date = $_POST['due_date'];

    try {
        $stmt = $pdo->prepare("INSERT INTO fee_structure (programme_id, fee_name, amount, academic_year, due_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$programme_id, $fee_name, $amount, $academic_year, $due_date]);
        header("Location: fees.php?tab=structure&success=added");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Record payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $student_id = $_POST['student_id'];
    $fee_id = $_POST['fee_id'];
    $amount_paid = $_POST['amount_paid'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $receipt_number = 'RCP-' . strtoupper(uniqid());
    $recorded_by = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO fee_payments (student_id, fee_id, amount_paid, payment_date, payment_method, receipt_number, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $fee_id, $amount_paid, $payment_date, $payment_method, $receipt_number, $recorded_by]);
        header("Location: fees.php?tab=payments&success=paid");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch data
$feeStructures = $pdo->query("
    SELECT fs.*, p.programme_name 
    FROM fee_structure fs
    LEFT JOIN programmes p ON fs.programme_id = p.programme_id
    ORDER BY fs.academic_year DESC, fs.due_date ASC
")->fetchAll();

$payments = $pdo->query("
    SELECT fp.*, u.full_name as student_name, fs.fee_name, fs.amount as fee_amount
    FROM fee_payments fp
    JOIN users u ON fp.student_id = u.user_id
    JOIN fee_structure fs ON fp.fee_id = fs.fee_id
    ORDER BY fp.payment_date DESC
")->fetchAll();

$programmes = $pdo->query("SELECT programme_id, programme_name FROM programmes WHERE status='active'")->fetchAll();
$students = $pdo->query("SELECT user_id, full_name FROM users WHERE role='student' AND status='active' ORDER BY full_name")->fetchAll();

$tab = $_GET['tab'] ?? 'structure';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Management — EduRole</title>
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
        .btn-success { background: #34a853; color: white; }
        .btn-danger { background: #ea4335; color: white; font-size: 12px; padding: 6px 12px; }
        .btn-sm { font-size: 12px; padding: 5px 12px; }
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
        .badge-paid { background: #e6f4ea; color: #34a853; }
        .badge-pending { background: #fff3e0; color: #fa7b17; }
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
        .amount { font-weight: 700; color: #1a73e8; }
        .search-bar {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            width: 220px;
            outline: none;
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
        <a href="timetable.php"><span>🗓️</span> Timetable</a>
        <a href="attendance.php"><span>✅</span> Attendance</a>
        <a href="grades.php"><span>📊</span> Grades</a>
        <a href="fees.php" class="active"><span>💰</span> Fee Management</a>
        <a href="staff.php"><span>👥</span> HR / Staff</a>
        <a href="notices.php"><span>📢</span> Notices</a>
        <a href="reports.php"><span>📈</span> Reports</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
</div>

<div class="main">
    <div class="topbar">
        <h2>💰 Fee Management</h2>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab <?php echo $tab === 'structure' ? 'active' : ''; ?>" onclick="window.location.href='?tab=structure'">Fee Structure</button>
        <button class="tab <?php echo $tab === 'payments' ? 'active' : ''; ?>" onclick="window.location.href='?tab=payments'">Payments</button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            if ($_GET['success'] == 'added') echo "✅ Fee structure added successfully!";
            if ($_GET['success'] == 'deleted') echo "🗑️ Deleted successfully!";
            if ($_GET['success'] == 'paid') echo "✅ Payment recorded successfully!";
            ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- FEE STRUCTURE TAB -->
    <?php if ($tab === 'structure'): ?>
    <div class="card">
        <div class="card-header">
            <h3>📋 Fee Structure (<?php echo count($feeStructures); ?>)</h3>
            <button class="btn btn-primary" onclick="document.getElementById('addFeeModal').classList.add('active')">
                + Add Fee
            </button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fee Name</th>
                    <th>Programme</th>
                    <th>Amount (ZMW)</th>
                    <th>Academic Year</th>
                    <th>Due Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($feeStructures)): ?>
                    <tr><td colspan="7"><div class="empty-state"><div class="icon">💰</div><p>No fee structures added yet.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($feeStructures as $i => $fee): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($fee['fee_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($fee['programme_name'] ?? 'All Programmes'); ?></td>
                            <td class="amount">ZMW <?php echo number_format($fee['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                            <td><?php echo date('d M Y', strtotime($fee['due_date'])); ?></td>
                            <td>
                                <a href="?delete_fee=<?php echo $fee['fee_id']; ?>&tab=structure" class="btn btn-danger btn-sm" onclick="return confirm('Delete this fee?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- PAYMENTS TAB -->
    <?php if ($tab === 'payments'): ?>
    <div class="card">
        <div class="card-header">
            <h3>💳 Payment History (<?php echo count($payments); ?>)</h3>
            <button class="btn btn-primary" onclick="document.getElementById('addPaymentModal').classList.add('active')">
                + Record Payment
            </button>
        </div>
        <table id="paymentsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Fee Type</th>
                    <th>Amount Paid</th>
                    <th>Payment Date</th>
                    <th>Method</th>
                    <th>Receipt No.</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="8"><div class="empty-state"><div class="icon">💳</div><p>No payments recorded yet.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $i => $payment): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($payment['student_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($payment['fee_name']); ?></td>
                            <td class="amount">ZMW <?php echo number_format($payment['amount_paid'], 2); ?></td>
                            <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo str_replace('_', ' ', ucfirst($payment['payment_method'])); ?></td>
                            <td><code><?php echo $payment['receipt_number']; ?></code></td>
                            <td>
                                <a href="?delete_payment=<?php echo $payment['payment_id']; ?>&tab=payments" class="btn btn-danger btn-sm" onclick="return confirm('Delete this payment?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Add Fee Modal -->
<div class="modal-overlay" id="addFeeModal">
    <div class="modal">
        <h3>➕ Add Fee Structure</h3>
        <form method="POST">
            <div class="form-group">
                <label>Fee Name *</label>
                <input type="text" name="fee_name" required placeholder="e.g. Tuition Fee, Registration Fee">
            </div>
            <div class="form-group">
                <label>Programme (Optional)</label>
                <select name="programme_id">
                    <option value="">-- All Programmes --</option>
                    <?php foreach ($programmes as $prog): ?>
                        <option value="<?php echo $prog['programme_id']; ?>"><?php echo htmlspecialchars($prog['programme_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Amount (ZMW) *</label>
                    <input type="number" name="amount" required step="0.01" placeholder="e.g. 5000.00">
                </div>
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="text" name="academic_year" required placeholder="e.g. 2025/2026">
                </div>
            </div>
            <div class="form-group">
                <label>Due Date *</label>
                <input type="date" name="due_date" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addFeeModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="add_fee" class="btn btn-primary">Add Fee</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal-overlay" id="addPaymentModal">
    <div class="modal">
        <h3>💰 Record Payment</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Student *</label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['user_id']; ?>"><?php echo htmlspecialchars($student['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fee Type *</label>
                    <select name="fee_id" required>
                        <option value="">-- Select Fee --</option>
                        <?php foreach ($feeStructures as $fee): ?>
                            <option value="<?php echo $fee['fee_id']; ?>"><?php echo htmlspecialchars($fee['fee_name']); ?> (ZMW <?php echo number_format($fee['amount'], 2); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Amount Paid *</label>
                    <input type="number" name="amount_paid" required step="0.01" placeholder="e.g. 5000.00">
                </div>
                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Payment Method *</label>
                <select name="payment_method" required>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_money">Mobile Money</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="document.getElementById('addPaymentModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="add_payment" class="btn btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>