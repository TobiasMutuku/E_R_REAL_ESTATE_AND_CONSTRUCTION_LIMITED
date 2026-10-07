<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/admin-form.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    exit("Use the sign-out form to end your session.");
}
if (!adminFormIsValid()) {
    http_response_code(403);
    exit("The sign-out request could not be verified.");
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();
header("Location: login.php");
exit;
