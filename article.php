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
    <link rel="icon" href="logo/E&R Logo.jfif">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=20261007-site-audit">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="container-fluid nav-bar p-0">
        <div class="container-lg p-0">
            <nav class="navbar navbar-expand-lg bg-secondary navbar-dark" aria-label="Main navigation">
                <a href="index.html" class="navbar-brand d-flex align-items-center">
                    <img src="logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo">
                </a>
                <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse"
                    aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ml-auto py-0">
                        <a href="index.html" class="nav-item nav-link">Home</a>
                        <a href="about.html" class="nav-item nav-link">About</a>
                        <a href="service.html" class="nav-item nav-link">Services</a>
                        <a href="constructions.html" class="nav-item nav-link">Projects</a>
                        <a href="real-estate.html" class="nav-item nav-link">Properties</a>
                        <a href="quote.php" class="nav-item nav-link">Get a Quote</a>
                        <a href="contact.php" class="nav-item nav-link">Contact</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>
    <div class="container py-5">
        <a href="blog.php">&larr; All insights</a>
        <?php if ($article): ?>
            <main class="mt-4" id="main-content">
                <p class="text-primary text-uppercase font-weight-bold"><?= htmlspecialchars($article["category"], ENT_QUOTES, "UTF-8") ?></p>
                <h1><?= htmlspecialchars($article["title"], ENT_QUOTES, "UTF-8") ?></h1>
                <p class="text-muted"><?= htmlspecialchars(date("F j, Y", strtotime($article["published_at"])), ENT_QUOTES, "UTF-8") ?></p>
                <?php if ($article["image"]): ?><img class="img-fluid my-4" src="<?= htmlspecialchars($article["image"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($article["title"], ENT_QUOTES, "UTF-8") ?>" loading="lazy" decoding="async"><?php endif; ?>
                <p class="lead"><?= htmlspecialchars($article["summary"], ENT_QUOTES, "UTF-8") ?></p>
                <div class="article-content"><?= nl2br(htmlspecialchars($article["content"], ENT_QUOTES, "UTF-8")) ?></div>
                <a class="btn btn-primary mt-4" href="quote.php">Discuss your project</a>
            </main>
        <?php else: ?>
            <main class="mt-5" id="main-content"><h1>Article unavailable</h1><p>This article may have been unpublished or moved.</p><a href="blog.php">Browse current insights</a></main>
        <?php endif; ?>
    </div>
    <footer class="container-fluid bg-secondary text-white mt-5 pt-5 px-sm-3 px-md-5">
        <div class="row pt-5">
            <div class="col-lg-4 col-md-6 mb-4">
                <a href="index.html" class="navbar-brand footer-brand d-flex align-items-center"><img src="logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo"></a>
                <p>Construction and property guidance from E&amp;R Real Estate and Construction Limited.</p>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <h5 class="font-weight-bold text-primary mb-4">Explore E&amp;R</h5>
                <div class="d-flex flex-column">
                    <a class="text-white mb-2" href="service.html">Services</a>
                    <a class="text-white mb-2" href="constructions.html">Projects</a>
                    <a class="text-white mb-2" href="real-estate.html">Properties</a>
                    <a class="text-white mb-2" href="quote.php">Request a quote</a>
                    <a class="text-white" href="privacy.html">Privacy notice</a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <h5 class="font-weight-bold text-primary mb-4">Get in touch</h5>
                <a class="text-white d-block mb-2" href="tel:+254748766822">+254 748 766 822</a>
                <a class="text-white" href="mailto:errealestateconstruction@gmail.com">errealestateconstruction@gmail.com</a>
            </div>
        </div>
        <div class="container-fluid py-4 px-0">
            <p class="m-0 text-center">&copy; <span data-current-year>2026</span> E&amp;R Real Estate and Construction Limited. All rights reserved.</p>
        </div>
    </footer>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    <script src="js/site-settings.js" defer></script>
</body>
</html>
<?php $conn->close(); ?>
