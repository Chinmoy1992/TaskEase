<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle leave request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leave_id = (int)$_POST['leave_id'];
    $status = mysqli_real_escape_string($conn, $_POST['action']);
    
    $sql = "UPDATE leave_requests SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $leave_id);
    $stmt->execute();
    
    header("Location: leaves.php");
    exit();
}

// Get all leave requests with user information
$leaves = $conn->query("
    SELECT lr.*, u.name as user_name 
    FROM leave_requests lr 
    LEFT JOIN users u ON lr.user_id = u.id 
    ORDER BY lr.created_at DESC
");

// Get leave request statistics
$stats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'approved_requests' => 0,
    'rejected_requests' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests");
$stats['total_requests'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
$stats['pending_requests'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'approved'");
$stats['approved_requests'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'rejected'");
$stats['rejected_requests'] = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskEase - Leave Management</title>
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
                        <a class="nav-link" href="tasks.php">Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="leaves.php">Leave Requests</a>
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
                    <h3 class="h5">Total Requests</h3>
                    <h2 class="display-4"><?php echo $stats['total_requests']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card pending">
                    <h3 class="h5">Pending</h3>
                    <h2 class="display-4"><?php echo $stats['pending_requests']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card completed">
                    <h3 class="h5">Approved</h3>
                    <h2 class="display-4"><?php echo $stats['approved_requests']; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card cancelled">
                    <h3 class="h5">Rejected</h3>
                    <h2 class="display-4"><?php echo $stats['rejected_requests']; ?></h2>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Leave Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date Range</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Requested On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($leave = $leaves->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($leave['user_name']); ?></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($leave['start_date'])); ?> -
                                    <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $leave['status'] === 'approved' ? 'success' : ($leave['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($leave['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($leave['created_at'])); ?></td>
                                <td>
                                    <?php if ($leave['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                        <button type="submit" name="action" value="approved" class="btn btn-success btn-sm">Approve</button>
                                        <button type="submit" name="action" value="rejected" class="btn btn-danger btn-sm">Reject</button>
                                    </form>
                                    <?php endif; ?>
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
</body>
</html> 