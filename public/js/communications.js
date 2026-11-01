(() => {
    const form = document.querySelector('[data-message-composer]');
    if (!form) return;
    const input = form.querySelector('[data-message-input]');
    const count = form.querySelector('[data-message-count]');
    const button = form.querySelector('[data-message-send]');
    const update = () => { count.textContent = input.value.length.toLocaleString(); };
    input.addEventListener('input', update);
    input.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); form.requestSubmit(); }
    });
    form.addEventListener('submit', event => {
        if (!input.value.trim()) { event.preventDefault(); input.setCustomValidity('Enter a message before sending.'); input.reportValidity(); return; }
        button.disabled = true;
        button.textContent = 'Sending…';
    });
    input.addEventListener('input', () => input.setCustomValidity(''));
    window.addEventListener('pageshow', () => { button.disabled = false; button.textContent = 'Send message'; });
    update();
    const history = document.querySelector('[data-conversation-history]');
    if (history && !new URL(window.location.href).searchParams.has('messages_page')) history.scrollTop = history.scrollHeight;
})();
