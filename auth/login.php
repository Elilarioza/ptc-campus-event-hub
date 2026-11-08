
<?php
session_start();
require_once "../config/database.php";

$message = "";
$messageType = "error";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["first_name"] = $user["first_name"];
        $_SESSION["last_name"] = $user["last_name"];
        $_SESSION["role"] = $user["role"];

        if ($user["role"] === "admin") {
            header("Location: ../admin/dashboard.php");
        } else {
            header("Location: ../student/dashboard.php");
        }
        exit;
    }

    $message = "Invalid email or password.";
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="../assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ptc-style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(16,75,43,0.95), rgba(23,107,58,0.8)), url("../assets/images/ptc-building.jpg") center/cover no-repeat;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="ptc-form-card">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light mb-3" style="width:78px;height:78px;overflow:hidden;border:2px solid rgba(16,75,43,0.15);">
                            <img src="../assets/images/ptc-logo.png" alt="Pateros Technological College logo" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                        <h2 class="mb-1">PTC Campus Event Hub</h2>
                        <p class="text-muted mb-0">Sign in to your account</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="notice error"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" required autocomplete="email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" required autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn btn-green w-100">Login</button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="text-muted">New to the platform?</span>
                        <a href="register.php" class="fw-semibold ms-1" style="color: var(--primary-green);">Create account</a>
                    </div>

                    <div class="text-center mt-4 text-muted small">
                        Pateros Technological College
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
