<?php

session_start();

if (!isset($_SESSION["admin_id"]) || ($_SESSION["admin_role"] ?? "") !== "admin") {
    $script_directory = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
    $project_root = preg_replace("~/admin(?:/.*)?$~", "", $script_directory);
    header("Location: " . rtrim($project_root, "/") . "/admin/login.php");
    exit;
}