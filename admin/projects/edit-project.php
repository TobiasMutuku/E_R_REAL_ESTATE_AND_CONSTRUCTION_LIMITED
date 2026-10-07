<?php

require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/admin-form.php";
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/project-media.php";

$project_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$project_id) {
    header("Location: ../../projects.php");
    exit;
}

$allowed_statuses = ["Planned", "Ongoing", "Completed"];
$allowed_image_types = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp",
];
$error = "";
$success = "";
$media_directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "projects";
$csrf_token = adminFormToken();
ensure_project_media_table($conn);

$load_stmt = $conn->prepare("SELECT project_name, location, description, project_status, start_date, completion_date, image FROM projects WHERE id = ? LIMIT 1");
$load_stmt->bind_param("i", $project_id);
$load_stmt->execute();
$project_result = $load_stmt->get_result();
$project = $project_result->fetch_assoc();
$load_stmt->close();

if (!$project) {
    $conn->close();
    header("Location: ../../projects.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $project["project_name"] = trim($_POST["project_name"] ?? "");
    $project["location"] = trim($_POST["location"] ?? "");
    $project["description"] = trim($_POST["description"] ?? "");
    $project["project_status"] = $_POST["project_status"] ?? "Planned";
    $project["start_date"] = trim($_POST["start_date"] ?? "");
    $project["completion_date"] = trim($_POST["completion_date"] ?? "");
    $image = $_FILES["image"] ?? null;
    $media = $_FILES["media"] ?? [];
    $remove_image = isset($_POST["remove_image"]);

    if (!in_array($project["project_status"], $allowed_statuses, true)) {
        $project["project_status"] = "Planned";
    }

    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh the page and try again.";
    } elseif ($project["project_name"] === "") {
        $error = "Project name is required.";
    } elseif ($project["location"] === "") {
        $error = "Project location is required.";
    } elseif ($image && $image["error"] !== UPLOAD_ERR_NO_FILE && $image["error"] !== UPLOAD_ERR_OK) {
        $error = "The project image could not be uploaded.";
    } elseif ($image && $image["error"] === UPLOAD_ERR_OK && $image["size"] > 5 * 1024 * 1024) {
        $error = "The project image must be 5 MB or smaller.";
    } elseif ($image && $image["error"] === UPLOAD_ERR_OK && !isset($allowed_image_types[(new finfo(FILEINFO_MIME_TYPE))->file($image["tmp_name"])])) {
        $error = "Project images must be JPG, PNG, or WebP files.";
    } else {
        $media_result = project_media_uploads($media, $media_directory);
        $uploaded_media = $media_result["items"];
        if ($media_result["errors"] !== []) {
            $error = implode(" ", $media_result["errors"]);
        }
        $old_image = $project["image"];
        $new_image = $old_image;
        $uploaded_image_path = "";

        if ($image && $image["error"] === UPLOAD_ERR_OK) {
            $image_mime = (new finfo(FILEINFO_MIME_TYPE))->file($image["tmp_name"]);
            $image_filename = bin2hex(random_bytes(16)) . "." . $allowed_image_types[$image_mime];
            $image_directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "projects";
            $uploaded_image_path = "uploads/projects/" . $image_filename;

            if (move_uploaded_file($image["tmp_name"], $image_directory . DIRECTORY_SEPARATOR . $image_filename)) {
                $new_image = $uploaded_image_path;
            } else {
                $error = "The project image could not be saved.";
            }
        } elseif ($remove_image) {
            $new_image = "";
        }

        if ($error === "") {
            $start_date = $project["start_date"] !== "" ? $project["start_date"] : null;
            $completion_date = $project["completion_date"] !== "" ? $project["completion_date"] : null;
            $update_stmt = $conn->prepare("UPDATE projects SET project_name = ?, location = ?, description = ?, project_status = ?, start_date = ?, completion_date = ?, image = ?, is_published = 0 WHERE id = ?");

            if ($update_stmt) {
                $update_stmt->bind_param(
                    "sssssssi",
                    $project["project_name"],
                    $project["location"],
                    $project["description"],
                    $project["project_status"],
                    $start_date,
                    $completion_date,
                    $new_image,
                    $project_id
                );

                if ($update_stmt->execute()) {
                    if ($uploaded_media !== []) {
                        $media_stmt = $conn->prepare("INSERT INTO project_media (project_id, media_path, media_type, sort_order) VALUES (?, ?, ?, ?)");
                        foreach ($uploaded_media as $sort_order => $media_item) {
                            $media_stmt->bind_param("issi", $project_id, $media_item["path"], $media_item["type"], $sort_order);
                            $media_stmt->execute();
                        }
                        $media_stmt->close();
                    }
                    if ($old_image !== $new_image && str_starts_with($old_image, "uploads/projects/")) {
                        @unlink(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $old_image);
                    }
                    $update_stmt->close();
                    $conn->close();
                    header("Location: ../../projects.php?project=updated");
                    exit;
                }

                $error = "Error updating project: " . $update_stmt->error;
                $update_stmt->close();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }

        if ($error !== "" && $uploaded_image_path !== "") {
            @unlink(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $uploaded_image_path);
        }
        if ($error !== "") {
            foreach ($uploaded_media as $media_item) {
                delete_project_media_file($media_item["path"]);
            }
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
    <meta name="description" content="Edit an E&R Real Estate and Construction Limited project">
    <title>Edit Project | E&amp;R Administration</title>
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
                <a class="sidebar-link" href="add-project.php"><i class="fas fa-plus" aria-hidden="true"></i> Add project</a>
                <a class="sidebar-link active" href="../../projects.php" aria-current="page"><i class="fas fa-building" aria-hidden="true"></i> Projects</a>
                <a class="sidebar-link" href="../../constructions.html"><i class="fas fa-globe" aria-hidden="true"></i> Public website</a>
            </nav>
            <div class="sidebar-bottom"><a class="sidebar-link sidebar-signout" href="../logout.php"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign out</a></div>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="breadcrumbs"><span>Workspace</span><i class="fas fa-chevron-right" aria-hidden="true"></i><strong>Edit project</strong></div>
                <div class="header-actions"><div class="user-chip"><span class="avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1)), ENT_QUOTES, "UTF-8") ?></span><span class="user-details"><strong><?= htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8") ?></strong><small>Administrator</small></span></div></div>
            </header>

            <section class="admin-content">
                <div class="page-intro">
                    <div><p class="eyebrow">Project management</p><h1>Edit project</h1><p class="intro-copy">Update the project details and presentation image.</p></div>
                    <a class="button button-quiet" href="../../projects.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to projects</a>
                </div>

                <section class="panel settings-panel">
                    <?php if ($error !== ""): ?><p class="saved-state" role="alert" style="color: #8a3f35; background: #f8e9e5;"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
                    <form method="POST" action="edit-project.php?id=<?= $project_id ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                        <div class="form-grid">
                            <label>Project name<input type="text" name="project_name" value="<?= htmlspecialchars($project["project_name"], ENT_QUOTES, "UTF-8") ?>" required></label>
                            <label>Location<input type="text" name="location" value="<?= htmlspecialchars($project["location"], ENT_QUOTES, "UTF-8") ?>" required></label>
                            <label class="full-width">Project summary<small>Use confirmed scope and outcomes only. Leave unverified details blank.</small><textarea name="description" rows="6"><?= htmlspecialchars($project["description"], ENT_QUOTES, "UTF-8") ?></textarea></label>
                            <label>Project status<select name="project_status"><?php foreach ($allowed_statuses as $status): ?><option value="<?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>" <?= $project["project_status"] === $status ? "selected" : "" ?>><?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                            <label>Start date<input type="date" name="start_date" value="<?= htmlspecialchars($project["start_date"] ?? "", ENT_QUOTES, "UTF-8") ?>"></label>
                            <label>Completion date<input type="date" name="completion_date" value="<?= htmlspecialchars($project["completion_date"] ?? "", ENT_QUOTES, "UTF-8") ?>"></label>

                            <div class="image-upload-field full-width">
                                <label for="image">Cover image</label>
                                <div class="image-upload-grid">
                                    <label class="image-dropzone" for="image"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i><strong>Choose a replacement image</strong><small>JPG, PNG, or WebP up to 5 MB</small><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                                    <div class="image-preview" id="imagePreview"><?php if (!empty($project["image"])): ?><img src="../../<?= htmlspecialchars($project["image"], ENT_QUOTES, "UTF-8") ?>" alt="Current project image"><?php else: ?><i class="fas fa-image" aria-hidden="true"></i><span>No image uploaded</span><?php endif; ?></div>
                                </div>
                                <?php if (!empty($project["image"])): ?><label class="remember-option remove-image-option"><input type="checkbox" name="remove_image" value="1"> Remove current image</label><?php endif; ?>
                                <label for="media" class="mt-3">Add gallery images or short videos</label>
                                <input id="media" type="file" name="media[]" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/ogg" multiple>
                                <small class="text-muted">Images up to 5 MB each; videos up to 50 MB each.</small>
                            </div>

                            <div class="settings-actions"><span>Leave the image empty to keep the current one.</span><button class="button button-primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i> Save changes</button></div>
                        </div>
                    </form>
                </section>
            </section>
        </main>
    </div>
    <script src="../../js/admin-image-optimizer.js"></script>
    <script>
        const imageInput = document.getElementById("image");
        const imagePreview = document.getElementById("imagePreview");
        imageInput.addEventListener("change", () => {
            const file = imageInput.files[0];
            if (!file) return;
            const image = document.createElement("img");
            image.src = URL.createObjectURL(file);
            image.alt = "Replacement project preview";
            imagePreview.replaceChildren(image);
        });
    </script>
</body>
</html>
