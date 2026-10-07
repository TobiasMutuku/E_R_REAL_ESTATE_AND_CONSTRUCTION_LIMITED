<?php

require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/admin-form.php";
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/project-media.php";

$message = "";
$error = "";
$project_name = "";
$location = "";
$description = "";
$project_status = "Planned";
$start_date = "";
$completion_date = "";
$allowed_statuses = ["Planned", "Ongoing", "Completed"];
$media_directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "projects";
$csrf_token = adminFormToken();
ensure_project_media_table($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $project_name = trim($_POST["project_name"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $project_status = $_POST["project_status"] ?? "Planned";
    $start_date = trim($_POST["start_date"] ?? "");
    $completion_date = trim($_POST["completion_date"] ?? "");
    $media = $_FILES["media"] ?? [];

    if (!in_array($project_status, $allowed_statuses, true)) {
        $project_status = "Planned";
    }

    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh the page and try again.";
    } elseif ($project_name === "") {
        $error = "Project name is required.";
    } elseif ($location === "") {
        $error = "Project location is required.";
    } else {
        $start_date_value = $start_date !== "" ? $start_date : null;
        $completion_date_value = $completion_date !== "" ? $completion_date : null;
        $media_result = project_media_uploads($media, $media_directory);
        $uploaded_media = $media_result["items"];
        if ($media_result["errors"] !== []) {
            $error = implode(" ", $media_result["errors"]);
        }
        $image_path = $uploaded_media[0]["path"] ?? "";

        $sql = "INSERT INTO projects
                (project_name, location, description, project_status, start_date, completion_date, image)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($error !== "") {
            $stmt = false;
        } elseif ($stmt) {
            $stmt->bind_param(
                "sssssss",
                $project_name,
                $location,
                $description,
                $project_status,
                $start_date_value,
                $completion_date_value,
                $image_path
            );

            if ($stmt->execute()) {
                $project_id = $conn->insert_id;
                $media_stmt = $conn->prepare("INSERT INTO project_media (project_id, media_path, media_type, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($uploaded_media as $sort_order => $media_item) {
                    $media_stmt->bind_param("issi", $project_id, $media_item["path"], $media_item["type"], $sort_order);
                    $media_stmt->execute();
                }
                $media_stmt->close();
                $stmt->close();
                $conn->close();
                header("Location: ../dashboard.php?project=created");
                exit;
            } else {
                $error = "Error saving project: " . $stmt->error;
                foreach ($uploaded_media as $media_item) {
                    delete_project_media_file($media_item["path"]);
                }
            }

            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Add a project to the E&R Real Estate and Construction Limited website">
    <title>Add Project | E&amp;R Administration</title>
    <link rel="icon" href="../../logo/E&R Logo.jfif">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="../../css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="sidebar-brand" href="../dashboard.php">
                <img src="../../logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo">
                <span><strong>E&amp;R</strong><span>Administration</span></span>
            </a>

            <p class="workspace-label">Workspace</p>
            <nav class="sidebar-nav" aria-label="Administration navigation">
                <a class="sidebar-link" href="../dashboard.php"><i class="fas fa-chart-pie" aria-hidden="true"></i> Dashboard</a>
                <a class="sidebar-link active" href="add-project.php" aria-current="page"><i class="fas fa-plus" aria-hidden="true"></i> Add project</a>
                <a class="sidebar-link" href="../../projects.php"><i class="fas fa-building" aria-hidden="true"></i> Projects</a>
                <a class="sidebar-link" href="../../constructions.html"><i class="fas fa-globe" aria-hidden="true"></i> Public website</a>
            </nav>

            <div class="sidebar-bottom">
                <a class="sidebar-link sidebar-signout" href="../logout.php"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign out</a>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="breadcrumbs"><span>Workspace</span><i class="fas fa-chevron-right" aria-hidden="true"></i><strong>Add project</strong></div>
                <div class="header-actions">
                    <div class="user-chip">
                        <span class="avatar" aria-hidden="true"> <?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1)), ENT_QUOTES, "UTF-8") ?></span>
                        <span class="user-details"><strong><?= htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8") ?></strong><small>Administrator</small></span>
                    </div>
                </div>
            </header>

            <section class="admin-content">
                <div class="page-intro">
                    <div>
                        <p class="eyebrow">Project management</p>
                        <h1>Add a project</h1>
                        <p class="intro-copy">Create a project record for the E&amp;R public website.</p>
                    </div>
                    <a class="button button-quiet" href="../../projects.php"><i class="fas fa-list" aria-hidden="true"></i> View projects</a>
                </div>

                <section class="panel settings-panel">
                    <?php if ($message !== ""): ?>
                        <p class="saved-state" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
                    <?php endif; ?>

                    <?php if ($error !== ""): ?>
                        <p class="saved-state" role="alert" style="color: #8a3f35; background: #f8e9e5;"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p>
                    <?php endif; ?>

                    <form method="POST" action="add-project.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                        <div class="form-grid">
                            <label>
                                Project name
                                <input type="text" name="project_name" value="<?= htmlspecialchars($project_name, ENT_QUOTES, "UTF-8") ?>" required>
                            </label>

                            <label>
                                Location
                                <input type="text" name="location" value="<?= htmlspecialchars($location, ENT_QUOTES, "UTF-8") ?>" required>
                            </label>

                            <label class="full-width">
                                Project summary
                                <small>Use confirmed scope and outcomes only. Leave unverified details blank.</small>
                                <textarea name="description" rows="6"><?= htmlspecialchars($description, ENT_QUOTES, "UTF-8") ?></textarea>
                            </label>

                            <div class="image-upload-field full-width">
                                <label for="media">Project images and short videos</label>
                                <div class="image-upload-grid">
                                    <label class="image-dropzone" for="media">
                                        <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                                        <strong>Choose images or short videos</strong>
                                        <small>Images up to 5 MB; videos up to 50 MB</small>
                                        <input id="media" type="file" name="media[]" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/ogg" multiple>
                                    </label>
                                    <div class="image-preview media-preview-grid" id="mediaPreview"><i class="fas fa-images" aria-hidden="true"></i><span>Media preview</span></div>
                                </div>
                            </div>

                            <label>
                                Project status
                                <select name="project_status">
                                    <?php foreach ($allowed_statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>" <?= $project_status === $status ? "selected" : "" ?>><?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Start date
                                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date, ENT_QUOTES, "UTF-8") ?>">
                            </label>

                            <label>
                                Completion date
                                <input type="date" name="completion_date" value="<?= htmlspecialchars($completion_date, ENT_QUOTES, "UTF-8") ?>">
                            </label>

                            <div class="settings-actions">
                                <span>Fields marked as required must be completed.</span>
                                <button class="button button-primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i> Save project</button>
                            </div>
                        </div>
                    </form>
                </section>
            </section>
        </main>
    </div>
    <script src="../../js/admin-image-optimizer.js"></script>
    <script>
        const mediaInput = document.getElementById("media");
        const mediaPreview = document.getElementById("mediaPreview");

        mediaInput.addEventListener("change", () => {
            const files = Array.from(mediaInput.files);

            if (!files.length) {
                mediaPreview.innerHTML = '<i class="fas fa-images" aria-hidden="true"></i><span>Media preview</span>';
                return;
            }

            mediaPreview.replaceChildren(...files.map((file) => {
                const preview = document.createElement(file.type.startsWith("video/") ? "video" : "img");
                preview.src = URL.createObjectURL(file);
                preview.controls = file.type.startsWith("video/");
                preview.muted = true;
                preview.alt = file.name;
                return preview;
            }));
        });
    </script>
</body>
</html>
