document.querySelectorAll('[data-hero-media]').forEach((media) => {
    const hero = media.parentElement;
    const videos = Array.from(media.querySelectorAll('[data-hero-video]'));
    const poster = media.querySelector('[data-hero-poster]');
    const controls = hero.querySelector('[data-hero-video-controls]');
    const toggle = controls.querySelector('[data-hero-video-toggle]');
    const next = controls.querySelector('[data-hero-video-next]');
    const status = controls.querySelector('[data-hero-video-status]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let visible = false;
    let paused = false;
    let leaving = false;
    let focusedWindow = true;
    let playVersion = 0;

    function allowed() {
        return visible && !paused && !leaving && focusedWindow && !document.hidden && !reducedMotion.matches;
    }

    function load(index) {
        const video = videos[index];
        if (!video.getAttribute('src')) {
            video.muted = true;
            video.preload = 'auto';
            video.src = video.dataset.src;
            video.load();
        }
        return video;
    }

    function label() {
        toggle.hidden = reducedMotion.matches;
        toggle.textContent = paused ? 'Play video' : 'Pause video';
    }

    async function sync() {
        const version = ++playVersion;
        label();
        if (!allowed()) {
            videos.forEach(video => video.pause());
            if (reducedMotion.matches) videos.forEach(video => video.classList.remove('is-active'));
            return;
        }
        const index = current;
        const video = load(index);
        try {
            await video.play();
            if (version !== playVersion) {
                if (!allowed() || index !== current) video.pause();
                return;
            }
            if (!allowed()) { video.pause(); return; }
            poster.src = video.dataset.poster;
            videos.forEach((item, itemIndex) => {
                item.classList.toggle('is-active', itemIndex === current);
                if (itemIndex !== current) item.pause();
            });
        } catch {
            if (version !== playVersion) return;
            // Autoplay or loading may be blocked; leave a usable poster and play button.
            paused = true;
            videos.forEach(item => item.pause());
            label();
        }
    }

    function advance(manual = false) {
        current = (current + 1) % videos.length;
        videos[current].currentTime = 0;
        if (manual || !allowed()) {
            poster.src = videos[current].dataset.poster;
            videos.forEach(video => video.classList.remove('is-active'));
        }
        if (manual) status.textContent = `Scene ${current + 1} of ${videos.length}`;
        sync();
    }

    videos.forEach((video, index) => {
        video.addEventListener('ended', () => { if (index === current && allowed()) advance(); });
        video.addEventListener('timeupdate', () => {
            if (index === current && allowed() && Number.isFinite(video.duration) && video.duration - video.currentTime <= 2) {
                load((current + 1) % videos.length);
            }
        });
    });
    toggle.addEventListener('click', () => { paused = !paused; sync(); });
    next.addEventListener('click', () => advance(true));
    reducedMotion.addEventListener('change', sync);
    document.addEventListener('visibilitychange', sync);
    window.addEventListener('blur', () => { focusedWindow = false; sync(); });
    window.addEventListener('focus', () => { focusedWindow = true; sync(); });
    window.addEventListener('pagehide', () => { leaving = true; sync(); });
    window.addEventListener('pageshow', () => { leaving = false; sync(); });
    if ('IntersectionObserver' in window) {
        new window.IntersectionObserver(entries => { visible = entries[0].isIntersecting; sync(); }, { threshold: 0.15 }).observe(hero);
    } else {
        const updateVisibility = () => {
            const rect = hero.getBoundingClientRect();
            visible = rect.bottom > 0 && rect.top < window.innerHeight;
            sync();
        };
        window.addEventListener('scroll', updateVisibility, { passive: true });
        window.addEventListener('resize', updateVisibility);
        updateVisibility();
    }
    controls.hidden = false;
    label();
});
