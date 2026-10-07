<?php
header("Content-Type: application/json; charset=utf-8");

try {
    require_once __DIR__ . "/config/db.php";
    $result = $conn->query(
        "SELECT title, slug, category, summary, image, published_at
         FROM articles
         WHERE is_published = 1
         ORDER BY published_at DESC, id DESC"
    );
    if ($result === false) {
        throw new RuntimeException("Unable to query published articles.");
    }
    $articles = [];
    while ($article = $result->fetch_assoc()) {
        $articles[] = $article;
    }
    echo json_encode($articles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $conn->close();
} catch (Throwable $exception) {
    error_log("Articles feed error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Published articles are temporarily unavailable."]);
}
