<?php
require_once __DIR__ . "/../includes/admin-session.php";
require_once __DIR__ . "/../includes/admin-form.php";
require_once __DIR__ . "/../includes/public-form-security.php";
require_once __DIR__ . "/../includes/site-mail.php";
require_once __DIR__ . "/../includes/site-settings.php";
require_once __DIR__ . "/../config/db.php";
startAdminSession();

$token = adminFormToken();
$notice = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim(adminPostString("email"));
    if (!adminFormIsValid()) {
        $error = "Your session expired. Refresh this page and try again.";
    } elseif (!publicFormRateLimit("admin-password-reset", 60)) {
        http_response_code(429);
        $error = "Please wait before requesting another password reset.";
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $statement = $conn->prepare("SELECT id, full_name, email FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1");
        if (!$statement) {
            throw new RuntimeException("Password recovery could not be prepared.");
        }
        $statement->bind_param("s", $email);
        if (!$statement->execute()) {
            throw new RuntimeException("Password recovery could not be checked.");
        }
        $account = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($account) {
            $baseUrl = rtrim((string) (getenv("ER_PUBLIC_BASE_URL") ?: ""), "/");
            $parsedBase = $baseUrl !== "" ? parse_url($baseUrl) : false;
            $localHost = preg_match('/\A(?:localhost|127\.0\.0\.1)(?::\d+)?\z/', (string) ($_SERVER["HTTP_HOST"] ?? "")) === 1;
            if ($parsedBase && isset($parsedBase["scheme"], $parsedBase["host"])
                && ($parsedBase["scheme"] === "https" || ($localHost && $parsedBase["scheme"] === "http"))) {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash("sha256", $rawToken);
                $invalidate = $conn->prepare("UPDATE admin_password_resets SET used_at = NOW() WHERE admin_user_id = ? AND used_at IS NULL");
                if (!$invalidate) {
                    throw new RuntimeException("Existing recovery links could not be invalidated.");
                }
                $invalidate->bind_param("i", $account["id"]);
                $invalidate->execute();
                $invalidate->close();

                $expiresAt = date("Y-m-d H:i:s", time() + 3600);
                $insert = $conn->prepare("INSERT INTO admin_password_resets (admin_user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                if (!$insert) {
                    throw new RuntimeException("The recovery link could not be prepared.");
                }
                $insert->bind_param("iss", $account["id"], $tokenHash, $expiresAt);
                if (!$insert->execute()) {
                    throw new RuntimeException("The recovery link could not be saved.");
                }
                $insert->close();

                $path = (string) ($parsedBase["path"] ?? "");
                $resetUrl = $baseUrl . $path . "/admin/reset-password.php?token=" . rawurlencode($rawToken);
                $mailBody = "A password reset was requested for your E&R administrator account.\n\n"
                    . "Use this one-time link within one hour:\n{$resetUrl}\n\n"
                    . "If you did not request this, ignore this email. No password has been changed.\n";
                sendSiteNotification(
                    $account["email"],
                    "E&R administrator password reset",
                    $mailBody
                );
            } else {
                error_log("ER_PUBLIC_BASE_URL must be configured with HTTPS before password reset email can be sent.");
            }
        }
    }
    $notice = "If an active administrator account matches that email, a one-time reset link will be sent.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password recovery | E&amp;R Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="login-page">
<main class="recovery-shell">
    <section class="login-card">
        <a class="login-brand" href="../index.html"><img src="../logo/E&R Logo.jfif" alt="E&amp;R logo"><span><strong>E&amp;R</strong><small>Administration</small></span></a>
        <p class="eyebrow">Account recovery</p>
        <h1>Reset your password.</h1>
        <p class="login-copy">Enter your administrator email. If your account is active, we will send a one-time reset link.</p>
        <?php if ($error !== ""): ?><p class="login-message visible warning" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
        <?php if ($notice !== ""): ?><p class="login-message visible" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
            <label class="form-field">Email address<input name="email" type="email" autocomplete="email" maxlength="254" required></label>
            <button class="button button-primary login-submit" type="submit">Send reset link <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
        </form>
        <a class="login-reset-link" href="login.php">Return to sign in</a>
    </section>
</main>
</body>
</html>
