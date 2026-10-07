<?php
declare(strict_types=1);

function publicFormPostString(string $key): string
{
    $value = $_POST[$key] ?? "";
    return is_string($value) ? trim($value) : "";
}

function publicFormRateLimit(string $form, int $intervalSeconds): bool
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "er-public-form-limits";
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log("Unable to create public form rate-limit directory.");
        return false;
    }

    if (random_int(1, 100) === 1) {
        $now = time();
        foreach (scandir($directory) ?: [] as $entry) {
            if (!preg_match('/\A[a-f0-9]{64}\z/', $entry)) {
                continue;
            }
            $expiredFile = $directory . DIRECTORY_SEPARATOR . $entry;
            $modifiedAt = is_file($expiredFile) ? filemtime($expiredFile) : false;
            if ($modifiedAt !== false && $now - $modifiedAt > 86400) {
                unlink($expiredFile);
            }
        }
    }

    $ip = (string) ($_SERVER["REMOTE_ADDR"] ?? "unknown");
    $file = $directory . DIRECTORY_SEPARATOR . hash("sha256", $form . "|" . $ip);
    $handle = fopen($file, "c+");
    if ($handle === false) {
        error_log("Unable to open public form rate-limit record.");
        return false;
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        error_log("Unable to lock public form rate-limit record.");
        return false;
    }

    $lastRequest = (int) stream_get_contents($handle);
    $allowed = time() - $lastRequest >= $intervalSeconds;
    if ($allowed) {
        ftruncate($handle, 0);
        rewind($handle);
        $written = fwrite($handle, (string) time());
        if ($written === false) {
            $allowed = false;
            error_log("Unable to update public form rate-limit record.");
        }
    }

    flock($handle, LOCK_UN);
    fclose($handle);
    return $allowed;
}
