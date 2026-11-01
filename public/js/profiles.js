(() => {
    const editor = document.querySelector('[data-profile-editor]');
    if (!editor) return;
    const themes = ['rose', 'wine', 'navy', 'teal'];
    const urls = new Map();
    const status = editor.querySelector('[data-profile-preview-status]');
    const name = editor.querySelector('[data-profile-name]');
    const originalName = name.textContent;
    const setTheme = () => {
        const selected = editor.querySelector('[data-profile-theme]').value;
        themes.forEach(theme => editor.classList.toggle('profile-theme-'+theme, theme === selected));
    };
    editor.querySelector('[data-profile-theme]').addEventListener('change', setTheme);
    setTheme();
    editor.querySelector('[data-profile-name-input]').addEventListener('input', event => { name.textContent = event.target.value.trim() || originalName; });
    editor.querySelectorAll('[data-profile-upload]').forEach(input => {
        const type = input.dataset.profileUpload;
        const image = editor.querySelector(`[data-profile-preview="${type}"]`);
        const originalSrc = image.getAttribute('src');
        const remove = editor.querySelector(`[data-profile-remove="${type}"]`);
        const fallback = type === 'avatar' ? editor.querySelector('[data-profile-avatar-fallback]') : null;
        const show = src => {
            if (src) image.src = src;
            else image.removeAttribute('src');
            image.hidden = !src;
            if (fallback) fallback.hidden = Boolean(src);
        };
        input.addEventListener('change', () => {
            input.setCustomValidity('');
            const file = input.files[0];
            if (urls.has(type)) { URL.revokeObjectURL(urls.get(type)); urls.delete(type); }
            if (!file) { show(remove?.checked ? null : originalSrc); return; }
            const limit = 2 * 1024 * 1024;
            if (file.size > limit || (file.type && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) {
                input.setCustomValidity('Choose a JPEG, PNG or WebP image up to 2 MB.');
                input.reportValidity(); show(originalSrc); return;
            }
            if (remove) remove.checked = false;
            const url = URL.createObjectURL(file); urls.set(type, url); show(url);
            status.textContent = 'Preview updated. Select Save appearance to keep your changes.';
        });
        remove?.addEventListener('change', () => {
            if (remove.checked) { input.value = ''; input.setCustomValidity(''); if (urls.has(type)) { URL.revokeObjectURL(urls.get(type)); urls.delete(type); } }
            show(remove.checked ? null : (urls.get(type) || originalSrc));
            status.textContent = 'Preview updated. Select Save appearance to keep your changes.';
        });
    });
    window.addEventListener('pagehide', () => { urls.forEach(url => URL.revokeObjectURL(url)); urls.clear(); });
})();
