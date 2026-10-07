<?php

$host = getenv("ER_DB_HOST") ?: "localhost";
$username = getenv("ER_DB_USER") ?: "root";
$password = getenv("ER_DB_PASSWORD") ?: "";
$database = getenv("ER_DB_NAME") ?: "er_real_estate";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(503);
    exit("The service is temporarily unavailable. Please try again later.");
}

?>