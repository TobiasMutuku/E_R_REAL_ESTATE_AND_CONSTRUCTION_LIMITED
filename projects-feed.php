<?php

header("Content-Type: application/json; charset=utf-8");

try {
    require_once __DIR__ . "/config/db.php";
    require_once __DIR__ . "/admin/projects/project-media.php";

    if (!ensure_project_media_table($conn)) {
        throw new RuntimeException("Unable to prepare project media records.");
    }

$sql = "SELECT id, project_name, location, description, project_status, start_date, completion_date, image
        FROM projects
        WHERE is_published = 1
        ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "Unable to load projects."]);
    $conn->close();
    exit;
}

$projects = [];
while ($project = $result->fetch_assoc()) {
    $media = [];
    $media_stmt = $conn->prepare("SELECT media_path, media_type FROM project_media WHERE project_id = ? ORDER BY sort_order, id");
    $media_stmt->bind_param("i", $project["id"]);
    $media_stmt->execute();
    $media_result = $media_stmt->get_result();
    while ($media_item = $media_result->fetch_assoc()) {
        $media[] = ["path" => $media_item["media_path"], "type" => $media_item["media_type"]];
    }
    $media_stmt->close();
    if ($media === [] && !empty($project["image"])) {
        $media[] = ["path" => $project["image"], "type" => "image"];
    }
    $projects[] = [
        "id" => (int) $project["id"],
        "project_name" => $project["project_name"],
        "location" => $project["location"],
        "description" => $project["description"],
        "project_status" => $project["project_status"],
        "start_date" => $project["start_date"],
        "completion_date" => $project["completion_date"],
        "image" => $project["image"],
        "media" => $media,
    ];
}

echo json_encode($projects, JSON_UNESCAPED_SLASHES);
$conn->close();
} catch (mysqli_sql_exception | RuntimeException $exception) {
    error_log("Projects feed error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Unable to load current project records."]);
}
