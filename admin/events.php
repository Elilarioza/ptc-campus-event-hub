
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
$messageType = "danger";

$allowedStatuses = ["upcoming", "ongoing", "completed", "cancelled"];
$searchTerm = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$statusFilter = isset($_GET["status"]) ? strtolower(trim($_GET["status"])) : "all";
$categoryFilter = isset($_GET["category"]) ? (int) $_GET["category"] : 0;
$editEventId = isset($_GET["edit_id"]) ? (int) $_GET["edit_id"] : 0;

function sanitizeUploadedImage(string $tmpName, string $originalName): ?string
{
    if (!is_uploaded_file($tmpName)) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    $allowedMimeTypes = ["image/jpeg", "image/png", "image/webp"];
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return null;
    }

    $filesize = filesize($tmpName);
    if ($filesize === false || $filesize > 2 * 1024 * 1024) {
        return null;
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ["jpg", "jpeg", "png", "webp"], true)) {
        return null;
    }

    $uploadDir = __DIR__ . "/../assets/images/events";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = "event_" . uniqid() . "." . $extension;
    $destination = $uploadDir . "/" . $fileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        return null;
    }

    return "assets/images/events/" . $fileName;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $message = "Invalid request. Please try again.";
        $messageType = "danger";
    } else {
        $action = $_POST["action"] ?? "";

        if ($action === "delete_event") {
            $eventId = filter_input(INPUT_POST, "event_id", FILTER_VALIDATE_INT);

            if (!$eventId || $eventId <= 0) {
                $message = "Invalid event selected.";
                $messageType = "danger";
            } else {
                try {
                    $pdo->beginTransaction();

                    $eventImageStmt = $pdo->prepare("SELECT event_image FROM events WHERE id = ?");
                    $eventImageStmt->execute([$eventId]);
                    $eventImage = $eventImageStmt->fetchColumn();

                    $registrationIdsStmt = $pdo->prepare("SELECT id FROM registrations WHERE event_id = ?");
                    $registrationIdsStmt->execute([$eventId]);
                    $registrationIds = $registrationIdsStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($registrationIds)) {
                        $placeholders = implode(",", array_fill(0, count($registrationIds), "?"));
                        $pdo->prepare("DELETE FROM attendance WHERE registration_id IN (" . $placeholders . ")")
                            ->execute($registrationIds);
                        $pdo->prepare("DELETE FROM registrations WHERE event_id = ?")->execute([$eventId]);
                    }

                    $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$eventId]);

                    if (!empty($eventImage) && file_exists(__DIR__ . "/../" . $eventImage)) {
                        unlink(__DIR__ . "/../" . $eventImage);
                    }

                    $pdo->commit();
                    $message = "Event successfully deleted.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = "Unable to delete event. Please try again.";
                    $messageType = "danger";
                }
            }
        } elseif ($action === "save_event" || $action === "update_event") {
            $eventId = filter_input(INPUT_POST, "event_id", FILTER_VALIDATE_INT);
            $eventName = trim($_POST["event_name"] ?? "");
            $categoryId = !empty($_POST["category_id"]) ? (int) $_POST["category_id"] : null;
            $venueId = !empty($_POST["venue_id"]) ? (int) $_POST["venue_id"] : null;
            $description = trim($_POST["description"] ?? "");
            $eventDate = $_POST["event_date"] ?? "";
            $startTime = !empty($_POST["start_time"]) ? $_POST["start_time"] : null;
            $endTime = !empty($_POST["end_time"]) ? $_POST["end_time"] : null;
            $registrationDeadline = !empty($_POST["registration_deadline"]) ? $_POST["registration_deadline"] : null;
            $maximumParticipants = isset($_POST["maximum_participants"]) ? (int) $_POST["maximum_participants"] : 0;
            $status = isset($_POST["status"]) ? strtolower(trim($_POST["status"])) : "upcoming";

            if ($eventName === "" || $eventDate === "" || $maximumParticipants < 1) {
                $message = "Please complete the required event fields.";
                $messageType = "danger";
            } elseif (!in_array($status, $allowedStatuses, true)) {
                $message = "Invalid event status selected.";
                $messageType = "danger";
            } elseif ($categoryId !== null) {
                $categoryCheck = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
                $categoryCheck->execute([$categoryId]);
                if (!$categoryCheck->fetch()) {
                    $message = "Selected category is invalid.";
                    $messageType = "danger";
                } elseif ($venueId !== null) {
                    $venueCheck = $pdo->prepare("SELECT id FROM venues WHERE id = ?");
                    $venueCheck->execute([$venueId]);
                    if (!$venueCheck->fetch()) {
                        $message = "Selected venue is invalid.";
                        $messageType = "danger";
                    } elseif ($startTime && $endTime && $endTime <= $startTime) {
                        $message = "End time must be later than the start time.";
                        $messageType = "danger";
                    } else {
                        $newImagePath = null;
                        if (!empty($_FILES["event_image"]["name"])) {
                            $newImagePath = sanitizeUploadedImage($_FILES["event_image"]["tmp_name"], $_FILES["event_image"]["name"]);
                            if ($newImagePath === null) {
                                $message = "Please upload a valid JPG, PNG, or WEBP image under 2MB.";
                                $messageType = "danger";
                            }
                        }

                        if ($message === "") {
                            try {
                                $pdo->beginTransaction();

                                if ($action === "update_event" && $eventId && $eventId > 0) {
                                    $existingImageStmt = $pdo->prepare("SELECT event_image FROM events WHERE id = ?");
                                    $existingImageStmt->execute([$eventId]);
                                    $existingImage = $existingImageStmt->fetchColumn();

                                    $query = "
                                        UPDATE events
                                        SET category_id = ?, venue_id = ?, event_name = ?, description = ?,
                                            event_date = ?, start_time = ?, end_time = ?, registration_deadline = ?,
                                            maximum_participants = ?, status = ?, event_image = ?, created_by = ?
                                        WHERE id = ?
                                    ";
                                    $params = [
                                        $categoryId,
                                        $venueId,
                                        $eventName,
                                        $description,
                                        $eventDate,
                                        $startTime,
                                        $endTime,
                                        $registrationDeadline,
                                        $maximumParticipants,
                                        $status,
                                        $newImagePath ?? $existingImage,
                                        $_SESSION["user_id"],
                                        $eventId
                                    ];

                                    $statement = $pdo->prepare($query);
                                    $statement->execute($params);

                                    if ($newImagePath && !empty($existingImage) && file_exists(__DIR__ . "/../" . $existingImage)) {
                                        unlink(__DIR__ . "/../" . $existingImage);
                                    }

                                    $message = "Event updated successfully.";
                                } else {
                                    $query = "
                                        INSERT INTO events (
                                            category_id, venue_id, event_name, description, event_date,
                                            start_time, end_time, event_image, registration_deadline,
                                            maximum_participants, status, created_by
                                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ";
                                    $statement = $pdo->prepare($query);
                                    $statement->execute([
                                        $categoryId,
                                        $venueId,
                                        $eventName,
                                        $description,
                                        $eventDate,
                                        $startTime,
                                        $endTime,
                                        $newImagePath,
                                        $registrationDeadline,
                                        $maximumParticipants,
                                        $status,
                                        $_SESSION["user_id"]
                                    ]);

                                    $message = "Event successfully added.";
                                }

                                $pdo->commit();
                                $messageType = "success";
                            } catch (PDOException $e) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                $message = "Unable to save the event. Please try again.";
                                $messageType = "danger";
                            }
                        }
                    }
                }
            }
        } elseif ($action === "update_status") {
            $eventId = filter_input(INPUT_POST, "event_id", FILTER_VALIDATE_INT);
            $status = isset($_POST["status"]) ? strtolower(trim($_POST["status"])) : "";

            if (!$eventId || $eventId <= 0) {
                $message = "Invalid event selected.";
                $messageType = "danger";
            } elseif (!in_array($status, $allowedStatuses, true)) {
                $message = "Invalid event status selected.";
                $messageType = "danger";
            } else {
                $stmt = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
                $stmt->execute([$status, $eventId]);
                $message = "Event status updated successfully.";
                $messageType = "success";
            }
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$venues = $pdo->query("SELECT * FROM venues ORDER BY venue_name ASC")->fetchAll();

$eventConditions = ["1 = 1"];
$eventParams = [];

if ($searchTerm !== "") {
    $eventConditions[] = "(e.event_name LIKE ? OR e.description LIKE ? OR c.category_name LIKE ? OR v.venue_name LIKE ?)";
    $likeTerm = "%" . $searchTerm . "%";
    $eventParams[] = $likeTerm;
    $eventParams[] = $likeTerm;
    $eventParams[] = $likeTerm;
    $eventParams[] = $likeTerm;
}

if ($categoryFilter > 0) {
    $eventConditions[] = "e.category_id = ?";
    $eventParams[] = $categoryFilter;
}

if ($statusFilter !== "all" && in_array($statusFilter, $allowedStatuses, true)) {
    $eventConditions[] = "e.status = ?";
    $eventParams[] = $statusFilter;
}

$eventsQuery = "
    SELECT
        e.*,
        c.category_name,
        v.venue_name,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('pending', 'approved')) AS registered_count
    FROM events e
    LEFT JOIN categories c ON c.id = e.category_id
    LEFT JOIN venues v ON v.id = e.venue_id
    WHERE " . implode(" AND ", $eventConditions) . "
    ORDER BY e.event_date DESC, e.created_at DESC
";

$eventsStmt = $pdo->prepare($eventsQuery);
$eventsStmt->execute($eventParams);
$events = $eventsStmt->fetchAll();

$editEvent = null;
if ($editEventId > 0) {
    $editStmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $editStmt->execute([$editEventId]);
    $editEvent = $editStmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events | PTC Campus Event Hub</title>
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
            margin-bottom: 25px;
        }

        .btn-green {
            background: #176B3A;
            color: white;
        }

        .btn-green:hover {
            background: #104B2B;
            color: white;
        }

        .event-thumb {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #DFEADF;
            background: #F8FAF8;
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
            <a href="events.php" class="active">Manage Events</a>
            <a href="categories.php">Categories</a>
            <a href="venues.php">Venues</a>
            <a href="students.php">Students</a>
            <a href="registrations.php">Registrations</a>
            <a href="attendance.php">Attendance</a>
            <a href="announcements.php">Announcements</a>
            <hr style="border-color: #ffffff80;">
            <a href="../auth/logout.php">Logout</a>
        </aside>

        <main class="col-md-9 col-lg-10 p-0">
            <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-1">Manage Events</h4>
                    <small class="text-muted">Add, edit, and track campus activities.</small>
                </div>
                <span class="badge bg-success">Administrator</span>
            </div>

            <div class="content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="fw-bold mb-0"><?php echo $editEvent ? 'Edit Event' : 'Add New Event'; ?></h5>
                        <?php if ($editEvent): ?>
                            <a href="events.php" class="btn btn-outline-secondary btn-sm">Cancel Edit</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                        <?php if ($editEvent): ?>
                            <input type="hidden" name="event_id" value="<?php echo (int) $editEvent["id"]; ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Event Name *</label>
                                <input type="text" name="event_name" class="form-control" value="<?php echo htmlspecialchars($editEvent["event_name"] ?? ""); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo (int) $category["id"]; ?>" <?php echo (($editEvent["category_id"] ?? "") == $category["id"]) ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($category["category_name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Venue</label>
                                <select name="venue_id" class="form-select">
                                    <option value="">Select Venue</option>
                                    <?php foreach ($venues as $venue): ?>
                                        <option value="<?php echo (int) $venue["id"]; ?>" <?php echo (($editEvent["venue_id"] ?? "") == $venue["id"]) ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($venue["venue_name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach ($allowedStatuses as $statusValue): ?>
                                        <option value="<?php echo htmlspecialchars($statusValue); ?>" <?php echo (($editEvent["status"] ?? "upcoming") === $statusValue) ? "selected" : ""; ?>>
                                            <?php echo ucfirst(htmlspecialchars($statusValue)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Event Date *</label>
                                <input type="date" name="event_date" class="form-control" value="<?php echo htmlspecialchars($editEvent["event_date"] ?? ""); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Registration Deadline</label>
                                <input type="date" name="registration_deadline" class="form-control" value="<?php echo htmlspecialchars($editEvent["registration_deadline"] ?? ""); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Start Time</label>
                                <input type="time" name="start_time" class="form-control" value="<?php echo htmlspecialchars($editEvent["start_time"] ?? ""); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">End Time</label>
                                <input type="time" name="end_time" class="form-control" value="<?php echo htmlspecialchars($editEvent["end_time"] ?? ""); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Maximum Participants *</label>
                                <input type="number" name="maximum_participants" class="form-control" min="1" value="<?php echo htmlspecialchars((string) ($editEvent["maximum_participants"] ?? 100)); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Event Image</label>
                                <input type="file" name="event_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <?php if (!empty($editEvent["event_image"])): ?>
                                    <div class="mt-2">
                                        <img src="../<?php echo htmlspecialchars($editEvent["event_image"]); ?>" class="event-thumb" alt="Event preview">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Event Description</label>
                                <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($editEvent["description"] ?? ""); ?></textarea>
                            </div>
                        </div>

                        <button type="submit" name="action" value="<?php echo $editEvent ? 'update_event' : 'save_event'; ?>" class="btn btn-green mt-4 px-4">
                            <?php echo $editEvent ? 'Update Event' : 'Add Event'; ?>
                        </button>
                    </form>
                </div>

                <div class="content-card">
                    <div class="row g-3 mb-3">
                        <div class="col-lg-5">
                            <form method="GET" class="d-flex gap-2">
                                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search events...">
                                <button type="submit" class="btn btn-green">Search</button>
                            </form>
                        </div>
                        <div class="col-lg-4">
                            <form method="GET">
                                <select name="category" class="form-select" onchange="this.form.submit()">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo (int) $category["id"]; ?>" <?php echo $categoryFilter === (int) $category["id"] ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($category["category_name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <div class="col-lg-3">
                            <form method="GET">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $statusFilter === "all" ? "selected" : ""; ?>>All Status</option>
                                    <?php foreach ($allowedStatuses as $statusValue): ?>
                                        <option value="<?php echo htmlspecialchars($statusValue); ?>" <?php echo $statusFilter === $statusValue ? "selected" : ""; ?>>
                                            <?php echo ucfirst(htmlspecialchars($statusValue)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Event</th>
                                    <th>Category</th>
                                    <th>Venue</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Slots</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($events): ?>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($event["event_image"])): ?>
                                                    <img src="../<?php echo htmlspecialchars($event["event_image"]); ?>" class="event-thumb" alt="<?php echo htmlspecialchars($event["event_name"]); ?>">
                                                <?php else: ?>
                                                    <div class="event-thumb d-flex align-items-center justify-content-center text-muted small">N/A</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($event["event_name"]); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($event["description"] ?? "No description"); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($event["category_name"] ?? "Uncategorized"); ?></td>
                                            <td><?php echo htmlspecialchars($event["venue_name"] ?? "To be announced"); ?></td>
                                            <td><?php echo htmlspecialchars(date("M d, Y", strtotime($event["event_date"]))); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $event["status"] === "upcoming" ? "success" : ($event["status"] === "ongoing" ? "primary" : ($event["status"] === "completed" ? "secondary" : "danger")); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($event["status"])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                    $slotCount = (int) $event["maximum_participants"];
                                                    $registeredCount = (int) $event["registered_count"];
                                                    $availableSlots = $slotCount > 0 ? max(0, $slotCount - $registeredCount) : 0;
                                                    echo $availableSlots . ' / ' . $slotCount;
                                                ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="events.php?edit_id=<?php echo (int) $event["id"]; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                                    <form method="POST" onsubmit="return confirm('Delete this event and all associated registrations?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                                        <input type="hidden" name="event_id" value="<?php echo (int) $event["id"]; ?>">
                                                        <input type="hidden" name="action" value="delete_event">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </div>
                                                <div class="mt-2">
                                                    <form method="POST" class="d-flex gap-2">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                                        <input type="hidden" name="event_id" value="<?php echo (int) $event["id"]; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                            <?php foreach ($allowedStatuses as $statusValue): ?>
                                                                <option value="<?php echo htmlspecialchars($statusValue); ?>" <?php echo $event["status"] === $statusValue ? "selected" : ""; ?>>
                                                                    <?php echo ucfirst(htmlspecialchars($statusValue)); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No events match your current search or filter.</td>
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
</body>
</html>
