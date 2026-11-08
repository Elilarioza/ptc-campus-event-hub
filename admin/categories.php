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
        $categoryId = filter_input(INPUT_POST, "category_id", FILTER_VALIDATE_INT);
        $categoryName = trim($_POST["category_name"] ?? "");
        $description = trim($_POST["description"] ?? "");

        if ($action === "delete_category") {
            if (!$categoryId || $categoryId <= 0) {
                $message = "Invalid category selected.";
            } else {
                $used = $pdo->prepare("SELECT COUNT(*) FROM events WHERE category_id = ?");
                $used->execute([$categoryId]);
                if ((int) $used->fetchColumn() > 0) {
                    $message = "This category is in use and cannot be deleted.";
                } else {
                    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$categoryId]);
                    $message = "Category deleted successfully.";
                    $messageType = "success";
                }
            }
        } else {
            if ($categoryName === "") {
                $message = "Category name is required.";
            } else {
                if ($action === "update_category" && $categoryId && $categoryId > 0) {
                    $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$categoryName, $description, $categoryId]);
                    $message = "Category updated successfully.";
                    $messageType = "success";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
                    $stmt->execute([$categoryName, $description]);
                    $message = "Category added successfully.";
                    $messageType = "success";
                }
            }
        }
    }
}

$editCategory = null;
if (isset($_GET["edit_id"])) {
    $editId = (int) $_GET["edit_id"];
    if ($editId > 0) {
        $editStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $editStmt->execute([$editId]);
        $editCategory = $editStmt->fetch();
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories | PTC Campus Event Hub</title>
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
                <a class="nav-link active" href="categories.php">Categories</a>
                <a class="nav-link" href="venues.php">Venues</a>
                <a class="nav-link" href="students.php">Students</a>
                <a class="nav-link" href="registrations.php">Registrations</a>
                <a class="nav-link" href="attendance.php">Attendance</a>
                <a class="nav-link" href="announcements.php">Announcements</a>
                <div class="mt-4"><a class="nav-link" href="../auth/logout.php">Logout</a></div>
            </aside>

            <main class="col-md-9 col-lg-10 ptc-main">
                <div class="ptc-topbar">
                    <div>
                        <h4 class="fw-bold mb-1">Event Categories</h4>
                        <small class="text-muted">Organize and classify campus events.</small>
                    </div>
                    <span class="badge bg-success">Administrator</span>
                </div>

                <div class="ptc-content">
                    <?php if ($message): ?>
                        <div class="notice <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <div class="ptc-card p-4 mb-4">
                        <h5 class="fw-bold mb-3"><?php echo $editCategory ? 'Edit Category' : 'Add New Category'; ?></h5>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                            <?php if ($editCategory): ?>
                                <input type="hidden" name="category_id" value="<?php echo (int) $editCategory["id"]; ?>">
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold">Category Name</label>
                                    <input type="text" name="category_name" class="form-control" value="<?php echo htmlspecialchars($editCategory["category_name"] ?? ""); ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold">Description</label>
                                    <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($editCategory["description"] ?? ""); ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" name="action" value="<?php echo $editCategory ? 'update_category' : 'add_category'; ?>" class="btn btn-green w-100"><?php echo $editCategory ? 'Update' : 'Add'; ?></button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="ptc-table-wrap">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($categories): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($category["category_name"]); ?></td>
                                            <td><?php echo htmlspecialchars($category["description"] ?? "No description"); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="categories.php?edit_id=<?php echo (int) $category["id"]; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                                    <form method="POST" onsubmit="return confirm('Delete this category?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                                        <input type="hidden" name="category_id" value="<?php echo (int) $category["id"]; ?>">
                                                        <input type="hidden" name="action" value="delete_category">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No categories added yet.</td></tr>
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
