<?php
session_start();
require_once __DIR__ . "/config/database.php";

date_default_timezone_set("Asia/Manila");

$currentMonth = isset($_GET["month"]) ? max(1, min(12, (int) $_GET["month"])) : (int) date("n");
$currentYear = isset($_GET["year"]) ? max(1970, (int) $_GET["year"]) : (int) date("Y");
$search = trim($_GET["search"] ?? "");
$categoryFilter = isset($_GET["category"]) ? (int) $_GET["category"] : 0;

$selectedMonthStart = new DateTimeImmutable(sprintf("%04d-%02d-01", $currentYear, $currentMonth));
$selectedMonthEnd = $selectedMonthStart->modify("last day of this month");
$today = new DateTimeImmutable("today");
$prevMonth = $selectedMonthStart->modify("-1 month");
$nextMonth = $selectedMonthStart->modify("+1 month");

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$eventQuery = "
    SELECT e.*, c.category_name, v.venue_name,
    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('pending', 'approved')) AS registered_count
    FROM events e
    LEFT JOIN categories c ON c.id = e.category_id
    LEFT JOIN venues v ON v.id = e.venue_id
    WHERE e.status != 'cancelled'
      AND e.event_date BETWEEN ? AND ?
";
$params = [$selectedMonthStart->format("Y-m-d"), $selectedMonthEnd->format("Y-m-d")];

if ($search !== "") {
    $eventQuery .= " AND e.event_name LIKE ?";
    $params[] = "%" . $search . "%";
}

if ($categoryFilter > 0) {
    $eventQuery .= " AND e.category_id = ?";
    $params[] = $categoryFilter;
}

$eventQuery .= " ORDER BY e.event_date ASC, e.start_time ASC";

$eventStmt = $pdo->prepare($eventQuery);
$eventStmt->execute($params);
$monthEvents = $eventStmt->fetchAll();

$eventsByDay = [];
foreach ($monthEvents as $event) {
    $dateKey = $event["event_date"];
    if (!isset($eventsByDay[$dateKey])) {
        $eventsByDay[$dateKey] = [];
    }
    $eventsByDay[$dateKey][] = $event;
}

$upcomingQuery = "
    SELECT e.*, c.category_name, v.venue_name,
    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('pending', 'approved')) AS registered_count
    FROM events e
    LEFT JOIN categories c ON c.id = e.category_id
    LEFT JOIN venues v ON v.id = e.venue_id
    WHERE e.status != 'cancelled' AND e.event_date >= CURDATE()
";

if ($categoryFilter > 0) {
    $upcomingQuery .= " AND e.category_id = ?";
    $upcomingParams = [$categoryFilter];
} else {
    $upcomingParams = [];
}

$upcomingQuery .= " ORDER BY e.event_date ASC, e.start_time ASC LIMIT 8";
$upcomingStmt = $pdo->prepare($upcomingQuery);
$upcomingStmt->execute($upcomingParams);
$upcomingEvents = $upcomingStmt->fetchAll();

$calendarEventsForJs = [];
foreach ($monthEvents as $event) {
    $detailLink = (isset($_SESSION["user_id"]) && isset($_SESSION["role"]) && $_SESSION["role"] === "student")
        ? "student/register-event.php?id=" . (int) $event["id"]
        : "auth/login.php";

    $calendarEventsForJs[] = [
        "id" => (int) $event["id"],
        "name" => $event["event_name"],
        "category" => $event["category_name"] ?? "General Event",
        "date" => date("F d, Y", strtotime($event["event_date"])),
        "time" => ($event["start_time"] && $event["end_time"]) ? date("h:i A", strtotime($event["start_time"])) . " - " . date("h:i A", strtotime($event["end_time"])) : "Time to be announced",
        "venue" => $event["venue_name"] ?? "To be announced",
        "description" => $event["description"] ?? "No description available.",
        "deadline" => !empty($event["registration_deadline"]) ? date("F d, Y", strtotime($event["registration_deadline"])) : "Open registration",
        "slots" => (int) $event["maximum_participants"] > 0 ? max(0, (int) $event["maximum_participants"] - (int) $event["registered_count"]) . " / " . (int) $event["maximum_participants"] : "Open",
        "status" => ucfirst($event["status"] ?? "upcoming"),
        "detailUrl" => $detailLink,
        "registerText" => (isset($_SESSION["user_id"]) && isset($_SESSION["role"]) && $_SESSION["role"] === "student") ? "View Event Details" : "Login to View Details",
        "registerUrl" => (isset($_SESSION["user_id"]) && isset($_SESSION["role"]) && $_SESSION["role"] === "student") ? "student/register-event.php?id=" . (int) $event["id"] : "auth/login.php",
    ];
}

$selectedCategoryName = "";
if ($categoryFilter > 0) {
    foreach ($categories as $category) {
        if ((int) $category["id"] === $categoryFilter) {
            $selectedCategoryName = $category["category_name"];
            break;
        }
    }
}

$calendarStartDay = (int) $selectedMonthStart->format("w");
$daysInMonth = (int) $selectedMonthStart->format("t");
$cellTotal = 42;
$calendarCells = [];
for ($i = 0; $i < $calendarStartDay; $i++) {
    $calendarCells[] = null;
}
for ($day = 1; $day <= $daysInMonth; $day++) {
    $calendarCells[] = $day;
}
while (count($calendarCells) < $cellTotal) {
    $calendarCells[] = null;
}

$categoryColorMap = [
    1 => "cat-one",
    2 => "cat-two",
    3 => "cat-three",
    4 => "cat-four",
    5 => "cat-five",
    6 => "cat-six",
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Event Calendar | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/ptc-style.css">
</head>
<body>
    <?php if (isset($_SESSION["user_id"]) && $_SESSION["role"] === "student"): ?>
        <nav class="navbar navbar-expand-lg ptc-navbar student-navbar">
            <div class="container">
                <div class="student-navbar-inner">
                    <div class="student-navbar-brand">
                        <a class="navbar-brand ptc-brand" href="student/dashboard.php" aria-label="PTC Campus Event Hub home">
                            <img src="assets/images/ptc-logo.png" alt="Pateros Technological College logo">
                            <span class="brand-text">PTC CAMPUS EVENT HUB</span>
                        </a>
                    </div>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNav" aria-controls="studentNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
                    </button>

                    <div class="collapse navbar-collapse student-navbar-menu" id="studentNav">
                        <ul class="navbar-nav align-items-lg-center gap-lg-1">
                            <li class="nav-item"><a href="student/dashboard.php" class="student-nav-item nav-link" title="Dashboard"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
                            <li class="nav-item"><a href="calendar.php" class="student-nav-item nav-link active" title="Calendar"><i class="bi bi-calendar3"></i><span>Calendar</span></a></li>
                            <li class="nav-item"><a href="student/events.php" class="student-nav-item nav-link" title="Events"><i class="bi bi-calendar-event"></i><span>Events</span></a></li>
                            <li class="nav-item"><a href="student/my-registrations.php" class="student-nav-item nav-link" title="My Registrations"><i class="bi bi-clipboard-check"></i><span>My Registrations</span></a></li>
                            <li class="nav-item"><a href="index.php#announcements" class="student-nav-item nav-link" title="Announcements"><i class="bi bi-megaphone"></i><span>Announcements</span></a></li>
                            <li class="nav-item"><a href="student/profile.php" class="student-nav-item nav-link" title="Profile"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>
                            <li class="nav-item"><a href="student/notifications.php" class="student-nav-item nav-link" title="Notifications"><i class="bi bi-bell-fill"></i><span>Notifications</span></a></li>
                            <li class="nav-item"><a href="auth/logout.php" class="btn btn-light btn-sm ms-lg-2" title="Logout">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    <?php elseif (isset($_SESSION["user_id"]) && $_SESSION["role"] === "admin"): ?>
        <nav class="navbar navbar-expand-lg ptc-navbar">
            <div class="container">
                <div class="ptc-navbar-inner">
                    <div class="ptc-navbar-brand">
                        <a class="navbar-brand ptc-brand" href="admin/dashboard.php" aria-label="PTC Campus Event Hub home">
                            <img src="assets/images/ptc-logo.png" alt="Pateros Technological College logo">
                            <span class="brand-text">PTC CAMPUS EVENT HUB</span>
                        </a>
                    </div>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
                    </button>

                    <div class="collapse navbar-collapse ptc-navbar-menu" id="siteNav">
                        <ul class="navbar-nav align-items-lg-center">
                            <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Admin Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="admin/events.php">Events</a></li>
                            <li class="nav-item ms-lg-3"><a class="btn btn-outline-light" href="auth/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    <?php else: ?>
        <nav class="navbar navbar-expand-lg ptc-navbar">
            <div class="container">
                <div class="ptc-navbar-inner">
                    <div class="ptc-navbar-brand">
                        <a class="navbar-brand ptc-brand" href="index.php" aria-label="PTC Campus Event Hub home">
                            <img src="assets/images/ptc-logo.png" alt="Pateros Technological College logo">
                            <span class="brand-text">PTC CAMPUS EVENT HUB</span>
                        </a>
                    </div>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
                    </button>

                    <div class="collapse navbar-collapse ptc-navbar-menu" id="siteNav">
                        <ul class="navbar-nav align-items-lg-center">
                            <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                            <li class="nav-item"><a class="nav-link active" href="calendar.php">Calendar</a></li>
                            <li class="nav-item"><a class="nav-link" href="student/events.php">Events</a></li>
                            <li class="nav-item"><a class="nav-link" href="index.php#categories">Categories</a></li>
                            <li class="nav-item"><a class="nav-link" href="index.php#announcements">Announcements</a></li>
                            <li class="nav-item ms-lg-3"><a class="btn btn-outline-light" href="auth/login.php">Login</a></li>
                            <li class="nav-item ms-2"><a class="btn btn-green" href="auth/register.php">Register</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <p class="ptc-kicker">Campus Schedule</p>
                <h2 class="ptc-section-title mb-1">Campus Event Calendar</h2>
                <p class="ptc-section-sub mb-0">Stay updated with campus activities, academic programs, and student events.</p>
            </div>
            <?php if (!isset($_SESSION["user_id"])): ?>
                <div class="d-flex gap-2">
                    <a href="auth/login.php" class="btn btn-outline-green">Login</a>
                    <a href="auth/register.php" class="btn btn-green">Register</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="ptc-card p-4 mb-4">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Search event</label>
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Event name...">
                </div>
                <div class="col-lg-3">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category["id"]; ?>" <?php echo $categoryFilter === (int) $category["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($category["category_name"]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-green w-100">Apply</button>
                    <a href="calendar.php" class="btn btn-outline-green">Reset</a>
                </div>
                <div class="col-lg-2 d-flex align-items-end">
                    <a href="calendar.php?month=<?php echo (int) date("n"); ?>&year=<?php echo (int) date("Y"); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo (int) $categoryFilter; ?>" class="btn btn-outline-green w-100">Today</a>
                </div>
            </form>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="ptc-card p-3 p-md-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <a href="calendar.php?month=<?php echo (int) $prevMonth->format('n'); ?>&year=<?php echo (int) $prevMonth->format('Y'); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo (int) $categoryFilter; ?>" class="btn btn-outline-green btn-sm" aria-label="Previous month">Previous</a>
                            <a href="calendar.php?month=<?php echo (int) $nextMonth->format('n'); ?>&year=<?php echo (int) $nextMonth->format('Y'); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo (int) $categoryFilter; ?>" class="btn btn-outline-green btn-sm" aria-label="Next month">Next</a>
                        </div>
                        <h3 class="fw-bold text-success mb-0"><?php echo htmlspecialchars(date("F Y", strtotime($selectedMonthStart->format("Y-m-01")))); ?></h3>
                    </div>

                    <div class="calendar-legend mb-3">
                        <span class="legend-item"><span class="legend-dot dot-one"></span>Academic</span>
                        <span class="legend-item"><span class="legend-dot dot-two"></span>Sports</span>
                        <span class="legend-item"><span class="legend-dot dot-three"></span>Cultural</span>
                        <span class="legend-item"><span class="legend-dot dot-four"></span>Seminars</span>
                        <span class="legend-item"><span class="legend-dot dot-five"></span>Social</span>
                        <span class="legend-item"><span class="legend-dot dot-six"></span>Celebration</span>
                    </div>

                    <div class="calendar-grid">
                        <?php foreach (["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as $dayLabel): ?>
                            <div class="calendar-weekday"><?php echo htmlspecialchars($dayLabel); ?></div>
                        <?php endforeach; ?>

                        <?php foreach ($calendarCells as $cell): ?>
                            <?php if ($cell === null): ?>
                                <div class="calendar-day empty-day"></div>
                            <?php else: ?>
                                <?php
                                $dayDate = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $cell);
                                $isToday = $dayDate === $today->format("Y-m-d");
                                $dayEvents = $eventsByDay[$dayDate] ?? [];
                                ?>
                                <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?>">
                                    <div class="calendar-day-header"><?php echo (int) $cell; ?></div>
                                    <?php if ($dayEvents): ?>
                                        <?php foreach (array_slice($dayEvents, 0, 2) as $event): ?>
                                            <?php
                                            $eventCategoryKey = (int) ($event["category_id"] ?? 0);
                                            $categoryClass = $categoryColorMap[$eventCategoryKey] ?? "cat-one";
                                            ?>
                                            <button
                                                type="button"
                                                class="calendar-event-pill <?php echo htmlspecialchars($categoryClass); ?>"
                                                data-event-id="<?php echo (int) $event["id"]; ?>"
                                                onclick="openEventModal(<?php echo (int) $event["id"]; ?>)"
                                            >
                                                <?php echo htmlspecialchars($event["event_name"]); ?>
                                            </button>
                                        <?php endforeach; ?>
                                        <?php if (count($dayEvents) > 2): ?>
                                            <div class="calendar-more">+<?php echo count($dayEvents) - 2; ?> more</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($monthEvents)): ?>
                        <div class="ptc-card p-4 mt-4 text-center text-muted">
                            No events scheduled for this month. Please check another month or browse upcoming events below.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="ptc-card p-4">
                    <h4 class="fw-bold text-success mb-3">Upcoming Events</h4>
                    <?php if ($upcomingEvents): ?>
                        <?php foreach ($upcomingEvents as $event): ?>
                            <div class="calendar-upcoming-item">
                                <div class="calendar-upcoming-date">
                                    <span><?php echo htmlspecialchars(date("M", strtotime($event["event_date"]))); ?></span>
                                    <strong><?php echo htmlspecialchars(date("d", strtotime($event["event_date"]))); ?></strong>
                                </div>
                                <div class="calendar-upcoming-content">
                                    <div class="fw-bold"><?php echo htmlspecialchars($event["event_name"]); ?></div>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($event["category_name"] ?? "General Event"); ?></small>
                                    <small class="text-muted"><?php echo htmlspecialchars($event["venue_name"] ?? "To be announced"); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No upcoming events found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="ptc-badge" id="modalCategoryBadge">Event</span>
                        <h5 class="modal-title fw-bold text-success mt-3" id="eventModalLabel">Event Details</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="ptc-card p-3"><strong>Date:</strong><div id="modalDate"></div></div></div>
                        <div class="col-md-6"><div class="ptc-card p-3"><strong>Category:</strong><div id="modalCategory"></div></div></div>
                        <div class="col-md-6"><div class="ptc-card p-3"><strong>Time:</strong><div id="modalTime"></div></div></div>
                        <div class="col-md-6"><div class="ptc-card p-3"><strong>Venue:</strong><div id="modalVenue"></div></div></div>
                        <div class="col-md-6"><div class="ptc-card p-3"><strong>Registration Deadline:</strong><div id="modalDeadline"></div></div></div>
                        <div class="col-md-6"><div class="ptc-card p-3"><strong>Available Slots:</strong><div id="modalSlots"></div></div></div>
                        <div class="col-12"><div class="ptc-card p-3"><strong>Status:</strong><div id="modalStatus"></div></div></div>
                        <div class="col-12"><div class="ptc-card p-3"><strong>Description:</strong><div id="modalDescription"></div></div></div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-green" data-bs-dismiss="modal">Close</button>
                    <a href="auth/login.php" class="btn btn-green" id="modalActionLink">View Event Details</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const calendarEvents = <?php echo json_encode($calendarEventsForJs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

        function openEventModal(eventId) {
            const event = calendarEvents.find(item => Number(item.id) === Number(eventId));
            if (!event) return;

            document.getElementById('eventModalLabel').textContent = event.name;
            document.getElementById('modalCategoryBadge').textContent = event.category;
            document.getElementById('modalDate').textContent = event.date;
            document.getElementById('modalCategory').textContent = event.category;
            document.getElementById('modalTime').textContent = event.time;
            document.getElementById('modalVenue').textContent = event.venue;
            document.getElementById('modalDeadline').textContent = event.deadline;
            document.getElementById('modalSlots').textContent = event.slots;
            document.getElementById('modalStatus').textContent = event.status;
            document.getElementById('modalDescription').textContent = event.description;

            const actionLink = document.getElementById('modalActionLink');
            actionLink.textContent = event.registerText;
            actionLink.href = event.registerUrl;

            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
        }
    </script>
</body>
</html>
