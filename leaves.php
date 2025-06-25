<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a regular user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

// Handle leave request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_leave') {
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    $sql = "INSERT INTO leave_requests (user_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $_SESSION['user_id'], $start_date, $end_date, $reason);
    $stmt->execute();
    
    header("Location: leaves.php");
    exit();
}

// Get user's leave requests
$user_id = $_SESSION['user_id'];
$leaves = $conn->query("
    SELECT * FROM leave_requests 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC
");

// Get leave request statistics
$stats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'approved_requests' => 0,
    'rejected_requests' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE user_id = $user_id");
$stats['total_requests'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE user_id = $user_id AND status = 'pending'");
$stats['pending_requests'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE user_id = $user_id AND status = 'approved'");
$stats['approved_requests'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE user_id = $user_id AND status = 'rejected'");
$stats['rejected_requests'] = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskEase - Leave Requests</title>
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
                        <a class="nav-link" href="tasks.php">My Tasks</a>
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

        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Request Leave</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="request_leave">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <textarea class="form-control" name="reason" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Leave History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date Range</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Requested On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($leave = $leaves->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($leave['start_date'])); ?> -
                                            <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $start = new DateTime($leave['start_date']);
                                            $end = new DateTime($leave['end_date']);
                                            $days = $end->diff($start)->days + 1;
                                            echo $days . ' day' . ($days > 1 ? 's' : '');
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $leave['status'] === 'approved' ? 'success' : ($leave['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($leave['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add date validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const startDate = new Date(this.start_date.value);
            const endDate = new Date(this.end_date.value);
            const today = new Date();
            
            if (startDate < today) {
                alert('Start date cannot be in the past');
                e.preventDefault();
                return;
            }
            
            if (endDate < startDate) {
                alert('End date cannot be before start date');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html> 