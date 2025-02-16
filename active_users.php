<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$db = new SQLite3('shares_new.db');

// Clean up old sessions (inactive for more than 30 minutes)
$db->exec('DELETE FROM sessions WHERE (strftime("%s", "now") - strftime("%s", last_activity)) > 1800');

// Get active sessions with user role
$query = "
    SELECT 
        s.username,
        s.login_time,
        s.last_activity,
        s.ip_address,
        CASE 
            WHEN (strftime('%s', 'now') - strftime('%s', s.last_activity)) < 300 THEN 'Active'
            ELSE 'Idle'
        END as status,
        CASE 
            WHEN u.is_admin = 1 THEN 'Admin'
            ELSE 'User'
        END as role
    FROM sessions s
    LEFT JOIN users u ON s.username = u.username
    ORDER BY s.last_activity DESC
";

$result = $db->query($query);
$active_users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $active_users[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Active Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Active Users</h2>
            <div>
                <a href="index.php" class="btn btn-secondary">Back to Files</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                Currently Active Users (Last 30 minutes)
            </div>
            <div class="card-body">
                <?php if (empty($active_users)): ?>
                    <div class="alert alert-info">No active users found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Login Time</th>
                                    <th>Last Activity</th>
                                    <th>IP Address</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'Admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($user['login_time'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($user['last_activity'])); ?></td>
                                        <td><?php echo htmlspecialchars($user['ip_address']); ?></td>
                                        <td>
                                            <?php if ($user['status'] === 'Active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Idle</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
