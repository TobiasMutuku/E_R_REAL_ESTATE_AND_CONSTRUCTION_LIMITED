<?php
require_once __DIR__ . "/../includes/auth.php";
requireAdminPermission("content");
require_once __DIR__ . "/../includes/admin-form.php";
require_once __DIR__ . "/../includes/admin-audit.php";
require_once __DIR__ . "/../includes/admin-ui.php";
require_once __DIR__ . "/../config/db.php";

$types = ["Residential", "Commercial", "Land", "Apartment", "House", "Office", "Other"];
$statuses = ["Available", "Reserved", "Sold", "Rented", "Coming Soon"];
$token = adminFormToken();
$error = "";
$propertyId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "properties";

if (!$propertyId) {
    http_response_code(404);
    exit("Property not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $propertyId = filter_var(adminPostString("id"), FILTER_VALIDATE_INT) ?: $propertyId;
    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh and try again.";
    } else {
        $name = trim(adminPostString("property_name"));
        $type = adminPostString("property_type");
        $location = trim(adminPostString("location"));
        $description = trim(adminPostString("description"));
        $status = adminPostString("property_status");
        $priceInput = trim(adminPostString("price"));
        $bedroomsInput = trim(adminPostString("bedrooms"));
        $bathroomsInput = trim(adminPostString("bathrooms"));
        $areaInput = trim(adminPostString("area"));
        $image = $_FILES["image"] ?? null;
        $price = $priceInput === "" ? null : (is_numeric($priceInput) ? (float) $priceInput : null);
        $bedrooms = $bedroomsInput === "" ? null : (ctype_digit($bedroomsInput) ? (int) $bedroomsInput : null);
        $bathrooms = $bathroomsInput === "" ? null : (ctype_digit($bathroomsInput) ? (int) $bathroomsInput : null);
        $area = $areaInput === "" ? null : (is_numeric($areaInput) ? (float) $areaInput : null);
        $imagePath = null;

        if ($name === "" || mb_strlen($name) > 255 || $location === "" || mb_strlen($location) > 255) {
            $error = "Enter a property name and location (255 characters maximum).";
        } elseif (!in_array($type, $types, true) || !in_array($status, $statuses, true)) {
            $error = "Select a valid property type and availability status.";
        } elseif ($description !== "" && mb_strlen($description) > 5000) {
            $error = "The description must not exceed 5,000 characters.";
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
                "UPDATE properties
                 SET property_name = ?, property_type = ?, location = ?, price = ?, bedrooms = ?,
                     bathrooms = ?, area = ?, description = ?, property_status = ?,
                     image = COALESCE(?, image), is_published = 0
                 WHERE id = ?"
            );
            if (!$statement) {
                throw new RuntimeException("Unable to prepare the property update.");
            }
            $statement->bind_param(
                "sssdiidsssi",
                $name,
                $type,
                $location,
                $price,
                $bedrooms,
                $bathrooms,
                $area,
                $description,
                $status,
                $imagePath,
                $propertyId
            );
            if ($statement->execute() && $statement->affected_rows >= 0) {
                try {
                    recordAdminAudit($conn, "update_property_draft", "property", (int) $propertyId);
                    header("Location: content-review.php?property=updated");
                    exit;
                } catch (RuntimeException $exception) {
                    error_log($exception->getMessage());
                    $error = "Property details were saved as an unpublished draft, but their audit event could not be recorded. Check server logs.";
                }
            } else {
                $error = "The property details could not be saved.";
            }
            $statement->close();
        }
    }
}

$statement = $conn->prepare(
    "SELECT property_name, property_type, location, price, bedrooms, bathrooms, area, description,
            property_status, image, is_published
     FROM properties WHERE id = ? LIMIT 1"
);
if (!$statement) {
    throw new RuntimeException("Unable to prepare the property lookup.");
}
$statement->bind_param("i", $propertyId);
$statement->execute();
$property = $statement->get_result()->fetch_assoc();
$statement->close();
if (!$property) {
    http_response_code(404);
    exit("Property not found.");
}
$adminName = $_SESSION["admin_name"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit property | E&amp;R Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="admin-shell">
    <?php renderAdminSidebar("content"); ?>
    <main class="admin-main">
        <header class="admin-header"><div class="breadcrumbs"><span>Workspace</span><strong>Edit property</strong></div><div class="user-chip"><strong><?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?></strong></div></header>
        <section class="admin-content">
            <div class="page-intro"><div><p class="eyebrow">Property management</p><h1>Edit property details</h1><p class="intro-copy">Saved edits return this listing to draft review before it can appear publicly.</p></div><a class="button button-secondary" href="content-review.php">Back to content review</a></div>
            <?php if ($error !== ""): ?><p class="saved-state warning-state" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <section class="panel settings-panel">
                <form method="post" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="id" value="<?= (int) $propertyId ?>">
                    <label>Property name<input name="property_name" maxlength="255" value="<?= htmlspecialchars($property["property_name"], ENT_QUOTES, "UTF-8") ?>" required></label>
                    <label>Location<input name="location" maxlength="255" value="<?= htmlspecialchars($property["location"], ENT_QUOTES, "UTF-8") ?>" required></label>
                    <label>Property type<select name="property_type"><?php foreach ($types as $type): ?><option value="<?= htmlspecialchars($type, ENT_QUOTES, "UTF-8") ?>" <?= $property["property_type"] === $type ? "selected" : "" ?>><?= htmlspecialchars($type, ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                    <label>Availability<select name="property_status"><?php foreach ($statuses as $status): ?><option value="<?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>" <?= $property["property_status"] === $status ? "selected" : "" ?>><?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?></option><?php endforeach; ?></select></label>
                    <label>Price (optional)<input name="price" type="number" min="0" step="0.01" value="<?= $property["price"] !== null ? htmlspecialchars((string) $property["price"], ENT_QUOTES, "UTF-8") : "" ?>"></label>
                    <label>Area (optional)<input name="area" type="number" min="0" step="0.01" value="<?= $property["area"] !== null ? htmlspecialchars((string) $property["area"], ENT_QUOTES, "UTF-8") : "" ?>"></label>
                    <label>Bedrooms<input name="bedrooms" type="number" min="0" step="1" value="<?= $property["bedrooms"] !== null ? (int) $property["bedrooms"] : "" ?>"></label>
                    <label>Bathrooms<input name="bathrooms" type="number" min="0" step="1" value="<?= $property["bathrooms"] !== null ? (int) $property["bathrooms"] : "" ?>"></label>
                    <label class="full-width">Factual description<textarea name="description" rows="5" maxlength="5000"><?= htmlspecialchars($property["description"] ?? "", ENT_QUOTES, "UTF-8") ?></textarea></label>
                    <?php if ($property["image"]): ?><div class="full-width"><img src="../<?= htmlspecialchars($property["image"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($property["property_name"], ENT_QUOTES, "UTF-8") ?> current photo" loading="lazy" style="width: min(100%, 420px); max-height: 260px; object-fit: cover; border-radius: 10px;"></div><?php endif; ?>
                    <label class="full-width">Replace photo (optional; JPG, PNG, WebP; max 5 MB)<input name="image" type="file" accept="image/jpeg,image/png,image/webp"></label>
                    <div class="settings-actions"><span><?= $property["is_published"] ? "Saving sends this listing back to review." : "This listing remains unpublished until reviewed." ?></span><button class="button button-primary" type="submit">Save property draft</button></div>
                </form>
            </section>
        </section>
    </main>
</div>
<script src="../js/admin-shell.js" defer></script>
</body>
</html>
