<?php

require_once __DIR__ . "/../../includes/auth.php";
requireAdminPermission("projects");
require_once __DIR__ . "/../../includes/admin-form.php";
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/project-media.php";
require_once __DIR__ . "/../../includes/admin-ui.php";
require_once __DIR__ . "/../../includes/admin-audit.php";

$message = "";
$error = "";
$project_name = "";
$location = "";
$description = "";
$scope_summary = "";
$outcome_summary = "";
$project_status = "Planned";
$start_date = "";
$completion_date = "";
$allowed_statuses = ["Planned", "Ongoing", "Completed"];
$media_directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "projects";
$csrf_token = adminFormToken();
if (!project_media_table_exists($conn)) {
    throw new RuntimeException("The project media table is missing. Apply the project media database migration.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $project_name = trim($_POST["project_name"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $scope_summary = trim($_POST["scope_summary"] ?? "");
    $outcome_summary = trim($_POST["outcome_summary"] ?? "");
    $project_status = $_POST["project_status"] ?? "Planned";
    $start_date = trim($_POST["start_date"] ?? "");
    $completion_date = trim($_POST["completion_date"] ?? "");
    $media = $_FILES["media"] ?? [];
    $publish_requested = ($_POST["publish"] ?? "") === "1";
    $confirmed_facts = ($_POST["confirm_facts"] ?? "") === "1";

    if (!in_array($project_status, $allowed_statuses, true)) {
        $project_status = "Planned";
    }

    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh the page and try again.";
    } elseif ($project_name === "") {
        $error = "Project name is required.";
    } elseif ($location === "" || mb_strlen($project_name) > 255 || mb_strlen($location) > 255) {
        $error = "Enter a project name and location within the 255-character limit.";
    } elseif (mb_strlen($description) > 5000 || mb_strlen($scope_summary) > 5000 || mb_strlen($outcome_summary) > 5000) {
        $error = "Project summary, scope, and outcomes must each be 5,000 characters or fewer.";
    } elseif ($publish_requested && (
        !$confirmed_facts
        || $description === ""
        || $scope_summary === ""
        || $outcome_summary === ""
    )) {
        $error = "To publish now, confirm the facts and provide a factual summary, verified scope, and verified outcome.";
    } else {
        $start_date_value = $start_date !== "" ? $start_date : null;
        $completion_date_value = $completion_date !== "" ? $completion_date : null;
        $media_result = project_media_uploads($media, $media_directory);
        $uploaded_media = $media_result["items"];
        if ($media_result["errors"] !== []) {
            $error = implode(" ", $media_result["errors"]);
        }
        $image_path = "";
        foreach ($uploaded_media as $media_item) {
            if ($media_item["type"] === "image") {
                $image_path = $media_item["path"];
                break;
            }
        }
        if ($publish_requested && !project_cover_image_exists($image_path)) {
            $error = "To publish now, upload at least one valid project photo. Videos cannot be used as the cover photo.";
        }

        $sql = "INSERT INTO projects
                (project_name, location, description, scope_summary, outcome_summary, project_status, start_date, completion_date, image, is_published)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($error !== "") {
            foreach ($uploaded_media as $media_item) {
                delete_project_media_file($media_item["path"]);
            }
            $stmt = false;
        } elseif ($stmt) {
            $published = $publish_requested ? 1 : 0;
            $stmt->bind_param(
                "sssssssssi",
                $project_name,
                $location,
                $description,
                $scope_summary,
                $outcome_summary,
                $project_status,
                $start_date_value,
                $completion_date_value,
                $image_path,
                $published
            );

            $saved = false;
            $conn->begin_transaction();
            try {
                if (!$stmt->execute()) {
                    throw new RuntimeException("The project record could not be saved.");
                }
                $project_id = (int) $conn->insert_id;
                if ($uploaded_media !== []) {
                    $media_stmt = $conn->prepare("INSERT INTO project_media (project_id, media_path, media_type, sort_order) VALUES (?, ?, ?, ?)");
                    foreach ($uploaded_media as $sort_order => $media_item) {
                        $media_stmt->bind_param("issi", $project_id, $media_item["path"], $media_item["type"], $sort_order);
                        if (!$media_stmt->execute()) {
                            throw new RuntimeException("A project media record could not be saved.");
                        }
                    }
                    $media_stmt->close();
                }
                $conn->commit();
                $saved = true;
            } catch (mysqli_sql_exception | RuntimeException $exception) {
                $conn->rollback();
                error_log("Project save failed: " . $exception->getMessage());
                $error = "The project and its media could not be saved. Please check the database and try again.";
                foreach ($uploaded_media as $media_item) {
                    delete_project_media_file($media_item["path"]);
                }
            }

            if ($saved) {
                $auditFailed = false;
                try {
                    recordAdminAudit($conn, $publish_requested ? "publish_project" : "create_project_draft", "project", (int) $project_id);
                } catch (RuntimeException $exception) {
                    error_log($exception->getMessage());
                    $auditFailed = true;
                }
                $stmt->close();
                $conn->close();
                header("Location: ../../projects.php?project=" . ($publish_requested ? "published" : "created") . ($auditFailed ? "&audit=failed" : ""));
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
        <?php renderAdminSidebar("add_project", "projects"); ?>

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
                            <label class="full-width">Verified scope of work<small>Record only work confirmed by the company or project owner.</small><textarea name="scope_summary" maxlength="5000" rows="4"><?= htmlspecialchars($scope_summary, ENT_QUOTES, "UTF-8") ?></textarea></label>
                            <label class="full-width">Verified outcomes<small>Do not publish estimates, guarantees, or outcomes that have not been verified.</small><textarea name="outcome_summary" maxlength="5000" rows="4"><?= htmlspecialchars($outcome_summary, ENT_QUOTES, "UTF-8") ?></textarea></label>

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

                            <div class="full-width">
                                <label class="remember-option"><input type="checkbox" name="confirm_facts" value="1"> I confirm these details, scope, and outcomes are verified and approved for public display.</label>
                                <small class="text-muted">Projects are drafts by default. Save and publish requires the confirmation above, a factual summary, verified scope and outcome, and at least one photo.</small>
                            </div>
                            <div class="settings-actions">
                                <span>Save as a draft, or publish verified details now.</span>
                                <div class="d-flex flex-wrap" style="gap: 10px;">
                                    <button class="button button-quiet" type="submit"><i class="fas fa-save" aria-hidden="true"></i> Save draft</button>
                                    <button class="button button-primary" type="submit" name="publish" value="1"><i class="fas fa-globe" aria-hidden="true"></i> Save and publish</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </section>
            </section>
        </main>
    </div>
    <script src="../../js/admin-image-optimizer.js"></script>
    <script src="../../js/admin-shell.js" defer></script>
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
