// QR decoding stays on this device; the existing form verifies captured references.
export async function createQrDecoder(browser = window, doc = document, load = () => import('jsqr')) {
    const software = async () => {
        const {default: decode} = await load();
        const canvas = doc.createElement('canvas');
        const context = canvas.getContext('2d', {willReadFrequently: true});
        if (!context) throw new Error('QR decoder could not create a camera frame.');
        return async video => {
            if (video.readyState < 2 || !video.videoWidth || !video.videoHeight) return null;
            const scale = Math.min(1, 960 / Math.max(video.videoWidth, video.videoHeight));
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const frame = context.getImageData(0, 0, canvas.width, canvas.height);
            return decode(frame.data, frame.width, frame.height, {inversionAttempts: 'attemptBoth'})?.data || null;
        };
    };
    try {
        if (browser.BarcodeDetector) {
            const formats = await browser.BarcodeDetector.getSupportedFormats?.();
            if (!formats || formats.includes('qr_code')) {
                const detector = new browser.BarcodeDetector({formats: ['qr_code']});
                let fallback;
                return async video => {
                    if (fallback) return fallback(video);
                    try { return (await detector.detect(video))[0]?.rawValue || null; }
                    catch { fallback = await software(); return fallback(video); }
                };
            }
        }
    } catch { /* Use the local decoder when native QR support is unavailable. */ }
    return software();
}

export async function openCamera(media, deviceId = '') {
    try {
        return await media.getUserMedia({
            audio: false,
            video: deviceId ? {deviceId: {exact: deviceId}} : {facingMode: {ideal: 'environment'}},
        });
    } catch (error) {
        if (!deviceId && ['OverconstrainedError', 'NotFoundError'].includes(error.name)) {
            return media.getUserMedia({audio: false, video: true});
        }
        throw error;
    }
}

export function cameraErrorMessage(error) {
    if (['NotAllowedError', 'SecurityError'].includes(error.name)) return 'Camera permission is blocked. Allow the camera for this site in your browser and device settings, then select Start camera again.';
    if (['NotFoundError', 'DevicesNotFoundError'].includes(error.name)) return 'No camera was found. Connect or enable a webcam, then try again. You can also use a scanner or manual entry.';
    if (['NotReadableError', 'TrackStartError', 'AbortError'].includes(error.name)) return 'The camera could not start. Close other apps or tabs using it and check that the webcam is enabled, then try again.';
    if (error.name === 'OverconstrainedError') return 'The selected camera is unavailable. Choose another camera or reconnect it, then try again.';
    return 'Camera scanning could not start. Retry, reload the page, or use a scanner or manual entry.';
}

export function initQrScanners(doc = document, browser = window, nav = navigator) {
    let owner = null;
    let decoderPromise;
    let nextId = 0;
    const scanners = [];
    const decoder = () => decoderPromise ||= createQrDecoder(browser, doc).catch(error => { decoderPromise = null; throw error; });

    const attach = (input, batch) => {
        const form = input.closest('form');
        const prefix = batch ? 'data-qr-batch' : 'data-qr';
        const start = form?.querySelector(`[${prefix}-start]`);
        const close = form?.querySelector(`[${prefix}-stop]`);
        const video = form?.querySelector(`[${prefix}-video]`);
        const status = form?.querySelector(`[${prefix}-status]`);
        const viewport = batch ? null : form?.querySelector('[data-qr-video-container]');
        const method = form?.querySelector('[name="method"]');
        if (!form || !start || !close || !video || !status) return;

        const picker = doc.createElement('label');
        picker.className = 'qr-camera-picker';
        picker.hidden = true;
        picker.textContent = 'Camera';
        const select = doc.createElement('select');
        select.id = `qr-camera-${++nextId}`;
        picker.htmlFor = select.id;
        picker.append(select);
        start.insertAdjacentElement('beforebegin', picker);
        video.classList.add('qr-camera-video');
        video.muted = true;
        video.playsInline = true;
        const startLabel = start.textContent;
        let stream = null;
        let version = 0;
        let timer = null;
        let running = false;
        let selectedDevice = '';
        const setMethod = value => { if (method) method.value = value; };
        const stop = () => {
            version++;
            running = false;
            browser.clearTimeout(timer);
            stream?.getTracks().forEach(track => track.stop());
            stream = null;
            video.srcObject = null;
            video.hidden = true;
            if (viewport) viewport.hidden = true;
            picker.hidden = true;
            select.disabled = false;
            start.disabled = false;
            start.hidden = false;
            start.textContent = startLabel;
            close.hidden = true;
            if (owner === stop) owner = null;
        };
        const listCameras = async token => {
            try {
                const devices = (await nav.mediaDevices.enumerateDevices()).filter(device => device.kind === 'videoinput');
                if (version !== token || !stream) return;
                const current = stream.getVideoTracks()[0]?.getSettings?.().deviceId;
                select.replaceChildren();
                devices.forEach((device, index) => {
                    const option = doc.createElement('option');
                    option.value = device.deviceId;
                    option.textContent = device.label || `Camera ${index + 1}`;
                    select.append(option);
                });
                select.value = current || selectedDevice || devices[0]?.deviceId || '';
                picker.hidden = devices.length < 2;
            } catch { /* The default webcam still works if device listing is restricted. */ }
        };
        const begin = async () => {
            owner?.();
            stop();
            if (!browser.isSecureContext) {
                status.textContent = 'Camera access requires HTTPS. Open https://shop.ezicart.trade or use localhost for local testing. A plain HTTP network address cannot use the camera.';
                return;
            }
            if (!nav.mediaDevices?.getUserMedia) {
                status.textContent = 'This browser does not provide camera access. Open this page in an up-to-date browser, or use a scanner or manual entry.';
                return;
            }
            owner = stop;
            const token = version;
            start.disabled = true;
            start.textContent = 'Starting camera...';
            close.hidden = false;
            status.textContent = 'Allow camera access if prompted. Your built-in or USB webcam can scan QR codes.';
            let acquired;
            try {
                const detect = await decoder();
                if (token !== version) return;
                acquired = await openCamera(nav.mediaDevices, selectedDevice);
                if (token !== version || doc.hidden) { acquired.getTracks().forEach(track => track.stop()); return; }
                stream = acquired;
                video.srcObject = stream;
                video.hidden = false;
                if (viewport) viewport.hidden = false;
                await video.play();
                if (token !== version) return;
                running = true;
                start.hidden = true;
                start.disabled = false;
                start.textContent = startLabel;
                setMethod('camera');
                status.textContent = batch ? 'Scan each selected parcel label once.' : 'Camera is on. Hold the QR code in front of the webcam, with the whole code visible.';
                listCameras(token);
                stream.getVideoTracks()[0]?.addEventListener('ended', () => {
                    if (token !== version) return;
                    stop();
                    status.textContent = 'Camera disconnected or access ended. Reconnect it, then start the camera again.';
                });
                const scan = async () => {
                    if (!running || token !== version) return;
                    try {
                        const code = (await detect(video))?.trim();
                        if (!running || token !== version) return;
                        if (code) {
                            if (batch) {
                                const codes = new Set(input.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean));
                                if (!codes.has(code)) {
                                    codes.add(code);
                                    input.value = [...codes].join('\n');
                                    status.textContent = `${codes.size} parcel label(s) captured.`;
                                    input.dispatchEvent(new Event('input', {bubbles: true}));
                                }
                            } else {
                                input.value = code;
                                input.dispatchEvent(new Event('input', {bubbles: true}));
                                stop();
                                status.textContent = 'QR code captured. Check the remaining fields and confirm verification if prompted.';
                                form.requestSubmit();
                                return;
                            }
                        }
                    } catch { if (token === version) status.textContent = 'Could not read that frame. Keep the QR code steady and improve the lighting.'; }
                    if (running && token === version) timer = browser.setTimeout(scan, 180);
                };
                scan();
            } catch (error) {
                acquired?.getTracks().forEach(track => track.stop());
                if (token !== version) return;
                stop();
                if (['NotFoundError', 'OverconstrainedError'].includes(error.name)) selectedDevice = '';
                status.textContent = cameraErrorMessage(error);
            }
        };
        start.addEventListener('click', begin);
        close.addEventListener('click', () => {
            stop();
            status.textContent = 'Camera closed. Start the camera again or use a scanner or manual entry.';
        });
        select.addEventListener('change', () => { selectedDevice = select.value; begin(); });
        form.addEventListener('submit', stop);
        if (!batch) {
            const tabs = form.closest('section')?.querySelectorAll('[data-scan-mode]') || [];
            tabs.forEach(tab => tab.addEventListener('click', () => {
                tabs.forEach(item => { item.classList.toggle('is-active', item === tab); item.setAttribute('aria-pressed', String(item === tab)); });
                if (tab.dataset.scanMode === 'camera') return begin();
                stop();
                setMethod(tab.dataset.scanMode === 'handheld' ? 'handheld' : 'manual');
                input.focus();
                status.textContent = tab.dataset.scanMode === 'handheld' ? 'Scanner gun ready. Scan the parcel label to verify it.' : 'Enter the parcel reference, then select Verify parcel.';
            }));
        }
        scanners.push(() => {
            if (owner !== stop) return;
            stop();
            status.textContent = 'Camera paused because you left this page. Start it again when you are ready.';
        });
    };
    doc.querySelectorAll('[data-qr-input]').forEach(input => attach(input, false));
    doc.querySelectorAll('[data-qr-batch]').forEach(input => attach(input, true));
    browser.addEventListener('pagehide', () => scanners.forEach(stop => stop()));
    doc.addEventListener('visibilitychange', () => { if (doc.hidden) scanners.forEach(stop => stop()); });
}
