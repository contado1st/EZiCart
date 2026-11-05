(() => {
    'use strict';

    document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
        const slides = [...gallery.querySelectorAll('[data-gallery-slide]')];
        if (!slides.length) return;
        const thumbnails = [...gallery.querySelectorAll('[data-gallery-thumbnail]')];
        const status = gallery.querySelector('[data-gallery-status]');
        const track = gallery.querySelector('[data-gallery-track]');
        let index = 0;
        const show = (next, moveFocus = false) => {
            index = (next + slides.length) % slides.length;
            slides.forEach((slide, position) => {
                slide.hidden = position !== index;
                if (position !== index) slide.querySelectorAll('video').forEach((video) => video.pause());
            });
            thumbnails.forEach((button, position) => button.setAttribute('aria-pressed', String(position === index)));
            status.textContent = `${index + 1} / ${slides.length} · ${slides[index].dataset.kind === 'video' ? 'Video' : 'Photo'}`;
            if (moveFocus) thumbnails[index]?.focus({ preventScroll: true });
        };
        gallery.classList.add('is-enhanced');
        gallery.querySelector('[data-gallery-controls]').hidden = slides.length < 2;
        gallery.querySelector('[data-gallery-thumbnails]').hidden = slides.length < 2;
        gallery.querySelector('[data-gallery-previous]').addEventListener('click', () => show(index - 1));
        gallery.querySelector('[data-gallery-next]').addEventListener('click', () => show(index + 1));
        thumbnails.forEach((button, position) => button.addEventListener('click', () => show(position)));
        gallery.addEventListener('keydown', (event) => {
            if (event.target.closest('video') || event.altKey || event.ctrlKey || event.metaKey) return;
            const keys = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: slides.length - 1 };
            if (!(event.key in keys)) return;
            event.preventDefault();
            show(keys[event.key], !!event.target.closest('[data-gallery-thumbnail]'));
        });
        let start = null;
        track.addEventListener('pointerdown', (event) => {
            if (event.target.closest('video') || event.pointerType === 'mouse') return;
            start = { x: event.clientX, y: event.clientY };
        });
        track.addEventListener('pointerup', (event) => {
            if (!start) return;
            const x = event.clientX - start.x;
            const y = event.clientY - start.y;
            start = null;
            if (Math.abs(x) > 45 && Math.abs(x) > Math.abs(y)) show(index + (x < 0 ? 1 : -1));
        });
        track.addEventListener('pointercancel', () => { start = null; });
        window.addEventListener('pagehide', () => slides.forEach((slide) => slide.querySelectorAll('video').forEach((video) => video.pause())));
        show(0);
    });

    const bytes = (value) => {
        const number = parseFloat(value);
        if (!Number.isFinite(number) || number <= 0) return Infinity;
        return number * ({ k: 1024, m: 1024 ** 2, g: 1024 ** 3 }[String(value).trim().slice(-1).toLowerCase()] || 1);
    };
    const size = (value) => `${Math.round(value / 1024 / 1024 * 10) / 10} MB`;

    document.querySelectorAll('[data-product-media-editor]').forEach((editor) => {
        const form = editor.closest('form');
        const images = editor.querySelector('[data-media-upload="images"]');
        const video = editor.querySelector('[data-media-upload="video"]');
        const cover = form.querySelector('input[name="image"]');
        const removeCover = editor.querySelector('input[name="remove_cover"]');
        const message = editor.querySelector('[data-media-message]');
        const previews = editor.querySelector('[data-media-previews]');
        const inputs = [cover, images, video].filter(Boolean);
        let urls = [];

        const clearPreviews = () => {
            previews.querySelectorAll('video').forEach((item) => item.pause());
            previews.replaceChildren();
            urls.forEach((url) => URL.revokeObjectURL(url));
            urls = [];
        };
        const validate = () => {
            inputs.forEach((input) => input.setCustomValidity(''));
            message.textContent = '';
            const problems = [];
            const fail = (input, text) => { if (!input.validationMessage) input.setCustomValidity(text); problems.push(text); };
            let total = 0;
            for (const input of inputs) {
                const isVideo = input === video;
                const allowed = isVideo ? ['video/mp4', 'video/webm'] : ['image/jpeg', 'image/png', 'image/webp'];
                const limit = Math.min(Number(isVideo ? editor.dataset.videoMax : editor.dataset.imageMax), bytes(editor.dataset.serverMax));
                for (const file of input.files || []) {
                    total += file.size;
                    if (!allowed.includes(file.type)) fail(input, `${file.name}: choose ${isVideo ? 'an MP4 or WebM video' : 'a JPEG, PNG or WebP image'}.`);
                    if (file.size > limit) fail(input, `${file.name}: the current upload limit is ${size(limit)} per ${isVideo ? 'video' : 'image'}. Choose a smaller file.`);
                }
            }
            const removed = editor.querySelectorAll('[data-media-remove="image"]:checked').length;
            const promoted = editor.dataset.hasCover !== '1' && !cover?.files?.length && !removeCover?.checked && images.files.length ? 1 : 0;
            if (Number(editor.dataset.existingImages) - removed + images.files.length - promoted > Number(editor.dataset.imageLimit)) {
                fail(images, `Keep at most ${editor.dataset.imageLimit} gallery images. Select saved images to remove before adding more.`);
            }
            if (removeCover?.checked && cover?.files?.length) fail(cover, 'Choose either a replacement cover or removal of the current cover.');
            const postLimit = bytes(editor.dataset.postMax);
            if (total && total + 65536 > postLimit) fail(images, `The combined uploads exceed this server's ${size(postLimit)} request limit. Save fewer files at a time.`);
            message.textContent = [...new Set(problems)].join(' ');
            return problems.length === 0;
        };
        const renderPreviews = () => {
            clearPreviews();
            if (!validate()) return;
            inputs.forEach((input) => {
                [...input.files].forEach((file) => {
                    const url = URL.createObjectURL(file); urls.push(url);
                    const figure = document.createElement('figure');
                    const visual = document.createElement(input === video ? 'video' : 'img');
                    visual.src = url;
                    if (input === video) { visual.controls = true; visual.playsInline = true; visual.preload = 'metadata'; }
                    else visual.alt = 'New product image preview';
                    const caption = document.createElement('figcaption');
                    caption.textContent = `${input === cover ? 'Cover · ' : ''}${file.name}`;
                    figure.append(visual, caption); previews.append(figure);
                });
            });
        };
        inputs.forEach((input) => input.addEventListener('change', renderPreviews));
        editor.querySelectorAll('input[type="checkbox"]').forEach((input) => input.addEventListener('change', renderPreviews));
        form.addEventListener('submit', (event) => {
            if (!validate()) { event.preventDefault(); inputs.find((input) => !input.checkValidity())?.reportValidity(); }
        });
        window.addEventListener('pagehide', clearPreviews);
    });
})();
