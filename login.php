<?php
session_start();

$db = new SQLite3('shares_new.db');

// Update last activity for existing session
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $stmt = $db->prepare('UPDATE sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_id = :session_id');
    $stmt->bindValue(':session_id', session_id(), SQLITE3_TEXT);
    $stmt->execute();
}

if (isset($_GET['logout'])) {
    // Remove session from database
    $stmt = $db->prepare('DELETE FROM sessions WHERE session_id = :session_id');
    $stmt->bindValue(':session_id', session_id(), SQLITE3_TEXT);
    $stmt->execute();
    
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $htpasswd = '/etc/nginx/.htpasswd';
        $lines = file($htpasswd);
        $authenticated = false;
        
        foreach ($lines as $line) {
            list($stored_username, $stored_hash) = explode(':', trim($line));
            if ($username === $stored_username && password_verify($password, $stored_hash)) {
                $authenticated = true;
                break;
            }
        }
        
        if ($authenticated) {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            
            // Record session in database
            $stmt = $db->prepare('INSERT INTO sessions (session_id, username, ip_address) VALUES (:session_id, :username, :ip)');
            $stmt->bindValue(':session_id', session_id(), SQLITE3_TEXT);
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
            $stmt->execute();
            
            header('Location: index.php');
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Login</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
