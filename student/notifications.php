<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/notifications.php";

$userId = (int) $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["mark_read"]) && !empty($_POST["notification_id"])) {
        $notificationId = filter_input(INPUT_POST, "notification_id", FILTER_VALIDATE_INT);
        if ($notificationId) {
            markNotificationAsRead($pdo, $notificationId, $userId);
        }
    }

    if (isset($_POST["mark_all_read"])) {
        markAllNotificationsAsRead($pdo, $userId);
    }

    header("Location: notifications.php");
    exit;
}

$unreadNotificationCount = getUnreadNotificationCount($pdo, $userId);
$notifications = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC"
);
$notifications->execute([$userId]);
$notifications = $notifications->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | PTC Campus Event Hub</title>
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
                        <li class="nav-item"><a href="announcements.php" class="student-nav-item nav-link" title="Announcements"><i class="bi bi-megaphone"></i><span>Announcements</span></a></li>
                        <li class="nav-item"><a href="profile.php" class="student-nav-item nav-link" title="Profile"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>
                        <li class="nav-item"><a href="notifications.php" class="student-nav-item nav-link active" title="Notifications"><i class="bi bi-bell-fill"></i><span>Notifications</span><?php if ($unreadNotificationCount > 0): ?><span class="notification-count"><?php echo $unreadNotificationCount; ?></span><?php endif; ?></a></li>
                        <li class="nav-item"><a href="../auth/logout.php" class="btn btn-light btn-sm ms-lg-2" title="Logout">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="ptc-section-title mb-1">Notifications</h2>
                <p class="ptc-section-sub mb-0">Stay informed about event updates and registration decisions.</p>
            </div>
            <?php if (!empty($notifications)): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="mark_all_read" value="1" class="btn btn-outline-green">Mark All as Read</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-card <?php echo (int) $notification["is_read"] === 0 ? "unread" : "read"; ?>">
                    <div class="notification-body">
                        <a href="my-registrations.php" class="notification-main-link" aria-label="View registration details">
                            <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
                            <div class="notification-content">
                                <div class="notification-top">
                                    <h5 class="notification-title"><?php echo htmlspecialchars($notification["title"]); ?></h5>
                                    <?php if ((int) $notification["is_read"] === 0): ?>
                                        <span class="notification-status">Unread</span>
                                    <?php endif; ?>
                                </div>
                                <p class="notification-message"><?php echo nl2br(htmlspecialchars($notification["message"])); ?></p>
                                <div class="notification-meta">
                                    <span><?php echo date("M d, Y • h:i A", strtotime($notification["created_at"])); ?></span>
                                    <?php if ((int) $notification["is_read"] === 0): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="notification_id" value="<?php echo (int) $notification["id"]; ?>">
                                            <button type="submit" name="mark_read" value="1" class="btn btn-sm btn-green">Mark as Read</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notification-empty">
                <div class="empty-icon"><i class="bi bi-bell-slash"></i></div>
                <h3 class="fw-bold text-success">No notifications yet</h3>
                <p class="text-muted mb-4">You will receive updates here when your event registrations are reviewed or approved.</p>
                <a href="events.php" class="btn btn-green">Browse Events</a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="ptc-footer mt-3">
        <div class="container text-center">
            <small>© <?php echo date("Y"); ?> Pateros Technological College | PTC Campus Event Hub</small>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
