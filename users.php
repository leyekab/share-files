<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

$db = new SQLite3('shares_new.db');

// Handle user deletion
if (isset($_GET['delete'])) {
    $user_to_delete = $_GET['delete'];
    if ($user_to_delete !== 'pantgr') {
        $htpasswd_file = '/etc/nginx/.htpasswd';
        $lines = file($htpasswd_file);
        $new_lines = [];
        foreach ($lines as $line) {
            if (strpos($line, $user_to_delete . ':') !== 0) {
                $new_lines[] = $line;
            }
        }
        if (file_put_contents($htpasswd_file, implode('', $new_lines))) {
            // Also remove from users table
            $stmt = $db->prepare('DELETE FROM users WHERE username = :username');
            $stmt->bindValue(':username', $user_to_delete, SQLITE3_TEXT);
            $stmt->execute();
            $message = "User $user_to_delete deleted successfully!";
        } else {
            $error = "Failed to delete user!";
        }
    } else {
        $error = "Cannot delete main admin user!";
    }
}

// Handle admin role toggle
if (isset($_GET['toggle_admin'])) {
    $username = $_GET['toggle_admin'];
    if ($username !== 'pantgr') {
        $stmt = $db->prepare('INSERT OR REPLACE INTO users (username, is_admin) 
                             VALUES (:username, COALESCE(
                                 (SELECT CASE WHEN is_admin = 1 THEN 0 ELSE 1 END 
                                  FROM users WHERE username = :username),
                                 1
                             ))');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->execute();
        $message = "Admin status updated for $username";
    } else {
        $error = "Cannot modify main admin status!";
    }
}

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_username']) && isset($_POST['new_password'])) {
        $new_username = trim($_POST['new_username']);
        $new_password = $_POST['new_password'];
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        if (empty($new_username) || empty($new_password)) {
            $error = "Username and password are required!";
        } else {
            $htpasswd_file = '/etc/nginx/.htpasswd';
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $new_line = $new_username . ':' . $hash . "\n";
            
            if (file_put_contents($htpasswd_file, $new_line, FILE_APPEND)) {
                // Add to users table with admin status
                $stmt = $db->prepare('INSERT OR REPLACE INTO users (username, is_admin) VALUES (:username, :is_admin)');
                $stmt->bindValue(':username', $new_username, SQLITE3_TEXT);
                $stmt->bindValue(':is_admin', $is_admin, SQLITE3_INTEGER);
                $stmt->execute();
                $message = "User $new_username added successfully!";
            } else {
                $error = "Failed to add user!";
            }
        }
    }
}

// Get list of users with their admin status
$users = [];
if (file_exists('/etc/nginx/.htpasswd')) {
    $lines = file('/etc/nginx/.htpasswd');
    foreach ($lines as $line) {
        $username = explode(':', trim($line))[0];
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray();
        $users[] = [
            'username' => $username,
            'is_admin' => $row ? $row['is_admin'] : 0
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
    function confirmDelete(username) {
        if (username === 'pantgr') {
            alert('Cannot delete main admin user!');
            return false;
        }
        return confirm('Are you sure you want to delete user "' + username + '"?');
    }
    </script>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">User Management</h4>
                        <a href="index.php" class="btn btn-secondary">Back to Files</a>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <h5>Add New User</h5>
                        <form method="post" class="mb-4">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="new_username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="is_admin" id="is_admin">
                                <label class="form-check-label" for="is_admin">Make Admin</label>
                            </div>
                            <button type="submit" class="btn btn-primary">Add User</button>
                        </form>
                        
                        <h5>Existing Users</h5>
                        <div class="list-group">
                            <?php foreach ($users as $user): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge bg-success ms-2">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="btn-group">
                                        <?php if ($user['username'] !== 'pantgr'): ?>
                                            <a href="?toggle_admin=<?php echo urlencode($user['username']); ?>" 
                                               class="btn btn-sm btn-<?php echo $user['is_admin'] ? 'warning' : 'success'; ?>">
                                                <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                            </a>
                                            <a href="?delete=<?php echo urlencode($user['username']); ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirmDelete('<?php echo htmlspecialchars($user['username']); ?>')">
                                                Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
