<?php
declare(strict_types=1);

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();
}

function refreshAdminSession(): void
{
    $now = time();
    $lastActivity = (int) ($_SESSION["admin_last_activity"] ?? $now);
    $createdAt = (int) ($_SESSION["admin_created_at"] ?? $now);

    if ($now - $lastActivity > 1800 || $now - $createdAt > 28800) {
        $_SESSION = [];
        session_regenerate_id(true);
        header("Location: " . adminLoginPath());
        exit;
    }

    if ($now - (int) ($_SESSION["admin_regenerated_at"] ?? 0) > 900) {
        session_regenerate_id(true);
        $_SESSION["admin_regenerated_at"] = $now;
    }

    $_SESSION["admin_created_at"] = $createdAt;
    $_SESSION["admin_last_activity"] = $now;
}

function adminLoginPath(): string
{
    $scriptDirectory = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
    $projectRoot = preg_replace("~/admin(?:/.*)?$~", "", $scriptDirectory) ?? "";
    return rtrim($projectRoot, "/") . "/admin/login.php";
}
