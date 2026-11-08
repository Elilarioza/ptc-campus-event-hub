<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "error";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $message = "Invalid request. Please try again.";
    } else {
        $action = $_POST["action"] ?? "";
        $venueId = filter_input(INPUT_POST, "venue_id", FILTER_VALIDATE_INT);
        $venueName = trim($_POST["venue_name"] ?? "");
        $location = trim($_POST["location"] ?? "");
        $capacity = isset($_POST["capacity"]) ? (int) $_POST["capacity"] : 0;

        if ($action === "delete_venue") {
            if (!$venueId || $venueId <= 0) {
                $message = "Invalid venue selected.";
            } else {
                $used = $pdo->prepare("SELECT COUNT(*) FROM events WHERE venue_id = ?");
                $used->execute([$venueId]);
                if ((int) $used->fetchColumn() > 0) {
                    $message = "This venue is currently assigned to an event and cannot be deleted.";
                } else {
                    $pdo->prepare("DELETE FROM venues WHERE id = ?")->execute([$venueId]);
                    $message = "Venue deleted successfully.";
                    $messageType = "success";
                }
            }
        } else {
            if ($venueName === "") {
                $message = "Venue name is required.";
            } else {
                if ($action === "update_venue" && $venueId && $venueId > 0) {
                    $pdo->prepare("UPDATE venues SET venue_name = ?, location = ?, capacity = ? WHERE id = ?")->execute([$venueName, $location, $capacity, $venueId]);
                    $message = "Venue updated successfully.";
                    $messageType = "success";
                } else {
                    $pdo->prepare("INSERT INTO venues (venue_name, location, capacity) VALUES (?, ?, ?)")->execute([$venueName, $location, $capacity]);
                    $message = "Venue added successfully.";
                    $messageType = "success";
                }
            }
        }
    }
}

$editVenue = null;
if (isset($_GET["edit_id"])) {
    $editId = (int) $_GET["edit_id"];
    if ($editId > 0) {
        $editStmt = $pdo->prepare("SELECT * FROM venues WHERE id = ?");
        $editStmt->execute([$editId]);
        $editVenue = $editStmt->fetch();
    }
}

$venues = $pdo->query("SELECT * FROM venues ORDER BY venue_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venues | PTC Campus Event Hub</title>
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
                <a class="nav-link active" href="venues.php">Venues</a>
                <a class="nav-link" href="students.php">Students</a>
                <a class="nav-link" href="registrations.php">Registrations</a>
                <a class="nav-link" href="attendance.php">Attendance</a>
                <a class="nav-link" href="announcements.php">Announcements</a>
                <div class="mt-4"><a class="nav-link" href="../auth/logout.php">Logout</a></div>
            </aside>

            <main class="col-md-9 col-lg-10 ptc-main">
                <div class="ptc-topbar">
                    <div>
                        <h4 class="fw-bold mb-1">Event Venues</h4>
                        <small class="text-muted">Manage the places where campus events happen.</small>
                    </div>
                    <span class="badge bg-success">Administrator</span>
                </div>

                <div class="ptc-content">
                    <?php if ($message): ?>
                        <div class="notice <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <div class="ptc-card p-4 mb-4">
                        <h5 class="fw-bold mb-3"><?php echo $editVenue ? 'Edit Venue' : 'Add New Venue'; ?></h5>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                            <?php if ($editVenue): ?>
                                <input type="hidden" name="venue_id" value="<?php echo (int) $editVenue["id"]; ?>">
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Venue Name</label>
                                    <input type="text" name="venue_name" class="form-control" value="<?php echo htmlspecialchars($editVenue["venue_name"] ?? ""); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Location</label>
                                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($editVenue["location"] ?? ""); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Capacity</label>
                                    <input type="number" name="capacity" min="1" class="form-control" value="<?php echo htmlspecialchars((string) ($editVenue["capacity"] ?? 0)); ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" name="action" value="<?php echo $editVenue ? 'update_venue' : 'add_venue'; ?>" class="btn btn-green w-100"><?php echo $editVenue ? 'Update' : 'Add'; ?></button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="ptc-table-wrap">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Venue Name</th>
                                    <th>Location</th>
                                    <th>Capacity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($venues): ?>
                                    <?php foreach ($venues as $venue): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($venue["venue_name"]); ?></td>
                                            <td><?php echo htmlspecialchars($venue["location"] ?? "N/A"); ?></td>
                                            <td><?php echo (int) $venue["capacity"]; ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="venues.php?edit_id=<?php echo (int) $venue["id"]; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                                    <form method="POST" onsubmit="return confirm('Delete this venue?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                                        <input type="hidden" name="venue_id" value="<?php echo (int) $venue["id"]; ?>">
                                                        <input type="hidden" name="action" value="delete_venue">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No venues available yet.</td></tr>
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
