<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

$db = new SQLite3('shares_new.db');

// Update session activity
$stmt = $db->prepare('UPDATE sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_id = :session_id');
$stmt->bindValue(':session_id', session_id(), SQLITE3_TEXT);
$stmt->execute();

// Check if user is admin
$stmt = $db->prepare('SELECT is_admin FROM users WHERE username = :username');
$stmt->bindValue(':username', $_SESSION['username'], SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result->fetchArray();
$is_admin = $row && $row['is_admin'] == 1;
$_SESSION['is_admin'] = $is_admin;

// Get current viewing user (for admin view)
$viewing_user = isset($_GET['user']) && $is_admin ? $_GET['user'] : $_SESSION['username'];

// Function to format file path for display
function formatFilePath($filename) {
    return str_replace('/', ' › ', $filename);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Sharing System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
    .btn-group { 
        white-space: nowrap;
        gap: 5px;
    }
    .card {
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .nav-tabs { 
        margin-bottom: 15px; 
    }
    .upload-section {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    .file-item .file-name {
    flex: 1;
    min-width: 0;
    margin-right: 20px;  /* Αλλαγή από 15px σε 20px */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    }
    .file-item:last-child { 
        border-bottom: none; 
    }
	.file-item .btn {
    margin-left: 5px;
    }
    .file-item:hover {
        background-color: #f8f9fa;
    }
    .file-item small {
        font-size: 0.85em;
        color: #6c757d;
    }
    .card-header { 
        font-weight: bold; 
    }
    .current-time {
        font-size: 0.9em;
        color: #6c757d;
    }
    /* Νέα styles για το πρόβλημα με τα μεγάλα ονόματα */
    .file-item .file-name {
        flex: 1;
        min-width: 0;
        margin-right: 15px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
	.file-item .file-name span {
    font-weight: bold;
    }
    .file-item .btn-group {
    flex-shrink: 0;
    min-width: fit-content;
    }
    </style>
    <script>
    function copyLink(token) {
        var baseUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
        baseUrl = baseUrl.replace('/index.php', '');
        var downloadLink = baseUrl + '/download.php?token=' + token;

        navigator.clipboard.writeText(downloadLink).then(function() {
            alert('Download link copied to clipboard!');
        });
    }

    function confirmDelete(token, filename) {
        if (confirm('Are you sure you want to delete "' + filename + '"?')) {
            window.location.href = 'delete.php?token=' + token;
        }
    }

    function getDirectLink(filename, basePath) {
        // Καθάρισε το filename από τυχόν 1/ στην αρχή
        filename = filename.replace(/^1\//, '');
        
        // Φτιάξε το URL
        var directLink = 'http://' + window.location.host + '/files/shared/1/' + filename;
        
        // Καθάρισε τυχόν διπλά slashes
        directLink = directLink.replace(/([^:])\/+/g, '$1/');
        
        navigator.clipboard.writeText(directLink);
        alert('Direct link copied to clipboard!');
    }
    </script>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Welcome <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
                <div class="current-time">
                    Current time (UTC): <?php echo date('Y-m-d H:i:s'); ?>
                </div>
            </div>
            <div>
                <?php if ($is_admin): ?>
                    <a href="users.php" class="btn btn-success me-2">User Management</a>
                    <a href="active_users.php" class="btn btn-info me-2">Active Users</a>
                <?php endif; ?>
                <a href="account.php" class="btn btn-info me-2">Account Settings</a>
                <a href="login.php?logout=1" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <?php if (isset($_GET['upload'])): ?>
            <?php if ($_GET['upload'] === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    $count = isset($_GET['count']) ? (int)$_GET['count'] : 1;
                    echo $count . " file" . ($count > 1 ? "s" : "") . " uploaded successfully!";
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['upload'] === 'mixed'): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?php
                    $success = isset($_GET['success']) ? (int)$_GET['success'] : 0;
                    $errors = isset($_GET['errors']) ? (int)$_GET['errors'] : 0;
                    echo "$success file" . ($success > 1 ? "s" : "") . " uploaded successfully, ";
                    echo "$errors file" . ($errors > 1 ? "s" : "") . " failed to upload.";
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                User Files
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs">
                    <?php
                    $users_result = $db->query('SELECT username FROM users ORDER BY username');
                    while ($user = $users_result->fetchArray()) {
                        $username = $user['username'];
                        $active = ($viewing_user === $username) ? ' active' : '';
                        echo "<li class='nav-item'>";
                        echo "<a class='nav-link$active' href='?user=" . urlencode($username) . "'>";
                        echo htmlspecialchars($username);
                        echo "</a>";
                        echo "</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        Upload Files
                    </div>
                    <div class="card-body">
                        <form action="upload.php" method="post" enctype="multipart/form-data">
                            <div class="upload-section">
                                <label class="form-label">Upload Files</label>
                                <input type="file" class="form-control mb-2" name="fileToUpload[]" multiple>
                                <small class="text-muted d-block">Select multiple files using Ctrl+Click or drag select</small>
                            </div>
                            <div class="upload-section">
                                <label class="form-label">Or Upload Folder</label>
                                <input type="file" class="form-control mb-2" id="folderInput" name="folderUpload[]"
                                       webkitdirectory directory multiple accept="*/*">
                                <small class="text-muted d-block">Select an entire folder to upload</small>
                            </div>
                            <button type="submit" class="btn btn-success">Upload</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
    <div class="card-header bg-primary text-white">
        <?php echo $is_admin ? "Files for " . htmlspecialchars($viewing_user) : "Your Files"; ?>
    </div>
    <div class="card-body">
    <?php
    $stmt = $db->prepare('SELECT * FROM shares 
                        WHERE username = :username 
                        AND filename NOT LIKE "%/" 
                        AND filename <> "" 
                        AND length(filename) > 1
                        AND filename NOT LIKE "2025"
                        AND filename NOT LIKE "2025/02"
                        ORDER BY upload_date DESC');
    $stmt->bindValue(':username', $viewing_user, SQLITE3_TEXT);
    $result = $stmt->execute();

    $files = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $filename = $row['filename'];
        
        // Skip if it's just a folder path
        if (empty($filename) || substr($filename, -1) === '/') {
            continue;
        }
        
        // Get only the actual filename without the path
        $displayName = basename($filename);
        
        $files[] = array(
            'displayName' => $displayName,
            'filename' => $filename,
            'token' => $row['token'],
            'upload_date' => $row['upload_date']
        );
    }
    // Ταξινόμηση του array με βάση το displayName
	usort($files, function($a, $b) {
		return strcasecmp($a['displayName'], $b['displayName']);
	});
    $hasFiles = !empty($files);

    if ($hasFiles) {
        foreach ($files as $file) {
            echo "<div class='file-item'>";
            echo "<div class='file-name'>";
            echo "<span>" . htmlspecialchars($file['displayName']) . "</span>";
            echo "<small class='text-muted d-block'>" . date('Y-m-d H:i:s', strtotime($file['upload_date'])) . "</small>";
            echo "</div>";
            echo "<div class='btn-group'>";
            echo "<button class='btn btn-sm btn-secondary' onclick='copyLink(\"{$file['token']}\")'>Copy Link</button>";
            echo "<a href='download.php?token={$file['token']}' class='btn btn-sm btn-primary'>Download</a>";
            if ($viewing_user === $_SESSION['username'] || $is_admin) {
                echo "<button class='btn btn-sm btn-danger' onclick='confirmDelete(\"{$file['token']}\", \"{$file['filename']}\")'>Delete</button>";
            }
            echo "</div>";
            echo "</div>";
        }
    } else {
        echo "<div class='alert alert-info mb-0'>No files available.</div>";
    }
    ?>
</div>
</div>
            </div>
        </div>

        <!-- Shared Files Section -->
<!-- Shared Files Section -->
<div class="card mt-4">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <span>Shared Files</span>
        <?php if ($is_admin): ?>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#uploadSharedModal">
            Upload to Shared
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
    <?php
    $stmt = $db->prepare('SELECT * FROM shares 
                        WHERE username = :username 
                        AND filename NOT LIKE "%/" 
                        AND filename <> "" 
                        AND length(filename) > 1
                        AND filename NOT LIKE "2025"
                        AND filename NOT LIKE "1"
                        AND filename NOT LIKE "2025/02"');
                        
    $stmt->bindValue(':username', 'shared', SQLITE3_TEXT);
    $result = $stmt->execute();

    $files = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $filename = $row['filename'];
        
        // Skip if it's just a folder path
        if (empty($filename) || substr($filename, -1) === '/') {
            continue;
        }
        
        // Get only the actual filename without the path
        $displayName = basename($filename);
        
        // Προσθήκη στο array μόνο αν δεν είναι φάκελος
        if ($displayName !== '2025' && $displayName !== '02' && $displayName !== '1') {
            $files[] = array(
                'displayName' => $displayName,
                'filename' => $filename,
                'token' => $row['token']
            );
        }
    }

    // Ταξινόμηση του array με βάση το displayName
    usort($files, function($a, $b) {
        return strcasecmp($a['displayName'], $b['displayName']);
    });

    $hasSharedFiles = !empty($files);

    if ($hasSharedFiles) {
        foreach ($files as $file) {
            echo "<div class='file-item'>";
            echo "<div class='file-name'>";
            echo "<span>" . htmlspecialchars($file['displayName']) . "</span>";
            echo "</div>";
            echo "<div class='btn-group'>";
            echo "<button class='btn btn-sm btn-secondary' onclick='copyLink(\"{$file['token']}\")'>Copy Link</button>";
            echo "<button class='btn btn-sm btn-info' onclick='getDirectLink(\"" . htmlspecialchars($file['filename']) . "\", \"\")'>Direct Link</button>";
            echo "<a href='download.php?token={$file['token']}' class='btn btn-sm btn-primary'>Download</a>";
            if ($is_admin) {
                echo "<button class='btn btn-sm btn-danger' onclick='confirmDelete(\"{$file['token']}\", \"{$file['filename']}\")'>Delete</button>";
            }
            echo "</div>";
            echo "</div>";
        }
    } else {
        echo "<div class='alert alert-info mb-0'>No shared files available.</div>";
    }
    ?>
</div>
</div>

    <?php if ($is_admin): ?>
    <!-- Upload to Shared Modal -->
    <div class="modal fade" id="uploadSharedModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload to Shared Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="upload_shared.php" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Target Folder (optional)</label>
                            <input type="text" class="form-control" name="folder" placeholder="e.g., folder/subfolder">
                            <small class="text-muted">Leave empty to upload to root shared folder</small>
                        </div>
                        <div class="upload-section">
                            <label class="form-label">Upload Files</label>
                            <input type="file" class="form-control mb-2" name="fileToUpload[]" multiple>
                            <small class="text-muted d-block">Select multiple files using Ctrl+Click or drag select</small>
                        </div>
                        <div class="upload-section">
                            <label class="form-label">Or Upload Folder</label>
                            <input type="file" class="form-control mb-2" id="sharedFolderInput" name="folderUpload[]"
                                   webkitdirectory directory multiple accept="*/*">
                            <small class="text-muted d-block">Select an entire folder to upload</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload to Shared</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>