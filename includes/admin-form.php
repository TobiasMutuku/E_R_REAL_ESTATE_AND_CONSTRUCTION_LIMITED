<?php
declare(strict_types=1);

function adminFormToken(): string
{
    if (empty($_SESSION["admin_csrf_token"])) {
        $_SESSION["admin_csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["admin_csrf_token"];
}

function adminFormIsValid(): bool
{
    $submittedToken = $_POST["csrf_token"] ?? "";
    return is_string($submittedToken)
        && isset($_SESSION["admin_csrf_token"])
        && hash_equals($_SESSION["admin_csrf_token"], $submittedToken);
}

function adminPostString(string $key, string $default = ""): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? $value : $default;
}
