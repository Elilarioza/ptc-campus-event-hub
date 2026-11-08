<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$selectedEventId = isset($_GET["event_id"]) ? (int) $_GET["event_id"] : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $registrationId = filter_input(INPUT_POST, "registration_id", FILTER_VALIDATE_INT);
    $attendanceStatus = $_POST["attendance_status"] ?? "absent";
    $checkInTime = $_POST["check_in_time"] ?? date("Y-m-d H:i:s");

    if ($registrationId && $registrationId > 0 && in_array($attendanceStatus, ["present", "absent"], true)) {
        $duplicate = $pdo->prepare("SELECT id FROM attendance WHERE registration_id = ?");
        $duplicate->execute([$registrationId]);
        if ($duplicate->fetch()) {
            $pdo->prepare("UPDATE attendance SET attendance_status = ?, check_in_time = ? WHERE registration_id = ?")->execute([$attendanceStatus, $checkInTime, $registrationId]);
        } else {
            $pdo->prepare("INSERT INTO attendance (registration_id, attendance_status, check_in_time) VALUES (?, ?, ?)")->execute([$registrationId, $attendanceStatus, $checkInTime]);
        }
    }
}

$events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();

$attendanceRows = [];
if ($selectedEventId > 0) {
    $attendanceRows = $pdo->prepare(
        "SELECT r.id AS registration_id, u.first_name, u.last_name, u.student_number, r.status,
                a.attendance_status, a.check_in_time
         FROM registrations r
         INNER JOIN users u ON u.id = r.user_id
         LEFT JOIN attendance a ON a.registration_id = r.id
         WHERE r.event_id = ? AND r.status = 'approved'
         ORDER BY u.last_name ASC"
    );
    $attendanceRows->execute([$selectedEventId]);
    $attendanceRows = $attendanceRows->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | PTC Campus Event Hub</title>
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
                <a class="nav-link" href="students.php">Students</a>
                <a class="nav-link" href="registrations.php">Registrations</a>
                <a class="nav-link active" href="attendance.php">Attendance</a>
                <a class="nav-link" href="announcements.php">Announcements</a>
                <div class="mt-4"><a class="nav-link" href="../auth/logout.php">Logout</a></div>
            </aside>

            <main class="col-md-9 col-lg-10 ptc-main">
                <div class="ptc-topbar">
                    <div>
                        <h4 class="fw-bold mb-1">Attendance Management</h4>
                        <small class="text-muted">Track attendance for approved participants.</small>
                    </div>
                    <span class="badge bg-success">Administrator</span>
                </div>

                <div class="ptc-content">
                    <div class="ptc-card p-4 mb-4">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Select Event</label>
                                <select name="event_id" class="form-select" onchange="this.form.submit()">
                                    <option value="0">Choose an event</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo (int) $event["id"]; ?>" <?php echo $selectedEventId === (int) $event["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($event["event_name"]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>

                    <?php if ($selectedEventId > 0): ?>
                        <div class="ptc-table-wrap">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Student #</th>
                                        <th>Status</th>
                                        <th>Check-in Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($attendanceRows): ?>
                                        <?php foreach ($attendanceRows as $row): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($row["first_name"] . " " . $row["last_name"]); ?></td>
                                                <td><?php echo htmlspecialchars($row["student_number"] ?? "N/A"); ?></td>
                                                <td>
                                                    <span class="ptc-badge <?php echo ($row["attendance_status"] ?? "absent") === 'present' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars(ucfirst($row["attendance_status"] ?? 'Absent')); ?></span>
                                                </td>
                                                <td><?php echo !empty($row["check_in_time"]) ? htmlspecialchars(date("M d, Y h:i A", strtotime($row["check_in_time"]))) : '—'; ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex gap-2 align-items-center">
                                                        <input type="hidden" name="registration_id" value="<?php echo (int) $row["registration_id"]; ?>">
                                                        <select name="attendance_status" class="form-select form-select-sm">
                                                            <option value="present" <?php echo ($row["attendance_status"] ?? "absent") === 'present' ? 'selected' : ''; ?>>Present</option>
                                                            <option value="absent" <?php echo ($row["attendance_status"] ?? "absent") === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                        </select>
                                                        <input type="datetime-local" name="check_in_time" class="form-control form-control-sm" value="<?php echo !empty($row["check_in_time"]) ? date("Y-m-d\TH:i", strtotime($row["check_in_time"])) : date("Y-m-d\TH:i"); ?>">
                                                        <button type="submit" class="btn btn-green btn-sm">Save</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No approved participants for this event yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
