
<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$studentCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$eventCount = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$upcomingCount = (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'upcoming' AND event_date >= CURDATE()")->fetchColumn();
$ongoingCount = (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'ongoing'")->fetchColumn();
$totalRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$pendingRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'pending'")->fetchColumn();

$recentRegistrations = $pdo->query(
    "SELECT r.*, u.first_name, u.last_name, e.event_name
     FROM registrations r
     INNER JOIN users u ON u.id = r.user_id
     INNER JOIN events e ON e.id = r.event_id
     ORDER BY r.registration_date DESC
     LIMIT 5"
)->fetchAll();

$recentEvents = $pdo->query(
    "SELECT e.event_name, e.event_date, e.status, c.category_name
     FROM events e
     LEFT JOIN categories c ON c.id = e.category_id
     ORDER BY e.created_at DESC
     LIMIT 5"
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="../assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ptc-style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <aside class="col-md-3 col-lg-2 ptc-sidebar">
                <span class="brand d-flex align-items-center gap-2 text-white">
                    <img src="../assets/images/ptc-logo.png" alt="Pateros Technological College logo">
                    <span class="brand-text">PTC CAMPUS<br>EVENT HUB</span>
                </span>
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="events.php">Manage Events</a>
                <a class="nav-link" href="categories.php">Categories</a>
                <a class="nav-link" href="venues.php">Venues</a>
                <a class="nav-link" href="students.php">Students</a>
                <a class="nav-link" href="registrations.php">Registrations</a>
                <a class="nav-link" href="attendance.php">Attendance</a>
                <a class="nav-link" href="announcements.php">Announcements</a>
                <div class="mt-4"><a class="nav-link" href="../auth/logout.php">Logout</a></div>
            </aside>

            <main class="col-md-9 col-lg-10 ptc-main">
                <div class="ptc-topbar">
                    <div>
                        <h4 class="fw-bold mb-1">Admin Dashboard</h4>
                        <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</small>
                    </div>
                    <span class="badge bg-success">Administrator</span>
                </div>

                <div class="ptc-content">
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-4"><div class="ptc-stat"><div class="label">Total Students</div><div class="value"><?php echo $studentCount; ?></div></div></div>
                        <div class="col-sm-6 col-xl-4"><div class="ptc-stat"><div class="label">Total Events</div><div class="value"><?php echo $eventCount; ?></div></div></div>
                        <div class="col-sm-6 col-xl-4"><div class="ptc-stat"><div class="label">Upcoming Events</div><div class="value"><?php echo $upcomingCount; ?></div></div></div>
                        <div class="col-sm-6 col-xl-4"><div class="ptc-stat"><div class="label">Ongoing Events</div><div class="value"><?php echo $ongoingCount; ?></div></div></div>
                        <div class="col-sm-6 col-xl-4"><div class="ptc-stat"><div class="label">Total Registrations</div><div class="value"><?php echo $totalRegistrations; ?></div></div></div>
                        <div class="col-sm-6 col-xl-4"><div class="ptc-stat"><div class="label">Pending Registrations</div><div class="value"><?php echo $pendingRegistrations; ?></div></div></div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="ptc-card p-4 mb-4">
                                <h5 class="fw-bold mb-3 text-success">Recent Registrations</h5>
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead>
                                            <tr><th>Student</th><th>Event</th><th>Status</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recentRegistrations): ?>
                                                <?php foreach ($recentRegistrations as $row): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row["first_name"] . " " . $row["last_name"]); ?></td>
                                                        <td><?php echo htmlspecialchars($row["event_name"]); ?></td>
                                                        <td><span class="ptc-badge <?php echo $row["status"] === 'approved' ? 'success' : ($row["status"] === 'pending' ? 'secondary' : 'danger'); ?>"><?php echo htmlspecialchars(ucfirst($row["status"])); ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="3" class="text-center text-muted py-4">No registrations available.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="ptc-card p-4 mb-4">
                                <h5 class="fw-bold mb-3 text-success">Recent Events</h5>
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead>
                                            <tr><th>Event</th><th>Date</th><th>Status</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recentEvents): ?>
                                                <?php foreach ($recentEvents as $row): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row["event_name"]); ?></td>
                                                        <td><?php echo htmlspecialchars(date("M d, Y", strtotime($row["event_date"]))); ?></td>
                                                        <td><span class="ptc-badge <?php echo $row["status"] === 'upcoming' ? 'success' : ($row["status"] === 'ongoing' ? 'secondary' : 'danger'); ?>"><?php echo htmlspecialchars(ucfirst($row["status"])); ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="3" class="text-center text-muted py-4">No events available.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>