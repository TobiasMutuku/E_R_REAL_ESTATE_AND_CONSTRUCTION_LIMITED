<?php

require_once __DIR__ . "/../includes/auth.php";
requireAdminPermission("enquiries");

header("Location: enquiries.php", true, 302);
exit;
