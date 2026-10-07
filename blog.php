<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E&amp;R Insights | Construction and property guidance</title>
    <meta name="description" content="Practical construction and property insights from E&R Real Estate and Construction Limited.">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="container py-5">
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
        <section id="articlesGrid" class="row" aria-live="polite"><p class="col-12">Loading published articles…</p></section>
        <a class="btn btn-primary mt-4" href="quote.php">Discuss your project</a>
    </main>
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
