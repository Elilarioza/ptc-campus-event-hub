
<?php
session_start();
require_once "../config/database.php";
require_once "../config/notifications.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$eventId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$eventId || $eventId <= 0) {
    die("Invalid event ID.");
}

$message = "";
$messageType = "error";

$stmt = $pdo->prepare("
    SELECT e.*, v.venue_name,
    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('pending','approved')) AS registered_count
    FROM events e
    LEFT JOIN venues v ON v.id = e.venue_id
    WHERE e.id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    die("Event not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($event["status"] !== "upcoming" || $event["event_date"] < date("Y-m-d")) {
        $message = "This event is no longer available for registration.";
    } elseif (!empty($event["registration_deadline"]) && $event["registration_deadline"] < date("Y-m-d")) {
        $message = "The registration deadline has passed.";
    } elseif ($event["maximum_participants"] !== null && (int) $event["registered_count"] >= (int) $event["maximum_participants"]) {
        $message = "Sorry, this event is already full.";
    } else {
        $check = $pdo->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
        $check->execute([$userId, $eventId]);
        if ($check->fetch()) {
            $message = "You are already registered for this event.";
        } else {
            $insert = $pdo->prepare("INSERT INTO registrations (user_id, event_id, status) VALUES (?, ?, 'pending')");
            $insert->execute([$userId, $eventId]);

            createNotification(
                $pdo,
                $userId,
                "Registration Submitted!",
                "Your registration for " . $event["event_name"] . " is pending approval. Please wait for the administrator to review your request."
            );

            header("Location: my-registrations.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for Event | PTC Campus Event Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ptc-style.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="event-detail-card">
                    <div class="detail-image" style="background-image:url('<?php echo !empty($event["event_image"]) ? "../" . htmlspecialchars($event["event_image"]) : "https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=1200&q=80"; ?>');"></div>
                    <div class="p-4 p-lg-5">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                            <div>
                                <span class="ptc-badge mb-2"><?php echo htmlspecialchars($event["event_name"]); ?></span>
                                <h2 class="fw-bold text-success mb-0"><?php echo htmlspecialchars($event["event_name"]); ?></h2>
                            </div>
                            <a href="events.php" class="btn btn-outline-green">Back to Events</a>
                        </div>

                        <?php if ($message): ?>
                            <div class="notice <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><div class="ptc-card p-3"><strong>Date:</strong><div><?php echo htmlspecialchars(date("F d, Y", strtotime($event["event_date"]))); ?></div></div></div>
                            <div class="col-md-6"><div class="ptc-card p-3"><strong>Venue:</strong><div><?php echo htmlspecialchars($event["venue_name"] ?? "To be announced"); ?></div></div></div>
                            <div class="col-md-6"><div class="ptc-card p-3"><strong>Time:</strong><div><?php echo date("h:i A", strtotime($event["start_time"])) . " - " . date("h:i A", strtotime($event["end_time"])); ?></div></div></div>
                            <div class="col-md-6"><div class="ptc-card p-3"><strong>Available Slots:</strong><div><?php echo max(0, (int) $event["maximum_participants"] - (int) $event["registered_count"]) . ' / ' . (int) $event["maximum_participants"]; ?></div></div></div>
                        </div>

                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($event["description"] ?? "No description available.")); ?></p>

                        <form method="POST">
                            <button type="submit" class="btn btn-green px-4">Confirm Registration</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
