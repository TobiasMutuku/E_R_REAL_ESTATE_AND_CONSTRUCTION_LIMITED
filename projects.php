<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/admin-form.php";
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/admin/projects/project-media.php";
ensure_project_media_table($conn);

$sql = "SELECT id, project_name, location, description, project_status, start_date, completion_date, image, is_published
        FROM projects
        ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result === false) {
    die("Error retrieving projects: " . htmlspecialchars($conn->error, ENT_QUOTES, "UTF-8"));
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";
$csrf_token = adminFormToken();
$project_count = $result->num_rows;
$project_updated = isset($_GET["project"]) && $_GET["project"] === "updated";
$project_deleted = isset($_GET["project"]) && $_GET["project"] === "deleted";
$delete_error = isset($_GET["project"]) && $_GET["project"] === "delete-error";

function project_status_class(string $status): string
{
    return match ($status) {
        "Ongoing" => "ongoing",
        "Completed" => "completed",
        default => "pending",
    };
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Manage E&R Real Estate and Construction Limited projects">
    <title>Projects | E&amp;R Administration</title>
    <link rel="icon" href="logo/E&R Logo.jfif">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="sidebar-brand" href="admin/dashboard.php">
                <img src="logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo">
                <span><strong>E&amp;R</strong><span>Administration</span></span>
            </a>

            <p class="workspace-label">Workspace</p>
            <nav class="sidebar-nav" aria-label="Administration navigation">
                <a class="sidebar-link" href="admin/dashboard.php"><i class="fas fa-chart-pie" aria-hidden="true"></i> Dashboard</a>
                <a class="sidebar-link" href="admin/projects/add-project.php"><i class="fas fa-plus" aria-hidden="true"></i> Add project</a>
                <a class="sidebar-link active" href="projects.php" aria-current="page"><i class="fas fa-building" aria-hidden="true"></i> Projects</a>
                <a class="sidebar-link" href="admin/content-review.php"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Content review</a>
                <a class="sidebar-link" href="admin/articles.php"><i class="fas fa-newspaper" aria-hidden="true"></i> Editorial CMS</a>
                <a class="sidebar-link" href="admin/enquiries.php"><i class="fas fa-inbox" aria-hidden="true"></i> Enquiries</a>
                <a class="sidebar-link" href="admin/settings.php"><i class="fas fa-cog" aria-hidden="true"></i> Site settings</a>
                <a class="sidebar-link" href="constructions.html"><i class="fas fa-globe" aria-hidden="true"></i> Public website</a>
            </nav>

            <div class="sidebar-bottom">
                <a class="sidebar-link sidebar-signout" href="admin/logout.php"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign out</a>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="breadcrumbs"><span>Workspace</span><i class="fas fa-chevron-right" aria-hidden="true"></i><strong>Projects</strong></div>
                <div class="header-actions">
                    <div class="user-chip">
                        <span class="avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1)), ENT_QUOTES, "UTF-8") ?></span>
                        <span class="user-details"><strong><?= htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8") ?></strong><small>Administrator</small></span>
                    </div>
                </div>
            </header>

            <section class="admin-content">
                <?php if ($project_updated): ?>
                    <p class="saved-state" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> Project updated successfully.</p>
                <?php endif; ?>
                <?php if ($project_deleted): ?>
                    <p class="saved-state" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> Project deleted successfully.</p>
                <?php endif; ?>
                <?php if ($delete_error): ?>
                    <p class="saved-state" role="alert" style="color: #8a3f35; background: #f8e9e5;"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> The project could not be deleted.</p>
                <?php endif; ?>

                <div class="page-intro">
                    <div>
                        <p class="eyebrow">Project management</p>
                        <h1>Saved projects</h1>
                        <p class="intro-copy">Review the projects currently connected to the public website.</p>
                    </div>
                    <a class="button button-primary" href="admin/projects/add-project.php"><i class="fas fa-plus" aria-hidden="true"></i> Add project</a>
                </div>

                <?php if ($project_count > 0): ?>
                    <div class="project-admin-grid">
                        <?php while ($project = $result->fetch_assoc()): ?>
                            <article class="project-admin-card">
                                <?php if (!empty($project["image"])): ?>
                                    <img class="project-admin-image" src="<?= htmlspecialchars($project["image"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($project["project_name"], ENT_QUOTES, "UTF-8") ?> project image" loading="lazy">
                                <?php else: ?>
                                    <div class="project-admin-image is-empty" role="img" aria-label="No image uploaded"><i class="fas fa-image" aria-hidden="true"></i></div>
                                <?php endif; ?>
                                <div class="project-admin-body">
                                    <h2><?= htmlspecialchars($project["project_name"], ENT_QUOTES, "UTF-8") ?></h2>
                                    <p class="project-admin-location"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><?= htmlspecialchars($project["location"], ENT_QUOTES, "UTF-8") ?></p>
                                    <p class="project-admin-description"><?= htmlspecialchars($project["description"] ?: "No description added yet.", ENT_QUOTES, "UTF-8") ?></p>
                                    <div class="project-admin-meta">
                                        <span class="status-tag <?= htmlspecialchars(project_status_class($project["project_status"]), ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($project["project_status"], ENT_QUOTES, "UTF-8") ?></span>
                                        <span class="status-tag <?= $project["is_published"] ? "completed" : "pending" ?>"><?= $project["is_published"] ? "Published" : "Draft" ?></span>
                                        <span><?= $project["start_date"] ? "Started " . htmlspecialchars($project["start_date"], ENT_QUOTES, "UTF-8") : "No start date" ?></span>
                                    </div>
                                    <div class="project-card-actions">
                                        <?php if ($project["is_published"]): ?><a class="button button-quiet" href="project-details.php?id=<?= (int) $project["id"] ?>"><i class="fas fa-eye" aria-hidden="true"></i> View details</a><?php endif; ?>
                                        <a class="button button-quiet" href="admin/projects/edit-project.php?id=<?= (int) $project["id"] ?>"><i class="fas fa-pen" aria-hidden="true"></i> Edit</a>
                                        <form method="POST" action="admin/projects/delete-project.php" onsubmit="return confirm('Delete this project and its uploaded image?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                                            <input type="hidden" name="id" value="<?= (int) $project["id"] ?>">
                                            <button class="button button-danger" type="submit"><i class="fas fa-trash-alt" aria-hidden="true"></i> Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-projects">
                        <i class="fas fa-building" aria-hidden="true"></i>
                        <strong>No projects saved yet.</strong>
                        <p>Create your first project to see it here.</p>
                        <a class="button button-primary" href="admin/projects/add-project.php"><i class="fas fa-plus" aria-hidden="true"></i> Add project</a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>
