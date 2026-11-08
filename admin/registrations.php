<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/notifications.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "danger";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $message = "Invalid request. Please try again.";
        $messageType = "danger";
    } else {
        $registrationId = filter_input(INPUT_POST, "registration_id", FILTER_VALIDATE_INT);
        $newStatus = isset($_POST["status"]) ? strtolower(trim($_POST["status"])) : "";

        if (!$registrationId || $registrationId <= 0) {
            $message = "Invalid registration selected.";
            $messageType = "danger";
        } elseif (!in_array($newStatus, ["approved", "rejected"], true)) {
            $message = "Unsupported registration action.";
            $messageType = "danger";
        } else {
            $registrationStmt = $pdo->prepare(
                "SELECT r.id, r.user_id, r.event_id, r.status, e.event_name, e.maximum_participants, e.status AS event_status
                 FROM registrations r
                 INNER JOIN events e ON e.id = r.event_id
                 WHERE r.id = ?"
            );
            $registrationStmt->execute([$registrationId]);
            $registration = $registrationStmt->fetch();

            if (!$registration) {
                $message = "Registration not found.";
                $messageType = "danger";
            } elseif ($registration["status"] !== "pending") {
                $message = "Only pending registrations can be reviewed.";
                $messageType = "danger";
            } elseif ($newStatus === "approved") {
                $availableSlotsQuery = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM registrations
                     WHERE event_id = ?
                     AND status IN ('pending', 'approved')
                     AND id != ?"
                );
                $availableSlotsQuery->execute([$registration["event_id"], $registrationId]);
                $otherRegistrationsCount = (int) $availableSlotsQuery->fetchColumn();

                $maxParticipants = $registration["maximum_participants"]; 
                if ($maxParticipants !== null && $maxParticipants > 0 && $otherRegistrationsCount >= (int) $maxParticipants) {
                    $message = "This event is already full. Approval is not allowed.";
                    $messageType = "danger";
                } else {
                    $updateStmt = $pdo->prepare(
                        "UPDATE registrations
                         SET status = 'approved'
                         WHERE id = ? AND status = 'pending'"
                    );
                    $updateStmt->execute([$registrationId]);

                    if ($updateStmt->rowCount() > 0) {
                        createNotification(
                            $pdo,
                            (int) $registration["user_id"],
                            "Registration Approved!",
                            "Your registration for " . $registration["event_name"] . " has been approved by the administrator. You are now officially registered for this event."
                        );

                        $message = "Registration approved successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Unable to approve the registration.";
                        $messageType = "danger";
                    }
                }
            } else {
                $updateStmt = $pdo->prepare(
                    "UPDATE registrations
                     SET status = 'rejected'
                     WHERE id = ? AND status = 'pending'"
                );
                $updateStmt->execute([$registrationId]);

                if ($updateStmt->rowCount() > 0) {
                    createNotification(
                        $pdo,
                        (int) $registration["user_id"],
                        "Registration Rejected!",
                        "Your registration for " . $registration["event_name"] . " has been rejected by the administrator. Please check other available events and try again."
                    );

                    $message = "Registration rejected successfully.";
                    $messageType = "success";
                } else {
                    $message = "Unable to reject the registration.";
                    $messageType = "danger";
                }
            }
        }
    }
}

$statusFilter = isset($_GET["status"]) ? strtolower(trim($_GET["status"])) : "all";
$searchTerm = isset($_GET["search"]) ? trim($_GET["search"]) : "";

$conditions = ["1 = 1"];
$params = [];

if ($statusFilter !== "all" && in_array($statusFilter, ["pending", "approved", "rejected", "cancelled"], true)) {
    $conditions[] = "r.status = ?";
    $params[] = $statusFilter;
}

if ($searchTerm !== "") {
    $conditions[] = "(
        u.first_name LIKE ? OR
        u.last_name LIKE ? OR
        u.student_number LIKE ? OR
        e.event_name LIKE ?
    )";
    $likeTerm = "%" . $searchTerm . "%";
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
}

$query = "
    SELECT
        r.id,
        r.registration_date,
        r.status,
        u.first_name,
        u.last_name,
        u.student_number,
        e.event_name,
        e.event_date,
        e.maximum_participants,
        v.venue_name,
        c.category_name
    FROM registrations r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN events e ON e.id = r.event_id
    LEFT JOIN venues v ON v.id = e.venue_id
    LEFT JOIN categories c ON c.id = e.category_id
    WHERE " . implode(" AND ", $conditions) . "
    ORDER BY r.registration_date DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$registrations = $stmt->fetchAll();

$registrationCounts = [
    "all" => $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn(),
    "pending" => $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'pending'")->fetchColumn(),
    "approved" => $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'approved'")->fetchColumn(),
    "rejected" => $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'rejected'")->fetchColumn(),
    "cancelled" => $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'cancelled'")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrations | PTC Campus Event Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #F3F8F3;
            font-family: Arial, sans-serif;
            color: #26382C;
        }

        .sidebar {
            background: #104B2B;
            min-height: 100vh;
            padding: 25px 15px;
        }

        .sidebar h5 {
            color: white;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 8px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #237A45;
        }

        .topbar {
            background: white;
            padding: 20px 25px;
            border-bottom: 1px solid #DFEADF;
        }

        .content {
            padding: 25px;
        }

        .content-card {
            background: white;
            border: 1px solid #DFEADF;
            border-radius: 12px;
            padding: 25px;
        }

        .badge-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .badge-pending {
            background: #fff3cd;
            color: #8a6d3b;
        }

        .badge-approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #842029;
        }

        .badge-cancelled {
            background: #e2e3e5;
            color: #41464b;
        }

        .btn-green {
            background: #176B3A;
            color: white;
        }

        .btn-green:hover {
            background: #104B2B;
            color: white;
        }

        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 sidebar">
            <h5>PTC CAMPUS<br>EVENT HUB</h5>
            <a href="dashboard.php">Dashboard</a>
            <a href="events.php">Manage Events</a>
            <a href="categories.php">Categories</a>
            <a href="venues.php">Venues</a>
            <a href="students.php">Students</a>
            <a href="registrations.php" class="active">Registrations</a>
            <a href="attendance.php">Attendance</a>
            <a href="announcements.php">Announcements</a>
            <hr style="border-color: #ffffff80;">
            <a href="../auth/logout.php">Logout</a>
        </aside>

        <main class="col-md-9 col-lg-10 p-0">
            <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-1">Event Registrations</h4>
                    <small class="text-muted">Review student event applications.</small>
                </div>
                <span class="badge bg-success">Administrator</span>
            </div>

            <div class="content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="content-card mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Search Student or Event</label>
                            <form method="GET" class="d-flex gap-2">
                                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Student name, ID, or event...">
                                <button type="submit" class="btn btn-green">Search</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Filter by Status</label>
                            <form method="GET">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $statusFilter === "all" ? "selected" : ""; ?>>All</option>
                                    <option value="pending" <?php echo $statusFilter === "pending" ? "selected" : ""; ?>>Pending</option>
                                    <option value="approved" <?php echo $statusFilter === "approved" ? "selected" : ""; ?>>Approved</option>
                                    <option value="rejected" <?php echo $statusFilter === "rejected" ? "selected" : ""; ?>>Rejected</option>
                                    <option value="cancelled" <?php echo $statusFilter === "cancelled" ? "selected" : ""; ?>>Cancelled</option>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="content-card h-100">
                            <small class="text-muted">Total</small>
                            <h3 class="mb-0 fw-bold"><?php echo (int) $registrationCounts["all"]; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="content-card h-100">
                            <small class="text-muted">Pending</small>
                            <h3 class="mb-0 fw-bold"><?php echo (int) $registrationCounts["pending"]; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="content-card h-100">
                            <small class="text-muted">Approved</small>
                            <h3 class="mb-0 fw-bold"><?php echo (int) $registrationCounts["approved"]; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="content-card h-100">
                            <small class="text-muted">Rejected</small>
                            <h3 class="mb-0 fw-bold"><?php echo (int) $registrationCounts["rejected"]; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Student #</th>
                                    <th>Event</th>
                                    <th>Event Date</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($registrations): ?>
                                    <?php foreach ($registrations as $registration): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($registration["first_name"] . " " . $registration["last_name"]); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($registration["student_number"] ?? "N/A"); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($registration["event_name"] ?? "N/A"); ?>
                                                <?php if (!empty($registration["venue_name"])): ?>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($registration["venue_name"]); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(date("M d, Y", strtotime($registration["event_date"]))); ?></td>
                                            <td><?php echo htmlspecialchars(date("M d, Y", strtotime($registration["registration_date"]))); ?></td>
                                            <td>
                                                <span class="badge-status badge-<?php echo htmlspecialchars($registration["status"]); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($registration["status"])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="button"
                                                            class="btn btn-outline-secondary btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#registrationDetailModal"
                                                            data-student-name="<?php echo htmlspecialchars($registration["first_name"] . " " . $registration["last_name"]); ?>"
                                                            data-student-number="<?php echo htmlspecialchars($registration["student_number"] ?? "N/A"); ?>"
                                                            data-event-name="<?php echo htmlspecialchars($registration["event_name"] ?? "N/A"); ?>"
                                                            data-event-date="<?php echo htmlspecialchars(date("F d, Y", strtotime($registration["event_date"]))); ?>"
                                                            data-venue="<?php echo htmlspecialchars($registration["venue_name"] ?? "To be announced"); ?>"
                                                            data-category="<?php echo htmlspecialchars($registration["category_name"] ?? "General"); ?>"
                                                            data-registration-date="<?php echo htmlspecialchars(date("F d, Y", strtotime($registration["registration_date"]))); ?>"
                                                            data-status="<?php echo htmlspecialchars(ucfirst($registration["status"])); ?>">
                                                        View
                                                    </button>

                                                    <?php if ($registration["status"] === "pending"): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                                            <input type="hidden" name="registration_id" value="<?php echo (int) $registration["id"]; ?>">
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" class="btn btn-success btn-sm" name="action" value="approve">
                                                                Approve
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                                            <input type="hidden" name="registration_id" value="<?php echo (int) $registration["id"]; ?>">
                                                            <input type="hidden" name="status" value="rejected">
                                                            <button type="submit" class="btn btn-danger btn-sm" name="action" value="reject">
                                                                Reject
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No registrations match the selected filters.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="registrationDetailModal" tabindex="-1" aria-labelledby="registrationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registrationDetailModalLabel">Registration Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Student:</strong> <span id="detailStudentName"></span></p>
                <p><strong>Student Number:</strong> <span id="detailStudentNumber"></span></p>
                <p><strong>Event:</strong> <span id="detailEventName"></span></p>
                <p><strong>Event Date:</strong> <span id="detailEventDate"></span></p>
                <p><strong>Venue:</strong> <span id="detailVenue"></span></p>
                <p><strong>Category:</strong> <span id="detailCategory"></span></p>
                <p><strong>Registration Date:</strong> <span id="detailRegistrationDate"></span></p>
                <p><strong>Status:</strong> <span id="detailStatus"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const registrationDetailModal = document.getElementById('registrationDetailModal');

    registrationDetailModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;

        document.getElementById('detailStudentName').textContent = button.getAttribute('data-student-name');
        document.getElementById('detailStudentNumber').textContent = button.getAttribute('data-student-number');
        document.getElementById('detailEventName').textContent = button.getAttribute('data-event-name');
        document.getElementById('detailEventDate').textContent = button.getAttribute('data-event-date');
        document.getElementById('detailVenue').textContent = button.getAttribute('data-venue');
        document.getElementById('detailCategory').textContent = button.getAttribute('data-category');
        document.getElementById('detailRegistrationDate').textContent = button.getAttribute('data-registration-date');
        document.getElementById('detailStatus').textContent = button.getAttribute('data-status');
    });
</script>
</body>
</html>
