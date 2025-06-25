<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Basic session check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
if (!isset($conn)) {
    die("Database connection failed");
}
if (!($conn instanceof mysqli)) {
    die("Database connection failed");
}

// Get counts for dashboard
$counts = [
    'users' => 0,
    'tasks' => 0,
    'pending_leaves' => 0
];

// Count users (excluding admin)
$sql = "SELECT COUNT(*) as count FROM users WHERE role = 'user'";
$result = $conn->query($sql);
if ($result) {
    $counts['users'] = $result->fetch_assoc()['count'];
}

// Count total tasks
$sql = "SELECT COUNT(*) as count FROM tasks";
$result = $conn->query($sql);
if ($result) {
    $counts['tasks'] = $result->fetch_assoc()['count'];
}

// Count pending leave requests
$sql = "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'";
$result = $conn->query($sql);
if ($result) {
    $counts['pending_leaves'] = $result->fetch_assoc()['count'];
}

// Get recent tasks
$sql = "SELECT t.*, u.name as assigned_to_name 
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        ORDER BY t.created_at DESC 
        LIMIT 5";
$recent_tasks = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TaskEase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f8f9fa; 
        }
        .welcome-banner { 
            background: linear-gradient(135deg, #28a745, #20c997); 
            color: white; 
            padding: 2rem 0; 
            margin-bottom: 2rem; 
        }
        .stats-card { 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            margin-bottom: 1rem; 
            padding: 1.5rem;
        }
        .card { 
            border: none; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        .stats-card h2 {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 1rem 0;
        }
        .stats-card h5 {
            color: #6c757d;
        }
        .status-badge {
            padding: 0.5em 1em;
            border-radius: 20px;
            font-weight: 600;
            text-transform: capitalize;
            font-size: 0.9em;
            display: inline-block;
            min-width: 100px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-pending {
            background: linear-gradient(45deg, #ff9800, #ffc107);
            color: #000;
        }
        .status-in-progress {
            background: linear-gradient(45deg, #2196F3, #03A9F4);
            color: #fff;
        }
        .status-completed {
            background: linear-gradient(45deg, #4CAF50, #8BC34A);
            color: #fff;
        }
        .status-cancelled {
            background: linear-gradient(45deg, #f44336, #ff5252);
            color: #fff;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#">TaskEase</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tasks.php">Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaves.php">Leave Requests</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="welcome-banner">
        <div class="container">
            <h1>Welcome to Your Dashboard</h1>
            <p>Manage your organization's tasks, users, and leave requests all in one place.</p>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="stats-card">
                    <h5>Total Users</h5>
                    <h2><?php echo $counts['users']; ?></h2>
                    <a href="users.php" class="btn btn-primary">Manage Users</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h5>Total Tasks</h5>
                    <h2><?php echo $counts['tasks']; ?></h2>
                    <a href="tasks.php" class="btn btn-success">Manage Tasks</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h5>Pending Leaves</h5>
                    <h2><?php echo $counts['pending_leaves']; ?></h2>
                    <a href="leaves.php" class="btn btn-warning">Review Leaves</a>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Recent Tasks</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($recent_tasks && $recent_tasks->num_rows > 0) {
                                while ($task = $recent_tasks->fetch_assoc()) {
                                    $status_class = '';
                                    switch(strtolower($task['status'])) {
                                        case 'pending':
                                            $status_class = 'status-pending';
                                            break;
                                        case 'in progress':
                                            $status_class = 'status-in-progress';
                                            break;
                                        case 'completed':
                                            $status_class = 'status-completed';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'status-cancelled';
                                            break;
                                    }
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($task['title']) . "</td>";
                                    echo "<td>" . htmlspecialchars($task['assigned_to_name']) . "</td>";
                                    echo "<td><span class='status-badge " . $status_class . "'>" . htmlspecialchars($task['status']) . "</span></td>";
                                    echo "<td>" . htmlspecialchars($task['due_date']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center'>No tasks found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 