
<?php
require_once "../config/database.php";

$message = "";
$messageType = "error";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST["first_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $studentNumber = trim($_POST["student_number"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (empty($firstName) || empty($lastName) || empty($studentNumber) || empty($email) || empty($password) || empty($confirmPassword)) {
        $message = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR student_number = ?");
            $check->execute([$email, $studentNumber]);

            if ($check->fetch()) {
                $message = "Email or student number is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, student_number, email, password, role) VALUES (?, ?, ?, ?, ?, 'student')");
                $stmt->execute([$firstName, $lastName, $studentNumber, $email, $hashedPassword]);
                $message = "Registration successful! You can now log in.";
                $messageType = "success";
            }
        } catch (PDOException $e) {
            $message = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | PTC Campus Event Hub</title>
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
            <div class="col-xl-6 col-lg-7 col-md-9">
                <div class="ptc-form-card">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light mb-3" style="width:78px;height:78px;overflow:hidden;border:2px solid rgba(16,75,43,0.15);">
                            <img src="../assets/images/ptc-logo.png" alt="Pateros Technological College logo" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                        <h2 class="mb-1">Create Your Student Account</h2>
                        <p class="text-muted mb-0">Join PTC Campus Event Hub</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="notice <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Student Number</label>
                                <input type="text" name="student_number" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Password</label>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-green w-100 mt-4">Create Account</button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="text-muted">Already have an account?</span>
                        <a href="login.php" class="fw-semibold ms-1" style="color: var(--primary-green);">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
