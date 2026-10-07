<?php

function ensure_project_media_table(mysqli $conn): bool
{
    return (bool) $conn->query(
        "CREATE TABLE IF NOT EXISTS project_media (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            media_path VARCHAR(255) NOT NULL,
            media_type ENUM('image', 'video') NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX project_media_project_idx (project_id),
            CONSTRAINT project_media_project_fk FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
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
