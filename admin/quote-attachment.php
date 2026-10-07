<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

$quoteId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$quoteId) {
    http_response_code(404);
    exit("Attachment not found.");
}

$statement = $conn->prepare("SELECT attachment FROM quote_requests WHERE id = ? LIMIT 1");
if (!$statement) {
    throw new RuntimeException("Unable to prepare attachment lookup.");
}
$statement->bind_param("i", $quoteId);
$statement->execute();
$record = $statement->get_result()->fetch_assoc();
$statement->close();
$conn->close();

$relativePath = $record["attachment"] ?? "";
if (!preg_match('/\Auploads\/quotes\/quote_[a-f0-9]{32}\.(pdf|jpg|png)\z/', $relativePath, $matches)) {
    http_response_code(404);
    exit("Attachment not found.");
}

$quotesDirectory = realpath(__DIR__ . "/../uploads/quotes");
$attachmentPath = realpath(__DIR__ . "/../" . $relativePath);
if ($quotesDirectory === false || $attachmentPath === false
    || strpos($attachmentPath, $quotesDirectory . DIRECTORY_SEPARATOR) !== 0
    || !is_file($attachmentPath) || !is_readable($attachmentPath)) {
    http_response_code(404);
    exit("Attachment not found.");
}

$mimeTypes = ["pdf" => "application/pdf", "jpg" => "image/jpeg", "png" => "image/png"];
header("Content-Type: " . $mimeTypes[$matches[1]]);
header("Content-Disposition: attachment; filename=\"quote-{$quoteId}.{$matches[1]}\"");
header("Content-Length: " . (string) filesize($attachmentPath));
header("Cache-Control: private, no-store");
readfile($attachmentPath);
