<?php

session_start();

require_once "config/db.php";
require_once __DIR__ . "/includes/public-form-security.php";

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

/*
|--------------------------------------------------------------------------
| FORM STATE
|--------------------------------------------------------------------------
*/
$success_message = "";
$error_message = "";

$full_name = "";
$email = "";
$phone = "";
$enquiry_type = "General";
$property_id = "";
$subject = "";
$enquiry_message = "";

/*
|--------------------------------------------------------------------------
| LOAD AVAILABLE PROPERTIES
|--------------------------------------------------------------------------
*/
$properties = [];

$property_sql = "
    SELECT id, property_name, property_type, location, price
    FROM properties
    WHERE is_published = 1
      AND property_status IN ('Available', 'Coming Soon')
    ORDER BY property_name ASC
";

$property_result = $conn->query($property_sql);

if ($property_result) {
    while ($property = $property_result->fetch_assoc()) {
        $properties[] = $property;
    }
}

/*
|--------------------------------------------------------------------------
| PRESELECT PROPERTY FROM URL
| Example: contact.php?property_id=3
|--------------------------------------------------------------------------
*/
$requested_property_id = $_GET["property_id"] ?? "";

if ($_SERVER["REQUEST_METHOD"] !== "POST" && $requested_property_id !== "") {
    if (filter_var($requested_property_id, FILTER_VALIDATE_INT)) {
        $property_id = (string) $requested_property_id;
        $enquiry_type = "Real Estate";
    }
}

/*
|--------------------------------------------------------------------------
| PROCESS ENQUIRY
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = publicFormPostString("full_name");
    $email = publicFormPostString("email");
    $phone = publicFormPostString("phone");
    $enquiry_type = publicFormPostString("enquiry_type") ?: "General";
    $property_id = publicFormPostString("property_id");
    $subject = publicFormPostString("subject");
    $enquiry_message = publicFormPostString("message");
    $submitted_csrf = publicFormPostString("csrf_token");
    $honeypot = publicFormPostString("website");

    if (!publicFormRateLimit("contact", 60)) {
        $error_message = "Please wait a moment before submitting another enquiry.";
    }

    elseif (!empty($honeypot)) {
        $error_message = "Unable to process your request.";
    }

    elseif (
        empty($submitted_csrf) ||
        !hash_equals($_SESSION["csrf_token"], $submitted_csrf)
    ) {
        $error_message = "Invalid form submission. Please try again.";
    }

    elseif (empty($full_name)) {
        $error_message = "Please enter your full name.";
    }

    elseif (mb_strlen($full_name) > 150) {
        $error_message = "Your name is too long.";
    }

    elseif (mb_strlen($phone) > 50) {
        $error_message = "Your phone number is too long.";
    }

    elseif (empty($email)) {
        $error_message = "Please enter your email address.";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    }

    elseif (mb_strlen($email) > 150) {
        $error_message = "Your email address is too long.";
    }

    elseif (
        !in_array(
            $enquiry_type,
            ["General", "Construction", "Real Estate", "Project", "Quote"],
            true
        )
    ) {
        $error_message = "Invalid enquiry type.";
    }

    elseif (empty($subject)) {
        $error_message = "Please enter a subject.";
    }

    elseif (mb_strlen($subject) > 255) {
        $error_message = "Your subject is too long.";
    }

    elseif (empty($enquiry_message)) {
        $error_message = "Please enter your message.";
    }

    elseif (mb_strlen($enquiry_message) > 5000) {
        $error_message = "Your message is too long.";
    }

    elseif (
        $enquiry_type === "Real Estate" &&
        empty($property_id)
    ) {
        $error_message = "Please select the property you are enquiring about.";
    }

    elseif (!empty($property_id)) {

        if (!filter_var($property_id, FILTER_VALIDATE_INT)) {

            $error_message = "Invalid property selected.";

        } else {

            $property_check_sql = "
                SELECT id
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
                }

                $property_check_stmt->close();

            } else {
                $error_message = "Unable to verify the selected property.";
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT INTO DATABASE
    |--------------------------------------------------------------------------
    */
    if (empty($error_message)) {

        $property_id_value = !empty($property_id)
            ? (int) $property_id
            : null;

        $sql = "
            INSERT INTO enquiries
            (
                full_name,
                email,
                phone,
                enquiry_type,
                property_id,
                subject,
                message
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param(
                "ssssiss",
                $full_name,
                $email,
                $phone,
                $enquiry_type,
                $property_id_value,
                $subject,
                $enquiry_message
            );

            if ($stmt->execute()) {

                $success_message =
                    "Thank you. Your enquiry has been recorded for our team to review. " .
                    "We will contact you using the details you provided.";

                $full_name = "";
                $email = "";
                $phone = "";
                $enquiry_type = "General";
                $property_id = "";
                $subject = "";
                $enquiry_message = "";

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION["csrf_token"];

            } else {
                $error_message =
                    "We were unable to submit your enquiry. Please try again.";
            }

            $stmt->close();

        } else {
            $error_message = "Database error. Please try again later.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>E&R REAL ESTATE AND CONSTRUCTION LIMITED - Contact Us</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="description" content="Send a construction or property enquiry to E&amp;R Real Estate and Construction Limited.">

    <!-- Favicon -->
    <link href="logo/E&R Logo.jfif" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <!-- Navbar Start -->
    <div class="container-fluid nav-bar p-0">
        <div class="container-lg p-0">
            <nav class="navbar navbar-expand-lg bg-secondary navbar-dark">
                <a href="index.html" class="navbar-brand d-flex align-items-center">
                    <img src="logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo">
                </a>
                <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-between" id="navbarCollapse">
                    <div class="navbar-nav ml-auto py-0">
                        <a href="index.html" class="nav-item nav-link">Home</a>
                        <a href="about.html" class="nav-item nav-link">About</a>
                        <a href="service.html" class="nav-item nav-link">Services</a>
                        <a href="constructions.html" class="nav-item nav-link">Projects</a>
                        <a href="real-estate.html" class="nav-item nav-link">Properties</a>
                        <a href="quote.php" class="nav-item nav-link">Get a Quote</a>
                        <a href="contact.php" class="nav-item nav-link active">Contact</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>
    <!-- Navbar End -->


    <!-- Page Header Start -->
    <div
        class="container-fluid page-header d-flex flex-column align-items-center justify-content-center pt-0 pt-lg-5 mb-5">
        <h1 class="display-4 text-white mb-3 mt-0 mt-lg-5">Contact</h1>
        <div class="d-inline-flex text-white">
            <p class="m-0"><a class="text-white" href="index.html">Home</a></p>
            <p class="m-0 px-2">/</p>
            <p class="m-0">Contact</p>
        </div>
    </div>
    <!-- Page Header Start -->


    <!-- Contact Start -->
    <div id="main-content" class="container-fluid py-5" tabindex="-1">
        <div class="container">
            <div class="text-center">
                <small class="bg-primary text-white text-uppercase font-weight-bold text-center px-1">Get In
                    Touch</small>
                <h1 class="mt-2 mb-5">Contact Us For Any Queries</h1>
            </div>
            <div class="row">
                <div class="col-md-5">
                    <div class="d-flex align-items-center border mb-3 p-4">
                        <i class="fa fa-2x fa-map-marker-alt text-primary mr-3"></i>
                        <div class="d-flex flex-column">
                            <h5 class="font-weight-bold">Our Office</h5>
                            <a href="geolocation.html" class="m-0">Watamu Mall, Office 34, 1st Floor, Watamu, Kilifi County</a>
                        </div>
                    </div>
                    <div class="d-flex align-items-center border mb-3 p-4">
                        <i class="fa fa-2x fa-envelope-open text-primary mr-3"></i>
                        <div class="d-flex flex-column">
                            <h5 class="font-weight-bold">Email Us</h5>
                            <a class="m-0"
                                href="mailto:errealestateconstruction@gmail.com">errealestateconstruction@gmail.com</a>
                        </div>
                    </div>
                    <div class="d-flex align-items-center border mb-3 mb-md-0 p-4">
                        <i class="fas fa-2x fa-phone-alt text-primary mr-3"></i>
                        <div class="d-flex flex-column">
                            <h5 class="font-weight-bold">Call Us</h5>
                            <a class="m-0" href="tel:+254748766822">+254 748 766 822</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="contact-form">
                        <div id="success"></div>
                        <form name="sentMessage" id="contactForm" method="POST" action="contact.php">
                            <!-- CSRF Protection -->
                            <input type="hidden" name="csrf_token"
                                value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                            <!-- Honeypot anti-spam field -->
                            <div class="d-none" aria-hidden="true">
                                <label>
                                    Leave this field blank
                                    <input name="website" type="text" tabindex="-1" autocomplete="off">
                                </label>
                            </div>

                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="control-group">
                                        <input type="text" class="form-control p-4" id="name" name="full_name"
                                            value="<?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Your Name" maxlength="150"
                                            required="required"
                                            data-validation-required-message="Please enter your name" />
                                        <p class="help-block text-danger"></p>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="control-group">
                                        <input type="email" class="form-control p-4" id="email" name="email"
                                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Your Email" maxlength="150"
                                            required="required"
                                            data-validation-required-message="Please enter your email" />
                                        <p class="help-block text-danger"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="control-group">
                                        <input type="text" class="form-control p-4" id="phone" name="phone"
                                            value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Phone Number" maxlength="50" />
                                        <p class="help-block text-danger"></p>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="control-group">
                                        <select class="form-control px-4" id="enquiry_type" name="enquiry_type"
                                            required="required"
                                            style="height: 60px;"
                                            data-validation-required-message="Please select an enquiry type">
                                            <option value="" disabled <?= empty($enquiry_type) ? 'selected' : '' ?>>
                                                Select Enquiry Type
                                            </option>
                                            <option value="General" <?= $enquiry_type === 'General' ? 'selected' : '' ?>>
                                                General Enquiry
                                            </option>
                                            <option value="Construction" <?= $enquiry_type === 'Construction' ? 'selected' : '' ?>>
                                                Construction Services
                                            </option>
                                            <option value="Real Estate" <?= $enquiry_type === 'Real Estate' ? 'selected' : '' ?>>
                                                Real Estate
                                            </option>
                                            <option value="Project" <?= $enquiry_type === 'Project' ? 'selected' : '' ?>>
                                                Project Enquiry
                                            </option>
                                            <option value="Quote" <?= $enquiry_type === 'Quote' ? 'selected' : '' ?>>
                                                Request a Quote
                                            </option>
                                        </select>
                                        <p class="help-block text-danger"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="control-group" id="propertyField"
                                style="<?= $enquiry_type === 'Real Estate' ? '' : 'display:none;' ?>">
                                <select class="form-control px-4" id="property_id" name="property_id"
                                    style="height: 60px;"
                                    <?= $enquiry_type === 'Real Estate' ? 'required' : '' ?>>
                                    <option value="">
                                        Select Property
                                    </option>

                                    <?php foreach ($properties as $property): ?>
                                        <option value="<?= (int) $property['id'] ?>"
                                            <?= (string) $property_id === (string) $property['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($property['property_name'], ENT_QUOTES, 'UTF-8') ?>
                                            - <?= htmlspecialchars($property['location'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block text-danger"></p>
                            </div>

                            <div class="control-group">
                                <input type="text" class="form-control p-4" id="subject" name="subject"
                                    value="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Subject" maxlength="255"
                                    required="required"
                                    data-validation-required-message="Please enter a subject" />
                                <p class="help-block text-danger"></p>
                            </div>

                            <div class="control-group">
                                <textarea class="form-control" rows="5" id="message" name="message"
                                    placeholder="Tell us how we can help you..."
                                    maxlength="5000"
                                    required="required"
                                    data-validation-required-message="Please enter your message"><?= htmlspecialchars($enquiry_message, ENT_QUOTES, 'UTF-8') ?></textarea>
                                <p class="help-block text-danger"></p>
                            </div>

                            <div id="formFeedback">
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success mb-4" role="alert">
                                        <i class="fa fa-check-circle mr-2"></i>
                                        <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger mb-4" role="alert">
                                        <i class="fa fa-exclamation-circle mr-2"></i>
                                        <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <button class="btn btn-primary contact-action-button font-weight-semi-bold px-4"
                                    style="height: 50px;" type="submit" id="sendMessageButton">
                                    <i class="fa fa-paper-plane mr-2"></i>Send Message
                                </button>

                                <a class="btn btn-success contact-action-button d-inline-flex align-items-center justify-content-center text-center font-weight-semi-bold px-4 ml-2"
                                    style="height: 50px;"
                                    href="https://wa.me/254748766822?text=Hello%20E%26R%2C%20I%20would%20like%20to%20discuss%20a%20project.">
                                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                                </a>
                            </div>

                            <p class="mb-0">
                                <a class="font-weight-semi-bold" href="quote.php">
                                    Need a detailed estimate? Request a project quote
                                    <i class="fa fa-angle-double-right"></i>
                                </a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
            <section class="office-location mt-5" aria-labelledby="officeLocationTitle">
                <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between mb-3">
                    <div>
                        <p class="text-primary text-uppercase font-weight-bold mb-1">Visit our office</p>
                        <h2 id="officeLocationTitle" class="mb-2">Find us in Watamu</h2>
                        <p class="mb-0" data-office-address>Watamu Mall, Office 34, 1st Floor, Watamu, Kilifi County</p>
                    </div>
                    <a class="btn btn-primary mt-3 mt-md-0" data-office-directions
                        href="https://www.google.com/maps/search/?api=1&amp;query=Watamu%20Mall%2C%20Watamu%2C%20Kilifi%20County"
                        target="_blank" rel="noopener noreferrer">Get directions</a>
                </div>
                <iframe class="office-map" data-office-map
                    src="https://maps.google.com/maps?q=Watamu%20Mall%2C%20Watamu%2C%20Kilifi%20County&amp;output=embed"
                    title="Map showing the E&amp;R office at Watamu Mall"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </section>
        </div>
    </div>
    <!-- Contact End -->


    <!-- Footer Start -->
    <div class="container-fluid bg-secondary text-white mt-5 pt-5 px-sm-3 px-md-5">
        <div class="row pt-5">
            <div class="col-lg-3 col-md-6 mb-5">
                <a href="index.html" class="navbar-brand footer-brand d-flex align-items-center">
                    <img src="logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo"
                        style="height: 60px; width: auto; max-width: 200px; object-fit: contain; border-radius: 0; filter: drop-shadow(0 6px 14px rgba(0,0,0,0.12));">
                </a>
                <p>To Our Esteemed Clients,You Can Follow E&R REAL ESTATE AND CONSTRUCTION LIMITED On Social Media
                    Platforms</p>
                <div class="d-flex justify-content-start mt-4">
                    <a class="btn btn-outline-primary rounded-circle text-center mr-2 px-0"
                        style="width: 38px; height: 38px;" href="contact.php" aria-label="Contact E&R on Facebook"><i
                            class="fab fa-facebook-f"></i></a>
                    <a class="btn btn-outline-primary rounded-circle text-center mr-2 px-0"
                        style="width: 38px; height: 38px;" href="contact.php" aria-label="Contact E&R on LinkedIn"><i
                            class="fab fa-linkedin-in"></i></a>
                    <a class="btn btn-outline-primary rounded-circle text-center mr-2 px-0"
                        style="width: 38px; height: 38px;"
                        href="https://www.instagram.com/e_r_real_estate_construction/"><i
                            class="fab fa-instagram"></i></a>
                    <a class="btn btn-outline-primary rounded-circle text-center mr-2 px-0"
                        style="width: 38px; height: 38px;" href="https://www.tiktok.com/@e_r_real_estate?lang=en"><i
                            class="fab fa-tiktok"></i></a>
                    <a class="btn btn-outline-primary rounded-circle text-center mr-2 px-0"
                        style="width: 38px; height: 38px;" href="https://x.com/E_R_REAL_ESTATE"
                        aria-label="Follow E&R on X"><span aria-hidden="true">X</span></a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-5">
                <h5 class="font-weight-bold text-primary mb-4">Our Approach</h5>
                <div class="d-flex flex-column justify-content-start">
                    <p class="text-white mb-2"><i class="fa fa-angle-right text-primary mr-2"></i>Scope-led project
                        planning</p>
                    <p class="text-white mb-2"><i class="fa fa-angle-right text-primary mr-2"></i>Clear communication
                        throughout</p>
                    <p class="text-white mb-2"><i class="fa fa-angle-right text-primary mr-2"></i>Coordinated
                        construction services</p>
                    <p class="text-white mb-2"><i class="fa fa-angle-right text-primary mr-2"></i>Quality-focused
                        workmanship</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-5">
                <h5 class="font-weight-bold text-primary mb-4">Popular Links</h5>
                <div class="d-flex flex-column justify-content-start">
                    <a class="text-white mb-2" href="index.html"><i
                            class="fa fa-angle-right text-primary mr-2"></i>Home</a>
                    <a class="text-white mb-2" href="about.html"><i
                            class="fa fa-angle-right text-primary mr-2"></i>About Us</a>
                    <a class="text-white mb-2" href="service.html"><i
                            class="fa fa-angle-right text-primary mr-2"></i>Services</a>
                    <a class="text-white mb-2" href="constructions.html"><i
                            class="fa fa-angle-right text-primary mr-2"></i>Projects</a>
                    <a class="text-white" href="contact.php"><i class="fa fa-angle-right text-primary mr-2"></i>Contact
                        Us</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-5">
                <h5 class="font-weight-bold text-primary mb-4">Get In Touch</h5>
                <p>For our services, suggestions and concerns.Reach us through</p>
                <p><i class="fa fa-map-marker-alt text-primary mr-2"></i>WATAMU MALL, OFFICE 34, 1ST FLOOR, WATAMU,
                    KILIFI COUNTY</p>
                <p><i class="fa fa-phone-alt text-primary mr-2"></i>+254748766822</p>
                <a class="footer-contact-link" href="mailto:errealestateconstruction@gmail.com"><i
                        class="fa fa-envelope text-primary mr-2" aria-hidden="true"></i>errealestateconstruction@gmail.com</a>
            </div>
        </div>
    </div>
    <div class="container-fluid py-4 px-sm-3 px-md-5">
        <p class="m-0 text-center">
            &copy;2026 <a class="font-weight-semi-bold" href="#">E&R REAL ESTATE AND CONSTRUCTION LIMITED</a>. Terms &
            Conditions. All Rights Reserved. <a class="ml-2" href="privacy.html">Privacy</a>
        </p>
    </div>
    <!-- Footer End -->


    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary back-to-top"><i class="fa fa-angle-up"></i></a>


    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>


    <script>
        (function () {
            const enquiryType = document.getElementById('enquiry_type');
            const propertyField = document.getElementById('propertyField');
            const propertySelect = document.getElementById('property_id');

            if (!enquiryType || !propertyField || !propertySelect) {
                return;
            }

            function togglePropertyField() {
                const isRealEstate = enquiryType.value === 'Real Estate';

                propertyField.style.display = isRealEstate ? '' : 'none';
                propertySelect.required = isRealEstate;

                if (!isRealEstate) {
                    propertySelect.value = '';
                }
            }

            enquiryType.addEventListener('change', togglePropertyField);
            togglePropertyField();
        })();
    </script>

    <!-- Template Javascript -->
    <script src="js/main.js"></script>
    <script src="js/site-settings.js" defer></script>
</body>

</html>

<?php

$conn->close();
?>