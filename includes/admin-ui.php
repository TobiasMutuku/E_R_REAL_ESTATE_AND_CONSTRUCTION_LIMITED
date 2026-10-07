<?php
declare(strict_types=1);

require_once __DIR__ . "/admin-form.php";

function renderAdminSidebar(string $active, string $context = "admin"): void
{
    $prefix = match ($context) {
        "root" => [
            "logo" => "logo/E&R Logo.jfif",
            "dashboard" => "admin/dashboard.php",
            "projects" => "projects.php",
            "add_project" => "admin/projects/add-project.php",
            "content" => "admin/content-review.php",
            "articles" => "admin/articles.php",
            "enquiries" => "admin/enquiries.php",
            "settings" => "admin/settings.php",
            "accounts" => "admin/accounts.php",
            "audit" => "admin/audit-log.php",
            "public" => "index.html",
            "logout" => "admin/logout.php",
        ],
        "projects" => [
            "logo" => "../../logo/E&R Logo.jfif",
            "dashboard" => "../dashboard.php",
            "projects" => "../../projects.php",
            "add_project" => "add-project.php",
            "content" => "../content-review.php",
            "articles" => "../articles.php",
            "enquiries" => "../enquiries.php",
            "settings" => "../settings.php",
            "accounts" => "../accounts.php",
            "audit" => "../audit-log.php",
            "public" => "../../index.html",
            "logout" => "../logout.php",
        ],
        default => [
            "logo" => "../logo/E&R Logo.jfif",
            "dashboard" => "dashboard.php",
            "projects" => "../projects.php",
            "add_project" => "projects/add-project.php",
            "content" => "content-review.php",
            "articles" => "articles.php",
            "enquiries" => "enquiries.php",
            "settings" => "settings.php",
            "accounts" => "accounts.php",
            "audit" => "audit-log.php",
            "public" => "../index.html",
            "logout" => "logout.php",
        ],
    };

    $items = [
        ["dashboard", "dashboard", "fa-chart-pie", "Dashboard"],
        ["projects", "projects", "fa-building", "Projects"],
        ["add_project", "projects", "fa-plus", "Add project"],
        ["content", "content", "fa-clipboard-check", "Content review"],
        ["articles", "articles", "fa-newspaper", "Editorial CMS"],
        ["enquiries", "enquiries", "fa-inbox", "Enquiries"],
        ["settings", "settings", "fa-cog", "Site settings"],
        ["accounts", "accounts", "fa-users-cog", "Team access"],
        ["audit", "audit", "fa-clipboard-list", "Audit log"],
        ["public", null, "fa-globe", "Public website"],
    ];
    ?>
    <aside class="admin-sidebar">
        <a class="sidebar-brand" href="<?= htmlspecialchars($prefix["dashboard"], ENT_QUOTES, "UTF-8") ?>">
            <img src="<?= htmlspecialchars($prefix["logo"], ENT_QUOTES, "UTF-8") ?>" alt="E&amp;R logo">
            <span><strong>E&amp;R</strong><span>Administration</span></span>
        </a>
        <p class="workspace-label">Workspace</p>
        <nav class="sidebar-nav" aria-label="Administration navigation">
            <?php foreach ($items as [$key, $permission, $icon, $label]): ?>
                <?php if ($permission === null || adminCan($permission)): ?>
                    <a class="sidebar-link <?= $active === $key ? "active" : "" ?>" href="<?= htmlspecialchars($prefix[$key], ENT_QUOTES, "UTF-8") ?>" <?= $active === $key ? 'aria-current="page"' : "" ?>>
                        <i class="fas <?= htmlspecialchars($icon, ENT_QUOTES, "UTF-8") ?>" aria-hidden="true"></i><?= htmlspecialchars($label, ENT_QUOTES, "UTF-8") ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
            <form method="post" action="<?= htmlspecialchars($prefix["logout"], ENT_QUOTES, "UTF-8") ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(adminFormToken(), ENT_QUOTES, "UTF-8") ?>">
                <button class="sidebar-link sidebar-signout sidebar-signout-button" type="submit"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign out</button>
            </form>
        </div>
    </aside>
    <?php
}
