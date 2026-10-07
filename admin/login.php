<?php

session_start();
require_once __DIR__ . "/../config/db.php";

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $sql = "SELECT id, full_name, email, password, role
                FROM admin_users
                WHERE email = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                if (password_verify($password, $user["password"])) {

                    session_regenerate_id(true);

                    $_SESSION["admin_id"] = $user["id"];
                    $_SESSION["admin_name"] = $user["full_name"];
                    $_SESSION["admin_email"] = $user["email"];
                    $_SESSION["admin_role"] = $user["role"];

                    header("Location: dashboard.php");
                    exit;

                } else {

                    $error = "Invalid email or password.";
                }

            } else {

                $error = "Invalid email or password.";
            }

            $stmt->close();

        } else {

            $error = "Database error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="E&R Real Estate and Construction Limited administration login">
    <title>Administrator Login | E&amp;R Real Estate &amp; Construction</title>
    <link rel="icon" href="../logo/E&R Logo.jfif">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
</head>

<body class="login-page">
    <main class="login-shell">
        <section class="login-card" aria-labelledby="loginTitle">
            <a class="login-brand" href="../index.html">
                <img src="../logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo">
                <span><strong>E&amp;R</strong><small>Administration</small></span>
            </a>

            <p class="eyebrow">Secure access</p>
            <h1 id="loginTitle">Welcome back.</h1>
            <p class="login-copy">Sign in to manage projects, enquiries, and the E&amp;R public website.</p>

            <?php if (!empty($error)): ?>
                <p class="login-message visible warning" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>" autocomplete="username" required>
                </div>

                <div class="form-field password-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>

                <button class="button button-primary login-submit" type="submit">
                    Sign in <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <p class="login-footer"><i class="fas fa-lock" aria-hidden="true"></i> Your administrator credentials are handled securely.</p>
        </section>

        <aside class="login-aside">
            <p class="eyebrow">E&amp;R Control Room</p>
            <h2>Build with care.<br><em>Manage with clarity.</em></h2>
            <p>Keep every project, client enquiry, and update moving from one protected workspace.</p>
            <a href="../index.html">Return to public site <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </aside>
    </main>
</body>
</html>