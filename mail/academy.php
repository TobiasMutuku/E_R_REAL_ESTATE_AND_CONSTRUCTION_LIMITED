<?php
declare(strict_types=1);

try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/site-settings.php';
    require_once __DIR__ . '/../includes/site-mail.php';
    $notificationEmail = loadSiteSettings($conn)['public_email'];
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
$statement = $conn->prepare(
    "INSERT INTO academy_enquiries (full_name, email, phone, course, message)
     VALUES (?, ?, ?, ?, ?)"
);
if (!$statement) {
    error_log('Academy enquiry storage could not be prepared: ' . $conn->error);
    $conn->close();
    academyRespond(500, 'The enquiry could not be saved. Please try again later.');
}
$statement->bind_param('sssss', $fullName, $email, $phone, $course, $message);
if (!$statement->execute()) {
    error_log('Academy enquiry storage failed: ' . $statement->error);
    $statement->close();
    $conn->close();
    academyRespond(500, 'The enquiry could not be saved. Please try again later.');
}
$enquiryId = (int) $conn->insert_id;
$statement->close();

$body = "New course enquiry from the website.\n\nName: {$fullName}\nEmail: {$email}\nPhone: {$phone}\nProgramme: {$courses[$course]}\n\nLearning goals:\n{$message}\n";
$sent = sendSiteNotification(
    $notificationEmail,
    "Academy enquiry: {$courses[$course]} - {$fullName}",
    $body,
    $email
);
$status = $sent ? 'sent' : 'failed';
$update = $conn->prepare("UPDATE academy_enquiries SET notification_status = ? WHERE id = ?");
if ($update) {
    $update->bind_param('si', $status, $enquiryId);
    if (!$update->execute()) {
        error_log('Academy notification status could not be updated: ' . $update->error);
    }
    $update->close();
} else {
    error_log('Academy notification status update could not be prepared: ' . $conn->error);
}
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['message' => 'Your enquiry has been received and saved. Our team will follow up.'], JSON_THROW_ON_ERROR);
