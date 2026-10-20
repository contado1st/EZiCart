import "./password-visibility.js";
import "./registration-name-inputs.js";
import "@h2d2/shopicons/es/regular/account";
import "@h2d2/shopicons/es/regular/arrowback";
import "@h2d2/shopicons/es/regular/bell";
import "@h2d2/shopicons/es/regular/cart1";
import "@h2d2/shopicons/es/regular/cart2";
import "@h2d2/shopicons/es/regular/checklist";
import "@h2d2/shopicons/es/regular/close";
import "@h2d2/shopicons/es/regular/delivery1";
import "@h2d2/shopicons/es/regular/diagram";
import "@h2d2/shopicons/es/regular/exclamationtriangle";
import "@h2d2/shopicons/es/regular/invoice";
import "@h2d2/shopicons/es/regular/lightbulb";
import "@h2d2/shopicons/es/regular/location";
import "@h2d2/shopicons/es/regular/lock";
import "@h2d2/shopicons/es/regular/megaphone";
import "@h2d2/shopicons/es/regular/money";
import "@h2d2/shopicons/es/regular/note";
import "@h2d2/shopicons/es/regular/package1";
import "@h2d2/shopicons/es/regular/phone";
import "@h2d2/shopicons/es/regular/print";
import "@h2d2/shopicons/es/regular/settings";
import "@h2d2/shopicons/es/regular/shield";
import "@h2d2/shopicons/es/regular/smileypositive";
import "@h2d2/shopicons/es/regular/voucher";
import "@h2d2/shopicons/es/regular/warning";

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

document.querySelectorAll("[data-seller-type]").forEach((select) => {
    const updateDocuments = () => {
        document.querySelectorAll("[data-seller-document]").forEach((field) => {
            const input = field.querySelector('input[type="file"]');
            const applicableTypes = field.dataset.sellerDocument.split(",");
            const visible = applicableTypes.includes(select.value);

            field.hidden = !visible;
            if (input) {
                input.required = visible && field.dataset.requiredOnVisible !== "0";
            }
        });
    };

    select.addEventListener("change", updateDocuments);
    updateDocuments();
});

document.querySelectorAll("form[data-auth-submit]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (event.defaultPrevented) return;
        const submit = form.querySelector('button[type="submit"]');
        if (!submit) return;
        submit.disabled = true;
        submit.setAttribute("aria-busy", "true");
        submit.dataset.originalText = submit.textContent;
        submit.textContent = "Sending…";
    });
});

document.querySelectorAll(".auth-field-error").forEach((error, index) => {
    const field = error.closest(".auth-field")?.querySelector("input, select, textarea");
    if (!field) return;
    if (!error.id) error.id = `auth-field-error-${index + 1}`;
    field.setAttribute("aria-invalid", "true");
    const describedBy = new Set((field.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean));
    describedBy.add(error.id);
    field.setAttribute("aria-describedby", Array.from(describedBy).join(" "));
});

window.addEventListener("pageshow", () => {
    document.querySelectorAll("form[data-auth-submit] button[type='submit']").forEach((button) => {
        button.disabled = false;
        button.removeAttribute("aria-busy");
        if (button.dataset.originalText) button.textContent = button.dataset.originalText;
    });
});

document.querySelectorAll("[data-ops-shell]").forEach((shell) => {
    const sidebar = shell.querySelector(".ops-sidebar");
    const toggle = shell.querySelector("[data-ops-menu-toggle]");
    const closeButton = shell.querySelector("[data-ops-menu-close]");
    const overlay = shell.querySelector("[data-ops-menu-overlay]");
    const mobileViewport = window.matchMedia("(max-width: 767px)");

    if (!sidebar || !toggle || !closeButton || !overlay) {
        return;
    }

    const focusableItems = () =>
        sidebar.querySelectorAll(
            'a[href]:not([tabindex="-1"]), button:not([disabled]), [tabindex]:not([tabindex="-1"])',
        );

    const setMenuOpen = (isOpen, returnFocus = false) => {
        const open = isOpen && mobileViewport.matches;

        sidebar.classList.toggle("is-open", open);
        overlay.classList.toggle("is-visible", open);
        sidebar.inert = mobileViewport.matches && !open;
        toggle.setAttribute("aria-expanded", String(open));
        toggle.setAttribute(
            "aria-label",
            open ? "Close operations navigation" : "Open operations navigation",
        );
        document.body.classList.toggle("ops-menu-open", open);

        if (open) {
            closeButton.focus();
        } else if (returnFocus) {
            toggle.focus();
        }
    };

    toggle.addEventListener("click", () => {
        setMenuOpen(toggle.getAttribute("aria-expanded") !== "true");
    });
    closeButton.addEventListener("click", () => setMenuOpen(false, true));
    overlay.addEventListener("click", () => setMenuOpen(false, true));

    sidebar.addEventListener("click", (event) => {
        if (event.target.closest("a[href]")) {
            setMenuOpen(false);
        }
    });

    shell.addEventListener("keydown", (event) => {
        if (!mobileViewport.matches || toggle.getAttribute("aria-expanded") !== "true") {
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            setMenuOpen(false, true);
            return;
        }

        if (event.key !== "Tab") {
            return;
        }

        const items = Array.from(focusableItems());
        const firstItem = items[0];
        const lastItem = items.at(-1);

        if (event.shiftKey && document.activeElement === firstItem) {
            event.preventDefault();
            lastItem?.focus();
        } else if (!event.shiftKey && document.activeElement === lastItem) {
            event.preventDefault();
            firstItem?.focus();
        }
    });

    mobileViewport.addEventListener("change", () => {
        setMenuOpen(false);
        sidebar.inert = mobileViewport.matches;
    });

    sidebar.inert = mobileViewport.matches;
});

document.querySelectorAll("[data-qr-input]").forEach((input) => {
    const form = input.closest("form");
    const startButton = form?.querySelector("[data-qr-start]");
    const stopButton = form?.querySelector("[data-qr-stop]");
    const video = form?.querySelector("[data-qr-video]");
    const videoContainer = form?.querySelector("[data-qr-video-container]");
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
        if (videoContainer) videoContainer.hidden = true;
        startButton.hidden = false;
        stopButton.hidden = true;
    };

    stopButton.addEventListener("click", () => {
        stop();
        method.value = "manual";
        status.textContent = "Camera closed. Start QR camera again or choose another scan method.";
    });
    startButton.addEventListener("click", async () => {
        if (!window.isSecureContext) {
            status.textContent =
                "Camera access requires HTTPS or localhost. Use a scanner gun or manual entry here.";
            return;
        }

        if (
            !("BarcodeDetector" in window) ||
            !navigator.mediaDevices?.getUserMedia
        ) {
            status.textContent =
                "QR camera scanning is not supported in this browser. Use a scanner gun or enter the parcel reference manually.";
            return;
        }

        try {
            const detector = new BarcodeDetector({ formats: ["qr_code"] });
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "environment" },
            });
            video.srcObject = stream;
            video.hidden = false;
            if (videoContainer) videoContainer.hidden = false;
            await video.play();
            active = true;
            startButton.hidden = true;
            stopButton.hidden = false;
            method.value = "camera";
            status.textContent =
                "Camera is on. Center the parcel QR code in the frame.";

            const scan = async () => {
                if (!active) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes.length > 0 && codes[0].rawValue) {
                        input.value = codes[0].rawValue;
                        status.textContent =
                            "QR code captured. Sending it for parcel verification…";
                        if (navigator.vibrate) {
                            navigator.vibrate([40, 30, 40]);
                        }
                        stop();
                        form.requestSubmit();
                        return;
                    }
                } catch (error) {
                    status.textContent =
                        "Could not read that QR code. Improve the lighting or use a scanner gun or manual entry.";
                }
                window.requestAnimationFrame(scan);
            };
            window.requestAnimationFrame(scan);
        } catch (error) {
            stop();
            status.textContent =
                "Camera access was denied or unavailable. Allow camera access, then retry, or use a scanner gun or manual entry.";
        }
    });

    modeTabs?.forEach((tab) => {
        tab.addEventListener("click", () => {
            modeTabs.forEach((t) => {
                t.classList.remove("is-active");
                t.setAttribute("aria-pressed", "false");
            });
            tab.classList.add("is-active");
            tab.setAttribute("aria-pressed", "true");
            const mode = tab.dataset.scanMode;
            if (mode === "camera") {
                method.value = "manual";
                startButton.click();
            } else {
                stop();
                method.value = mode === "handheld" ? "handheld" : "manual";
                input.focus();
                status.textContent =
                    mode === "handheld"
                        ? "Scanner gun ready. Scan the parcel label to verify it."
                        : "Enter the parcel QR value or order number, then select Verify parcel.";
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
                accessibleCopy.className = "sr-only";
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
