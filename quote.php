<?php

session_start();
require_once __DIR__ . "/includes/public-form-security.php";
$database_available = false;
$attachment_path = null;

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["quote_csrf_token"])) {
    $_SESSION["quote_csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["quote_csrf_token"];

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/
$success_message = "";
$error_message = "";

$full_name = "";
$email = "";
$phone = "";
$service = "";
$location = "";
$budget = "";
$timeline = "";
$message = "";
$property_id = "";
$property_name = "";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $service_options = [
        "new-construction" => "New construction",
        "construction" => "New construction",
        "renovation" => "Renovation",
        "architecture" => "Architectural design",
        "architectural-design" => "Architectural design",
        "plumbing" => "Plumbing",
        "electrical" => "Electrical installations",
        "plumbing-and-electrical" => "Plumbing and electrical",
        "property-or-land-enquiry" => "Property or land enquiry",
    ];
    $requested_service = strtolower(trim((string) ($_GET["service"] ?? "")));
    $service = $service_options[$requested_service] ?? "";
}

try {
    require_once "config/db.php";
    require_once __DIR__ . "/includes/site-mail.php";
    require_once __DIR__ . "/includes/site-settings.php";
    $database_available = true;

/*
|--------------------------------------------------------------------------
| LOAD PROPERTY FROM URL
|--------------------------------------------------------------------------
| Example: quote.php?property_id=5
|--------------------------------------------------------------------------
*/
$url_property_id = filter_input(
    INPUT_GET,
    "property_id",
    FILTER_VALIDATE_INT
);

if ($url_property_id && $database_available) {

    $property_sql = "
        SELECT id, property_name, property_type, location, price
        FROM properties
        WHERE id = ?
          AND is_published = 1
          AND property_status IN ('Available', 'Coming Soon')
        LIMIT 1
    ";

    $property_stmt = $conn->prepare($property_sql);

    if ($property_stmt) {

        $property_stmt->bind_param("i", $url_property_id);
        $property_stmt->execute();

        $property_result = $property_stmt->get_result();

        if ($property_result->num_rows === 1) {
            $selected_property = $property_result->fetch_assoc();
            $property_id = (int) $selected_property["id"];
            $property_name = $selected_property["property_name"];
        }

        $property_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| PROCESS FORM
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$database_available) {
    $error_message = "Quote requests cannot be saved right now. Please contact us by phone or WhatsApp.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = publicFormPostString("full_name");
    $email = publicFormPostString("email");
    $phone = publicFormPostString("phone");
    $service = publicFormPostString("service");
    $location = publicFormPostString("location");
    $budget = publicFormPostString("budget");
    $timeline = publicFormPostString("timeline");
    $message = publicFormPostString("message");
    $property_id = publicFormPostString("property_id");

    $submitted_csrf = publicFormPostString("csrf_token");
    $honeypot = publicFormPostString("website");

    /*
     * Basic anti-spam check
     */
    if (!publicFormRateLimit("quote", 300)) {
        $error_message = "Please wait a few minutes before submitting another quote request.";
    }

    elseif (!empty($honeypot)) {
        $error_message = "Unable to process this request.";
    }

    /*
     * CSRF validation
     */
    elseif (
        empty($submitted_csrf) ||
        !hash_equals($_SESSION["quote_csrf_token"], $submitted_csrf)
    ) {
        $error_message = "Invalid form submission. Please try again.";
    }

    /*
     * Required field validation
     */
    elseif (empty($full_name)) {
        $error_message = "Please enter your full name.";
    }

    elseif (mb_strlen($full_name) > 150) {
        $error_message = "Your name is too long.";
    }

    elseif (empty($email)) {
        $error_message = "Please enter your email address.";
    }

    elseif (mb_strlen($email) > 150) {
        $error_message = "Your email address is too long.";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    }

    elseif (empty($phone)) {
        $error_message = "Please enter your phone or WhatsApp number.";
    }

    elseif (mb_strlen($phone) > 40) {
        $error_message = "Your phone number is too long.";
    }

    elseif (!in_array($service, [
        "New construction",
        "Renovation",
        "Architectural design",
        "Plumbing",
        "Electrical installations",
        "Plumbing and electrical",
        "Property or land enquiry",
    ], true)) {
        $error_message = "Please select a project type.";
    }

    elseif (empty($location)) {
        $error_message = "Please provide the project location.";
    }

    elseif (mb_strlen($location) > 150) {
        $error_message = "Your project location is too long.";
    }

    elseif (!in_array($budget, [
        "",
        "Below KSh 1,000,000",
        "KSh 1,000,000 - 5,000,000",
        "KSh 5,000,000 - 15,000,000",
        "Above KSh 15,000,000",
    ], true)) {
        $error_message = "Please choose a valid estimated budget.";
    }

    elseif (mb_strlen($timeline) > 100) {
        $error_message = "Your preferred timeline is too long.";
    }

    elseif (empty($message)) {
        $error_message = "Please describe your project.";
    }

    /*
     * Validate property, when supplied
     */
    elseif (!empty($property_id)) {

        if (!filter_var($property_id, FILTER_VALIDATE_INT)) {
            $error_message = "Invalid property selected.";
        } else {

            $property_id = (int) $property_id;

            $property_check_sql = "
                SELECT id, property_name
                FROM properties
                WHERE id = ?
                  AND is_published = 1
                  AND property_status IN ('Available', 'Coming Soon')
                LIMIT 1
            ";

            $property_check_stmt = $conn->prepare($property_check_sql);

            if ($property_check_stmt) {

                $property_check_stmt->bind_param("i", $property_id);
                $property_check_stmt->execute();

                $property_check_result = $property_check_stmt->get_result();

                if ($property_check_result->num_rows !== 1) {
                    $error_message = "The selected property is no longer available.";
                } else {
                    $selected_property = $property_check_result->fetch_assoc();
                    $property_name = $selected_property["property_name"];
                }

                $property_check_stmt->close();

            } else {
                $error_message = "Unable to verify the selected property.";
            }
        }
    }

    /*
     * File upload
     */
    if (
        empty($error_message) &&
        isset($_FILES["attachment"]) &&
        $_FILES["attachment"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        $attachment = $_FILES["attachment"];

        if (!isset($attachment["error"], $attachment["size"], $attachment["tmp_name"])
            || $attachment["error"] !== UPLOAD_ERR_OK) {
            $error_message = "There was a problem uploading your attachment.";
        }

        elseif ($attachment["size"] > 5 * 1024 * 1024) {
            $error_message = "Attachment must not exceed 5 MB.";
        }

        else {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $attachment["tmp_name"]);
            finfo_close($finfo);

            $allowed_types = [
                "application/pdf" => "pdf",
                "image/jpeg"      => "jpg",
                "image/png"       => "png"
            ];

            if (!array_key_exists($mime_type, $allowed_types)) {
                $error_message = "Only PDF, JPG and PNG files are allowed.";
            }

            else {

                $extension = $allowed_types[$mime_type];

                $new_filename =
                    "quote_" . bin2hex(random_bytes(16)) . "." . $extension;

                $upload_directory =
                    __DIR__ . DIRECTORY_SEPARATOR .
                    "uploads" . DIRECTORY_SEPARATOR .
                    "quotes" . DIRECTORY_SEPARATOR;

                if (!is_dir($upload_directory)) {
                    if (!mkdir($upload_directory, 0755, true) && !is_dir($upload_directory)) {
                        $error_message = "Unable to create the attachment folder.";
                    }
                }

                if (empty($error_message)) {

                    $destination = $upload_directory . $new_filename;

                    if (move_uploaded_file($attachment["tmp_name"], $destination)) {
                        $attachment_path = "uploads/quotes/" . $new_filename;
                    } else {
                        $error_message = "Unable to save the attachment.";
                    }
                }
            }
        }
    }

    /*
     * Save quote request
     */
    if (empty($error_message)) {

        $property_id_value =
            !empty($property_id) ? (int) $property_id : null;

        $sql = "
            INSERT INTO quote_requests
            (
                full_name,
                email,
                phone,
                service,
                location,
                budget,
                timeline,
                property_id,
                message,
                attachment
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param(
                "sssssssiss",
                $full_name,
                $email,
                $phone,
                $service,
                $location,
                $budget,
                $timeline,
                $property_id_value,
                $message,
                $attachment_path
            );

            if ($stmt->execute()) {
                $quoteId = (int) $conn->insert_id;
                $notificationStatus = "failed";
                try {
                    $settings = loadSiteSettings($conn);
                    $body = "New website quote request.\n\nName: {$full_name}\nEmail: {$email}\nPhone: {$phone}\nService: {$service}\nLocation: {$location}\nBudget: {$budget}\nTimeline: {$timeline}\nProperty record: {$property_name}\nAttachment: " . ($attachment_path !== null ? "Available to authorized administrators" : "None") . "\n\nProject details:\n{$message}\n";
                    $notificationStatus = sendSiteNotification(
                        $settings["public_email"],
                        "Website quote request: {$service}",
                        $body,
                        $email
                    ) ? "sent" : "failed";
                } catch (RuntimeException $exception) {
                    error_log("Quote notification could not be prepared: " . $exception->getMessage());
                }
                $notificationUpdate = $conn->prepare("UPDATE quote_requests SET notification_status = ? WHERE id = ?");
                if ($notificationUpdate) {
                    $notificationUpdate->bind_param("si", $notificationStatus, $quoteId);
                    if (!$notificationUpdate->execute()) {
                        error_log("Quote notification status could not be saved: " . $notificationUpdate->error);
                    }
                    $notificationUpdate->close();
                } else {
                    error_log("Quote notification status update could not be prepared: " . $conn->error);
                }

                $success_message =
                    "Thank you. Your quote request has been recorded for our team to review. " .
                    "We will contact you using the details you provided.";

                $full_name = "";
                $email = "";
                $phone = "";
                $service = "";
                $location = "";
                $budget = "";
                $timeline = "";
                $message = "";

                $_SESSION["quote_csrf_token"] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION["quote_csrf_token"];

            } else {

                if (
                    $attachment_path !== null &&
                    file_exists(__DIR__ . DIRECTORY_SEPARATOR . $attachment_path)
                ) {
                    unlink(__DIR__ . DIRECTORY_SEPARATOR . $attachment_path);
                }

                $error_message =
                    "Unable to submit your quote request. Please try again.";
            }

            $stmt->close();

        } else {
            $error_message = "Database error. Please try again later.";
        }
    }
}

} catch (mysqli_sql_exception $exception) {
    error_log("Quote page database error: " . $exception->getMessage());
    $database_available = false;
    $error_message = "Quote requests cannot be saved right now. Please contact us by phone or WhatsApp.";

    if (
        $attachment_path !== null &&
        file_exists(__DIR__ . DIRECTORY_SEPARATOR . $attachment_path)
    ) {
        unlink(__DIR__ . DIRECTORY_SEPARATOR . $attachment_path);
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>E&amp;R Real Estate and Construction Limited - Request a Quote</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta
        content="Request a construction, renovation, design, or property quotation from E&R Real Estate and Construction Limited."
        name="description">

    <link href="logo/E&R Logo.jfif" rel="icon">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css"
        rel="stylesheet">

    <link href="css/style.css?v=20261007-site-audit" rel="stylesheet">
</head>

<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <!-- Navbar Start -->
    <div class="container-fluid nav-bar p-0">
        <div class="container-lg p-0">
            <nav class="navbar navbar-expand-lg bg-secondary navbar-dark">

                <a href="index.html" class="navbar-brand d-flex align-items-center">
                    <img src="logo/E&R Logo.jfif" alt="E&R logo">
                </a>

                <button
                    type="button"
                    class="navbar-toggler"
                    data-toggle="collapse"
                    data-target="#navbarCollapse">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div
                    class="collapse navbar-collapse justify-content-between"
                    id="navbarCollapse">

                    <div class="navbar-nav ml-auto py-0">
                        <a href="index.html" class="nav-item nav-link">Home</a>
                        <a href="service.html" class="nav-item nav-link">Services</a>
                        <a href="constructions.html" class="nav-item nav-link">Projects</a>
                        <a href="real-estate.html" class="nav-item nav-link">Properties</a>
                        <a href="about.html" class="nav-item nav-link">About</a>
                        <a href="quote.php" class="nav-item nav-link active" aria-current="page">Get a Quote</a>
                        <a href="contact.php" class="nav-item nav-link">Contact</a>
                    </div>

                </div>
            </nav>
        </div>
    </div>
    <!-- Navbar End -->


    <!-- Page Header Start -->
    <div class="container-fluid page-header d-flex flex-column align-items-center justify-content-center pt-0 pt-lg-5 mb-5">
        <h1 class="display-4 text-white mb-3 mt-0 mt-lg-5">Request a Quote</h1>

        <div class="d-inline-flex text-white">
            <p class="m-0">
                <a class="text-white" href="index.html">Home</a>
            </p>
            <p class="m-0 px-2">/</p>
            <p class="m-0">Quote</p>
        </div>
    </div>
    <!-- Page Header End -->


    <!-- Quote Form Start -->
    <main id="main-content" class="quote-page py-5" tabindex="-1">
      <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">

                <div class="quote-intro text-center mb-4">
                    <span class="quote-eyebrow">Start with a brief</span>

                    <h1 class="mt-3 mb-3">
                        Tell us what you want to build
                    </h1>

                    <p class="quote-intro-copy mb-0">
                        Share enough detail for our team to understand your project.
                        Our team will review your enquiry and contact you to discuss next steps.
                    </p>
                </div>


                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fa fa-check-circle mr-2"></i>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>


                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fa fa-exclamation-circle mr-2"></i>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$database_available): ?>
                    <div class="alert alert-warning" role="status">
                        The quote form is temporarily unavailable because the request service is offline.
                        Call or WhatsApp us using the contact options below.
                    </div>
                <?php endif; ?>

                <form
                    id="quoteForm"
                    class="quote-form-card"
                    action="quote.php<?= !empty($property_id) ? '?property_id=' . (int) $property_id : '' ?>"
                    method="post"
                    enctype="multipart/form-data">

                    <fieldset class="border-0 p-0 m-0" <?= !$database_available ? "disabled" : "" ?>>
                    <!-- CSRF TOKEN -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf_token) ?>">


                    <!-- PROPERTY ID -->
                    <?php if (!empty($property_id)): ?>

                        <input
                            type="hidden"
                            name="property_id"
                            value="<?= (int) $property_id ?>">

                        <div class="alert alert-light border mb-4">
                            <i class="fa fa-home text-primary mr-2"></i>
                            <strong>Property enquiry:</strong>
                            <?= htmlspecialchars($property_name) ?>
                        </div>

                    <?php endif; ?>


                    <!-- HONEYPOT -->
                    <div class="d-none" aria-hidden="true">
                        <label>
                            Leave this field blank
                            <input
                                name="website"
                                type="text"
                                tabindex="-1"
                                autocomplete="off">
                        </label>
                    </div>


                    <!-- NAME + EMAIL -->
                    <div class="form-row">

                        <div class="col-md-6 form-group">
                            <label for="quoteName">
                                Full name *
                            </label>

                            <input
                                class="form-control"
                                id="quoteName"
                                name="full_name"
                                type="text"
                                value="<?= htmlspecialchars($full_name) ?>"
                                maxlength="150"
                                required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="quoteEmail">
                                Email address *
                            </label>

                            <input
                                class="form-control"
                                id="quoteEmail"
                                name="email"
                                type="email"
                                value="<?= htmlspecialchars($email) ?>"
                                maxlength="150"
                                required>
                        </div>

                    </div>


                    <!-- PHONE + SERVICE -->
                    <div class="form-row">

                        <div class="col-md-6 form-group">
                            <label for="quotePhone">
                                Phone / WhatsApp *
                            </label>

                            <input
                                class="form-control"
                                id="quotePhone"
                                name="phone"
                                type="tel"
                                value="<?= htmlspecialchars($phone) ?>"
                                maxlength="40"
                                required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="quoteService">
                                Project type *
                            </label>

                            <select
                                class="form-control"
                                id="quoteService"
                                name="service"
                                required>

                                <option value="">
                                    Choose a service
                                </option>

                                <option value="New construction" <?= $service === "New construction" ? "selected" : "" ?>>
                                    New construction
                                </option>

                                <option value="Renovation" <?= $service === "Renovation" ? "selected" : "" ?>>
                                    Renovation
                                </option>

                                <option value="Architectural design" <?= $service === "Architectural design" ? "selected" : "" ?>>
                                    Architectural design
                                </option>

                                <option value="Plumbing and electrical" <?= $service === "Plumbing and electrical" ? "selected" : "" ?>>
                                    Plumbing and electrical
                                </option>

                                <option value="Plumbing" <?= $service === "Plumbing" ? "selected" : "" ?>>
                                    Plumbing
                                </option>

                                <option value="Electrical installations" <?= $service === "Electrical installations" ? "selected" : "" ?>>
                                    Electrical installations
                                </option>

                                <option value="Property or land enquiry" <?= $service === "Property or land enquiry" ? "selected" : "" ?>>
                                    Property or land enquiry
                                </option>

                            </select>
                        </div>

                    </div>


                    <!-- LOCATION + BUDGET -->
                    <div class="form-row">

                        <div class="col-md-6 form-group">
                            <label for="quoteLocation">
                                Project location *
                            </label>

                            <input
                                class="form-control"
                                id="quoteLocation"
                                name="location"
                                type="text"
                                value="<?= htmlspecialchars($location) ?>"
                                placeholder="Town or county"
                                maxlength="150"
                                required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="quoteBudget">
                                Estimated budget
                            </label>

                            <select
                                class="form-control"
                                id="quoteBudget"
                                name="budget">

                                <option value="">
                                    Prefer to discuss
                                </option>

                                <option value="Below KSh 1,000,000" <?= $budget === "Below KSh 1,000,000" ? "selected" : "" ?>>
                                    Below KSh 1,000,000
                                </option>

                                <option value="KSh 1,000,000 - 5,000,000" <?= $budget === "KSh 1,000,000 - 5,000,000" ? "selected" : "" ?>>
                                    KSh 1,000,000 - 5,000,000
                                </option>

                                <option value="KSh 5,000,000 - 15,000,000" <?= $budget === "KSh 5,000,000 - 15,000,000" ? "selected" : "" ?>>
                                    KSh 5,000,000 - 15,000,000
                                </option>

                                <option value="Above KSh 15,000,000" <?= $budget === "Above KSh 15,000,000" ? "selected" : "" ?>>
                                    Above KSh 15,000,000
                                </option>

                            </select>
                        </div>

                    </div>


                    <!-- TIMELINE -->
                    <div class="form-group">
                        <label for="quoteTimeline">
                            Preferred start or completion date
                        </label>

                        <input
                            class="form-control"
                            id="quoteTimeline"
                            name="timeline"
                            type="text"
                            value="<?= htmlspecialchars($timeline) ?>"
                            placeholder="For example: September 2026"
                            maxlength="100">
                    </div>


                    <!-- MESSAGE -->
                    <div class="form-group">
                        <label for="quoteMessage">
                            Project brief *
                        </label>

                        <textarea
                            class="form-control"
                            id="quoteMessage"
                            name="message"
                            rows="6"
                            placeholder="Tell us about the site, size, rooms, current stage, or work needed."
                            maxlength="5000"
                            required><?= htmlspecialchars($message) ?></textarea>
                    </div>


                    <!-- ATTACHMENT -->
                    <div class="form-group">
                        <label for="quoteAttachment">
                            Plans or site photos
                        </label>

                        <input
                            class="form-control-file"
                            id="quoteAttachment"
                            name="attachment"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png">

                        <small class="form-text text-muted">
                            PDF, JPG, or PNG; maximum 5 MB.
                        </small>
                    </div>


                    </fieldset>

                    <div class="quote-actions">
                        <button class="btn btn-primary quote-submit" type="submit" id="quoteSubmit">
                            <i class="fa fa-paper-plane mr-2" aria-hidden="true"></i>
                            Send quote request
                        </button>
                        <a class="btn btn-success quote-whatsapp"
                            href="https://wa.me/254748766822?text=Hello%20E%26R%2C%20I%20would%20like%20a%20construction%20quote."
                            target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-whatsapp mr-2" aria-hidden="true"></i>
                            Use WhatsApp
                        </a>
                    </div>

                </form>


                <div class="quote-reassurance text-center mt-3">
                    <i class="fa fa-lock mr-1" aria-hidden="true"></i>
                    <small>Your details are used to respond to this request. We record your enquiry for our team to review. <a href="privacy.html">Privacy information</a>.</small>
                </div>

            </div>
        </div>
      </div>
    </main>
    <!-- Quote Form End -->


    <!-- Footer Start -->
    <div class="container-fluid bg-secondary text-white mt-5 pt-5 px-sm-3 px-md-5">
        <div class="row pt-5">

            <div class="col-lg-4 mb-5">
                <img
                    src="logo/E&R Logo.jfif"
                    alt="E&R logo"
                    style="height:60px; width:auto; max-width:200px">

                <p class="mt-3">
                    Real estate and construction services for residential and commercial projects.
                </p>
            </div>

            <div class="col-lg-4 mb-5">
                <h5 class="font-weight-bold text-primary mb-4">
                    Need a quick answer?
                </h5>

                <p>
                    <i class="fab fa-whatsapp text-primary mr-2"></i>
                    <a class="text-white" href="https://wa.me/254748766822">
                        Chat with E&amp;R on WhatsApp
                    </a>
                </p>

                <p>
                    <i class="fa fa-phone-alt text-primary mr-2"></i>
                    <a class="text-white" href="tel:+254748766822">
                        +254 748 766 822
                    </a>
                </p>
            </div>

            <div class="col-lg-4 mb-5">
                <h5 class="font-weight-bold text-primary mb-4">
                    Explore
                </h5>

                <a class="text-white d-block mb-2" href="real-estate.html">
                    Properties
                </a>

                <a class="text-white d-block mb-2" href="constructions.html">
                    Projects
                </a>

                <a class="text-white d-block" href="contact.php">
                    Contact us
                </a>
            </div>

        </div>
    </div>

    <div class="container-fluid py-4">
        <p class="m-0 text-center">
            &copy; 2026 E&amp;R REAL ESTATE AND CONSTRUCTION LIMITED.
            All Rights Reserved.
        </p>
    </div>
    <!-- Footer End -->


    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>

    <script src="js/site-settings.js" defer></script>
</body>
</html>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
