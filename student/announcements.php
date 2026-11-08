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

$announcements = $pdo->query(
    "SELECT a.*, u.first_name, u.last_name FROM announcements a LEFT JOIN users u ON u.id = a.posted_by WHERE a.status = 'published' ORDER BY a.created_at DESC"
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="../assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/ptc-style.css">
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
                        <li class="nav-item"><a href="dashboard.php" class="student-nav-item nav-link" title="Dashboard"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a href="../calendar.php" class="student-nav-item nav-link" title="Calendar"><i class="bi bi-calendar3"></i><span>Calendar</span></a></li>
                        <li class="nav-item"><a href="events.php" class="student-nav-item nav-link" title="Events"><i class="bi bi-calendar-event"></i><span>Events</span></a></li>
                        <li class="nav-item"><a href="my-registrations.php" class="student-nav-item nav-link" title="My Registrations"><i class="bi bi-clipboard-check"></i><span>My Registrations</span></a></li>
                        <li class="nav-item"><a href="announcements.php" class="student-nav-item nav-link active" title="Announcements"><i class="bi bi-megaphone"></i><span>Announcements</span></a></li>
                        <li class="nav-item"><a href="profile.php" class="student-nav-item nav-link" title="Profile"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>
                        <li class="nav-item"><a href="notifications.php" class="student-nav-item nav-link" title="Notifications"><i class="bi bi-bell-fill"></i><span>Notifications</span><?php if ($unreadNotificationCount > 0): ?><span class="notification-count"><?php echo $unreadNotificationCount; ?></span><?php endif; ?></a></li>
                        <li class="nav-item"><a href="../auth/logout.php" class="btn btn-light btn-sm ms-lg-2" title="Logout">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="ptc-section-title mb-1">Campus Announcements</h2>
                <p class="ptc-section-sub mb-0">Stay informed about student news and important updates.</p>
            </div>
        </div>

        <div class="row g-4">
            <?php if ($announcements): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="col-lg-6">
                        <div class="ptc-card p-4 h-100">
                            <span class="ptc-badge secondary mb-3">Announcement</span>
                            <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($announcement["title"]); ?></h4>
                            <div class="small text-muted mb-3"><?php echo date("M d, Y", strtotime($announcement["created_at"])); ?> • Posted by <?php echo htmlspecialchars(trim(($announcement["first_name"] ?? "") . " " . ($announcement["last_name"] ?? ""))); ?></div>
                            <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($announcement["content"])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="ptc-card p-5 text-center text-muted">No published announcements yet.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
