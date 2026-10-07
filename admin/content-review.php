<?php
require_once __DIR__ . "/../includes/auth.php";
requireAdminPermission("content");
require_once __DIR__ . "/../includes/admin-form.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/admin-ui.php";
require_once __DIR__ . "/../includes/admin-audit.php";
require_once __DIR__ . "/projects/project-media.php";

$token = adminFormToken();
$error = "";
$notice = "";
$projectStatuses = ["Planned", "Ongoing", "Completed"];
$propertyTypes = ["Residential", "Commercial", "Land", "Apartment", "House", "Office", "Other"];
$propertyStatuses = ["Available", "Reserved", "Sold", "Rented", "Coming Soon"];
$uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "properties";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh the page and try again.";
    } else {
        $action = adminPostString("action");
        $id = filter_var(adminPostString("id"), FILTER_VALIDATE_INT);

        if ($action === "review_project" && $id) {
            $publish = adminPostString("publish") === "1";
            $confirmed = adminPostString("confirm_facts") === "1";
            $statement = $conn->prepare("SELECT project_name, location, description, scope_summary, outcome_summary, image FROM projects WHERE id = ? LIMIT 1");
            $statement->bind_param("i", $id);
            $statement->execute();
            $project = $statement->get_result()->fetch_assoc();
            $statement->close();

            if (!$project) {
                $error = "The selected project no longer exists.";
            } elseif ($publish && (
                !$confirmed
                || trim($project["description"] ?? "") === ""
                || trim($project["scope_summary"] ?? "") === ""
                || trim($project["outcome_summary"] ?? "") === ""
                || !project_cover_image_exists((string) ($project["image"] ?? ""))
                || trim($project["location"]) === ""
            )) {
                $error = "To publish, confirm the facts and add a factual summary, verified scope and outcome, location, and a valid cover photo.";
            } else {
                $statement = $conn->prepare("UPDATE projects SET is_published = ? WHERE id = ?");
                $published = $publish ? 1 : 0;
                $statement->bind_param("ii", $published, $id);
                if ($statement->execute()) {
                    $notice = $publish ? "Project published." : "Project withdrawn from the public website.";
                    try {
                        recordAdminAudit($conn, $publish ? "publish_project" : "withdraw_project", "project", (int) $id);
                    } catch (RuntimeException $exception) {
                        error_log($exception->getMessage());
                        $error = "The review decision was saved, but its audit event could not be recorded. Check server logs.";
                    }
                } else {
                    $error = "The project review decision could not be saved.";
                }
                $statement->close();
            }
        } elseif ($action === "review_property" && $id) {
            $publish = adminPostString("publish") === "1";
            $confirmed = adminPostString("confirm_facts") === "1";
            $statement = $conn->prepare("SELECT property_name, location, description, image, property_status FROM properties WHERE id = ? LIMIT 1");
            $statement->bind_param("i", $id);
            $statement->execute();
            $property = $statement->get_result()->fetch_assoc();
            $statement->close();

            if (!$property) {
                $error = "The selected property no longer exists.";
            } elseif ($publish && (
                !$confirmed
                || trim($property["description"] ?? "") === ""
                || empty($property["image"])
                || !in_array($property["property_status"], ["Available", "Coming Soon"], true)
            )) {
                $error = "To publish, confirm the details, add a description and photo, and set the status to Available or Coming Soon.";
            } else {
                $statement = $conn->prepare("UPDATE properties SET is_published = ? WHERE id = ?");
                $published = $publish ? 1 : 0;
                $statement->bind_param("ii", $published, $id);
                if ($statement->execute()) {
                    $notice = $publish ? "Property published." : "Property withdrawn from the public website.";
                    try {
                        recordAdminAudit($conn, $publish ? "publish_property" : "withdraw_property", "property", (int) $id);
                    } catch (RuntimeException $exception) {
                        error_log($exception->getMessage());
                        $error = "The review decision was saved, but its audit event could not be recorded. Check server logs.";
                    }
                } else {
                    $error = "The property review decision could not be saved.";
                }
                $statement->close();
            }
        } elseif ($action === "property_status" && $id) {
            $status = adminPostString("property_status");
            if (!in_array($status, $propertyStatuses, true)) {
                $error = "Select a valid property availability status.";
            } else {
                $statement = $conn->prepare(
                    "UPDATE properties
                     SET property_status = ?,
                         is_published = CASE WHEN ? IN ('Available', 'Coming Soon') THEN is_published ELSE 0 END
                     WHERE id = ?"
                );
                $statement->bind_param("ssi", $status, $status, $id);
                if ($statement->execute()) {
                    $notice = "Property availability updated.";
                    try {
                        recordAdminAudit($conn, "update_property_availability", "property", (int) $id, ["status" => $status]);
                    } catch (RuntimeException $exception) {
                        error_log($exception->getMessage());
                        $error = "Availability was saved, but its audit event could not be recorded. Check server logs.";
                    }
                } else {
                    $error = "Property availability could not be updated.";
                }
                $statement->close();
            }
        } elseif ($action === "add_property") {
            $name = trim(adminPostString("property_name"));
            $type = adminPostString("property_type", "Other");
            $location = trim(adminPostString("location"));
            $description = trim(adminPostString("description"));
            $status = adminPostString("property_status", "Coming Soon");
            $priceInput = trim(adminPostString("price"));
            $bedroomsInput = trim(adminPostString("bedrooms"));
            $bathroomsInput = trim(adminPostString("bathrooms"));
            $areaInput = trim(adminPostString("area"));
            $image = $_FILES["image"] ?? null;
            $price = $priceInput !== "" && is_numeric($priceInput) ? (float) $priceInput : null;
            $bedrooms = $bedroomsInput !== "" && ctype_digit($bedroomsInput) ? (int) $bedroomsInput : null;
            $bathrooms = $bathroomsInput !== "" && ctype_digit($bathroomsInput) ? (int) $bathroomsInput : null;
            $area = $areaInput !== "" && is_numeric($areaInput) ? (float) $areaInput : null;
            $imagePath = null;

            if ($name === "" || mb_strlen($name) > 255 || $location === "" || mb_strlen($location) > 255) {
                $error = "Enter a property name and location (255 characters maximum).";
            } elseif (!in_array($type, $propertyTypes, true) || !in_array($status, $propertyStatuses, true)) {
                $error = "Select a valid property type and availability status.";
            } elseif ($description !== "" && mb_strlen($description) > 5000) {
                $error = "The property description must be 5,000 characters or fewer.";
            } elseif (($priceInput !== "" && ($price === null || $price < 0))
                || ($bedroomsInput !== "" && $bedrooms === null)
                || ($bathroomsInput !== "" && $bathrooms === null)
                || ($areaInput !== "" && ($area === null || $area < 0))) {
                $error = "Enter valid non-negative price, room, and area values.";
            } elseif ($image && $image["error"] !== UPLOAD_ERR_NO_FILE && $image["error"] !== UPLOAD_ERR_OK) {
                $error = "The property photo could not be uploaded.";
            } elseif ($image && $image["error"] === UPLOAD_ERR_OK) {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($image["tmp_name"]);
                $extensions = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
                if ($image["size"] > 5 * 1024 * 1024 || !isset($extensions[$mime])) {
                    $error = "Choose a JPG, PNG, or WebP property photo no larger than 5 MB.";
                } elseif (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    $error = "The property image directory could not be prepared.";
                } else {
                    $filename = bin2hex(random_bytes(16)) . "." . $extensions[$mime];
                    if (move_uploaded_file($image["tmp_name"], $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
                        $imagePath = "uploads/properties/" . $filename;
                    } else {
                        $error = "The property photo could not be saved.";
                    }
                }
            }

            if ($error === "") {
                $statement = $conn->prepare(
                    "INSERT INTO properties
                     (property_name, property_type, location, price, bedrooms, bathrooms, area, description, property_status, image, is_published)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
                );
                $statement->bind_param("sssdiidsss", $name, $type, $location, $price, $bedrooms, $bathrooms, $area, $description, $status, $imagePath);
                if ($statement->execute()) {
                    $notice = "Property saved as an unpublished draft. Review its details before publishing.";
                    try {
                        recordAdminAudit($conn, "create_property_draft", "property", (int) $conn->insert_id);
                    } catch (RuntimeException $exception) {
                        error_log($exception->getMessage());
                        $error = "The property draft was saved, but its audit event could not be recorded. Check server logs.";
                    }
                } else {
                    if ($imagePath !== null) {
                        unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . $imagePath);
                    }
                    $error = "The property record could not be saved.";
                }
                $statement->close();
            }
        } else {
            $error = "Invalid content review action.";
        }
    }
}

$projects = $conn->query("SELECT id, project_name, location, description, scope_summary, outcome_summary, project_status, start_date, completion_date, image, is_published FROM projects ORDER BY updated_at DESC");
$properties = $conn->query("SELECT id, property_name, property_type, location, price, bedrooms, bathrooms, area, description, property_status, image, is_published FROM properties ORDER BY updated_at DESC");
if (!$projects || !$properties) {
    throw new RuntimeException("Content records could not be loaded.");
}
$notice = $notice ?: (isset($_GET["property"]) && $_GET["property"] === "updated" ? "Property details were saved as an unpublished draft." : "");
$adminName = $_SESSION["admin_name"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Content review | E&amp;R Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="admin-shell">
    <?php renderAdminSidebar("content"); ?>
    <main class="admin-main">
        <header class="admin-header"><div class="breadcrumbs"><span>Workspace</span><strong>Content review</strong></div><div class="user-chip"><strong><?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?></strong></div></header>
        <section class="admin-content">
            <div class="page-intro"><div><p class="eyebrow">Publishing workflow</p><h1>Review public content</h1><p class="intro-copy">New records stay private until a reviewer confirms the facts and required presentation details.</p></div></div>
            <?php if ($error !== ""): ?><p class="saved-state" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <?php if ($notice !== ""): ?><p class="saved-state" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <section class="panel settings-panel">
                <h2>Add a property draft</h2>
                <p class="intro-copy">This saves a private draft. A photo, factual description, availability check, and reviewer confirmation are required before publication.</p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="action" value="add_property">
                    <div class="form-grid">
                        <label>Property name<input name="property_name" maxlength="255" required></label>
                        <label>Location<input name="location" maxlength="255" required></label>
                        <label>Type<select name="property_type"><?php foreach ($propertyTypes as $type): ?><option><?= htmlspecialchars($type, ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                        <label>Availability<select name="property_status"><?php foreach ($propertyStatuses as $status): ?><option <?= $status === "Coming Soon" ? "selected" : "" ?>><?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                        <label>Price (optional)<input name="price" type="number" min="0" step="0.01"></label>
                        <label>Area (optional)<input name="area" type="number" min="0" step="0.01"></label>
                        <label>Bedrooms<input name="bedrooms" type="number" min="0" step="1"></label>
                        <label>Bathrooms<input name="bathrooms" type="number" min="0" step="1"></label>
                        <label class="full-width">Factual description<textarea name="description" rows="4" maxlength="5000"></textarea></label>
                        <label class="full-width">Property photo (JPG, PNG, WebP; max 5 MB)<input name="image" type="file" accept="image/jpeg,image/png,image/webp"></label>
                        <div class="settings-actions">
                            <span>Saved as unpublished</span><button class="button button-primary" type="submit">Save property draft</button></div>
                    </div>
                </form>
            </section>

            <section class="panel settings-panel">
                <h2>Projects</h2>
                <?php while ($project = $projects->fetch_assoc()): ?>
                    <article class="activity-item">
                        <?php if ($project["image"]): ?><img src="../<?= htmlspecialchars($project["image"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($project["project_name"], ENT_QUOTES, "UTF-8") ?> review photo" loading="lazy" style="width: 100px; height: 72px; object-fit: cover; border-radius: 6px;"><?php endif; ?>
                        <div><strong><?= htmlspecialchars($project["project_name"], ENT_QUOTES, "UTF-8") ?></strong><p><?= htmlspecialchars($project["location"], ENT_QUOTES, "UTF-8") ?> · <?= htmlspecialchars($project["project_status"], ENT_QUOTES, "UTF-8") ?> · <?= $project["start_date"] ? htmlspecialchars($project["start_date"], ENT_QUOTES, "UTF-8") : "No start date" ?><?= $project["completion_date"] ? " to " . htmlspecialchars($project["completion_date"], ENT_QUOTES, "UTF-8") : "" ?></p><small><?= htmlspecialchars($project["description"] ?: "No factual summary yet.", ENT_QUOTES, "UTF-8") ?></small><small>Scope: <?= htmlspecialchars($project["scope_summary"] ?: "Not provided", ENT_QUOTES, "UTF-8") ?> · Outcome: <?= htmlspecialchars($project["outcome_summary"] ?: "Not provided", ENT_QUOTES, "UTF-8") ?> · <?= project_cover_image_exists((string) ($project["image"] ?? "")) ? "Valid cover photo" : "Cover photo required" ?></small><?php if (!$project["is_published"]): ?><small class="d-block mt-2">Drafts are private. Complete any missing facts, then confirm to publish.</small><?php endif; ?></div>
                        <a class="button button-secondary" href="projects/edit-project.php?id=<?= (int) $project["id"] ?>">Edit details</a>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="action" value="review_project">
                            <input type="hidden" name="id" value="<?= (int) $project["id"] ?>">
                            <input type="hidden" name="publish" value="<?= $project["is_published"] ? "0" : "1" ?>">
                            <?php if (!$project["is_published"]): ?><label class="remember-option"><input type="checkbox" name="confirm_facts" value="1" required> I verified the project facts, photo, scope, outcome, and status.</label><?php endif; ?>
                            <button class="button <?= $project["is_published"] ? "button-danger" : "button-primary" ?>" type="submit"><?= $project["is_published"] ? "Withdraw" : "Publish" ?></button>
                        </form>
                    </article>
                <?php endwhile; ?>
            </section>

            <section class="panel settings-panel">
                <h2>Properties</h2>
                <?php while ($property = $properties->fetch_assoc()): ?>
                    <article class="activity-item">
                        <?php if ($property["image"]): ?><img src="../<?= htmlspecialchars($property["image"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($property["property_name"], ENT_QUOTES, "UTF-8") ?> review photo" loading="lazy" style="width: 100px; height: 72px; object-fit: cover; border-radius: 6px;"><?php endif; ?>
                        <div><strong><?= htmlspecialchars($property["property_name"], ENT_QUOTES, "UTF-8") ?></strong><p><?= htmlspecialchars($property["location"], ENT_QUOTES, "UTF-8") ?> · <?= htmlspecialchars($property["property_type"], ENT_QUOTES, "UTF-8") ?> · <?= htmlspecialchars($property["property_status"], ENT_QUOTES, "UTF-8") ?><?= $property["price"] !== null ? " · KSh " . number_format((float) $property["price"], 2) : "" ?><?= $property["bedrooms"] !== null ? " · " . (int) $property["bedrooms"] . " bedrooms" : "" ?><?= $property["bathrooms"] !== null ? " · " . (int) $property["bathrooms"] . " bathrooms" : "" ?><?= $property["area"] !== null ? " · " . number_format((float) $property["area"], 2) . " area" : "" ?></p><small><?= htmlspecialchars($property["description"] ?: "No factual description yet.", ENT_QUOTES, "UTF-8") ?> · <?= $property["image"] ? "Photo present" : "No photo" ?></small></div>
                        <a class="button button-secondary" href="edit-property.php?id=<?= (int) $property["id"] ?>">Edit details</a>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="action" value="property_status">
                            <input type="hidden" name="id" value="<?= (int) $property["id"] ?>">
                            <label>Availability<select name="property_status"><?php foreach ($propertyStatuses as $status): ?><option <?= $property["property_status"] === $status ? "selected" : "" ?>><?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                            <button class="button button-quiet" type="submit">Save availability</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="action" value="review_property">
                            <input type="hidden" name="id" value="<?= (int) $property["id"] ?>">
                            <input type="hidden" name="publish" value="<?= $property["is_published"] ? "0" : "1" ?>">
                            <?php if (!$property["is_published"]): ?><label class="remember-option"><input type="checkbox" name="confirm_facts" value="1" required> I verified the facts, photo, price, and availability.</label><?php endif; ?>
                            <button class="button <?= $property["is_published"] ? "button-danger" : "button-primary" ?>" type="submit"><?= $property["is_published"] ? "Withdraw" : "Publish" ?></button>
                        </form>
                    </article>
                <?php endwhile; ?>
            </section>
        </section>
    </main>
</div>
<script src="../js/admin-image-optimizer.js"></script>
<script src="../js/admin-shell.js" defer></script>
</body>
</html>
