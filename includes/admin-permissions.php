<?php
declare(strict_types=1);

function adminRolePermissions(string $role): array
{
    return match ($role) {
        "admin" => ["dashboard", "projects", "content", "articles", "enquiries", "settings", "accounts", "audit"],
        "content_editor" => ["dashboard", "projects", "content", "articles"],
        "enquiry_manager" => ["dashboard", "enquiries"],
        default => [],
    };
}

function adminCan(string $permission): bool
{
    return in_array($permission, adminRolePermissions((string) ($_SESSION["admin_role"] ?? "")), true);
}

function requireAdminPermission(string $permission): void
{
    if (adminCan($permission)) {
        return;
    }

    http_response_code(403);
    header("Content-Type: text/plain; charset=utf-8");
    exit("You do not have permission to access this administration feature.");
}
