const registrationNameInputsReady = "data-registration-input-constraints-ready";

if (!document.documentElement.hasAttribute(registrationNameInputsReady)) {
    document.documentElement.setAttribute(registrationNameInputsReady, "");

    const formatPhilippinePhone = (value) => {
        if (!/^\+?[0-9\s]*$/.test(value)) {
            return value;
        }

        const digits = value.replace(/\D/g, "");
        let nationalNumber = digits.startsWith("63")
            ? digits.slice(2)
            : digits.startsWith("0")
              ? digits.slice(1)
              : digits;

        if (nationalNumber.startsWith("0")) {
            nationalNumber = nationalNumber.slice(1);
        }

        if (nationalNumber.length !== 10) {
            return value;
        }

        const groups = [
            nationalNumber.slice(0, 3),
            nationalNumber.slice(3, 6),
            nationalNumber.slice(6, 10),
        ].filter(Boolean);

        return `+63 ${groups.join(" ")}`.trimEnd();
    };

    const phoneInputValues = new WeakMap();
    const tinInputValues = new WeakMap();
    const tinFormatError = "TIN must contain exactly 12 digits in the format 000-000-000-000.";

    const phoneDigitLimit = (value) => (value.replace(/\D/g, "").startsWith("63") ? 12 : 11);
    const insertedValue = (input, text) =>
        input.value.slice(0, input.selectionStart ?? input.value.length) +
        text +
        input.value.slice(input.selectionEnd ?? input.value.length);
    const formatTin = (digits) => digits.replace(/(\d{3})(?=\d)/g, "$1-");
    const tinCaretPosition = (digitCount) => digitCount + Math.max(0, Math.floor((digitCount - 1) / 3));

    document.querySelectorAll("[data-philippine-phone]").forEach((input) => {
        input.value = formatPhilippinePhone(input.value);
        phoneInputValues.set(input, input.value);
    });

    document.querySelectorAll("[data-tin-digits]").forEach((input) => {
        const digits = input.value.replace(/\D/g, "");
        const hasInvalidCharacters = !/^[\d-]*$/.test(input.value);
        input.value = formatTin(digits);
        if (hasInvalidCharacters || digits.length > 12) input.setCustomValidity(tinFormatError);
        tinInputValues.set(input, input.value);
    });

    document.addEventListener("beforeinput", (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !event.inputType.startsWith("insert") || event.data === null) return;

        if (input.matches("[data-philippine-phone]")) {
            if (insertedValue(input, event.data).replace(/\D/g, "").length > phoneDigitLimit(insertedValue(input, event.data))) {
                event.preventDefault();
            }
        } else if (input.matches("[data-tin-digits]")) {
            const candidate = insertedValue(input, event.data);
            if (/\D/.test(event.data)) {
                event.preventDefault();
                input.setCustomValidity(tinFormatError);
            } else if (candidate.replace(/\D/g, "").length > 12) {
                event.preventDefault();
            } else {
                input.setCustomValidity("");
            }
        }
    });

    document.addEventListener("paste", (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) return;

        const pastedText = event.clipboardData?.getData("text") ?? "";
        if (input.matches("[data-philippine-phone]")) {
            const candidate = insertedValue(input, pastedText);
            if (candidate.replace(/\D/g, "").length > phoneDigitLimit(candidate)) event.preventDefault();
        } else if (input.matches("[data-tin-digits]")) {
            const candidate = insertedValue(input, pastedText);
            if (/\D/.test(pastedText)) {
                event.preventDefault();
                input.setCustomValidity(tinFormatError);
            } else if (candidate.replace(/\D/g, "").length > 12) {
                event.preventDefault();
            } else {
                input.setCustomValidity("");
            }
        }
    });

    document.addEventListener("input", (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement)) return;

        if (input.matches("[data-philippine-phone]")) {
            const digits = input.value.replace(/\D/g, "");
            const digitLimit = phoneDigitLimit(input.value);

            if (digits.length > digitLimit) {
                const previousValue = phoneInputValues.get(input) ?? "";
                const caret = Math.min(input.selectionStart ?? previousValue.length, previousValue.length);
                input.value = previousValue;
                input.setSelectionRange(caret, caret);
                return;
            }

            phoneInputValues.set(input, input.value);
            return;
        }

        if (input.matches("[data-tin-digits]")) {
            const digits = input.value.replace(/\D/g, "");
            if (digits.length > 12) {
                input.value = tinInputValues.get(input) ?? "";
                input.setCustomValidity(tinFormatError);
                return;
            }

            input.setCustomValidity("");
            const digitCountBeforeCaret = input.value.slice(0, input.selectionStart ?? input.value.length).replace(/\D/g, "").length;
            input.value = formatTin(digits);
            const caret = tinCaretPosition(digitCountBeforeCaret);
            input.setSelectionRange(caret, caret);
            tinInputValues.set(input, input.value);
            return;
        }

        const allowedCharacters = input.matches("[data-name-only]")
            ? /[^\p{L}\p{M} .'-]/gu
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
            phoneInputValues.set(input, input.value);
        }
    });
}
