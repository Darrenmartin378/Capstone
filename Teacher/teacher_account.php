<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($new === '' || $confirm === '') {
        $flash = 'Please fill all password fields.';
    } elseif ($new !== $confirm) {
        $flash = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT password FROM teachers WHERE id = ?');
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!password_verify($old, $result['password'])) {
            $flash = 'Old password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare('UPDATE teachers SET password = ? WHERE id = ?');
            $stmt2->bind_param('si', $hash, $teacherId);
            $stmt2->execute();
            $flash = 'Password changed successfully.';
        }
    }
}

render_teacher_header('account', $teacherName, 'Account');
?>
<style>
body { background: #f6f8fc; }
.container { max-width: 500px; margin: 32px auto; }
.card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 32px; }
.card-header { background: #eef2ff; padding: 18px 24px; border-radius: 12px 12px 0 0; font-size: 1.15rem; font-weight: 600; color: #3730a3; border-bottom: 1px solid #e0e7ff; }
.card-body { padding: 24px; }
.btn { border: none; border-radius: 6px; padding: 7px 18px; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
.btn-danger { background: #f87171; color: #fff; }
.btn-danger:hover { background: #dc2626; }
.btn-primary { background: #6366f1; color: #fff; }
.btn-primary:hover { background: #4338ca; }
input[type="password"], input[type="text"] { border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 10px; font-size: 1rem; margin-top: 4px; margin-bottom: 12px; width: 100%; box-sizing: border-box; background: #f9fafb; }
label { font-weight: 500; color: #3730a3; margin-bottom: 2px; display: block; }
.flash { background: #fde68a; color: #b45309; border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; font-weight: 500; box-shadow: 0 2px 8px rgba(251,191,36,0.08); position: relative; }
.flash-close { position: absolute; right: 14px; top: 10px; background: none; border: none; font-size: 1.3rem; color: #b45309; cursor: pointer; line-height: 1; }
.flash-close:hover { color: #a16207; }
</style>
<div class="container">
    <?php if (!empty($flash)): ?>
        <div class="flash" id="flash-message">
            <?php echo h($flash); ?>
            <button type="button" class="flash-close" onclick="document.getElementById('flash-message').style.display='none';">&times;</button>
        </div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header"><strong>Account</strong></div>
        <div class="card-body">
            <p>You are logged in as <strong><?php echo h($teacherName); ?></strong>.</p>
            <a class="btn btn-danger" href="?logout=1">Logout</a>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><strong>Change Password</strong></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <label>Old Password</label>
                <input type="password" name="old_password" required>
                <label>New Password</label>
                <input type="password" name="new_password" required>
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
                <div style="text-align:right;">
                    <button class="btn btn-primary" type="submit">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php render_teacher_footer(); ?>


