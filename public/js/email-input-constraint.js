const emailInputSelector = "input[data-email-character-limit]";
const disallowedEmailCharacters = /[^A-Za-z0-9@.]/g;
const containsDisallowedEmailCharacter = /[^A-Za-z0-9@.]/;

const sanitizeEmailValue = (value) => {
    const sanitized = value.replace(disallowedEmailCharacters, "");
    const firstAtSign = sanitized.indexOf("@");

    if (firstAtSign === -1) return sanitized;

    return `${sanitized.slice(0, firstAtSign + 1)}${sanitized
        .slice(firstAtSign + 1)
        .replace(/@/g, "")}`;
};

const restoreSelection = (input, start, end) => {
    try {
        input.setSelectionRange(start, end);
    } catch {
        // Some browsers do not expose selection APIs for email inputs.
    }
};

document.querySelectorAll(emailInputSelector).forEach((input) => {
    input.addEventListener("beforeinput", (event) => {
        if (!event.inputType.startsWith("insert") || typeof event.data !== "string") {
            return;
        }

        if (containsDisallowedEmailCharacter.test(event.data)) {
            event.preventDefault();
            return;
        }

        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? start;
        const nextValue = `${input.value.slice(0, start)}${event.data}${input.value.slice(end)}`;

        if ((nextValue.match(/@/g) ?? []).length > 1) {
            event.preventDefault();
        }
    });

    input.addEventListener("paste", (event) => {
        event.preventDefault();

        const pastedText = event.clipboardData?.getData("text") ?? "";
        const sanitizedText = sanitizeEmailValue(pastedText);
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? start;
        const nextValue = sanitizeEmailValue(
            `${input.value.slice(0, start)}${sanitizedText}${input.value.slice(end)}`,
        );

        input.value = nextValue;
        restoreSelection(input, start + sanitizedText.length, start + sanitizedText.length);
        input.dispatchEvent(new Event("input", { bubbles: true }));
    });

    input.addEventListener("input", () => {
        const sanitizedValue = sanitizeEmailValue(input.value);

        if (sanitizedValue === input.value) return;

        const selectionStart = input.selectionStart ?? input.value.length;
        const selectionEnd = input.selectionEnd ?? selectionStart;
        const sanitizedStart = sanitizeEmailValue(input.value.slice(0, selectionStart)).length;
        const sanitizedEnd = sanitizeEmailValue(input.value.slice(0, selectionEnd)).length;

        input.value = sanitizedValue;
        restoreSelection(input, sanitizedStart, sanitizedEnd);
    });
});
