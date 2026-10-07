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
            status.textContent = "Point the camera at the parcel QR code or barcode.";

            const scan = async () => {
                if (!active) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes.length > 0 && codes[0].rawValue) {
                        input.value = codes[0].rawValue;
                        status.textContent = "Code captured! Submitting verification…";
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
