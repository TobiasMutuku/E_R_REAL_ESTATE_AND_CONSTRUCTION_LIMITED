<?php

require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/admin/projects/project-media.php";
ensure_project_media_table($conn);

$project_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$project_id) {
    http_response_code(404);
    exit("Project not found.");
}

$stmt = $conn->prepare("SELECT project_name, location, description, project_status, start_date, completion_date, image FROM projects WHERE id = ? AND is_published = 1 LIMIT 1");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();
$stmt->close();
$media = [];
if ($project) {
    $media_stmt = $conn->prepare("SELECT media_path, media_type FROM project_media WHERE project_id = ? ORDER BY sort_order, id");
    $media_stmt->bind_param("i", $project_id);
    $media_stmt->execute();
    $media_result = $media_stmt->get_result();
    while ($media_item = $media_result->fetch_assoc()) {
        $media[] = $media_item;
    }
    $media_stmt->close();
    if ($media === [] && !empty($project["image"])) {
        $media[] = ["media_path" => $project["image"], "media_type" => "image"];
    }
}
$conn->close();

if (!$project) {
    http_response_code(404);
    exit("Project not found.");
}

$description = $project["description"] ?: "A project description has not been provided.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($project["project_name"] . " in " . $project["location"] . " | E&R Real Estate and Construction Limited project record", ENT_QUOTES, "UTF-8") ?>">
    <title><?= htmlspecialchars($project["project_name"], ENT_QUOTES, "UTF-8") ?> | E&amp;R Projects</title>
    <link rel="icon" href="logo/E&R Logo.jfif">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .project-detail-hero { padding: 82px 0 70px; color: #fff; background: #123d34; }
        .project-detail-hero h1 { max-width: 760px; font-weight: 800; }
        .project-detail-hero .lead { max-width: 650px; color: rgba(255,255,255,.76); }
        .project-detail-wrap { margin-top: -48px; position: relative; z-index: 2; }
        .project-detail-card { overflow: hidden; background: #fff; box-shadow: 0 18px 45px rgba(18,61,52,.14); }
        .project-detail-slideshow { position: relative; height: 430px; overflow: hidden; background: #e8efea; }
        .project-detail-slide { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity .7s ease; pointer-events: none; background: #e8efea; }
        .project-detail-slide.active { opacity: 1; pointer-events: auto; }
        .slide-control { position: absolute; top: 50%; width: 42px; height: 42px; border: 0; border-radius: 50%; color: #fff; background: rgba(18,61,52,.82); transform: translateY(-50%); }
        .slide-control.prev { left: 18px; }.slide-control.next { right: 18px; }
        .slide-dots { position: absolute; right: 0; bottom: 16px; left: 0; display: flex; justify-content: center; gap: 7px; }
        .slide-dot { width: 8px; height: 8px; padding: 0; border: 0; border-radius: 50%; background: rgba(255,255,255,.58); }.slide-dot.active { background: #c9a35b; }
        .project-detail-empty { height: 430px; display: grid; place-items: center; color: #98aaa0; background: #e8efea; font-size: 55px; }
        .project-detail-copy { padding: 42px; }
        .project-detail-copy h2 { color: #123d34; font-weight: 800; }
        .project-detail-copy p { color: #71817a; line-height: 1.8; }
        .project-detail-meta { display: flex; flex-wrap: wrap; gap: 12px 28px; margin: 24px 0 28px; color: #71817a; font-size: 13px; }
        .project-detail-meta i { margin-right: 6px; color: #c9a35b; }
        @media (max-width: 575px) { .project-detail-copy { padding: 28px 22px; } .project-detail-slideshow, .project-detail-empty { height: 270px; } }
    </style>
</head>
<body>
    <div class="container-fluid nav-bar p-0">
        <div class="container-lg p-0">
            <nav class="navbar navbar-expand-lg bg-secondary navbar-dark">
                <a href="index.html" class="navbar-brand d-flex align-items-center"><img src="logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo"></a>
                <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse justify-content-between" id="navbarCollapse"><div class="navbar-nav ml-auto py-0">
                    <a href="index.html" class="nav-item nav-link">Home</a><a href="service.html" class="nav-item nav-link">Services</a><a href="constructions.html" class="nav-item nav-link active">Projects</a><a href="real-estate.html" class="nav-item nav-link">Properties</a><a href="about.html" class="nav-item nav-link">About</a><a href="quote.php" class="nav-item nav-link">Get a Quote</a><a href="contact.html" class="nav-item nav-link">Contact</a>
                </div></div>
            </nav>
        </div>
    </div>

    <header class="project-detail-hero">
        <div class="container">
            <small class="text-primary text-uppercase font-weight-bold">Project details</small>
            <h1 class="display-4 mt-3 mb-3"><?= htmlspecialchars($project["project_name"], ENT_QUOTES, "UTF-8") ?></h1>
            <p class="lead mb-0"><i class="fas fa-map-marker-alt mr-2"></i><?= htmlspecialchars($project["location"], ENT_QUOTES, "UTF-8") ?></p>
        </div>
    </header>

    <main class="container project-detail-wrap pb-5">
        <article class="project-detail-card">
            <?php if ($media !== []): ?>
                <div class="project-detail-slideshow" data-slideshow>
                    <?php foreach ($media as $index => $media_item): ?>
                        <?php if ($media_item["media_type"] === "video"): ?>
                            <video class="project-detail-slide <?= $index === 0 ? "active" : "" ?>" controls preload="metadata"><source src="<?= htmlspecialchars($media_item["media_path"], ENT_QUOTES, "UTF-8") ?>"></video>
                        <?php else: ?>
                            <img class="project-detail-slide <?= $index === 0 ? "active" : "" ?>" src="<?= htmlspecialchars($media_item["media_path"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($project["project_name"], ENT_QUOTES, "UTF-8") ?> project image" loading="<?= $index === 0 ? "eager" : "lazy" ?>" decoding="async">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($media) > 1): ?>
                        <button class="slide-control prev" type="button" data-slide-prev aria-label="Previous media"><i class="fas fa-chevron-left"></i></button>
                        <button class="slide-control next" type="button" data-slide-next aria-label="Next media"><i class="fas fa-chevron-right"></i></button>
                        <div class="slide-dots"><?php foreach ($media as $index => $media_item): ?><button class="slide-dot <?= $index === 0 ? "active" : "" ?>" type="button" data-slide-dot="<?= $index ?>" aria-label="Show media <?= $index + 1 ?>"></button><?php endforeach; ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="project-detail-empty" role="img" aria-label="No project image uploaded"><i class="fas fa-building" aria-hidden="true"></i></div>
            <?php endif; ?>
            <div class="project-detail-copy">
                <span class="badge badge-primary px-3 py-2"><?= htmlspecialchars($project["project_status"], ENT_QUOTES, "UTF-8") ?></span>
                <div class="project-detail-meta">
                    <span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($project["location"], ENT_QUOTES, "UTF-8") ?></span>
                    <?php if (!empty($project["start_date"])): ?><span><i class="fas fa-calendar-alt"></i>Started <?= htmlspecialchars($project["start_date"], ENT_QUOTES, "UTF-8") ?></span><?php endif; ?>
                    <?php if (!empty($project["completion_date"])): ?><span><i class="fas fa-flag-checkered"></i>Completed <?= htmlspecialchars($project["completion_date"], ENT_QUOTES, "UTF-8") ?></span><?php endif; ?>
                </div>
                <h2>Project summary</h2>
                <p><?= nl2br(htmlspecialchars($description, ENT_QUOTES, "UTF-8")) ?></p>
                <a class="btn btn-primary mt-3" href="quote.php">Discuss a similar project <i class="fas fa-arrow-right ml-2"></i></a>
                <a class="btn btn-outline-primary mt-3 ml-2" href="constructions.html#projects">Back to projects</a>
            </div>
        </article>
    </main>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    <script>
        const slideshow = document.querySelector("[data-slideshow]");
        if (slideshow) {
            const slides = [...slideshow.querySelectorAll(".project-detail-slide")];
            const dots = [...slideshow.querySelectorAll(".slide-dot")];
            let current = 0;
            const showSlide = (index) => {
                current = (index + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => {
                    slide.classList.toggle("active", slideIndex === current);
                    if (slide.tagName === "VIDEO") {
                        slide.pause();
                        if (slideIndex === current) slide.play().catch(() => {});
                    }
                });
                dots.forEach((dot, dotIndex) => dot.classList.toggle("active", dotIndex === current));
            };
            slideshow.querySelector("[data-slide-prev]")?.addEventListener("click", () => showSlide(current - 1));
            slideshow.querySelector("[data-slide-next]")?.addEventListener("click", () => showSlide(current + 1));
            dots.forEach((dot) => dot.addEventListener("click", () => showSlide(Number(dot.dataset.slideDot))));
            showSlide(0);
            if (slides.length > 1) setInterval(() => showSlide(current + 1), 6000);
        }
    </script>
    <script src="js/site-settings.js" defer></script>
</body>
</html>
