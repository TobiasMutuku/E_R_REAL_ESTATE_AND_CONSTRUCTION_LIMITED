<?php
declare(strict_types=1);

$configuredBaseUrl = trim((string) getenv("ER_PUBLIC_BASE_URL"));
$baseUrl = rtrim($configuredBaseUrl, "/");
$baseParts = filter_var($baseUrl, FILTER_VALIDATE_URL) ? parse_url($baseUrl) : false;
$isLocalHost = is_array($baseParts)
    && in_array($baseParts["host"] ?? "", ["localhost", "127.0.0.1"], true);

if (!$baseParts || !isset($baseParts["scheme"], $baseParts["host"])
    || !in_array($baseParts["scheme"], $isLocalHost ? ["http", "https"] : ["https"], true)
    || isset($baseParts["user"]) || isset($baseParts["pass"]) || isset($baseParts["query"]) || isset($baseParts["fragment"])) {
    http_response_code(503);
    header("Content-Type: text/plain; charset=utf-8");
    exit("Set ER_PUBLIC_BASE_URL to the canonical public website URL to generate the sitemap.");
}

$paths = [
    "",
    "about.html",
    "service.html",
    "constructions.html",
    "real-estate.html",
    "blog.php",
    "geolocation.html",
    "contact.php",
    "quote.php",
    "privacy.html",
];

try {
    require_once __DIR__ . "/config/db.php";
    $projects = $conn->query("SELECT id FROM projects WHERE is_published = 1");
    if ($projects === false) {
        throw new RuntimeException("Published projects could not be loaded for sitemap generation.");
    }
    while ($project = $projects->fetch_assoc()) {
        $paths[] = "project-details.php?id=" . rawurlencode((string) $project["id"]);
    }

    $articles = $conn->query("SELECT slug FROM articles WHERE is_published = 1");
    if ($articles === false) {
        throw new RuntimeException("Published articles could not be loaded for sitemap generation.");
    }
    while ($article = $articles->fetch_assoc()) {
        $paths[] = "article.php?slug=" . rawurlencode($article["slug"]);
    }
    $conn->close();
} catch (Throwable $exception) {
    error_log("Sitemap generation failed: " . $exception->getMessage());
    http_response_code(503);
    header("Content-Type: text/plain; charset=utf-8");
    exit("The sitemap is temporarily unavailable.");
}

header("Content-Type: application/xml; charset=utf-8");
header("Cache-Control: public, max-age=3600");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($paths as $path) {
    $url = htmlspecialchars($baseUrl . "/" . $path, ENT_XML1 | ENT_QUOTES, "UTF-8");
    echo "  <url><loc>{$url}</loc></url>\n";
}
echo "</urlset>\n";
