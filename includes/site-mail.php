<?php
declare(strict_types=1);

function sendSiteNotification(
    string $recipient,
    string $subject,
    string $body,
    string $replyTo = ""
): bool {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        error_log("Site notification recipient is not a valid email address.");
        return false;
    }

    $from = trim((string) (getenv("ER_MAIL_FROM") ?: $recipient));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log("ER_MAIL_FROM must be configured to a valid email address.");
        return false;
    }

    $headers = [
        "From: E&R Website <{$from}>",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
    ];
    if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = "Reply-To: " . str_replace(["\r", "\n"], "", $replyTo);
    }

    $safeSubject = trim(str_replace(["\r", "\n"], "", $subject));
    $sent = mail($recipient, $safeSubject, $body, implode("\r\n", $headers));
    if (!$sent) {
        error_log("Site email notification could not be handed to the configured mail transport.");
    }

    return $sent;
}
