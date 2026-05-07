<?php
session_start();
require '../db.php';

// Admin-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ─── ACTIONS ────────────────────────────────────────────────────────────────

// Deactivate parent
if (isset($_GET['deactivate'])) {
    $stmt = $pdo->prepare("UPDATE users SET status='inactive' WHERE user_id=? AND role='parent'");
    $stmt->execute([$_GET['deactivate']]);
    header("Location: parents.php?success=deactivated");
    exit();
}

// Activate parent
if (isset($_GET['activate'])) {
    $stmt = $pdo->prepare("UPDATE users SET status='active' WHERE user_id=? AND role='parent'");
    $stmt->execute([$_GET['activate']]);
    header("Location: parents.php?success=activated");
    exit();
}

// Delete parent (removes user + all links)
if (isset($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM parent_student WHERE parent_id=?")->execute([$_GET['delete']]);
        $pdo->prepare("DELETE FROM users WHERE user_id=? AND role='parent'")->execute([$_GET['delete']]);
        $pdo->commit();
        header("Location: parents.php?success=deleted");
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Could not delete parent: " . $e->getMessage();
    }
    exit();
}

// Unlink a parent–student relationship
if (isset($_GET['unlink'])) {
    $pdo->prepare("DELETE FROM parent_student WHERE id=?")->execute([$_GET['unlink']]);
    header("Location: parents.php?success=unlinked");
    exit();
}

// Add new parent account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_parent'])) {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $address   = trim($_POST['address']);
    $password  = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, phone, address, status) VALUES (?, ?, ?, 'parent', ?, ?, 'active')");
        $stmt->execute([$full_name, $email, $password, $phone, $address]);
        header("Location: parents.php?success=added");
        exit();
    } catch (PDOException $e) {
        $error = "Email already exists or an error occurred.";
    }
}

// Link parent to student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_student'])) {
    $parent_id    = (int)$_POST['parent_id'];
    $student_id   = (int)$_POST['student_id'];
    $relationship = trim($_POST['relationship']);

    // Check duplicate link
    $check = $pdo->prepare("SELECT id FROM parent_student WHERE parent_id=? AND student_id=?");
    $check->execute([$parent_id, $student_id]);

    if ($check->fetch()) {
        $error = "This parent is already linked to that student.";
    } else {
        try {
            $pdo->prepare("INSERT INTO parent_student (parent_id, student_id, relationship) VALUES (?, ?, ?)")
                ->execute([$parent_id, $student_id, $relationship]);
            header("Location: parents.php?success=linked");
            exit();
        } catch (PDOException $e) {
            $error = "Could not link parent: " . $e->getMessage();
        }
    }
}

// ─── DATA FETCHING ────────────────────────────────────────────────────────────

// All parents
$parents = $pdo->query("SELECT * FROM users WHERE role='parent' ORDER BY created_at DESC")->fetchAll();

// All students (for the link dropdown)
$students = $pdo->query("SELECT user_id, full_name, email FROM users WHERE role='student' AND status='active' ORDER BY full_name ASC")->fetchAll();

// All parent–student links with names
$links = $pdo->query("
    SELECT ps.id, ps.relationship,
           p.user_id  AS parent_id,  p.full_name  AS parent_name,  p.email AS parent_email,
           s.user_id  AS student_id, s.full_name  AS student_name, s.email AS student_email
    FROM parent_student ps
    JOIN users p ON ps.parent_id  = p.user_id
    JOIN users s ON ps.student_id = s.user_id
    ORDER BY p.full_name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parents — EduRole</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }

        /* ── Sidebar ── */
        .sidebar {
            width: 250px;
            background: #1a73e8;
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
        }
        .brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }
        .brand h1 { color: #fff; font-size: 22px; letter-spacing: 1px; }
        .brand p  { color: rgba(255,255,255,0.7); font-size: 12px; margin-top: 2px; }
        nav { padding: 12px 0; }
        nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 20px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        nav a:hover, nav a.active { background: rgba(255,255,255,0.15); color: #fff; }
        nav a span { font-size: 16px; width: 20px; text-align: center; }

        /* ── Main ── */
        .main { margin-left: 250px; padding: 28px; min-height: 100vh; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .topbar h2 { font-size: 22px; color: #333; }

        /* ── Tabs ── */
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .tab-btn {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            background: none;
            cursor: pointer;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab-btn.active { color: #1a73e8; border-bottom-color: #1a73e8; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── Cards ── */
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .card-header h3 { font-size: 15px; color: #333; }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; }
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
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9f9f9; }

        /* ── Badges ── */
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-active   { background: #e6f4ea; color: #34a853; }
        .badge-inactive { background: #fce8e6; color: #ea4335; }
        .badge-rel      { background: #e8f0fe; color: #1a73e8; }

        /* ── Buttons ── */
        .btn {
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            border: none;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary  { background: #1a73e8; color: #fff; }
        .btn-success  { background: #34a853; color: #fff; }
        .btn-danger   { background: #ea4335; color: #fff; }
        .btn-warning  { background: #fbbc04; color: #333; }
        .btn-cancel   { background: #f0f0f0; color: #333; }
        .btn-sm       { padding: 4px 10px; font-size: 12px; }
        .btn-group    { display: flex; gap: 6px; }

        /* ── Alerts ── */
        .alert {
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #e6f4ea; color: #34a853; }
        .alert-error   { background: #fce8e6; color: #ea4335; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #999;
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 10px; }

        /* ── Search ── */
        .search-bar {
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            width: 240px;
            outline: none;
        }

        /* ── Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff;
            border-radius: 12px;
            padding: 28px 32px;
            width: 520px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
        }
        .modal h3 { font-size: 18px; color: #333; margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus { border-color: #1a73e8; }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 8px;
        }

        /* ── Info pill ── */
        .linked-students {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .pill {
            background: #e8f0fe;
            color: #1a73e8;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 20px;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <h1>EDUROLE</h1>
        <p>Admin Panel</p>
    </div>
    <nav>
        <a href="dashboard.php"><span>🏠</span> Dashboard</a>
        <a href="students.php"><span>🎓</span> Students</a>
        <a href="teachers.php"><span>👨‍🏫</span> Teachers</a>
        <a href="parents.php" class="active"><span>👨‍👩‍👧</span> Parents</a>
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

<!-- Main Content -->
<div class="main">
    <div class="topbar">
        <h2>👨‍👩‍👧 Parent Management</h2>
        <button class="btn btn-primary" onclick="openModal('addParentModal')">+ Add New Parent</button>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            $msgs = [
                'added'      => '✅ Parent account created successfully!',
                'deactivated'=> '⛔ Parent account deactivated.',
                'activated'  => '✅ Parent account activated.',
                'deleted'    => '🗑️ Parent account deleted permanently.',
                'linked'     => '🔗 Parent linked to student successfully!',
                'unlinked'   => '✂️ Parent–student link removed.',
            ];
            echo $msgs[$_GET['success']] ?? 'Action completed.';
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('accounts', this)">👤 Parent Accounts (<?php echo count($parents); ?>)</button>
        <button class="tab-btn" onclick="switchTab('links', this)">🔗 Student Links (<?php echo count($links); ?>)</button>
    </div>

    <!-- ── TAB 1: Parent Accounts ── -->
    <div id="tab-accounts" class="tab-panel active">
        <div class="card">
            <div class="card-header">
                <h3>All Parent Accounts</h3>
                <input type="text" class="search-bar" id="parentSearch" placeholder="🔍 Search parents..." onkeyup="searchTable('parentSearch','parentTable')">
            </div>
            <table id="parentTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Linked Students</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parents)): ?>
                        <tr><td colspan="8">
                            <div class="empty-state">
                                <div class="icon">👨‍👩‍👧</div>
                                <p>No parent accounts yet. Click "Add New Parent" to create one.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($parents as $i => $parent): ?>
                            <?php
                            // Fetch linked students for this parent
                            $linked = $pdo->prepare("
                                SELECT u.full_name FROM parent_student ps
                                JOIN users u ON ps.student_id = u.user_id
                                WHERE ps.parent_id = ?
                            ");
                            $linked->execute([$parent['user_id']]);
                            $linkedStudents = $linked->fetchAll();
                            ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($parent['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($parent['email']); ?></td>
                                <td><?php echo htmlspecialchars($parent['phone'] ?? '—'); ?></td>
                                <td>
                                    <?php if (empty($linkedStudents)): ?>
                                        <span style="color:#999; font-size:13px;">None</span>
                                    <?php else: ?>
                                        <div class="linked-students">
                                            <?php foreach ($linkedStudents as $s): ?>
                                                <span class="pill"><?php echo htmlspecialchars($s['full_name']); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $parent['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo ucfirst($parent['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($parent['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <!-- Link student button -->
                                        <button class="btn btn-primary btn-sm"
                                            onclick="openLinkModal(<?php echo $parent['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($parent['full_name'])); ?>')">
                                            🔗 Link
                                        </button>
                                        <!-- Toggle active/inactive -->
                                        <?php if ($parent['status'] === 'active'): ?>
                                            <a href="?deactivate=<?php echo $parent['user_id']; ?>" class="btn btn-warning btn-sm"
                                               onclick="return confirm('Deactivate this parent account?')">Deactivate</a>
                                        <?php else: ?>
                                            <a href="?activate=<?php echo $parent['user_id']; ?>" class="btn btn-success btn-sm">Activate</a>
                                        <?php endif; ?>
                                        <!-- Delete -->
                                        <a href="?delete=<?php echo $parent['user_id']; ?>" class="btn btn-danger btn-sm"
                                           onclick="return confirm('Permanently delete this parent and all their student links? This cannot be undone.')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── TAB 2: Parent–Student Links ── -->
    <div id="tab-links" class="tab-panel">
        <div class="card">
            <div class="card-header">
                <h3>All Parent–Student Relationships</h3>
                <input type="text" class="search-bar" id="linkSearch" placeholder="🔍 Search links..." onkeyup="searchTable('linkSearch','linksTable')">
            </div>
            <table id="linksTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Parent Name</th>
                        <th>Parent Email</th>
                        <th>Student Name</th>
                        <th>Student Email</th>
                        <th>Relationship</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($links)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <div class="icon">🔗</div>
                                <p>No parent–student links yet. Use the "Link" button on a parent account to connect them to a student.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($links as $i => $link): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($link['parent_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($link['parent_email']); ?></td>
                                <td><strong><?php echo htmlspecialchars($link['student_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($link['student_email']); ?></td>
                                <td>
                                    <span class="badge badge-rel"><?php echo htmlspecialchars($link['relationship'] ?: 'Not specified'); ?></span>
                                </td>
                                <td>
                                    <a href="?unlink=<?php echo $link['id']; ?>" class="btn btn-danger btn-sm"
                                       onclick="return confirm('Remove this parent–student link?')">✂️ Unlink</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── MODAL: Add New Parent ── -->
<div class="modal-overlay" id="addParentModal">
    <div class="modal">
        <h3>➕ Add New Parent Account</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required placeholder="e.g. Mary Banda">
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required placeholder="parent@example.com">
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
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" placeholder="Parent's home address">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('addParentModal')">Cancel</button>
                <button type="submit" name="add_parent" class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL: Link Parent to Student ── -->
<div class="modal-overlay" id="linkStudentModal">
    <div class="modal">
        <h3 id="linkModalTitle">🔗 Link Parent to Student</h3>
        <form method="POST">
            <input type="hidden" name="parent_id" id="linkParentId">
            <div class="form-group">
                <label>Parent</label>
                <input type="text" id="linkParentName" readonly style="background:#f5f5f5; color:#666;">
            </div>
            <div class="form-group">
                <label>Select Student *</label>
                <select name="student_id" required>
                    <option value="">— Choose a student —</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['user_id']; ?>">
                            <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Relationship *</label>
                <select name="relationship" required>
                    <option value="">— Select relationship —</option>
                    <option value="Father">Father</option>
                    <option value="Mother">Mother</option>
                    <option value="Guardian">Guardian</option>
                    <option value="Grandparent">Grandparent</option>
                    <option value="Sibling">Sibling</option>
                    <option value="Uncle/Aunt">Uncle / Aunt</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('linkStudentModal')">Cancel</button>
                <button type="submit" name="link_student" class="btn btn-primary">🔗 Link Student</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Modal helpers ──
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal when clicking outside
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});

// Open link modal pre-filled with parent details
function openLinkModal(parentId, parentName) {
    document.getElementById('linkParentId').value = parentId;
    document.getElementById('linkParentName').value = parentName;
    document.getElementById('linkModalTitle').textContent = '🔗 Link ' + parentName + ' to a Student';
    openModal('linkStudentModal');
}

// ── Tab switching ──
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ── Table search ──
function searchTable(inputId, tableId) {
    const query = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
}
</script>

</body>
</html>
