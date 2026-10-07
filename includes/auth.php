<?php

require_once __DIR__ . "/admin-session.php";
require_once __DIR__ . "/admin-permissions.php";
require_once __DIR__ . "/../config/db.php";

startAdminSession();
header("Cache-Control: no-store, private");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

if (!isset($_SESSION["admin_id"])) {
    header("Location: " . adminLoginPath());
    exit;
}

refreshAdminSession();
$adminId = filter_var($_SESSION["admin_id"], FILTER_VALIDATE_INT);
$statement = $adminId
    ? $conn->prepare("SELECT full_name, role, is_active FROM admin_users WHERE id = ? LIMIT 1")
    : false;
if (!$statement) {
    http_response_code(500);
    error_log("Unable to prepare administrator session check: " . $conn->error);
    exit("Administrator access could not be verified.");
}
$statement->bind_param("i", $adminId);
if (!$statement->execute()) {
    $statement->close();
    http_response_code(500);
    error_log("Unable to verify administrator session: " . $conn->error);
    exit("Administrator access could not be verified.");
}
$currentAdmin = $statement->get_result()->fetch_assoc();
$statement->close();

if (!$currentAdmin || !(int) $currentAdmin["is_active"]) {
    $_SESSION = [];
    session_regenerate_id(true);
    header("Location: " . adminLoginPath());
    exit;
}

$_SESSION["admin_name"] = $currentAdmin["full_name"];
$_SESSION["admin_role"] = $currentAdmin["role"];