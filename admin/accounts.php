<?php
require_once __DIR__ . "/../includes/auth.php";
requireAdminPermission("accounts");
require_once __DIR__ . "/../includes/admin-form.php";
require_once __DIR__ . "/../includes/admin-audit.php";
require_once __DIR__ . "/../includes/admin-ui.php";
require_once __DIR__ . "/../config/db.php";

$token = adminFormToken();
$error = "";
$notice = "";
$roles = [
    "admin" => "Administrator — full system access",
    "content_editor" => "Content editor — projects and published content",
    "enquiry_manager" => "Enquiry manager — leads and follow-up",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = adminPostString("action");
    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh and try again.";
    } elseif ($action === "create") {
        $name = trim(adminPostString("full_name"));
        $email = strtolower(trim(adminPostString("email")));
        $password = adminPostString("password");
        $role = adminPostString("role");
        if ($name === "" || mb_strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 12 || strlen($password) > 200 || !isset($roles[$role])) {
            $error = "Enter a valid name and email, a password of at least 12 characters, and an allowed role.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $statement = $conn->prepare("INSERT INTO admin_users (full_name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
            if (!$statement) {
                throw new RuntimeException("The administrator account could not be prepared.");
            }
            $statement->bind_param("ssss", $name, $email, $passwordHash, $role);
            if ($statement->execute()) {
                $newId = (int) $conn->insert_id;
                try {
                    recordAdminAudit($conn, "create_account", "admin_user", $newId, ["role" => $role]);
                    $notice = "Administrator account created.";
                } catch (RuntimeException $exception) {
                    error_log($exception->getMessage());
                    $notice = "Account created, but its audit event could not be recorded. Check server logs before continuing.";
                }
            } else {
                $error = $statement->errno === 1062
                    ? "An account with that email already exists."
                    : "The administrator account could not be created.";
            }
            $statement->close();
        }
    } elseif ($action === "update") {
        $id = filter_var(adminPostString("id"), FILTER_VALIDATE_INT);
        $role = adminPostString("role");
        $active = adminPostString("is_active") === "1" ? 1 : 0;
        if (!$id || !isset($roles[$role])) {
            $error = "Choose a valid administrator and role.";
        } elseif ((int) $id === (int) $_SESSION["admin_id"] && ($role !== "admin" || $active !== 1)) {
            $error = "You cannot remove administrator access from your own active session.";
        } else {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException("Unable to begin the administrator access update.");
            }
            $activeAdmins = $conn->query("SELECT id FROM admin_users WHERE role = 'admin' AND is_active = 1 ORDER BY id FOR UPDATE");
            if (!$activeAdmins) {
                $conn->rollback();
                throw new RuntimeException("Unable to lock active administrator accounts.");
            }
            $activeAdminCount = $activeAdmins->num_rows;
            $current = $conn->prepare("SELECT role, is_active FROM admin_users WHERE id = ? LIMIT 1 FOR UPDATE");
            if (!$current) {
                $conn->rollback();
                throw new RuntimeException("Unable to lock the administrator account for update.");
            }
            $current->bind_param("i", $id);
            $current->execute();
            $existing = $current->get_result()->fetch_assoc();
            $current->close();
            if (!$existing) {
                $error = "That administrator account no longer exists.";
            } elseif ($existing["role"] === "admin" && (int) $existing["is_active"] === 1 && ($role !== "admin" || $active !== 1)) {
                if ($activeAdminCount <= 1) {
                    $error = "At least one active administrator must remain.";
                }
            }
            if ($error === "") {
                $statement = $conn->prepare("UPDATE admin_users SET role = ?, is_active = ? WHERE id = ?");
                if (!$statement) {
                    $conn->rollback();
                    throw new RuntimeException("Unable to prepare the administrator access update.");
                }
                $statement->bind_param("sii", $role, $active, $id);
                if ($statement->execute() && $conn->commit()) {
                    try {
                        recordAdminAudit($conn, "update_account_access", "admin_user", (int) $id, ["role" => $role, "is_active" => $active]);
                        $notice = "Account access updated.";
                    } catch (RuntimeException $exception) {
                        error_log($exception->getMessage());
                        $notice = "Account access updated, but its audit event could not be recorded. Check server logs.";
                    }
                } else {
                    $conn->rollback();
                    $error = "The account access update could not be saved.";
                }
                $statement->close();
            } else {
                $conn->rollback();
            }
        }
    } else {
        $error = "Invalid account action.";
    }
}

$accounts = $conn->query("SELECT id, full_name, email, role, is_active, last_login_at, created_at FROM admin_users ORDER BY full_name");
if (!$accounts) {
    throw new RuntimeException("Administrator accounts could not be loaded.");
}
$adminName = $_SESSION["admin_name"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Team access | E&amp;R Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="admin-shell">
    <?php renderAdminSidebar("accounts"); ?>
    <main class="admin-main">
        <header class="admin-header"><div class="breadcrumbs"><span>Workspace</span><strong>Team access</strong></div><div class="user-chip"><strong><?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?></strong></div></header>
        <section class="admin-content">
            <div class="page-intro"><div><p class="eyebrow">Security and access</p><h1>Administrator accounts</h1><p class="intro-copy">Give each person only the access needed for their work. Passwords are stored as secure hashes.</p></div></div>
            <?php if ($error !== ""): ?><p class="saved-state warning-state" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <?php if ($notice !== ""): ?><p class="saved-state" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <section class="panel settings-panel">
                <div class="panel-heading"><div><p class="eyebrow">Invite</p><h2>Create an account</h2></div></div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>"><input type="hidden" name="action" value="create">
                    <label>Full name<input name="full_name" maxlength="150" autocomplete="name" required></label>
                    <label>Email address<input name="email" type="email" maxlength="254" autocomplete="email" required></label>
                    <label>Temporary password<input name="password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required><small>At least 12 characters. Ask the new account holder to reset it after first sign-in.</small></label>
                    <label>Access role<select name="role"><?php foreach ($roles as $role => $label): ?><option value="<?= htmlspecialchars($role, ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($label, ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                    <div class="settings-actions"><span>Never share one administrator account between people.</span><button class="button button-primary" type="submit">Create account</button></div>
                </form>
            </section>
            <section class="panel table-panel account-table-panel">
                <div class="table-toolbar"><div><strong>Team members</strong><p class="table-meta">Deactivated users lose access immediately.</p></div><span class="table-meta"><?= $accounts->num_rows ?> accounts</span></div>
                <div class="table-wrap"><table><thead><tr><th>Account</th><th>Last sign in</th><th>Role and status</th><th>Save</th></tr></thead><tbody>
                    <?php while ($account = $accounts->fetch_assoc()): ?>
                        <?php $accountFormId = "account-form-" . (int) $account["id"]; ?>
                        <tr><td><strong><?= htmlspecialchars($account["full_name"], ENT_QUOTES, "UTF-8") ?></strong><small><?= htmlspecialchars($account["email"], ENT_QUOTES, "UTF-8") ?></small></td>
                            <td><?= $account["last_login_at"] ? htmlspecialchars($account["last_login_at"], ENT_QUOTES, "UTF-8") : "Never" ?></td>
                            <td colspan="2"><form id="<?= $accountFormId ?>" method="post" class="account-inline-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $account["id"] ?>">
                                <label class="sr-only" for="role-<?= (int) $account["id"] ?>">Role for <?= htmlspecialchars($account["full_name"], ENT_QUOTES, "UTF-8") ?></label><select id="role-<?= (int) $account["id"] ?>" name="role"><?php foreach ($roles as $role => $label): ?><option value="<?= htmlspecialchars($role, ENT_QUOTES, "UTF-8") ?>" <?= $account["role"] === $role ? "selected" : "" ?>><?= htmlspecialchars(str_replace("_", " ", $role), ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select>
                                <label class="remember-option"><input type="checkbox" name="is_active" value="1" <?= (int) $account["is_active"] === 1 ? "checked" : "" ?>> Active</label>
                                <button class="button button-quiet" type="submit">Save access</button></form></td></tr>
                    <?php endwhile; ?>
                </tbody></table></div>
            </section>
        </section>
    </main>
</div>
<script src="../js/admin-shell.js" defer></script>
</body>
</html>
