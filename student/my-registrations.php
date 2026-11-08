
<?php
session_start();
require_once "../config/database.php";
require_once "../config/notifications.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$unreadNotificationCount = getUnreadNotificationCount($pdo, $user_id);
$searchTerm = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_registration'])) {
    $registrationId = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT);
    if ($registrationId) {
        $registrationCheck = $pdo->prepare(
            "SELECT r.id, r.status, e.event_name
             FROM registrations r
             INNER JOIN events e ON e.id = r.event_id
             WHERE r.id = ? AND r.user_id = ?"
        );
        $registrationCheck->execute([$registrationId, $user_id]);
        $registration = $registrationCheck->fetch();

        if ($registration && $registration['status'] === 'pending') {
            $cancelStmt = $pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            $cancelStmt->execute([$registrationId, $user_id]);

            if ($cancelStmt->rowCount() > 0) {
                $successMessage = "Your registration has been cancelled successfully.";
                createNotification(
                    $pdo,
                    $user_id,
                    "Registration Cancelled!",
                    "Your pending registration for " . $registration['event_name'] . " has been cancelled."
                );
            }
        } else {
            $errorMessage = "Only pending registrations can be cancelled.";
        }
    }
}

$summaryStmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM registrations WHERE user_id = ? GROUP BY status");
$summaryStmt->execute([$user_id]);
$summaryCounts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'cancelled' => 0,
];

foreach ($summaryStmt->fetchAll() as $row) {
    if (isset($summaryCounts[$row['status']])) {
        $summaryCounts[$row['status']] = (int) $row['total'];
    }
}

$totalRegistrations = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE user_id = " . $user_id)->fetchColumn();

$sql = "
    SELECT
        r.id AS registration_id,
        r.registration_date,
        r.status AS registration_status,
        e.id AS event_id,
        e.event_name,
        e.description,
        e.event_date,
        e.start_time,
        e.end_time,
        e.event_image,
        v.venue_name,
        c.category_name
    FROM registrations r
    INNER JOIN events e ON r.event_id = e.id
    LEFT JOIN venues v ON e.venue_id = v.id
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE r.user_id = ?
";

$params = [$user_id];

if ($searchTerm !== '') {
    $sql .= " AND (e.event_name LIKE ? OR c.category_name LIKE ? OR v.venue_name LIKE ?)";
    $like = "%" . $searchTerm . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY r.registration_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registrations = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Registrations | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="../assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/ptc-style.css">
    <style>
        .ptc-registration-header {
            background: linear-gradient(135deg, var(--dark-green), var(--primary-green));
            color: white;
            border: none;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .ptc-registration-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .status-badge.pending {
            background: #fff5d6;
            color: #8a6416;
        }

        .status-badge.approved {
            background: #eaf8ee;
            color: var(--primary-green);
        }

        .status-badge.rejected {
            background: #fdeaea;
            color: #9c3232;
        }

        .status-badge.cancelled {
            background: #eef2f7;
            color: #4b5d78;
        }

        .registration-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 1.5rem;
        }

        .registration-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(44, 92, 58, 0.12);
        }

        .registration-card .image-wrap {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: linear-gradient(135deg, #dfeee1, #f5faf6);
        }

        .registration-card .image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .registration-card .content {
            padding: 1.5rem;
        }

        .registration-card h3 {
            color: var(--dark-green);
            font-weight: 800;
            margin-bottom: 0.8rem;
        }

        .meta-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem 1rem;
            margin: 1.1rem 0;
        }

        .meta-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            color: var(--text);
        }

        .meta-item .meta-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: var(--light-green);
            color: var(--primary-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .meta-item small {
            display: block;
            color: var(--muted);
            margin-bottom: 0.15rem;
        }

        .meta-item strong {
            font-size: 0.94rem;
        }

        .empty-state {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
            text-align: center;
            padding: 3rem 1.5rem;
        }

        .empty-state .empty-icon {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            background: var(--light-green);
            color: var(--primary-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 767px) {
            .ptc-registration-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .meta-list {
                grid-template-columns: 1fr;
            }
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
                        <li class="nav-item"><a href="dashboard.php" class="student-nav-item nav-link" title="Dashboard"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a href="../calendar.php" class="student-nav-item nav-link" title="Calendar"><i class="bi bi-calendar3"></i><span>Calendar</span></a></li>
                        <li class="nav-item"><a href="events.php" class="student-nav-item nav-link" title="Events"><i class="bi bi-calendar-event"></i><span>Events</span></a></li>
                        <li class="nav-item"><a href="my-registrations.php" class="student-nav-item nav-link active" title="My Registrations"><i class="bi bi-clipboard-check"></i><span>My Registrations</span></a></li>
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
        <div class="ptc-registration-header mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <p class="mb-2 text-uppercase small fw-semibold opacity-75">Student Portal</p>
                    <h2 class="mb-1 fw-bold">My Registrations</h2>
                    <p class="mb-0 opacity-75">Track your campus event registration status and upcoming participation.</p>
                </div>
                <a href="events.php" class="btn btn-light fw-semibold">Browse Events</a>
            </div>
        </div>

        <?php if (isset($successMessage)): ?>
            <div class="notice success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="notice error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="ptc-registration-summary">
            <div class="ptc-stat">
                <div class="label">Total Registrations</div>
                <div class="value"><?php echo $totalRegistrations; ?></div>
            </div>
            <div class="ptc-stat">
                <div class="label">Pending</div>
                <div class="value"><?php echo $summaryCounts['pending']; ?></div>
            </div>
            <div class="ptc-stat">
                <div class="label">Approved</div>
                <div class="value"><?php echo $summaryCounts['approved']; ?></div>
            </div>
            <div class="ptc-stat">
                <div class="label">Rejected</div>
                <div class="value"><?php echo $summaryCounts['rejected']; ?></div>
            </div>
        </div>

        <div class="ptc-card p-4 mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Search registrations</label>
                    <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by event, category, or venue...">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-green">Filter</button>
                </div>
            </form>
        </div>

        <?php if (!empty($registrations)): ?>
            <?php foreach ($registrations as $registration): ?>
                <div class="registration-card">
                    <div class="row g-0">
                        <div class="col-lg-4">
                            <div class="image-wrap">
                                <img src="<?php echo !empty($registration['event_image']) ? "../" . htmlspecialchars($registration['event_image']) : "https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=900&q=80"; ?>" alt="<?php echo htmlspecialchars($registration['event_name']); ?>">
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="content">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-3">
                                    <div>
                                        <span class="ptc-badge mb-2"><?php echo htmlspecialchars($registration['category_name'] ?? 'Campus Event'); ?></span>
                                        <h3 class="mb-0"><?php echo htmlspecialchars($registration['event_name']); ?></h3>
                                    </div>
                                    <span class="status-badge <?php echo htmlspecialchars(strtolower($registration['registration_status'])); ?>"><?php echo htmlspecialchars(ucfirst($registration['registration_status'])); ?></span>
                                </div>

                                <div class="meta-list">
                                    <div class="meta-item">
                                        <span class="meta-icon"><i class="bi bi-tag"></i></span>
                                        <div>
                                            <small>Category</small>
                                            <strong><?php echo htmlspecialchars($registration['category_name'] ?? 'N/A'); ?></strong>
                                        </div>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-icon"><i class="bi bi-calendar3"></i></span>
                                        <div>
                                            <small>Event Date</small>
                                            <strong><?php echo htmlspecialchars(date('F d, Y', strtotime($registration['event_date']))); ?></strong>
                                        </div>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-icon"><i class="bi bi-clock"></i></span>
                                        <div>
                                            <small>Event Time</small>
                                            <strong><?php echo htmlspecialchars(date('h:i A', strtotime($registration['start_time'])) . ' - ' . date('h:i A', strtotime($registration['end_time']))); ?></strong>
                                        </div>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-icon"><i class="bi bi-geo-alt"></i></span>
                                        <div>
                                            <small>Venue</small>
                                            <strong><?php echo htmlspecialchars($registration['venue_name'] ?? 'To be announced'); ?></strong>
                                        </div>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-icon"><i class="bi bi-calendar-check"></i></span>
                                        <div>
                                            <small>Registration Date</small>
                                            <strong><?php echo htmlspecialchars(date('F d, Y', strtotime($registration['registration_date']))); ?></strong>
                                        </div>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-icon"><i class="bi bi-info-circle"></i></span>
                                        <div>
                                            <small>Status</small>
                                            <strong><?php echo htmlspecialchars(ucfirst($registration['registration_status'])); ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-sm-row gap-2 mt-3">
                                    <a href="register-event.php?id=<?php echo (int) $registration['event_id']; ?>" class="btn btn-green">View Event Details</a>
                                    <?php if ($registration['registration_status'] === 'pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this registration?');" class="d-inline">
                                            <input type="hidden" name="registration_id" value="<?php echo (int) $registration['registration_id']; ?>">
                                            <button type="submit" name="cancel_registration" value="1" class="btn btn-outline-danger">Cancel Registration</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar2-check"></i></div>
                <h3 class="fw-bold text-success">No registrations found</h3>
                <p class="text-muted mb-4">You have not registered for any events yet. Explore campus activities and reserve your spot.</p>
                <a href="events.php" class="btn btn-green">Browse Events</a>
            </div>
        <?php endif; ?>

    </div>

    <footer class="ptc-footer mt-3">
        <div class="container text-center">
            <small>© <?php echo date('Y'); ?> Pateros Technological College | PTC Campus Event Hub</small>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
