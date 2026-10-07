<?php

function project_media_table_exists(mysqli $conn): bool
{
    $result = $conn->query("SHOW TABLES LIKE 'project_media'");
    return $result !== false && $result->num_rows === 1;
}

function project_cover_image_exists(string $relativePath): bool
{
    if (!str_starts_with($relativePath, "uploads/projects/")) {
        return false;
    }

    $directory = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "projects");
    $imagePath = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath));
    if ($directory === false || $imagePath === false
        || strpos($imagePath, $directory . DIRECTORY_SEPARATOR) !== 0
        || !is_file($imagePath)) {
        return false;
    }

    return in_array((new finfo(FILEINFO_MIME_TYPE))->file($imagePath), ["image/jpeg", "image/png", "image/webp"], true);
}

function project_media_uploads(array $files, string $directory): array
{
    $allowed_types = [
        "image/jpeg" => ["extension" => "jpg", "type" => "image", "max" => 5 * 1024 * 1024],
        "image/png" => ["extension" => "png", "type" => "image", "max" => 5 * 1024 * 1024],
        "image/webp" => ["extension" => "webp", "type" => "image", "max" => 5 * 1024 * 1024],
        "video/mp4" => ["extension" => "mp4", "type" => "video", "max" => 50 * 1024 * 1024],
        "video/webm" => ["extension" => "webm", "type" => "video", "max" => 50 * 1024 * 1024],
        "video/ogg" => ["extension" => "ogv", "type" => "video", "max" => 50 * 1024 * 1024],
    ];
    $uploaded = [];
    $errors = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    if (!isset($files["name"]) || !is_array($files["name"])) {
        return ["items" => [], "errors" => []];
    }

    foreach ($files["name"] as $index => $original_name) {
        if (($files["error"][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($files["error"][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = "One of the selected files could not be uploaded.";
            continue;
        }

        $temporary_path = $files["tmp_name"][$index];
        $mime = $finfo->file($temporary_path);
        if (!isset($allowed_types[$mime])) {
            $errors[] = htmlspecialchars($original_name, ENT_QUOTES, "UTF-8") . " is not a supported image or video.";
            continue;
        }
        if ((int) $files["size"][$index] > $allowed_types[$mime]["max"]) {
            $limit = $allowed_types[$mime]["type"] === "video" ? "50 MB" : "5 MB";
            $errors[] = htmlspecialchars($original_name, ENT_QUOTES, "UTF-8") . " must be {$limit} or smaller.";
            continue;
        }

        $filename = bin2hex(random_bytes(16)) . "." . $allowed_types[$mime]["extension"];
        $relative_path = "uploads/projects/" . $filename;
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($temporary_path, $destination)) {
            $errors[] = "One of the selected files could not be saved.";
            continue;
        }

        $uploaded[] = ["path" => $relative_path, "type" => $allowed_types[$mime]["type"]];
    }

    return ["items" => $uploaded, "errors" => $errors];
}

function delete_project_media_file(string $path): void
{
    if (str_starts_with($path, "uploads/projects/")) {
        @unlink(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $path);
    }
}
