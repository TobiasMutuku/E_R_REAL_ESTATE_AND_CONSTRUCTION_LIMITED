(() => {
    "use strict";

    const sidebar = document.querySelector(".admin-sidebar");
    const header = document.querySelector(".admin-header");
    if (!sidebar || !header) return;

    const toggle = document.createElement("button");
    toggle.className = "mobile-menu";
    toggle.type = "button";
    toggle.setAttribute("aria-label", "Open administration menu");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-controls", "adminSidebar");
    toggle.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
    header.prepend(toggle);
    sidebar.id = "adminSidebar";

    const overlay = document.createElement("button");
    overlay.className = "sidebar-overlay";
    overlay.type = "button";
    overlay.setAttribute("aria-label", "Close administration menu");
    document.body.append(overlay);

    const close = () => {
        sidebar.classList.remove("open");
        overlay.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Open administration menu");
    };
    const open = () => {
        sidebar.classList.add("open");
        overlay.classList.add("open");
        toggle.setAttribute("aria-expanded", "true");
        toggle.setAttribute("aria-label", "Close administration menu");
    };

    toggle.addEventListener("click", () => {
        if (sidebar.classList.contains("open")) close();
        else open();
    });
    overlay.addEventListener("click", close);
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") close();
    });
    sidebar.querySelectorAll("a").forEach((link) => link.addEventListener("click", close));
})();
