<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, max-age=0");

try {
    require_once __DIR__ . "/config/db.php";
    require_once __DIR__ . "/includes/site-settings.php";

    echo json_encode(loadSiteSettings($conn), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $conn->close();
} catch (Throwable $exception) {
    error_log("Public site settings error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Site settings are temporarily unavailable."]);
}
