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
                if (!container) return;

                const address = container.querySelector('a[href*="geolocation"], p');
                if (address && !address.contains(icon)) {
                    setDirectText(address, settings.office_address);
                } else if (icon.parentElement === container) {
                    setDirectText(container, settings.office_address);
                }
            });

            const encodedAddress = encodeURIComponent(settings.office_address);
            document.querySelectorAll("[data-office-map]").forEach((map) => {
                map.src = `https://maps.google.com/maps?q=${encodedAddress}&output=embed`;
            });
            document.querySelectorAll("[data-office-directions]").forEach((link) => {
                link.href = `https://www.google.com/maps/search/?api=1&query=${encodedAddress}`;
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
