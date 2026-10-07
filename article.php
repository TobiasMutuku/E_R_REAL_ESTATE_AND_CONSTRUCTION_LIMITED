<?php
require_once __DIR__ . "/config/db.php";

$slug = trim((string) ($_GET["slug"] ?? ""));
$statement = $conn->prepare(
    "SELECT title, category, summary, content, image, published_at
     FROM articles
     WHERE slug = ? AND is_published = 1
     LIMIT 1"
);
$statement->bind_param("s", $slug);
$statement->execute();
$article = $statement->get_result()->fetch_assoc();
$statement->close();
if (!$article) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $article ? htmlspecialchars($article["title"], ENT_QUOTES, "UTF-8") . " | E&amp;R Insights" : "Article not found | E&amp;R Insights" ?></title>
    <meta name="description" content="<?= $article ? htmlspecialchars($article["summary"], ENT_QUOTES, "UTF-8") : "This article is unavailable." ?>">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container py-5">
        <a href="blog.php">&larr; All insights</a>
        <?php if ($article): ?>
            <main class="mt-4">
                <p class="text-primary text-uppercase font-weight-bold"><?= htmlspecialchars($article["category"], ENT_QUOTES, "UTF-8") ?></p>
                <h1><?= htmlspecialchars($article["title"], ENT_QUOTES, "UTF-8") ?></h1>
                <p class="text-muted"><?= htmlspecialchars(date("F j, Y", strtotime($article["published_at"])), ENT_QUOTES, "UTF-8") ?></p>
                <?php if ($article["image"]): ?><img class="img-fluid my-4" src="<?= htmlspecialchars($article["image"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($article["title"], ENT_QUOTES, "UTF-8") ?>" loading="lazy" decoding="async"><?php endif; ?>
                <p class="lead"><?= htmlspecialchars($article["summary"], ENT_QUOTES, "UTF-8") ?></p>
                <div class="article-content"><?= nl2br(htmlspecialchars($article["content"], ENT_QUOTES, "UTF-8")) ?></div>
                <a class="btn btn-primary mt-4" href="quote.php">Discuss your project</a>
            </main>
        <?php else: ?>
            <main class="mt-5"><h1>Article unavailable</h1><p>This article may have been unpublished or moved.</p></main>
        <?php endif; ?>
    </div>
    <script src="js/site-settings.js" defer></script>
</body>
</html>
<?php $conn->close(); ?>
