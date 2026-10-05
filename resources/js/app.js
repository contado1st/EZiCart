document.querySelectorAll('[data-qr-input]').forEach((input) => {
    const form = input.closest('form');
    const startButton = form?.querySelector('[data-qr-start]');
    const stopButton = form?.querySelector('[data-qr-stop]');
    const video = form?.querySelector('[data-qr-video]');
    const status = form?.querySelector('[data-qr-status]');
    const method = form?.querySelector('[name="method"]');

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

    stopButton.addEventListener('click', stop);
    startButton.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Camera scanning is unavailable. Use a handheld scanner or enter the code manually.';
            return;
        }

        try {
            const detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            active = true;
            startButton.hidden = true;
            stopButton.hidden = false;
            method.value = 'camera';
            status.textContent = 'Point the camera at the QR code.';

            const scan = async () => {
                if (!active) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes.length > 0 && codes[0].rawValue) {
                        input.value = codes[0].rawValue;
                        status.textContent = 'QR code captured. Validating…';
                        stop();
                        form.requestSubmit();
                        return;
                    }
                } catch (error) {
                    status.textContent = 'Could not read the QR code. Try again or enter the code manually.';
                }
                window.requestAnimationFrame(scan);
            };
            window.requestAnimationFrame(scan);
        } catch (error) {
            stop();
            status.textContent = 'Camera access is unavailable. Use a handheld scanner or enter the code manually.';
        }
    });

    form.addEventListener('submit', () => {
        if (method.value !== 'camera') {
            method.value = 'manual';
        }
        stop();
    });
});
