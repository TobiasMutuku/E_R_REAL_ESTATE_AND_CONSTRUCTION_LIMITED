<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

$project_count = 0;
$property_count = 0;
$enquiry_count = 0;
$new_enquiry_count = 0;

$sql = "SELECT COUNT(*) AS total FROM projects";
$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    $project_count = (int) $row["total"];
}
$result = $conn->query("SELECT COUNT(*) AS total FROM properties");
if ($result) {
    $property_count = (int) $result->fetch_assoc()["total"];
}
$result = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM enquiries) + (SELECT COUNT(*) FROM quote_requests) AS total,
        (SELECT COUNT(*) FROM enquiries WHERE status = 'New') +
        (SELECT COUNT(*) FROM quote_requests WHERE status = 'New') AS new_total"
);
if ($result) {
    $counts = $result->fetch_assoc();
    $enquiry_count = (int) $counts["total"];
    $new_enquiry_count = (int) $counts["new_total"];
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";
$admin_role = $_SESSION["admin_role"] ?? "Administrator";
$project_created = isset($_GET["project"]) && $_GET["project"] === "created";
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
        <aside class="admin-sidebar">
            <a class="sidebar-brand" href="dashboard.php">
                <img src="../logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo">
                <span><strong>E&amp;R</strong><span>Administration</span></span>
            </a>

            <p class="workspace-label">Workspace</p>
            <nav class="sidebar-nav" aria-label="Administration navigation">
                <a class="sidebar-link active" href="dashboard.php" aria-current="page"><i class="fas fa-chart-pie" aria-hidden="true"></i> Dashboard</a>
                <a class="sidebar-link" href="../projects.php"><i class="fas fa-building" aria-hidden="true"></i> Projects</a>
                <a class="sidebar-link" href="content-review.php"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Content review</a>
                <a class="sidebar-link" href="articles.php"><i class="fas fa-newspaper" aria-hidden="true"></i> Editorial CMS</a>
                <a class="sidebar-link" href="enquiries.php"><i class="fas fa-inbox" aria-hidden="true"></i> Enquiries<?php if ($new_enquiry_count > 0): ?> <span class="nav-count"><?= $new_enquiry_count ?></span><?php endif; ?></a>
                <a class="sidebar-link" href="settings.php"><i class="fas fa-cog" aria-hidden="true"></i> Site settings</a>
                <a class="sidebar-link" href="../constructions.html"><i class="fas fa-globe" aria-hidden="true"></i> Public website</a>
            </nav>

            <div class="sidebar-bottom">
                <a class="sidebar-link sidebar-signout" href="logout.php"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign out</a>
            </div>
        </aside>

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

                <div class="page-intro">
                    <div>
                        <p class="eyebrow">Overview</p>
                        <?php

date_default_timezone_set('Africa/Nairobi');

$current_hour = (int) date('H');

if ($current_hour >= 00 && $current_hour < 12) {
    $greeting = "Good morning";
} elseif ($current_hour >= 12 && $current_hour < 17) {
    $greeting = "Good afternoon";
} elseif ($current_hour >= 17 && $current_hour < 0) {
    $greeting = "Good evening";
} else {
    $greeting = "Good night";
}

?>

<h1>
    <?= $greeting ?>,
    <?= htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8") ?>.
</h1>
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
                                <div><strong><?= $project_count ?> project<?= $project_count === 1 ? "" : "s" ?> available</strong><p>Project records are connected to the MySQL database.</p><small>Live database count</small></div>
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
                        <a class="quick-action" href="../constructions.html"><span class="quick-icon blue"><i class="fas fa-external-link-alt" aria-hidden="true"></i></span><span><strong>Preview public site</strong><small>Open the visitor-facing project portfolio</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                    </section>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
