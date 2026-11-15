document.querySelectorAll('[data-category-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-carousel-track]');
    const controls = carousel.querySelector('[data-carousel-controls]');
    const toggle = carousel.querySelector('[data-carousel-toggle]');
    const originals = Array.from(track.children);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pixelsPerSecond = 28;
    let copies = [];
    let cycleWidth = 0;
    let frame = null;
    let resumeTimer;
    let lastTime = null;
    let position = track.scrollLeft;
    let running = false;
    let overflowing = false;
    let paused = false;
    let hovered = false;
    let focused = false;
    let dragging = false;
    let visible = false;
    let leaving = false;

    function canPlay() {
        return !leaving && !paused && !hovered && !focused && !dragging && visible
            && !document.hidden && !reducedMotion.matches && overflowing && cycleWidth > 0;
    }

    function stop() {
        if (frame !== null) window.cancelAnimationFrame(frame);
        frame = null;
        running = false;
        lastTime = null;
    }

    function animate(time) {
        if (!canPlay()) { stop(); return; }
        if (lastTime !== null) {
            position = (position + pixelsPerSecond * Math.min(time - lastTime, 64) / 1000) % cycleWidth;
            track.scrollLeft = position;
        }
        lastTime = time;
        frame = window.requestAnimationFrame(animate);
    }

    function play() {
        window.clearTimeout(resumeTimer);
        if (!canPlay()) { stop(); return; }
        if (running) return;
        position = track.scrollLeft % cycleWidth;
        track.scrollLeft = position;
        running = true;
        frame = window.requestAnimationFrame(animate);
    }

    // This delay only waits for manual scrolling to settle; playback itself has no interval.
    function resumeWhenIdle() {
        window.clearTimeout(resumeTimer);
        stop();
        if (canPlay()) resumeTimer = window.setTimeout(play, 800);
    }

    function refresh() {
        stop();
        window.clearTimeout(resumeTimer);
        const previousCycle = cycleWidth;
        const previousPosition = previousCycle ? track.scrollLeft % previousCycle : track.scrollLeft;
        copies.forEach(copy => copy.remove());
        copies = [];
        cycleWidth = 0;
        overflowing = track.scrollWidth > track.clientWidth + 2;
        if (overflowing && !reducedMotion.matches && originals.length) {
            copies = originals.map(card => {
                const copy = card.cloneNode(true);
                copy.setAttribute('aria-hidden', 'true');
                copy.setAttribute('tabindex', '-1');
                copy.dataset.carouselCopy = '';
                track.append(copy);
                return copy;
            });
            cycleWidth = copies[0].offsetLeft - originals[0].offsetLeft;
        }
        track.scrollLeft = cycleWidth ? previousPosition % cycleWidth : previousPosition;
        controls.hidden = !overflowing;
        toggle.hidden = reducedMotion.matches;
        play();
    }

    function move(direction) {
        if (!overflowing || !originals.length) return;
        stop();
        const max = track.scrollWidth - track.clientWidth;
        const current = cycleWidth ? track.scrollLeft % cycleWidth : track.scrollLeft;
        track.scrollLeft = current;
        const positions = originals.map(card => card.offsetLeft - originals[0].offsetLeft);
        let target;
        if (direction > 0) {
            target = positions.find(value => value > current + 2) ?? (cycleWidth || 0);
            if (!cycleWidth) target = current >= max - 2 ? 0 : Math.min(max, target || max);
        } else if (current <= 2) {
            if (cycleWidth) track.scrollLeft = cycleWidth + current;
            target = cycleWidth ? positions[positions.length - 1] : max;
        } else {
            target = positions.filter(value => value < current - 2).pop() ?? 0;
        }
        track.scrollTo({ left: target, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
        resumeWhenIdle();
    }

    carousel.querySelector('[data-carousel-previous]').addEventListener('click', () => move(-1));
    carousel.querySelector('[data-carousel-next]').addEventListener('click', () => move(1));
    toggle.addEventListener('click', () => {
        paused = !paused;
        toggle.textContent = paused ? 'Resume rotation' : 'Pause rotation';
        play();
    });
    carousel.addEventListener('mouseenter', () => { hovered = true; play(); });
    carousel.addEventListener('mouseleave', () => { hovered = false; resumeWhenIdle(); });
    carousel.addEventListener('focusin', () => { focused = true; play(); });
    carousel.addEventListener('focusout', event => {
        focused = carousel.contains(event.relatedTarget);
        resumeWhenIdle();
    });
    track.addEventListener('pointerdown', () => { dragging = true; play(); });
    window.addEventListener('pointerup', () => { dragging = false; resumeWhenIdle(); });
    window.addEventListener('pointercancel', () => { dragging = false; resumeWhenIdle(); });
    window.addEventListener('blur', () => { dragging = false; visible = false; play(); });
    track.addEventListener('scroll', () => { if (!running) resumeWhenIdle(); }, { passive: true });
    document.addEventListener('visibilitychange', play);
    reducedMotion.addEventListener('change', refresh);
    window.addEventListener('pagehide', () => { leaving = true; play(); });
    window.addEventListener('pageshow', () => { leaving = false; refresh(); });

    const updateVisibility = () => {
        const rect = carousel.getBoundingClientRect();
        visible = rect.bottom > 0 && rect.top < window.innerHeight;
        play();
    };
    if ('IntersectionObserver' in window) {
        new window.IntersectionObserver(entries => {
            visible = entries[0].isIntersecting;
            play();
        }, { threshold: 0.25 }).observe(carousel);
    } else {
        window.addEventListener('scroll', updateVisibility, { passive: true });
        window.addEventListener('resize', updateVisibility);
        updateVisibility();
    }
    window.addEventListener('focus', updateVisibility);
    window.addEventListener('resize', refresh);
    if ('ResizeObserver' in window) new window.ResizeObserver(refresh).observe(track);
    refresh();
});
