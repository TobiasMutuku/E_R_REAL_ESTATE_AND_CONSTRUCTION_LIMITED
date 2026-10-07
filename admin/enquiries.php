<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/admin-form.php";
require_once __DIR__ . "/../config/db.php";

$statuses = ["New", "Read", "Responded", "Closed"];
$token = adminFormToken();
$error = "";
$notice = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = filter_var(adminPostString("id"), FILTER_VALIDATE_INT);
    $source = adminPostString("source");
    $status = adminPostString("status");
    $ownerInput = adminPostString("assigned_admin_id");
    $followUpInput = trim(adminPostString("follow_up_at"));
    $ownerId = $ownerInput === "" ? null : filter_var($ownerInput, FILTER_VALIDATE_INT);
    $ownerIsAdmin = $ownerInput === "";
    if ($ownerId) {
        $ownerStatement = $conn->prepare("SELECT id FROM admin_users WHERE id = ? AND role = 'admin' LIMIT 1");
        $ownerStatement->bind_param("i", $ownerId);
        $ownerStatement->execute();
        $ownerIsAdmin = $ownerStatement->get_result()->num_rows === 1;
        $ownerStatement->close();
    }
    $followUpAt = null;
    if ($followUpInput !== "") {
        $followUpDate = DateTime::createFromFormat("Y-m-d\TH:i", $followUpInput);
        if ($followUpDate && $followUpDate->format("Y-m-d\TH:i") === $followUpInput) {
            $followUpAt = $followUpDate->format("Y-m-d H:i:s");
        }
    }
    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh and try again.";
    } elseif (!$id || !in_array($source, ["quote", "contact"], true) || !in_array($status, $statuses, true)
        || ($ownerInput !== "" && !$ownerId) || !$ownerIsAdmin || ($followUpInput !== "" && $followUpAt === null)) {
        $error = "The requested enquiry update is invalid.";
    } else {
        $table = $source === "quote" ? "quote_requests" : "enquiries";
        $statement = $conn->prepare("UPDATE {$table} SET status = ?, assigned_admin_id = ?, follow_up_at = ? WHERE id = ?");
        $statement->bind_param("sisi", $status, $ownerId, $followUpAt, $id);
        if (!$statement->execute()) {
            $error = "The enquiry follow-up details could not be saved.";
        } else {
            $notice = "Enquiry follow-up details saved.";
        }
        $statement->close();
    }
}

$adminsResult = $conn->query("SELECT id, full_name FROM admin_users WHERE role = 'admin' ORDER BY full_name");
if (!$adminsResult) {
    throw new RuntimeException("Administrator accounts could not be loaded.");
}
$admins = $adminsResult->fetch_all(MYSQLI_ASSOC);
$leads = $conn->query(
    "SELECT q.id, 'quote' AS source, q.full_name, q.email, q.phone, q.service AS category, q.location,
            q.message, q.attachment, q.status, q.assigned_admin_id, q.follow_up_at, q.created_at, a.full_name AS assigned_admin
     FROM quote_requests q
     LEFT JOIN admin_users a ON a.id = q.assigned_admin_id
     UNION ALL
     SELECT e.id, 'contact' AS source, e.full_name, e.email, e.phone, e.enquiry_type AS category,
            e.subject AS location, e.message, NULL AS attachment, e.status, e.assigned_admin_id, e.follow_up_at, e.created_at, a.full_name AS assigned_admin
     FROM enquiries e
     LEFT JOIN admin_users a ON a.id = e.assigned_admin_id
     ORDER BY created_at DESC"
);
if (!$leads) {
    throw new RuntimeException("Enquiries could not be loaded.");
}
$adminName = $_SESSION["admin_name"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enquiries | E&amp;R Administration</title>
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
            <a class="sidebar-link active" href="enquiries.php" aria-current="page">Enquiries</a>
            <a class="sidebar-link" href="settings.php">Site settings</a>
        </nav>
        <div class="sidebar-bottom"><a class="sidebar-link sidebar-signout" href="logout.php">Sign out</a></div>
    </aside>
    <main class="admin-main">
        <header class="admin-header"><div class="breadcrumbs"><span>Workspace</span><strong>Enquiries</strong></div><div class="user-chip"><strong><?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?></strong></div></header>
        <section class="admin-content">
            <div class="page-intro"><div><p class="eyebrow">Lead management</p><h1>Website enquiries</h1><p class="intro-copy">Track each quote and contact enquiry from receipt through follow-up and closure.</p></div></div>
            <?php if ($error !== ""): ?><p class="saved-state" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <?php if ($notice !== ""): ?><p class="saved-state" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <?php if ($leads->num_rows === 0): ?><section class="panel settings-panel"><p>No website enquiries have been received.</p></section><?php endif; ?>
            <?php while ($lead = $leads->fetch_assoc()): ?>
                <article class="panel settings-panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow"><?= $lead["source"] === "quote" ? "Quote request" : "Contact enquiry" ?> · <?= htmlspecialchars($lead["category"], ENT_QUOTES, "UTF-8") ?></p><h2><?= htmlspecialchars($lead["full_name"], ENT_QUOTES, "UTF-8") ?></h2><p><?= htmlspecialchars($lead["location"] ?? "", ENT_QUOTES, "UTF-8") ?> · <?= htmlspecialchars($lead["created_at"], ENT_QUOTES, "UTF-8") ?></p></div>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="source" value="<?= htmlspecialchars($lead["source"], ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="id" value="<?= (int) $lead["id"] ?>">
                            <label>Status<select name="status"><?php foreach ($statuses as $status): ?><option <?= $status === $lead["status"] ? "selected" : "" ?>><?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                            <label>Assigned administrator<select name="assigned_admin_id"><option value="">Unassigned</option><?php foreach ($admins as $admin): ?><option value="<?= (int) $admin["id"] ?>" <?= (int) $admin["id"] === (int) ($lead["assigned_admin_id"] ?? 0) ? "selected" : "" ?>><?= htmlspecialchars($admin["full_name"], ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                            <label>Follow up by<input type="datetime-local" name="follow_up_at" value="<?= $lead["follow_up_at"] ? htmlspecialchars(date("Y-m-d\\TH:i", strtotime($lead["follow_up_at"])), ENT_QUOTES, "UTF-8") : "" ?>"></label>
                            <button class="button button-primary" type="submit">Save follow-up</button>
                        </form>
                    </div>
                    <p><a href="mailto:<?= rawurlencode($lead["email"]) ?>"><?= htmlspecialchars($lead["email"], ENT_QUOTES, "UTF-8") ?></a><?php if ($lead["phone"] !== ""): ?> · <a href="tel:<?= htmlspecialchars(preg_replace('/[^\d+]/', '', $lead["phone"]), ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($lead["phone"], ENT_QUOTES, "UTF-8") ?></a><?php endif; ?> · Assigned to <?= htmlspecialchars($lead["assigned_admin"] ?: "no one", ENT_QUOTES, "UTF-8") ?> · Follow up <?= $lead["follow_up_at"] ? htmlspecialchars($lead["follow_up_at"], ENT_QUOTES, "UTF-8") : "not scheduled" ?></p>
                    <p><?= nl2br(htmlspecialchars($lead["message"], ENT_QUOTES, "UTF-8")) ?></p>
                    <?php if ($lead["attachment"]): ?><p><a href="quote-attachment.php?id=<?= (int) $lead["id"] ?>">Download attached plans or photos</a></p><?php endif; ?>
                </article>
            <?php endwhile; ?>
        </section>
    </main>
</div>
</body>
</html>
