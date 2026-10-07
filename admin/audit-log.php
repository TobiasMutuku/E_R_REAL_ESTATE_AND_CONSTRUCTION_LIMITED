<?php
require_once __DIR__ . "/../includes/auth.php";
requireAdminPermission("audit");
require_once __DIR__ . "/../includes/admin-ui.php";
require_once __DIR__ . "/../config/db.php";

$events = $conn->query("SELECT actor_name, actor_role, action, entity_type, entity_id, details, remote_address, created_at FROM admin_audit_logs ORDER BY created_at DESC LIMIT 200");
if (!$events) {
    throw new RuntimeException("Administrator audit events could not be loaded.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit log | E&amp;R Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="admin-shell">
    <?php renderAdminSidebar("audit"); ?>
    <main class="admin-main">
        <header class="admin-header"><div class="breadcrumbs"><span>Workspace</span><strong>Audit log</strong></div><div class="user-chip"><strong><?= htmlspecialchars($_SESSION["admin_name"] ?? "Administrator", ENT_QUOTES, "UTF-8") ?></strong></div></header>
        <section class="admin-content">
            <div class="page-intro"><div><p class="eyebrow">Accountability</p><h1>Administrator activity</h1><p class="intro-copy">Recent sign-ins, publication decisions, and account changes. The latest 200 events are shown.</p></div></div>
            <section class="panel table-panel">
                <div class="table-toolbar"><strong>Audit trail</strong><span class="table-meta"><?= $events->num_rows ?> events</span></div>
                <div class="table-wrap"><table class="audit-table"><thead><tr><th>Date and time</th><th>Administrator</th><th>Action</th><th>Record</th><th>Details</th><th>Network</th></tr></thead><tbody>
                    <?php if ($events->num_rows === 0): ?><tr><td colspan="6">No administrator activity has been recorded yet.</td></tr><?php endif; ?>
                    <?php while ($event = $events->fetch_assoc()): ?>
                        <?php $details = $event["details"] ? json_decode($event["details"], true) : null; ?>
                        <tr><td><?= htmlspecialchars($event["created_at"], ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars($event["actor_name"], ENT_QUOTES, "UTF-8") ?><small><?= htmlspecialchars($event["actor_role"], ENT_QUOTES, "UTF-8") ?></small></td><td><?= htmlspecialchars(str_replace("_", " ", $event["action"]), ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars($event["entity_type"], ENT_QUOTES, "UTF-8") ?><?= $event["entity_id"] ? " #" . (int) $event["entity_id"] : "" ?></td><td><?= htmlspecialchars(is_array($details) ? json_encode($details, JSON_UNESCAPED_SLASHES) : "", ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars($event["remote_address"] ?? "", ENT_QUOTES, "UTF-8") ?></td></tr>
                    <?php endwhile; ?>
                </tbody></table></div>
            </section>
        </section>
    </main>
</div>
<script src="../js/admin-shell.js" defer></script>
</body>
</html>
