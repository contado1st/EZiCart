(() => {
    const timers = [...document.querySelectorAll('[data-flash-timer]')].map(node => ({
        node, end: Date.parse(node.dataset.endsAt), offset: Date.parse(node.dataset.serverNow) - Date.now(),
    })).filter(timer => Number.isFinite(timer.end) && Number.isFinite(timer.offset));
    if (!timers.length) return;
    const update = () => {
        timers.forEach(timer => {
            const seconds = Math.max(0, Math.ceil((timer.end - Date.now() - timer.offset) / 1000));
            timer.node.textContent = seconds ? `${Math.floor(seconds / 3600)}h ${Math.floor(seconds % 3600 / 60)}m ${seconds % 60}s` : 'Ended · refresh for current offers';
        });
        if (timers.every(timer => timer.end <= Date.now() + timer.offset)) clearInterval(interval);
    };
    const interval = setInterval(update, 1000);
    update();
})();
