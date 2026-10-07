<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/admin-form.php";
require_once __DIR__ . "/../includes/site-settings.php";
require_once __DIR__ . "/../config/db.php";

$settings = loadSiteSettings($conn);
$token = adminFormToken();
$error = "";
$saved = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($settings as $key => $_value) {
        $submitted = $_POST[$key] ?? "";
        $settings[$key] = is_string($submitted) ? trim($submitted) : "";
    }

    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh and try again.";
    } elseif ($settings["company_name"] === "" || mb_strlen($settings["company_name"]) > 150
        || $settings["office_address"] === "" || mb_strlen($settings["office_address"]) > 255) {
        $error = "Enter a company name and office address within the stated limits.";
    } elseif (!preg_match('/^\+?[0-9\s().-]{7,30}$/', $settings["public_phone"])) {
        $error = "Enter a valid public phone number.";
    } elseif (!filter_var($settings["public_email"], FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid public email address.";
    } elseif (!preg_match('/^[1-9][0-9]{7,14}$/', $settings["whatsapp_phone"])) {
        $error = "Enter the WhatsApp number in international digits-only format, without the plus sign.";
    } else {
        saveSiteSettings($conn, $settings);
        $saved = true;
    }
}
$adminName = $_SESSION["admin_name"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Site settings | E&amp;R Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="sidebar-brand" href="dashboard.php"><img src="../logo/E&R Logo.jfif" alt=""><span><strong>E&amp;R</strong><span>Administration</span></span></a>
        <p class="workspace-label">Workspace</p>
        <nav class="sidebar-nav" aria-label="Administration navigation">
            <a class="sidebar-link" href="dashboard.php">Dashboard</a>
            <a class="sidebar-link" href="../projects.php">Projects</a>
            <a class="sidebar-link" href="content-review.php">Content review</a>
            <a class="sidebar-link" href="articles.php">Editorial CMS</a>
            <a class="sidebar-link" href="enquiries.php">Enquiries</a>
            <a class="sidebar-link active" href="settings.php" aria-current="page">Site settings</a>
        </nav>
        <div class="sidebar-bottom"><a class="sidebar-link sidebar-signout" href="logout.php">Sign out</a></div>
    </aside>
    <main class="admin-main">
        <header class="admin-header"><div class="breadcrumbs"><span>Workspace</span><strong>Site settings</strong></div><div class="user-chip"><strong><?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?></strong></div></header>
        <section class="admin-content">
            <div class="page-intro"><div><p class="eyebrow">Public information</p><h1>Site-wide settings</h1><p class="intro-copy">Update the contact details displayed across the public site from one place.</p></div></div>
            <?php if ($error !== ""): ?><p class="saved-state" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <?php if ($saved): ?><p class="saved-state" role="status">Site settings saved.</p><?php endif; ?>
            <section class="panel settings-panel">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                    <div class="form-grid">
                        <label>Company name<input name="company_name" maxlength="150" required value="<?= htmlspecialchars($settings["company_name"], ENT_QUOTES, "UTF-8") ?>"></label>
                        <label>Public phone<input name="public_phone" maxlength="30" required value="<?= htmlspecialchars($settings["public_phone"], ENT_QUOTES, "UTF-8") ?>"></label>
                        <label>Public email<input name="public_email" type="email" maxlength="254" required value="<?= htmlspecialchars($settings["public_email"], ENT_QUOTES, "UTF-8") ?>"></label>
                        <label>WhatsApp number<input name="whatsapp_phone" inputmode="numeric" maxlength="15" required value="<?= htmlspecialchars($settings["whatsapp_phone"], ENT_QUOTES, "UTF-8") ?>"><small>Country code and number, digits only.</small></label>
                        <label class="full-width">Office address<textarea name="office_address" maxlength="255" rows="3" required><?= htmlspecialchars($settings["office_address"], ENT_QUOTES, "UTF-8") ?></textarea></label>
                        <div class="settings-actions"><span>Changes update public contact links after page refresh.</span><button class="button button-primary" type="submit">Save settings</button></div>
                    </div>
                </form>
            </section>
        </section>
    </main>
</div>
</body>
</html>
