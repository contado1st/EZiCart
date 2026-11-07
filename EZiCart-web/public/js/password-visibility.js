const passwordToggleSelector = "[data-password-toggle]";
const passwordToggleReady = "data-password-visibility-ready";

if (!document.documentElement.hasAttribute(passwordToggleReady)) {
    document.documentElement.setAttribute(passwordToggleReady, "");

    document.addEventListener("click", (event) => {
        const clickedElement =
            event.target instanceof Element
                ? event.target
                : event.target?.parentElement;
        const button = clickedElement?.closest(passwordToggleSelector);

        if (!button) return;

        const targetId = button.dataset.passwordToggle;
        const input = targetId
            ? document.getElementById(targetId)
            : button.closest(".auth-password-wrap")?.querySelector("input");

        if (!(input instanceof HTMLInputElement)) return;

        const shouldShowPassword = input.type === "password";
        input.type = shouldShowPassword ? "text" : "password";
        button.textContent = shouldShowPassword ? "Hide" : "Show";
        button.setAttribute(
            "aria-label",
            `${shouldShowPassword ? "Hide" : "Show"} password`,
        );
        button.setAttribute("aria-pressed", String(shouldShowPassword));
    });
}
