const registrationNameInputsReady = "data-registration-name-inputs-ready";

if (!document.documentElement.hasAttribute(registrationNameInputsReady)) {
    document.documentElement.setAttribute(registrationNameInputsReady, "");

    const sanitizePhoneInput = (value) => {
        const sanitized = value.replace(/[^0-9+ ]/g, "");
        const hasLeadingPlus = sanitized.startsWith("+");

        return `${hasLeadingPlus ? "+" : ""}${sanitized.replace(/\+/g, "")}`;
    };

    const formatPhilippinePhone = (value) => {
        const digits = value.replace(/\D/g, "");
        let nationalNumber = digits.startsWith("63")
            ? digits.slice(2)
            : digits.startsWith("0")
              ? digits.slice(1)
              : digits;

        if (nationalNumber.startsWith("0")) {
            nationalNumber = nationalNumber.slice(1);
        }

        nationalNumber = nationalNumber.slice(0, 10);

        const groups = [
            nationalNumber.slice(0, 3),
            nationalNumber.slice(3, 6),
            nationalNumber.slice(6, 10),
        ].filter(Boolean);

        return `+63 ${groups.join(" ")}`.trimEnd();
    };

    document.querySelectorAll("[data-philippine-phone]").forEach((input) => {
        input.value = formatPhilippinePhone(input.value);
    });

    document.addEventListener("input", (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement)) return;

        if (input.matches("[data-philippine-phone]")) {
            const selectionStart = input.selectionStart;
            const valueBeforeCursor = input.value.slice(0, selectionStart);
            const sanitizedValue = sanitizePhoneInput(input.value);

            if (sanitizedValue !== input.value) {
                input.value = sanitizedValue;
                input.setSelectionRange(
                    sanitizePhoneInput(valueBeforeCursor).length,
                    sanitizePhoneInput(valueBeforeCursor).length,
                );
            }

            return;
        }

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

    document.addEventListener("focusout", (event) => {
        const input = event.target;

        if (input instanceof HTMLInputElement && input.matches("[data-philippine-phone]")) {
            input.value = formatPhilippinePhone(input.value);
        }
    });
}
