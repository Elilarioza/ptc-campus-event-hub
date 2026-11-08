
<?php
session_start();
require_once "../config/database.php";
require_once "../config/notifications.php";

$isStudent = isset($_SESSION["user_id"]) && isset($_SESSION["role"]) && $_SESSION["role"] === "student";
$currentUserId = $isStudent ? (int) $_SESSION["user_id"] : 0;
$unreadNotificationCount = $isStudent ? getUnreadNotificationCount($pdo, $currentUserId) : 0;

$search = trim($_GET["search"] ?? "");
$categoryFilter = isset($_GET["category"]) ? (int) $_GET["category"] : 0;
$venueFilter = isset($_GET["venue"]) ? (int) $_GET["venue"] : 0;
$statusFilter = trim($_GET["status"] ?? "");
$dateFilter = trim($_GET["date_filter"] ?? "");
$customStart = trim($_GET["start_date"] ?? "");
$customEnd = trim($_GET["end_date"] ?? "");

$conditions = ["e.status != 'cancelled'", "e.status IN ('upcoming','ongoing','completed')"];
$params = [];

if ($search !== "") {
    $conditions[] = "(e.event_name LIKE ? OR e.description LIKE ? OR c.category_name LIKE ? OR v.venue_name LIKE ?)";
    $searchLike = "%" . $search . "%";
    $params[] = $searchLike; $params[] = $searchLike; $params[] = $searchLike; $params[] = $searchLike;
}

if ($categoryFilter > 0) {
    $conditions[] = "e.category_id = ?";
    $params[] = $categoryFilter;
}

if ($venueFilter > 0) {
    $conditions[] = "e.venue_id = ?";
    $params[] = $venueFilter;
}

if ($statusFilter !== "") {
    $allowedStatuses = ["upcoming", "ongoing", "completed"];
    if (in_array($statusFilter, $allowedStatuses, true)) {
        $conditions[] = "e.status = ?";
        $params[] = $statusFilter;
    }
}

$today = date("Y-m-d");
$startOfThisWeek = date("Y-m-d", strtotime("monday this week"));
$endOfThisWeek = date("Y-m-d", strtotime("sunday this week"));
$startOfThisMonth = date("Y-m-01");
$endOfThisMonth = date("Y-m-t");

if ($dateFilter === "today") {
    $conditions[] = "e.event_date = ?";
    $params[] = $today;
} elseif ($dateFilter === "week") {
    $conditions[] = "e.event_date BETWEEN ? AND ?";
    $params[] = $startOfThisWeek;
    $params[] = $endOfThisWeek;
} elseif ($dateFilter === "month") {
    $conditions[] = "e.event_date BETWEEN ? AND ?";
    $params[] = $startOfThisMonth;
    $params[] = $endOfThisMonth;
} elseif ($dateFilter === "custom" && $customStart !== "") {
    $conditions[] = "e.event_date >= ?";
    $params[] = $customStart;
    if ($customEnd !== "") {
        $conditions[] = "e.event_date <= ?";
        $params[] = $customEnd;
    }
}

$query = "
    SELECT e.*, c.category_name, v.venue_name,
    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('pending','approved')) AS registered_count
    FROM events e
    LEFT JOIN categories c ON c.id = e.category_id
    LEFT JOIN venues v ON v.id = e.venue_id
    WHERE " . implode(" AND ", $conditions) . "
    ORDER BY e.event_date ASC, e.start_time ASC
";

$eventsStmt = $pdo->prepare($query);
$eventsStmt->execute($params);
$events = $eventsStmt->fetchAll();

$registrationStmt = $pdo->prepare("SELECT event_id FROM registrations WHERE user_id = ?");
if ($isStudent) {
    $registrationStmt->execute([$currentUserId]);
    $registeredEvents = $registrationStmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $registeredEvents = [];
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$venues = $pdo->query("SELECT * FROM venues ORDER BY venue_name ASC")->fetchAll();

$selectedCategoryName = "";
if ($categoryFilter > 0) {
    $selectedCategoryStmt = $pdo->prepare("SELECT category_name FROM categories WHERE id = ?");
    $selectedCategoryStmt->execute([$categoryFilter]);
    $selectedCategoryName = (string) $selectedCategoryStmt->fetchColumn();
}

$selectedVenueName = "";
if ($venueFilter > 0) {
    $selectedVenueStmt = $pdo->prepare("SELECT venue_name FROM venues WHERE id = ?");
    $selectedVenueStmt->execute([$venueFilter]);
    $selectedVenueName = (string) $selectedVenueStmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Events | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="../assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/ptc-style.css">
</head>
<body>
    <?php if ($isStudent): ?>
    <nav class="navbar navbar-expand-lg ptc-navbar student-navbar">
        <div class="container">
            <div class="student-navbar-inner">
                <div class="student-navbar-brand">
                    <a class="navbar-brand ptc-brand" href="dashboard.php" aria-label="PTC Campus Event Hub home">
                        <img src="../assets/images/ptc-logo.png" alt="Pateros Technological College logo">
                        <span class="brand-text">PTC CAMPUS EVENT HUB</span>
                    </a>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNav" aria-controls="studentNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
                </button>

                <div class="collapse navbar-collapse student-navbar-menu" id="studentNav">
                    <ul class="navbar-nav align-items-lg-center gap-lg-1">
                        <li class="nav-item"><a href="dashboard.php" class="student-nav-item nav-link" title="Dashboard"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a href="../calendar.php" class="student-nav-item nav-link" title="Calendar"><i class="bi bi-calendar3"></i><span>Calendar</span></a></li>
                        <li class="nav-item"><a href="events.php" class="student-nav-item nav-link active" title="Events"><i class="bi bi-calendar-event"></i><span>Events</span></a></li>
                        <li class="nav-item"><a href="my-registrations.php" class="student-nav-item nav-link" title="My Registrations"><i class="bi bi-clipboard-check"></i><span>My Registrations</span></a></li>
                        <li class="nav-item"><a href="announcements.php" class="student-nav-item nav-link" title="Announcements"><i class="bi bi-megaphone"></i><span>Announcements</span></a></li>
                        <li class="nav-item"><a href="profile.php" class="student-nav-item nav-link" title="Profile"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>
                        <li class="nav-item"><a href="notifications.php" class="student-nav-item nav-link" title="Notifications"><i class="bi bi-bell-fill"></i><span>Notifications</span><?php if ($unreadNotificationCount > 0): ?><span class="notification-count"><?php echo $unreadNotificationCount; ?></span><?php endif; ?></a></li>
                        <li class="nav-item"><a href="../auth/logout.php" class="btn btn-light btn-sm ms-lg-2" title="Logout">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <?php else: ?>
    <nav class="navbar navbar-expand-lg ptc-navbar">
        <div class="container">
            <a class="navbar-brand ptc-brand" href="../index.php" aria-label="PTC Campus Event Hub home">
                <img src="../assets/images/ptc-logo.png" alt="Pateros Technological College logo">
                <span class="brand-text">PTC CAMPUS EVENT HUB</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>
            <div class="collapse navbar-collapse" id="siteNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="events.php">Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="../calendar.php">Calendar</a></li>
                    <li class="nav-item"><a class="nav-link" href="../index.php#categories">Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="../index.php#announcements">Announcements</a></li>
                    <li class="nav-item ms-lg-3"><a class="btn btn-outline-light" href="../auth/login.php">Login</a></li>
                    <li class="nav-item ms-2"><a class="btn btn-green" href="../auth/register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="ptc-section-title mb-1">Campus Events</h2>
                <p class="ptc-section-sub mb-0">Explore upcoming campus opportunities and student programs.</p>
            </div>
            <div class="text-muted fw-semibold">Showing <span class="text-success"><?php echo count($events); ?></span> matching event<?php echo count($events) === 1 ? '' : 's'; ?></div>
        </div>

        <div class="ptc-card p-4 mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search event name or keyword...">
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category["id"]; ?>" <?php echo $categoryFilter === (int) $category["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($category["category_name"]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Venue</label>
                    <select name="venue" class="form-select">
                        <option value="0">All Venues</option>
                        <?php foreach ($venues as $venue): ?>
                            <option value="<?php echo (int) $venue["id"]; ?>" <?php echo $venueFilter === (int) $venue["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($venue["venue_name"]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="upcoming" <?php echo $statusFilter === "upcoming" ? "selected" : ""; ?>>Upcoming</option>
                        <option value="ongoing" <?php echo $statusFilter === "ongoing" ? "selected" : ""; ?>>Ongoing</option>
                        <option value="completed" <?php echo $statusFilter === "completed" ? "selected" : ""; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Date</label>
                    <select name="date_filter" id="dateFilter" class="form-select">
                        <option value="">Any Date</option>
                        <option value="today" <?php echo $dateFilter === "today" ? "selected" : ""; ?>>Today</option>
                        <option value="week" <?php echo $dateFilter === "week" ? "selected" : ""; ?>>This Week</option>
                        <option value="month" <?php echo $dateFilter === "month" ? "selected" : ""; ?>>This Month</option>
                        <option value="custom" <?php echo $dateFilter === "custom" ? "selected" : ""; ?>>Custom Range</option>
                    </select>
                </div>
                <div id="customRange" class="col-12 row g-3 <?php echo $dateFilter === 'custom' ? '' : 'd-none'; ?>">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($customStart); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($customEnd); ?>">
                    </div>
                    <div class="col-md-6 d-flex gap-2 align-items-end justify-content-md-end">
                        <button type="submit" class="btn btn-green">Apply Filters</button>
                        <a href="events.php" class="btn btn-outline-green">Clear Filters</a>
                    </div>
                </div>
                <?php if ($dateFilter !== "custom"): ?>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-green">Apply Filters</button>
                        <a href="events.php" class="btn btn-outline-green">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($categoryFilter > 0 || $venueFilter > 0 || $statusFilter !== "" || $dateFilter !== ""): ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 ptc-filter-status">
                <?php if ($selectedCategoryName !== ""): ?>
                    <span class="ptc-badge success">Category: <?php echo htmlspecialchars($selectedCategoryName); ?></span>
                <?php endif; ?>
                <?php if ($selectedVenueName !== ""): ?>
                    <span class="ptc-badge secondary">Venue: <?php echo htmlspecialchars($selectedVenueName); ?></span>
                <?php endif; ?>
                <?php if ($statusFilter !== ""): ?>
                    <span class="ptc-badge">Status: <?php echo htmlspecialchars(ucfirst($statusFilter)); ?></span>
                <?php endif; ?>
                <a href="events.php" class="text-decoration-none fw-semibold" style="color: var(--primary-green);">Clear all filters</a>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if ($events): ?>
                <?php foreach ($events as $event): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="ptc-event-card h-100">
                            <div class="image" style="background-image:url('<?php echo !empty($event["event_image"]) ? "../" . htmlspecialchars($event["event_image"]) : "https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=900&q=80"; ?>');"></div>
                            <div class="body">
                                <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                    <span class="ptc-badge"><?php echo htmlspecialchars($event["category_name"] ?? "General Event"); ?></span>
                                    <span class="ptc-badge <?php echo $event["status"] === 'upcoming' ? 'success' : ($event["status"] === 'ongoing' ? 'secondary' : 'danger'); ?>"><?php echo htmlspecialchars(ucfirst($event["status"] ?? 'upcoming')); ?></span>
                                </div>
                                <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($event["event_name"]); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars(substr($event["description"] ?? "No description available.", 0, 120)); ?><?php echo strlen((string) ($event["description"] ?? "")) > 120 ? '...' : ''; ?></p>
                                <div class="small text-muted mb-1">Date: <?php echo htmlspecialchars(date("F d, Y", strtotime($event["event_date"]))); ?></div>
                                <div class="small text-muted mb-1">Venue: <?php echo htmlspecialchars($event["venue_name"] ?? "To be announced"); ?></div>
                                <div class="small text-muted mb-3">Available: <?php echo max(0, (int) $event["maximum_participants"] - (int) $event["registered_count"]) . '/' . (int) $event["maximum_participants"]; ?></div>

                                <div class="d-flex gap-2">
                                    <a href="<?php echo $isStudent ? 'register-event.php?id=' . (int) $event["id"] : '../auth/login.php'; ?>" class="btn btn-green flex-grow-1"><?php echo $isStudent ? 'View Details' : 'Login to Register'; ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="ptc-card p-5 text-center">
                        <h5 class="fw-bold text-success mb-2">No events match your search or filters.</h5>
                        <p class="text-muted mb-0">Try another keyword, date range, venue, or status to find campus activities.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const dateFilter = document.getElementById('dateFilter');
        const customRange = document.getElementById('customRange');
        if (dateFilter) {
            dateFilter.addEventListener('change', function () {
                customRange.classList.toggle('d-none', this.value !== 'custom');
            });
        }
    </script>
</body>
</html>
