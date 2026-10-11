const registrationNameInputsReady = "data-registration-name-inputs-ready";

if (!document.documentElement.hasAttribute(registrationNameInputsReady)) {
    document.documentElement.setAttribute(registrationNameInputsReady, "");

    document.addEventListener("input", (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement)) return;

        const allowedCharacters = input.matches("[data-name-only]")
            ? /[^\p{L}\p{M} ]/gu
            : input.matches("[data-name-suffix]")
              ? /[^\p{L}\p{M}\p{N} .-]/gu
              : null;

        if (!allowedCharacters) return;

        const selectionStart = input.selectionStart;
        const selectionEnd = input.selectionEnd;
        const valueBeforeCursor = input.value.slice(0, selectionStart);
        const valueBeforeSelectionEnd = input.value.slice(0, selectionEnd);
        const sanitizedValue = input.value.replace(allowedCharacters, "");

        if (sanitizedValue === input.value) return;

        input.value = sanitizedValue;
        input.setSelectionRange(
            valueBeforeCursor.replace(allowedCharacters, "").length,
            valueBeforeSelectionEnd.replace(allowedCharacters, "").length,
        );
    });
}
