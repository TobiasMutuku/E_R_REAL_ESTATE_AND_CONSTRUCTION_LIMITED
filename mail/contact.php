<?php
declare(strict_types=1);

try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/site-settings.php';
    $notificationEmail = loadSiteSettings($conn)['public_email'];
    $conn->close();
} catch (Throwable $exception) {
    error_log('Contact notification settings error: ' . $exception->getMessage());
    http_response_code(500);
    exit();
}

function contactText(string $value, int $limit): string
{
    $value = strip_tags(trim($value));
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    return substr($value, 0, $limit);
}

function contactRateLimit(): bool
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'er-form-rate-limit';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        return false;
    }
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file = $directory . DIRECTORY_SEPARATOR . hash('sha256', 'contact|' . $ip);
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        return false;
    }
    $lastRequest = (int) stream_get_contents($handle);
    $allowed = time() - $lastRequest >= 60;
    if ($allowed) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) time());
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $allowed;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}
if (!empty($_POST['website'] ?? '')) {
    http_response_code(400);
    exit();
}
if (!contactRateLimit()) {
    http_response_code(429);
    exit();
}

$name = contactText((string) ($_POST['name'] ?? ''), 100);
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$subjectText = contactText((string) ($_POST['subject'] ?? ''), 150);
$message = contactText((string) ($_POST['message'] ?? ''), 5000);
if ($name === '' || $email === false || $subjectText === '' || $message === '') {
    http_response_code(422);
    exit();
}

$body = "New message from the website contact form.\n\nName: {$name}\nEmail: {$email}\nSubject: {$subjectText}\n\nMessage:\n{$message}\n";
$headers = [
    'From: E&R Website <website@errealestate.co.ke>',
    "Reply-To: {$email}",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];
if (!mail($notificationEmail, 'Website contact: ' . $subjectText, $body, implode("\r\n", $headers))) {
    http_response_code(500);
    exit();
}
http_response_code(204);
