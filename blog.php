<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E&amp;R Insights | Construction and property guidance</title>
    <meta name="description" content="Practical construction and property insights from E&R Real Estate and Construction Limited.">
    <link rel="icon" href="logo/E&R Logo.jfif">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=20261007-site-audit">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="container-fluid nav-bar p-0">
        <div class="container-lg p-0">
            <nav class="navbar navbar-expand-lg bg-secondary navbar-dark" aria-label="Main navigation">
                <a href="index.html" class="navbar-brand d-flex align-items-center">
                    <img src="logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo">
                </a>
                <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse"
                    aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ml-auto py-0">
                        <a href="index.html" class="nav-item nav-link">Home</a>
                        <a href="about.html" class="nav-item nav-link">About</a>
                        <a href="service.html" class="nav-item nav-link">Services</a>
                        <a href="constructions.html" class="nav-item nav-link">Projects</a>
                        <a href="real-estate.html" class="nav-item nav-link">Properties</a>
                        <a href="quote.php" class="nav-item nav-link">Get a Quote</a>
                        <a href="contact.php" class="nav-item nav-link">Contact</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>
    <main class="container py-5" id="main-content">
        <a href="index.html">E&amp;R Real Estate and Construction</a>
        <header class="py-5">
            <p class="text-primary text-uppercase font-weight-bold">E&amp;R insights</p>
            <h1>Construction and property guidance</h1>
            <p class="lead">Practical information for planning and delivering property projects.</p>
        </header>
        <div class="form-group">
            <label for="articleSearch">Search published articles</label>
            <input class="form-control" id="articleSearch" type="search" placeholder="Search by title, summary, or category">
        </div>
        <section id="articlesGrid" class="row" aria-live="polite"><p class="col-12">Loading published articles&hellip;</p></section>
        <a class="btn btn-primary mt-4" href="quote.php">Discuss your project</a>
    </main>
    <footer class="container-fluid bg-secondary text-white mt-5 pt-5 px-sm-3 px-md-5">
        <div class="row pt-5">
            <div class="col-lg-4 col-md-6 mb-4">
                <a href="index.html" class="navbar-brand footer-brand d-flex align-items-center"><img src="logo/E&R Logo.jfif" alt="E&R Real Estate and Construction Limited logo"></a>
                <p>Construction and property guidance from E&amp;R Real Estate and Construction Limited.</p>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <h5 class="font-weight-bold text-primary mb-4">Explore E&amp;R</h5>
                <div class="d-flex flex-column">
                    <a class="text-white mb-2" href="service.html">Services</a>
                    <a class="text-white mb-2" href="constructions.html">Projects</a>
                    <a class="text-white mb-2" href="real-estate.html">Properties</a>
                    <a class="text-white mb-2" href="quote.php">Request a quote</a>
                    <a class="text-white" href="privacy.html">Privacy notice</a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <h5 class="font-weight-bold text-primary mb-4">Get in touch</h5>
                <a class="text-white d-block mb-2" href="tel:+254748766822">+254 748 766 822</a>
                <a class="text-white" href="mailto:errealestateconstruction@gmail.com">errealestateconstruction@gmail.com</a>
            </div>
        </div>
        <div class="container-fluid py-4 px-0">
            <p class="m-0 text-center">&copy; <span data-current-year>2026</span> E&amp;R Real Estate and Construction Limited. All rights reserved.</p>
        </div>
    </footer>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    <script src="js/site-settings.js" defer></script>
    <script>
        const grid = document.getElementById("articlesGrid");
        const search = document.getElementById("articleSearch");
        let articles = [];
        const escapeHtml = (value) => String(value || "")
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        const renderArticles = () => {
            const query = search.value.trim().toLocaleLowerCase();
            const visible = articles.filter((article) =>
                `${article.title} ${article.summary} ${article.category}`.toLocaleLowerCase().includes(query)
            );
            grid.innerHTML = visible.length
                ? visible.map((article) => `<article class="col-md-6 mb-4"><div class="border h-100 p-4">
                    ${article.image ? `<img class="img-fluid mb-3" src="${escapeHtml(article.image)}" alt="${escapeHtml(article.title)}" loading="lazy">` : ""}
                    <p class="text-primary text-uppercase font-weight-bold">${escapeHtml(article.category)}</p>
                    <h2 class="h4">${escapeHtml(article.title)}</h2>
                    <p>${escapeHtml(article.summary)}</p>
                    <a href="article.php?slug=${encodeURIComponent(article.slug)}">Read article</a>
                </div></article>`).join("")
                : '<p class="col-12">No published articles match. Contact E&amp;R if you need project guidance.</p>';
        };
        search.addEventListener("input", renderArticles);
        fetch("articles-feed.php")
            .then((response) => {
                if (!response.ok) throw new Error("Published articles could not be loaded.");
                return response.json();
            })
            .then((data) => {
                if (!Array.isArray(data)) throw new Error("Article data is unavailable.");
                articles = data;
                renderArticles();
            })
            .catch((error) => {
                console.error("Article feed error.", error);
                grid.innerHTML = '<p class="col-12">Published articles are temporarily unavailable. Please try again later.</p>';
            });
    </script>
</body>
</html>
