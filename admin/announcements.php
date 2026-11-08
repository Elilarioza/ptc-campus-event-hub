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
        $announcementId = filter_input(INPUT_POST, "announcement_id", FILTER_VALIDATE_INT);
        $title = trim($_POST["title"] ?? "");
        $content = trim($_POST["content"] ?? "");
        $status = $_POST["status"] ?? "draft";

        if ($title === "" || $content === "") {
            $message = "Announcement title and content are required.";
        } else {
            if ($action === "delete_announcement") {
                if (!$announcementId || $announcementId <= 0) {
                    $message = "Invalid announcement selected.";
                } else {
                    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$announcementId]);
                    $message = "Announcement deleted successfully.";
                    $messageType = "success";
                }
            } else {
                if ($action === "update_announcement" && $announcementId && $announcementId > 0) {
                    $pdo->prepare("UPDATE announcements SET title = ?, content = ?, status = ?, posted_by = ? WHERE id = ?")->execute([$title, $content, $status, $_SESSION["user_id"], $announcementId]);
                    $message = "Announcement updated successfully.";
                    $messageType = "success";
                } else {
                    $pdo->prepare("INSERT INTO announcements (title, content, posted_by, status) VALUES (?, ?, ?, ?)")->execute([$title, $content, $_SESSION["user_id"], $status]);
                    $message = "Announcement saved successfully.";
                    $messageType = "success";
                }
            }
        }
    }
}

$editAnnouncement = null;
if (isset($_GET["edit_id"])) {
    $editId = (int) $_GET["edit_id"];
    if ($editId > 0) {
        $editStmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $editStmt->execute([$editId]);
        $editAnnouncement = $editStmt->fetch();
    }
}

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements | PTC Campus Event Hub</title>
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
                <a class="nav-link" href="venues.php">Venues</a>
                <a class="nav-link" href="students.php">Students</a>
                <a class="nav-link" href="registrations.php">Registrations</a>
                <a class="nav-link" href="attendance.php">Attendance</a>
                <a class="nav-link active" href="announcements.php">Announcements</a>
                <div class="mt-4"><a class="nav-link" href="../auth/logout.php">Logout</a></div>
            </aside>

            <main class="col-md-9 col-lg-10 ptc-main">
                <div class="ptc-topbar">
                    <div>
                        <h4 class="fw-bold mb-1">Announcements</h4>
                        <small class="text-muted">Create, publish, and manage campus updates.</small>
                    </div>
                    <span class="badge bg-success">Administrator</span>
                </div>

                <div class="ptc-content">
                    <?php if ($message): ?>
                        <div class="notice <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <div class="ptc-card p-4 mb-4">
                        <h5 class="fw-bold mb-3"><?php echo $editAnnouncement ? 'Edit Announcement' : 'Create Announcement'; ?></h5>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                            <?php if ($editAnnouncement): ?>
                                <input type="hidden" name="announcement_id" value="<?php echo (int) $editAnnouncement["id"]; ?>">
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Title</label>
                                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editAnnouncement["title"] ?? ""); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="draft" <?php echo (($editAnnouncement["status"] ?? "draft") === "draft") ? "selected" : ""; ?>>Draft</option>
                                        <option value="published" <?php echo (($editAnnouncement["status"] ?? "draft") === "published") ? "selected" : ""; ?>>Published</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" name="action" value="<?php echo $editAnnouncement ? 'update_announcement' : 'add_announcement'; ?>" class="btn btn-green w-100"><?php echo $editAnnouncement ? 'Update' : 'Save'; ?></button>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Content</label>
                                    <textarea name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($editAnnouncement["content"] ?? ""); ?></textarea>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="ptc-table-wrap">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($announcements): ?>
                                    <?php foreach ($announcements as $item): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($item["title"]); ?></td>
                                            <td><span class="ptc-badge <?php echo $item["status"] === 'published' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars(ucfirst($item["status"])); ?></span></td>
                                            <td><?php echo htmlspecialchars(date("M d, Y", strtotime($item["created_at"]))); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="announcements.php?edit_id=<?php echo (int) $item["id"]; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                                    <form method="POST" onsubmit="return confirm('Delete this announcement?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                                        <input type="hidden" name="announcement_id" value="<?php echo (int) $item["id"]; ?>">
                                                        <input type="hidden" name="action" value="delete_announcement">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No announcements found.</td></tr>
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
