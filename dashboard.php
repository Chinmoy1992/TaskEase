<?php
// First include the configuration files
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/auth.php';

// Then start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize security and auth
$security = Security::getInstance();
$auth = Auth::getInstance();

// Check if user is logged in and is a regular user
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

try {
    // Get user's tasks
    $sql = "SELECT t.*, u.name as assigned_to_name 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_to = ? 
            ORDER BY t.due_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $tasks = $stmt->get_result();

    // Get user's leave requests
    $sql = "SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $leaves = $stmt->get_result();

    // Count tasks by status
    $task_counts = [
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0
    ];

    $sql = "SELECT status, COUNT(*) as count 
            FROM tasks 
            WHERE assigned_to = ? 
            GROUP BY status";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $status = strtolower(str_replace(' ', '_', $row['status']));
        if (isset($task_counts[$status])) {
            $task_counts[$status] = $row['count'];
        }
    }

} catch (Exception $e) {
    $error = "An error occurred while loading your dashboard.";
}

// Generate CSRF token for any forms
$csrf_token = $security->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - TaskEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .welcome-banner h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .welcome-banner p {
            margin: 0.5rem 0 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .navbar .welcome-text {
            font-weight: 600;
            color: white;
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            margin-right: 1rem;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-check2-square"></i> TaskEase
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tasks.php">
                            <i class="bi bi-list-task"></i> My Tasks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaves.php">
                            <i class="bi bi-calendar-check"></i> Leave Requests
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link welcome-text">
                            <i class="bi bi-person-circle"></i> 
                            Welcome, <?php echo $security->sanitizeOutput($_SESSION['name']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php" onclick="return confirm('Are you sure you want to logout?')">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="welcome-banner">
        <div class="container">
            <h1>Welcome, <?php echo $security->sanitizeOutput($_SESSION['name']); ?>!</h1>
            <p>Track your tasks and manage your leave requests in one place.</p>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo $security->sanitizeOutput($error); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-hourglass text-warning"></i> Pending Tasks
                        </h5>
                        <h2 class="card-text"><?php echo $security->sanitizeOutput($task_counts['pending']); ?></h2>
                        <a href="tasks.php?status=pending" class="btn btn-warning">View Tasks</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-arrow-repeat text-info"></i> In Progress
                        </h5>
                        <h2 class="card-text"><?php echo $security->sanitizeOutput($task_counts['in_progress']); ?></h2>
                        <a href="tasks.php?status=in_progress" class="btn btn-info">View Tasks</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-check-circle text-success"></i> Completed
                        </h5>
                        <h2 class="card-text"><?php echo $security->sanitizeOutput($task_counts['completed']); ?></h2>
                        <a href="tasks.php?status=completed" class="btn btn-success">View Tasks</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-task"></i> Recent Tasks
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($tasks && $tasks->num_rows > 0): ?>
                                        <?php while ($task = $tasks->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $security->sanitizeOutput($task['title']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($task['status']) {
                                                            'pending' => 'warning',
                                                            'in_progress' => 'info',
                                                            'completed' => 'success',
                                                            'cancelled' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $security->sanitizeOutput($task['status']))); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $security->sanitizeOutput($task['due_date']); ?></td>
                                                <td>
                                                    <a href="tasks.php?action=view&id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No tasks found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-check"></i> Recent Leave Requests
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($leaves && $leaves->num_rows > 0): ?>
                            <?php while ($leave = $leaves->fetch_assoc()): ?>
                                <div class="mb-3 p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?php 
                                            echo match($leave['status']) {
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($security->sanitizeOutput($leave['status'])); ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo $security->sanitizeOutput($leave['created_at']); ?>
                                        </small>
                                    </div>
                                    <div class="mt-2">
                                        <strong>From:</strong> <?php echo $security->sanitizeOutput($leave['start_date']); ?><br>
                                        <strong>To:</strong> <?php echo $security->sanitizeOutput($leave['end_date']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <div class="text-center mt-3">
                                <a href="leaves.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Request Leave
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-center mb-3">No leave requests found</p>
                            <a href="leaves.php" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle"></i> Request Leave
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html> 