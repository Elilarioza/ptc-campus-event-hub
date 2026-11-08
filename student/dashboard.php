
<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/notifications.php";

$currentUserId = (int) $_SESSION["user_id"];
$unreadNotificationCount = getUnreadNotificationCount($pdo, $currentUserId);

$studentProfileImage = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
$studentProfileImage->execute([$currentUserId]);
$studentProfileImagePath = $studentProfileImage->fetchColumn();

$studentInitials = strtoupper(substr($_SESSION["first_name"] ?? "S", 0, 1) . substr($_SESSION["last_name"] ?? "T", 0, 1));
if ($studentInitials === "") {
    $studentInitials = "PT";
}

$studentDefaultAvatar = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" rx="100" fill="#eef8ef"/><circle cx="100" cy="72" r="32" fill="#1d6e3a"/><path d="M52 158c12-26 34-39 48-39s36 13 48 39" fill="#1d6e3a"/><text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="52" font-weight="700" fill="#0c4d2c">' . htmlspecialchars($studentInitials, ENT_QUOTES, 'UTF-8') . '</text></svg>');

if (!empty($studentProfileImagePath)) {
    $studentProfileImagePath = "../" . ltrim($studentProfileImagePath, "/");
} else {
    $studentProfileImagePath = $studentDefaultAvatar;
}

$totalRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE user_id = " . $currentUserId)->fetchColumn();
$pendingRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE user_id = " . (int) $_SESSION["user_id"] . " AND status = 'pending'")->fetchColumn();
$approvedRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE user_id = " . (int) $_SESSION["user_id"] . " AND status = 'approved'")->fetchColumn();
$rejectedRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE user_id = " . (int) $_SESSION["user_id"] . " AND status = 'rejected'")->fetchColumn();
$cancelledRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE user_id = " . (int) $_SESSION["user_id"] . " AND status = 'cancelled'")->fetchColumn();

$myUpcomingEvents = $pdo->prepare(
    "SELECT r.status AS registration_status, e.id AS event_id, e.event_name, e.event_date, e.start_time, e.end_time, v.venue_name, c.category_name
     FROM registrations r
     INNER JOIN events e ON e.id = r.event_id
     LEFT JOIN venues v ON v.id = e.venue_id
     LEFT JOIN categories c ON c.id = e.category_id
     WHERE r.user_id = ? AND r.status = 'approved' AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC, e.start_time ASC
     LIMIT 4"
);
$myUpcomingEvents->execute([$currentUserId]);
$myUpcomingEvents = $myUpcomingEvents->fetchAll();

$upcomingEvents = $pdo->query(
    "SELECT e.*, c.category_name, v.venue_name
     FROM events e
     LEFT JOIN categories c ON c.id = e.category_id
     LEFT JOIN venues v ON v.id = e.venue_id
     WHERE e.status IN ('upcoming', 'ongoing')
     AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC
     LIMIT 4"
)->fetchAll();

$popularEvents = $pdo->query(
    "SELECT e.*, c.category_name, v.venue_name
     FROM events e
     LEFT JOIN categories c ON c.id = e.category_id
     LEFT JOIN venues v ON v.id = e.venue_id
     WHERE e.status IN ('upcoming', 'ongoing')
     AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC
     LIMIT 4"
)->fetchAll();

$announcements = $pdo->query(
    "SELECT a.*, u.first_name, u.last_name
     FROM announcements a
     LEFT JOIN users u ON u.id = a.posted_by
     WHERE a.status = 'published'
     ORDER BY a.created_at DESC
     LIMIT 3"
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="../assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/ptc-style.css">
    <style>
        .dashboard-profile-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            flex-shrink: 0;
        }

        .dashboard-profile-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.7);
            box-shadow: 0 12px 24px rgba(16,75,43,0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.12);
            color: var(--white);
            font-weight: 800;
            font-size: 1rem;
        }

        .dashboard-profile-placeholder {
            background: linear-gradient(135deg, #f2faf3, #dcefe0);
            color: var(--dark-green);
        }

    </style>
</head>
<body>
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
                        <li class="nav-item"><a href="dashboard.php" class="student-nav-item nav-link active" title="Dashboard"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a href="../calendar.php" class="student-nav-item nav-link" title="Calendar"><i class="bi bi-calendar3"></i><span>Calendar</span></a></li>
                        <li class="nav-item"><a href="events.php" class="student-nav-item nav-link" title="Events"><i class="bi bi-calendar-event"></i><span>Events</span></a></li>
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

    <div class="container py-4">
        <div class="ptc-card p-4 mb-4" style="background: linear-gradient(135deg, var(--dark-green), var(--primary-green)); color: white; border:none;">
            <div class="row align-items-center gy-3">
                <div class="col-lg-8 d-flex align-items-center gap-3">
                    <a href="profile.php" class="dashboard-profile-badge" aria-label="View profile">
                        <?php if (!empty($studentProfileImage)) : ?>
                            <img src="<?php echo htmlspecialchars($studentProfileImagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="Student profile picture" class="dashboard-profile-avatar">
                        <?php else : ?>
                            <span class="dashboard-profile-avatar dashboard-profile-placeholder"><?php echo htmlspecialchars($studentInitials); ?></span>
                        <?php endif; ?>
                    </a>
                    <div>
                        <h2 class="mb-2 fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</h2>
                        <p class="mb-0 opacity-75">Stay updated with the latest campus activities, event opportunities, and announcements.</p>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="events.php" class="btn btn-light">Browse Events</a>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="ptc-stat">
                    <div class="label">Total Registrations</div>
                    <div class="value"><?php echo $totalRegistrations; ?></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="ptc-stat">
                    <div class="label">Pending</div>
                    <div class="value"><?php echo $pendingRegistrations; ?></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="ptc-stat">
                    <div class="label">Approved</div>
                    <div class="value"><?php echo $approvedRegistrations; ?></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="ptc-stat">
                    <div class="label">Rejected</div>
                    <div class="value"><?php echo $rejectedRegistrations; ?></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="ptc-stat">
                    <div class="label">Cancelled</div>
                    <div class="value"><?php echo $cancelledRegistrations; ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="ptc-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0 text-success">My Upcoming Events</h4>
                        <a href="my-registrations.php" class="btn btn-outline-green btn-sm">View Registrations</a>
                    </div>

                    <?php if ($myUpcomingEvents): ?>
                        <div class="row g-3">
                            <?php foreach ($myUpcomingEvents as $event): ?>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div>
                                                <div class="small text-success fw-semibold"><?php echo htmlspecialchars($event["category_name"] ?? "Campus Event"); ?></div>
                                                <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($event["event_name"]); ?></h6>
                                            </div>
                                            <span class="badge bg-success-subtle text-success"><?php echo htmlspecialchars(ucfirst($event["registration_status"])); ?></span>
                                        </div>
                                        <div class="small text-muted mb-1"><?php echo htmlspecialchars(date("F d, Y", strtotime($event["event_date"]))); ?></div>
                                        <div class="small text-muted mb-1"><?php echo htmlspecialchars(date("h:i A", strtotime($event["start_time"])) . " - " . date("h:i A", strtotime($event["end_time"]))); ?></div>
                                        <div class="small text-muted mb-3"><?php echo htmlspecialchars($event["venue_name"] ?? "To be announced"); ?></div>
                                        <a href="register-event.php?id=<?php echo (int) $event["event_id"]; ?>" class="btn btn-green btn-sm">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="ptc-card p-4 text-center text-muted mb-0">No approved upcoming event registrations found.</div>
                    <?php endif; ?>
                </div>

                <div class="ptc-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0 text-success">Featured Upcoming Events</h4>
                        <a href="events.php" class="btn btn-outline-green btn-sm">View All</a>
                    </div>
                    <div class="row g-3">
                        <?php if ($upcomingEvents): ?>
                            <?php foreach ($upcomingEvents as $event): ?>
                                <div class="col-md-6">
                                    <div class="ptc-event-card">
                                        <div class="image" style="background-image:url('<?php echo !empty($event["event_image"]) ? "../" . htmlspecialchars($event["event_image"]) : "https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=900&q=80"; ?>');"></div>
                                        <div class="body">
                                            <span class="ptc-badge mb-2"><?php echo htmlspecialchars($event["category_name"] ?? "Campus Event"); ?></span>
                                            <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($event["event_name"]); ?></h5>
                                            <p class="text-muted small mb-2"><?php echo htmlspecialchars(date("F d, Y", strtotime($event["event_date"]))); ?> • <?php echo htmlspecialchars($event["venue_name"] ?? "To be announced"); ?></p>
                                            <a href="register-event.php?id=<?php echo (int) $event["id"]; ?>" class="btn btn-green w-100">Register</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12"><div class="ptc-card p-4 text-center text-muted">No upcoming events available right now.</div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ptc-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0 text-success">Popular Activities</h4>
                        <a href="events.php" class="btn btn-outline-green btn-sm">Browse More</a>
                    </div>
                    <div class="row g-3">
                        <?php if ($popularEvents): ?>
                            <?php foreach ($popularEvents as $event): ?>
                                <div class="col-md-6">
                                    <div class="ptc-event-card">
                                        <div class="image" style="background-image:url('<?php echo !empty($event["event_image"]) ? "../" . htmlspecialchars($event["event_image"]) : "https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=900&q=80"; ?>');"></div>
                                        <div class="body">
                                            <span class="ptc-badge mb-2"><?php echo htmlspecialchars($event["category_name"] ?? "Campus Event"); ?></span>
                                            <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($event["event_name"]); ?></h5>
                                            <p class="text-muted small mb-2"><?php echo htmlspecialchars(date("F d, Y", strtotime($event["event_date"]))); ?> • <?php echo htmlspecialchars($event["venue_name"] ?? "To be announced"); ?></p>
                                            <a href="register-event.php?id=<?php echo (int) $event["id"]; ?>" class="btn btn-green w-100">Join Event</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="ptc-card p-4 mb-4">
                    <h4 class="fw-bold mb-3 text-success">Latest Updates</h4>
                    <?php if ($announcements): ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="border rounded-4 p-3 mb-3 bg-light">
                                <div class="fw-bold mb-1"><?php echo htmlspecialchars($announcement["title"]); ?></div>
                                <div class="small text-muted mb-2"><?php echo htmlspecialchars(date("M d, Y", strtotime($announcement["created_at"]))); ?></div>
                                <p class="small text-muted mb-0"><?php echo htmlspecialchars(substr($announcement["content"], 0, 120)); ?><?php echo strlen($announcement["content"]) > 120 ? '...' : ''; ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No announcements posted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="ptc-footer mt-3">
        <div class="container text-center">
            <small>© <?php echo date("Y"); ?> Pateros Technological College | PTC Campus Event Hub</small>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
