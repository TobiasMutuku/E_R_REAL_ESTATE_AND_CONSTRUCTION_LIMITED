<?php
require_once __DIR__ . "/../includes/admin-session.php";
require_once __DIR__ . "/../includes/admin-form.php";
require_once __DIR__ . "/../config/db.php";
startAdminSession();

$rawToken = trim((string) ($_GET["token"] ?? $_POST["token"] ?? ""));
$tokenHash = preg_match('/\A[a-f0-9]{64}\z/', $rawToken) ? hash("sha256", $rawToken) : "";
$account = null;
$tokenId = 0;
if ($tokenHash !== "") {
    $statement = $conn->prepare(
        "SELECT r.id, r.admin_user_id, u.email
         FROM admin_password_resets r
         JOIN admin_users u ON u.id = r.admin_user_id
         WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_active = 1
         LIMIT 1"
    );
    if (!$statement) {
        throw new RuntimeException("Password reset could not be checked.");
    }
    $statement->bind_param("s", $tokenHash);
    if (!$statement->execute()) {
        throw new RuntimeException("Password reset could not be checked.");
    }
    $account = $statement->get_result()->fetch_assoc();
    $statement->close();
    if ($account) {
        $tokenId = (int) $account["id"];
    }
}

$formToken = adminFormToken();
$error = "";
$changed = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = adminPostString("password");
    $confirm = adminPostString("confirm_password");
    if (!adminFormIsValid()) {
        $error = "Your session expired. Refresh this page and try again.";
    } elseif (!$account) {
        $error = "This reset link is invalid, expired, or already used. Request a new link.";
    } elseif (strlen($password) < 12 || strlen($password) > 200) {
        $error = "Choose a password between 12 and 200 characters.";
    } elseif (!hash_equals($password, $confirm)) {
        $error = "The password confirmation does not match.";
    } else {
        $conn->begin_transaction();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ? AND is_active = 1");
        $consume = $conn->prepare("UPDATE admin_password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
        if (!$update || !$consume) {
            $conn->rollback();
            throw new RuntimeException("The password reset could not be prepared.");
        }
        $update->bind_param("si", $passwordHash, $account["admin_user_id"]);
        $consume->bind_param("i", $tokenId);
        if (!$update->execute() || $update->affected_rows !== 1 || !$consume->execute() || $consume->affected_rows !== 1) {
            $update->close();
            $consume->close();
            $conn->rollback();
            $error = "This reset link could not be used. Request a new link.";
        } else {
            $update->close();
            $consume->close();
            $audit = $conn->prepare(
                "INSERT INTO admin_audit_logs
                    (actor_admin_id, actor_name, actor_role, action, entity_type, entity_id, details, remote_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$audit) {
                $conn->rollback();
                throw new RuntimeException("The password reset audit event could not be prepared.");
            }
            $actorId = null;
            $actorName = "Self-service recovery";
            $actorRole = "system";
            $action = "password_reset";
            $entity = "admin_user";
            $entityId = (int) $account["admin_user_id"];
            $details = null;
            $remoteAddress = filter_var($_SERVER["REMOTE_ADDR"] ?? "", FILTER_VALIDATE_IP) ?: null;
            $audit->bind_param("issssiss", $actorId, $actorName, $actorRole, $action, $entity, $entityId, $details, $remoteAddress);
            if (!$audit->execute()) {
                $audit->close();
                $conn->rollback();
                throw new RuntimeException("The password reset audit event could not be saved.");
            }
            $audit->close();
            $conn->commit();
            $account = null;
            $changed = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose a new password | E&amp;R Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="login-page">
<main class="recovery-shell">
    <section class="login-card">
        <a class="login-brand" href="../index.html"><img src="../logo/E&R Logo.jfif" alt="E&amp;R logo"><span><strong>E&amp;R</strong><small>Administration</small></span></a>
        <p class="eyebrow">Account recovery</p>
        <h1>Choose a new password.</h1>
        <?php if ($changed): ?>
            <p class="login-message visible" role="status">Your password has been reset. You can now sign in with your new password.</p>
            <a class="button button-primary login-submit" href="login.php">Return to sign in</a>
        <?php elseif (!$account): ?>
            <p class="login-message visible warning" role="alert">This reset link is invalid, expired, or already used. Request a new link.</p>
            <a class="button button-primary login-submit" href="forgot-password.php">Request another link</a>
        <?php else: ?>
            <p class="login-copy">Reset link for <?= htmlspecialchars($account["email"], ENT_QUOTES, "UTF-8") ?>. Choose a unique password with at least 12 characters.</p>
            <?php if ($error !== ""): ?><p class="login-message visible warning" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($formToken, ENT_QUOTES, "UTF-8") ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken, ENT_QUOTES, "UTF-8") ?>">
                <label class="form-field">New password<input name="password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required></label>
                <label class="form-field">Confirm password<input name="confirm_password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required></label>
                <button class="button button-primary login-submit" type="submit">Save new password <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
