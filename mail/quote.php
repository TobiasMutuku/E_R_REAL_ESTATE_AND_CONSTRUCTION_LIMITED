<?php
declare(strict_types=1);

try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/site-settings.php';
    $notificationEmail = loadSiteSettings($conn)['public_email'];
    $conn->close();
} catch (Throwable $exception) {
    error_log('Quote notification settings error: ' . $exception->getMessage());
    http_response_code(500);
    exit();
}

function quoteText(string $value, int $limit, bool $singleLine = true): string
{
    $value = strip_tags(trim($value));
    if ($singleLine) {
        $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    }
    return substr($value, 0, $limit);
}
function rejectQuote(int $status): void { http_response_code($status); exit(); }

function quoteRateLimit(): bool
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'er-form-rate-limit';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) return false;
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file = $directory . DIRECTORY_SEPARATOR . hash('sha256', 'quote|' . $ip);
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) return false;
    $lastRequest = (int) stream_get_contents($handle);
    $allowed = time() - $lastRequest >= 300;
    if ($allowed) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) time());
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $allowed;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['website'] ?? '')) rejectQuote(400);
if (!quoteRateLimit()) rejectQuote(429);
$name = quoteText((string) ($_POST['name'] ?? ''), 100);
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$phone = quoteText((string) ($_POST['phone'] ?? ''), 40);
$service = quoteText((string) ($_POST['service'] ?? ''), 100);
$location = quoteText((string) ($_POST['location'] ?? ''), 150);
$budget = quoteText((string) ($_POST['budget'] ?? 'Not specified'), 100);
$timeline = quoteText((string) ($_POST['timeline'] ?? 'Not specified'), 100);
$message = quoteText((string) ($_POST['message'] ?? ''), 5000, false);
if ($name === '' || $email === false || $phone === '' || $service === '' || $location === '' || $message === '') rejectQuote(422);

$body = "New quote request from the website.\n\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\nService: {$service}\nLocation: {$location}\nBudget: {$budget}\nTimeline: {$timeline}\n\nProject brief:\n{$message}\n";
$headers = ['From: E&R Website <website@errealestate.co.ke>', "Reply-To: {$email}", 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8'];
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) rejectQuote(422);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) rejectQuote(422);
    $boundary = '=_ER_' . bin2hex(random_bytes(12));
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['name']));
    $headers[3] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}\r\n--{$boundary}\r\nContent-Type: {$mime}; name=\"{$filename}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n" . chunk_split(base64_encode((string) file_get_contents($file['tmp_name']))) . "--{$boundary}--\r\n";
}
if (!mail($notificationEmail, "Quote request: {$service} - {$name}", $body, implode("\r\n", $headers))) rejectQuote(500);
http_response_code(204);
