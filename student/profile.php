<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/notifications.php";

try {
    $profileImageColumnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($profileImageColumnCheck && !$profileImageColumnCheck->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER email");
    }
} catch (PDOException $e) {
    // Ignore schema errors here; fallback behavior is allowed if the column cannot be created.
}

$currentUserId = (int) $_SESSION["user_id"];
$unreadNotificationCount = getUnreadNotificationCount($pdo, $currentUserId);
$userId = $currentUserId;
$message = $_SESSION["profile_message"] ?? "";
$messageType = $_SESSION["profile_message_type"] ?? "error";
unset($_SESSION["profile_message"], $_SESSION["profile_message_type"]);
$initials = "PT";
$defaultProfileAvatar = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" rx="100" fill="#eef8ef"/><circle cx="100" cy="72" r="32" fill="#1d6e3a"/><path d="M52 158c12-26 34-39 48-39s36 13 48 39" fill="#1d6e3a"/><text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="52" font-weight="700" fill="#0c4d2c">PT</text></svg>');
$profileImagePath = $defaultProfileAvatar;

$stmt = $pdo->prepare("SELECT id, first_name, last_name, student_number, email, profile_image FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user && !empty($user["profile_image"])) {
    $profileImagePath = "../" . ltrim($user["profile_image"], "/");
}

$editMode = false;
$profileDraft = [
    "first_name" => $user["first_name"] ?? "",
    "last_name" => $user["last_name"] ?? "",
    "email" => $user["email"] ?? "",
];

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    $postAction = $_POST["action"] ?? "";
    $redirectAfterPost = false;

    if ($postAction === "upload_avatar") {
        if (!isset($_FILES["profile_image"]) || $_FILES["profile_image"]["error"] !== UPLOAD_ERR_OK) {
            $message = "Please select a valid image file to upload.";
            $messageType = "error";
            $redirectAfterPost = true;
        } else {
            $file = $_FILES["profile_image"];
            $allowedMimeTypes = ["image/jpeg", "image/png", "image/webp"];
            $allowedExtensions = ["jpg", "jpeg", "png", "webp"];
            $maxSize = 2 * 1024 * 1024;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo ? finfo_file($finfo, $file["tmp_name"]) : null;
            if ($finfo) {
                finfo_close($finfo);
            }

            $imageInfo = @getimagesize($file["tmp_name"]);
            $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            $isPhpImageType = $imageInfo && defined("IMAGETYPE_PHP") && $imageInfo[2] === IMAGETYPE_PHP;

            if ($file["size"] > $maxSize) {
                $message = "Profile image must be 2MB or smaller.";
                $messageType = "error";
                $redirectAfterPost = true;
            } elseif (!in_array($detectedMime, $allowedMimeTypes, true) || !$imageInfo || $isPhpImageType) {
                $message = "Only JPG, JPEG, PNG, and WEBP images are allowed.";
                $messageType = "error";
                $redirectAfterPost = true;
            } elseif (!in_array($extension, $allowedExtensions, true)) {
                $message = "Unsupported image format.";
                $messageType = "error";
                $redirectAfterPost = true;
            } else {
                $uploadDir = __DIR__ . "/../assets/uploads/profiles/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filename = "student_" . $userId . "_" . bin2hex(random_bytes(8)) . "." . $extension;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($file["tmp_name"], $targetPath)) {
                    $storedPath = "assets/uploads/profiles/" . $filename;
                    $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$storedPath, $userId]);
                    $user["profile_image"] = $storedPath;
                    $profileImagePath = "../" . $storedPath;
                    $message = "Profile picture updated successfully.";
                    $messageType = "success";
                    $redirectAfterPost = true;
                } else {
                    $message = "Unable to upload profile image.";
                    $messageType = "error";
                    $redirectAfterPost = true;
                }
            }
        }
    } elseif ($postAction === "remove_avatar") {
        $pdo->prepare("UPDATE users SET profile_image = NULL WHERE id = ?")->execute([$userId]);
        $user["profile_image"] = null;
        $profileImagePath = $defaultProfileAvatar;
        $message = "Profile picture removed successfully.";
        $messageType = "success";
        $redirectAfterPost = true;
    } elseif ($postAction === "save_profile") {
        $firstName = trim($_POST["first_name"] ?? "");
        $lastName = trim($_POST["last_name"] ?? "");
        $email = trim($_POST["email"] ?? "");

        if (empty($firstName) || empty($lastName) || empty($email)) {
            $message = "Please complete all profile fields.";
            $messageType = "error";
            $redirectAfterPost = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = "error";
            $redirectAfterPost = true;
        } else {
            $duplicate = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $duplicate->execute([$email, $userId]);

            if ($duplicate->fetch()) {
                $message = "Another account already uses this email address.";
                $messageType = "error";
                $redirectAfterPost = true;
            } else {
                $update = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                $update->execute([$firstName, $lastName, $email, $userId]);
                $message = "Profile updated successfully.";
                $messageType = "success";
                $_SESSION["first_name"] = $firstName;
                $_SESSION["last_name"] = $lastName;
                $user["first_name"] = $firstName;
                $user["last_name"] = $lastName;
                $user["email"] = $email;
                $profileDraft["first_name"] = $firstName;
                $profileDraft["last_name"] = $lastName;
                $profileDraft["email"] = $email;
                $redirectAfterPost = true;
            }
        }
    } elseif ($postAction === "change_password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        $storedUser = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $storedUser->execute([$userId]);
        $storedPassword = $storedUser->fetchColumn();

        if (!$storedPassword || !password_verify($currentPassword, $storedPassword)) {
            $message = "Current password is incorrect.";
            $messageType = "error";
            $redirectAfterPost = true;
        } elseif (strlen($newPassword) < 8) {
            $message = "New password must be at least 8 characters long.";
            $messageType = "error";
            $redirectAfterPost = true;
        } elseif ($newPassword !== $confirmPassword) {
            $message = "New password and confirm password do not match.";
            $messageType = "error";
            $redirectAfterPost = true;
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
            $message = "Password changed successfully.";
            $messageType = "success";
            $redirectAfterPost = true;
        }
    }

    if ($redirectAfterPost) {
        $_SESSION["profile_message"] = $message;
        $_SESSION["profile_message_type"] = $messageType;
        header("Location: profile.php");
        exit;
    }
}

$displayFirstName = $user["first_name"] ?? "";
$displayLastName = $user["last_name"] ?? "";
$displayEmail = $user["email"] ?? "";
$displayStudentNumber = $user["student_number"] ?? "";
$fullName = trim($displayFirstName . " " . $displayLastName);
$initials = strtoupper(substr($displayFirstName, 0, 1) . substr($displayLastName, 0, 1));
if ($initials === "") {
    $initials = "PT";
}
$defaultProfileAvatar = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" rx="100" fill="#eef8ef"/><circle cx="100" cy="72" r="32" fill="#1d6e3a"/><path d="M52 158c12-26 34-39 48-39s36 13 48 39" fill="#1d6e3a"/><text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="52" font-weight="700" fill="#0c4d2c">' . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</text></svg>');
if (empty($user["profile_image"])) {
    $profileImagePath = $defaultProfileAvatar;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="../assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/ptc-style.css">
    <style>
        body {
            background: linear-gradient(180deg, #f2f8f3 0%, #edf5ef 100%);
        }

        .student-profile-shell {
            max-width: 1200px;
            margin: 2.5rem auto 3rem;
            padding: 0 1rem;
        }

        .profile-header {
            background: linear-gradient(135deg, rgba(23,107,58,0.06), rgba(35,122,69,0.02));
            border: 1px solid rgba(16,75,43,0.12);
            border-radius: 26px;
            box-shadow: 0 18px 38px rgba(16, 75, 43, 0.08);
            overflow: hidden;
        }

        .profile-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 1.6rem 1.8rem;
        }

        .profile-main {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex: 1;
            min-width: 0;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(23,107,58,0.18);
            background: var(--soft-green);
            box-shadow: 0 12px 26px rgba(16,75,43,0.12);
        }

        .profile-avatar-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: var(--white);
            font-size: 2rem;
            font-weight: 800;
            box-shadow: 0 12px 26px rgba(16,75,43,0.12);
        }

        .profile-name-wrap {
            min-width: 0;
        }

        .profile-name {
            margin: 0;
            font-size: clamp(1.7rem, 2vw, 2.3rem);
            color: var(--dark-green);
            font-weight: 800;
            line-height: 1.2;
        }

        .profile-subline {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.45rem;
            color: var(--muted);
            font-weight: 600;
        }

        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(23,107,58,0.08);
            color: var(--primary-green);
            border: 1px solid rgba(23,107,58,0.15);
            border-radius: 999px;
            padding: 0.38rem 0.8rem;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .profile-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #2bb673;
            display: inline-block;
        }

        .profile-action {
            flex-shrink: 0;
        }

        .profile-section {
            background: rgba(232, 245, 233, 0.7);
            border: 1px solid rgba(16,75,43,0.1);
            border-radius: 24px;
            box-shadow: 0 14px 30px rgba(16,75,43,0.05);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .section-title {
            margin: 0;
            color: var(--dark-green);
            font-size: 1.5rem;
            font-weight: 800;
        }

        .section-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(23,107,58,0.09);
            color: var(--primary-green);
            font-size: 1.05rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .info-card {
            background: var(--white);
            border: 1px solid rgba(16,75,43,0.08);
            border-radius: 18px;
            padding: 1rem 1.1rem;
            box-shadow: 0 10px 20px rgba(16,75,43,0.04);
        }

        .info-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-green);
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .info-value {
            margin: 0;
            color: var(--text);
            font-size: 1.02rem;
            font-weight: 600;
        }

        .ptc-form-group {
            margin-bottom: 1rem;
        }

        .ptc-form-group label {
            display: block;
            font-weight: 700;
            color: var(--dark-green);
            margin-bottom: 0.5rem;
        }

        .ptc-form-control,
        .ptc-form-control:focus {
            background: var(--white);
            border: 1px solid rgba(16,75,43,0.18);
            border-radius: 12px;
            box-shadow: none;
            min-height: 48px;
            color: var(--text);
            padding: 0.78rem 0.9rem;
        }

        .ptc-form-control:focus {
            border-color: rgba(23,107,58,0.6);
            box-shadow: 0 0 0 0.2rem rgba(23,107,58,0.12);
        }

        .profile-actions,
        .security-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            margin-top: 1.2rem;
        }

        .btn-edit,
        .btn-save,
        .btn-cancel,
        .btn-password,
        .btn-logout {
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-weight: 700;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .btn-edit,
        .btn-save,
        .btn-password {
            background: var(--primary-green);
            color: var(--white);
            box-shadow: 0 10px 20px rgba(23,107,58,0.14);
        }

        .btn-edit:hover,
        .btn-save:hover,
        .btn-password:hover {
            background: var(--dark-green);
            color: var(--white);
        }

        .btn-cancel {
            background: var(--white);
            color: var(--primary-green);
            border-color: rgba(23,107,58,0.25);
        }

        .btn-cancel:hover {
            background: rgba(23,107,58,0.05);
            color: var(--dark-green);
        }

        .btn-logout {
            background: rgba(182,59,59,0.08);
            color: #8f2a2a;
            border-color: rgba(182,59,59,0.15);
        }

        .btn-logout:hover {
            background: #8f2a2a;
            color: var(--white);
        }

        .avatar-upload {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .avatar-upload form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .upload-input {
            display: none;
        }

        .upload-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            background: rgba(23,107,58,0.08);
            border: 1px solid rgba(23,107,58,0.18);
            color: var(--primary-green);
            border-radius: 12px;
            padding: 0.7rem 1rem;
            font-weight: 700;
            cursor: pointer;
        }

        .security-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .notice {
            margin: 0 0 1.2rem 0;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .notice.success {
            background: rgba(23, 107, 58, 0.08);
            border-color: rgba(23, 107, 58, 0.18);
            color: var(--dark-green);
        }

        .notice.error {
            background: rgba(182,59,59,0.08);
            border-color: rgba(182,59,59,0.18);
            color: #7a2121;
        }

        @media (max-width: 991.98px) {
            .profile-header-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile-main {
                width: 100%;
            }

            .profile-action {
                width: 100%;
            }

            .profile-action .btn-edit {
                width: 100%;
            }
        }

        @media (max-width: 767px) {
            .info-grid,
            .security-grid {
                grid-template-columns: 1fr;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .avatar-upload form {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .upload-label,
            .profile-actions .btn,
            .security-actions .btn,
            .avatar-actions .btn {
                width: 100%;
            }
        }

        .avatar-preview {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(23,107,58,0.18);
            background: var(--soft-green);
            box-shadow: 0 10px 22px rgba(16,75,43,0.10);
            display: block;
        }

        .avatar-preview.empty {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--dark-green);
            background: rgba(23,107,58,0.08);
            border: 2px dashed rgba(23,107,58,0.25);
        }

        .avatar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
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
                        <li class="nav-item"><a href="my-registrations.php" class="student-nav-item nav-link" title="My Registrations"><i class="bi bi-clipboard-check"></i><span>My Registrations</span></a></li>
                        <li class="nav-item"><a href="announcements.php" class="student-nav-item nav-link" title="Announcements"><i class="bi bi-megaphone"></i><span>Announcements</span></a></li>
                        <li class="nav-item"><a href="profile.php" class="student-nav-item nav-link active" title="Profile"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>
                        <li class="nav-item"><a href="notifications.php" class="student-nav-item nav-link" title="Notifications"><i class="bi bi-bell-fill"></i><span>Notifications</span><?php if ($unreadNotificationCount > 0): ?><span class="notification-count"><?php echo $unreadNotificationCount; ?></span><?php endif; ?></a></li>
                        <li class="nav-item"><a href="../auth/logout.php" class="btn btn-light btn-sm ms-lg-2" title="Logout">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="student-profile-shell">
        <?php if ($message): ?>
            <div class="notice <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-header-inner">
                <div class="profile-main">
                    <?php if (!empty($user["profile_image"])): ?>
                        <img src="<?php echo htmlspecialchars($profileImagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="Student profile picture" class="profile-avatar">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder" aria-label="Student avatar"><?php echo htmlspecialchars($initials ?: 'PT'); ?></div>
                    <?php endif; ?>

                    <div class="profile-name-wrap">
                        <h1 class="profile-name"><?php echo htmlspecialchars($fullName ?: "Student"); ?></h1>
                        <div class="profile-subline">
                            <span><?php echo htmlspecialchars($displayStudentNumber ?: "Student Number Unavailable"); ?></span>
                            <span class="profile-badge"><span class="dot"></span> Active Account</span>
                        </div>
                    </div>
                </div>

                <div class="profile-action">
                    <button type="button" class="btn-edit" id="toggleEditProfile"><i class="bi bi-pencil-square me-2"></i>Edit Profile</button>
                </div>
            </div>
        </div>

        <section class="profile-section">
            <div class="section-heading">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <span class="section-icon"><i class="bi bi-person-vcard"></i></span>
                    <h2 class="section-title">Personal Information</h2>
                </div>
            </div>

            <div class="avatar-upload mb-4">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?php echo htmlspecialchars($profileImagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="Current profile picture" class="avatar-preview <?php echo empty($user["profile_image"]) ? 'empty' : ''; ?>" id="currentAvatarPreview">
                </div>

                <form method="POST" enctype="multipart/form-data" id="profileImageForm">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="file" id="profileImageInput" name="profile_image" class="upload-input" accept="image/png,image/jpeg,image/jpg,image/webp">
                    <div class="avatar-actions">
                        <label for="profileImageInput" class="upload-label"><i class="bi bi-camera-fill"></i> Change Profile Picture</label>
                        <button type="submit" class="btn btn-outline-green">Save Photo</button>
                        <?php if (!empty($user["profile_image"])): ?>
                            <button type="button" class="btn btn-outline-danger" id="removeProfileImageBtn">Remove Photo</button>
                        <?php endif; ?>
                    </div>
                </form>

                <form method="POST" id="removeAvatarForm" class="d-none">
                    <input type="hidden" name="action" value="remove_avatar">
                </form>
            </div>

            <form method="POST" id="profileForm">
                <input type="hidden" name="action" value="save_profile">
                <div class="info-grid" id="profileViewMode">
                    <div class="info-card">
                        <span class="info-label">First Name</span>
                        <p class="info-value"><?php echo htmlspecialchars($displayFirstName ?: "Not set"); ?></p>
                    </div>
                    <div class="info-card">
                        <span class="info-label">Last Name</span>
                        <p class="info-value"><?php echo htmlspecialchars($displayLastName ?: "Not set"); ?></p>
                    </div>
                    <div class="info-card">
                        <span class="info-label">Student Number</span>
                        <p class="info-value"><?php echo htmlspecialchars($displayStudentNumber ?: "Not set"); ?></p>
                    </div>
                    <div class="info-card">
                        <span class="info-label">Email Address</span>
                        <p class="info-value"><?php echo htmlspecialchars($displayEmail ?: "Not set"); ?></p>
                    </div>
                </div>

                <div class="info-grid d-none" id="profileEditMode">
                    <div class="ptc-form-group">
                        <label for="editFirstName">First Name</label>
                        <input type="text" id="editFirstName" name="first_name" class="form-control ptc-form-control" value="<?php echo htmlspecialchars($displayFirstName); ?>" required>
                    </div>
                    <div class="ptc-form-group">
                        <label for="editLastName">Last Name</label>
                        <input type="text" id="editLastName" name="last_name" class="form-control ptc-form-control" value="<?php echo htmlspecialchars($displayLastName); ?>" required>
                    </div>
                    <div class="ptc-form-group">
                        <label for="editStudentNumber">Student Number</label>
                        <input type="text" id="editStudentNumber" name="student_number" class="form-control ptc-form-control" value="<?php echo htmlspecialchars($displayStudentNumber); ?>" readonly>
                    </div>
                    <div class="ptc-form-group">
                        <label for="editEmail">Email Address</label>
                        <input type="email" id="editEmail" name="email" class="form-control ptc-form-control" value="<?php echo htmlspecialchars($displayEmail); ?>" required>
                    </div>
                </div>

                <div class="profile-actions d-none" id="saveProfileActions">
                    <button type="submit" class="btn-save"><i class="bi bi-check-circle me-2"></i>Save Changes</button>
                    <button type="button" class="btn-cancel" id="cancelProfileEdit">Cancel</button>
                </div>
            </form>
        </section>

        <section class="profile-section">
            <div class="section-heading">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <span class="section-icon"><i class="bi bi-shield-lock"></i></span>
                    <h2 class="section-title">Account Security</h2>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="security-grid">
                    <div class="ptc-form-group">
                        <label for="currentPassword">Current Password</label>
                        <input type="password" id="currentPassword" name="current_password" class="form-control ptc-form-control" placeholder="Enter current password" required>
                    </div>
                    <div class="ptc-form-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="new_password" class="form-control ptc-form-control" placeholder="Enter new password" minlength="8" required>
                    </div>
                    <div class="ptc-form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input type="password" id="confirmPassword" name="confirm_password" class="form-control ptc-form-control" placeholder="Confirm new password" minlength="8" required>
                    </div>
                </div>

                <div class="security-actions">
                    <button type="submit" class="btn-password"><i class="bi bi-key me-2"></i>Change Password</button>
                </div>
            </form>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const viewMode = document.getElementById('profileViewMode');
        const editMode = document.getElementById('profileEditMode');
        const saveActions = document.getElementById('saveProfileActions');
        const toggleEditProfile = document.getElementById('toggleEditProfile');
        const cancelProfileEdit = document.getElementById('cancelProfileEdit');
        const profileImageInput = document.getElementById('profileImageInput');
        const currentAvatarPreview = document.getElementById('currentAvatarPreview');
        const profileImageForm = document.getElementById('profileImageForm');
        const removeProfileImageBtn = document.getElementById('removeProfileImageBtn');
        const removeAvatarForm = document.getElementById('removeAvatarForm');
        const defaultAvatarPath = <?php echo json_encode($defaultProfileAvatar); ?>;
        const hasCurrentProfileImage = <?php echo !empty($user["profile_image"]) ? 'true' : 'false'; ?>;

        function setProfileMode(isEditing) {
            viewMode.classList.toggle('d-none', isEditing);
            editMode.classList.toggle('d-none', !isEditing);
            saveActions.classList.toggle('d-none', !isEditing);
        }

        if (toggleEditProfile) {
            toggleEditProfile.addEventListener('click', () => setProfileMode(true));
        }

        if (cancelProfileEdit) {
            cancelProfileEdit.addEventListener('click', () => {
                document.getElementById('editFirstName').value = '<?php echo addslashes(htmlspecialchars($displayFirstName)); ?>';
                document.getElementById('editLastName').value = '<?php echo addslashes(htmlspecialchars($displayLastName)); ?>';
                document.getElementById('editEmail').value = '<?php echo addslashes(htmlspecialchars($displayEmail)); ?>';
                setProfileMode(false);
            });
        }

        if (profileImageInput && currentAvatarPreview) {
            profileImageInput.addEventListener('change', function () {
                if (!this.files || !this.files[0]) {
                    currentAvatarPreview.src = defaultAvatarPath;
                    return;
                }

                const file = this.files[0];
                const url = URL.createObjectURL(file);
                currentAvatarPreview.src = url;
                currentAvatarPreview.classList.remove('empty');
            });
        }

        if (profileImageForm && profileImageInput) {
            profileImageForm.addEventListener('submit', function (event) {
                if (!profileImageInput.files || !profileImageInput.files[0]) {
                    return;
                }

                if (hasCurrentProfileImage) {
                    const confirmed = window.confirm('You are about to replace your current profile picture. Continue?');
                    if (!confirmed) {
                        event.preventDefault();
                    }
                }
            });
        }

        if (removeProfileImageBtn && removeAvatarForm) {
            removeProfileImageBtn.addEventListener('click', function () {
                const confirmed = window.confirm('Are you sure you want to remove your current profile picture?');
                if (confirmed) {
                    removeAvatarForm.submit();
                }
            });
        }
    </script>
</body>
</html>
