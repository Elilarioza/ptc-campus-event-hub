<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$search = trim($_GET["search"] ?? "");
$conditions = ["role = 'student'"];
$params = [];

if ($search !== "") {
    $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR student_number LIKE ? OR email LIKE ?)";
    $like = "%" . $search . "%";
    $params = [$like, $like, $like, $like];
}

$query = "SELECT id, first_name, last_name, student_number, email, created_at FROM users WHERE " . implode(" AND ", $conditions) . " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students | PTC Campus Event Hub</title>
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
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="events.php">Manage Events</a>
                <a class="nav-link" href="categories.php">Categories</a>
                <a class="nav-link" href="venues.php">Venues</a>
                <a class="nav-link active" href="students.php">Students</a>
                <a class="nav-link" href="registrations.php">Registrations</a>
                <a class="nav-link" href="attendance.php">Attendance</a>
                <a class="nav-link" href="announcements.php">Announcements</a>
                <div class="mt-4"><a class="nav-link" href="../auth/logout.php">Logout</a></div>
            </aside>

            <main class="col-md-9 col-lg-10 ptc-main">
                <div class="ptc-topbar">
                    <div>
                        <h4 class="fw-bold mb-1">Student Accounts</h4>
                        <small class="text-muted">View registered students and keep student records organized.</small>
                    </div>
                    <span class="badge bg-success">Administrator</span>
                </div>

                <div class="ptc-content">
                    <div class="ptc-card p-4 mb-4">
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student name, number, or email">
                            <button type="submit" class="btn btn-green">Search</button>
                        </form>
                    </div>

                    <div class="ptc-table-wrap">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student Number</th>
                                    <th>Email</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students): ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($student["first_name"] . " " . $student["last_name"]); ?></td>
                                            <td><?php echo htmlspecialchars($student["student_number"] ?? "N/A"); ?></td>
                                            <td><?php echo htmlspecialchars($student["email"]); ?></td>
                                            <td><?php echo htmlspecialchars(date("M d, Y", strtotime($student["created_at"]))); ?></td>
                                            <td><span class="ptc-badge success">Active</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No student records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
