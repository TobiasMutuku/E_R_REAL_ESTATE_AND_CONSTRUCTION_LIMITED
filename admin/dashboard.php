<?php

require_once __DIR__ . "/../includes/auth.php";
requireAdminPermission("dashboard");
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/admin-ui.php";

$project_count = 0;
$published_project_count = 0;
$property_count = 0;
$academy_enquiry_count = 0;
$enquiry_count = 0;
$new_enquiry_count = 0;

$sql = "SELECT COUNT(*) AS total, SUM(is_published = 1) AS published FROM projects";
$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    $project_count = (int) $row["total"];
    $published_project_count = (int) $row["published"];
}
$result = $conn->query("SELECT COUNT(*) AS total FROM properties");
if ($result) {
    $property_count = (int) $result->fetch_assoc()["total"];
}
$result = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM enquiries) + (SELECT COUNT(*) FROM quote_requests) +
        (SELECT COUNT(*) FROM academy_enquiries) AS total,
        (SELECT COUNT(*) FROM enquiries WHERE status = 'New') +
        (SELECT COUNT(*) FROM quote_requests WHERE status = 'New') +
        (SELECT COUNT(*) FROM academy_enquiries WHERE status = 'New') AS new_total,
        (SELECT COUNT(*) FROM academy_enquiries) AS academy_total"
);
if ($result) {
    $counts = $result->fetch_assoc();
    $enquiry_count = (int) $counts["total"];
    $new_enquiry_count = (int) $counts["new_total"];
    $academy_enquiry_count = (int) $counts["academy_total"];
}

$article_result = $conn->query("SELECT COUNT(*) AS total FROM articles WHERE is_published = 1");
$published_article_count = $article_result ? (int) $article_result->fetch_assoc()["total"] : 0;

$admin_name = $_SESSION["admin_name"] ?? "Administrator";
$admin_role = $_SESSION["admin_role"] ?? "Administrator";
$project_created = isset($_GET["project"]) && $_GET["project"] === "created";
$audit_failed = isset($_GET["audit"]) && $_GET["audit"] === "failed";
$initials = "A";

if ($admin_name !== "") {
    $name_parts = preg_split('/\s+/', trim($admin_name));
    $initials = strtoupper(substr($name_parts[0], 0, 1));

    if (count($name_parts) > 1) {
        $initials .= strtoupper(substr($name_parts[count($name_parts) - 1], 0, 1));
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="E&R Real Estate and Construction Limited administrator dashboard">
    <title>Dashboard | E&amp;R Administration</title>
    <link rel="icon" href="../logo/E&R Logo.jfif">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-shell">
        <?php renderAdminSidebar("dashboard"); ?>

        <main class="admin-main">
            <header class="admin-header">
                <div class="breadcrumbs"><span>Workspace</span><i class="fas fa-chevron-right" aria-hidden="true"></i><strong>Dashboard</strong></div>
                <div class="header-actions">
                    <div class="user-chip">
                        <span class="avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, "UTF-8") ?></span>
                        <span class="user-details"><strong><?= htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8") ?></strong><small><?= htmlspecialchars($admin_role, ENT_QUOTES, "UTF-8") ?></small></span>
                    </div>
                </div>
            </header>

            <section class="admin-content">
                <?php if ($project_created): ?>
                    <p class="saved-state" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> Project added successfully.</p>
                <?php endif; ?>
                <?php if ($audit_failed): ?><p class="saved-state warning-state" role="alert">The project was saved, but its audit event could not be recorded. Check the server logs before continuing.</p><?php endif; ?>

                <div class="page-intro">
                    <div>
                        <p class="eyebrow">Overview</p>
                        <?php
                        $current_hour = (int) date("G");
                        $greeting = $current_hour < 12
                            ? "Good morning"
                            : ($current_hour < 17 ? "Good afternoon" : "Good evening");
                        ?>
                        <h1><?= $greeting ?>, <?= htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8") ?>.</h1>
                        <p class="intro-copy">Keep the public E&amp;R website current from one workspace.</p>
                    </div>
                    <div class="header-actions">
                        <a class="button button-quiet" href="projects/add-project.php"><i class="fas fa-plus" aria-hidden="true"></i> Add project</a>
                        <a class="button button-primary" href="../projects.php"><i class="fas fa-building" aria-hidden="true"></i> View projects</a>
                    </div>
                </div>

                <div class="stat-grid">
                    <article class="stat-card">
                        <span class="stat-icon stat-icon-green"><i class="fas fa-building" aria-hidden="true"></i></span>
                        <div><span>Total Projects</span><strong><?= $project_count ?></strong><small class="stat-trend"></small></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-icon stat-icon-brass"><i class="fas fa-home" aria-hidden="true"></i></span>
                        <div><span>Total Properties</span><strong><?= $property_count ?></strong><small class="stat-trend">Saved records</small></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-icon stat-icon-blue"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                        <div><span>Total Enquiries</span><strong><?= $enquiry_count ?></strong><small class="stat-trend"><?= $new_enquiry_count ?> new</small></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-icon stat-icon-coral"><i class="fas fa-briefcase" aria-hidden="true"></i></span>
                        <div><span>New Enquiries</span><strong><?= $new_enquiry_count ?></strong><small class="stat-trend">Follow-up required</small></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-icon stat-icon-blue"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span>
                        <div><span>Academy enquiries</span><strong><?= $academy_enquiry_count ?></strong><small class="stat-trend">Saved for follow-up</small></div>
                    </article>
                </div>

                <div class="content-grid">
                    <section class="panel activity-panel">
                        <div class="panel-heading">
                            <div><p class="eyebrow">Content</p><h2>Project management</h2></div>
                            <a class="text-button" href="../projects.php">Open list <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                        </div>
                        <div class="activity-list">
                            <div class="activity-item">
                                <span class="activity-icon green"><i class="fas fa-database" aria-hidden="true"></i></span>
                                <div><strong><?= $published_project_count ?> published project<?= $published_project_count === 1 ? "" : "s" ?></strong><p><?= $project_count ?> project records are stored in MySQL.</p><small>Only reviewed work is visible publicly</small></div>
                            </div>
                            <div class="activity-item">
                                <span class="activity-icon brass"><i class="fas fa-lock" aria-hidden="true"></i></span>
                                <div><strong>Protected administrator area</strong><p>Your session is authenticated before this dashboard loads.</p><small>Access control enabled</small></div>
                            </div>
                        </div>
                    </section>

                    <section class="panel quick-panel">
                        <div class="panel-heading"><div><p class="eyebrow">Shortcuts</p><h2>Quick actions</h2></div></div>
                        <a class="quick-action" href="../projects.php"><span class="quick-icon green"><i class="fas fa-list" aria-hidden="true"></i></span><span><strong>Manage projects</strong><small>Review published project records</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        <a class="quick-action" href="content-review.php"><span class="quick-icon brass"><i class="fas fa-clipboard-check" aria-hidden="true"></i></span><span><strong>Review content</strong><small>Publish verified project and property records</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        <a class="quick-action" href="articles.php"><span class="quick-icon brass"><i class="fas fa-newspaper" aria-hidden="true"></i></span><span><strong>Manage insights</strong><small>Write and publish reviewed articles</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        <a class="quick-action" href="enquiries.php"><span class="quick-icon blue"><i class="fas fa-inbox" aria-hidden="true"></i></span><span><strong>Track enquiries</strong><small>Update lead status and follow-up</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        <?php if (adminCan("accounts")): ?>
                            <a class="quick-action" href="accounts.php"><span class="quick-icon green"><i class="fas fa-user-shield" aria-hidden="true"></i></span><span><strong>Manage team access</strong><small>Assign roles and disable accounts</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                            <a class="quick-action" href="audit-log.php"><span class="quick-icon blue"><i class="fas fa-clipboard-list" aria-hidden="true"></i></span><span><strong>Review audit trail</strong><small>See recent administrator activity</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <a class="quick-action" href="../constructions.html"><span class="quick-icon blue"><i class="fas fa-external-link-alt" aria-hidden="true"></i></span><span><strong>Preview public site</strong><small>Open the visitor-facing project portfolio</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                    </section>
                </div>
            </section>
        </main>
    </div>
    <script src="../js/admin-shell.js" defer></script>
</body>
</html>
