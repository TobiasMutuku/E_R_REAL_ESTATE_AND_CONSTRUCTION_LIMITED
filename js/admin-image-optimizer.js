(() => {
    "use strict";

    const maximumDimension = 1920;
    const quality = 0.82;
    const pending = new Map();

    function statusFor(input) {
        let status = input.parentElement.querySelector(".image-optimization-status");
        if (!status) {
            status = document.createElement("small");
            status.className = "image-optimization-status d-block text-muted mt-2";
            status.setAttribute("role", "status");
            input.insertAdjacentElement("afterend", status);
        }
        return status;
    }

    async function optimize(file) {
        if (!file.type.startsWith("image/") || typeof createImageBitmap !== "function") {
            return file;
        }

        const bitmap = await createImageBitmap(file);
        try {
            const scale = Math.min(1, maximumDimension / Math.max(bitmap.width, bitmap.height));
            if (scale === 1 && file.type === "image/webp") {
                return file;
            }

            const canvas = document.createElement("canvas");
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            const context = canvas.getContext("2d");
            if (!context) {
                throw new Error("A canvas could not be created to optimize the image.");
            }
            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/webp", quality));
            if (!blob || blob.size >= file.size) {
                return file;
            }

            const name = file.name.replace(/\.[^.]+$/, "") + ".webp";
            return new File([blob], name, { type: "image/webp", lastModified: Date.now() });
        } finally {
            bitmap.close();
        }
    }

    document.querySelectorAll('input[type="file"]').forEach((input) => {
        const form = input.form;
        if (!form) return;
        const status = statusFor(input);

        input.addEventListener("change", () => {
            const sourceFiles = Array.from(input.files || []);
            if (!sourceFiles.some((file) => file.type.startsWith("image/"))) return;

            status.textContent = "Optimizing selected images for faster loading…";
            const task = Promise.all(sourceFiles.map(optimize))
                .then((optimizedFiles) => {
                    const transfer = new DataTransfer();
                    optimizedFiles.forEach((file) => transfer.items.add(file));
                    input.files = transfer.files;
                    const optimizedCount = optimizedFiles.filter((file, index) => file !== sourceFiles[index]).length;
                    status.textContent = optimizedCount
                        ? `${optimizedCount} image${optimizedCount === 1 ? "" : "s"} resized and compressed for the website.`
                        : "Images are already optimized; originals will be uploaded.";
                })
                .catch((error) => {
                    console.error("Image optimization failed.", error);
                    status.textContent = "Image optimization failed; the selected original file will be uploaded.";
                });
            const tasks = pending.get(form) || new Set();
            tasks.add(task);
            pending.set(form, tasks);
            task.finally(() => {
                tasks.delete(task);
                if (tasks.size === 0) pending.delete(form);
            });
        });

        if (!form.dataset.imageOptimizationBound) {
            form.dataset.imageOptimizationBound = "true";
            form.addEventListener("submit", (event) => {
                if (!pending.has(form) || form.dataset.imageResubmitPending === "true") return;
                event.preventDefault();
                form.dataset.imageResubmitPending = "true";
                const waitForOptimization = async () => {
                    while (pending.has(form)) {
                        await Promise.all(Array.from(pending.get(form)));
                    }
                    form.dataset.imageResubmitPending = "false";
                    if (event.submitter) form.requestSubmit(event.submitter);
                    else form.requestSubmit();
                };
                waitForOptimization().catch((error) => {
                    form.dataset.imageResubmitPending = "false";
                    console.error("The form could not wait for image optimization.", error);
                });
            });
        }
    });
})();
