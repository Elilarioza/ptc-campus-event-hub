
<?php
session_start();

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["confirm_logout"] ?? "") === "1") {
    $csrfToken = $_POST["csrf_token"] ?? null;

    if ($csrfToken !== null && !empty($_SESSION["csrf_token"]) && !hash_equals($_SESSION["csrf_token"], $csrfToken)) {
        header("Location: ../index.php");
        exit;
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: ../index.php");
    exit;
}

$csrfToken = $_SESSION["csrf_token"] ?? bin2hex(random_bytes(32));
$_SESSION["csrf_token"] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout | PTC Campus Event Hub</title>
    <link rel="icon" type="image/png" href="../assets/images/ptc-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --dark-green: #104B2B;
            --primary-green: #176B3A;
            --white: #ffffff;
            --text: #26382C;
            --muted: #6B7C70;
            --border: #dfeadf;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(16,75,43,0.96), rgba(23,107,58,0.9));
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
        }

        .logout-modal {
            width: min(92vw, 440px);
            background: rgba(255,255,255,0.96);
            border-radius: 20px;
            box-shadow: 0 28px 60px rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.15);
            overflow: hidden;
        }

        .logout-modal-header {
            background: linear-gradient(180deg, rgba(23,107,58,0.08), rgba(23,107,58,0));
            border-bottom: 1px solid var(--border);
            padding: 1.5rem 1.5rem 1rem;
            text-align: center;
        }

        .logout-modal-header .logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 0.9rem;
            border-radius: 18px;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(23,107,58,0.12);
            box-shadow: 0 8px 24px rgba(16, 75, 43, 0.08);
        }

        .logout-modal-header .logo img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .logout-modal-title {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--dark-green);
        }

        .logout-modal-body {
            padding: 1.4rem 1.5rem 1.1rem;
            text-align: center;
        }

        .logout-modal-body p {
            margin: 0;
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.6;
        }

        .logout-modal-footer {
            padding: 0 1.5rem 1.5rem;
            display: flex;
            justify-content: center;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn-cancel,
        .btn-confirm {
            min-width: 150px;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-weight: 700;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .btn-cancel {
            background: transparent;
            border-color: rgba(23,107,58,0.25);
            color: var(--primary-green);
        }

        .btn-cancel:hover {
            background: rgba(23,107,58,0.06);
            color: var(--dark-green);
        }

        .btn-confirm {
            background: var(--primary-green);
            color: var(--white);
            border-color: var(--primary-green);
        }

        .btn-confirm:hover {
            background: var(--dark-green);
            border-color: var(--dark-green);
            color: var(--white);
        }

        @media (max-width: 480px) {
            .logout-modal-title {
                font-size: 1.5rem;
            }

            .logout-modal-footer {
                flex-direction: column-reverse;
            }

            .btn-cancel,
            .btn-confirm {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
        <div class="logout-modal-header">
            <div class="logo">
                <img src="../assets/images/ptc-logo.png" alt="PTC logo">
            </div>
            <h1 id="logoutTitle" class="logout-modal-title">Confirm Logout</h1>
        </div>

        <div class="logout-modal-body">
            <p>Are you sure you want to log out of your account?</p>
        </div>

        <div class="logout-modal-footer">
            <button type="button" class="btn-cancel" id="cancelLogout">Cancel</button>
            <form method="POST" action="logout.php" style="display:inline;">
                <input type="hidden" name="confirm_logout" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn-confirm">Yes, Logout</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById("cancelLogout").addEventListener("click", function () {
            const referrer = document.referrer;
            if (referrer) {
                window.location.href = referrer;
                return;
            }
            window.location.href = "../index.php";
        });
    </script>
</body>
</html>

