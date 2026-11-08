<?php
session_start();

require_once __DIR__ . "/config/database.php";

$isLoggedIn = isset($_SESSION["user_id"]);
$currentUserRole = $_SESSION["role"] ?? null;

$featuredEvents = $pdo->query(
    "SELECT e.*, c.category_name, v.venue_name
     FROM events e
     LEFT JOIN categories c ON c.id = e.category_id
     LEFT JOIN venues v ON v.id = e.venue_id
     WHERE e.status IN ('upcoming', 'ongoing')
     AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC
     LIMIT 6"
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

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$announcements = $pdo->query("SELECT a.*, u.first_name, u.last_name FROM announcements a LEFT JOIN users u ON u.id = a.posted_by WHERE a.status = 'published' ORDER BY a.created_at DESC LIMIT 3")->fetchAll();
$eventCount = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$studentCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

$categoryImages = [
    'Academic Events' => 'assets/images/academic-programming.jpg',
    'Sports Events' => 'assets/images/sports-basketball.jpg',
    'Cultural Events' => 'assets/images/cultural-dance.jpg',
    'Social Events' => 'assets/images/social-workshop.jpg',
    'Seminars and Workshops' => 'assets/images/academic-seminar.jpg',
    'School Celebrations' => 'assets/images/school-celebration.jpg',
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PTC Campus Event Hub | One Campus. Every Event. One Community.</title>
    <link rel="icon" type="image/png" href="assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/ptc-style.css">
</head>
<body>
    <?php if ($isLoggedIn && $currentUserRole === "student"): ?>
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
                            <li class="nav-item"><a href="student/dashboard.php" class="student-nav-item nav-link active" title="Dashboard"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
                            <li class="nav-item"><a href="calendar.php" class="student-nav-item nav-link" title="Calendar"><i class="bi bi-calendar3"></i><span>Calendar</span></a></li>
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
    <?php elseif ($isLoggedIn && $currentUserRole === "admin"): ?>
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
                            <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                            <li class="nav-item"><a class="nav-link" href="calendar.php">Calendar</a></li>
                            <li class="nav-item"><a class="nav-link" href="#events">Events</a></li>
                            <li class="nav-item"><a class="nav-link" href="#categories">Categories</a></li>
                            <li class="nav-item"><a class="nav-link" href="#announcements">Announcements</a></li>
                            <li class="nav-item ms-lg-3"><a class="btn btn-outline-light" href="auth/login.php">Login</a></li>
                            <li class="nav-item ms-2"><a class="btn btn-green" href="auth/register.php">Register</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <section class="ptc-hero">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="ptc-badge">Campus Life • Events • Community</span>
                    <h1>Discover What's Happening at PTC!</h1>
                    <p class="lead">One Campus. Every Event. One Community.</p>
                    <p>From student competitions and academic programs to cultural showcases and community celebrations, PTC Campus Event Hub brings every campus opportunity together in one place.</p>
                    <div class="d-flex flex-wrap gap-3 mt-4"></div>
                    <div class="ptc-home-metrics">
                        <div class="ptc-home-metric">
                            <span class="label">Events</span>
                            <span class="value"><?php echo $eventCount; ?></span>
                        </div>
                        <div class="ptc-home-metric">
                            <span class="label">Students</span>
                            <span class="value"><?php echo $studentCount; ?></span>
                        </div>
                        <div class="ptc-home-metric">
                            <span class="label">Categories</span>
                            <span class="value"><?php echo count($categories); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="ptc-hero-visual">
                        <img src="assets/images/ptc-building.jpg" alt="Pateros Technological College building facade">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="events" class="ptc-section">
        <div class="container">
            <div class="ptc-section-headline">
                <div>
                    <h2 class="ptc-section-title mb-1">Featured Events</h2>
                    <p class="ptc-section-sub mb-0">Make the most of your campus experience.</p>
                </div>
                <a href="auth/register.php" class="btn btn-outline-green">Register Now</a>
            </div>

            <div class="row g-4">
                <?php if ($featuredEvents): ?>
                    <?php foreach ($featuredEvents as $event): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="ptc-event-card h-100">
                                <div class="image" style="background-image:url('<?php echo !empty($event["event_image"]) ? htmlspecialchars($event["event_image"]) : "https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=900&q=80"; ?>');"></div>
                                <div class="body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="ptc-badge"><?php echo htmlspecialchars($event["category_name"] ?? "General Event"); ?></span>
                                        <span class="text-muted small"><?php echo htmlspecialchars(date("M d, Y", strtotime($event["event_date"]))); ?></span>
                                    </div>
                                    <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($event["event_name"]); ?></h5>
                                    <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($event["description"] ?? "No description available.", 0, 110)); ?><?php echo strlen((string) ($event["description"] ?? "")) > 110 ? '...' : ''; ?></p>
                                    <div class="small text-muted mb-2">Venue: <?php echo htmlspecialchars($event["venue_name"] ?? "To be announced"); ?></div>
                                    <div class="small text-muted mb-2">Time: <?php echo $event["start_time"] ? date("h:i A", strtotime($event["start_time"])) . " - " . date("h:i A", strtotime($event["end_time"])) : "TBA"; ?></div>
                                    <div class="small text-muted mb-3">Registration: <?php echo !empty($event["registration_deadline"]) ? date("M d, Y", strtotime($event["registration_deadline"])) : "Open"; ?></div>
                                    <div class="d-flex gap-2">
                                        <a href="auth/login.php" class="btn btn-outline-green flex-grow-1">View Details</a>
                                        <a href="auth/register.php" class="btn btn-green flex-grow-1">Register Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="ptc-card p-5 text-center">
                            <h5 class="text-success fw-bold">No featured events yet</h5>
                            <p class="text-muted mb-0">Check back soon for the next campus activity.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="ptc-section" style="background:#f0f8f2;">
        <div class="container">
            <div class="ptc-section-headline">
                <div>
                    <h2 class="ptc-section-title mb-1">Popular Activities</h2>
                    <p class="ptc-section-sub mb-0">Everyday experiences that bring students together.</p>
                </div>
            </div>
            <div class="row g-4">
                <?php foreach ($popularEvents as $event): ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="ptc-event-card h-100">
                            <div class="image" style="background-image:url('<?php echo !empty($event["event_image"]) ? htmlspecialchars($event["event_image"]) : "https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=900&q=80"; ?>');"></div>
                            <div class="body">
                                <span class="ptc-badge mb-2"><?php echo htmlspecialchars($event["category_name"] ?? "Campus Event"); ?></span>
                                <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($event["event_name"]); ?></h5>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars(date("M d, Y", strtotime($event["event_date"]))); ?> • <?php echo htmlspecialchars($event["venue_name"] ?? "TBA"); ?></p>
                                <a href="auth/register.php" class="btn btn-green w-100">Join Now</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="categories" class="ptc-section">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="ptc-section-title">Browse by Category</h2>
                <p class="ptc-section-sub">Find the program that matches your interests and goals.</p>
            </div>
            <div class="row g-4">
                <?php foreach ($categories as $category): ?>
                    <div class="col-md-4 col-lg-2">
                        <a class="ptc-category-card-link" href="student/events.php?category=<?php echo (int) $category["id"]; ?>" aria-label="Browse <?php echo htmlspecialchars($category["category_name"]); ?> events">
                            <div class="ptc-category-card" style="background-image:url('<?php echo $categoryImages[$category["category_name"]] ?? "assets/images/school-celebration.jpg"; ?>');">
                                <div class="content">
                                    <div class="category-name"><?php echo htmlspecialchars($category["category_name"]); ?></div>
                                    <p class="category-copy"><?php echo htmlspecialchars($category["description"] ?? "Campus event category."); ?></p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="announcements" class="ptc-section">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="ptc-section-title">Latest Announcements</h2>
                <p class="ptc-section-sub">Important updates and notices for the campus community.</p>
            </div>
            <div class="row g-4">
                <?php if ($announcements): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="ptc-card p-4 h-100">
                                <span class="ptc-badge secondary mb-3">Announcement</span>
                                <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($announcement["title"]); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars(substr($announcement["content"], 0, 170)); ?><?php echo strlen($announcement["content"]) > 170 ? '...' : ''; ?></p>
                                <div class="small text-muted">Posted by <?php echo htmlspecialchars(trim(($announcement["first_name"] ?? "") . " " . ($announcement["last_name"] ?? ""))); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="ptc-card p-5 text-center">
                            <h5 class="text-success fw-bold">No announcements yet</h5>
                            <p class="text-muted mb-0">New school updates will appear here.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="ptc-campus-links">
        <div class="container">
            <div class="ptc-campus-header">
                <div>
                    <p class="ptc-kicker">Campus Resources</p>
                    <h2 class="ptc-section-title mb-2">Explore Pateros Technological College</h2>
                </div>
            </div>

            <div class="row g-4 align-items-stretch">
                <div class="col-lg-5">
                    <div class="ptc-campus-map-card">
                        <div class="map-frame">
                            <iframe
                                title="Pateros Technological College location map"
                                src="https://www.google.com/maps?q=Pateros%20Technological%20College&output=embed"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen>
                            </iframe>
                        </div>
                        <div class="map-caption">
                            <h4>Campus Location</h4>
                            <p>Visit the official campus grounds and discover where student life, activities, and events take place.</p>
                            <a href="https://www.google.com/maps?sca_esv=83f312d482a6a4fe&output=search&q=pateros+technological+college&source=lnms&fbs=ACAhTvwdifjn2dKXmjtOfBxVFl_koHVe5RLSnJlTH2QYaq_YVb_kNJDbJh_Zr-NZfti9sLZ_5ktbIlyi1xomEoEsUAeSPbwXlC8xfc3KcmTV_8wPJRIwLhxoSq-HPZdAg4ucJj6cBWdp7Jt9hSls-NBdIeEIaFtM0J6tp4QYKb_uVTUqcpe6j6AtqCPPMfVNCB2nb3ndXLBVv-iGWZv5tijyAFXH9_-1Tw&entry=mc&ved=1t:200715&ictx=111" target="_blank" rel="noopener noreferrer" class="btn btn-green">Get Directions</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <a class="ptc-campus-resource" href="https://www.google.com/maps?sca_esv=83f312d482a6a4fe&output=search&q=pateros+technological+college&source=lnms&fbs=ACAhTvwdifjn2dKXmjtOfBxVFl_koHVe5RLSnJlTH2QYaq_YVb_kNJDbJh_Zr-NZfti9sLZ_5ktbIlyi1xomEoEsUAeSPbwXlC8xfc3KcmTV_8wPJRIwLhxoSq-HPZdAg4ucJj6cBWdp7Jt9hSls-NBdIeEIaFtM0J6tp4QYKb_uVTUqcpe6j6AtqCPPMfVNCB2nb3ndXLBVv-iGWZv5tijyAFXH9_-1Tw&entry=mc&ved=1t:200715&ictx=111" target="_blank" rel="noopener noreferrer">
                                <div class="resource-icon maps"><i class="bi bi-geo-alt-fill"></i></div>
                                <div class="resource-content">
                                    <h4>Campus Map</h4>
                                    <p>Find the Pateros Technological College campus and plan your visit or commute.</p>
                                </div>
                                <span class="resource-arrow"><i class="bi bi-arrow-up-right"></i></span>
                            </a>
                        </div>

                        <div class="col-md-6">
                            <a class="ptc-campus-resource" href="https://www.facebook.com/ptc1993" target="_blank" rel="noopener noreferrer">
                                <div class="resource-icon facebook"><i class="bi bi-facebook"></i></div>
                                <div class="resource-content">
                                    <h4>Official Facebook</h4>
                                    <p>Follow school announcements, student highlights, and official updates from PTC.</p>
                                </div>
                                <span class="resource-arrow"><i class="bi bi-arrow-up-right"></i></span>
                            </a>
                        </div>

                        <div class="col-md-12">
                            <a class="ptc-campus-resource website" href="https://www.paterostechnologicalcollege.edu.ph/" target="_blank" rel="noopener noreferrer">
                                <div class="resource-icon website"><i class="bi bi-globe2"></i></div>
                                <div class="resource-content">
                                    <h4>PTC Official Website</h4>
                                    <p>Explore programs, admissions, campus updates, and more information from the official school portal.</p>
                                </div>
                                <span class="resource-arrow"><i class="bi bi-arrow-up-right"></i></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="ptc-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="ptc-footer-brand">
                        <img src="assets/images/ptc-logo.png" alt="Pateros Technological College official logo">
                        <span>PTC Campus Event Hub</span>
                    </div>
                    <p class="mb-0">One Campus. Every Event. One Community.</p>
                </div>
                <div class="col-lg-4">
                    <h6 class="text-white fw-semibold mb-3">Quick Links</h6>
                    <ul class="list-unstyled mb-0">
                        <li><a href="index.php">Home</a></li>
                        <?php if ($isLoggedIn && $currentUserRole === "student"): ?>
                            <li><a href="student/dashboard.php">Student Dashboard</a></li>
                            <li><a href="auth/logout.php">Logout</a></li>
                        <?php elseif ($isLoggedIn && $currentUserRole === "admin"): ?>
                            <li><a href="admin/dashboard.php">Admin Dashboard</a></li>
                            <li><a href="auth/logout.php">Logout</a></li>
                        <?php else: ?>
                            <li><a href="auth/login.php">Login</a></li>
                            <li><a href="auth/register.php">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6 class="text-white fw-semibold mb-3">Official PTC</h6>
                    <ul class="list-unstyled mb-0">
                        <li><a href="https://www.google.com/maps?sca_esv=83f312d482a6a4fe&output=search&q=pateros+technological+college&source=lnms&fbs=ACAhTvwdifjn2dKXmjtOfBxVFl_koHVe5RLSnJlTH2QYaq_YVb_kNJDbJh_Zr-NZfti9sLZ_5ktbIlyi1xomEoEsUAeSPbwXlC8xfc3KcmTV_8wPJRIwLhxoSq-HPZdAg4ucJj6cBWdp7Jt9hSls-NBdIeEIaFtM0J6tp4QYKb_uVTUqcpe6j6AtqCPPMfVNCB2nb3ndXLBVv-iGWZv5tijyAFXH9_-1Tw&entry=mc&ved=1t:200715&ictx=111" target="_blank" rel="noopener noreferrer">Campus Location</a></li>
                        <li><a href="https://www.facebook.com/ptc1993" target="_blank" rel="noopener noreferrer">Official Facebook</a></li>
                        <li><a href="https://www.paterostechnologicalcollege.edu.ph/" target="_blank" rel="noopener noreferrer">Official Website</a></li>
                    </ul>
                </div>
            </div>
            <div class="text-center mt-4 pt-3 border-top border-secondary">
                <small>© <?php echo date("Y"); ?> Pateros Technological College. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
