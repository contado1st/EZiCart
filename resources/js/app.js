document.querySelectorAll(".ops-shell .ops-table").forEach((table) => {
    const headers = Array.from(table.querySelectorAll("thead th")).map(
        (header) => header.textContent.trim().replace(/\s+/g, " "),
    );

    table.querySelectorAll("tbody tr").forEach((row) => {
        row.querySelectorAll("td:not([colspan])").forEach((cell, index) => {
            if (headers[index]) {
                cell.dataset.label = headers[index];
            }
        });
    });
});

document.querySelectorAll("[data-qr-input]").forEach((input) => {
    const form = input.closest("form");
    const startButton = form?.querySelector("[data-qr-start]");
    const stopButton = form?.querySelector("[data-qr-stop]");
    const video = form?.querySelector("[data-qr-video]");
    const status = form?.querySelector("[data-qr-status]");
    const method = form?.querySelector('[name="method"]');
    const modeTabs = form?.querySelectorAll("[data-scan-mode]");

    if (!form || !startButton || !stopButton || !video || !status || !method) {
        return;
    }

    let stream = null;
    let active = false;

    const stop = () => {
        active = false;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        video.hidden = true;
        startButton.hidden = false;
        stopButton.hidden = true;
    };

    stopButton.addEventListener("click", stop);
    startButton.addEventListener("click", async () => {
        if (
            !("BarcodeDetector" in window) ||
            !navigator.mediaDevices?.getUserMedia
        ) {
            status.textContent =
                "Camera scanning is unavailable. Use a handheld scanner or enter the code manually.";
            return;
        }

        try {
            const detector = new BarcodeDetector({ formats: ["qr_code"] });
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "environment" },
            });
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            active = true;
            startButton.hidden = true;
            stopButton.hidden = false;
            method.value = "camera";
            status.textContent =
                "Point the camera at the parcel QR code or barcode.";

            const scan = async () => {
                if (!active) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes.length > 0 && codes[0].rawValue) {
                        input.value = codes[0].rawValue;
                        status.textContent =
                            "Code captured! Submitting verification…";
                        if (navigator.vibrate) {
                            navigator.vibrate([40, 30, 40]);
                        }
                        stop();
                        form.requestSubmit();
                        return;
                    }
                } catch (error) {
                    status.textContent =
                        "Could not read the code. Adjust lighting or enter the reference manually.";
                }
                window.requestAnimationFrame(scan);
            };
            window.requestAnimationFrame(scan);
        } catch (error) {
            stop();
            status.textContent =
                "Camera access is unavailable. Use a handheld scanner or enter the code manually.";
        }
    });

    modeTabs?.forEach((tab) => {
        tab.addEventListener("click", () => {
            modeTabs.forEach((t) => t.classList.remove("is-active"));
            tab.classList.add("is-active");
            const mode = tab.dataset.scanMode;
            if (mode === "camera") {
                startButton.click();
            } else {
                stop();
                method.value = mode === "handheld" ? "handheld" : "manual";
                input.focus();
                status.textContent =
                    mode === "handheld"
                        ? "Scanner gun ready. Scan the barcode to auto-submit."
                        : "Enter the code or order number manually.";
            }
        });
    });

    form.addEventListener("submit", () => {
        if (method.value !== "camera" && method.value !== "handheld") {
            method.value = "manual";
        }
        stop();
    });
});

document.querySelectorAll("[data-qr-batch]").forEach((input) => {
    const form = input.closest("form");
    const startButton = form?.querySelector("[data-qr-batch-start]");
    const stopButton = form?.querySelector("[data-qr-batch-stop]");
    const video = form?.querySelector("[data-qr-batch-video]");
    const status = form?.querySelector("[data-qr-batch-status]");
    const method = form?.querySelector('[name="method"]');

    if (!form || !startButton || !stopButton || !video || !status || !method) {
        return;
    }

    let stream = null;
    let active = false;
    const scannedCodes = new Set();

    const stop = () => {
        active = false;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        video.hidden = true;
        startButton.hidden = false;
        stopButton.hidden = true;
    };

    stopButton.addEventListener("click", stop);
    startButton.addEventListener("click", async () => {
        if (
            !("BarcodeDetector" in window) ||
            !navigator.mediaDevices?.getUserMedia
        ) {
            status.textContent =
                "Camera scanning is unavailable. Use a handheld scanner or enter each code on a separate line.";
            return;
        }

        try {
            const detector = new BarcodeDetector({ formats: ["qr_code"] });
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "environment" },
            });
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            active = true;
            startButton.hidden = true;
            stopButton.hidden = false;
            method.value = "camera";
            status.textContent = "Scan each selected parcel label once.";

            const scan = async () => {
                if (!active) return;
                try {
                    const codes = await detector.detect(video);
                    const scannedCode = codes[0]?.rawValue?.trim();
                    if (scannedCode && !scannedCodes.has(scannedCode)) {
                        scannedCodes.add(scannedCode);
                        input.value = [
                            ...input.value.split(/\r?\n/).filter(Boolean),
                            scannedCode,
                        ].join("\n");
                        status.textContent = `${scannedCodes.size} parcel label(s) captured.`;
                    }
                } catch (error) {
                    status.textContent =
                        "Could not read the QR code. Try again or enter the code manually.";
                }
                window.requestAnimationFrame(scan);
            };
            window.requestAnimationFrame(scan);
        } catch (error) {
            stop();
            status.textContent =
                "Camera access is unavailable. Use a handheld scanner or enter each code on a separate line.";
        }
    });

    form.addEventListener("submit", stop);
});

const editorialLanding = document.querySelector(".ez-editorial");

if (editorialLanding) {
    const scrollStages = Array.from(
        editorialLanding.querySelectorAll("[data-scroll-stage]"),
    );
    const motionPreference = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
    );
    const journeyRows = Array.from(
        editorialLanding.querySelectorAll("[data-journey-row]"),
    );
    let previousJourneyIndex = -1;
    let scrollFramePending = false;
    let revealsInitialized = false;

    const clampProgress = (progress) => Math.min(1, Math.max(0, progress));

    const prepareWordReveals = () => {
        editorialLanding
            .querySelectorAll("[data-word-reveal]")
            .forEach((element) => {
                const copy = element.textContent.trim().replace(/\s+/g, " ");
                const wordRun = document.createElement("span");
                const accessibleCopy = document.createElement("span");

                wordRun.className = "word-run";
                wordRun.setAttribute("aria-hidden", "true");
                accessibleCopy.className = "visually-hidden";
                accessibleCopy.textContent = copy;

                copy.split(" ").forEach((word, index) => {
                    if (index > 0) {
                        wordRun.append(document.createTextNode(" "));
                    }

                    const wordElement = document.createElement("span");
                    wordElement.className = "word";
                    wordElement.style.setProperty(
                        "--word-delay",
                        `${index * 38}ms`,
                    );
                    wordElement.textContent = word;
                    wordRun.append(wordElement);
                });

                element.replaceChildren(wordRun, accessibleCopy);
                element.classList.add("word-reveal");
                element.dataset.rev = "";
            });
    };

    const updateJourneyRows = (activeCount) => {
        if (activeCount === previousJourneyIndex) {
            return;
        }

        journeyRows.forEach((row, index) => {
            row.classList.toggle("is-active", index < activeCount);
        });
        previousJourneyIndex = activeCount;
    };

    const updateEditorialProgress = () => {
        scrollFramePending = false;

        if (motionPreference.matches) {
            scrollStages.forEach((stage) => {
                stage.style.setProperty("--stage-p", "1");
                stage.style.setProperty("--reveal-bottom", "0%");
                stage.style.setProperty("--reveal-headline-opacity", "1");
                stage.style.setProperty("--reveal-caption-opacity", "1");
                stage.style.setProperty("--hero-image-scale", "1");
                stage.style.setProperty("--hero-image-y", "0%");
                stage.style.setProperty("--hero-copy-y", "0px");
                stage.style.setProperty("--hero-copy-opacity", "1");
                stage.style.setProperty("--composition-image-scale", "1");
            });
            updateJourneyRows(journeyRows.length);
            return;
        }

        const viewportHeight = window.innerHeight || 1;
        const currentScroll = window.scrollY;

        scrollStages.forEach((stage) => {
            const stageTop = stage.getBoundingClientRect().top + currentScroll;
            const stageDistance = Math.max(
                1,
                stage.offsetHeight - viewportHeight,
            );
            const progress = clampProgress(
                (currentScroll - stageTop) / stageDistance,
            );
            const stageName = stage.dataset.scrollStage;

            stage.style.setProperty("--stage-p", progress.toFixed(4));

            if (stageName === "hero") {
                stage.style.setProperty(
                    "--hero-image-scale",
                    (1.04 + progress * 0.07).toFixed(4),
                );
                stage.style.setProperty(
                    "--hero-image-y",
                    `${(-3 * progress).toFixed(3)}%`,
                );
                stage.style.setProperty(
                    "--hero-copy-y",
                    `${(-46 * progress).toFixed(2)}px`,
                );
                stage.style.setProperty(
                    "--hero-copy-opacity",
                    (1 - progress * 0.35).toFixed(4),
                );
            }

            if (stageName === "reveal") {
                stage.style.setProperty(
                    "--reveal-bottom",
                    `${((1 - progress) * 100).toFixed(3)}%`,
                );
                stage.style.setProperty(
                    "--reveal-headline-opacity",
                    clampProgress(
                        1 - Math.max(0, progress - 0.45) * 1.8,
                    ).toFixed(4),
                );
                stage.style.setProperty(
                    "--reveal-caption-opacity",
                    clampProgress((progress - 0.45) / 0.24).toFixed(4),
                );
            }

            if (stageName === "composition") {
                stage.style.setProperty(
                    "--composition-image-scale",
                    (1.08 - progress * 0.08).toFixed(4),
                );
                const nextActiveCount = Math.max(
                    1,
                    Math.ceil(progress * journeyRows.length),
                );
                updateJourneyRows(nextActiveCount);
            }
        });
    };

    const requestProgressUpdate = () => {
        if (!scrollFramePending) {
            scrollFramePending = true;
            window.requestAnimationFrame(updateEditorialProgress);
        }
    };

    const initializeEditorialLanding = () => {
        if (motionPreference.matches) {
            updateEditorialProgress();
        } else {
            if (!revealsInitialized) {
                prepareWordReveals();
                const revealElements = Array.from(
                    editorialLanding.querySelectorAll("[data-rev]"),
                );

                revealElements.forEach((element, index) => {
                    element.style.setProperty(
                        "--reveal-delay",
                        `${(index % 7) * 48}ms`,
                    );
                });

                if ("IntersectionObserver" in window) {
                    const revealObserver = new IntersectionObserver(
                        (entries, observer) => {
                            entries.forEach((entry) => {
                                if (entry.isIntersecting) {
                                    entry.target.classList.add("is-visible");
                                    observer.unobserve(entry.target);
                                }
                            });
                        },
                        { threshold: 0.12 },
                    );

                    revealElements.forEach((element) => {
                        revealObserver.observe(element);
                    });
                } else {
                    revealElements.forEach((element) => {
                        element.classList.add("is-visible");
                    });
                }

                revealsInitialized = true;
            }

            document.body.classList.add("motion-ready");
            requestProgressUpdate();
        }

        const search = new URLSearchParams(window.location.search);
        if (
            search.has("category") ||
            search.has("search") ||
            search.has("page")
        ) {
            window.requestAnimationFrame(() => {
                document.getElementById("browse-products")?.scrollIntoView({
                    block: "start",
                    behavior: motionPreference.matches ? "auto" : "smooth",
                });
            });
        }
    };

    window.addEventListener("scroll", requestProgressUpdate, { passive: true });
    window.addEventListener("resize", requestProgressUpdate, { passive: true });
    motionPreference.addEventListener("change", () => {
        if (motionPreference.matches) {
            requestProgressUpdate();
        } else {
            initializeEditorialLanding();
        }
    });

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initializeEditorialLanding,
            {
                once: true,
            },
        );
    } else {
        initializeEditorialLanding();
    }
}
