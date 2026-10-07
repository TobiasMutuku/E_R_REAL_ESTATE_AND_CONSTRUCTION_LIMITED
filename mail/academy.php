<?php
declare(strict_types=1);

try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/site-settings.php';
    $notificationEmail = loadSiteSettings($conn)['public_email'];
    $conn->close();
} catch (Throwable $exception) {
    error_log('Academy notification settings error: ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'The enquiry could not be sent.']);
    exit();
}

function academyRespond(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => $message], JSON_THROW_ON_ERROR);
    exit();
}

function academyText(string $value, int $limit, bool $singleLine = true): string
{
    $value = strip_tags(trim($value));
    if ($singleLine) {
        $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    }
    return substr($value, 0, $limit);
}

function academyRateLimit(): bool
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'er-form-rate-limit';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        return false;
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file = $directory . DIRECTORY_SEPARATOR . hash('sha256', 'academy|' . $ip);
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
    academyRespond(405, 'Method not allowed.');
}
if (!empty($_POST['website'] ?? '')) {
    academyRespond(400, 'Invalid submission.');
}
if (!academyRateLimit()) {
    academyRespond(429, 'Please wait before sending another enquiry.');
}

$firstName = academyText((string) ($_POST['first_name'] ?? ''), 100);
$surname = academyText((string) ($_POST['surname'] ?? ''), 100);
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$phone = academyText((string) ($_POST['phone'] ?? ''), 40);
$course = academyText((string) ($_POST['course'] ?? ''), 40);
$message = academyText((string) ($_POST['message'] ?? ''), 5000, false);
$courses = [
    'architecture' => 'Architecture & design',
    'construction' => 'Building & construction',
    'plumbing' => 'Plumbing',
    'electrical' => 'Electrical installation',
];

if (
    $firstName === '' || $surname === '' || $email === false || $phone === '' ||
    !isset($courses[$course]) || $message === ''
) {
    academyRespond(422, 'Please complete all required fields with valid details.');
}

$fullName = $firstName . ' ' . $surname;
$body = "New course enquiry from the website.\n\nName: {$fullName}\nEmail: {$email}\nPhone: {$phone}\nProgramme: {$courses[$course]}\n\nLearning goals:\n{$message}\n";
$headers = [
    'From: E&R Website <website@errealestate.co.ke>',
    "Reply-To: {$email}",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];

if (!mail($notificationEmail, "Academy enquiry: {$courses[$course]} - {$fullName}", $body, implode("\r\n", $headers))) {
    error_log('Academy enquiry email could not be sent.');
    academyRespond(500, 'The enquiry could not be sent.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['message' => 'Enquiry sent.'], JSON_THROW_ON_ERROR);
