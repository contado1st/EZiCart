(() => {
    const form = document.querySelector('[data-message-composer]');
    if (!form) return;
    const input = form.querySelector('[data-message-input]');
    const count = form.querySelector('[data-message-count]');
    const button = form.querySelector('[data-message-send]');
    const files = form.querySelector('[data-message-files]');
    const preview = form.querySelector('[data-message-file-preview]');
    const feedback = form.querySelector('[data-message-upload-feedback]');
    let previewUrls = [];
    files?.addEventListener('change', () => {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls = [];
        preview.replaceChildren();
        input.setCustomValidity('');
        const selected = [...files.files];
        const maxBytes = Number(files.dataset.maxBytes);
        files.setCustomValidity(selected.length > 3 ? 'Choose at most three attachments.' : selected.some(file => file.size > maxBytes) ? `Each attachment must be ${(maxBytes / 1024 / 1024).toFixed(1)} MB or smaller.` : '');
        selected.forEach(file => {
            const item = document.createElement('div');
            item.className = 'web-attachment';
            const name = document.createElement('span');
            name.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
            if (['image/jpeg','image/png','image/webp'].includes(file.type)) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file); previewUrls.push(img.src);
                img.alt = 'Selected image preview'; item.append(img);
            }
            item.append(name); preview.append(item);
        });
        feedback.textContent = selected.length ? `${selected.length} attachment(s) ready to send.` : 'No files selected.';
    });
    const update = () => { count.textContent = input.value.length.toLocaleString(); };
    input.addEventListener('input', update);
    input.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); form.requestSubmit(); }
    });
    form.addEventListener('submit', event => {
        if (!input.value.trim() && !files?.files.length) { event.preventDefault(); input.setCustomValidity('Enter a message or choose a file before sending.'); input.reportValidity(); return; }
        if (event.defaultPrevented) return;
        if (feedback) feedback.textContent = 'Uploading and sending. Please keep this panel open.';
        button.disabled = true;
        button.textContent = 'Sending…';
    });
    input.addEventListener('input', () => input.setCustomValidity(''));
    window.addEventListener('pageshow', () => { button.disabled = false; button.textContent = 'Send message'; });
    update();
    const history = document.querySelector('[data-conversation-history]');
    if (history && !new URL(window.location.href).searchParams.has('messages_page')) history.scrollTop = history.scrollHeight;
})();
