<?php
declare(strict_types=1);

function recordAdminAudit(
    mysqli $connection,
    string $action,
    string $entityType,
    ?int $entityId = null,
    array $details = []
): void {
    $actorId = filter_var($_SESSION["admin_id"] ?? null, FILTER_VALIDATE_INT);
    $actorName = (string) ($_SESSION["admin_name"] ?? "Administrator");
    $actorRole = (string) ($_SESSION["admin_role"] ?? "unknown");
    $encodedDetails = $details === []
        ? null
        : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($encodedDetails === false) {
        throw new RuntimeException("Audit details could not be encoded.");
    }
    $remoteAddress = filter_var($_SERVER["REMOTE_ADDR"] ?? "", FILTER_VALIDATE_IP)
        ? (string) $_SERVER["REMOTE_ADDR"]
        : null;

    $statement = $connection->prepare(
        "INSERT INTO admin_audit_logs
            (actor_admin_id, actor_name, actor_role, action, entity_type, entity_id, details, remote_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$statement) {
        throw new RuntimeException("The administrator audit event could not be prepared.");
    }

    $statement->bind_param(
        "issssiss",
        $actorId,
        $actorName,
        $actorRole,
        $action,
        $entityType,
        $entityId,
        $encodedDetails,
        $remoteAddress
    );
    if (!$statement->execute()) {
        $statement->close();
        throw new RuntimeException("The administrator audit event could not be saved.");
    }
    $statement->close();
}
