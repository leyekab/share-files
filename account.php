<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['current_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        } else {
            $htpasswd_file = '/etc/nginx/.htpasswd';
            $lines = file($htpasswd_file);
            $new_lines = [];
            $password_changed = false;
            
            foreach ($lines as $line) {
                if (strpos($line, $_SESSION['username'] . ':') === 0) {
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $new_lines[] = $_SESSION['username'] . ':' . $new_hash . "\n";
                    $password_changed = true;
                } else {
                    $new_lines[] = $line;
                }
            }
            
            if ($password_changed) {
                file_put_contents($htpasswd_file, implode('', $new_lines));
                $message = "Password changed successfully!";
            } else {
                $error = "Failed to change password!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Account Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Account Management</h4>
                        <a href="index.php" class="btn btn-secondary">Back to Files</a>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
