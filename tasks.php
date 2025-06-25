<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle task operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $assigned_to = (int)$_POST['assigned_to'];
        $priority = mysqli_real_escape_string($conn, $_POST['priority']);
        $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
        
        $sql = "INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, due_date) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiss", $title, $description, $assigned_to, $_SESSION['user_id'], $priority, $due_date);
        $stmt->execute();
    } elseif ($_POST['action'] === 'update_status') {
        $task_id = (int)$_POST['task_id'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $sql = "UPDATE tasks SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $task_id);
        $stmt->execute();
    } elseif ($_POST['action'] === 'delete') {
        $task_id = (int)$_POST['task_id'];
        
        // Delete the task
        $sql = "DELETE FROM tasks WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $task_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit();
    }
}

// Get all users for assignment
$users = $conn->query("SELECT id, name FROM users WHERE role = 'user' ORDER BY name");

// Get all tasks with user information
$tasks = $conn->query("
    SELECT t.*, u.name as assigned_to_name, u2.name as assigned_by_name 
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    LEFT JOIN users u2 ON t.assigned_by = u2.id 
    ORDER BY t.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskEase - Task Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="tasks.php">Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaves.php">Leave Requests</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
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

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                    Create New Task
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Tasks</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Assigned To</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($tasks->num_rows > 0): ?>
                                <?php while ($task = $tasks->fetch_assoc()): ?>
                                <tr id="task-row-<?php echo $task['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($task['description']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['assigned_to_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm status-select" data-task-id="<?php echo $task['id']; ?>">
                                            <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $task['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($task['due_date'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewTask(<?php echo $task['id']; ?>)">View</button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteTask(<?php echo $task['id']; ?>)">Delete</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No tasks found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select class="form-select" name="assigned_to" required>
                                <option value="">Select User</option>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this task? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle status change
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const taskId = this.dataset.taskId;
                const status = this.value;
                
                fetch('tasks.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_status&task_id=${taskId}&status=${status}`
                });
            });
        });

        // Delete task functionality
        let taskToDelete = null;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));

        function deleteTask(taskId) {
            taskToDelete = taskId;
            deleteModal.show();
        }

        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (taskToDelete) {
                fetch('tasks.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&task_id=${taskToDelete}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the task row from the table
                        document.getElementById(`task-row-${taskToDelete}`).remove();
                        
                        // If no tasks left, show the "No tasks found" message
                        const tbody = document.querySelector('tbody');
                        if (tbody.children.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No tasks found</td></tr>';
                        }
                    } else {
                        alert('Error deleting task: ' + data.error);
                    }
                    deleteModal.hide();
                })
                .catch(error => {
                    alert('Error deleting task: ' + error);
                    deleteModal.hide();
                });
            }
        });

        function viewTask(taskId) {
            // Implement view task functionality if needed
            alert('View task functionality to be implemented');
        }
    </script>
</body>
</html> 