(() => {
    const timers = document.querySelectorAll('[data-order-deadline]');
    if (!timers.length) return;
    const update = () => timers.forEach(timer => {
        const remaining = Date.parse(timer.dataset.orderDeadline) - Date.now();
        if (!Number.isFinite(remaining)) return;
        if (remaining <= 0) { timer.textContent = 'Deadline reached — awaiting server processing'; return; }
        const minutes = Math.ceil(remaining / 60000);
        const hours = Math.floor(minutes / 60);
        timer.textContent = `${hours ? `${hours}h ` : ''}${minutes % 60}m remaining`;
    });
    update();
    window.setInterval(update, 30000);
})();
