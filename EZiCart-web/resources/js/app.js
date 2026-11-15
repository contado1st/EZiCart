import { initQrScanners } from './qr-camera.js';
import "./password-visibility.js";
import "./registration-name-inputs.js";
if (document.querySelector('form[data-decision]')) {
    import('./interactions/decision-dialog.jsx');
}
if (document.querySelector('[data-analytics-charts]')) {
    import('./analytics-charts.jsx');
}
document.querySelector('[data-analytics-print]')?.addEventListener('click', () => window.print());

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

initQrScanners();

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
                    (1.12 + progress * 0.04).toFixed(4),
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
