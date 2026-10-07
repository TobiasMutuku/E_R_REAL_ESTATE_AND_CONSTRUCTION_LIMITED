<?php

require_once __DIR__ . "/../../includes/auth.php";
requireAdminPermission("projects");
require_once __DIR__ . "/../../includes/admin-form.php";
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/project-media.php";
require_once __DIR__ . "/../../includes/admin-audit.php";
if (!project_media_table_exists($conn)) {
    throw new RuntimeException("The project media table is missing. Apply the project media database migration.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../projects.php");
    exit;
}
if (!adminFormIsValid()) {
    http_response_code(403);
    exit("Invalid form submission. Return to the project list and try again.");
}

$project_id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
if (!$project_id) {
    header("Location: ../../projects.php");
    exit;
}

$select_stmt = $conn->prepare("SELECT image FROM projects WHERE id = ? LIMIT 1");
$select_stmt->bind_param("i", $project_id);
$select_stmt->execute();
$project_result = $select_stmt->get_result();
$project = $project_result->fetch_assoc();
$select_stmt->close();

$media_paths = [];
$media_stmt = $conn->prepare("SELECT media_path FROM project_media WHERE project_id = ?");
$media_stmt->bind_param("i", $project_id);
$media_stmt->execute();
$media_result = $media_stmt->get_result();
while ($media_item = $media_result->fetch_assoc()) {
    $media_paths[] = $media_item["media_path"];
}
$media_stmt->close();

if (!$project) {
    $conn->close();
    header("Location: ../../projects.php");
    exit;
}

$delete_stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
$delete_stmt->bind_param("i", $project_id);
$deleted = $delete_stmt->execute();
$delete_stmt->close();

$auditFailed = false;
if ($deleted) {
    try {
        recordAdminAudit($conn, "delete_project", "project", (int) $project_id);
    } catch (RuntimeException $exception) {
        error_log($exception->getMessage());
        $auditFailed = true;
    }
}

if ($deleted && !empty($project["image"]) && str_starts_with($project["image"], "uploads/projects/")) {
    delete_project_media_file($project["image"]);
}

if ($deleted) {
    foreach ($media_paths as $media_path) {
        delete_project_media_file($media_path);
    }
}

$conn->close();
header("Location: ../../projects.php?project=" . ($deleted ? "deleted" : "delete-error") . ($auditFailed ? "&audit=failed" : ""));
exit;
