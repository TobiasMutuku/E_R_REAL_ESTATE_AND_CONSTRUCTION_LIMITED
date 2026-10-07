<?php
declare(strict_types=1);

header("Content-Type: text/plain; charset=utf-8");

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /config/\n";
echo "Disallow: /database/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /mail/\n";
echo "Disallow: /uploads/quotes/\n";

$baseUrl = rtrim(trim((string) getenv("ER_PUBLIC_BASE_URL")), "/");
$baseParts = filter_var($baseUrl, FILTER_VALIDATE_URL) ? parse_url($baseUrl) : false;
if ($baseParts && isset($baseParts["scheme"], $baseParts["host"])
    && in_array($baseParts["scheme"], in_array($baseParts["host"], ["localhost", "127.0.0.1"], true)
        ? ["http", "https"]
        : ["https"], true)
    && !isset($baseParts["user"], $baseParts["pass"], $baseParts["query"], $baseParts["fragment"])) {
    echo "Sitemap: {$baseUrl}/sitemap.php\n";
}
