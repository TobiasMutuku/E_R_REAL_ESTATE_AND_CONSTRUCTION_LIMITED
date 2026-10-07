<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/admin-form.php";
require_once __DIR__ . "/../config/db.php";

$token = adminFormToken();
$error = "";
$notice = "";
$uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "articles";
$editId = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT) ?: 0;
$formArticle = ["title" => "", "category" => "", "summary" => "", "content" => "", "image" => ""];

if ($editId) {
    $statement = $conn->prepare("SELECT title, category, summary, content, image FROM articles WHERE id = ? LIMIT 1");
    $statement->bind_param("i", $editId);
    $statement->execute();
    $formArticle = $statement->get_result()->fetch_assoc() ?: $formArticle;
    $statement->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = adminPostString("action");
    $id = filter_var(adminPostString("id"), FILTER_VALIDATE_INT);
    if (!adminFormIsValid()) {
        $error = "Your session token has expired. Refresh the page and try again.";
    } elseif ($action === "publish" && $id) {
        $confirmed = adminPostString("confirm_facts") === "1";
        $statement = $conn->prepare("SELECT title, category, summary, content, image FROM articles WHERE id = ? LIMIT 1");
        $statement->bind_param("i", $id);
        $statement->execute();
        $article = $statement->get_result()->fetch_assoc();
        $statement->close();
        if (!$article) {
            $error = "The selected article no longer exists.";
        } elseif (!$confirmed || trim($article["title"]) === "" || trim($article["category"]) === "" || trim($article["summary"]) === "" || trim($article["content"]) === "" || empty($article["image"])) {
            $error = "To publish, confirm the article is accurate and complete and has a suitable cover image.";
        } else {
            $statement = $conn->prepare("UPDATE articles SET is_published = 1, published_at = COALESCE(published_at, NOW()) WHERE id = ?");
            $statement->bind_param("i", $id);
            if ($statement->execute()) {
                $notice = "Article published.";
            } else {
                $error = "The article could not be published.";
            }
            $statement->close();
        }
    } elseif ($action === "withdraw" && $id) {
        $statement = $conn->prepare("UPDATE articles SET is_published = 0 WHERE id = ?");
        $statement->bind_param("i", $id);
        if ($statement->execute()) {
            $notice = "Article withdrawn from the public site.";
        } else {
            $error = "The article could not be withdrawn.";
        }
        $statement->close();
    } elseif ($action === "save") {
        $editId = filter_var(adminPostString("article_id"), FILTER_VALIDATE_INT) ?: 0;
        $formArticle = [
            "title" => trim(adminPostString("title")),
            "category" => trim(adminPostString("category")),
            "summary" => trim(adminPostString("summary")),
            "content" => trim(adminPostString("content")),
            "image" => $formArticle["image"] ?? "",
        ];
        if ($editId) {
            $statement = $conn->prepare("SELECT image FROM articles WHERE id = ? LIMIT 1");
            $statement->bind_param("i", $editId);
            $statement->execute();
            $existingArticle = $statement->get_result()->fetch_assoc();
            $statement->close();
            if ($existingArticle) {
                $formArticle["image"] = $existingArticle["image"] ?? "";
            } else {
                $error = "The article being edited no longer exists.";
            }
        }

        if ($error === "" && (
            $formArticle["title"] === "" || mb_strlen($formArticle["title"]) > 255
            || $formArticle["category"] === "" || mb_strlen($formArticle["category"]) > 80
            || $formArticle["summary"] === "" || mb_strlen($formArticle["summary"]) > 1000
            || $formArticle["content"] === "" || mb_strlen($formArticle["content"]) > 100000
        )) {
            $error = "Enter a title, category, summary, and article text within the stated limits.";
        }

        $image = $_FILES["image"] ?? null;
        $uploadedPath = "";
        if ($error === "" && $image && $image["error"] !== UPLOAD_ERR_NO_FILE) {
            if ($image["error"] !== UPLOAD_ERR_OK) {
                $error = "The article image could not be uploaded.";
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($image["tmp_name"]);
                $extensions = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
                if ($image["size"] > 5 * 1024 * 1024 || !isset($extensions[$mime])) {
                    $error = "Choose a JPG, PNG, or WebP article image no larger than 5 MB.";
                } elseif (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    $error = "The article image directory could not be prepared.";
                } else {
                    $filename = bin2hex(random_bytes(16)) . "." . $extensions[$mime];
                    if (move_uploaded_file($image["tmp_name"], $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
                        $uploadedPath = "uploads/articles/" . $filename;
                        $formArticle["image"] = $uploadedPath;
                    } else {
                        $error = "The article image could not be saved.";
                    }
                }
            }
        }

        if ($error === "") {
            $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $formArticle["title"]) ?? "", "-"));
            $slug = substr($baseSlug, 0, 245) . "-" . bin2hex(random_bytes(3));
            $imagePath = $formArticle["image"] !== "" ? $formArticle["image"] : null;

            if ($editId) {
                $statement = $conn->prepare("UPDATE articles SET title = ?, slug = ?, category = ?, summary = ?, content = ?, image = ?, is_published = 0, published_at = NULL WHERE id = ?");
                $statement->bind_param("ssssssi", $formArticle["title"], $slug, $formArticle["category"], $formArticle["summary"], $formArticle["content"], $imagePath, $editId);
            } else {
                $statement = $conn->prepare("INSERT INTO articles (title, slug, category, summary, content, image) VALUES (?, ?, ?, ?, ?, ?)");
                $statement->bind_param("ssssss", $formArticle["title"], $slug, $formArticle["category"], $formArticle["summary"], $formArticle["content"], $imagePath);
            }

            if ($statement->execute()) {
                $notice = "Article saved as an unpublished draft. Review and publish it when ready.";
                $editId = 0;
                $formArticle = ["title" => "", "category" => "", "summary" => "", "content" => "", "image" => ""];
            } else {
                if ($uploadedPath !== "") {
                    unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . $uploadedPath);
                }
                $error = "The article draft could not be saved.";
            }
            $statement->close();
        }
    } else {
        $error = "Invalid article management action.";
    }
}

$articles = $conn->query("SELECT id, title, category, summary, image, is_published, published_at FROM articles ORDER BY updated_at DESC");
if (!$articles) {
    throw new RuntimeException("Editorial records could not be loaded.");
}
$adminName = $_SESSION["admin_name"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editorial CMS | E&amp;R Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="sidebar-brand" href="dashboard.php"><img src="../logo/E&R Logo.jfif" alt=""><span><strong>E&amp;R</strong><span>Administration</span></span></a>
        <p class="workspace-label">Workspace</p>
        <nav class="sidebar-nav" aria-label="Administration navigation">
            <a class="sidebar-link" href="dashboard.php">Dashboard</a>
            <a class="sidebar-link" href="../projects.php">Projects</a>
            <a class="sidebar-link" href="content-review.php">Content review</a>
            <a class="sidebar-link" href="articles.php">Editorial CMS</a>
            <a class="sidebar-link" href="enquiries.php">Enquiries</a>
            <a class="sidebar-link" href="settings.php">Site settings</a>
        </nav>
        <div class="sidebar-bottom"><a class="sidebar-link sidebar-signout" href="logout.php">Sign out</a></div>
    </aside>
    <main class="admin-main">
        <header class="admin-header"><div class="breadcrumbs"><span>Workspace</span><strong>Editorial CMS</strong></div><div class="user-chip"><strong><?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?></strong></div></header>
        <section class="admin-content">
            <div class="page-intro"><div><p class="eyebrow">Insights</p><h1>Editorial content</h1><p class="intro-copy">Articles stay private until an administrator confirms accuracy and publishes them.</p></div></div>
            <?php if ($error !== ""): ?><p class="saved-state" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <?php if ($notice !== ""): ?><p class="saved-state" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
            <section class="panel settings-panel">
                <h2><?= $editId ? "Edit article draft" : "Write an article" ?></h2>
                <?php if (!empty($formArticle["image"])): ?><p>Current image: <?= htmlspecialchars(basename($formArticle["image"]), ENT_QUOTES, "UTF-8") ?></p><?php endif; ?>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="article_id" value="<?= (int) $editId ?>">
                    <div class="form-grid">
                        <label class="full-width">Title<input name="title" maxlength="255" required value="<?= htmlspecialchars($formArticle["title"], ENT_QUOTES, "UTF-8") ?>"></label>
                        <label>Category<input name="category" maxlength="80" required value="<?= htmlspecialchars($formArticle["category"], ENT_QUOTES, "UTF-8") ?>"></label>
                        <label>Cover image (JPG, PNG, WebP; max 5 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                        <label class="full-width">Summary<textarea name="summary" maxlength="1000" rows="3" required><?= htmlspecialchars($formArticle["summary"], ENT_QUOTES, "UTF-8") ?></textarea></label>
                        <label class="full-width">Article content (plain text)<textarea name="content" maxlength="100000" rows="12" required><?= htmlspecialchars($formArticle["content"], ENT_QUOTES, "UTF-8") ?></textarea></label>
                        <div class="settings-actions"><span>Saving returns the article to unpublished review.</span><button class="button button-primary" type="submit">Save draft</button></div>
                    </div>
                </form>
            </section>
            <section class="panel settings-panel">
                <h2>Saved articles</h2>
                <?php if ($articles->num_rows === 0): ?><p>No editorial articles have been created yet.</p><?php endif; ?>
                <?php while ($article = $articles->fetch_assoc()): ?>
                    <article class="activity-item">
                        <?php if ($article["image"]): ?><img src="../<?= htmlspecialchars($article["image"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($article["title"], ENT_QUOTES, "UTF-8") ?> cover photo" loading="lazy" style="width: 100px; height: 72px; object-fit: cover; border-radius: 6px;"><?php endif; ?>
                        <div><strong><?= htmlspecialchars($article["title"], ENT_QUOTES, "UTF-8") ?></strong><p><?= htmlspecialchars($article["category"], ENT_QUOTES, "UTF-8") ?> · <?= htmlspecialchars($article["summary"], ENT_QUOTES, "UTF-8") ?></p><small><?= $article["is_published"] ? "Published " . htmlspecialchars($article["published_at"], ENT_QUOTES, "UTF-8") : "Unpublished draft" ?></small></div>
                        <div>
                            <a class="button button-quiet" href="articles.php?edit=<?= (int) $article["id"] ?>">Edit</a>
                            <?php if ($article["is_published"]): ?>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>"><input type="hidden" name="action" value="withdraw"><input type="hidden" name="id" value="<?= (int) $article["id"] ?>"><button class="button button-danger" type="submit">Withdraw</button></form>
                            <?php else: ?>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int) $article["id"] ?>"><label class="remember-option"><input type="checkbox" name="confirm_facts" value="1" required> I reviewed this article for factual accuracy and publication readiness.</label><button class="button button-primary" type="submit">Publish</button></form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            </section>
        </section>
    </main>
</div>
<script src="../js/admin-image-optimizer.js"></script>
</body>
</html>
