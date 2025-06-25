<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a regular user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $task_id = (int)$_POST['task_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $sql = "UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $status, $task_id, $_SESSION['user_id']);
    $stmt->execute();
    
    header("Location: tasks.php");
    exit();
}

// Get user's tasks with additional information
$user_id = $_SESSION['user_id'];
$tasks = $conn->query("
    SELECT t.*, u.name as assigned_by_name 
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_by = u.id 
    WHERE t.assigned_to = $user_id 
    ORDER BY 
        CASE 
            WHEN t.status = 'pending' THEN 1
            WHEN t.status = 'in_progress' THEN 2
            WHEN t.status = 'completed' THEN 3
            ELSE 4
        END,
        t.due_date ASC
");

// Get task statistics
$stats = [
    'total_tasks' => 0,
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = $user_id");
$stats['total_tasks'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = $user_id AND status = 'pending'");
$stats['pending_tasks'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = $user_id AND status = 'in_progress'");
$stats['in_progress_tasks'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = $user_id AND status = 'completed'");
$stats['completed_tasks'] = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskEase - My Tasks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">TaskEase</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="tasks.php">My Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaves.php">Leave Requests</a>
                    </li>
                </ul>
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>
                </span>
                <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="dashboard-card">
                    <h3 class="h5">Total Tasks</h3>
                    <h2 class="display-4"><?php echo $stats['total_tasks']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card pending">
                    <h3 class="h5">Pending</h3>
                    <h2 class="display-4"><?php echo $stats['pending_tasks']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card">
                    <h3 class="h5">In Progress</h3>
                    <h2 class="display-4"><?php echo $stats['in_progress_tasks']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card completed">
                    <h3 class="h5">Completed</h3>
                    <h2 class="display-4"><?php echo $stats['completed_tasks']; ?></h2>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">My Tasks</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Assigned By</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($task = $tasks->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($task['description']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($task['assigned_by_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <?php 
                                    $due_date = strtotime($task['due_date']);
                                    $today = strtotime('today');
                                    $date_class = $due_date < $today ? 'text-danger' : ($due_date == $today ? 'text-warning' : 'text-success');
                                    ?>
                                    <span class="<?php echo $date_class; ?>">
                                        <?php echo date('M d, Y', $due_date); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewTask(<?php echo $task['id']; ?>)">View Details</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewTask(taskId) {
            // Implement view task functionality
            alert('View task ' + taskId);
        }
    </script>
</body>
</html> 