(() => {
    const initializeRegistrationWizards = () => {
        document.querySelectorAll("form[data-registration-wizard]").forEach((form) => {
            if (form.dataset.wizardInitialized === "true") return;
            form.dataset.wizardInitialized = "true";

            const steps = [...form.querySelectorAll("[data-registration-step]")];
            const progress = form.closest(".auth-content")?.querySelector("[data-registration-progress]");
            if (!steps.length || !progress) return;

            const track = progress.querySelector('[role="progressbar"]');
            const fill = progress.querySelector("[data-progress-fill]");
            const count = progress.querySelector("[data-step-count]");
            const title = progress.querySelector("[data-step-title]");
            const tabs = document.createElement("div");
            tabs.className = "registration-step-tabs";
            tabs.setAttribute("role", "tablist");
            tabs.setAttribute("aria-label", "Registration sections");
            progress.after(tabs);

            const tabButtons = steps.map((step, index) => {
                const id = `registration-step-${index + 1}`;
                const tab = document.createElement("button");
                step.id = step.id || id;
                step.setAttribute("role", "tabpanel");
                step.setAttribute("aria-labelledby", `${id}-tab`);
                tab.type = "button";
                tab.id = `${id}-tab`;
                tab.className = "registration-step-tab";
                tab.setAttribute("role", "tab");
                tab.setAttribute("aria-controls", step.id);
                tab.textContent = `${index + 1}. ${step.dataset.stepLabel}`;
                tab.addEventListener("click", () => showStep(index));
                tabs.append(tab);
                return tab;
            });

            const actions = document.createElement("div");
            actions.className = "registration-actions";
            const back = document.createElement("button");
            back.type = "button";
            back.className = "registration-back";
            back.textContent = "Back";
            back.addEventListener("click", () => showStep(current - 1));
            const next = document.createElement("button");
            next.type = "button";
            next.className = "auth-submit registration-next";
            next.textContent = "Continue";
            next.addEventListener("click", () => {
                const invalid = invalidControlIn(steps[current]);
                if (invalid) {
                    invalid.reportValidity();
                    invalid.focus();
                    return;
                }
                showStep(current + 1);
            });
            const submit = form.querySelector('button[type="submit"]');
            submit?.parentElement.insertBefore(actions, submit);
            actions.append(back, next);

            let current = 0;
            const invalidControlIn = (step) => [...step.querySelectorAll("input, select, textarea")]
                .find((control) => !control.disabled && !control.checkValidity());

            function showStep(index, focusTab = false) {
                current = Math.max(0, Math.min(index, steps.length - 1));
                steps.forEach((step, stepIndex) => {
                    const active = stepIndex === current;
                    step.hidden = !active;
                    step.setAttribute("aria-hidden", String(!active));
                    tabButtons[stepIndex].setAttribute("aria-selected", String(active));
                    tabButtons[stepIndex].tabIndex = active ? 0 : -1;
                    tabButtons[stepIndex].classList.toggle("is-current", active);
                    tabButtons[stepIndex].classList.toggle("is-complete", stepIndex < current);
                });
                const label = steps[current].dataset.stepLabel;
                count.textContent = `Step ${current + 1} of ${steps.length}`;
                title.textContent = label;
                track?.setAttribute("aria-valuemax", String(steps.length));
                track?.setAttribute("aria-valuenow", String(current + 1));
                fill?.style.setProperty("--registration-progress", `${((current + 1) / steps.length) * 100}%`);
                back.hidden = current === 0;
                next.hidden = current === steps.length - 1;
                if (submit) submit.hidden = current !== steps.length - 1;
                if (focusTab) tabButtons[current].focus();
            }

            tabs.addEventListener("keydown", (event) => {
                const keys = { ArrowRight: 1, ArrowLeft: -1, Home: -current, End: steps.length - 1 - current };
                if (!(event.key in keys)) return;
                event.preventDefault();
                showStep(current + keys[event.key], true);
            });

            form.addEventListener("submit", (event) => {
                for (let index = 0; index < steps.length; index += 1) {
                    const invalid = invalidControlIn(steps[index]);
                    if (!invalid) continue;
                    event.preventDefault();
                    showStep(index);
                    invalid.focus();
                    invalid.reportValidity();
                    return;
                }
            }, true);

            const firstErrorStep = steps.findIndex((step) => step.querySelector(".auth-field-error"));
            showStep(firstErrorStep >= 0 ? firstErrorStep : 0);
        });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeRegistrationWizards, { once: true });
    } else {
        initializeRegistrationWizards();
    }
})();
