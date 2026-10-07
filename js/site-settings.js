(() => {
    "use strict";

    document.querySelectorAll("img").forEach((image) => {
        image.decoding = "async";
        if (!image.hasAttribute("loading")) {
            image.loading = image.closest(".navbar-brand, .carousel-item.active") ? "eager" : "lazy";
        }
        if (image.closest(".carousel-item.active")) {
            image.fetchPriority = "high";
        }
    });

    const scriptUrl = document.currentScript?.src;
    if (!scriptUrl) return;

    const endpoint = new URL("../site-settings-feed.php", scriptUrl);
    const setDirectText = (element, value) => {
        const textNode = Array.from(element.childNodes).find((node) => node.nodeType === Node.TEXT_NODE);
        if (textNode) {
            textNode.nodeValue = value;
        } else {
            element.append(document.createTextNode(value));
        }
    };
    const renderOfficeMap = (map) => {
        map.dataset.mapState = "loading";
        const latitude = Number(map.dataset.centerLat || "-3.355");
        const longitude = Number(map.dataset.centerLon || "40.02");
        const zoom = 13;
        const tileCount = 2 ** zoom;
        const centerX = Math.floor((longitude + 180) / 360 * tileCount);
        const latitudeRadians = latitude * Math.PI / 180;
        const centerY = Math.floor(
            (1 - Math.log(Math.tan(latitudeRadians) + 1 / Math.cos(latitudeRadians)) / Math.PI)
            / 2 * tileCount
        );
        const grid = document.createElement("div");
        grid.className = "office-map-grid";
        grid.setAttribute("aria-hidden", "true");
        let loadedTiles = 0;
        const tileTotal = 15;

        for (let row = -1; row <= 1; row += 1) {
            for (let column = -2; column <= 2; column += 1) {
                const tile = document.createElement("img");
                tile.alt = "";
                tile.width = 256;
                tile.height = 256;
                tile.loading = "eager";
                tile.decoding = "async";
                tile.src = `https://a.basemaps.cartocdn.com/light_all/${zoom}/${centerX + column}/${centerY + row}.png`;
                tile.addEventListener("load", () => {
                    loadedTiles += 1;
                    if (loadedTiles === tileTotal) {
                        map.dataset.mapState = "ready";
                    }
                }, { once: true });
                tile.addEventListener("error", () => {
                    tile.remove();
                    map.dataset.mapState = "partial";
                    console.error("A Watamu map tile could not be loaded.");
                }, { once: true });
                grid.append(tile);
            }
        }

        const marker = document.createElement("span");
        marker.className = "office-map-area-label";
        marker.innerHTML = '<i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>Watamu area</span>';
        const note = document.createElement("span");
        note.className = "office-map-note";
        note.textContent = "Some map tiles could not load. Use the directions link for the address.";
        map.replaceChildren(grid, marker, note);
    };

    document.querySelectorAll("[data-office-map]").forEach(renderOfficeMap);

    fetch(endpoint)
        .then((response) => {
            if (!response.ok) throw new Error("Site settings request failed.");
            return response.json();
        })
        .then((settings) => {
            document.querySelectorAll("[data-current-year]").forEach((element) => {
                setDirectText(element, String(new Date().getFullYear()));
            });

            document.querySelectorAll('a[href^="tel:"]').forEach((link) => {
                link.href = `tel:${settings.public_phone.replace(/[^\d+]/g, "")}`;
                setDirectText(link, settings.public_phone);
            });

            document.querySelectorAll('a[href^="mailto:"]').forEach((link) => {
                link.href = `mailto:${settings.public_email}`;
                setDirectText(link, settings.public_email);
            });

            document.querySelectorAll('a[href*="wa.me/"]').forEach((link) => {
                link.href = link.href.replace(/wa\.me\/\d+/, `wa.me/${settings.whatsapp_phone}`);
            });

            document.querySelectorAll("[data-office-address]").forEach((element) => {
                setDirectText(element, settings.office_address);
            });

            document.querySelectorAll(".fa-map-marker-alt").forEach((icon) => {
                const container = icon.closest(".d-flex") || icon.closest("p") || icon.parentElement;
                if (!container || !container.textContent.toLowerCase().includes("watamu mall")) return;

                const address = container.querySelector('a[href*="geolocation"], p');
                if (address && !address.contains(icon)) {
                    setDirectText(address, settings.office_address);
                } else if (icon.parentElement === container) {
                    setDirectText(container, settings.office_address);
                }
            });

            const encodedAddress = encodeURIComponent(settings.office_address);
            document.querySelectorAll("[data-office-directions]").forEach((link) => {
                link.href = `https://www.openstreetmap.org/search?query=${encodedAddress}`;
            });

            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            let node;
            while ((node = walker.nextNode())) {
                node.nodeValue = node.nodeValue.replace(/©\s*\d{4}/g, `© ${new Date().getFullYear()}`);
                node.nodeValue = node.nodeValue.replace(/E&R REAL ESTATE AND CONSTRUCTION LIMITED/gi, settings.company_name);
            }
        })
        .catch((error) => console.error("Unable to apply current site settings.", error));
})();
